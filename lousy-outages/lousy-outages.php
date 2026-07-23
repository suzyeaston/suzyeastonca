<?php
declare( strict_types=1 );
/**
 * Plugin Name: Lousy Outages
 * Description: WordPress-native outage intelligence, community reporting, and early-warning signals for third-party service dependencies.
 * Version: 0.4.4
 * Author: Suzy Easton
 * Text Domain: lousy-outages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'LOUSY_OUTAGES_LOADED' ) ) {
    return;
}
define( 'LOUSY_OUTAGES_LOADED', true );

// LO: allow hard shutdown via constant for emergency maintenance.
if ( defined( 'LOUSY_OUTAGES_DISABLE' ) && LOUSY_OUTAGES_DISABLE ) {
    error_log( '[LO] disabled via constant' );
    return;
}

if ( ! defined( 'LOUSY_OUTAGES_VERSION' ) ) {
    define( 'LOUSY_OUTAGES_VERSION', '0.4.4' );
}
if ( ! defined( 'LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION' ) ) {
    define( 'LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION', 5 );
}
if ( ! defined( 'LOUSY_OUTAGES_FILE' ) ) {
    define( 'LOUSY_OUTAGES_FILE', __FILE__ );
}
if ( ! defined( 'LOUSY_OUTAGES_PATH' ) ) {
    define( 'LOUSY_OUTAGES_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LOUSY_OUTAGES_URL' ) ) {
    define( 'LOUSY_OUTAGES_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! function_exists( 'lousy_outages_require' ) ) {
    function lousy_outages_require( string $relative, bool $required = true ): void {
        $path = LOUSY_OUTAGES_PATH . ltrim( $relative, '/' );
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
        error_log( '[LO] missing include: ' . $relative );
        if ( $required ) {
            return;
        }
    }
}

lousy_outages_require( 'includes/ProviderRegistry.php' );
lousy_outages_require( 'includes/Providers.php' );
lousy_outages_require( 'includes/Fetch.php' );
lousy_outages_require( 'includes/Adapters.php' );
lousy_outages_require( 'includes/Adapters/Statuspage.php' );
lousy_outages_require( 'includes/Store.php' );
lousy_outages_require( 'includes/Trending.php' );
lousy_outages_require( 'includes/Fetcher.php' );
lousy_outages_require( 'includes/I18n.php' );
lousy_outages_require( 'includes/Detector.php' );
lousy_outages_require( 'includes/SMS.php' );
lousy_outages_require( 'includes/Mailer.php' );
lousy_outages_require( 'includes/MailTransport.php' );
lousy_outages_require( 'includes/email-templates.php' );
lousy_outages_require( 'includes/Email.php' );
lousy_outages_require( 'includes/Precursor.php' );
lousy_outages_require( 'includes/Subscriptions.php' );
lousy_outages_require( 'includes/UserReports.php' );
lousy_outages_require( 'includes/SignalEngine.php' );
lousy_outages_require( 'includes/Subscribe.php' );
lousy_outages_require( 'includes/Feeds.php' );
lousy_outages_require( 'includes/Summary.php' );
lousy_outages_require( 'includes/CurrentState.php' );
lousy_outages_require( 'includes/IncidentAlerts.php' );
lousy_outages_require( 'includes/Snapshot.php' );
lousy_outages_require( 'includes/PublicCopy.php' );
lousy_outages_require( 'includes/Cron.php' );
lousy_outages_require( 'includes/compat.php' );
lousy_outages_require( 'includes/Sources/StatuspageSource.php' );
lousy_outages_require( 'includes/Model/Incident.php' );
lousy_outages_require( 'includes/Storage/HistoryStore.php' );
lousy_outages_require( 'includes/Storage/IncidentStore.php' );
lousy_outages_require( 'includes/Email/Composer.php' );
lousy_outages_require( 'includes/Sources/Sources.php' );
lousy_outages_require( 'includes/Sources/SourceRegistry.php' );
lousy_outages_require( 'includes/Sources/index.php' );
lousy_outages_require( 'includes/Cron/Refresh.php' );

// External signal infrastructure must load before concrete source classes.
lousy_outages_require( 'includes/SignalSourceInterface.php' );
lousy_outages_require( 'includes/ExternalSignals.php' );
lousy_outages_require( 'includes/Sources/ProviderFeedSource.php' );
lousy_outages_require( 'includes/Sources/SourceBudgetManager.php' );
lousy_outages_require( 'includes/Sources/ChatterRejectionReasons.php' );
lousy_outages_require( 'includes/Sources/StatuspageIntelSource.php' );
lousy_outages_require( 'includes/SignalCollector.php' );
lousy_outages_require( 'includes/Api.php' );
lousy_outages_require( 'includes/AdminCleanup.php' );
lousy_outages_require( 'includes/AdminDiagnostics.php' );
lousy_outages_require( 'includes/ProviderPages.php' );

lousy_outages_require( 'public/shortcode.php' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    lousy_outages_require( 'wp-cli/class-wp-cli-lousy.php', false );
}

use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\Store;
use SuzyEaston\LousyOutages\Fetcher;
use SuzyEaston\LousyOutages\Detector;
use SuzyEaston\LousyOutages\SMS;
use SuzyEaston\LousyOutages\Email;
use SuzyEaston\LousyOutages\Precursor;
use SuzyEaston\LousyOutages\Subscriptions;
use SuzyEaston\LousyOutages\UserReports;
use SuzyEaston\LousyOutages\SignalEngine;
use SuzyEaston\LousyOutages\Api;
use SuzyEaston\LousyOutages\Feeds;
use SuzyEaston\LousyOutages\MailTransport;
use SuzyEaston\LousyOutages\IncidentAlerts;
use SuzyEaston\LousyOutages\ExternalSignals;
use SuzyEaston\LousyOutages\SignalCollector;
use SuzyEaston\LousyOutages\Cron\Refresh as RefreshCron;

Api::bootstrap();
// LO: Legacy feed slug removed to avoid clashing with the dashboard page; status feed now handled below.
Feeds::bootstrap();
MailTransport::bootstrap();
IncidentAlerts::bootstrap();
RefreshCron::bootstrap();
\SuzyEaston\LousyOutages\AdminCleanup::bootstrap();
\SuzyEaston\LousyOutages\AdminDiagnostics::bootstrap();
\SuzyEaston\LousyOutages\ProviderPages::bootstrap();

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
    lousy_outages_upgrade_032_runtime();
    lousy_outages_schedule_canonical_refresh();
    lousy_outages_create_page();
    lousy_outages_maybe_install_schema( true );
    if ( class_exists( '\\SuzyEaston\\LousyOutages\\Storage\\HistoryStore' ) ) { ( new \SuzyEaston\LousyOutages\Storage\HistoryStore() )->installTable(); }
    Subscriptions::schedule_purge();
    $default_email = 'suzyeaston@gmail.com';
    $stored_email  = get_option( 'lousy_outages_email' );
    if ( empty( $stored_email ) && is_email( $default_email ) ) {
        update_option( 'lousy_outages_email', sanitize_email( $default_email ) );
    }
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules( false );
    }
}
register_activation_hook( __FILE__, 'lousy_outages_activate' );

function lousy_outages_schedule_canonical_refresh(): void {
    wp_clear_scheduled_hook( 'lousy_outages_refresh_official_providers' );
    $schedules  = function_exists( 'wp_get_schedules' ) ? wp_get_schedules() : [];
    $recurrence = isset( $schedules['lousy_outages_15min'] ) ? 'lousy_outages_15min' : ( isset( $schedules['lo_five_minutes'] ) ? 'lo_five_minutes' : 'hourly' );
    wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, 'lousy_outages_refresh_official_providers' );
}

function lousy_outages_upgrade_032_runtime(): void {
    foreach ( [ 'lousy_outages_poll', 'lousy_outages_cron_refresh', 'lousy_outages_refresh', 'lo_check_statuses', 'lo_refresh_snapshot' ] as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }
    foreach ( [ 'lo_diag_public_chatter', 'lo_diag_hacker_news_chatter', 'lousy_outages_last_external_collection', 'lousy_outages_signal_engine_last_fused', 'lo_diag_cloudflare_radar', 'lo_diag_synthetic_canary', 'lo_diag_canadian_infrastructure_watch' ] as $key ) {
        delete_option( $key );
        delete_transient( $key );
    }
    update_option( 'lousy_outages_runtime_version', LOUSY_OUTAGES_VERSION, false );
    lousy_outages_schedule_canonical_refresh();
}
add_action( 'init', static function (): void {
    if ( version_compare( (string) get_option( 'lousy_outages_runtime_version', '0.0.0' ), '0.3.2', '<' ) ) {
        lousy_outages_upgrade_032_runtime();
    }
}, 3 );
if ( ! has_action( 'lousy_outages_refresh_official_providers', 'lousy_outages_refresh_official_providers' ) ) {
    add_action( 'lousy_outages_refresh_official_providers', 'lousy_outages_refresh_official_providers' );
}
function lousy_outages_refresh_official_providers( bool $bypass_cache = true ): array {
    return lousy_outages_refresh_data( $bypass_cache );
}

function lousy_outages_ensure_canonical_cron_scheduled(): void {
    if ( wp_next_scheduled( 'lousy_outages_refresh_official_providers' ) ) {
        return;
    }
    $recurrence = 'lousy_outages_interval';
    wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, 'lousy_outages_refresh_official_providers' );
}
add_action( 'init', 'lousy_outages_ensure_canonical_cron_scheduled', 30 );


function lousy_outages_maybe_install_schema( bool $force = false ): void {
    $schema_version = ExternalSignals::SCHEMA_VERSION;
    $option_key = 'lousy_outages_schema_version';
    $current = (string) get_option( $option_key, '' );
    $diag = ExternalSignals::schema_diagnostics();
    $needs_external = ! empty( $diag['missing_columns'] ) || empty( $diag['table_exists'] );
    if ( ! $force && version_compare( $current, $schema_version, '>=' ) && ! $needs_external ) {
        return;
    }

    Subscriptions::create_table();
    UserReports::install();
    if ( $needs_external || $force || version_compare( $current, $schema_version, '<' ) ) {
        ExternalSignals::install();
    }
    update_option( 'lousy_outages_external_schema_diagnostic', ExternalSignals::schema_diagnostics(), false );
    update_option( $option_key, $schema_version, false );
}

function lousy_outages_maybe_repair_snapshot_caches(): void {
    $option_key = 'lousy_outages_snapshot_schema_version';
    $current = (int) get_option( $option_key, 0 );
    if ( $current >= (int) LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION ) { return; }
    foreach ( [ 'lousy_outages_cached_statuses', 'lousy_status_snapshot', 'lo_snapshot_payload_v1', 'lousy_outages_fragment_public' ] as $key ) { delete_transient( $key ); }
    delete_option( 'lousy_outages_current_state' );
    delete_option( 'lo_diag_public_chatter' );
    update_option( $option_key, (int) LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION, false );
}
add_action( 'admin_init', 'lousy_outages_maybe_install_schema' );
add_action( 'init', 'lousy_outages_maybe_repair_snapshot_caches', 4 );
add_action( 'init', static function (): void {
    if ( is_admin() ) {
        return;
    }
    lousy_outages_maybe_install_schema();
}, 5 );

/**
 * Clear cron on deactivation.
 */
