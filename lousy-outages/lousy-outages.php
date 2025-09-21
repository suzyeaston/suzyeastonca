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

define( 'LOUSY_OUTAGES_PATH', plugin_dir_path( __FILE__ ) );

require_once LOUSY_OUTAGES_PATH . 'includes/Providers.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Store.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Fetcher.php';
require_once LOUSY_OUTAGES_PATH . 'includes/I18n.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Detector.php';
require_once LOUSY_OUTAGES_PATH . 'includes/SMS.php';
require_once LOUSY_OUTAGES_PATH . 'includes/Email.php';
require_once LOUSY_OUTAGES_PATH . 'public/shortcode.php';

use LousyOutages\Providers;
use LousyOutages\Store;
use LousyOutages\Fetcher;
use LousyOutages\Detector;
use LousyOutages\SMS;
use LousyOutages\Email;

/**
 * Schedule polling event on activation and create page.
 */
function lousy_outages_activate() {
    if ( ! wp_next_scheduled( 'lousy_outages_poll' ) ) {
        $interval = (int) get_option( 'lousy_outages_interval', 300 );
        wp_schedule_event( time() + 60, 'lousy_outages_interval', 'lousy_outages_poll' );
    }
    lousy_outages_create_page();
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
    return $schedules;
} );

/**
 * Poll providers and handle transitions.
 */
add_action( 'lousy_outages_poll', 'lousy_outages_run_poll' );
function lousy_outages_run_poll() {
    $store     = new Store();
    $detector  = new Detector( $store );
    $sms       = new SMS();
    $email     = new Email();
    $statuses  = lousy_outages_collect_statuses( true );

    foreach ( $statuses as $id => $state ) {
        $transition = $detector->detect( $id, $state );
        $store->update( $id, $state );
        if ( ! $transition ) {
            continue;
        }

        if ( in_array( $transition['new'], [ 'degraded', 'outage' ], true ) ) {
            $sms->send_alert( $state['name'], $transition['new'], $state['message'], $state['url'] );
            $email->send_alert( $state['name'], $transition['new'], $state['message'], $state['url'] );
        } elseif ( 'operational' === $transition['new'] && in_array( $transition['old'], [ 'degraded', 'outage' ], true ) ) {
            $sms->send_recovery( $state['name'], $state['url'] );
            $email->send_recovery( $state['name'], $state['url'] );
        }
    }
}

function lousy_outages_collect_statuses( bool $bypass_cache = false ): array {
    $cache_key = 'lousy_outages_cached_statuses';
    if ( ! $bypass_cache ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }
    }

    $timeout   = (int) apply_filters( 'lousy_outages_fetch_timeout', 10 );
    $fetcher   = new Fetcher( $timeout );
    $providers = Providers::enabled();
    $results   = [];

    foreach ( $providers as $id => $provider ) {
        $results[ $id ] = $fetcher->fetch( $provider );
    }

    $ttl = (int) apply_filters( 'lousy_outages_cache_ttl', 90 );
    set_transient( $cache_key, $results, max( 30, $ttl ) );

    return $results;
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
} );

function lousy_outages_settings_page() {
    $providers = Providers::list();
    $enabled   = get_option( 'lousy_outages_providers', array_keys( $providers ) );
    $interval  = get_option( 'lousy_outages_interval', 300 );
    ?>
    <div class="wrap">
        <h1>Lousy Outages Settings</h1>
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
                        <?php foreach ( $providers as $id => $prov ) : ?>
                            <label><input type="checkbox" name="lousy_outages_providers[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $enabled, true ) ); ?>> <?php echo esc_html( $prov['name'] ); ?></label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function lousy_outages_build_provider_payload( string $id, array $state, string $fetched_at ): array {
    $status     = $state['status'] ?? 'unknown';
    $label      = Fetcher::status_label( $status );
    $updated_at = ! empty( $state['updated_at'] ) ? (string) $state['updated_at'] : $fetched_at;
    $incidents  = [];

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

    return [
        'id'         => $id,
        'provider'   => $state['provider'] ?? $state['name'] ?? $id,
        'name'       => $state['name'] ?? $state['provider'] ?? $id,
        'state'      => $label,
        'stateCode'  => $status,
        'summary'    => $state['summary'] ?? $label,
        'updatedAt'  => $updated_at,
        'url'        => $state['url'] ?? '',
        'snark'      => $state['snark'] ?? '',
        'incidents'  => $incidents,
        'error'      => $state['error'] ?? null,
    ];
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

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'lousy_outages_api';
    return $vars;
} );

add_action( 'init', function () {
    add_rewrite_rule( '^api/outages/?$', 'index.php?lousy_outages_api=1', 'top' );
} );

add_action( 'template_redirect', function () {
    $flag = get_query_var( 'lousy_outages_api' );
    if ( empty( $flag ) ) {
        return;
    }

    $store   = new Store();
    $refresh = isset( $_GET['refresh'] ) ? filter_var( wp_unslash( $_GET['refresh'] ), FILTER_VALIDATE_BOOLEAN ) : false;
    $fetched = gmdate( 'c' );
    $data    = lousy_outages_collect_statuses( (bool) $refresh );

    foreach ( $data as $id => $state ) {
        $store->update( $id, $state );
    }

    $providers = [];
    foreach ( $data as $id => $state ) {
        $providers[] = lousy_outages_build_provider_payload( $id, $state, $fetched );
    }

    if ( function_exists( 'nocache_headers' ) ) {
        nocache_headers();
    }

    $payload = [
        'providers' => $providers,
        'meta'      => [
            'fetchedAt'   => $fetched,
            'generatedAt' => gmdate( 'c' ),
            'fresh'       => (bool) $refresh,
        ],
    ];

    wp_send_json( $payload );
    exit;
} );
