<?php
/** YVR Radio: page provisioning, directory and station configuration. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function se_radio_url( $value ) {
    $url = esc_url_raw( trim( (string) $value ), array( 'https' ) );
    if ( wp_parse_url($url, PHP_URL_USER) || wp_parse_url($url, PHP_URL_PASS) ) { return ''; }
    return wp_parse_url( $url, PHP_URL_SCHEME ) === 'https' ? $url : '';
}

function se_radio_channels() {
    // Catalog only: no slow network probes during page render.
    $channels = se_broadcaster_audio_channel_catalog();
    foreach ( $channels as $key => &$channel ) {
        $channel['group'] = 'Radio';
        if ( ($channel['pin_tier'] ?? '') === 'atc' || $channel['mode'] === 'broadcastify' ) {
            $channel['group'] = 'Scanners';
            // Provider player preserves attribution and avoids scraping stream mounts.
            $channel['mode'] = 'link_out';
            $channel['stream_url'] = '';
            $channel['link_url'] = $channel['link_url'] ?? $channel['source_url'];
        }
        if ( ! empty( $channel['orcasound_node'] ) ) {
            $channel['group'] = 'Hydrophones';
            $channel['mode'] = 'link_out';
            $channel['stream_url'] = '';
            $channel['link_url'] = $channel['source_url'];
        }
        if ( $channel['mode'] === 'soundscape' ) {
            $channel['group'] = 'Recorded loops';
            if ( $key === 'sound_skytrain' ) {
                $channel['label'] = 'Train rumble';
                $channel['hint'] = 'Recorded Tokyo train; not a live SkyTrain feed';
            } elseif ( $key === 'sound_ferry' ) {
                $channel['label'] = 'Steam whistle';
                $channel['hint'] = 'Recorded whistle; not a live BC Ferries feed';
            } else { $channel['hint'] = 'Recorded rain loop; not live weather'; }
        }
    }
    unset( $channel );
    $channels['cknw']['label'] = 'CKNW';
    $channels['cknw']['source'] = 'CKNW';
    $channels['cknw']['freq'] = '';
    $channels['cknw']['hint'] = 'Vancouver news and talk';
    $extra = array(
        'citr' => array('CiTR 101.9 FM', 'Campus and community radio · UBC', 'https://player.citr.ca/', 'https://live.citr.ca/live.aac', 'aac'),
        'cfro' => array('CFRO 100.5 FM', 'Vancouver Co-operative Radio', 'https://coopradio.org/', '', 'mp3'),
        'cjsf' => array('CJSF 90.1 FM', 'Independent radio · SFU', 'https://www.cjsf.ca/streaming', '', 'mp3'),
    );
    foreach ( $extra as $key => $row ) {
        $channels[$key] = array('key'=>$key, 'label'=>$row[0], 'hint'=>$row[1], 'source_url'=>$row[2], 'link_url'=>$row[2], 'stream_url'=>$row[3], 'format'=>$row[4], 'mode'=>$row[3] ? 'stream' : 'link_out', 'group'=>'Community radio');
    }
    $stream = se_radio_url( get_option( 'se_radio_stream', '' ) );
    if ( $stream ) {
        $channels['community'] = array('key'=>'community', 'label'=>'Suzy community channel', 'hint'=>'Independent community stream', 'stream_url'=>$stream, 'format'=>strpos($stream,'.m3u8')!==false?'hls':'mp3', 'mode'=>'stream', 'group'=>'Community radio', 'source_url'=>home_url('/radio/'));
    }
    return array_values( $channels );
}

// Provision once after all required theme files have reached the server.
add_action( 'init', function () {
    if ( get_option( 'se_radio_page_id' ) || ! file_exists( get_template_directory() . '/template-yvr-radio.php' ) || ! file_exists( get_template_directory() . '/assets/js/yvr-radio.js' ) || ! file_exists( get_template_directory() . '/assets/css/yvr-radio.css' ) ) { return; }
    if ( get_page_by_path( 'radio' ) ) { return; } // Never overwrite an existing page.
    $lock = (int) get_option( 'se_radio_setup_lock', 0 );
    if ( $lock && time() - $lock > 120 ) { delete_option( 'se_radio_setup_lock' ); }
    if ( ! add_option( 'se_radio_setup_lock', time(), '', false ) ) { return; }
    $id = wp_insert_post( array('post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'YVR Radio', 'post_name'=>'radio', 'post_content'=>'Listen to Vancouver. Community radio, public scanner links, hydrophones and recorded soundscapes.', 'comment_status'=>'open', 'ping_status'=>'closed', 'meta_input'=>array('_wp_page_template'=>'template-yvr-radio.php')), true );
    if ( ! is_wp_error($id) && $id ) { update_option('se_radio_page_id', $id, false); }
    delete_option( 'se_radio_setup_lock' );
}, 30 );

add_action( 'wp_enqueue_scripts', function () {
    $page = is_page_template('template-yvr-radio.php');
    if ( ! $page && ! is_front_page() && ! is_page_template('page-home.php') ) { return; }
    wp_enqueue_style('yvr-radio', get_template_directory_uri().'/assets/css/yvr-radio.css', array('main-styles'), filemtime(get_template_directory().'/assets/css/yvr-radio.css'));
    if ( ! $page ) {
        wp_dequeue_script('home-yvr-broadcaster');
        wp_dequeue_script('home-hero-map');
        wp_dequeue_script('hls-js');
        return;
    }
    wp_enqueue_script('hls-js', 'https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js', array(), '1.5.15', true);
    wp_enqueue_script('yvr-radio', get_template_directory_uri().'/assets/js/yvr-radio.js', array('hls-js'), filemtime(get_template_directory().'/assets/js/yvr-radio.js'), true);
    wp_localize_script('yvr-radio', 'YvrRadio', array('channels'=>se_radio_channels()));
}, 99 );

add_action('admin_init', function () {
    register_setting('se_radio', 'se_radio_stream', array('type'=>'string', 'sanitize_callback'=>'se_radio_url', 'default'=>''));
    register_setting('se_radio', 'se_radio_room', array('type'=>'string', 'sanitize_callback'=>'se_radio_url', 'default'=>''));
});
add_action('admin_menu', function () {
    add_options_page('YVR Radio', 'YVR Radio', 'manage_options', 'se-radio', 'se_radio_settings');
});
function se_radio_settings() {
    if ( ! current_user_can('manage_options') ) { return; }
    ?>
    <div class="wrap"><h1>YVR Radio</h1>
    <p>Use a public HTTPS listener URL from Icecast or AzuraCast. Never enter a source password or private studio URL here.</p>
    <form action="options.php" method="post"><?php settings_fields('se_radio'); ?>
    <p><label for="se-radio-stream">Community stream URL</label><br><input class="large-text" id="se-radio-stream" type="url" name="se_radio_stream" value="<?php echo esc_attr(get_option('se_radio_stream','')); ?>"></p>
    <p><label for="se-radio-room">Optional public community room URL</label><br><input class="large-text" id="se-radio-room" type="url" name="se_radio_room" value="<?php echo esc_attr(get_option('se_radio_room','')); ?>"></p>
    <?php submit_button(); ?></form>
    <p>Messages use WordPress comments, require a site login and await moderation. Approve them in Comments. This is a message board, not live voice chat.</p>
    <?php if ( ! get_option('se_radio_page_id') && get_page_by_path('radio') ) : ?>
    <p>A page already uses /radio/. It was not changed. Edit that page and select the YVR Radio template to use this app.</p>
    <?php endif; ?></div><?php
}
function se_radio_is_post( $id ) { return get_post_meta( (int)$id, '_wp_page_template', true ) === 'template-yvr-radio.php'; }
add_filter('preprocess_comment', function($comment) {
    if ( se_radio_is_post($comment['comment_post_ID'] ?? 0) && ! is_user_logged_in() ) { wp_die('Please sign in to send a radio message.', '', array('response'=>403)); }
    return $comment;
});
add_filter('pre_comment_approved', function($approved, $comment) {
    if ( se_radio_is_post($comment['comment_post_ID'] ?? 0) && ! is_wp_error($approved) && $approved !== 'spam' && $approved !== 'trash' ) { return 0; }
    return $approved;
}, 20, 2);
