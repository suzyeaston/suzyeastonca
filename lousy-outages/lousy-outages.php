<?php
declare( strict_types=1 );
/**
 * Plugin Name: Lousy Outages
 * Description: Aggregates service status and sends SMS/email alerts on incidents.
 * Version: 0.1.0
 * Author: Suzy Easton
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// LO: allow hard shutdown via constant for emergency maintenance.
if ( defined( 'LOUSY_OUTAGES_DISABLE' ) && LOUSY_OUTAGES_DISABLE ) {
    error_log( '[LO] disabled via constant' );
    return;
}

define( 'LOUSY_OUTAGES_PATH', plugin_dir_path( __FILE__ ) );

require_once LOUSY_OUTAGES_PATH . 'includes/Providers.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Fetch.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Adapters.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Adapters/Statuspage.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Store.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Trending.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Fetcher.php';
require_once LOUSY_OUTAGES_PATH . 'includes/I18n.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Detector.php';
require_once LOUSY_OUTAGES_PATH . 'includes/SMS.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Mailer.php';
require_once LOUSY_OUTAGES_PATH . 'includes/MailTransport.php';
require_once LOUSY_OUTAGES_PATH . 'includes/email-templates.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Email.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Precursor.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Subscriptions.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Subscribe.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Api.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Feeds.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Summary.php';
require_once LOUSY_OUTAGES_PATH . 'includes/IncidentAlerts.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Snapshot.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Cron.php';
require_once LOUSY_OUTAGES_PATH . 'includes/compat.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Sources/StatuspageSource.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Model/Incident.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Storage/IncidentStore.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Email/Composer.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Sources/Sources.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Sources/index.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Cron/Refresh.php';
require_once LOUSY_OUTAGES_PATH . 'public/shortcode.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once LOUSY_OUTAGES_PATH . 'wp-cli/class-wp-cli-lousy.php';
}

use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\Store;
use SuzyEaston\LousyOutages\Fetcher;
use SuzyEaston\LousyOutages\Detector;
use SuzyEaston\LousyOutages\SMS;
use SuzyEaston\LousyOutages\Email;
use SuzyEaston\LousyOutages\Precursor;
use SuzyEaston\LousyOutages\Subscriptions;
use SuzyEaston\LousyOutages\Api;
use SuzyEaston\LousyOutages\Feeds;
use SuzyEaston\LousyOutages\MailTransport;
use SuzyEaston\LousyOutages\IncidentAlerts;
use SuzyEaston\LousyOutages\Cron\Refresh as RefreshCron;

Api::bootstrap();
// LO: Legacy feed slug removed to avoid clashing with the dashboard page; status feed now handled below.
Feeds::bootstrap();
MailTransport::bootstrap();
IncidentAlerts::bootstrap();
RefreshCron::bootstrap();

lo_snapshot_bootstrap();
lo_cron_bootstrap();

add_action('lousy_outages_purge_pending', [Subscriptions::class, 'purge_stale_pending']);

add_action(
    'lousy_outages_log',
    static function ( string $event, $data ): void {
        error_log( '[lousy_outages] ' . $event . ' ' . wp_json_encode( $data ) );
    },
    10,
    2
);

/**
 * Schedule polling event on activation and create page.
 */
function lousy_outages_activate() {
    if ( ! wp_next_scheduled( 'lousy_outages_poll' ) ) {
        $interval = (int) get_option( 'lousy_outages_interval', 300 );
        wp_schedule_event( time() + 60, 'lousy_outages_interval', 'lousy_outages_poll' );
    }
    if ( ! wp_next_scheduled( 'lousy_outages_cron_refresh' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'lousy_outages_15min', 'lousy_outages_cron_refresh' );
    }
    if ( ! wp_next_scheduled( 'lo_check_statuses' ) ) {
        wp_schedule_event( time() + 60, 'lo_five_minutes', 'lo_check_statuses' );
    }
    if ( function_exists( 'lo_cron_activate' ) ) {
        lo_cron_activate();
    }
    lousy_outages_create_page();
    Subscriptions::create_table();
    Subscriptions::schedule_purge();
    $default_email = 'suzanneeaston@gmail.com';
    $stored_email  = get_option( 'lousy_outages_email' );
    if ( empty( $stored_email ) && is_email( $default_email ) ) {
        update_option( 'lousy_outages_email', sanitize_email( $default_email ) );
    }
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules( false );
    }
}
register_activation_hook( __FILE__, 'lousy_outages_activate' );

/**
 * Clear cron on deactivation.
 */
function lousy_outages_deactivate() {
    wp_clear_scheduled_hook( 'lousy_outages_poll' );
    wp_clear_scheduled_hook( 'lousy_outages_cron_refresh' );
    wp_clear_scheduled_hook( 'lo_check_statuses' );
    Subscriptions::clear_schedule();
    if ( function_exists( 'lo_cron_deactivate' ) ) {
        lo_cron_deactivate();
    }
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules( false );
    }
}
register_deactivation_hook( __FILE__, 'lousy_outages_deactivate' );

/**
 * Add custom interval for cron based on setting.
 */
add_filter( 'cron_schedules', function ( $schedules ) {
    $interval                      = (int) get_option( 'lousy_outages_interval', 300 );
    $schedules['lousy_outages_interval'] = [
        'interval' => max( 60, $interval ),
        'display'  => 'Lousy Outages Interval',
    ];
    $schedules['lo_five_minutes'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => 'Lousy Outages – 5 minutes',
    ];
    return $schedules;
} );