function lousy_outages_deactivate() {
    wp_clear_scheduled_hook( 'lousy_outages_refresh_official_providers' );
    wp_clear_scheduled_hook( 'lousy_outages_poll' );
    wp_clear_scheduled_hook( 'lousy_outages_cron_refresh' );
    wp_clear_scheduled_hook( 'lousy_outages_refresh' );
    wp_clear_scheduled_hook( 'lo_check_statuses' );
    Subscriptions::clear_schedule();
    wp_clear_scheduled_hook( 'lo_refresh_snapshot' );
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
    $schedules['lousy_outages_15min'] = [
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => 'Lousy Outages – 15 minutes',
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
    $fetch_error_count = 0;
    $operational_count = 0;
    $non_operational_count = 0;
    $unknown_count = 0;

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
                'summary'     => 'Status temporarily unavailable.',
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
            $normalized_status = strtolower( (string) ( $state['status'] ?? 'unknown' ) );
            if ( in_array( $normalized_status, [ 'operational', 'ok' ], true ) ) {
                $operational_count++;
            } elseif ( in_array( $normalized_status, [ 'unknown', '' ], true ) ) {
                $unknown_count++;
            } else {
                $non_operational_count++;
            }
            if ( '' !== $error_message && ! in_array( $normalized_status, [ 'operational', 'ok', 'degraded', 'outage', 'partial', 'maintenance' ], true ) ) {
                $fetch_error_count++;
                error_log( sprintf( '[lousy_outages] fetch_failure provider=%s message=%s', $id, $error_message ) );
            }
        }
    }

    $ttl     = (int) apply_filters( 'lousy_outages_cache_ttl', 90 );
    $payload = lousy_outages_make_serializable( $results );
    set_transient( $cache_key, $payload, max( 30, $ttl ) );

    $duration_ms = ( microtime( true ) - $start_time ) * 1000;
    error_log( sprintf( '[lousy_outages] fetch_complete count=%d duration_ms=%.2f operational=%d non_operational=%d unknown=%d fetch_errors=%d', count( $payload ), $duration_ms, $operational_count, $non_operational_count, $unknown_count, $fetch_error_count ) );

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

function lousy_outages_get_last_poll_timestamp(): ?int {
    $candidates = [];
    $lastFetched = lousy_outages_get_last_fetched_timestamp();
    if ( null !== $lastFetched && $lastFetched > 0 ) { $candidates[] = (int) $lastFetched; }
    foreach ( ['lousy_outages_last_poll', 'lo_last_status_check'] as $key ) {
        $v = get_option( $key );
        if ( is_string( $v ) && '' !== trim( $v ) ) { $ts = strtotime( $v ); if ( false !== $ts ) { $candidates[] = (int) $ts; } }
    }
    if ( empty( $candidates ) ) { return null; }
    rsort( $candidates, SORT_NUMERIC );
    return (int) $candidates[0];
}

function lousy_outages_format_external_collection_summary( $raw ): string {
    if ( empty( $raw ) ) { return 'Not collected yet.'; }
    if ( ! is_array( $raw ) ) { return (string) $raw; }
    $ts = (string) ( $raw['timestamp'] ?? $raw['ran_at'] ?? '' );
    $tsLabel = 'unknown';
    if ( '' !== $ts ) { $parsed = strtotime( $ts ); if ( false !== $parsed ) { $tsLabel = wp_date( 'M j, Y g:i a', $parsed ); } }
    $success = (int) ( $raw['success_count'] ?? $raw['stored'] ?? 0 );
    $errors = (int) ( $raw['error_count'] ?? ( is_array( $raw['errors'] ?? null ) ? count( $raw['errors'] ) : 0 ) );
    $attempted = (int) ( $raw['sources_attempted'] ?? ( is_array( $raw['sources'] ?? null ) ? count( $raw['sources'] ) : 0 ) );
    if ( 'unknown' === $tsLabel ) { return 'No successful external collection yet'; }
    return sprintf( 'Last collection: %s. Sources checked: %d. Stored signals: %d. Errors: %d.', $tsLabel, $attempted, $success, $errors );
}

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
        'alert_diagnostics' => [],
    ];

    try {
        // Use UTC to avoid double-applying the site timezone offset when formatting below.
        $timestamp_epoch = current_time( 'timestamp', true );
        $timestamp_iso   = wp_date( 'c', $timestamp_epoch );
        $timestamp_gmt   = gmdate( 'c', $timestamp_epoch );
        $store        = new Store();
        $previous_states = $store->get_all();
        $raw_states   = lousy_outages_collect_statuses( $bypass_cache );
        $quality      = lousy_outages_snapshot_quality( $raw_states, $previous_states );
        $states       = lousy_outages_merge_verified_states( $raw_states, $previous_states, $timestamp_gmt );
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
        $alert_diagnostics = [];
        if ( class_exists( '\\SuzyEaston\\LousyOutages\\IncidentAlerts' ) && method_exists( '\\SuzyEaston\\LousyOutages\\IncidentAlerts', 'process_snapshot' ) ) {
            $alert_diagnostics = \SuzyEaston\LousyOutages\IncidentAlerts::process_snapshot( $snapshot, [ 'mode' => 'canonical_refresh' ] );
        }
        $providers = isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] ) ? $snapshot['providers'] : [];
        $trending  = isset( $snapshot['trending'] ) && is_array( $snapshot['trending'] ) ? $snapshot['trending'] : [ 'trending' => false, 'signals' => [] ];

        if ( empty( $errors ) && isset( $snapshot['errors'] ) && is_array( $snapshot['errors'] ) ) {
            $errors = $snapshot['errors'];
        }

        update_option( 'lousy_outages_last_refresh_attempt', [ 'timestamp' => $timestamp_gmt, 'providers_attempted' => count( $states ), 'quality' => $quality ], false );
        update_option( 'lousy_outages_last_refresh_at', $timestamp_gmt, false );
        $health = (array) get_option( 'lousy_outages_provider_health', [] );
        foreach ( $states as $id => $state ) {
            $prior_health = isset( $health[ $id ] ) && is_array( $health[ $id ] ) ? $health[ $id ] : [];
            $failed_state = is_array( $state ) && lousy_outages_provider_fetch_failed( $state );
            $consecutive = $failed_state ? ( (int) ( $prior_health['consecutive_failures'] ?? 0 ) + 1 ) : 0;
            $health[ $id ] = [
                'last_attempt' => $timestamp_gmt,
                'last_success' => $failed_state ? (string) ( $prior_health['last_success'] ?? ( $state['last_successful_at'] ?? '' ) ) : $timestamp_gmt,
                'last_error' => $failed_state ? (string) ( $state['fetch_error'] ?? $state['error'] ?? 'Fetch failed' ) : '',
                'endpoint' => is_array( $state ) ? (string) ( $state['endpoint'] ?? '' ) : '',
                'final_url' => is_array( $state ) ? (string) ( $state['final_url'] ?? $state['endpoint'] ?? '' ) : '',
                'http_status' => is_array( $state ) ? ( $state['http_status'] ?? null ) : null,
                'content_type' => is_array( $state ) ? (string) ( $state['content_type'] ?? '' ) : '',
                'adapter' => is_array( $state ) ? (string) ( $state['adapter'] ?? '' ) : '',
                'schema_result' => is_array( $state ) ? (string) ( $state['schema_result'] ?? '' ) : '',
                'current_error' => $failed_state ? (string) ( $state['fetch_error'] ?? $state['error'] ?? 'Fetch failed' ) : '',
                'consecutive_failures' => $consecutive,
                'snapshot_age_seconds' => ! empty( $state['updated_at'] ) ? max( 0, time() - ( strtotime( (string) $state['updated_at'] ) ?: time() ) ) : null,
            ];
        }
        update_option( 'lousy_outages_provider_health', $health, false );
        if ( ! empty( $quality['ok'] ) && 0 === (int) ( $quality['failed'] ?? 0 ) ) {
            update_option( 'lousy_outages_last_refresh_complete', [ 'timestamp' => $timestamp_gmt, 'providers_successful' => (int) ( $quality['verified'] ?? 0 ) ], false );
        }

        update_option( 'lousy_outages_last_poll', $timestamp_iso, false );
        update_option( 'lousy_outages_last_attempted', $timestamp_epoch, false );
        update_option( 'lousy_outages_last_attempted_iso', $timestamp_gmt, false );
        if ( ! empty( $quality['ok'] ) ) {
            update_option( 'lousy_outages_last_fetched', $timestamp_epoch, false );
            update_option( 'lousy_outages_last_fetched_iso', $timestamp_gmt, false );
        }
        do_action( 'lousy_outages_log', 'refresh_complete', [
            'count' => count( $states ),
            'ts'    => $timestamp_iso,
        ] );

        $response = [
            'ok'           => ! empty( $quality['ok'] ),
            'providers'    => $providers,
            'errors'       => $errors,
            'trending'     => $trending,
            'alert_diagnostics' => $alert_diagnostics,
            'quality'      => $quality,
            'current_state'=> isset( $snapshot['current_state'] ) && is_array( $snapshot['current_state'] ) ? $snapshot['current_state'] : lousy_outages_current_state_from_snapshot( $snapshot ),
            'source'       => ! empty( $quality['ok'] ) ? 'live' : 'last_good_with_errors',
            'refreshedAt'  => $timestamp_iso,
            'refreshed_at' => $timestamp_epoch,
            'alert_diagnostics' => $alert_diagnostics,
        ];
    } catch ( \Throwable $e ) {
        error_log( '[LO] refresh failed: ' . $e->getMessage() );
        $response['message'] = $e->getMessage();
    } finally {
        delete_transient( $lock_key );
    }

    return $response;
}


