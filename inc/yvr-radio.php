<?php
/** Suzy Pirate Radio: optional public HTTPS listener and transmitter-status URLs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function se_radio_url( $value ) {
    $url = esc_url_raw( trim( (string) $value ), array( 'https' ) );
    if ( ! $url || wp_parse_url( $url, PHP_URL_SCHEME ) !== 'https' ) { return ''; }
    if ( wp_parse_url( $url, PHP_URL_USER ) || wp_parse_url( $url, PHP_URL_PASS ) ) { return ''; }
    return $url;
}

// Keep the existing /radio/ URL and template assignment for bookmarks.
add_action( 'init', function () {
    if ( ! file_exists( get_template_directory() . '/template-yvr-radio.php' ) ) { return; }
    $existing = get_page_by_path( 'radio' );
    if ( $existing ) {
        if ( get_post_meta( $existing->ID, '_wp_page_template', true ) === 'template-yvr-radio.php' ) {
            if ( $existing->post_title === 'YVR Radio' ) {
                wp_update_post( array(
                    'ID' => $existing->ID,
                    'post_title' => 'Suzy Pirate Radio',
                    'post_content' => 'An independent live internet-radio station operated by Suzy Easton.',
                ) );
            }
            if ( ! get_option( 'se_radio_page_id' ) ) {
                update_option( 'se_radio_page_id', $existing->ID, false );
            }
        }
        return; // Never replace an unrelated page with the same slug.
    }
    if ( get_option( 'se_radio_page_id' ) ) { return; }
    $lock = (int) get_option( 'se_radio_setup_lock', 0 );
    if ( $lock && time() - $lock > 120 ) { delete_option( 'se_radio_setup_lock' ); }
    if ( ! add_option( 'se_radio_setup_lock', time(), '', false ) ) { return; }
    $id = wp_insert_post( array(
        'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => 'Suzy Pirate Radio', 'post_name' => 'radio',
        'post_content' => 'An independent live internet-radio station operated by Suzy Easton.',
        'comment_status' => 'closed', 'ping_status' => 'closed',
        'meta_input' => array( '_wp_page_template' => 'template-yvr-radio.php' ),
    ), true );
    if ( ! is_wp_error( $id ) && $id ) { update_option( 'se_radio_page_id', $id, false ); }
    delete_option( 'se_radio_setup_lock' );
}, 30 );

add_action( 'wp_enqueue_scripts', function () {
    $on_radio_page = is_page_template( 'template-yvr-radio.php' );
    if ( ! $on_radio_page && ! is_front_page() && ! is_page_template( 'page-home.php' ) ) { return; }
    wp_enqueue_style( 'yvr-radio', get_template_directory_uri() . '/assets/css/yvr-radio.css', array( 'main-styles' ), filemtime( get_template_directory() . '/assets/css/yvr-radio.css' ) );
    wp_enqueue_script( 'yvr-radio', get_template_directory_uri() . '/assets/js/yvr-radio.js', array(), filemtime( get_template_directory() . '/assets/js/yvr-radio.js' ), true );
    if ( ! $on_radio_page ) {
        wp_dequeue_script( 'home-yvr-broadcaster' );
        wp_dequeue_script( 'home-hero-map' );
        wp_dequeue_script( 'hls-js' );
    }
}, 99 );

add_action( 'admin_init', function () {
    register_setting( 'se_radio', 'se_radio_stream', array( 'type' => 'string', 'sanitize_callback' => 'se_radio_url', 'default' => '' ) );
    register_setting( 'se_radio', 'se_radio_status', array( 'type' => 'string', 'sanitize_callback' => 'se_radio_url', 'default' => '' ) );
} );
add_action( 'admin_menu', function () {
    add_options_page( 'Suzy Pirate Radio', 'Pirate Radio', 'manage_options', 'se-radio', 'se_radio_settings' );
} );
function se_radio_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?>
    <div class="wrap">
        <h1>Suzy Pirate Radio</h1>
        <p>Configure public HTTPS listener URLs from the independent radio relay. Never paste a source password, transmitter token or private upload endpoint here.</p>
        <form action="options.php" method="post">
            <?php settings_fields( 'se_radio' ); ?>
            <p><label for="se-radio-stream">Public MP3 stream URL (relay: /live.mp3)</label><br>
            <input class="large-text" id="se-radio-stream" type="url" name="se_radio_stream" value="<?php echo esc_attr( get_option( 'se_radio_stream', '' ) ); ?>" placeholder="https://radio.example.com/live.mp3"></p>
            <p><label for="se-radio-status">Public status URL (relay: /status.json)</label><br>
            <input class="large-text" id="se-radio-status" type="url" name="se_radio_status" value="<?php echo esc_attr( get_option( 'se_radio_status', '' ) ); ?>" placeholder="https://radio.example.com/status.json"></p>
            <?php submit_button(); ?>
        </form>
        <p>The player reports a source as online only after the status endpoint confirms it. A running website is not proof of a running transmitter. Server deployment instructions live in <code>radio-station/README.md</code>.</p>
    </div>
    <?php
}