/**
 * Poll providers and handle transitions.
 */
add_action( 'lousy_outages_poll', 'lousy_outages_run_poll' );
function lousy_outages_run_poll(): int {
    $store     = new Store();
    $detector  = new Detector( $store );
    $sms       = new SMS();
    $email     = new Email();
    $statuses  = lousy_outages_collect_statuses( true );
    $processed = 0;
    $threshold = (int) get_option( 'lousy_outages_prealert_threshold', 60 );
    if ( $threshold < 0 ) {
        $threshold = 0;
    }
    if ( $threshold > 100 ) {
        $threshold = 100;
    }
    $email_prealerts = ! empty( get_option( 'lousy_outages_prealert_email', '1' ) );
    $sms_prealerts   = ! empty( get_option( 'lousy_outages_prealert_sms', '1' ) );
    $prealert_last   = get_option( 'lousy_outages_prealert_last', [] );
    if ( ! is_array( $prealert_last ) ) {
        $prealert_last = [];
    }
    $prealert_updated = false;

    foreach ( $statuses as $id => $state ) {
        $transition = $detector->detect( $id, $state );
        $store->update( $id, $state );
        $processed++;
        $prealert = ( isset( $state['prealert'] ) && is_array( $state['prealert'] ) ) ? $state['prealert'] : [];
        $risk     = isset( $prealert['risk'] ) ? (int) $prealert['risk'] : ( isset( $state['risk'] ) ? (int) $state['risk'] : 0 );

        if ( $transition ) {
            if ( in_array( $transition['new'], [ 'degraded', 'outage' ], true ) ) {
                $already_unhealthy = in_array( $transition['old'], [ 'degraded', 'outage' ], true );
                if ( ! $already_unhealthy ) {
                    $sms->send_alert( $state['name'], $transition['new'], $state['message'], $state['url'] );
                    $email->send_alert( $state['name'], $transition['new'], $state['message'], $state['url'] );
                }
            } elseif ( 'operational' === $transition['new'] && in_array( $transition['old'], [ 'degraded', 'outage' ], true ) ) {
                $sms->send_recovery( $state['name'], $state['url'] );
                $email->send_recovery( $state['name'], $state['url'] );
            }
        } else {
            $status_code = strtolower( (string) ( $state['status'] ?? '' ) );
            if ( 'operational' === $status_code && $risk >= $threshold && $risk > 0 && ( $email_prealerts || $sms_prealerts ) ) {
                $last_sent = isset( $prealert_last[ $id ] ) ? (int) $prealert_last[ $id ] : 0;
                $elapsed   = time() - $last_sent;
                if ( $last_sent <= 0 || $elapsed >= 3600 ) {
                    $summary = ! empty( $prealert['summary'] ) ? $prealert['summary'] : 'Early warning detected';
                    if ( $email_prealerts ) {
                        $email->send_alert( $state['name'], 'early-warning', $summary, $state['url'] );
                    }
                    if ( $sms_prealerts ) {
                        $sms->send_alert( $state['name'], 'early-warning', $summary, $state['url'] );
                    }
                    if ( $email_prealerts || $sms_prealerts ) {
                        $prealert_last[ $id ] = time();
                        $prealert_updated      = true;
                    }
                }
            }
        }
    }

    if ( $prealert_updated ) {
        update_option( 'lousy_outages_prealert_last', $prealert_last, false );
    }

    // Use a UTC epoch to avoid applying the site timezone offset twice when formatting
    // with wp_date(), which already handles the local conversion.
    $timestamp_epoch = current_time( 'timestamp', true );
    $timestamp       = wp_date( 'c', $timestamp_epoch );
    $timestamp_gmt   = gmdate( 'c', $timestamp_epoch );
    update_option( 'lousy_outages_last_poll', $timestamp, false );
    update_option( 'lousy_outages_last_fetched', $timestamp_epoch, false );
    update_option( 'lousy_outages_last_fetched_iso', $timestamp_gmt, false );
    lousy_outages_refresh_snapshot( $statuses, $timestamp );
    do_action( 'lousy_outages_log', 'poll_complete', [ 'count' => $processed, 'ts' => $timestamp ] );

    return $processed;
}

function lousy_outages_collect_statuses( bool $bypass_cache = false ): array {
    $cache_key = 'lousy_outages_cached_statuses';
    if ( ! $bypass_cache ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            error_log( '[lousy_outages] fetch_cache_hit count=' . count( $cached ) . ' ts=' . gmdate( 'c' ) );
            return $cached;
        }
    }

    $start_time = microtime( true );
    $timeout   = (int) apply_filters( 'lousy_outages_fetch_timeout', 10 );
    $fetcher   = new Fetcher( $timeout );
    $providers = Providers::enabled();
    $results   = [];
    $precursor = new Precursor();
    $failures  = [];

    foreach ( $providers as $id => $provider ) {
        if ( ! isset( $provider['id'] ) ) {
            $provider['id'] = $id;
        }
        try {
            $state = $fetcher->fetch( $provider );
        } catch ( \Throwable $e ) {
            error_log( '[LO] fetch failed: ' . $e->getMessage() );
            $state = [
                'status'      => 'unknown',
                'summary'     => 'Status unavailable',
                'error'       => 'exception:' . $e->getMessage(),
                'incidents'   => [],
                'url'         => $provider['status_url'] ?? '',
                'updated_at'  => gmdate( 'c' ),
                'name'        => $provider['name'] ?? $provider['id'],
                'provider'    => $provider['name'] ?? $provider['id'],
                'source_type' => $provider['type'] ?? '',
            ];
        }
        $pre   = $precursor->evaluate( $provider, $state );
        $state['risk']     = $pre['risk'];
        $state['prealert'] = $pre;
        $results[ $id ]    = $state;

        if ( is_array( $state ) ) {
            $error_message = '';
            if ( ! empty( $state['error'] ) ) {
                $error_message = (string) $state['error'];
            } elseif ( isset( $state['message'] ) && '' !== trim( (string) $state['message'] ) ) {
                $error_message = (string) $state['message'];
            }
            if ( '' !== $error_message ) {
                $failures[ $id ] = $error_message;
            }
        }
    }

    $ttl     = (int) apply_filters( 'lousy_outages_cache_ttl', 90 );
    $payload = lousy_outages_make_serializable( $results );
    set_transient( $cache_key, $payload, max( 30, $ttl ) );

    $duration_ms = ( microtime( true ) - $start_time ) * 1000;
    error_log( sprintf( '[lousy_outages] fetch_complete count=%d duration_ms=%.2f failures=%d', count( $payload ), $duration_ms, count( $failures ) ) );
    foreach ( $failures as $provider_id => $message ) {
        error_log( sprintf( '[lousy_outages] fetch_failure provider=%s message=%s', $provider_id, $message ) );
    }

    return $payload;
}