function lousy_outages_provider_fetch_failed( array $state ): bool {
    $status = strtolower( (string) ( $state['status'] ?? '' ) );
    return ( '' === $status || 'unknown' === $status || ! empty( $state['error'] ) ) && empty( $state['incidents'] );
}

function lousy_outages_stale_grace_seconds(): int {
    return (int) apply_filters( 'lousy_outages_stale_grace_seconds', 6 * HOUR_IN_SECONDS );
}

function lousy_outages_merge_verified_states( array $new_states, array $previous_states, string $attempted_at ): array {
    $merged = [];
    foreach ( $new_states as $id => $state ) {
        if ( ! is_array( $state ) ) { continue; }
        $failed = lousy_outages_provider_fetch_failed( $state );
        $prior  = isset( $previous_states[ $id ] ) && is_array( $previous_states[ $id ] ) ? $previous_states[ $id ] : [];
        if ( $failed && $prior ) {
            $last_success = (string) ( $prior['last_successful_at'] ?? $prior['checked_at'] ?? $prior['updated_at'] ?? '' );
            $last_ts = $last_success ? ( strtotime( $last_success ) ?: 0 ) : 0;
            if ( $last_ts && ( time() - $last_ts ) <= lousy_outages_stale_grace_seconds() ) {
                $kept = $prior;
                $kept['verification_status'] = 'stale';
                $kept['is_stale'] = true;
                $kept['fetch_error'] = (string) ( $state['error'] ?? $state['message'] ?? 'Fetch failed' );
                $kept['error'] = $kept['fetch_error'];
                $kept['last_attempted_at'] = $attempted_at;
                $kept['last_successful_at'] = $last_success;
                $merged[ $id ] = $kept;
                continue;
            }
        }
        $state['verification_status'] = $failed ? 'failed' : 'verified';
        $state['is_stale'] = false;
        $state['fetch_error'] = $failed ? (string) ( $state['error'] ?? $state['message'] ?? 'Fetch failed' ) : '';
        $state['last_attempted_at'] = $attempted_at;
        if ( ! $failed ) { $state['last_successful_at'] = $attempted_at; }
        elseif ( ! empty( $prior['last_successful_at'] ) ) { $state['last_successful_at'] = $prior['last_successful_at']; }
        $merged[ $id ] = $state;
    }
    return $merged;
}

function lousy_outages_snapshot_quality( array $states, array $previous_states = [] ): array {
    $total = count( $states ); $failed = 0; $verified = 0;
    foreach ( $states as $state ) { if ( is_array( $state ) && lousy_outages_provider_fetch_failed( $state ) ) { $failed++; } else { $verified++; } }
    $ok = $total > 0 && $verified > 0 && ( $verified / max( 1, $total ) ) >= (float) apply_filters( 'lousy_outages_quality_min_success_ratio', 0.25 );
    return [ 'ok' => $ok, 'total' => $total, 'verified' => $verified, 'failed' => $failed, 'success_ratio' => $total ? $verified / $total : 0 ];
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

    $original_provider_count = count( $providers );
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

    if ( count( $snapshot['providers'] ) !== $original_provider_count || ! isset( $snapshot['trending'] ) ) {
        $snapshot['trending'] = ( new \SuzyEaston\LousyOutages\Trending() )->evaluate( $snapshot['providers'] );
    }

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
        'schema_version' => LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION,
        'providers'  => $providers,
        'fetched_at' => $timestamp,
        'trending'   => $trending,
        'errors'     => $errors,
        'source'     => $source,
    ] );
}


function lousy_outages_update_aws_snapshot_diagnostic( array $snapshot ): void {
    $providers = isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] ) ? $snapshot['providers'] : [];
    foreach ( $providers as $provider ) {
        if ( ! is_array( $provider ) || 'aws' !== strtolower( (string) ( $provider['id'] ?? '' ) ) ) { continue; }
        $incidents = isset( $provider['incidents'] ) && is_array( $provider['incidents'] ) ? $provider['incidents'] : [];
        $recent = isset( $provider['recentIncidents'] ) && is_array( $provider['recentIncidents'] ) ? $provider['recentIncidents'] : [];
        $first = $incidents[0] ?? ( $recent[0] ?? [] );
        update_option( 'lousy_outages_aws_snapshot_diagnostic', [
            'parsed_rss_item_count' => (int) ( $provider['parsed_rss_item_count'] ?? $provider['rss_item_count'] ?? count( $incidents ) + count( $recent ) ),
            'active_incident_count' => count( $incidents ),
            'recent_incident_count' => count( $recent ),
            'tile_kind' => (string) ( $provider['tile_kind'] ?? '' ),
            'source_title' => is_array( $first ) ? (string) ( $first['source_title'] ?? $first['title'] ?? '' ) : '',
            'display_title' => is_array( $first ) ? (string) ( $first['display_title'] ?? $first['displayTitle'] ?? '' ) : '',
            'official_update_timestamp' => is_array( $first ) ? (string) ( $first['last_official_update'] ?? $first['lastOfficialUpdate'] ?? '' ) : '',
            'checked_timestamp' => (string) ( $provider['checked_at'] ?? $provider['checkedAt'] ?? $snapshot['fetched_at'] ?? '' ),
            'snapshot_schema_version' => (int) ( $snapshot['schema_version'] ?? 0 ),
        ], false );
        return;
    }
}


function lousy_outages_signal_freshness_seconds(): int {
    return (int) apply_filters( 'lousy_outages_signal_freshness_seconds', 45 * MINUTE_IN_SECONDS );
}

function lousy_outages_provider_timestamp( array $provider ): int {
    foreach ( [ 'checked_at', 'checkedAt', 'fetched_at', 'updated_at', 'updatedAt', 'last_successful_at' ] as $field ) {
        if ( ! empty( $provider[ $field ] ) ) {
            $ts = strtotime( (string) $provider[ $field ] );
            if ( $ts ) { return (int) $ts; }
        }
    }
    return 0;
}

function lousy_outages_current_state_from_snapshot( array $snapshot ): array {
    $providers = isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] ) ? array_values( $snapshot['providers'] ) : [];
    $now = time();
    $fresh_window = lousy_outages_signal_freshness_seconds();
    $outages = [];
    $signals = [];
    $unverified = [];
    $operational = [];
    $current_provider_ids = [];
    foreach ( $providers as $provider ) {
        if ( ! is_array( $provider ) ) { continue; }
        $provider_id = sanitize_key( (string) ( $provider['id'] ?? $provider['provider'] ?? $provider['name'] ?? '' ) );
        $provider['provider_id'] = $provider_id;
        $verification = strtolower( (string) ( $provider['verification_status'] ?? '' ) );
        $is_stale = ! empty( $provider['is_stale'] );
        $status = strtolower( (string) ( $provider['stateCode'] ?? $provider['status'] ?? 'unknown' ) );
        if ( in_array( $verification, [ 'failed', 'unknown' ], true ) || ( 'unknown' === $status && ! $is_stale ) ) {
            $provider['lane'] = 'unverified';
            $provider['stale_label'] = $is_stale ? 'Delayed provider data' : 'Could not verify provider status';
            $unverified[] = $provider;
            continue;
        }
        if ( $is_stale ) {
            $provider['lane'] = 'unverified';
            $provider['stale_label'] = 'Delayed provider data; showing bounded last-known-good context.';
            $unverified[] = $provider;
            continue;
        }
        $current_incidents = class_exists( '\\SuzyEaston\\LousyOutages\\Summary' ) ? \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider( $provider ) : (array) ( $provider['incidents'] ?? [] );
        foreach ( $current_incidents as $incident ) {
            if ( ! is_array( $incident ) ) { continue; }
            $record = $incident;
            $record['provider_id'] = $provider_id;
            $record['provider'] = (string) ( $provider['name'] ?? $provider['provider'] ?? $provider_id );
            $record['provider_url'] = (string) ( $provider['url'] ?? $provider['link'] ?? '' );
            $outages[] = $record;
            if ( '' !== $provider_id ) { $current_provider_ids[] = $provider_id; }
        }
        if ( $current_incidents ) { continue; }
        $tile_kind = strtolower( (string) ( $provider['tile_kind'] ?? $provider['tileKind'] ?? '' ) );
        $ts = lousy_outages_provider_timestamp( $provider );
        $fresh = $ts > 0 && ( $now - $ts ) <= $fresh_window;
        if ( 'signal' === $tile_kind && $fresh ) {
            $provider['lane'] = 'signals';
            $provider['fresh_until'] = gmdate( 'c', $ts + $fresh_window );
            $signals[] = $provider;
        } else {
            $provider['lane'] = 'operational';
            $operational[] = $provider;
        }
    }
    return [
        'outages' => array_values( $outages ),
        'signals' => array_values( $signals ),
        'unverified' => array_values( $unverified ),
        'operational' => array_values( $operational ),
        'meta' => [
            'active_outage_count' => count( $outages ),
            'signal_count' => count( $signals ),
            'unverified_count' => count( $unverified ),
            'operational_count' => count( $operational ),
            'generated_at' => gmdate( 'c' ),
            'freshness_window_seconds' => $fresh_window,
            'current_official_provider_ids' => array_values( array_unique( $current_provider_ids ) ),
        ],
    ];
}

