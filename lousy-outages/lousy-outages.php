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
    lousy_outages_create_page();
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
function lousy_outages_run_poll(): int {
    $store     = new Store();
    $detector  = new Detector( $store );
    $sms       = new SMS();
    $email     = new Email();
    $statuses  = lousy_outages_collect_statuses( true );
    $processed = 0;

    foreach ( $statuses as $id => $state ) {
        $transition = $detector->detect( $id, $state );
        $store->update( $id, $state );
        $processed++;
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

    $timestamp = gmdate( 'c' );
    update_option( 'lousy_outages_last_poll', $timestamp, false );
    do_action( 'lousy_outages_log', 'poll_complete', [ 'count' => $processed, 'ts' => $timestamp ] );

    return $processed;
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
    $providers       = Providers::list();
    $default_enabled = array_keys( array_filter( $providers, static fn( $prov ) => $prov['enabled'] ?? true ) );
    $enabled         = get_option( 'lousy_outages_providers', $default_enabled );
    $interval  = get_option( 'lousy_outages_interval', 300 );
    $store     = new Store();
    $states    = $store->get_all();
    $notice    = get_transient( 'lousy_outages_notice' );
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
    ];
}

function lousy_outages_format_rss_date( string $time ): string {
    $timestamp = strtotime( $time );
    if ( ! $timestamp ) {
        $timestamp = time();
    }
    return gmdate( 'D, d M Y H:i:s +0000', $timestamp );
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
    $vars[] = 'lousy_outages_feed';
    return $vars;
} );

add_action( 'init', function () {
    add_rewrite_rule( '^api/outages/?$', 'index.php?lousy_outages_api=1', 'top' );
    add_rewrite_rule( '^outages/feed/?$', 'index.php?lousy_outages_feed=1', 'top' );
} );

add_action( 'template_redirect', function () {
    $feed_flag = get_query_var( 'lousy_outages_feed' );
    if ( ! empty( $feed_flag ) ) {
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

        $items = [];
        foreach ( $providers as $provider ) {
            $provider_id      = $provider['id'];
            $provider_name    = $provider['name'];
            $provider_summary = $provider['summary'];
            $provider_url     = $provider['url'] ?: home_url( '/lousy-outages/' );
            $provider_state   = $provider['stateCode'];
            $provider_updated = $provider['updatedAt'];

            if ( ! empty( $provider['incidents'] ) ) {
                foreach ( $provider['incidents'] as $incident ) {
                    $title       = sprintf( '%s: %s', $provider_name, $incident['title'] );
                    $description = $incident['summary'] ?: $provider_summary;
                    $link        = $incident['url'] ?: $provider_url;
                    $updated     = $incident['updatedAt'] ?: $incident['startedAt'] ?: $provider_updated;

                    $items[] = [
                        'title'       => $title,
                        'description' => $description,
                        'link'        => $link,
                        'guid'        => $provider_id . '-' . $incident['id'],
                        'pubDate'     => lousy_outages_format_rss_date( $updated ?: $fetched ),
                    ];
                }
            } elseif ( 'operational' !== $provider_state ) {
                $items[] = [
                    'title'       => sprintf( '%s: %s', $provider_name, $provider['state'] ),
                    'description' => $provider_summary,
                    'link'        => $provider_url,
                    'guid'        => $provider_id . '-' . md5( $provider_state . $provider_summary ),
                    'pubDate'     => lousy_outages_format_rss_date( $provider_updated ?: $fetched ),
                ];
            }
        }

        if ( empty( $items ) ) {
            $items[] = [
                'title'       => 'All systems operational',
                'description' => 'No active incidents detected across monitored providers.',
                'link'        => home_url( '/lousy-outages/' ),
                'guid'        => 'lousy-outages-' . gmdate( 'Ymd' ),
                'pubDate'     => lousy_outages_format_rss_date( $fetched ),
            ];
        }

        $items      = array_slice( $items, 0, 30 );
        $feed_title = get_bloginfo( 'name' ) . ' – Lousy Outages Alerts';
        $feed_link  = home_url( '/lousy-outages/' );
        $feed_desc  = 'Live incident alerts from the Lousy Outages dashboard.';

        if ( function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }

        header( 'Content-Type: application/rss+xml; charset=UTF-8' );

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<rss version="2.0">
  <channel>
    <title><?php echo esc_html( $feed_title ); ?></title>
    <link><?php echo esc_url( $feed_link ); ?></link>
    <description><?php echo esc_html( $feed_desc ); ?></description>
    <language>en-US</language>
    <lastBuildDate><?php echo esc_html( lousy_outages_format_rss_date( gmdate( 'c' ) ) ); ?></lastBuildDate>
    <?php foreach ( $items as $item ) : ?>
      <item>
        <title><?php echo esc_html( $item['title'] ); ?></title>
        <link><?php echo esc_url( $item['link'] ); ?></link>
        <guid isPermaLink="false"><?php echo esc_html( $item['guid'] ); ?></guid>
        <pubDate><?php echo esc_html( $item['pubDate'] ); ?></pubDate>
        <description><?php echo esc_html( $item['description'] ); ?></description>
      </item>
    <?php endforeach; ?>
  </channel>
</rss>
        <?php
        exit;
    }

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