function lousy_outages_get_last_fetched_timestamp(): ?int {
    $stored = get_option( 'lousy_outages_last_fetched' );
    if ( is_numeric( $stored ) ) {
        return (int) $stored;
    }

    if ( is_string( $stored ) && '' !== trim( $stored ) ) {
        $parsed = strtotime( $stored );
        if ( false !== $parsed ) {
            return $parsed;
        }
    }

    $last_poll = get_option( 'lousy_outages_last_poll' );
    if ( is_string( $last_poll ) && '' !== trim( $last_poll ) ) {
        $parsed = strtotime( $last_poll );
        if ( false !== $parsed ) {
            return $parsed;
        }
    }

    return null;
}

/**
 * Return the canonical "last fetched" timestamp in ISO8601 using the site timezone.
 *
 * The canonical value is stored in the `lousy_outages_last_fetched` option as an epoch
 * integer to avoid timezone drift; this helper applies wp_date() for display.
 */
function lousy_outages_get_last_fetched_iso(): ?string {
    $ts = lousy_outages_get_last_fetched_timestamp();
    if ( ! $ts ) {
        return null;
    }

    return wp_date( 'c', $ts );
}

/**
 * Refresh provider data and snapshot caches.
 *
 * The canonical "Last fetched" timestamp is stored as an epoch in the
 * `lousy_outages_last_fetched` option. The snapshot endpoint and HUD both
 * render their date/time displays from this value via wp_date().
 */
function lousy_outages_refresh_data( bool $bypass_cache = true ): array {
    $lock_key = 'lousy_outages_refresh_lock';
    if ( get_transient( $lock_key ) ) {
        return [
            'ok'      => false,
            'skipped' => true,
            'message' => 'Refresh already in progress',
        ];
    }

    set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

    $response = [
        'ok'           => false,
        'providers'    => [],
        'errors'       => [],
        'trending'     => [ 'trending' => false, 'signals' => [] ],
        'source'       => 'live',
        'refreshedAt'  => null,
        'refreshed_at' => null,
    ];

    try {
        // Use UTC to avoid double-applying the site timezone offset when formatting below.
        $timestamp_epoch = current_time( 'timestamp', true );
        $timestamp_iso   = wp_date( 'c', $timestamp_epoch );
        $timestamp_gmt   = gmdate( 'c', $timestamp_epoch );
        $store        = new Store();
        $states       = lousy_outages_collect_statuses( $bypass_cache );
        $errors       = [];

        foreach ( $states as $id => $state ) {
            $store->update( $id, $state );
            if ( is_array( $state ) && ! empty( $state['error'] ) ) {
                $errors[] = [
                    'id'       => $id,
                    'provider' => isset( $state['name'] ) ? (string) $state['name'] : ( isset( $state['provider'] ) ? (string) $state['provider'] : $id ),
                    'message'  => (string) $state['error'],
                ];
            }
        }

        $snapshot  = lousy_outages_refresh_snapshot( $states, $timestamp_iso, 'live' );
        $providers = isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] ) ? $snapshot['providers'] : [];
        $trending  = isset( $snapshot['trending'] ) && is_array( $snapshot['trending'] ) ? $snapshot['trending'] : [ 'trending' => false, 'signals' => [] ];

        if ( empty( $errors ) && isset( $snapshot['errors'] ) && is_array( $snapshot['errors'] ) ) {
            $errors = $snapshot['errors'];
        }

        update_option( 'lousy_outages_last_poll', $timestamp_iso, false );
        update_option( 'lousy_outages_last_fetched', $timestamp_epoch, false );
        update_option( 'lousy_outages_last_fetched_iso', $timestamp_gmt, false );
        do_action( 'lousy_outages_log', 'refresh_complete', [
            'count' => count( $states ),
            'ts'    => $timestamp_iso,
        ] );

        $response = [
            'ok'           => true,
            'providers'    => $providers,
            'errors'       => $errors,
            'trending'     => $trending,
            'source'       => 'live',
            'refreshedAt'  => $timestamp_iso,
            'refreshed_at' => $timestamp_epoch,
        ];
    } catch ( \Throwable $e ) {
        error_log( '[LO] refresh failed: ' . $e->getMessage() );
        $response['message'] = $e->getMessage();
    } finally {
        delete_transient( $lock_key );
    }

    return $response;
}