function lousy_outages_store_snapshot( array $snapshot ): void {
    $cache_key = lousy_outages_snapshot_cache_key();
    $ttl       = (int) apply_filters( 'lousy_outages_snapshot_ttl', 5 * MINUTE_IN_SECONDS );
    $snapshot['current_state'] = lousy_outages_current_state_from_snapshot( $snapshot );
    set_transient( $cache_key, $snapshot, max( 60, $ttl ) );
    update_option( 'lousy_outages_snapshot', $snapshot, false );
    update_option( 'lousy_outages_current_state', $snapshot['current_state'], false );
    lousy_outages_update_aws_snapshot_diagnostic( $snapshot );
}

function lousy_outages_refresh_snapshot( array $states, string $timestamp, string $source = 'snapshot' ): array {
    $snapshot = lousy_outages_build_snapshot( $states, $timestamp, $source );
    lousy_outages_store_snapshot( $snapshot );
    $stored_snapshot = get_option( 'lousy_outages_snapshot', $snapshot );
    if ( is_array( $stored_snapshot ) ) { $snapshot = $stored_snapshot; }

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


function lousy_outages_snapshot_schema_is_current( array $snapshot ): bool {
    if ( (int) ( $snapshot['schema_version'] ?? 0 ) !== (int) LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION ) {
        return false;
    }
    $providers = isset( $snapshot['providers'] ) && is_array( $snapshot['providers'] ) ? $snapshot['providers'] : [];
    foreach ( $providers as $provider ) {
        if ( ! is_array( $provider ) ) { return false; }
        $incidents = isset( $provider['incidents'] ) && is_array( $provider['incidents'] ) ? $provider['incidents'] : [];
        foreach ( $incidents as $incident ) {
            if ( ! is_array( $incident ) ) { return false; }
            $needs = ! empty( $incident['region_name'] ) || ! empty( $incident['region_code'] ) || ! empty( $incident['is_long_running'] ) || ( isset( $incident['scope'] ) && 'regional' === strtolower( (string) $incident['scope'] ) );
            if ( $needs ) {
                foreach ( [ 'status', 'scope', 'last_official_update', 'checked_at', 'display_title', 'source_title' ] as $field ) {
                    if ( ! array_key_exists( $field, $incident ) ) { return false; }
                }
            }
        }
    }
    return true;
}

function lousy_outages_incident_value( array $incident, string $snake, string $camel = '' ) {
    if ( array_key_exists( $snake, $incident ) ) { return $incident[ $snake ]; }
    if ( $camel && array_key_exists( $camel, $incident ) ) { return $incident[ $camel ]; }
    return null;
}

function lousy_outages_display_title_for_incident( string $id, array $state, array $incident ): string {
    $provider = (string) ( $state['name'] ?? $state['provider'] ?? $id );
    $source = trim( (string) ( lousy_outages_incident_value( $incident, 'source_title' ) ?? lousy_outages_incident_value( $incident, 'title' ) ?? '' ) );
    $service = trim( (string) ( lousy_outages_incident_value( $incident, 'service_name' ) ?? lousy_outages_incident_value( $incident, 'service' ) ?? '' ) );
    $region_name = trim( (string) ( lousy_outages_incident_value( $incident, 'region_name', 'regionName' ) ?? '' ) );
    $region_code = trim( (string) ( lousy_outages_incident_value( $incident, 'region_code', 'regionCode' ) ?? '' ) );
    $status = strtolower( (string) ( lousy_outages_incident_value( $incident, 'status' ) ?? lousy_outages_incident_value( $incident, 'impact' ) ?? $state['status'] ?? '' ) );
    $generic = preg_match( '/^(increased error rates|operational issue\s*-\s*multiple services(?:\s*\([^)]*\))?|multiple services|service disruption|major outage reported\.?|service degradation reported\.?)$/i', $source ) === 1;
    if ( $generic && ( $region_name || $region_code || $service || 'aws' === strtolower( $id ) ) ) {
        $service_label = $service ?: ( false !== stripos( $source, 'multiple services' ) ? 'Multiple services' : $provider . ' services' );
        if ( 'aws' === strtolower( $id ) && false === stripos( $service_label, 'aws' ) ) {
            $service_label = str_ireplace( 'Multiple services', 'Multiple AWS services', $service_label );
            if ( false === stripos( $service_label, 'AWS' ) ) { $service_label = $provider . ' ' . lcfirst( $service_label ); }
        }
        $verb = in_array( $status, [ 'outage', 'major', 'critical', 'disrupted' ], true ) ? 'disrupted' : 'degraded';
        $where = $region_name ? ' in ' . $region_name : '';
        if ( $region_code ) { $where .= ' (' . $region_code . ')'; }
        return trim( $service_label . ' ' . $verb . $where );
    }
    return $source ?: (string) ( $incident['summary'] ?? $state['summary'] ?? 'Incident' );
}

function lousy_outages_map_incident_payload( string $id, array $state, array $incident, string $fetched_at ): array {
    $source_title = (string) ( lousy_outages_incident_value( $incident, 'source_title', 'sourceTitle' ) ?? lousy_outages_incident_value( $incident, 'title' ) ?? 'Incident' );
    $display_title = (string) ( lousy_outages_incident_value( $incident, 'display_title', 'displayTitle' ) ?? lousy_outages_display_title_for_incident( $id, $state, array_merge( $incident, [ 'source_title' => $source_title ] ) ) );
    return [
        'id'        => (string) ( $incident['id'] ?? md5( $id . wp_json_encode( $incident ) ) ),
        'title'     => $source_title,
        'display_title' => $display_title,
        'displayTitle' => $display_title,
        'source_title' => $source_title,
        'sourceTitle' => $source_title,
        'summary'   => $incident['summary'] ?? '',
        'startedAt' => lousy_outages_incident_value( $incident, 'started_at', 'startedAt' ) ?? '',
        'updatedAt' => lousy_outages_incident_value( $incident, 'updated_at', 'updatedAt' ) ?? '',
        'impact'    => lousy_outages_incident_value( $incident, 'impact' ) ?? 'minor',
        'status'    => lousy_outages_incident_value( $incident, 'status' ) ?? lousy_outages_incident_value( $incident, 'impact' ) ?? 'minor',
        'scope'     => lousy_outages_incident_value( $incident, 'scope' ) ?? '',
        'region_name' => lousy_outages_incident_value( $incident, 'region_name', 'regionName' ) ?? '',
        'region_code' => lousy_outages_incident_value( $incident, 'region_code', 'regionCode' ) ?? '',
        'is_long_running' => (bool) ( lousy_outages_incident_value( $incident, 'is_long_running', 'isLongRunning' ) ?? false ),
        'last_official_update' => lousy_outages_incident_value( $incident, 'last_official_update', 'lastOfficialUpdate' ) ?? lousy_outages_incident_value( $incident, 'updated_at', 'updatedAt' ) ?? '',
        'checked_at' => lousy_outages_incident_value( $incident, 'checked_at', 'checkedAt' ) ?? ( $state['checked_at'] ?? $fetched_at ),
        'eta'       => $incident['eta'] ?? 'investigating',
        'url'       => $incident['url'] ?? ( $state['url'] ?? '' ),
    ];
}

function lousy_outages_get_snapshot( bool $force_refresh = false ): array {
    $cache_key = lousy_outages_snapshot_cache_key();
    if ( ! $force_refresh ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['providers'] ) && lousy_outages_snapshot_schema_is_current( $cached ) ) {
            return lousy_outages_filter_snapshot( $cached );
        }
    }

    $stored = get_option( 'lousy_outages_snapshot', [] );
    if ( ! $force_refresh && is_array( $stored ) && ! empty( $stored['providers'] ) && lousy_outages_snapshot_schema_is_current( $stored ) ) {
        $filtered = lousy_outages_filter_snapshot( $stored );
        lousy_outages_store_snapshot( $filtered );
        return $filtered;
    }

    $store  = new Store();
    $states = $store->get_all();
    if ( ! $force_refresh && empty( $states ) ) {
        return is_array( $stored ) ? lousy_outages_filter_snapshot( $stored ) : [];
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
    add_menu_page( 'Lousy Outages', 'Lousy Outages', 'manage_options', 'lousy-outages', 'lousy_outages_admin_dashboard_page', 'dashicons-warning', 58 );
    add_submenu_page( 'lousy-outages', 'Dashboard', 'Dashboard', 'manage_options', 'lousy-outages', 'lousy_outages_admin_dashboard_page' );
    add_submenu_page( 'lousy-outages', 'Providers', 'Providers', 'manage_options', 'lousy-outages-providers', 'lousy_outages_admin_providers_page' );
    add_submenu_page( 'lousy-outages', 'Subscribers', 'Subscribers', 'manage_options', 'lousy-outages-subscribers', 'lousy_outages_admin_subscribers_page' );
    add_submenu_page( 'lousy-outages', 'Community Signals', 'Community Signals', 'manage_options', 'lousy-outages-community-signals', 'lousy_outages_admin_community_signals_page' );
    add_submenu_page( 'lousy-outages', 'External Signals', 'External Signals', 'manage_options', 'lousy-outages-external-signals', 'lousy_outages_admin_external_signals_page' );
    add_submenu_page( 'lousy-outages', 'Settings', 'Settings', 'manage_options', 'lousy-outages-settings', 'lousy_outages_settings_page' );
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

    register_setting( 'lousy_outages', 'lousy_outages_public_chatter_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'1'] );
    register_setting( 'lousy_outages', 'lousy_outages_public_chatter_bluesky_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'1'] );
    register_setting( 'lousy_outages', 'lousy_outages_public_chatter_mastodon_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'1'] );
    register_setting( 'lousy_outages', 'lousy_outages_public_chatter_gdelt_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'1'] );
    register_setting( 'lousy_outages', 'lousy_outages_public_chatter_reddit_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'0'] );
    register_setting( 'lousy_outages', 'lousy_outages_reddit_client_id', ['sanitize_callback'=>'sanitize_text_field','default'=>''] );
    register_setting( 'lousy_outages', 'lousy_outages_reddit_client_secret', ['sanitize_callback'=>'sanitize_text_field','default'=>''] );
    register_setting( 'lousy_outages', 'lousy_outages_reddit_user_agent', ['sanitize_callback'=>'sanitize_text_field','default'=>''] );
    register_setting( 'lousy_outages', 'lousy_outages_reddit_subreddits', ['sanitize_callback'=>'sanitize_textarea_field','default'=>''] );
    register_setting( 'lousy_outages', 'lousy_outages_cloudflare_radar_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'0'] );
    register_setting( 'lousy_outages', 'lousy_outages_cloudflare_radar_token', ['sanitize_callback'=>'sanitize_text_field','default'=>''] );
    register_setting( 'lousy_outages', 'lousy_outages_caida_ioda_enabled', ['sanitize_callback'=>'lousy_outages_sanitize_checkbox','default'=>'0'] );

    register_setting(
        'lousy_outages',
        'lousy_outages_probes',
        [
            'sanitize_callback' => 'lousy_outages_sanitize_probes_option',
            'default'           => [],
        ]
    );
} );


function lousy_outages_initialize_public_chatter_defaults(): void {
    $defaults = [
        'lousy_outages_public_chatter_enabled' => '1',
        'lousy_outages_public_chatter_bluesky_enabled' => '1',
        'lousy_outages_public_chatter_mastodon_enabled' => '1',
        'lousy_outages_public_chatter_gdelt_enabled' => '1',
        'lousy_outages_public_chatter_reddit_enabled' => '0',
        'lousy_outages_cloudflare_radar_enabled' => '0',
        'lousy_outages_caida_ioda_enabled' => '0',
    ];
    foreach ( $defaults as $option => $value ) {
        if ( null === get_option( $option, null ) ) {
            update_option( $option, $value, false );
        }
    }
}
add_action( 'init', 'lousy_outages_initialize_public_chatter_defaults', 1 );
add_action( 'admin_init', 'lousy_outages_initialize_public_chatter_defaults', 1 );

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
                
                <?php
                $lo_public_chatter_source = class_exists('\\SuzyEaston\\LousyOutages\\Sources\\PublicChatterSource') ? new \SuzyEaston\LousyOutages\Sources\PublicChatterSource() : null;
                $lo_public_chatter_master_enabled = ! empty( get_option( 'lousy_outages_public_chatter_enabled', '1' ) );
                $lo_public_chatter_direct_enabled = $lo_public_chatter_source->direct_sources_enabled();
                $lo_public_chatter_source_statuses = [
                    'Bluesky' => ! empty( get_option( 'lousy_outages_public_chatter_bluesky_enabled', '1' ) ),
                    'Mastodon' => ! empty( get_option( 'lousy_outages_public_chatter_mastodon_enabled', '1' ) ),
                    'GDELT' => ! empty( get_option( 'lousy_outages_public_chatter_gdelt_enabled', '1' ) ),
                    'Reddit' => ! empty( get_option( 'lousy_outages_public_chatter_reddit_enabled', '0' ) ),
                ];
                $lo_source_registry = \SuzyEaston\LousyOutages\Sources\SourceRegistry::definitions();
                $lo_source_runtime = \SuzyEaston\LousyOutages\Sources\SourceRegistry::runtime_statuses( (array) get_option( 'lo_diag_public_chatter', [] ) );
                ?>
                <?php
                $lo_public_chatter_diag = (array) get_option( 'lo_diag_public_chatter', [] );
                $lo_gdelt_cooldown = (string) ( $lo_public_chatter_diag['gdelt_cooldown_until'] ?? '' );
                $lo_gdelt_limited = ! empty( $lo_public_chatter_diag['gdelt_rate_limited'] ) || $lo_gdelt_cooldown !== '';
                ?>
                <tr><th scope="row">Sources / Corroboration</th><td>
                    <p><strong>Official/status feeds:</strong> enabled by default, trusted, and do not change email behavior.</p>
                    <p><strong>Public/social:</strong> Bluesky is real-time public search; Mastodon is instance-limited federated search; HN is developer/forum chatter; Reddit is disabled until official API/OAuth credentials are configured.</p>
                    <p><strong>Open web:</strong> GDELT is open-web/news corroboration with budget/cooldown protection. <strong>Internet health:</strong> Cloudflare Radar and CAIDA IODA are telemetry and do not prove SaaS application outages. <strong>Community:</strong> first-party reports remain unconfirmed.</p>
                    <p class="description">Enabled sources affect dashboard diagnostics/watch candidates only. Email alerts remain thresholded; community reports remains unconfirmed and raw social post text is not shown publicly.</p>
                    <p><strong>Master Public Chatter Radar:</strong> <?php echo esc_html( $lo_public_chatter_master_enabled ? 'Enabled' : 'Disabled' ); ?> | <strong>Direct public source gate:</strong> <?php echo esc_html( $lo_public_chatter_direct_enabled ? 'Enabled' : 'Disabled' ); ?></p>
                    <?php if ( ! $lo_public_chatter_direct_enabled ) : ?>
                        <div class="notice notice-warning inline"><p><?php echo esc_html__( 'Direct community reports source gate is disabled. Saved source checkboxes will be visible but collection is blocked until the gate is enabled.', 'lousy-outages' ); ?></p></div>
                    <?php endif; ?>
                    <?php if ( $lo_gdelt_limited ) : ?>
                        <div class="notice notice-warning inline"><p><?php echo esc_html( $lo_gdelt_cooldown !== '' ? 'GDELT is temporarily in cooldown after a rate-limit/error response. It will resume automatically; do not disable it permanently.' : 'GDELT recently reported rate limiting. It remains enabled and budgeted.' ); ?></p></div>
                    <?php endif; ?>
                    <input type="hidden" name="lousy_outages_public_chatter_enabled" value="0"><label><input type="checkbox" name="lousy_outages_public_chatter_enabled" value="1" <?php checked( $lo_public_chatter_master_enabled ); ?>> Enable Public Chatter Radar</label><br>
                    <input type="hidden" name="lousy_outages_public_chatter_bluesky_enabled" value="0"><label><input type="checkbox" name="lousy_outages_public_chatter_bluesky_enabled" value="1" <?php checked( $lo_public_chatter_source_statuses['Bluesky'] ); ?>> Enable Bluesky source</label> <em>Bluesky: <?php echo esc_html( $lo_public_chatter_source_statuses['Bluesky'] ? ( $lo_public_chatter_direct_enabled ? 'enabled' : 'enabled, gate disabled' ) : 'disabled' ); ?></em><br>
                    <input type="hidden" name="lousy_outages_public_chatter_mastodon_enabled" value="0"><label><input type="checkbox" name="lousy_outages_public_chatter_mastodon_enabled" value="1" <?php checked( $lo_public_chatter_source_statuses['Mastodon'] ); ?>> Enable Mastodon source</label> <em>Mastodon: <?php echo esc_html( $lo_public_chatter_source_statuses['Mastodon'] ? ( $lo_public_chatter_direct_enabled ? 'enabled' : 'enabled, gate disabled' ) : 'disabled' ); ?></em><br>
                    <input type="hidden" name="lousy_outages_public_chatter_gdelt_enabled" value="0"><label><input type="checkbox" name="lousy_outages_public_chatter_gdelt_enabled" value="1" <?php checked( $lo_public_chatter_source_statuses['GDELT'] ); ?>> Enable GDELT source</label> <em>GDELT: <?php echo esc_html( $lo_public_chatter_source_statuses['GDELT'] ? ( $lo_gdelt_limited ? 'enabled, rate-limited/cooldown' : ( $lo_public_chatter_direct_enabled ? 'enabled' : 'enabled, gate disabled' ) ) : 'disabled' ); ?></em><br>
                    <input type="hidden" name="lousy_outages_public_chatter_reddit_enabled" value="0"><label><input type="checkbox" name="lousy_outages_public_chatter_reddit_enabled" value="1" <?php checked( $lo_public_chatter_source_statuses['Reddit'] ); ?>> Enable Reddit source</label> <em>Reddit: <?php echo esc_html( \SuzyEaston\LousyOutages\Sources\SourceRegistry::reddit_credentials_configured() ? ( $lo_public_chatter_source_statuses['Reddit'] ? 'configured' : 'configured, disabled' ) : 'not configured' ); ?></em><br>
                    <label for="lo_reddit_client_id">Reddit client ID</label><br><input id="lo_reddit_client_id" type="text" name="lousy_outages_reddit_client_id" value="<?php echo esc_attr( get_option( 'lousy_outages_reddit_client_id', '' ) ); ?>" class="regular-text"><br>
                    <label for="lo_reddit_client_secret">Reddit client secret</label><br><input id="lo_reddit_client_secret" type="password" name="lousy_outages_reddit_client_secret" value="<?php echo esc_attr( get_option( 'lousy_outages_reddit_client_secret', '' ) ); ?>" class="regular-text"><br>
                    <label for="lo_reddit_user_agent">Reddit user agent</label><br><input id="lo_reddit_user_agent" type="text" name="lousy_outages_reddit_user_agent" value="<?php echo esc_attr( get_option( 'lousy_outages_reddit_user_agent', '' ) ); ?>" class="regular-text"><br>
                    <label for="lo_reddit_subreddits">Subreddits</label><br><textarea id="lo_reddit_subreddits" name="lousy_outages_reddit_subreddits" rows="4" cols="60"><?php echo esc_textarea( get_option( 'lousy_outages_reddit_subreddits', '' ) ); ?></textarea>
                    <p class="description">Reddit uses official API/OAuth only; no scraping or unauthenticated HTML endpoints. Raw Reddit post text is not shown publicly.</p>
                    <input type="hidden" name="lousy_outages_cloudflare_radar_enabled" value="0"><label><input type="checkbox" name="lousy_outages_cloudflare_radar_enabled" value="1" <?php checked( ! empty( get_option( 'lousy_outages_cloudflare_radar_enabled', '0' ) ) ); ?>> Enable Cloudflare Radar telemetry</label><br>
                    <label for="lo_cloudflare_radar_token">Cloudflare Radar token</label><br><input id="lo_cloudflare_radar_token" type="password" name="lousy_outages_cloudflare_radar_token" value="<?php echo esc_attr( get_option( 'lousy_outages_cloudflare_radar_token', '' ) ); ?>" class="regular-text"><br>
                    <input type="hidden" name="lousy_outages_caida_ioda_enabled" value="0"><label><input type="checkbox" name="lousy_outages_caida_ioda_enabled" value="1" <?php checked( ! empty( get_option( 'lousy_outages_caida_ioda_enabled', '0' ) ) ); ?>> Enable CAIDA IODA telemetry placeholder</label> <em>Optional telemetry stub; future collection should only corroborate country/ASN-level disruption.</em>
                    <p class="description">GDELT is open-web/news corroboration with a very small per-run budget, success cache, and automatic cooldown/backoff. Cloudflare Radar remains internet-health telemetry, not rumour radar.</p>
                    <details><summary>Source lane registry</summary><table class="widefat striped"><thead><tr><th>Source</th><th>Lane</th><th>Trust</th><th>Capability</th><th>Status</th></tr></thead><tbody><?php foreach ( $lo_source_registry as $lo_source_key => $lo_source_def ) : $lo_runtime = (array) ( $lo_source_runtime[ $lo_source_key ] ?? [] ); ?><tr><td><?php echo esc_html( (string) ( $lo_source_def['display_name'] ?? $lo_source_key ) ); ?></td><td><?php echo esc_html( (string) ( $lo_source_def['lane'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $lo_source_def['trust_level'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $lo_source_def['source_capability'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $lo_runtime['status'] ?? 'unknown' ) ); ?></td></tr><?php endforeach; ?></tbody></table></details>
                </td></tr>

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
            <?php submit_button( 'Send Legacy Test Email', 'secondary' ); ?>
        </form>
        <p class="description">Legacy test verifies the old basic mail transport only; it does not exercise the modern incident pipeline.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 0.5rem;">
            <?php wp_nonce_field( 'lousy_outages_synthetic_alert' ); ?>
            <input type="hidden" name="action" value="lousy_outages_synthetic_alert">
            <label><input type="checkbox" name="notification_only" value="1" checked> Test configured notification inbox only</label>
            <?php submit_button( 'Send Synthetic Incident Alert', 'secondary', 'submit', false ); ?>
        </form>
        <p class="description">Synthetic incident verifies the modern template and notification pipeline.</p>

        <?php $lo_replay_candidate = IncidentAlerts::latest_replay_candidate(); ?>
        <h2>Alert Health</h2>
        <?php $lo_alert_health = IncidentAlerts::alert_health(); ?>
        <table class="widefat striped"><tbody><?php foreach ( $lo_alert_health as $lo_health_key => $lo_health_value ) : ?><tr><th scope="row"><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $lo_health_key ) ) ); ?></th><td><?php echo esc_html( is_scalar( $lo_health_value ) ? (string) $lo_health_value : wp_json_encode( $lo_health_value ) ); ?></td></tr><?php endforeach; ?></tbody></table>
        <h3>Replay latest real incident to notification inbox</h3>
        <?php if ( $lo_replay_candidate ) : ?><p><strong>Provider:</strong> <?php echo esc_html( $lo_replay_candidate->provider ); ?> · <strong>Title:</strong> <?php echo esc_html( $lo_replay_candidate->title ); ?> · <strong>Status:</strong> <?php echo esc_html( $lo_replay_candidate->status ); ?> · <strong>Detected:</strong> <?php echo esc_html( gmdate( 'c', $lo_replay_candidate->detected_at ) ); ?> · <strong>Source:</strong> stored canonical incident</p><?php else : ?><p>No replayable real incident is currently retained.</p><?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 0.5rem;">
            <?php wp_nonce_field( 'lousy_outages_replay_latest_real_incident' ); ?>
            <input type="hidden" name="action" value="lousy_outages_replay_latest_real_incident">
            <?php submit_button( 'Replay latest real incident to notification inbox', 'secondary', 'submit', false, $lo_replay_candidate ? [] : [ 'disabled' => 'disabled' ] ); ?>
        </form>
        <p class="description">Replay sends actual stored incident content only to the configured administrative notification inbox; public subscribers are never included.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 0.5rem;">
            <?php wp_nonce_field( 'lousy_outages_test_sms' ); ?>
            <input type="hidden" name="action" value="lousy_outages_test_sms">
            <?php submit_button( 'Send Test SMS', 'secondary' ); ?>
        </form>

        <p class="description">SMS requires Twilio SID/Auth/From and your phone set. Alternatively subscribe to RSS on your phone.</p>

        <h2>Debug panel</h2>
        <p><strong>Last poll:</strong> <?php echo esc_html( $last_poll_text ); ?></p>
        <p><strong>Legacy test email:</strong> <?php echo esc_html( $last_email_text ); ?></p>
        <p><strong>Modern alert delivery:</strong> <?php echo esc_html( wp_json_encode( get_option( 'lousy_outages_last_alert_delivery_result', [] ) ) ); ?></p>
        <p><strong>Modern synthetic alert:</strong> <?php echo esc_html( wp_json_encode( get_option( 'lousy_outages_last_synthetic_alert', [] ) ) ); ?></p>
        <p><strong>Modern real alert success:</strong> <?php echo esc_html( wp_json_encode( get_option( 'lousy_outages_last_alert_success', [] ) ) ); ?></p>
        <p><strong>Modern real alert failure:</strong> <?php echo esc_html( wp_json_encode( get_option( 'lousy_outages_last_alert_failure', [] ) ) ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 1rem;">
            <?php wp_nonce_field( 'lousy_outages_poll_now' ); ?>
            <input type="hidden" name="action" value="lousy_outages_queue_poll">
            <?php submit_button( 'Queue Poll', 'secondary', 'submit', false ); ?>
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

        <?php $diag = UserReports::get_admin_diagnostics(60); $topSignal = !empty($diag['signals'][0]['classification']) ? (string)$diag['signals'][0]['classification'] : 'quiet'; ?>
        <h2>Community Signal Diagnostics</h2>
        <p><strong>Total community reports in last hour:</strong> <?php echo esc_html((string)($diag['total_reports'] ?? 0)); ?></p>
        <p><strong>Providers with reports:</strong> <?php echo esc_html((string)count((array)($diag['provider_counts'] ?? []))); ?></p>
        <p><strong>Top signal classification:</strong> <?php echo esc_html(ucfirst($topSignal)); ?> (unconfirmed community signal)</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <?php wp_nonce_field('lousy_outages_seed_demo_reports'); ?><input type="hidden" name="action" value="lousy_outages_seed_demo_reports"><?php submit_button('Seed Demo Community Reports', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
            <?php wp_nonce_field('lousy_outages_clear_demo_reports'); ?><input type="hidden" name="action" value="lousy_outages_clear_demo_reports"><?php submit_button('Clear Demo Community Reports', 'delete', 'submit', false); ?>
        </form>
        <?php $external = ExternalSignals::get_recent_signals(['windowMinutes'=>60,'limit'=>8]); $fused = SignalEngine::summarize_fused_signals(60); $collector = SignalCollector::get_last_collection_result(); ?>
        <h2>External Signal Diagnostics</h2>
        <p><strong>Last external collection:</strong> <?php echo esc_html((string)($collector['finished_at'] ?? 'Never')); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;"><?php wp_nonce_field('lousy_outages_collect_signals_now'); ?><input type="hidden" name="action" value="lousy_outages_collect_signals_now"><?php submit_button('Collect External Signals Now','secondary','submit',false); ?></form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;"><?php wp_nonce_field('lousy_outages_seed_demo_external_signals'); ?><input type="hidden" name="action" value="lousy_outages_seed_demo_external_signals"><?php submit_button('Seed Demo External Signals','secondary','submit',false); ?></form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;"><?php wp_nonce_field('lousy_outages_clear_demo_external_signals'); ?><input type="hidden" name="action" value="lousy_outages_clear_demo_external_signals"><?php submit_button('Clear Demo External Signals','delete','submit',false); ?></form>
        
        <?php $feedDiag = class_exists('SuzyEaston\LousyOutages\Feeds') ? SuzyEaston\LousyOutages\Feeds::get_status_feed_diagnostics() : []; ?>
        <h2>RSS Feed Diagnostics</h2>
        <p><strong>Feed URL:</strong> <?php echo esc_html(home_url('/?feed=lousy_outages_status')); ?></p>
        <p><strong>Admin test URL:</strong> <?php echo esc_html(home_url('/?feed=lousy_outages_status&lo_nocache=1')); ?></p>
        <p><strong>Last rendered:</strong> <?php echo esc_html((string)($feedDiag['render_timestamp'] ?? 'Never')); ?> | <strong>lastBuildDate:</strong> <?php echo esc_html((string)($feedDiag['last_build'] ?? 'n/a')); ?> | <strong>Items:</strong> <?php echo esc_html((string)($feedDiag['item_count'] ?? 0)); ?></p>
        <p><strong>Renderer source:</strong> <?php echo esc_html((string)($feedDiag['renderer_source'] ?? 'unknown')); ?> | <strong>Cache:</strong> <?php echo esc_html((string)($feedDiag['cache_status'] ?? 'n/a')); ?> | <strong>Last cache clear:</strong> <?php echo esc_html((string)($feedDiag['last_cache_clear_time'] ?? 'Never')); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;"><?php wp_nonce_field('lousy_outages_clear_rss_feed_cache'); ?><input type="hidden" name="action" value="lousy_outages_clear_rss_feed_cache"><?php submit_button('Clear RSS Feed Cache','secondary','submit',false); ?></form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;"><?php wp_nonce_field('lousy_outages_public_chatter_dry_run'); ?><input type="hidden" name="action" value="lousy_outages_public_chatter_dry_run"><?php submit_button('Run Public Chatter Dry Run','secondary','submit',false); ?></form>

        <h3>Fused signals (community + official status resources)</h3><ul><?php foreach (array_slice((array)$fused,0,8) as $row) : ?><li><?php echo esc_html((string)($row['provider_name'] ?? $row['provider_id'])); ?> — <?php echo esc_html((string)($row['classification'] ?? 'quiet')); ?> (<?php echo esc_html((string)($row['confidence'] ?? 0)); ?>) — <?php echo esc_html((string)($row['message'] ?? '')); ?></li><?php endforeach; ?></ul>
        <h3>Recent external signals</h3><ul><?php foreach((array)$external as $row) : ?><li><?php echo esc_html((string)($row['observed_at'] ?? '')); ?> · <?php echo esc_html((string)($row['source'] ?? '')); ?> · <?php echo esc_html((string)($row['provider_name'] ?? $row['provider_id'] ?? '')); ?> · <?php echo esc_html((string)($row['signal_type'] ?? '')); ?> · <?php echo esc_html((string)($row['title'] ?? '')); ?></li><?php endforeach; ?></ul>
        <h3>Top providers by report count</h3>
        <ul><?php foreach ((array)($diag['provider_counts'] ?? []) as $row) : ?><li><?php echo esc_html((string)($row['provider_id'] ?? 'unknown')); ?>: <?php echo esc_html((string)($row['report_count'] ?? 0)); ?></li><?php endforeach; ?></ul>
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

add_action( 'admin_post_lousy_outages_synthetic_alert', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) );
    }
    check_admin_referer( 'lousy_outages_synthetic_alert' );
    $notification_only = ! empty( $_POST['notification_only'] );
    $incident = IncidentAlerts::make_synthetic_incident();
    $result = IncidentAlerts::process_incidents(
        [ $incident ],
        [
            'synthetic'         => true,
            'mode'              => 'admin_synthetic',
            'notification_only' => $notification_only,
        ]
    );
    $ok = (int) ( $result['sent'] ?? 0 ) > 0 && (int) ( $result['failed'] ?? 0 ) === 0;
    $message = $ok
        ? sprintf( 'Synthetic incident alert sent to %s at %s.', implode( ', ', (array) ( $result['recipients'] ?? [] ) ), gmdate( 'c' ) )
        : sprintf( 'Synthetic incident alert failed. Reason: %s. Check Debug panel and logs.', implode( '; ', (array) ( $result['failures'] ?? [] ) ) );
    set_transient(
        'lousy_outages_notice',
        [ 'message' => $message, 'type' => $ok ? 'success' : 'error' ],
        30
    );
    wp_safe_redirect( add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) ) );
    exit;
} );


add_action( 'admin_post_lousy_outages_replay_latest_real_incident', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) );
    }
    if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
        wp_die( esc_html__( 'Replay must be submitted with POST.', 'lousy-outages' ) );
    }
    check_admin_referer( 'lousy_outages_replay_latest_real_incident' );
    $result = IncidentAlerts::replay_latest_real_incident_to_notification_inbox();
    set_transient(
        'lousy_outages_notice',
        [
            'message' => ! empty( $result['ok'] ) ? 'Replay sent to configured notification inbox. Public subscribers were not notified.' : 'Replay failed: ' . (string) ( $result['error'] ?? 'unknown error' ),
            'type'    => ! empty( $result['ok'] ) ? 'success' : 'error',
        ],
        30
    );
    wp_safe_redirect( add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) ) );
    exit;
} );

