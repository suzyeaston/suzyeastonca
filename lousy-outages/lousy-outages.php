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
}
register_activation_hook( __FILE__, 'lousy_outages_activate' );

/**
 * Clear cron on deactivation.
 */
function lousy_outages_deactivate() {
    wp_clear_scheduled_hook( 'lousy_outages_poll' );
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
    $fetcher  = new Fetcher();
    $store    = new Store();
    $detector = new Detector( $store );
    $sms      = new SMS();
    $email    = new Email();
    $providers = Providers::enabled();
    foreach ( $providers as $id => $prov ) {
        $data = $fetcher->fetch( $prov );
        if ( ! $data ) {
            continue;
        }
        $transition = $detector->detect( $id, $data );
        $store->update( $id, $data );
        if ( $transition ) {
            if ( in_array( $transition['new'], [ 'degraded', 'partial_outage', 'major_outage' ], true ) ) {
                $sms->send_alert( $prov['name'], $transition['new'], $data['message'], $prov['url'] );
                $email->send_alert( $prov['name'], $transition['new'], $data['message'], $prov['url'] );
            } elseif ( 'operational' === $transition['new'] && in_array( $transition['old'], [ 'degraded', 'partial_outage', 'major_outage' ], true ) ) {
                $sms->send_recovery( $prov['name'], $prov['url'] );
                $email->send_recovery( $prov['name'], $prov['url'] );
            }
        }
    }
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

/**
 * REST endpoint exposing current status.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'lousy-outages/v1', '/status', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            $store = new Store();
            $data  = $store->get_all();
            if ( empty( $data ) ) {
                $fetcher   = new Fetcher();
                $providers = Providers::enabled();
                foreach ( $providers as $id => $prov ) {
                    $state = $fetcher->fetch( $prov );
                    if ( $state ) {
                        $data[ $id ] = $state;
                        $store->update( $id, $state );
                    }
                }
            }
            return rest_ensure_response( $data );
        },
    ] );
} );