function lousy_outages_filter_states_to_enabled( array $states ): array {
    if ( empty( $states ) ) {
        return [];
    }

    $enabled = Providers::enabled();
    if ( ! is_array( $enabled ) || empty( $enabled ) ) {
        return [];
    }

    $allowed = array_fill_keys( array_keys( $enabled ), true );

    return array_intersect_key( $states, $allowed );
}

function lousy_outages_filter_snapshot( array $snapshot ): array {
    $providers = isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] )
        ? $snapshot['providers']
        : [];

    if ( empty( $providers ) ) {
        $snapshot['providers'] = [];
        $snapshot['trending']  = ( new \SuzyEaston\LousyOutages\Trending() )->evaluate( [] );
        return $snapshot;
    }

    $enabled = Providers::enabled();
    if ( ! is_array( $enabled ) || empty( $enabled ) ) {
        $snapshot['providers'] = [];
        $snapshot['trending']  = ( new \SuzyEaston\LousyOutages\Trending() )->evaluate( [] );
        return $snapshot;
    }

    $allowed = array_fill_keys( array_keys( $enabled ), true );

    $snapshot['providers'] = array_values(
        array_filter(
            $providers,
            static function ( $provider ) use ( $allowed ): bool {
                if ( ! is_array( $provider ) ) {
                    return false;
                }

                $id = isset( $provider['id'] ) ? (string) $provider['id'] : '';

                if ( '' === $id ) {
                    return false;
                }

                return isset( $allowed[ $id ] );
            }
        )
    );

    $snapshot['trending'] = ( new \SuzyEaston\LousyOutages\Trending() )->evaluate( $snapshot['providers'] );

    return $snapshot;
}

function lousy_outages_snapshot_cache_key(): string {
    return 'lousy_status_snapshot';
}

function lousy_outages_build_snapshot( array $states, string $timestamp, string $source = 'snapshot' ): array {
    $states = lousy_outages_filter_states_to_enabled( $states );

    $providers = [];
    $errors    = [];

    foreach ( $states as $id => $state ) {
        $providers[] = lousy_outages_build_provider_payload( $id, $state, $timestamp );

        if ( is_array( $state ) && ! empty( $state['error'] ) ) {
            $provider_name = isset( $state['name'] ) ? (string) $state['name'] : ( isset( $state['provider'] ) ? (string) $state['provider'] : $id );
            $errors[]      = [
                'id'       => $id,
                'provider' => $provider_name,
                'message'  => (string) $state['error'],
            ];
        }
    }

    $providers = lousy_outages_sort_providers( $providers );
    $trending  = ( new \SuzyEaston\LousyOutages\Trending() )->evaluate( $providers );

    return lousy_outages_filter_snapshot( [
        'providers'  => $providers,
        'fetched_at' => $timestamp,
        'trending'   => $trending,
        'errors'     => $errors,
        'source'     => $source,
    ] );
}

function lousy_outages_store_snapshot( array $snapshot ): void {
    $cache_key = lousy_outages_snapshot_cache_key();
    $ttl       = (int) apply_filters( 'lousy_outages_snapshot_ttl', 5 * MINUTE_IN_SECONDS );
    set_transient( $cache_key, $snapshot, max( 60, $ttl ) );
    update_option( 'lousy_outages_snapshot', $snapshot, false );
}

function lousy_outages_refresh_snapshot( array $states, string $timestamp, string $source = 'snapshot' ): array {
    $snapshot = lousy_outages_build_snapshot( $states, $timestamp, $source );
    lousy_outages_store_snapshot( $snapshot );

    if ( function_exists( 'lo_snapshot_store' ) && function_exists( 'lo_snapshot_normalize_service' ) ) {
        $services = [];
        if ( isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] ) ) {
            foreach ( $snapshot['providers'] as $provider ) {
                if ( ! is_array( $provider ) ) {
                    continue;
                }
                $services[] = lo_snapshot_normalize_service( $provider, $timestamp );
            }
        }

        lo_snapshot_store(
            [
                'updated_at' => $timestamp,
                'services'   => $services,
            ]
        );
    }

    return $snapshot;
}

function lousy_outages_get_snapshot( bool $force_refresh = false ): array {
    $cache_key = lousy_outages_snapshot_cache_key();
    if ( ! $force_refresh ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['providers'] ) ) {
            return lousy_outages_filter_snapshot( $cached );
        }
    }

    $stored = get_option( 'lousy_outages_snapshot', [] );
    if ( ! $force_refresh && is_array( $stored ) && ! empty( $stored['providers'] ) ) {
        $filtered = lousy_outages_filter_snapshot( $stored );
        lousy_outages_store_snapshot( $filtered );
        return $filtered;
    }

    $store  = new Store();
    $states = $store->get_all();
    if ( empty( $states ) ) {
        $states = lousy_outages_collect_statuses( $force_refresh );
    }

    $timestamp = get_option( 'lousy_outages_last_poll' );
    if ( ! $timestamp ) {
        $timestamp = gmdate( 'c' );
    }

    return lousy_outages_refresh_snapshot( $states, $timestamp );
}

/**
 * Ensure cached payload contains only serializable values.
 *
 * WordPress calls serialize() on transient payloads. If any provider response
 * includes objects that implement __sleep() incorrectly (for example
 * SimpleXMLElement instances coming from poorly formed RSS feeds) the
 * serialization step fatals. To guard against that we recursively normalise
 * provider results into arrays, scalars, or null.
 */