add_action( 'admin_post_lousy_outages_queue_poll', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) ); }
    check_admin_referer( 'lousy_outages_poll_now' );

    if ( ! (bool) get_option( 'lousy_outages_allow_sync_poll_now', false ) ) {
        set_transient( 'lousy_outages_notice', [ 'message' => 'Synchronous Poll Now is disabled for stability. Use Queue Poll instead.', 'type' => 'warning' ], 30 );
        wp_safe_redirect( add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) ) );
        exit;
    }
    $hook = 'lousy_outages_refresh_official_providers';
    $nextRun = time() + 30;
    $scheduled = wp_schedule_single_event( $nextRun, $hook ) !== false;
    if ( ! $scheduled ) {
        $existing = wp_next_scheduled( $hook );
        if ( is_numeric( $existing ) && (int) $existing > 0 ) { $scheduled = true; $nextRun = (int) $existing; }
    }
    update_option( 'lousy_outages_last_poll_queue_result', [
        'timestamp' => gmdate('c'),
        'scheduled' => (bool) $scheduled,
        'hook_name' => $hook,
        'next_run'  => $scheduled ? gmdate('c', (int) $nextRun) : null,
    ], false );
    set_transient( 'lousy_outages_notice', [
        'message' => $scheduled ? 'Provider poll queued. Refresh the dashboard shortly.' : 'Could not queue provider poll. Check WP-Cron configuration and try again.',
        'type'    => $scheduled ? 'success' : 'error',
    ], 30 );
    wp_safe_redirect( add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) ) );
    exit;
} );