function lousy_outages_make_serializable( $value ) {
    if ( is_array( $value ) ) {
        $clean = [];
        foreach ( $value as $key => $item ) {
            $clean[ $key ] = lousy_outages_make_serializable( $item );
        }
        return $clean;
    }

    if ( $value instanceof \DateTimeInterface ) {
        return gmdate( 'c', (int) $value->getTimestamp() );
    }

    if ( $value instanceof \JsonSerializable ) {
        return lousy_outages_make_serializable( $value->jsonSerialize() );
    }

    if ( $value instanceof \SimpleXMLElement ) {
        return lousy_outages_make_serializable( json_decode( wp_json_encode( $value ), true ) );
    }

    if ( is_object( $value ) ) {
        if ( method_exists( $value, '__toString' ) ) {
            return (string) $value;
        }

        $vars = get_object_vars( $value );
        if ( ! empty( $vars ) ) {
            return lousy_outages_make_serializable( $vars );
        }

        return null;
    }

    if ( is_resource( $value ) ) {
        return null;
    }

    return $value;
}

function lousy_outages_get_poll_interval(): int {
    $env = getenv( 'OUTAGES_POLL_MS' );
    if ( ! $env ) {
        $legacy = getenv( 'LOUSY_OUTAGES_POLL_INTERVAL' );
        $env    = $legacy ?: null;
    }

    if ( $env ) {
        $interval = (int) $env;
    } else {
        $configured = (int) get_option( 'lousy_outages_interval', 300 );
        $interval   = $configured * 1000;
    }

    if ( $interval <= 0 ) {
        $interval = 300000;
    }

    $interval = max( 60000, $interval );

    /**
     * Filter the polling interval in milliseconds for the public dashboard.
     */
    return (int) apply_filters( 'lousy_outages_poll_interval', $interval );
}

/**
 * Create public page if missing.
 */
function lousy_outages_create_page() {
    $page = get_page_by_path( 'lousy-outages' );
    if ( ! $page ) {
        wp_insert_post( [
            'post_title'   => 'Lousy Outages',
            'post_name'    => 'lousy-outages',
            'post_content' => '[lousy_outages]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
    }
}

/**
 * Settings page.
 */
function lousy_outages_sanitize_prealert_threshold( $value ) {
    $value = is_numeric( $value ) ? (int) $value : 0;
    if ( $value < 0 ) {
        $value = 0;
    }
    if ( $value > 100 ) {
        $value = 100;
    }
    return $value;
}

function lousy_outages_sanitize_checkbox( $value ) {
    return empty( $value ) ? '0' : '1';
}

function lousy_outages_sanitize_probes_option( $value ) {
    $output = [];

    if ( is_string( $value ) ) {
        $lines = preg_split( '/\r\n|\r|\n/', $value );
        if ( is_array( $lines ) ) {
            foreach ( $lines as $line ) {
                $line = trim( (string) $line );
                if ( '' === $line ) {
                    continue;
                }
                $parts = explode( '|', $line, 2 );
                $id    = isset( $parts[0] ) ? sanitize_key( trim( (string) $parts[0] ) ) : '';
                $url   = isset( $parts[1] ) ? esc_url_raw( trim( (string) $parts[1] ) ) : '';
                if ( '' === $id || '' === $url ) {
                    continue;
                }
                if ( ! isset( $output[ $id ] ) ) {
                    $output[ $id ] = [];
                }
                if ( ! in_array( $url, $output[ $id ], true ) ) {
                    $output[ $id ][] = $url;
                }
            }
        }
        return $output;
    }

    if ( is_array( $value ) ) {
        foreach ( $value as $id => $urls ) {
            $key = sanitize_key( (string) $id );
            if ( '' === $key ) {
                continue;
            }
            $urls = is_array( $urls ) ? $urls : [ $urls ];
            foreach ( $urls as $url ) {
                if ( ! is_string( $url ) ) {
                    continue;
                }
                $url = esc_url_raw( trim( $url ) );
                if ( '' === $url ) {
                    continue;
                }
                if ( ! isset( $output[ $key ] ) ) {
                    $output[ $key ] = [];
                }
                if ( ! in_array( $url, $output[ $key ], true ) ) {
                    $output[ $key ][] = $url;
                }
            }
        }
    }

    return $output;
}

add_action( 'admin_menu', function () {
    add_options_page( 'Lousy Outages', 'Lousy Outages', 'manage_options', 'lousy-outages', 'lousy_outages_settings_page' );
} );

add_action( 'admin_init', function () {
    register_setting( 'lousy_outages', 'lousy_outages_twilio_sid' );
    register_setting( 'lousy_outages', 'lousy_outages_twilio_token' );
    register_setting( 'lousy_outages', 'lousy_outages_twilio_from' );
    register_setting( 'lousy_outages', 'lousy_outages_phone' );
    register_setting( 'lousy_outages', 'lousy_outages_email' );
    register_setting( 'lousy_outages', 'lousy_outages_interval' );
    register_setting( 'lousy_outages', 'lousy_outages_providers' );
    register_setting(
        'lousy_outages',
        'lousy_outages_prealert_threshold',
        [
            'sanitize_callback' => 'lousy_outages_sanitize_prealert_threshold',
            'default'           => 60,
        ]
    );
    register_setting(
        'lousy_outages',
        'lousy_outages_prealert_email',
        [
            'sanitize_callback' => 'lousy_outages_sanitize_checkbox',
            'default'           => '1',
        ]
    );
    register_setting(
        'lousy_outages',
        'lousy_outages_prealert_sms',
        [
            'sanitize_callback' => 'lousy_outages_sanitize_checkbox',
            'default'           => '1',
        ]
    );
    register_setting(
        'lousy_outages',
        'lousy_outages_probes',
        [
            'sanitize_callback' => 'lousy_outages_sanitize_probes_option',
            'default'           => [],
        ]
    );
} );

function lousy_outages_settings_page() {
    $providers       = Providers::list();
    $default_enabled = array_keys( array_filter( $providers, static fn( $prov ) => $prov['enabled'] ?? true ) );
    $enabled         = get_option( 'lousy_outages_providers', $default_enabled );
    $interval        = get_option( 'lousy_outages_interval', 300 );
    $store           = new Store();
    $states          = $store->get_all();
    $notice          = get_transient( 'lousy_outages_notice' );
    $threshold       = (int) get_option( 'lousy_outages_prealert_threshold', 60 );
    if ( $threshold < 0 ) {
        $threshold = 0;
    }
    if ( $threshold > 100 ) {
        $threshold = 100;
    }
    $prealert_email = get_option( 'lousy_outages_prealert_email', '1' );
    $prealert_sms   = get_option( 'lousy_outages_prealert_sms', '1' );
    $probes_config  = get_option( 'lousy_outages_probes', [] );
    if ( ! is_array( $probes_config ) ) {
        $probes_config = [];
    }
    $probe_lines = [];
    foreach ( $probes_config as $prov_id => $urls ) {
        $urls = is_array( $urls ) ? $urls : [ $urls ];
        foreach ( $urls as $url ) {
            if ( ! is_string( $url ) ) {
                continue;
            }
            $url = trim( $url );
            if ( '' === $url ) {
                continue;
            }
            $probe_lines[] = sanitize_key( (string) $prov_id ) . '|' . $url;
        }
    }
    $probes_text = implode( "\n", $probe_lines );
    if ( $notice ) {
        $type = ! empty( $notice['type'] ) && 'error' === $notice['type'] ? 'notice-error' : 'notice-success';
        printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $type ), esc_html( (string) $notice['message'] ) );
        delete_transient( 'lousy_outages_notice' );
    }

    $format_datetime = static function ( ?string $iso ): string {
        if ( empty( $iso ) ) {
            return '—';
        }
        $timestamp = strtotime( $iso );
        if ( ! $timestamp ) {
            return '—';
        }
        $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        return wp_date( $format, $timestamp );
    };

    $last_email      = get_option( 'lousy_outages_last_email' );
    $last_email_text = 'No email sent yet.';
    if ( is_array( $last_email ) ) {
        $status = ! empty( $last_email['ok'] ) ? 'Delivered' : 'Failed';
        $parts  = [
            $format_datetime( $last_email['ts'] ?? null ),
            $status,
        ];
        if ( ! empty( $last_email['to'] ) ) {
            $parts[] = 'to ' . $last_email['to'];
        }
        if ( ! empty( $last_email['subject'] ) ) {
            $parts[] = $last_email['subject'];
        }
        $last_email_text = implode( ' — ', array_filter( $parts, static fn ( $part ) => '' !== (string) $part ) );
    }

    $last_poll      = get_option( 'lousy_outages_last_poll' );
    $last_poll_text = $format_datetime( $last_poll ?: null );
    ?>
    <div class="wrap">
        <h1>Lousy Outages Settings</h1>
        <?php settings_errors( 'lousy_outages' ); ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'lousy_outages' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="lo_sid">Twilio SID</label></th>
                    <td><input id="lo_sid" type="text" name="lousy_outages_twilio_sid" value="<?php echo esc_attr( get_option( 'lousy_outages_twilio_sid' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_token">Twilio Auth Token</label></th>
                    <td><input id="lo_token" type="text" name="lousy_outages_twilio_token" value="<?php echo esc_attr( get_option( 'lousy_outages_twilio_token' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_from">Twilio From Number</label></th>
                    <td><input id="lo_from" type="text" name="lousy_outages_twilio_from" value="<?php echo esc_attr( get_option( 'lousy_outages_twilio_from' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_phone">Destination Phone</label></th>
                    <td><input id="lo_phone" type="text" name="lousy_outages_phone" value="<?php echo esc_attr( get_option( 'lousy_outages_phone' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_email">Notification Email</label></th>
                    <td><input id="lo_email" type="email" name="lousy_outages_email" value="<?php echo esc_attr( get_option( 'lousy_outages_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_interval">Poll Interval (seconds)</label></th>
                    <td><input id="lo_interval" type="number" min="60" name="lousy_outages_interval" value="<?php echo esc_attr( $interval ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Providers</th>
                    <td>
                        <?php foreach ( $providers as $id => $prov ) :
                            $enabled_default = $prov['enabled'] ?? true;
                            $is_enabled      = in_array( $id, (array) $enabled, true );
                            ?>
                            <label>
                                <input type="checkbox" name="lousy_outages_providers[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $is_enabled ); ?>>
                                <?php echo esc_html( $prov['name'] ); ?><?php if ( ! $enabled_default ) : ?> <span class="description">(optional)</span><?php endif; ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_prealert_threshold">Early-warning threshold</label></th>
                    <td>
                        <input id="lo_prealert_threshold" type="number" min="0" max="100" name="lousy_outages_prealert_threshold" value="<?php echo esc_attr( $threshold ); ?>">
                        <p class="description">Send early-warning notifications when the risk score meets or exceeds this value.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Pre-alert notifications</th>
                    <td>
                        <input type="hidden" name="lousy_outages_prealert_email" value="0">
                        <label><input type="checkbox" name="lousy_outages_prealert_email" value="1" <?php checked( ! empty( $prealert_email ) ); ?>> Email</label><br>
                        <input type="hidden" name="lousy_outages_prealert_sms" value="0">
                        <label><input type="checkbox" name="lousy_outages_prealert_sms" value="1" <?php checked( ! empty( $prealert_sms ) ); ?>> SMS</label>
                        <p class="description">Control whether early-warning emails and SMS messages are sent.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="lo_probes">Probe URLs</label></th>
                    <td>
                        <textarea id="lo_probes" name="lousy_outages_probes" rows="5" cols="60"><?php echo esc_textarea( $probes_text ); ?></textarea>
                        <p class="description">One per line: provider_id|https://probe.example.com/ping. Leave blank for defaults.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Last email sent</th>
                    <td>
                        <p><?php echo esc_html( $last_email_text ); ?></p>
                        <p class="description">Use the test button below to verify delivery to <?php echo esc_html( get_option( 'lousy_outages_email', get_option( 'admin_email' ) ) ); ?>.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 1rem;">
            <?php wp_nonce_field( 'lousy_outages_test_email' ); ?>
            <input type="hidden" name="action" value="lousy_outages_test_email">
            <?php submit_button( 'Send Test Email', 'secondary' ); ?>
        </form>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 0.5rem;">
            <?php wp_nonce_field( 'lousy_outages_test_sms' ); ?>
            <input type="hidden" name="action" value="lousy_outages_test_sms">
            <?php submit_button( 'Send Test SMS', 'secondary' ); ?>
        </form>

        <p class="description">SMS requires Twilio SID/Auth/From and your phone set. Alternatively subscribe to RSS on your phone.</p>

        <h2>Debug panel</h2>
        <p><strong>Last poll:</strong> <?php echo esc_html( $last_poll_text ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 1rem;">
            <?php wp_nonce_field( 'lousy_outages_poll_now' ); ?>
            <input type="hidden" name="action" value="lousy_outages_poll_now">
            <?php submit_button( 'Poll Now', 'secondary', 'submit', false ); ?>
        </form>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col">Provider</th>
                    <th scope="col">Enabled</th>
                    <th scope="col">Last state</th>
                    <th scope="col">Updated</th>
                    <th scope="col">Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $providers as $id => $prov ) :
                    $state    = $states[ $id ] ?? [];
                    $status   = $state['status_label'] ?? Fetcher::status_label( (string) ( $state['status'] ?? 'unknown' ) );
                    $updated  = $format_datetime( $state['updated_at'] ?? null );
                    $error    = $state['error'] ?? '';
                    $is_enabled = in_array( $id, (array) $enabled, true );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $prov['name'] ); ?></td>
                        <td><?php echo esc_html( $is_enabled ? 'Yes' : 'No' ); ?></td>
                        <td><?php echo esc_html( $status ); ?></td>
                        <td><?php echo esc_html( $updated ); ?></td>
                        <td><?php echo esc_html( (string) $error ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action( 'admin_post_lousy_outages_test_email', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) );
    }

    check_admin_referer( 'lousy_outages_test_email' );

    $email = new Email();
    $email->send_alert( 'Test Provider', 'degraded', 'Test alert from settings', (string) home_url() );

    set_transient(
        'lousy_outages_notice',
        [
            'message' => 'Test email sent. Check the log below for confirmation.',
            'type'    => 'success',
        ],
        30
    );

    $redirect = add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
} );