add_action( 'admin_post_lousy_outages_poll_now', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) );
    }

    check_admin_referer( 'lousy_outages_poll_now' );

    $started = microtime( true );
    $memoryBefore = function_exists( 'memory_get_usage' ) ? memory_get_usage( true ) : 0;
    $result  = [
        'timestamp'      => gmdate( 'c' ),
        'success'        => false,
        'provider_count' => 0,
        'changed_count'  => 0,
        'errors'         => [],
        'duration_ms'    => 0,
        'triggered_by'   => 'admin_canonical_refresh',
        'memory_before'  => $memoryBefore,
        'memory_after'   => 0,
        'memory_peak'    => 0,
    ];
    $notice = [ 'message' => 'Poll failed safely. Check diagnostics/logs for details.', 'type' => 'error' ];
    try {
        $refresh = lousy_outages_refresh_official_providers( true );
        $result['provider_count'] = is_array( $refresh['providers'] ?? null ) ? count( $refresh['providers'] ) : 0;
        $result['alert_diagnostics'] = $refresh['alert_diagnostics'] ?? [];
        $result['success']        = true;
        if ( class_exists( 'SuzyEaston\LousyOutages\Feeds' ) ) {
            SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache();
        }
        $notice = [ 'message' => sprintf( 'Canonical refresh completed (%d providers).', (int) $result['provider_count'] ), 'type' => 'success' ];
    } catch ( \Throwable $e ) {
        $result['errors'][] = $e->getMessage();
        error_log( '[LO] poll_now_failed ' . wp_json_encode( [ 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ] ) );
    }
    $result['duration_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );
    $result['memory_after'] = function_exists( 'memory_get_usage' ) ? memory_get_usage( true ) : 0;
    $result['memory_peak'] = function_exists( 'memory_get_peak_usage' ) ? memory_get_peak_usage( true ) : 0;
    update_option( 'lousy_outages_last_poll_now_result', $result, false );
    set_transient( 'lousy_outages_notice', $notice, 30 );

    $redirect = add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
} );