add_action( 'admin_post_lousy_outages_test_sms', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) );
    }

    check_admin_referer( 'lousy_outages_test_sms' );

    $sid   = get_option( 'lousy_outages_twilio_sid' );
    $token = get_option( 'lousy_outages_twilio_token' );
    $from  = get_option( 'lousy_outages_twilio_from' );
    $to    = get_option( 'lousy_outages_phone' );

    if ( ! $sid || ! $token || ! $from || ! $to ) {
        set_transient(
            'lousy_outages_notice',
            [
                'message' => 'Add your Twilio SID/Auth/From and destination phone number before sending a test SMS.',
                'type'    => 'error',
            ],
            30
        );
    } else {
        $sms = new SMS();
        $sms->send_alert( 'Test Provider', 'degraded', 'Test alert from settings', (string) home_url( '/lousy-outages/' ) );

        set_transient(
            'lousy_outages_notice',
            [
                'message' => 'Test SMS sent. Check your phone shortly.',
                'type'    => 'success',
            ],
            30
        );
    }

    $redirect = add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
} );

add_action( 'admin_post_lousy_outages_poll_now', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) );
    }

    check_admin_referer( 'lousy_outages_poll_now' );

    $count = lousy_outages_run_poll();

    set_transient(
        'lousy_outages_notice',
        [
            'message' => sprintf( 'Poll completed (%d providers).', $count ),
            'type'    => 'success',
        ],
        30
    );

    $redirect = add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
} );

function lousy_outages_build_provider_payload( string $id, array $state, string $fetched_at ): array {
    $status     = $state['status'] ?? 'unknown';
    $label      = Fetcher::status_label( $status );
    $updated_at = ! empty( $state['updated_at'] ) ? (string) $state['updated_at'] : $fetched_at;
    $incidents  = [];
    $prealert   = [];
    if ( isset( $state['prealert'] ) && is_array( $state['prealert'] ) ) {
        $prealert = $state['prealert'];
    }
    $risk = isset( $prealert['risk'] ) ? (int) $prealert['risk'] : ( isset( $state['risk'] ) ? (int) $state['risk'] : 0 );
    $prealert['risk'] = $risk;
    if ( ! isset( $prealert['summary'] ) ) {
        $prealert['summary'] = $risk > 0 ? 'Early warning detected' : 'No early signals';
    }
    if ( ! isset( $prealert['signals'] ) || ! is_array( $prealert['signals'] ) ) {
        $prealert['signals'] = [];
    }
    if ( ! isset( $prealert['measures'] ) || ! is_array( $prealert['measures'] ) ) {
        $prealert['measures'] = [];
    }
    if ( ! isset( $prealert['details'] ) || ! is_array( $prealert['details'] ) ) {
        $prealert['details'] = [];
    }
    if ( ! isset( $prealert['updated_at'] ) ) {
        $prealert['updated_at'] = $updated_at;
    }
    static $provider_urls = null;
    if ( null === $provider_urls ) {
        $provider_urls = [];
        foreach ( Providers::list() as $provider_id => $provider ) {
            $provider_urls[ $provider_id ] = $provider['status_url'] ?? '';
        }
    }

    if ( ! empty( $state['incidents'] ) && is_array( $state['incidents'] ) ) {
        foreach ( $state['incidents'] as $incident ) {
            if ( ! is_array( $incident ) ) {
                continue;
            }
            $incidents[] = [
                'id'        => (string) ( $incident['id'] ?? md5( $id . wp_json_encode( $incident ) ) ),
                'title'     => $incident['title'] ?? 'Incident',
                'summary'   => $incident['summary'] ?? '',
                'startedAt' => $incident['started_at'] ?? '',
                'updatedAt' => $incident['updated_at'] ?? '',
                'impact'    => $incident['impact'] ?? 'minor',
                'eta'       => $incident['eta'] ?? 'investigating',
                'url'       => $incident['url'] ?? ( $state['url'] ?? '' ),
            ];
        }
    }

    $url = $state['url'] ?? '';
    if ( ! $url && isset( $provider_urls[ $id ] ) ) {
        $url = $provider_urls[ $id ];
    }

    return [
        'id'         => $id,
        'provider'   => $state['provider'] ?? $state['name'] ?? $id,
        'name'       => $state['name'] ?? $state['provider'] ?? $id,
        'state'      => $label,
        'stateCode'  => $status,
        'summary'    => $state['summary'] ?? $label,
        'updatedAt'  => $updated_at,
        'url'        => $url,
        'snark'      => $state['snark'] ?? '',
        'incidents'  => $incidents,
        'error'      => $state['error'] ?? null,
        'risk'       => $risk,
        'prealert'   => $prealert,
        'sourceType' => $state['source_type'] ?? '',
    ];
}

/**
 * Sort providers so that the most severe issues appear first.
 */
function lousy_outages_sort_providers( array $providers ): array {
    $state_priority = [
        'outage'      => 0,
        'degraded'    => 1,
        'maintenance' => 2,
        'unknown'     => 3,
        'operational' => 4,
    ];

    $sort_key = static function ( array $provider ) use ( $state_priority ): array {
        $state      = strtolower( (string) ( $provider['stateCode'] ?? 'unknown' ) );
        $state_rank = $state_priority[ $state ] ?? $state_priority['unknown'];

        $has_incidents = ! empty( $provider['incidents'] );
        $has_error     = ! empty( $provider['error'] );
        $risk          = isset( $provider['risk'] ) ? (int) $provider['risk'] : 0;
        if ( ! $risk && isset( $provider['prealert']['risk'] ) ) {
            $risk = (int) $provider['prealert']['risk'];
        }

        $name = strtolower( (string) ( $provider['name'] ?? $provider['id'] ?? '' ) );

        return [
            $has_incidents ? 0 : 1,
            $state_rank,
            $has_error ? 0 : 1,
            -1 * $risk,
            $name,
        ];
    };

    usort(
        $providers,
        static function ( array $a, array $b ) use ( $sort_key ): int {
            $a_key = $sort_key( $a );
            $b_key = $sort_key( $b );

            foreach ( $a_key as $index => $value ) {
                $a_value = $value;
                $b_value = $b_key[ $index ] ?? null;

                if ( $a_value === $b_value ) {
                    continue;
                }

                return $a_value <=> $b_value;
            }

            return 0;
        }
    );

    return $providers;
}

/**
 * REST endpoint exposing current status.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'lousy-outages/v1', '/status', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function ( \WP_REST_Request $request ) {
            $store    = new Store();
            $refresh  = $request->get_param( 'refresh' );
            $bypass   = null !== $refresh ? filter_var( $refresh, FILTER_VALIDATE_BOOLEAN ) : false;
            $fetched  = gmdate( 'c' );
            $data     = lousy_outages_collect_statuses( $bypass );

            foreach ( $data as $id => $state ) {
                $store->update( $id, $state );
            }

            $providers = [];
            foreach ( $data as $id => $state ) {
                $providers[] = lousy_outages_build_provider_payload( $id, $state, $fetched );
            }

            $providers = lousy_outages_sort_providers( $providers );

            $payload  = [
                'providers' => $providers,
                'meta'      => [
                    'fetchedAt'   => $fetched,
                    'generatedAt' => gmdate( 'c' ),
                    'fresh'       => (bool) $bypass,
                ],
            ];
            $response = rest_ensure_response( $payload );
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', '0' );

            return $response;
        },
    ] );
} );