add_action( 'admin_post_lousy_outages_seed_demo_reports', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) ); }
    check_admin_referer( 'lousy_outages_seed_demo_reports' );
    $result = UserReports::seed_demo_reports(); if (class_exists('SuzyEaston\LousyOutages\Feeds')) { SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache(); }
    set_transient('lousy_outages_notice',['message'=>sprintf('Seeded %d demo community reports.', (int)($result['inserted'] ?? 0)),'type'=>'success'],30);
    wp_safe_redirect( add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) ) ); exit;
} );

add_action( 'admin_post_lousy_outages_clear_demo_reports', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lousy-outages' ) ); }
    check_admin_referer( 'lousy_outages_clear_demo_reports' );
    $count = UserReports::clear_demo_reports(); if (class_exists('SuzyEaston\LousyOutages\Feeds')) { SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache(); }
    set_transient('lousy_outages_notice',['message'=>sprintf('Cleared %d demo community reports.', (int)$count),'type'=>'success'],30);
    wp_safe_redirect( add_query_arg( 'page', 'lousy-outages', admin_url( 'options-general.php' ) ) ); exit;
} );

function lousy_outages_build_provider_payload( string $id, array $state, string $fetched_at ): array {
    $status     = $state['status'] ?? 'unknown';
    $label      = Fetcher::status_label( $status );
    $updated_at = ! empty( $state['updated_at'] ) ? (string) $state['updated_at'] : $fetched_at;
    $incidents  = [];
    $recent_incidents = [];
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

    // LO: Split active vs recent incidents so resolved history never drives badges or sorting.
    if ( ! empty( $state['incidents'] ) && is_array( $state['incidents'] ) ) {
        foreach ( $state['incidents'] as $incident ) {
            if ( ! is_array( $incident ) ) {
                continue;
            }
            $incidents[] = lousy_outages_map_incident_payload( $id, $state, $incident, $fetched_at );
        }
    }
    if ( ! empty( $state['recent_incidents'] ) && is_array( $state['recent_incidents'] ) ) {
        foreach ( $state['recent_incidents'] as $incident ) {
            if ( ! is_array( $incident ) ) {
                continue;
            }
            $recent_incidents[] = lousy_outages_map_incident_payload( $id, $state, $incident, $fetched_at );
        }
    }

    if ( 'aws' === strtolower( $id ) && empty( $incidents ) ) {
        $observed_ts = $updated_at ? ( strtotime( $updated_at ) ?: 0 ) : 0;
        $freshness_window = (int) apply_filters( 'lousy_outages_provider_signal_freshness_seconds', 6 * HOUR_IN_SECONDS, $id );
        $has_ongoing_state = ! in_array( strtolower( (string) $status ), [ 'ok', 'none', 'operational', 'resolved', 'unknown' ], true );
        if ( $observed_ts > 0 && ( time() - $observed_ts ) > $freshness_window && ! $has_ongoing_state ) {
            $status = 'operational';
            $label = Fetcher::status_label( $status );
            $state['tile_kind'] = 'operational';
            $state['summary'] = 'No current AWS incident in the freshness window.';
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
        'tile_kind'  => $state['tile_kind'] ?? null,
        'sort_key'   => $state['sort_key'] ?? null,
        'confidence'=> $state['confidence'] ?? null,
        'summary'    => $state['summary'] ?? $label,
        'verification_status' => $state['verification_status'] ?? ( ! empty( $state['error'] ) ? 'failed' : 'verified' ),
        'last_attempted_at' => $state['last_attempted_at'] ?? $state['checked_at'] ?? $fetched_at,
        'last_successful_at' => $state['last_successful_at'] ?? ( empty( $state['error'] ) ? ( $state['checked_at'] ?? $fetched_at ) : '' ),
        'data_observed_at' => $updated_at,
        'is_stale' => ! empty( $state['is_stale'] ),
        'fetch_error' => $state['fetch_error'] ?? $state['error'] ?? '',
        'checked_at' => $state['checked_at'] ?? $fetched_at,
        'checkedAt'  => $state['checked_at'] ?? $fetched_at,
        'last_official_update' => $state['last_official_update'] ?? '',
        'lastOfficialUpdate' => $state['last_official_update'] ?? '',
        'updatedAt'  => $updated_at,
        'url'        => $url,
        'snark'      => $state['snark'] ?? '',
        'incidents'  => $incidents,
        'recentIncidents' => $recent_incidents,
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
    $tile_priority = [
        'outage'      => 0,
        'signal'      => 1,
        'unknown'     => 2,
        'manual'      => 3,
        'operational' => 4,
    ];

    $sort_key = static function ( array $provider ) use ( $state_priority, $tile_priority ): array {
        $sort_key_value = $provider['sort_key'] ?? null;
        $tile_kind = strtolower( (string) ( $provider['tile_kind'] ?? '' ) );
        if ( is_numeric( $sort_key_value ) ) {
            $name = strtolower( (string) ( $provider['name'] ?? $provider['id'] ?? '' ) );
            $tile_rank = $tile_priority[ $tile_kind ] ?? $tile_priority['unknown'];
            return [
                (int) $sort_key_value,
                $tile_rank,
                $name,
            ];
        }

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

add_action( 'admin_post_lousy_outages_collect_signals_now', function () { if(!current_user_can('manage_options')){wp_die(esc_html__('Sorry, you are not allowed to access this page.','lousy-outages'));} check_admin_referer('lousy_outages_collect_signals_now'); $r=SignalCollector::collect(['collection_trigger'=>'admin']); if (class_exists('SuzyEaston\LousyOutages\Feeds')) { SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache(); } set_transient('lousy_outages_notice',['message'=>sprintf('External signal collection finished. Stored %d signals.',(int)($r['total_stored']??0)),'type'=>'success'],30); wp_safe_redirect(add_query_arg('page','lousy-outages-settings',admin_url('admin.php'))); exit; } );
add_action( 'admin_post_lousy_outages_seed_demo_external_signals', function () { if(!current_user_can('manage_options')){wp_die(esc_html__('Sorry, you are not allowed to access this page.','lousy-outages'));} check_admin_referer('lousy_outages_seed_demo_external_signals'); $r=ExternalSignals::seed_demo_signals(); if (class_exists('SuzyEaston\LousyOutages\Feeds')) { SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache(); } set_transient('lousy_outages_notice',['message'=>sprintf('Seeded %d demo external signals.',(int)($r['inserted']??0)),'type'=>'success'],30); wp_safe_redirect(add_query_arg('page','lousy-outages-settings',admin_url('admin.php'))); exit; } );
add_action( 'admin_post_lousy_outages_clear_demo_external_signals', function () { if(!current_user_can('manage_options')){wp_die(esc_html__('Sorry, you are not allowed to access this page.','lousy-outages'));} check_admin_referer('lousy_outages_clear_demo_external_signals'); $c=ExternalSignals::clear_demo_signals(); if (class_exists('SuzyEaston\LousyOutages\Feeds')) { SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache(); } set_transient('lousy_outages_notice',['message'=>sprintf('Cleared %d demo external signals.',(int)$c),'type'=>'success'],30); wp_safe_redirect(add_query_arg('page','lousy-outages-settings',admin_url('admin.php'))); exit; } );

function lousy_outages_admin_dashboard_page() { $zero=['total'=>0,'confirmed'=>0,'pending'=>0,'realtime'=>0,'digest'=>0,'newsletter'=>0]; $stats=(class_exists('SuzyEaston\LousyOutages\Subscriptions')&&method_exists('SuzyEaston\LousyOutages\Subscriptions','stats'))?SuzyEaston\LousyOutages\Subscriptions::stats():$zero; if(!is_array($stats)){$stats=$zero;} $stats=array_merge($zero,$stats); $providers = Providers::enabled(); $community = (class_exists('SuzyEaston\LousyOutages\UserReports')&&method_exists('SuzyEaston\LousyOutages\UserReports','recent')) ? SuzyEaston\LousyOutages\UserReports::recent(60,100) : []; if(!is_array($community)){$community=[];} $external = (class_exists('SuzyEaston\LousyOutages\ExternalSignals')&&method_exists('SuzyEaston\LousyOutages\ExternalSignals','recent')) ? SuzyEaston\LousyOutages\ExternalSignals::recent(60,100) : []; if(!is_array($external)){$external=[];} $fused = get_option('lousy_outages_signal_engine_last_fused', []); if ( ! is_array($fused) ) { $fused = []; } echo '<div class="wrap"><h1>Lousy Outages Dashboard</h1><p><strong>Last provider check:</strong> '.esc_html((($ts=lousy_outages_get_last_poll_timestamp())?wp_date('M j, Y g:i a',$ts):'—')).'</p><p class="description">Manual polling is queued for stability. Scheduled polling continues in the background.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:1120px;">'; $cards=['Monitored Providers'=>count($providers),'Active Subscribers'=>(int)($stats['confirmed']??0),'Community Reports Last Hour'=>count($community),'External Signals Last Hour'=>count($external),'Fused Signals'=>count($fused),'Last Poll'=>((($ts=lousy_outages_get_last_poll_timestamp())?wp_date('M j, Y g:i a',$ts):'—')),'Last External Collection'=>lousy_outages_format_external_collection_summary(get_option('lousy_outages_last_external_collection',null))]; foreach($cards as $label=>$value){ echo '<div style="border:1px solid #ccd0d4;background:#fff;padding:12px;"><strong>'.esc_html($label).'</strong><div style="font-size:18px;margin-top:8px;">'.esc_html((string)$value).'</div></div>'; } echo '</div><p style="margin-top:16px;"><a class="button" href="'.esc_url(home_url('/lousy-outages/')).'">View Public Page</a> <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=lousy_outages_poll_now'),'lousy_outages_poll_now')).'">Queue Poll</a> <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=lousy_outages_collect_signals_now'),'lousy_outages_collect_signals_now')).'">Collect External Signals Now</a> <a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=lousy-outages-settings')).'">Settings</a></p></div>'; }
function lousy_outages_admin_providers_page() { echo '<div class="wrap"><h1>Providers</h1><p>Provider table and diagnostics are available in Settings.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=lousy-outages-settings' ) ) . '">Open Settings</a></p></div>'; }
function lousy_outages_admin_subscribers_page() { $stats = class_exists('SuzyEaston\LousyOutages\Subscriptions') ? SuzyEaston\LousyOutages\Subscriptions::stats() : []; $diag=get_option('lousy_outages_last_alert_recipient_diagnostics',[]); $excluded=is_array($diag['excluded']??null)?$diag['excluded']:[]; echo '<div class="wrap"><h1>Subscribers</h1><p>Subscriber details are admin-only. Public visitors never see this.</p><ul><li>Total confirmed: '.esc_html((string)($stats['confirmed']??0)).'</li><li>Pending confirmations: '.esc_html((string)($stats['pending']??0)).'</li><li>Realtime alert subscribers: '.esc_html((string)($stats['realtime']??0)).'</li><li>Digest subscribers: '.esc_html((string)($stats['digest']??0)).'</li><li>Newsletter subscribers: '.esc_html((string)($stats['newsletter']??0)).'</li><li>All providers: '.esc_html((string)($stats['provider_all']??0)).'</li><li>Provider-specific: '.esc_html((string)($stats['provider_specific']??0)).'</li></ul>'; if(is_array($diag)&&!empty($diag)){ echo '<h2>Last alert recipients</h2><p><strong>Notification inbox:</strong> '.esc_html((string)($diag['notification_recipients'][0]??'—')).'</p><p><strong>Subscriber count:</strong> '.esc_html((string)($diag['subscriber_recipients_count']??0)).'</p><ul><li>Pending: '.esc_html((string)($excluded['pending']??0)).'</li><li>No realtime opt-in: '.esc_html((string)($excluded['no_realtime_opt_in']??0)).'</li><li>Provider preference mismatch: '.esc_html((string)($excluded['provider_preference_mismatch']??0)).'</li><li>Invalid email: '.esc_html((string)($excluded['invalid_email']??0)).'</li><li>Already sent/deduped: '.esc_html((string)($excluded['already_sent_deduped']??0)).'</li></ul>'; } echo '</div>'; }
function lousy_outages_admin_community_signals_page() { echo '<div class="wrap"><h1>Community Signals</h1><p>Community reports, top providers, and demo seed/clear actions are managed in Settings diagnostics.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=lousy-outages-settings' ) ) . '">Open Settings Diagnostics</a></p></div>'; }
function lousy_outages_admin_external_signals_page() { echo '<div class="wrap"><h1>External Signals</h1><p>Source statuses, fused signals, and collect/seed/clear actions are managed in Settings diagnostics.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=lousy-outages-settings' ) ) . '">Open Settings Diagnostics</a></p></div>'; }


add_action( 'admin_post_lousy_outages_clear_rss_feed_cache', function () { if(!current_user_can('manage_options')){wp_die(esc_html__('Sorry, you are not allowed to access this page.','lousy-outages'));} check_admin_referer('lousy_outages_clear_rss_feed_cache'); if (class_exists('SuzyEaston\LousyOutages\Feeds')) { SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache(); } set_transient('lousy_outages_notice',['message'=>'RSS feed cache cleared.','type'=>'success'],30); wp_safe_redirect(add_query_arg('page','lousy-outages-settings',admin_url('admin.php'))); exit; } );
