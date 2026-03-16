<?php
// Auto-configure OpenAI key from environment if not defined
if ( ! defined( 'OPENAI_API_KEY' ) ) {
    $env_key = getenv( 'OPENAI_API_KEY' );
    if ( $env_key ) {
        define( 'OPENAI_API_KEY', $env_key );
    }
}

require_once get_template_directory() . '/inc/albini-quotes.php';
require_once get_template_directory() . '/inc/openai.php';
require_once get_template_directory() . '/inc/vancouver-tech-events.php';
/**
 * Functions file for Suzy’s Music Theme
 *   - Canucks App Integration (News + Betting)
 *   - Albini Q&A React widget
 *   - Security hardening: disable XML-RPC, hide users, block author archives
 */

// =========================================
// 1. THEME SCRIPTS & STYLES
// =========================================
function retro_game_music_theme_scripts() {
    // Retro font + main stylesheet
    wp_enqueue_style('retro-font', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
    wp_enqueue_style('main-styles', get_stylesheet_uri());
    wp_enqueue_style(
        'buttons',
        get_template_directory_uri() . '/assets/css/buttons.css',
        [],
        filemtime( get_template_directory() . '/assets/css/buttons.css' )
    );
    if ( is_front_page() ) {
        wp_enqueue_style(
            'retro-title',
            get_template_directory_uri() . '/assets/css/retro-title.css',
            [],
            filemtime( get_template_directory() . '/assets/css/retro-title.css' )
        );
    }

    // Game & piano scripts
    wp_enqueue_script('piano-script', get_template_directory_uri() . '/js/piano-script.js', [], '1.0.1', true);
    wp_enqueue_script('bio-sections', get_template_directory_uri() . '/js/bio-sections.js', [], '1.0.0', true);
    if ( is_front_page() ) {
        wp_enqueue_script('game-init', get_template_directory_uri() . '/js/game-init.js', [], '1.0.0', true);
        wp_enqueue_script('title-color', get_template_directory_uri() . '/js/title-color.js', [], '1.0.0', true);
    }

    if ( is_front_page() || is_page_template( 'page-home.php' ) ) {
        wp_enqueue_script(
            'hero-ship-drag',
            get_template_directory_uri() . '/js/hero-ship-drag.js',
            array(),
            filemtime( get_template_directory() . '/js/hero-ship-drag.js' ),
            true
        );
        wp_enqueue_script(
            'hero-galaga',
            get_template_directory_uri() . '/js/hero-galaga.js',
            array( 'hero-ship-drag' ),
            filemtime( get_template_directory() . '/js/hero-galaga.js' ),
            true
        );
    }

    if ( is_page_template( 'page-bio.php' ) || is_page( 'bio' ) ) {
        wp_enqueue_script(
            'tone-js',
            'https://unpkg.com/tone@14.7.77/build/Tone.js',
            array(),
            '14.7.77',
            true
        );
        wp_enqueue_script(
            'bio-crawl',
            get_template_directory_uri() . '/js/bio-crawl.js',
            array( 'tone-js' ),
            filemtime( get_template_directory() . '/js/bio-crawl.js' ),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'retro_game_music_theme_scripts');

function suzy_enqueue_scripts() {
    $ver = wp_get_theme()->get( 'Version' ) . '-' . substr( md5( filemtime( get_stylesheet_directory() . '/assets/js/canucksPuckBash.js' ) ), 0, 8 );
    wp_enqueue_style( 'suzy-style', get_stylesheet_uri(), [], $ver );

    if ( is_page_template( 'page-arcade.php' ) ) {
        wp_enqueue_script( 'canucks-game', get_stylesheet_directory_uri() . '/assets/js/canucksPuckBash.js', [], $ver, true );
    }

    if ( is_page_template( 'page-albini-qa.php' ) ) {
        $build_dir = get_template_directory_uri() . '/albini-qa/build';
        wp_enqueue_script( 'albini-qa-js',  $build_dir . '/static/js/main.js', [], null, true );
        wp_enqueue_style( 'albini-qa-css',  $build_dir . '/static/css/main.css', [], null );
    }
}
add_action( 'wp_enqueue_scripts', 'suzy_enqueue_scripts' );

function se_enqueue_brand_assets() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style(
        'se-brand-logo',
        $uri . '/assets/brand/brand-logo.css',
        array(),
        file_exists( $dir . '/assets/brand/brand-logo.css' ) ? filemtime( $dir . '/assets/brand/brand-logo.css' ) : null
    );
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_brand_assets' );

function se_enqueue_hero_wordmark_styles() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    $path = '/assets/brand/hero-wordmark.css';
    if ( file_exists( $dir . $path ) ) {
        wp_enqueue_style('se-hero-wordmark', $uri . $path, array(), filemtime($dir . $path));
    }
}
add_action('wp_enqueue_scripts', 'se_enqueue_hero_wordmark_styles');

function se_enqueue_lousy_outages_page_styles() {
    if ( is_page_template( 'page-lousy-outages.php' ) || is_page( 'lousy-outages' ) ) {
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        $path = '/assets/css/lousy-outages-page.css';
        $version = file_exists( $dir . $path ) ? filemtime( $dir . $path ) : null;
        wp_enqueue_style( 'se-lousy-outages-page', $uri . $path, array(), $version );
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_lousy_outages_page_styles' );

function se_enqueue_asmr_lab_assets() {
    if ( ! is_page_template( 'page-asmr-lab.php' ) ) {
        return;
    }

    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    $css_path = '/assets/css/asmr-lab.css';
    if ( file_exists( $dir . $css_path ) ) {
        wp_enqueue_style( 'se-asmr-lab', $uri . $css_path, array(), filemtime( $dir . $css_path ) );
    }

    wp_enqueue_script(
        'tone-js',
        'https://unpkg.com/tone@14.7.77/build/Tone.js',
        array(),
        '14.7.77',
        true
    );

    $engine_path = '/js/asmr-foley-engine.js';
    if ( file_exists( $dir . $engine_path ) ) {
        wp_enqueue_script( 'se-asmr-foley-engine', $uri . $engine_path, array( 'tone-js' ), filemtime( $dir . $engine_path ), true );
    }

    $visual_path = '/js/asmr-visual-engine.js';
    if ( file_exists( $dir . $visual_path ) ) {
        wp_enqueue_script( 'se-asmr-visual-engine', $uri . $visual_path, array(), filemtime( $dir . $visual_path ), true );
    }

    $app_path = '/js/asmr-lab.js';
    if ( file_exists( $dir . $app_path ) ) {
        wp_enqueue_script( 'se-asmr-lab', $uri . $app_path, array( 'se-asmr-foley-engine', 'se-asmr-visual-engine' ), filemtime( $dir . $app_path ), true );
        wp_localize_script(
            'se-asmr-lab',
            'seAsmrLab',
            array(
                'endpoint' => esc_url_raw( rest_url( 'se/v1/asmr-generate' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'visualRegistry' => se_get_asmr_visual_registry(),
                'sceneDataUrl'  => esc_url_raw( $uri . '/assets/data/vancouver-scene-seeds.json' ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_asmr_lab_assets' );


function se_enqueue_gastown_sim_assets() {
    if ( ! is_page_template( 'page-gastown-sim.php' ) ) {
        return;
    }

    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    $css_path = '/assets/css/gastown-sim.css';
    if ( file_exists( $dir . $css_path ) ) {
        wp_enqueue_style( 'se-gastown-sim', $uri . $css_path, array(), filemtime( $dir . $css_path ) );
    }

    wp_enqueue_script(
        'three-js',
        'https://unpkg.com/three@0.160.1/build/three.min.js',
        array(),
        '0.160.1',
        true
    );

    wp_enqueue_script(
        'howler-js',
        'https://unpkg.com/howler@2.2.4/dist/howler.min.js',
        array(),
        '2.2.4',
        true
    );

    $loader_path = '/js/gastown-world-loader.js';
    if ( file_exists( $dir . $loader_path ) ) {
        wp_enqueue_script(
            'se-gastown-world-loader',
            $uri . $loader_path,
            array(),
            filemtime( $dir . $loader_path ),
            true
        );
    }

    $normalizer_path = '/js/gastown-building-normalizer.js';
    if ( file_exists( $dir . $normalizer_path ) ) {
        wp_enqueue_script(
            'se-gastown-building-normalizer',
            $uri . $normalizer_path,
            array(),
            filemtime( $dir . $normalizer_path ),
            true
        );
    }

    $app_path = '/js/gastown-sim.js';
    if ( file_exists( $dir . $app_path ) ) {
        wp_enqueue_script(
            'se-gastown-sim',
            $uri . $app_path,
            array( 'three-js', 'howler-js', 'se-gastown-world-loader', 'se-gastown-building-normalizer' ),
            filemtime( $dir . $app_path ),
            true
        );

        wp_localize_script(
            'se-gastown-sim',
            'seGastownSim',
            array(
                'worldDataUrl'   => esc_url_raw( $uri . '/assets/world/gastown-water-street.json' ),
                'audioBaseUrl'   => esc_url_raw( $uri . '/assets/audio/gastown' ),
                'defaultWeather' => 'rain',
                'defaultMood'    => 'eerie',
                'defaultTimeOfDay' => 'morning',
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_gastown_sim_assets' );


// Header tweak CSS for the Lousy Outages page/template.
// Idempotent: safe if this file is included multiple times.
if ( ! function_exists('se_enqueue_header_tweak_css') ) {
    function se_enqueue_header_tweak_css() {
        if ( is_admin() ) return;
        if ( ! is_page_template('page-lousy-outages.php') && ! is_page('lousy-outages') ) return;

        $dir  = get_stylesheet_directory();
        $uri  = get_stylesheet_directory_uri();
        $path = '/assets/brand/header-tweak.css';

        if ( file_exists($dir . $path) ) {
            $handle = 'se-header-tweak';

            // Enqueue file (cache-busted)
            wp_enqueue_style($handle, $uri . $path, [], filemtime($dir . $path));

            // Also target by page-id (in case template class is missing)
            $page = get_page_by_path('lousy-outages');
            if ( $page instanceof WP_Post ) {
                $id = (int) $page->ID;
                $inline = '.page-id-' . $id . ' .main-header{min-height:auto;padding-block:8px 12px}' .
                          '.page-id-' . $id . ' .main-header .logo img{max-height:48px;height:auto}' .
                          '.page-id-' . $id . ' .entry-header h1{margin-top:.5rem;line-height:1.15;overflow:visible}' .
                          '.page-id-' . $id . ' .entry-header{scroll-margin-top:80px}';
                wp_add_inline_style($handle, $inline);
            }
        }
    }
}
if ( ! has_action('wp_enqueue_scripts', 'se_enqueue_header_tweak_css') ) {
    add_action('wp_enqueue_scripts', 'se_enqueue_header_tweak_css');
}

if ( ! function_exists( 'se_enqueue_header_contact_assets' ) ) {
    function se_enqueue_header_contact_assets() {
        if ( is_admin() ) {
            return;
        }

        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();

        $css_path = '/assets/css/header-contact-modal.css';
        if ( file_exists( $dir . $css_path ) ) {
            wp_enqueue_style(
                'se-header-contact-modal',
                $uri . $css_path,
                array(),
                filemtime( $dir . $css_path )
            );
        }

        $js_path = '/assets/js/header-contact-modal.js';
        if ( file_exists( $dir . $js_path ) ) {
            wp_enqueue_script(
                'se-header-contact-modal',
                $uri . $js_path,
                array(),
                filemtime( $dir . $js_path ),
                true
            );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_header_contact_assets' );

if ( ! function_exists( 'se_handle_contact_suzy_submission' ) ) {
    function se_handle_contact_suzy_submission() {
        if ( ! wp_doing_ajax() ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ), 400 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'se_contact_suzy' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed. Please refresh and try again.' ), 403 );
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $chaos_check = isset( $_POST['chaos_check'] ) ? sanitize_text_field( wp_unslash( $_POST['chaos_check'] ) ) : '';

        if ( '' === $name || '' === $email || '' === $message || '' === $chaos_check ) {
            wp_send_json_error( array( 'message' => 'Please fill out every field before sending.' ), 422 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ), 422 );
        }

        if ( 'yowhatsup' !== strtolower( $chaos_check ) ) {
            wp_send_json_error( array( 'message' => 'Chaos bot check failed. Type yowhatsup and try again.' ), 422 );
        }

        $to = 'suzyeaston@icloud.com';
        $subject = sprintf( 'New site message from %s', $name );
        $body = "New Contact Suzy submission:

";
        $body .= "Name: {$name}
";
        $body .= "Email: {$email}

";
        $body .= "Message:
{$message}
";

        $headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => 'Message failed to send. Please try again in a bit.' ), 500 );
        }

        wp_send_json_success( array( 'message' => 'Message received. Suzy will get back to you soon.' ) );
    }
}
add_action( 'wp_ajax_se_contact_suzy', 'se_handle_contact_suzy_submission' );
add_action( 'wp_ajax_nopriv_se_contact_suzy', 'se_handle_contact_suzy_submission' );

// Load bundled Lousy Outages plugin so shortcode and REST endpoint work
$lousy_outages = get_template_directory() . '/lousy-outages/lousy-outages.php';
if ( file_exists( $lousy_outages ) ) {
    require_once $lousy_outages;
}

add_filter( 'lousy_outages_voice_enabled', '__return_true' );

add_action( 'phpmailer_init', function( $phpmailer ) {
    $host = getenv( 'SMTP_HOST' );
    if ( ! $host ) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;

    $auth_env = getenv( 'SMTP_AUTH' );
    if ( false === $auth_env || null === $auth_env ) {
        $phpmailer->SMTPAuth = true;
    } else {
        $phpmailer->SMTPAuth = filter_var( $auth_env, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        if ( null === $phpmailer->SMTPAuth ) {
            $phpmailer->SMTPAuth = true;
        }
    }

    $phpmailer->Port       = (int) ( getenv( 'SMTP_PORT' ) ?: 587 );
    $phpmailer->SMTPSecure = getenv( 'SMTP_SECURE' ) ?: 'tls';
    $phpmailer->Username   = getenv( 'SMTP_USER' ) ?: '';
    $phpmailer->Password   = getenv( 'SMTP_PASS' ) ?: '';
    $phpmailer->Timeout    = 10;
} );

function lousy_outages_feed_autodiscovery() {
    if ( is_front_page() || is_page_template( 'page-lousy-outages.php' ) || is_page( 'lousy-outages' ) ) {
        $feed_url = home_url( '/?feed=lousy_outages_status' ); // Pretty /feed/lousy_outages_status/ works once permalinks flush, but we keep the query form for reliability.
        echo '\n<link rel="alternate" type="application/rss+xml" title="Lousy Outages" href="' . esc_url( $feed_url ) . '" />\n';
    }
}
add_action( 'wp_head', 'lousy_outages_feed_autodiscovery' );

if ( ! defined( 'LOUSY_OUTAGES_HOME_OUTAGE_WINDOW_HOURS' ) ) {
    define( 'LOUSY_OUTAGES_HOME_OUTAGE_WINDOW_HOURS', 1 );
}

if ( ! defined( 'LOUSY_OUTAGES_HOME_DEGRADED_WINDOW_HOURS' ) ) {
    define( 'LOUSY_OUTAGES_HOME_DEGRADED_WINDOW_HOURS', 2 );
}

if ( ! defined( 'LOUSY_OUTAGES_HOME_COMMUNITY_WINDOW_HOURS' ) ) {
    define( 'LOUSY_OUTAGES_HOME_COMMUNITY_WINDOW_HOURS', 2 );
}

function get_lousy_outages_home_teaser_data(): array {
    $feed_url = home_url( '/?feed=lousy_outages_status' );
    $default = [
        'headline' => 'All clear — no active outages right now.',
        'href'     => home_url( '/lousy-outages/' ),
        'status'   => 'clear',
        'footnote' => '',
        'feed_url' => $feed_url,
    ];

    $summary = get_lousy_outages_home_teaser_from_summary( $default );
    if ( $summary ) {
        return $summary;
    }

    return get_lousy_outages_home_teaser_from_feed( $default );
}

function get_lousy_outages_home_teaser_from_summary( array $default ): ?array {
    $endpoint = home_url( '/wp-json/lousy-outages/v1/summary' );
    $response = wp_remote_get(
        $endpoint,
        [
            'timeout' => 3,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return null;
    }

    $status = wp_remote_retrieve_response_code( $response );
    if ( $status < 200 || $status >= 300 ) {
        return null;
    }

    $payload = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $payload ) ) {
        return null;
    }

    $meta = $payload['summary_meta'] ?? $payload['meta'] ?? [];
    if ( ! is_array( $meta ) ) {
        $meta = [];
    }

    $outage_count = (int) ( $meta['outage_count'] ?? $meta['active_outage_count'] ?? 0 );
    $signal_count = (int) ( $meta['signal_count'] ?? 0 );
    $unverified_count = (int) ( $meta['unverified_count'] ?? 0 );
    $generated_at_raw = $meta['generated_at'] ?? $payload['generated_at'] ?? $payload['fetched_at'] ?? '';
    $generated_at = lousy_outages_home_format_generated_at( $generated_at_raw );

    $dashboard_url = $default['href'];
    $footnote_link = sprintf( '<a href="%s">%s</a>', esc_url( $dashboard_url ), esc_html__( 'View the dashboard', 'suzyeastonca' ) );

    if ( $outage_count > 0 ) {
        $provider = lousy_outages_home_find_top_provider( $payload['providers'] ?? [] );
        $provider_name = $provider['name'] ?? $provider['label'] ?? $provider['provider'] ?? '';
        $provider_name = is_string( $provider_name ) ? trim( $provider_name ) : '';
        if ( '' === $provider_name ) {
            $provider_name = 'Multiple providers';
        }

        $incident_title = '';
        if ( ! empty( $provider['incidents'] ) && is_array( $provider['incidents'] ) ) {
            $lead_incident = $provider['incidents'][0];
            if ( is_array( $lead_incident ) ) {
                $incident_title = $lead_incident['name'] ?? $lead_incident['title'] ?? $lead_incident['summary'] ?? '';
            }
        }
        $incident_title = is_string( $incident_title ) ? trim( $incident_title ) : '';
        if ( '' === $incident_title ) {
            $incident_title = 'Incident posted';
        }

        $headline = sprintf( 'Outage detected: %s — %s', $provider_name, $incident_title );
        $footnote_text = $generated_at ? sprintf( 'Last checked: %s. %s', $generated_at, $footnote_link ) : sprintf( 'Last checked recently. %s', $footnote_link );

        return [
            'headline' => $headline,
            'href'     => $dashboard_url,
            'status'   => 'outage',
            'footnote' => $footnote_text,
            'feed_url' => $default['feed_url'],
        ];
    }

    $signal_note = '';
    if ( $signal_count > 0 || $unverified_count > 0 ) {
        $signal_note = 'Signals detected (degraded/unverified).';
    }

    if ( $generated_at ) {
        $signal_suffix = $signal_note ? ' ' . $signal_note : '';
        $footnote_text = sprintf(
            'Last checked: %s.%s <a href="%s">%s</a>',
            $generated_at,
            $signal_suffix,
            esc_url( $dashboard_url ),
            esc_html__( 'View the dashboard for signals + history.', 'suzyeastonca' )
        );
    } else {
        $prefix = $signal_note ? $signal_note . ' ' : '';
        $footnote_text = sprintf(
            '%s<a href="%s">%s</a>',
            $prefix,
            esc_url( $dashboard_url ),
            esc_html__( 'View the dashboard for signals + history.', 'suzyeastonca' )
        );
    }

    return [
        'headline' => 'All clear — no active outages right now.',
        'href'     => $dashboard_url,
        'status'   => 'clear',
        'footnote' => $footnote_text,
        'feed_url' => $default['feed_url'],
    ];
}

function lousy_outages_home_find_top_provider( array $providers ): array {
    $top = null;
    $top_sort_key = null;

    foreach ( $providers as $provider ) {
        if ( ! is_array( $provider ) ) {
            continue;
        }
        $tile_kind = strtolower( (string) ( $provider['tile_kind'] ?? $provider['tileKind'] ?? '' ) );
        $status = strtolower( (string) ( $provider['status'] ?? $provider['stateCode'] ?? '' ) );
        $has_incidents = ! empty( $provider['incidents'] );
        $is_outage = 'outage' === $tile_kind
            || in_array( $status, [ 'outage', 'major', 'critical', 'major_outage' ], true )
            || $has_incidents;

        if ( ! $is_outage ) {
            continue;
        }

        $sort_key_value = $provider['sort_key'] ?? null;
        $sort_key = is_numeric( $sort_key_value ) ? (int) $sort_key_value : 999;

        if ( null === $top || $sort_key < $top_sort_key ) {
            $top = $provider;
            $top_sort_key = $sort_key;
        }
    }

    return $top ?: [];
}

function lousy_outages_home_format_generated_at( $value ): string {
    if ( empty( $value ) ) {
        return '';
    }
    $timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
    if ( ! $timestamp ) {
        return '';
    }
    return date_i18n( 'M j, Y g:i a', $timestamp );
}

function get_lousy_outages_home_teaser_from_feed( array $default ): array {
    $feed_url = $default['feed_url'] ?? home_url( '/?feed=lousy_outages_status' );

    if ( ! function_exists( 'fetch_feed' ) ) {
        include_once ABSPATH . WPINC . '/feed.php';
    }

    static $cache_filter_added = false;
    if ( ! $cache_filter_added ) {
        add_filter(
            'wp_feed_cache_transient_lifetime',
            static function ( $lifetime, $url ) use ( $feed_url ) {
                if ( $url === $feed_url ) {
                    return 5 * MINUTE_IN_SECONDS;
                }
                return $lifetime;
            },
            10,
            2
        );
        $cache_filter_added = true;
    }

    $feed = fetch_feed( $feed_url );
    if ( is_wp_error( $feed ) ) {
        return $default;
    }

    $items = $feed->get_items( 0, 25 );
    if ( empty( $items ) ) {
        return $default;
    }

    $now               = current_time( 'timestamp' );
    $recent_outage     = null;
    $community_reports = 0;
    $seen              = [];

    foreach ( $items as $item ) {
        if ( ! $item instanceof SimplePie_Item ) {
            continue;
        }

        $guid      = trim( (string) $item->get_id() );
        $title     = trim( (string) $item->get_title() );
        $link      = trim( (string) $item->get_link() );
        $pub_date  = $item->get_date( 'U' );
        $timestamp = is_numeric( $pub_date ) ? (int) $pub_date : ( $pub_date ? strtotime( (string) $pub_date ) : 0 );

        $dedupe_key = $guid ?: sprintf( '%s|%s|%s', $title, $timestamp ?: '', $link );
        if ( $dedupe_key && isset( $seen[ $dedupe_key ] ) ) {
            continue;
        }
        if ( $dedupe_key ) {
            $seen[ $dedupe_key ] = true;
        }

        if ( ! preg_match( '/^\\[(OUTAGE|DEGRADED|COMMUNITY REPORT)\\]\\s*/i', $title, $matches ) ) {
            continue;
        }

        if ( ! $timestamp ) {
            continue;
        }

        $kind        = strtoupper( $matches[1] );
        $clean_title = trim( preg_replace( '/^\\[[^\\]]+\\]\\s*/', '', $title ) );
        $parts       = preg_split( '/\\s+[–-]\\s+/', $clean_title, 2 );
        $provider    = trim( $parts[0] ?? '' );
        $incident    = trim( $parts[1] ?? '' );
        $age         = $now - $timestamp;

        if ( 'OUTAGE' === $kind && $age <= (int) LOUSY_OUTAGES_HOME_OUTAGE_WINDOW_HOURS * HOUR_IN_SECONDS ) {
            if ( ! $recent_outage || $timestamp > $recent_outage['timestamp'] ) {
                $recent_outage = [
                    'provider'  => $provider ?: $clean_title,
                    'incident'  => $incident ?: $clean_title,
                    'timestamp' => $timestamp,
                    'link'      => $link,
                ];
            }
        }

        if ( 'COMMUNITY REPORT' === $kind && $age <= (int) LOUSY_OUTAGES_HOME_COMMUNITY_WINDOW_HOURS * HOUR_IN_SECONDS ) {
            $community_reports++;
        }
    }

    if ( $recent_outage ) {
        $relative = human_time_diff( $recent_outage['timestamp'], $now );
        $headline = sprintf(
            'Outage detected: %s — %s (started %s ago)',
            $recent_outage['provider'],
            $recent_outage['incident'],
            $relative
        );

        return [
            'headline' => $headline,
            'href'     => $recent_outage['link'] ?: $default['href'],
            'status'   => 'outage',
            'footnote' => '',
            'feed_url' => $feed_url,
        ];
    }

    $footnote = '';
    if ( $community_reports > 0 ) {
        $footnote = 'Signals detected (unverified). View the dashboard for details.';
    }

    $default['footnote'] = $footnote;

    return $default;
}

// =========================================
// 2. DISABLE AUTOMATIC PARAGRAPH FORMATTING
// =========================================
function disable_autop_formatting() {
    remove_filter('the_content', 'wpautop');
    remove_filter('the_excerpt', 'wpautop');
}
add_action('init', 'disable_autop_formatting');

// =========================================
// 3. ON-DEMAND DATA UPDATE (NO CRON)
// =========================================
function update_canucks_data() {
    // --- News via rss2json ---
    $news_api      = 'https://api.rss2json.com/v1/api.json?rss_url=https://theprovince.com/category/sports/hockey/nhl/vancouver-canucks/feed';
    $news_response = wp_remote_get($news_api);

    if ( ! is_wp_error($news_response) ) {
        $news_body = wp_remote_retrieve_body($news_response);
        $news_data = json_decode($news_body, true);
        if ( isset($news_data['items']) && json_last_error() === JSON_ERROR_NONE ) {
            set_transient('suzyeaston_canucks_news', $news_data['items'], HOUR_IN_SECONDS);
        }
    }

    // --- Betting via The Odds API ---
    $betting_api      = 'https://api.the-odds-api.com/v4/sports/icehockey_nhl/odds?regions=us&markets=h2h,spreads,totals&oddsFormat=american&apiKey=c7b1ad088542ae4e9262844141ecb250';
    $betting_response = wp_remote_get($betting_api);

    if ( ! is_wp_error($betting_response) ) {
        $betting_body     = wp_remote_retrieve_body($betting_response);
        $betting_data_all = json_decode($betting_body, true);
        if ( json_last_error() === JSON_ERROR_NONE ) {
            // Filter for Canucks games only
            $canucks_betting = array_filter($betting_data_all, function($game) {
                $home = $game['home_team'] ?? '';
                $away = $game['away_team'] ?? '';
                return stripos($home, 'Canucks') !== false || stripos($away, 'Canucks') !== false;
            });
            set_transient('suzyeaston_canucks_betting', $canucks_betting, HOUR_IN_SECONDS);
        }
    }
}

// =========================================
// 4. REGISTER CUSTOM REST API ENDPOINTS
// =========================================
add_action('rest_api_init', function() {
    register_rest_route('canucks/v1', '/news', [
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_news'
    ]);

    register_rest_route('canucks/v1', '/betting', [
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_betting'
    ]);

    register_rest_route('albini/v1', '/ask', [
        'methods'             => 'POST',
        'callback'            => 'albini_handle_query',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('se/v1', '/riff-tip', [
        'methods'             => 'POST',
        'callback'            => 'se_handle_riff_tip',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('se/v1', '/asmr-generate', [
        'methods'             => 'POST',
        'callback'            => 'se_handle_asmr_generate',
        'permission_callback' => '__return_true',
    ]);
});

// =========================================
// 5. ENDPOINT CALLBACKS: CANUCKS DATA
// =========================================
function get_custom_canucks_news( WP_REST_Request $request ) {
    $items = get_transient('suzyeaston_canucks_news');
    if ( false === $items ) {
        update_canucks_data();
        $items = get_transient('suzyeaston_canucks_news');
    }
    return rest_ensure_response( $items );
}

function get_custom_canucks_betting( WP_REST_Request $request ) {
    $odds = get_transient('suzyeaston_canucks_betting');
    if ( false === $odds ) {
        update_canucks_data();
        $odds = get_transient('suzyeaston_canucks_betting');
    }
    return rest_ensure_response( $odds );
}

function enqueue_starfield() {
    wp_enqueue_script(
        'starfield',
        get_template_directory_uri() . '/js/starfield.js',
        [], '1.0', true
    );
}
add_action('wp_enqueue_scripts', 'enqueue_starfield');

// =========================================
// 6. SHORTCODE: DISPLAY THE CANUCKS APP
// =========================================
function canucks_app_shortcode() {
    $news_data    = get_transient('suzyeaston_canucks_news');
    $betting_data = get_transient('suzyeaston_canucks_betting');

    if ( false === $news_data || false === $betting_data ) {
        update_canucks_data();
        $news_data    = get_transient('suzyeaston_canucks_news');
        $betting_data = get_transient('suzyeaston_canucks_betting');
    }

    ob_start();
    ?>
    <div class="canucks-app">
      <h2>Latest News</h2>
      <?php if ( empty($news_data) ): ?>
        <p>No news data available at the moment.</p>
      <?php else: foreach ( $news_data as $item ): ?>
        <div class="canucks-news-item">
          <p><a href="<?php echo esc_url($item['link']); ?>" target="_blank"><?php echo esc_html($item['title']); ?></a></p>
          <?php if ( ! empty($item['pubDate']) ): ?>
            <p><?php echo esc_html($item['pubDate']); ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>

      <h2>Latest Betting Odds</h2>
      <?php if ( empty($betting_data) ): ?>
        <p>No betting data available at the moment.</p>
      <?php else: foreach ( $betting_data as $bet ): ?>
        <div class="canucks-betting">
          <p><strong>Matchup:</strong> <?php echo esc_html($bet['away_team']); ?> @ <?php echo esc_html($bet['home_team']); ?></p>
          <?php if ( ! empty($bet['commence_time']) ): ?>
            <p><strong>Start Time:</strong> <?php echo date_i18n('F j, Y g:i a', strtotime($bet['commence_time'])); ?></p>
          <?php endif; ?>
          <?php if ( ! empty($bet['bookmakers']) ): foreach ( $bet['bookmakers'] as $bm ): ?>
            <?php if ( ! empty($bm['markets']) ): foreach ( $bm['markets'] as $mkt ): ?>
              <p><strong><?php echo esc_html($bm['title']); ?></strong> – <?php echo esc_html($mkt['key']); ?>:
                <?php
                  $info = array_map(function($o){
                    $pt = (isset($o['point']) && $o['point'] !== '') ? ', ' . esc_html($o['point']) : '';
                    return esc_html($o['name']) . ' (' . esc_html($o['price']) . $pt . ')';
                  }, $mkt['outcomes']);
                  echo implode(' ', $info);
                ?>
              </p>
            <?php endforeach; endif; ?>
          <?php endforeach; endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('canucks_app', 'canucks_app_shortcode');

// =========================================
// 7. ALBINI Q&A SHORTCODE & ASSETS
// =========================================
function albini_qa_shortcode() {
    return '<div id="albini-qa-root"></div>';
}
add_shortcode('albini_qa', 'albini_qa_shortcode');

// =========================================
// 8. ALBINI HANDLER (OpenAI Proxy)
// =========================================
function albini_handle_query( WP_REST_Request $req ) {
    $question = sanitize_textarea_field( $req->get_param('question') );

    if ( empty( $question ) ) {
        return new WP_Error( 'albini_invalid_question', 'Please ask a question.', [ 'status' => 400 ] );
    }

    $matched_quotes = suzy_albini_match_quotes( $question );
    $system_prompt  = "You are a neutral engineer and writer who is very familiar with Steve Albini's publicly documented views. "
        . "You will be given a user question and a small library of short Steve Albini quotes with topics and sources. "
        . "Your job: pick the provided quotes that best relate to the question, do not invent new quotes, "
        . "and return STRICT JSON with these keys: "
        . "\"quotes\" (array of the chosen quotes with id, quote, source, year, topics), "
        . "\"commentary\" (a short neutral explanation in your own voice), "
        . "and optionally \"topics\" (array of keywords). "
        . "The ENTIRE response must be a single JSON object with no surrounding text, no Markdown, and NO ``` code fences. "
        . "Never write in the first person as Steve Albini or claim to be him.";

    $user_payload = [
        'question' => $question,
        'quotes'   => $matched_quotes,
    ];

    $response = se_openai_chat(
        array(
            array(
                'role'    => 'system',
                'content' => $system_prompt,
            ),
            array(
                'role'    => 'user',
                'content' => wp_json_encode( $user_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            ),
        ),
        array(
            'model'       => 'gpt-4o',
            'max_tokens'  => 350,
            'temperature' => 0.4,
        ),
        array(
            'timeout' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'openai_error', $response->get_error_message(), array( 'status' => 500 ) );
    }

    $data = $response;

    $content = isset( $data['choices'][0]['message']['content'] )
        ? trim( $data['choices'][0]['message']['content'] )
        : '';

    // If the model wrapped the JSON in a markdown code block, strip the fences.
    if ( preg_match( '/```(?:json)?\\s*(.+?)```/is', $content, $matches ) ) {
        $content = trim( $matches[1] );
    }

    $decoded = json_decode( $content, true );

    if ( json_last_error() !== JSON_ERROR_NONE || empty( $decoded ) ) {
        return rest_ensure_response([
            'quotes'     => $matched_quotes,
            'commentary' => $content,
            'topics'     => [],
        ]);
    }

    return rest_ensure_response([
        'quotes'     => isset( $decoded['quotes'] ) ? $decoded['quotes'] : $matched_quotes,
        'commentary' => isset( $decoded['commentary'] ) ? wp_kses_post( $decoded['commentary'] ) : '',
        'topics'     => isset( $decoded['topics'] ) ? (array) $decoded['topics'] : [],
    ]);
}

function se_handle_riff_tip( WP_REST_Request $req ) {
    $mode       = sanitize_text_field( $req->get_param( 'mode' ) );
    $tempo      = absint( $req->get_param( 'tempo' ) );
    $instrument = sanitize_text_field( $req->get_param( 'instrument' ) );
    $summary    = sanitize_textarea_field( $req->get_param( 'summary' ) );

    $fallbacks = array(
        "Leave space for the groove.",
        "Try muting the high strings for punch.",
        "Let the melody breathe.",
        "Focus on emotion over perfection.",
        "Layer subtle textures for depth.",
    );

    $user_prompt = sprintf(
        'Mode: %s. Tempo: %s BPM. Instrument: %s. Riff: %s',
        $mode ? $mode : 'Unknown',
        $tempo ? $tempo : 'Unknown',
        $instrument ? $instrument : 'Unknown',
        $summary ? wp_trim_words( $summary, 60, '...' ) : 'No summary provided.'
    );

    $response = se_openai_chat(
        array(
            array(
                'role'    => 'system',
                'content' => 'You are a concise music producer giving practical arrangement tips. Keep replies to 1-2 sentences.',
            ),
            array(
                'role'    => 'user',
                'content' => $user_prompt,
            ),
        ),
        array(
            'temperature' => 0.8,
            'max_tokens'  => 80,
        ),
        array(
            'timeout' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {
        return rest_ensure_response( array( 'tip' => $fallbacks[ array_rand( $fallbacks ) ] ) );
    }

    $tip = isset( $response['choices'][0]['message']['content'] )
        ? trim( $response['choices'][0]['message']['content'] )
        : '';

    if ( ! $tip ) {
        $tip = $fallbacks[ array_rand( $fallbacks ) ];
    }

    return rest_ensure_response( array( 'tip' => $tip ) );
}

function se_get_asmr_allowed_engines() {
    return array(
        'glissando_rise',
        'synth_bloom',
        'sub_swell',
        'filtered_noise_wash',
        'paper_crackle',
        'breath_pulse',
        'ceramic_tick',
        'steam_hiss',
        'tape_stop_drop',
        'bit_pulse',
        'low_hum',
        'digital_shimmer',
        'glass_ping',
        'glass_resonance',
        'relay_click_cluster',
        'shimmer_swirl',
        'crystal_chime',
        'ghost_pad',
        'spectral_suck',
        'voltage_flutter',
        'halo_tone',
        'particle_spark',
        'ritual_bass_swell',
        'reverse_bloom',
        'steam_clock_burst',
        'distant_bell_toll',
        'cold_air_hush',
        'wet_street_shimmer',
        'harbor_fog_bed',
        'metal_resonance',
        'snow_muffle',
        'city_electrical_hum',
        'footsteps_wet',
        'footsteps_snow',
        'rain_close',
        'rain_roof',
        'puddle_splash',
        'crowd_murmur',
        'laughter_burst',
        'skytrain_pass',
        'bus_idle',
        'car_horn_short',
        'seabus_horn',
        'ocean_waves',
        'gulls_distant',
        'crosswalk_chirp',
        'compass_tap',
        'bike_bell',
        'skateboard_roll',
        'siren_distant',
        'gastown_clock_whistle',
        'church_bells',
        'harbour_noon_horn',
        'nine_oclock_gun',
    );
}

function se_get_asmr_visual_registry() {
    static $registry = null;
    if ( null !== $registry ) {
        return $registry;
    }

    $registry = array(
        array( 'id' => 'scanline_field', 'label' => 'Scanline Field', 'category' => 'support', 'priority' => 'support', 'description' => 'CRT line drift overlay.', 'expected_shape' => 'horizontal scan lines', 'renderer' => 'drawScanlineField', 'intensity_min' => 0.08, 'intensity_max' => 0.4, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'pixel_grid_pulse', 'label' => 'Pixel Grid Pulse', 'category' => 'support', 'priority' => 'support', 'description' => 'Retro pixel grid pulse.', 'expected_shape' => 'small grid cells', 'renderer' => 'drawPixelGridPulse', 'intensity_min' => 0.1, 'intensity_max' => 0.35, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'wireframe_horizon', 'label' => 'Wireframe Horizon', 'category' => 'scene', 'priority' => 'support', 'description' => 'Perspective horizon lines.', 'expected_shape' => 'vanishing horizon fan', 'renderer' => 'drawWireframeHorizon', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'radial_bloom', 'label' => 'Radial Bloom', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Soft center bloom.', 'expected_shape' => 'radial glow', 'renderer' => 'drawRadialBloom', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'particle_trail', 'label' => 'Particle Trail', 'category' => 'motion', 'priority' => 'support', 'description' => 'Moving particle points.', 'expected_shape' => 'drifting dots', 'renderer' => 'drawParticleTrail', 'intensity_min' => 0.2, 'intensity_max' => 0.6, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'glitch_flash', 'label' => 'Glitch Flash', 'category' => 'support', 'priority' => 'support', 'description' => 'Brief digital glitch streaks.', 'expected_shape' => 'horizontal flashes', 'renderer' => 'drawGlitchFlash', 'intensity_min' => 0.05, 'intensity_max' => 0.25, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'waveform_ring', 'label' => 'Waveform Ring', 'category' => 'motion', 'priority' => 'support', 'description' => 'Oscillating ring pulse.', 'expected_shape' => 'wobble ring', 'renderer' => 'drawWaveformRing', 'intensity_min' => 0.15, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'macro_texture_drift', 'label' => 'Macro Texture Drift', 'category' => 'texture', 'priority' => 'support', 'description' => 'Linear texture drift.', 'expected_shape' => 'short bars', 'renderer' => 'drawMacroTextureDrift', 'intensity_min' => 0.12, 'intensity_max' => 0.35, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'signal_bars', 'label' => 'Signal Bars', 'category' => 'support', 'priority' => 'support', 'description' => 'Diagnostic signal meter bars.', 'expected_shape' => 'small stacked bars', 'renderer' => 'drawSignalBars', 'intensity_min' => 0.05, 'intensity_max' => 0.2, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'text_reveal', 'label' => 'Text Reveal', 'category' => 'support', 'priority' => 'support', 'description' => 'Short terminal text reveal.', 'expected_shape' => 'single text block', 'renderer' => 'drawTextReveal', 'intensity_min' => 0.15, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'volumetric_fog', 'label' => 'Volumetric Fog', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Layered fog drift.', 'expected_shape' => 'horizontal haze bands', 'renderer' => 'drawVolumetricFog', 'intensity_min' => 0.2, 'intensity_max' => 0.6, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'glass_refraction', 'label' => 'Glass Refraction', 'category' => 'texture', 'priority' => 'support', 'description' => 'Refraction contour lines.', 'expected_shape' => 'curved refractive lines', 'renderer' => 'drawGlassRefraction', 'intensity_min' => 0.15, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'halo_glyphs', 'label' => 'Halo Glyphs', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Arc glyph halos.', 'expected_shape' => 'partial circular arcs', 'renderer' => 'drawHaloGlyphs', 'intensity_min' => 0.15, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'cathedral_beam', 'label' => 'Cathedral Beam', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Focused light beam.', 'expected_shape' => 'vertical cone beam', 'renderer' => 'drawCathedralBeam', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'monolith_silhouette', 'label' => 'Monolith Silhouette', 'category' => 'scene', 'priority' => 'hero', 'description' => 'Large central monolith.', 'expected_shape' => 'single tall slab', 'renderer' => 'drawMonolithSilhouette', 'intensity_min' => 0.25, 'intensity_max' => 0.75, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'starfield_drift', 'label' => 'Starfield Drift', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Sparse moving stars.', 'expected_shape' => 'small drifting stars', 'renderer' => 'drawStarfieldDrift', 'intensity_min' => 0.08, 'intensity_max' => 0.3, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'orbiting_shards', 'label' => 'Orbiting Shards', 'category' => 'motion', 'priority' => 'support', 'description' => 'Orbiting shard fragments.', 'expected_shape' => 'angular orbit points', 'renderer' => 'drawOrbitingShards', 'intensity_min' => 0.15, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'pulse_orb', 'label' => 'Pulse Orb', 'category' => 'motion', 'priority' => 'support', 'description' => 'Core pulse orb.', 'expected_shape' => 'pulsing circle', 'renderer' => 'drawPulseOrb', 'intensity_min' => 0.2, 'intensity_max' => 0.58, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'energy_column', 'label' => 'Energy Column', 'category' => 'motion', 'priority' => 'support', 'description' => 'Energy column pulse.', 'expected_shape' => 'soft vertical beam', 'renderer' => 'drawEnergyColumn', 'intensity_min' => 0.2, 'intensity_max' => 0.55, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'refraction_ripple', 'label' => 'Refraction Ripple', 'category' => 'texture', 'priority' => 'support', 'description' => 'Ripple distortion rings.', 'expected_shape' => 'ripple circles', 'renderer' => 'drawRefractionRipple', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'chromatic_veil', 'label' => 'Chromatic Veil', 'category' => 'support', 'priority' => 'support', 'description' => 'Chromatic split veil.', 'expected_shape' => 'subtle RGB haze', 'renderer' => 'drawChromaticVeil', 'intensity_min' => 0.06, 'intensity_max' => 0.2, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'terminal_runes', 'label' => 'Terminal Runes', 'category' => 'texture', 'priority' => 'support', 'description' => 'Arcade rune marks.', 'expected_shape' => 'tiny glyph strokes', 'renderer' => 'drawTerminalRunes', 'intensity_min' => 0.1, 'intensity_max' => 0.35, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'snow_drift', 'label' => 'Snow Drift', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Snow particles drifting.', 'expected_shape' => 'falling dots', 'renderer' => 'drawSnowDrift', 'intensity_min' => 0.25, 'intensity_max' => 0.7, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'amber_halo', 'label' => 'Amber Halo', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Warm streetlight halos.', 'expected_shape' => 'amber glow circles', 'renderer' => 'drawAmberHalo', 'intensity_min' => 0.15, 'intensity_max' => 0.4, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'wet_reflection_shimmer', 'label' => 'Wet Reflection Shimmer', 'category' => 'texture', 'priority' => 'support', 'description' => 'Wet streak reflections.', 'expected_shape' => 'vertical reflection strips', 'renderer' => 'drawWetReflectionShimmer', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'brick_shadow_drift', 'label' => 'Brick Shadow Drift', 'category' => 'texture', 'priority' => 'support', 'description' => 'Brick shadow texture.', 'expected_shape' => 'brick-like side blocks', 'renderer' => 'drawBrickShadowDrift', 'intensity_min' => 0.12, 'intensity_max' => 0.38, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'steam_plume_column', 'label' => 'Steam Plume Column', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Steam column plume.', 'expected_shape' => 'soft column mist', 'renderer' => 'drawSteamPlumeColumn', 'intensity_min' => 0.18, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'clock_face_reveal', 'label' => 'Clock Face Reveal', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Clock face reveal motif.', 'expected_shape' => 'round clock face', 'renderer' => 'drawClockFaceReveal', 'intensity_min' => 0.25, 'intensity_max' => 0.7, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'harbor_mist', 'label' => 'Harbor Mist', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Harbor fog sheet.', 'expected_shape' => 'layered mist rows', 'renderer' => 'drawHarborMist', 'intensity_min' => 0.2, 'intensity_max' => 0.6, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'neon_wet_reflections', 'label' => 'Neon Wet Reflections', 'category' => 'texture', 'priority' => 'support', 'description' => 'Neon wet pavement texture.', 'expected_shape' => 'glowing vertical puddles', 'renderer' => 'drawNeonWetReflections', 'intensity_min' => 0.2, 'intensity_max' => 0.55, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'winter_particulate_depth', 'label' => 'Winter Particulate Depth', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Depth snow particles.', 'expected_shape' => 'layered snow points', 'renderer' => 'drawWinterParticulateDepth', 'intensity_min' => 0.2, 'intensity_max' => 0.62, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'gastown_clock_silhouette', 'label' => 'Gastown Clock', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Steam clock silhouette.', 'expected_shape' => 'tall steam clock with roof cap, round face, glass body, side pipes, and pedestal base', 'renderer' => 'drawGastownClockSilhouette', 'intensity_min' => 0.35, 'intensity_max' => 0.8, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'steam_clock_approach', 'label' => 'Steam Clock Approach', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Steam clock reveal embedded in route movement.', 'expected_shape' => 'clock on left/right third inside corridor', 'renderer' => 'drawSteamClockApproach', 'intensity_min' => 0.3, 'intensity_max' => 0.82, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'water_street_corridor', 'label' => 'Water Street Corridor', 'category' => 'scene', 'priority' => 'support', 'description' => 'Route-first Water Street corridor framing.', 'expected_shape' => 'off-center vanishing corridor with facades', 'renderer' => 'drawWaterStreetCorridor', 'intensity_min' => 0.22, 'intensity_max' => 0.62, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'station_threshold_glow', 'label' => 'Station Threshold Glow', 'category' => 'scene', 'priority' => 'support', 'description' => 'Waterfront threshold entry glow.', 'expected_shape' => 'station-edge glow with corridor entry', 'renderer' => 'drawStationThresholdGlow', 'intensity_min' => 0.2, 'intensity_max' => 0.56, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'angled_building_split', 'label' => 'Angled Building Split', 'category' => 'scene', 'priority' => 'support', 'description' => 'Street split with angled building node.', 'expected_shape' => 'forking curb lines and angled facade mass', 'renderer' => 'drawAngledBuildingSplit', 'intensity_min' => 0.18, 'intensity_max' => 0.6, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'lamp_rhythm_row', 'label' => 'Lamp Rhythm Row', 'category' => 'texture', 'priority' => 'support', 'description' => 'Rhythmic staggered lamp halos along route.', 'expected_shape' => 'receding off-center lamp cadence', 'renderer' => 'drawLampRhythmRow', 'intensity_min' => 0.12, 'intensity_max' => 0.4, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'wet_cobble_axis', 'label' => 'Wet Cobble Axis', 'category' => 'texture', 'priority' => 'support', 'description' => 'Ground-plane reflective cobble motion axis.', 'expected_shape' => 'wet perspective axis with converging lines', 'renderer' => 'drawWetCobbleAxis', 'intensity_min' => 0.12, 'intensity_max' => 0.44, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'cobblestone_perspective', 'label' => 'Cobblestone Perspective', 'category' => 'texture', 'priority' => 'support', 'description' => 'Cobblestone lane texture.', 'expected_shape' => 'ground grid stones', 'renderer' => 'drawCobblestonePerspective', 'intensity_min' => 0.18, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'brick_wall_parallax', 'label' => 'Brick Wall Parallax', 'category' => 'texture', 'priority' => 'support', 'description' => 'Brick facade sides.', 'expected_shape' => 'left/right brick blocks', 'renderer' => 'drawBrickWallParallax', 'intensity_min' => 0.18, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'streetlamp_halo_row', 'label' => 'Streetlamp Halo Row', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Streetlamp halo row.', 'expected_shape' => 'four halo circles', 'renderer' => 'drawStreetlampHaloRow', 'intensity_min' => 0.18, 'intensity_max' => 0.48, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'street_corridor_vanishing_point', 'label' => 'Street Corridor Vanishing Point', 'category' => 'scene', 'priority' => 'support', 'description' => 'Narrow corridor converging to a vanishing point.', 'expected_shape' => 'converging street guides', 'renderer' => 'drawStreetCorridorVanishingPoint', 'intensity_min' => 0.16, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'curb_line_perspective', 'label' => 'Curb Line Perspective', 'category' => 'texture', 'priority' => 'support', 'description' => 'Parallel curb guides into depth.', 'expected_shape' => 'paired curb lines', 'renderer' => 'drawCurbLinePerspective', 'intensity_min' => 0.14, 'intensity_max' => 0.42, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'facade_plane_bands', 'label' => 'Facade Plane Bands', 'category' => 'texture', 'priority' => 'support', 'description' => 'Receding facade plane blocks.', 'expected_shape' => 'stacked side facade bands', 'renderer' => 'drawFacadePlaneBands', 'intensity_min' => 0.16, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'awning_edge_rows', 'label' => 'Awning Edge Rows', 'category' => 'texture', 'priority' => 'support', 'description' => 'Awning strips receding in perspective.', 'expected_shape' => 'angled awning rows', 'renderer' => 'drawAwningEdgeRows', 'intensity_min' => 0.14, 'intensity_max' => 0.4, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'heritage_window_grid', 'label' => 'Heritage Window Grid', 'category' => 'texture', 'priority' => 'support', 'description' => 'Tight heritage storefront window rhythm.', 'expected_shape' => 'small repeated window panes', 'renderer' => 'drawHeritageWindowGrid', 'intensity_min' => 0.12, 'intensity_max' => 0.4, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'district_sign_columns', 'label' => 'District Sign Columns', 'category' => 'scene', 'priority' => 'support', 'description' => 'Vertical sign and marquee columns.', 'expected_shape' => 'tall sign slabs', 'renderer' => 'drawDistrictSignColumns', 'intensity_min' => 0.16, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'wet_reflection_axis', 'label' => 'Wet Reflection Axis', 'category' => 'texture', 'priority' => 'support', 'description' => 'Centerline wet reflection streaks.', 'expected_shape' => 'reflection bands toward horizon', 'renderer' => 'drawWetReflectionAxis', 'intensity_min' => 0.14, 'intensity_max' => 0.44, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'skyline_mask_lowrise', 'label' => 'Skyline Mask Lowrise', 'category' => 'scene', 'priority' => 'support', 'description' => 'Low-rise skyline silhouette mask.', 'expected_shape' => 'stepped low skyline', 'renderer' => 'drawSkylineMaskLowrise', 'intensity_min' => 0.12, 'intensity_max' => 0.38, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'granville_neon_marquee', 'label' => 'Granville Neon Marquee', 'category' => 'scene', 'priority' => 'support', 'description' => 'Neon marquee slabs.', 'expected_shape' => 'vertical neon panels', 'renderer' => 'drawGranvilleNeonMarquee', 'intensity_min' => 0.18, 'intensity_max' => 0.48, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'neon_sign_flicker', 'label' => 'Neon Sign Flicker', 'category' => 'scene', 'priority' => 'support', 'description' => 'Neon strip flicker.', 'expected_shape' => 'horizontal neon bars', 'renderer' => 'drawNeonSignFlicker', 'intensity_min' => 0.18, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'traffic_light_glow', 'label' => 'Traffic Light Glow', 'category' => 'scene', 'priority' => 'support', 'description' => 'Traffic signal glow.', 'expected_shape' => 'three light halos', 'renderer' => 'drawTrafficLightGlow', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'skytrain_track', 'label' => 'SkyTrain Track', 'category' => 'motion', 'priority' => 'support', 'description' => 'Elevated track lines.', 'expected_shape' => 'dual horizontal track', 'renderer' => 'drawSkytrainTrack', 'intensity_min' => 0.2, 'intensity_max' => 0.55, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'skytrain_pass_visual', 'label' => 'SkyTrain Pass', 'category' => 'motion', 'priority' => 'hero', 'description' => 'SkyTrain car pass-through.', 'expected_shape' => 'moving train block', 'renderer' => 'drawSkytrainPassVisual', 'intensity_min' => 0.25, 'intensity_max' => 0.7, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'bus_pass_visual', 'label' => 'Bus Pass', 'category' => 'motion', 'priority' => 'support', 'description' => 'Bus pass-through.', 'expected_shape' => 'moving bus block', 'renderer' => 'drawBusPassVisual', 'intensity_min' => 0.2, 'intensity_max' => 0.6, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'northshore_mountain_ridge', 'label' => 'North Shore Ridge', 'category' => 'scene', 'priority' => 'support', 'description' => 'North Shore mountain ridge.', 'expected_shape' => 'mountain silhouette ridge', 'renderer' => 'drawNorthshoreMountainRidge', 'intensity_min' => 0.22, 'intensity_max' => 0.62, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'mountain_mist_layers', 'label' => 'Mountain Mist Layers', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Mist bands for mountain scene.', 'expected_shape' => 'layered mist bands', 'renderer' => 'drawMountainMistLayers', 'intensity_min' => 0.18, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'rain_streaks', 'label' => 'Rain Streaks', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Diagonal rain lines.', 'expected_shape' => 'falling streaks', 'renderer' => 'drawRainStreaks', 'intensity_min' => 0.2, 'intensity_max' => 0.6, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'puddle_reflections', 'label' => 'Puddle Reflections', 'category' => 'texture', 'priority' => 'support', 'description' => 'Puddle neon reflections.', 'expected_shape' => 'vertical reflection pools', 'renderer' => 'drawPuddleReflections', 'intensity_min' => 0.18, 'intensity_max' => 0.5, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'science_world_dome', 'label' => 'Science World Dome', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Geodesic dome silhouette with lattice.', 'expected_shape' => 'dome arc + triangle lattice', 'renderer' => 'drawScienceWorldDome', 'intensity_min' => 0.35, 'intensity_max' => 0.85, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'chinatown_gate', 'label' => 'Chinatown Gate', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Chinatown gate profile.', 'expected_shape' => 'two pillars + layered roof', 'renderer' => 'drawChinatownGate', 'intensity_min' => 0.3, 'intensity_max' => 0.8, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'english_bay_inukshuk', 'label' => 'English Bay Inukshuk', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Stacked stone Inukshuk reveal.', 'expected_shape' => 'base + torso + arm stone + head', 'renderer' => 'drawEnglishBayInukshuk', 'intensity_min' => 0.35, 'intensity_max' => 0.88, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'maritime_museum_sailroof', 'label' => 'Maritime Museum Sail Roof', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Sail roof gesture.', 'expected_shape' => 'arched sail roof line', 'renderer' => 'drawMaritimeMuseumSailroof', 'intensity_min' => 0.28, 'intensity_max' => 0.8, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'lions_gate_bridge', 'label' => 'Lions Gate Bridge', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Suspension bridge silhouette with two towers, deck, top cable arc, and suspenders.', 'expected_shape' => 'two towers + deck + hanging cable lines', 'renderer' => 'drawLionsGateBridge', 'intensity_min' => 0.35, 'intensity_max' => 0.9, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'bc_place_dome', 'label' => 'BC Place Dome', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Stadium dome spoked silhouette.', 'expected_shape' => 'low dome with radial spokes', 'renderer' => 'drawBCPlaceDome', 'intensity_min' => 0.3, 'intensity_max' => 0.8, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'port_cranes', 'label' => 'Port Cranes', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Container crane row.', 'expected_shape' => 'angular crane arms', 'renderer' => 'drawPortCranes', 'intensity_min' => 0.28, 'intensity_max' => 0.75, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'planetarium_dome', 'label' => 'Planetarium Dome', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Smooth observatory dome.', 'expected_shape' => 'smooth dome + low base', 'renderer' => 'drawPlanetariumDome', 'intensity_min' => 0.3, 'intensity_max' => 0.82, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'starfield_projection', 'label' => 'Starfield Projection', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Planetarium star projection.', 'expected_shape' => 'dome projection arcs + stars', 'renderer' => 'drawStarfieldProjection', 'intensity_min' => 0.25, 'intensity_max' => 0.75, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'constellation_lines', 'label' => 'Constellation Lines', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Constellation line overlay.', 'expected_shape' => 'tiny connected stars', 'renderer' => 'drawConstellationLines', 'intensity_min' => 0.12, 'intensity_max' => 0.35, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'canada_place_sails', 'label' => 'Canada Place Sails', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'Canada Place sail peaks.', 'expected_shape' => 'triangular sail peaks', 'renderer' => 'drawCanadaPlaceSails', 'intensity_min' => 0.28, 'intensity_max' => 0.78, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'gastown_scene', 'label' => 'Gastown Scene', 'category' => 'scene', 'priority' => 'hero', 'description' => 'Gastown scene background stack.', 'expected_shape' => 'brick + cobblestone + clock support', 'renderer' => 'drawGastownScene', 'intensity_min' => 0.28, 'intensity_max' => 0.76, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'granville_scene', 'label' => 'Granville Scene', 'category' => 'scene', 'priority' => 'hero', 'description' => 'Granville neon street scene.', 'expected_shape' => 'neon facades + reflections', 'renderer' => 'drawGranvilleScene', 'intensity_min' => 0.28, 'intensity_max' => 0.76, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'north_shore_scene', 'label' => 'North Shore Scene', 'category' => 'scene', 'priority' => 'hero', 'description' => 'North Shore ridge and mist scene.', 'expected_shape' => 'ridge horizon + mist', 'renderer' => 'drawNorthShoreScene', 'intensity_min' => 0.3, 'intensity_max' => 0.8, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'waterfront_scene', 'label' => 'Waterfront Scene', 'category' => 'scene', 'priority' => 'hero', 'description' => 'Waterfront horizon and harbor support.', 'expected_shape' => 'waterline + distant structures', 'renderer' => 'drawWaterfrontScene', 'intensity_min' => 0.28, 'intensity_max' => 0.8, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'clear_cold_shimmer', 'label' => 'Clear Cold Shimmer', 'category' => 'atmosphere', 'priority' => 'support', 'description' => 'Cold air shimmer.', 'expected_shape' => 'soft cool haze', 'renderer' => 'drawClearColdShimmer', 'intensity_min' => 0.15, 'intensity_max' => 0.45, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'ocean_surface_shimmer', 'label' => 'Ocean Surface Shimmer', 'category' => 'texture', 'priority' => 'support', 'description' => 'Ocean highlight shimmer.', 'expected_shape' => 'horizontal sparkle strips', 'renderer' => 'drawOceanSurfaceShimmer', 'intensity_min' => 0.18, 'intensity_max' => 0.52, 'opening_hero' => false, 'support_only' => true ),
        array( 'id' => 'seabus_silhouette', 'label' => 'SeaBus Silhouette', 'category' => 'landmark', 'priority' => 'hero', 'description' => 'SeaBus vessel silhouette.', 'expected_shape' => 'low ferry hull', 'renderer' => 'drawSeabusSilhouette', 'intensity_min' => 0.28, 'intensity_max' => 0.76, 'opening_hero' => true, 'support_only' => false ),
        array( 'id' => 'gull_silhouettes', 'label' => 'Gull Silhouettes', 'category' => 'motion', 'priority' => 'support', 'description' => 'Subtle gull V silhouettes.', 'expected_shape' => '2-4 small V arcs', 'renderer' => 'drawGullSilhouettes', 'intensity_min' => 0.06, 'intensity_max' => 0.2, 'opening_hero' => false, 'support_only' => true ),
    );

    return $registry;
}

function se_get_asmr_visual_types() {
    return array_values( array_map( static function( $item ) { return $item['id']; }, se_get_asmr_visual_registry() ) );
}

function se_get_asmr_visual_hero_types() {
    return array_values( array_map( static function( $item ) { return $item['id']; }, array_filter( se_get_asmr_visual_registry(), static function( $item ) { return 'hero' === ( $item['priority'] ?? '' ) && empty( $item['support_only'] ); } ) ) );
}

function se_get_asmr_visual_landmark_types() {
    return array_values( array_map( static function( $item ) { return $item['id']; }, array_filter( se_get_asmr_visual_registry(), static function( $item ) { return 'landmark' === ( $item['category'] ?? '' ); } ) ) );
}

function se_get_asmr_visual_scene_types() {
    return array_values( array_map( static function( $item ) { return $item['id']; }, array_filter( se_get_asmr_visual_registry(), static function( $item ) { return 'scene' === ( $item['category'] ?? '' ); } ) ) );
}

function se_get_asmr_visual_support_types() {
    return array_values( array_map( static function( $item ) { return $item['id']; }, array_filter( se_get_asmr_visual_registry(), static function( $item ) { return ! empty( $item['support_only'] ); } ) ) );
}

function se_get_asmr_semantic_cues( $payload ) {
    $source = strtolower( implode( ' ', array(
        (string) ( $payload['concept'] ?? '' ),
        (string) ( $payload['object'] ?? '' ),
        (string) ( $payload['setting'] ?? '' ),
        (string) ( $payload['mood'] ?? '' ),
        (string) ( $payload['creative_goal'] ?? '' ),
        implode( ' ', (array) ( $payload['audio_layers'] ?? array() ) ),
        implode( ' ', (array) ( $payload['visual_layers'] ?? array() ) ),
    ) ) );

    $cue_map = array(
        'gastown' => array( 'steam_clock', 'cobblestone', 'brick', 'amber', 'urban_night', 'water_street_route' ),
        'vancouver' => array( 'harbor', 'fog', 'coastal', 'urban_night' ),
        'steam clock' => array( 'steam_clock', 'metal', 'bell' ),
        'snow' => array( 'snow', 'winter_hush', 'cold_air' ),
        'rain' => array( 'rain', 'wet_reflection' ),
        'harbor fog' => array( 'harbor', 'fog' ),
        'amber' => array( 'amber', 'streetlamp' ),
        'streetlamp' => array( 'streetlamp', 'amber' ),
        'water street' => array( 'water_street_route', 'corridor_traversal', 'first_person_unseen' ),
        'wet cobblestone' => array( 'wet_reflection', 'cobblestone' ),
        'cobblestone' => array( 'cobblestone' ),
        'brick' => array( 'brick' ),
        'neon reflection' => array( 'neon', 'wet_reflection' ),
        'granville' => array( 'granville', 'neon', 'nightlife', 'marquee', 'traffic', 'urban_night' ),
        'north shore' => array( 'north_shore', 'mountains', 'mist', 'wind', 'ridge', 'harbor' ),
        'northshore' => array( 'north_shore', 'mountains', 'mist', 'wind', 'ridge', 'harbor' ),
        'skytrain' => array( 'transit', 'skytrain' ),
        'bus' => array( 'transit', 'bus' ),
        'car horn' => array( 'transit', 'car_horn' ),
        'footsteps' => array( 'footsteps' ),
        'chatter' => array( 'chatter' ),
        'laughter' => array( 'laughter' ),
        'winter hush' => array( 'winter_hush', 'snow' ),
        '8-bit' => array( 'pixel_art', 'arcade' ),
        '8bit' => array( 'pixel_art', 'arcade' ),
        'pixel art' => array( 'pixel_art', 'dither' ),
        'pixel' => array( 'pixel_art' ),
        'dither' => array( 'dither' ),
        'arcade' => array( 'arcade' ),
        'chiptune' => array( 'arcade' ),
        'sprite' => array( 'pixel_art' ),
        'spiritual' => array( 'spiritual' ),
        'reverent' => array( 'spiritual' ),
        'haunted' => array( 'haunted' ),
        'cinematic' => array( 'cinematic' ),
        'city' => array( 'urban_night' ),
        'urban' => array( 'urban_night' ),
        'science world' => array( 'science_world', 'dome', 'landmark' ),
        'science_world' => array( 'science_world', 'dome', 'landmark' ),
        'chinatown gate' => array( 'chinatown_gate', 'landmark' ),
        'inukshuk' => array( 'inukshuk', 'landmark' ),
        'english bay' => array( 'english_bay', 'coastal', 'landmark' ),
        'maritime museum' => array( 'maritime_museum', 'harbor', 'landmark' ),
        'sail roof' => array( 'maritime_museum', 'sailroof', 'landmark' ),
    );

    $cues = array();
    foreach ( $cue_map as $needle => $tags ) {
        if ( false !== strpos( $source, $needle ) ) {
            $cues = array_merge( $cues, $tags );
        }
    }

    if ( false !== strpos( $source, 'vancouver' ) && false !== strpos( $source, 'snow' ) ) {
        $cues[] = 'vancouver_winter';
    }
    if ( false !== strpos( $source, 'gastown' ) && false !== strpos( $source, 'clock' ) ) {
        $cues[] = 'gastown_clock_focus';
    }

    return array_values( array_unique( $cues ) );
}

function se_apply_asmr_semantic_cues( $decoded, $payload ) {
    $runtime = max( 10, min( 30, floatval( $decoded['runtime_seconds'] ?? 20 ) ) );
    $cues = se_get_asmr_semantic_cues( $payload );
    if ( empty( $cues ) ) {
        return $decoded;
    }

    $decoded['style_tags'] = array_values( array_unique( array_merge( (array) ( $decoded['style_tags'] ?? array() ), $cues ) ) );

    $audio = is_array( $decoded['audio_events'] ?? null ) ? $decoded['audio_events'] : array();
    $visual = is_array( $decoded['visual_events'] ?? null ) ? $decoded['visual_events'] : array();
    $sync = is_array( $decoded['sync_points'] ?? null ) ? $decoded['sync_points'] : array();
    $selected_visual = array_values( array_unique( array_map( 'sanitize_key', (array) ( $payload['visual_layers'] ?? array() ) ) ) );
    $has_selected_visual = static function( $token ) use ( $selected_visual ) {
        return in_array( sanitize_key( $token ), $selected_visual, true );
    };

    if ( in_array( 'gastown_clock_focus', $cues, true ) || in_array( 'steam_clock', $cues, true ) ) {
        $audio[] = array( 'time' => 0.55, 'duration' => 1.4, 'engine' => 'steam_clock_burst', 'intensity' => 0.7, 'params' => array(), 'sync_role' => 'steam_clock_identity' );
        $audio[] = array( 'time' => $runtime * 0.68, 'duration' => 1.1, 'engine' => 'distant_bell_toll', 'intensity' => 0.58, 'params' => array(), 'sync_role' => 'clock_chime_reveal' );
        if ( $has_selected_visual( 'clock_face_reveal' ) ) {
            $visual[] = array( 'time' => 0.5, 'duration' => 2.1, 'visual_type' => 'clock_face_reveal', 'intensity' => 0.52, 'params' => array(), 'sync_role' => 'clock_face_entry' );
        }
        if ( $has_selected_visual( 'steam_plume_column' ) ) {
            $visual[] = array( 'time' => 0.3, 'duration' => 2.2, 'visual_type' => 'steam_plume_column', 'intensity' => 0.48, 'params' => array(), 'sync_role' => 'steam_column' );
        }
    }

    if ( in_array( 'snow', $cues, true ) || in_array( 'winter_hush', $cues, true ) ) {
        $audio[] = array( 'time' => 0, 'duration' => min( 8.5, $runtime * 0.5 ), 'engine' => 'snow_muffle', 'intensity' => 0.45, 'params' => array(), 'sync_role' => 'winter_bed' );
        $audio[] = array( 'time' => 0.2, 'duration' => min( 7.5, $runtime * 0.45 ), 'engine' => 'cold_air_hush', 'intensity' => 0.42, 'params' => array(), 'sync_role' => 'cold_air_layer' );
        $visual[] = array( 'time' => 0, 'duration' => min( 8, $runtime * 0.52 ), 'visual_type' => 'snow_drift', 'intensity' => 0.68, 'params' => array(), 'sync_role' => 'winter_particles' );
        $visual[] = array( 'time' => $runtime * 0.12, 'duration' => min( 7, $runtime * 0.4 ), 'visual_type' => 'winter_particulate_depth', 'intensity' => 0.62, 'params' => array(), 'sync_role' => 'depth_atmosphere' );
    }

    if ( in_array( 'harbor', $cues, true ) || in_array( 'fog', $cues, true ) ) {
        $audio[] = array( 'time' => 0, 'duration' => min( 9, $runtime * 0.6 ), 'engine' => 'harbor_fog_bed', 'intensity' => 0.46, 'params' => array(), 'sync_role' => 'harbor_ambience' );
        $visual[] = array( 'time' => 0, 'duration' => min( 9, $runtime * 0.64 ), 'visual_type' => 'harbor_mist', 'intensity' => 0.6, 'params' => array(), 'sync_role' => 'harbor_layer' );
    }

    if ( in_array( 'wet_reflection', $cues, true ) || in_array( 'rain', $cues, true ) ) {
        $audio[] = array( 'time' => $runtime * 0.15, 'duration' => min( 6.2, $runtime * 0.35 ), 'engine' => 'wet_street_shimmer', 'intensity' => 0.52, 'params' => array(), 'sync_role' => 'wet_street_texture' );
        if ( $has_selected_visual( 'wet_reflection_shimmer' ) ) {
            $visual[] = array( 'time' => $runtime * 0.12, 'duration' => min( 6.2, $runtime * 0.38 ), 'visual_type' => 'wet_reflection_shimmer', 'intensity' => 0.4, 'params' => array(), 'sync_role' => 'street_reflection' );
        }
        $visual[] = array( 'time' => $runtime * 0.2, 'duration' => min( 5.5, $runtime * 0.32 ), 'visual_type' => 'neon_wet_reflections', 'intensity' => 0.55, 'params' => array(), 'sync_role' => 'neon_reflection' );
    }

    if ( in_array( 'brick', $cues, true ) || in_array( 'cobblestone', $cues, true ) ) {
        if ( $has_selected_visual( 'brick_shadow_drift' ) ) {
            $visual[] = array( 'time' => 0.4, 'duration' => min( 6.5, $runtime * 0.45 ), 'visual_type' => 'brick_shadow_drift', 'intensity' => 0.36, 'params' => array(), 'sync_role' => 'material_grounding' );
        }
        $audio[] = array( 'time' => 0.9, 'duration' => 1.2, 'engine' => 'metal_resonance', 'intensity' => 0.42, 'params' => array(), 'sync_role' => 'urban_material_ping' );
    }

    if ( in_array( 'amber', $cues, true ) || in_array( 'streetlamp', $cues, true ) ) {
        if ( $has_selected_visual( 'amber_halo' ) ) {
            $visual[] = array( 'time' => 0.25, 'duration' => min( 8, $runtime * 0.52 ), 'visual_type' => 'amber_halo', 'intensity' => 0.36, 'params' => array(), 'sync_role' => 'streetlamp_glow' );
        }
    }

    if ( in_array( 'urban_night', $cues, true ) ) {
        $audio[] = array( 'time' => 0.05, 'duration' => min( 8, $runtime * 0.6 ), 'engine' => 'city_electrical_hum', 'intensity' => 0.35, 'params' => array(), 'sync_role' => 'city_grid_bed' );
    }

    if ( in_array( 'scanline_field', $cues, true ) && $has_selected_visual( 'scanline_field' ) ) {
        $visual[] = array( 'time' => 0.16, 'duration' => min( 4.2, $runtime * 0.28 ), 'visual_type' => 'scanline_field', 'intensity' => 0.34, 'params' => array(), 'sync_role' => 'crt_scan_support' );
    }

    $source = strtolower( implode( ' ', array(
        (string) ( $payload['concept'] ?? '' ),
        (string) ( $payload['object'] ?? '' ),
        (string) ( $payload['setting'] ?? '' ),
        (string) ( $payload['mood'] ?? '' ),
        (string) ( $payload['creative_goal'] ?? '' ),
        implode( ' ', (array) ( $payload['audio_layers'] ?? array() ) ),
        implode( ' ', (array) ( $payload['visual_layers'] ?? array() ) ),
    ) ) );
    if ( ! empty( $payload['use_end_card'] ) && ( false !== strpos( $source, 'event card' ) || false !== strpos( $source, 'screening' ) || false !== strpos( $source, 'film club' ) || false !== strpos( $source, 'deadline' ) ) ) {
        $end_card = is_array( $decoded['end_card'] ?? null ) ? $decoded['end_card'] : array();
        $end_card['use_end_card'] = true;
        $end_card['reveal_style'] = 'event_card';
        $decoded['end_card'] = $end_card;
    }

    $sync[] = array( 'time' => $runtime * 0.68, 'cue' => 'location-specific reveal toll', 'importance' => 'high' );

    usort( $audio, static function( $a, $b ) { return ( floatval( $a['time'] ?? 0 ) <=> floatval( $b['time'] ?? 0 ) ); } );
    usort( $visual, static function( $a, $b ) { return ( floatval( $a['time'] ?? 0 ) <=> floatval( $b['time'] ?? 0 ) ); } );
    usort( $sync, static function( $a, $b ) { return ( floatval( $a['time'] ?? 0 ) <=> floatval( $b['time'] ?? 0 ) ); } );

    $decoded['audio_events'] = $audio;
    $decoded['visual_events'] = $visual;
    $decoded['sync_points'] = $sync;
    return $decoded;
}

function se_enrich_asmr_event_density( $decoded ) {
    $runtime = max( 10, min( 30, floatval( $decoded['runtime_seconds'] ?? 20 ) ) );

    $visual_events = is_array( $decoded['visual_events'] ?? null ) ? $decoded['visual_events'] : array();
    $audio_events  = is_array( $decoded['audio_events'] ?? null ) ? $decoded['audio_events'] : array();

    if ( empty( $visual_events ) || ( $visual_events[0]['time'] ?? 99 ) > 0.12 ) {
        $visual_events[] = array(
            'time' => 0,
            'duration' => min( 6.5, $runtime * 0.42 ),
            'visual_type' => 'volumetric_fog',
            'intensity' => 0.45,
            'params' => array( 'drift' => 0.35 ),
            'sync_role' => 'opening_atmosphere',
        );
    }

    $needs_early = true;
    foreach ( $visual_events as $event ) {
        if ( ( $event['time'] ?? 99 ) <= 0.8 && ( $event['intensity'] ?? 0 ) >= 0.32 ) {
            $needs_early = false;
            break;
        }
    }
    if ( $needs_early ) {
        $visual_events[] = array(
            'time' => 0.46,
            'duration' => 1.35,
            'visual_type' => 'pulse_orb',
            'intensity' => 0.56,
            'params' => array(),
            'sync_role' => 'early_reveal',
        );
    }

    $min_visual_count = max( 8, (int) ceil( $runtime * 0.7 ) );
    $seed_types = array( 'chromatic_veil', 'starfield_drift', 'refraction_ripple', 'halo_glyphs' );
    while ( count( $visual_events ) < $min_visual_count ) {
        $idx = count( $visual_events );
        $visual_events[] = array(
            'time' => min( $runtime - 0.15, 0.2 + ( $idx * ( $runtime / ( $min_visual_count + 1 ) ) ) ),
            'duration' => 0.9 + ( ( $idx % 3 ) * 0.45 ),
            'visual_type' => $seed_types[ $idx % count( $seed_types ) ],
            'intensity' => 0.22 + ( ( $idx % 4 ) * 0.08 ),
            'params' => array(),
            'sync_role' => 'support_layer',
        );
    }

    usort( $visual_events, static function( $a, $b ) {
        return ( $a['time'] <=> $b['time'] );
    } );

    $max_gap = max( 2.6, $runtime * 0.2 );
    $patched_visuals = array();
    $prev_time = 0;
    foreach ( $visual_events as $event ) {
        $time = floatval( $event['time'] ?? 0 );
        if ( $time - $prev_time > $max_gap ) {
            $patched_visuals[] = array(
                'time' => $prev_time + ( $max_gap * 0.48 ),
                'duration' => min( 2.8, max( 1.1, $max_gap * 0.6 ) ),
                'visual_type' => 'volumetric_fog',
                'intensity' => 0.24,
                'params' => array(),
                'sync_role' => 'bridge_motion',
            );
        }
        $patched_visuals[] = $event;
        $prev_time = $time;
    }
    $visual_events = $patched_visuals;

    if ( empty( $audio_events ) || ( $audio_events[0]['time'] ?? 99 ) > 0.24 ) {
        $audio_events[] = array(
            'time' => 0,
            'duration' => min( 4.5, $runtime * 0.3 ),
            'engine' => 'ghost_pad',
            'intensity' => 0.24,
            'params' => array( 'from' => 140, 'to' => 188 ),
            'sync_role' => 'ambient_lead_in',
        );
    }

    $min_audio_count = max( 7, (int) ceil( $runtime * 0.42 ) );
    while ( count( $audio_events ) < $min_audio_count ) {
        $idx = count( $audio_events );
        $audio_events[] = array(
            'time' => min( $runtime - 0.12, 0.3 + ( $idx * ( $runtime / ( $min_audio_count + 2 ) ) ) ),
            'duration' => 0.3 + ( ( $idx % 4 ) * 0.18 ),
            'engine' => ( 0 === $idx % 2 ) ? 'shimmer_swirl' : 'particle_spark',
            'intensity' => 0.18 + ( ( $idx % 5 ) * 0.06 ),
            'params' => array(),
            'sync_role' => 'support_texture',
        );
    }

    usort( $visual_events, static function( $a, $b ) {
        return ( $a['time'] <=> $b['time'] );
    } );
    usort( $audio_events, static function( $a, $b ) {
        return ( $a['time'] <=> $b['time'] );
    } );

    $decoded['visual_events'] = $visual_events;
    $decoded['audio_events']  = $audio_events;
    return $decoded;
}



function se_get_asmr_audio_layers_allowed() {
    return array(
        'footsteps', 'footsteps_snow_mode', 'rain_ambience', 'wind_gust', 'crowd_murmur', 'laughter_burst',
        'skytrain_pass', 'bus_pass', 'car_horn_short', 'steam_clock',
        'seabus_horn', 'ocean_waves', 'gulls_distant', 'crosswalk_chirp', 'compass_tap', 'bike_bell', 'skateboard_roll', 'siren_distant',
        'gastown_clock_whistle', 'church_bells', 'harbour_noon_horn', 'nine_oclock_gun'
    );
}

function se_get_asmr_visual_layers_allowed() {
    return array(
        'rain_streaks', 'snow_drift', 'harbor_mist', 'clear_cold_shimmer',
        'gastown_scene', 'granville_scene', 'north_shore_scene', 'waterfront_scene',
        'gastown_clock_silhouette', 'science_world_dome', 'chinatown_gate', 'english_bay_inukshuk', 'maritime_museum_sailroof',
        'lions_gate_bridge', 'bc_place_dome', 'port_cranes', 'planetarium_dome', 'starfield_projection', 'constellation_lines', 'canada_place_sails',
        'cobblestone_perspective', 'brick_wall_parallax', 'puddle_reflections', 'streetlamp_halo_row',
        'water_street_corridor', 'station_threshold_glow', 'steam_clock_approach', 'angled_building_split', 'lamp_rhythm_row', 'wet_cobble_axis',
        'street_corridor_vanishing_point', 'curb_line_perspective', 'facade_plane_bands', 'awning_edge_rows',
        'heritage_window_grid', 'district_sign_columns', 'wet_reflection_axis', 'skyline_mask_lowrise',
        'skytrain_track', 'skytrain_pass_visual', 'bus_pass_visual',
        'scanline_field', 'glitch_flash', 'signal_bars', 'chromatic_veil', 'neon_sign_flicker', 'ocean_surface_shimmer', 'seabus_silhouette',
        'granville_neon_marquee', 'traffic_light_glow', 'northshore_mountain_ridge', 'mountain_mist_layers', 'volumetric_fog',
        'pixel_grid_pulse', 'starfield_drift', 'wet_reflection_shimmer', 'neon_wet_reflections', 'gull_silhouettes'
    );
}

function se_get_asmr_visual_to_audio_map() {
    return array(
        'seabus_silhouette' => array( 'seabus_horn' ),
        'skytrain_pass_visual' => array( 'skytrain_pass' ),
        'rain_streaks' => array( 'rain_ambience' ),
    );
}

function se_get_asmr_scene_visual_support_map() {
    return array(
        'gastown_scene' => array( 'water_street_corridor', 'station_threshold_glow', 'wet_cobble_axis', 'lamp_rhythm_row', 'steam_clock_approach', 'angled_building_split', 'gastown_clock_silhouette' ),
        'granville_scene' => array( 'street_corridor_vanishing_point', 'district_sign_columns', 'wet_reflection_axis', 'granville_neon_marquee', 'puddle_reflections' ),
        'north_shore_scene' => array( 'northshore_mountain_ridge', 'mountain_mist_layers', 'gull_silhouettes' ),
        'waterfront_scene' => array( 'ocean_surface_shimmer', 'seabus_silhouette', 'canada_place_sails', 'gull_silhouettes' ),
    );
}

function se_get_asmr_legacy_audio_map() {
    return array(
        'rain' => 'rain_ambience',
        'chatter' => 'crowd_murmur',
        'laughter' => 'laughter_burst',
        'skytrain' => 'skytrain_pass',
        'bus' => 'bus_pass',
        'car_horn' => 'car_horn_short',
    );
}

function se_get_asmr_mapped_visual_layers_from_audio( $audio_layers ) {
    $map = array(
        'skytrain_pass' => 'skytrain_pass_visual',
        'bus_pass' => 'bus_pass_visual',
        'rain_ambience' => 'rain_streaks',
        'steam_clock' => 'steam_clock_approach',
        'gastown_clock_whistle' => 'steam_clock_approach',
        'church_bells' => 'constellation_lines',
        'harbour_noon_horn' => 'waterfront_scene',
        'ocean_waves' => 'ocean_surface_shimmer',
        'seabus_horn' => 'seabus_silhouette',
    );
    $visual = array();
    foreach ( (array) $audio_layers as $token ) {
        $key = sanitize_key( $token );
        if ( isset( $map[ $key ] ) ) {
            $visual[] = $map[ $key ];
        }
    }
    return array_values( array_unique( $visual ) );
}

function se_get_asmr_vancouver_derived_payload( $payload ) {
    $audio_layers = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['audio_layers'] ?? array() ) ) ) );
    $visual_layers = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['visual_layers'] ?? array() ) ) ) );
    $motif_line = empty( $visual_layers ) ? 'unselected motif rack' : implode( ' + ', $visual_layers );
    $foley_text = empty( $audio_layers ) ? 'soft civic ambience' : implode( ', ', $audio_layers );
    $payload['concept'] = sprintf( 'Vancouver Motif Stack: %s', $motif_line );
    $payload['object'] = 'city motif rack';
    $payload['setting'] = 'retro arcade CRT Vancouver night world';
    $payload['mood'] = 'atmospheric, tactile, and cinematic';
    $payload['creative_goal'] = sprintf( 'Build a readable motif-forward timeline with %s while preserving coherent visual geography.', $foley_text );
    return $payload;
}


function se_get_asmr_motif_brief( $payload ) {
    $visual_layers = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['visual_layers'] ?? array() ) ) ) );
    $audio_layers = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['audio_layers'] ?? array() ) ) ) );

    $scene = array_values( array_intersect( $visual_layers, array( 'gastown_scene', 'granville_scene', 'north_shore_scene', 'waterfront_scene' ) ) );
    $atmosphere = array_values( array_intersect( $visual_layers, array( 'rain_streaks', 'snow_drift', 'harbor_mist', 'clear_cold_shimmer' ) ) );
    $signature = array_values( array_intersect( $visual_layers, array( 'science_world_dome', 'chinatown_gate', 'lions_gate_bridge', 'gastown_clock_silhouette', 'seabus_silhouette', 'planetarium_dome', 'starfield_projection', 'canada_place_sails' ) ) );

    $parts = array();
    if ( ! empty( $scene ) ) {
        $parts[] = 'Selected scene: ' . implode( ', ', $scene ) . '.';
    }
    if ( ! empty( $atmosphere ) ) {
        $parts[] = 'Atmosphere: ' . implode( ', ', $atmosphere ) . '.';
    }
    if ( ! empty( $signature ) ) {
        $parts[] = 'Signature: ' . implode( ', ', $signature ) . '.';
    }
    if ( ! empty( $audio_layers ) ) {
        $parts[] = 'Audio: ' . implode( ' + ', $audio_layers ) . '.';
    }

    return implode( ' ', $parts );
}

function se_stage_selected_visual_motifs( $visual_layers, $runtime, $story_beats ) {
    $windows = array(
        'opening' => array( 't0' => 0.0, 't1' => $runtime * 0.25 ),
        'arrival' => array( 't0' => $runtime * 0.25, 't1' => $runtime * 0.5 ),
        'lift' => array( 't0' => $runtime * 0.5, 't1' => $runtime * 0.75 ),
        'resolve' => array( 't0' => $runtime * 0.75, 't1' => $runtime ),
    );

    $scenes = array( 'gastown_scene', 'granville_scene', 'north_shore_scene', 'waterfront_scene' );
    $atmosphere = array( 'rain_streaks', 'snow_drift', 'harbor_mist', 'clear_cold_shimmer', 'ocean_surface_shimmer' );
    $landmarks = array( 'gastown_clock_silhouette', 'science_world_dome', 'chinatown_gate', 'english_bay_inukshuk', 'maritime_museum_sailroof', 'lions_gate_bridge', 'bc_place_dome', 'port_cranes', 'planetarium_dome', 'starfield_projection', 'constellation_lines', 'canada_place_sails', 'seabus_silhouette' );
    $transit = array( 'skytrain_pass_visual', 'skytrain_track', 'bus_pass_visual', 'seabus_silhouette' );

    $selected = array_values( array_unique( array_values( array_filter( array_map( 'sanitize_key', (array) $visual_layers ) ) ) ) );
    $opening = array();
    foreach ( $selected as $token ) {
        if ( empty( $opening ) && in_array( $token, $scenes, true ) ) { $opening[] = $token; continue; }
        if ( 0 === count( array_intersect( $opening, $atmosphere ) ) && in_array( $token, $atmosphere, true ) ) { $opening[] = $token; continue; }
        if ( 0 === count( array_intersect( $opening, $landmarks ) ) && in_array( $token, $landmarks, true ) ) { $opening[] = $token; continue; }
    }

    $remaining = array_values( array_diff( $selected, $opening ) );
    $arrival = array_slice( $remaining, 0, min( 2, count( $remaining ) ) );
    $remaining = array_values( array_diff( $remaining, $arrival ) );

    $lift = array_values( array_intersect( $remaining, $transit ) );
    if ( empty( $lift ) && ! empty( $remaining ) ) { $lift[] = $remaining[0]; }
    if ( count( $lift ) > 2 ) { $lift = array_slice( $lift, 0, 2 ); }
    $remaining = array_values( array_diff( $remaining, $lift ) );

    $resolve = array();
    if ( ! empty( $remaining ) ) {
        $resolve[] = $remaining[0];
    } elseif ( ! empty( $opening ) ) {
        $resolve[] = $opening[0];
    }

    return array(
        'opening' => $opening,
        'arrival' => $arrival,
        'lift' => $lift,
        'resolve' => $resolve,
        'windows' => $windows,
    );
}

function se_inject_asmr_vancouver_anchors( $decoded, $payload ) {
    $link_av = ! array_key_exists( 'link_av', $payload ) || ! empty( $payload['link_av'] );
    $audio_layers = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['audio_layers'] ?? array() ) ) ) );
    $visual_layers = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['visual_layers'] ?? array() ) ) ) );
    if ( empty( $audio_layers ) && empty( $visual_layers ) ) {
        return $decoded;
    }

    if ( $link_av ) {
        foreach ( se_get_asmr_visual_to_audio_map() as $visual_token => $mapped_audio ) {
            if ( ! in_array( $visual_token, $visual_layers, true ) ) {
                continue;
            }
            foreach ( $mapped_audio as $audio_token ) {
                if ( 'footsteps_snow_mode' === $audio_token && ! in_array( 'footsteps', $audio_layers, true ) ) {
                    continue;
                }
                $audio_layers[] = $audio_token;
            }
        }
        $audio_layers = array_values( array_unique( $audio_layers ) );
    }


    $runtime = max( 10, min( 30, floatval( $decoded['runtime_seconds'] ?? 20 ) ) );
    $audio = is_array( $decoded['audio_events'] ?? null ) ? $decoded['audio_events'] : array();
    $visual = is_array( $decoded['visual_events'] ?? null ) ? $decoded['visual_events'] : array();
    $story_beats = is_array( $decoded['story_beats'] ?? null ) ? $decoded['story_beats'] : array();

    $has_audio = static function( $events, $engine, $t0, $t1 ) {
        foreach ( $events as $event ) {
            $time = floatval( $event['time'] ?? -1 );
            if ( ( $event['engine'] ?? '' ) === $engine && $time >= $t0 && $time <= $t1 ) {
                return true;
            }
        }
        return false;
    };
    $has_visual = static function( $events, $token, $t0, $t1 ) {
        foreach ( $events as $event ) {
            $time = floatval( $event['time'] ?? -1 );
            if ( ( $event['visual_type'] ?? '' ) === $token && $time >= $t0 && $time <= $t1 ) {
                return true;
            }
        }
        return false;
    };

    $audio_defaults = array(
        'rain_ambience' => array( 'engine' => 'rain_close', 'time' => 0.02, 'duration' => max( 7.5, $runtime * 0.78 ), 'intensity' => 0.48, 'params' => array(), 'sync_role' => 'repair_rain_bed' ),
        'ocean_waves' => array( 'engine' => 'ocean_waves', 'time' => 0.02, 'duration' => max( 8.2, $runtime * 0.82 ), 'intensity' => 0.45, 'params' => array( 'fade_out' => 3.0, 'foam' => 0.35 ), 'sync_role' => 'repair_ocean_bed' ),
        'seabus_horn' => array( 'engine' => 'seabus_horn', 'time' => $runtime * 0.56, 'duration' => 2.2, 'intensity' => 0.36, 'params' => array( 'attack' => 0.28, 'release' => 1.8 ), 'sync_role' => 'repair_seabus_horn' ),
        'gastown_clock_whistle' => array( 'engine' => 'gastown_clock_whistle', 'time' => $runtime * 0.34, 'duration' => 2.4, 'intensity' => 0.46, 'params' => array( 'breathy' => 0.72, 'vibrato' => 0.46 ), 'sync_role' => 'repair_gastown_whistle' ),
        'church_bells' => array( 'engine' => 'church_bells', 'time' => $runtime * 0.62, 'duration' => 5.2, 'intensity' => 0.3, 'params' => array( 'decay' => 5.4, 'brightness' => 0.26 ), 'sync_role' => 'repair_church_bells' ),
        'harbour_noon_horn' => array( 'engine' => 'harbour_noon_horn', 'time' => $runtime * 0.46, 'duration' => 4.6, 'intensity' => 0.42, 'params' => array( 'attack' => 0.42, 'release' => 3.1, 'wobble' => 0.05 ), 'sync_role' => 'repair_harbour_horn' ),
    );

    foreach ( $audio_layers as $layer ) {
        if ( ! isset( $audio_defaults[ $layer ] ) ) {
            continue;
        }
        $event = $audio_defaults[ $layer ];
        if ( ! $has_audio( $audio, $event['engine'], max( 0, $event['time'] - 1.0 ), min( $runtime, $event['time'] + 1.0 ) ) ) {
            $audio[] = $event;
        }
    }

    $staged = se_stage_selected_visual_motifs( $visual_layers, $runtime, $story_beats );
    $windows = $staged['windows'];
    foreach ( array( 'opening', 'arrival', 'lift', 'resolve' ) as $beat ) {
        $tokens = $staged[ $beat ] ?? array();
        $window = $windows[ $beat ];
        $count = count( $tokens );
        if ( 0 === $count ) {
            continue;
        }
        foreach ( array_values( $tokens ) as $idx => $token ) {
            if ( $has_visual( $visual, $token, $window['t0'], $window['t1'] ) ) {
                continue;
            }
            $slot = ( $idx + 1 ) / ( $count + 1 );
            $time = $window['t0'] + ( $window['t1'] - $window['t0'] ) * $slot;
            if ( 'opening' === $beat && 0 === $idx ) {
                $time = min( 0.48, max( 0.12, $time ) );
            }
            $visual[] = array(
                'time' => round( $time, 3 ),
                'duration' => min( max( 1.8, $runtime * 0.2 ), $runtime * 0.34 ),
                'visual_type' => $token,
                'intensity' => ( 'resolve' === $beat ) ? 0.5 : 0.62,
                'params' => array(),
                'sync_role' => 'repair_' . $beat . '_motif',
            );
        }
    }

    $scene_or_landmark = array(
        'gastown_scene', 'granville_scene', 'north_shore_scene', 'waterfront_scene', 'gastown_clock_silhouette', 'science_world_dome',
        'chinatown_gate', 'english_bay_inukshuk', 'maritime_museum_sailroof', 'lions_gate_bridge', 'bc_place_dome', 'port_cranes', 'seabus_silhouette',
        'planetarium_dome', 'starfield_projection', 'constellation_lines', 'canada_place_sails'
    );
    $selected_scene_landmarks = array_values( array_intersect( $visual_layers, $scene_or_landmark ) );
    if ( ! empty( $selected_scene_landmarks ) ) {
        $already_early = false;
        foreach ( $visual as $event ) {
            if ( in_array( $event['visual_type'] ?? '', $selected_scene_landmarks, true ) && floatval( $event['time'] ?? 99 ) <= 0.6 ) {
                $already_early = true;
                break;
            }
        }
        if ( ! $already_early ) {
            $visual[] = array( 'time' => 0.32, 'duration' => min( 2.6, $runtime * 0.2 ), 'visual_type' => $selected_scene_landmarks[0], 'intensity' => 0.66, 'params' => array(), 'sync_role' => 'early_readability_anchor' );
        }
    }

    usort( $audio, static function( $a, $b ) { return (float) ( $a['time'] ?? 0 ) <=> (float) ( $b['time'] ?? 0 ); } );
    usort( $visual, static function( $a, $b ) { return (float) ( $a['time'] ?? 0 ) <=> (float) ( $b['time'] ?? 0 ); } );

    $decoded['style_tags'] = se_build_asmr_style_tags( $decoded, array( 'retro_arcade_crt', 'beat_compiled' ) );
    $decoded['audio_events'] = $audio;
    $decoded['visual_events'] = $visual;
    $decoded['presentation_note'] = 'Motif-repair injection: OpenAI composes the story arc, deterministic pass only ensures selected motif presence and readability.';
    return $decoded;
}

function se_extract_json_object( $raw ) {
    $raw = trim( (string) $raw );
    if ( preg_match( '/```(?:json)?\s*(.+?)```/is', $raw, $matches ) ) {
        $raw = trim( $matches[1] );
    }
    $start = strpos( $raw, '{' );
    $end   = strrpos( $raw, '}' );
    if ( false === $start || false === $end || $end <= $start ) {
        return '';
    }
    return substr( $raw, $start, $end - $start + 1 );
}


function se_get_asmr_default_story_beats( $runtime ) {
    $runtime = max( 10, min( 30, floatval( $runtime ) ) );
    return array(
        array( 't0' => 0.0, 't1' => min( 4.0, $runtime * 0.2 ), 'beat' => 'Opening', 'intent' => 'Set the atmosphere and establish the primary subject.' ),
        array( 't0' => min( 4.0, $runtime * 0.2 ), 't1' => min( 9.0, $runtime * 0.45 ), 'beat' => 'Arrival', 'intent' => 'Introduce movement or a location reveal with restraint.' ),
        array( 't0' => min( 9.0, $runtime * 0.45 ), 't1' => min( 15.0, $runtime * 0.75 ), 'beat' => 'Lift', 'intent' => 'Deliver the strongest reveal and synchronized signature cue.' ),
        array( 't0' => min( 15.0, $runtime * 0.75 ), 't1' => $runtime, 'beat' => 'Resolve', 'intent' => 'Simplify and settle back into the primary world.' ),
    );
}


function se_pick_random_from_allowed( $candidates, $allowed ) {
    $pool = array_values( array_intersect( array_map( 'sanitize_key', (array) $candidates ), array_map( 'sanitize_key', (array) $allowed ) ) );
    if ( empty( $pool ) ) {
        return '';
    }
    shuffle( $pool );
    return $pool[0];
}


function se_is_gastown_route_selection( $visual_layers ) {
    $visual = array_values( array_unique( array_map( 'sanitize_key', (array) $visual_layers ) ) );
    $route_tokens = array( 'gastown_scene', 'water_street_corridor', 'station_threshold_glow', 'steam_clock_approach', 'angled_building_split', 'lamp_rhythm_row', 'wet_cobble_axis' );
    return count( array_intersect( $visual, $route_tokens ) ) >= 2;
}

function se_restrict_generation_plan_to_selected_motifs( $plan, $audio_layers, $visual_layers ) {
    $plan = is_array( $plan ) ? $plan : array();
    $plan['visual_plan'] = is_array( $plan['visual_plan'] ?? null ) ? $plan['visual_plan'] : array();
    $plan['audio_plan'] = is_array( $plan['audio_plan'] ?? null ) ? $plan['audio_plan'] : array();

    $selected_audio = array_values( array_unique( array_map( 'sanitize_key', (array) $audio_layers ) ) );
    $selected_visual = array_values( array_unique( array_map( 'sanitize_key', (array) $visual_layers ) ) );

    $visual_slots = array( 'primary_scene', 'landmark', 'motion', 'resolve_landmark' );
    foreach ( $visual_slots as $slot ) {
        $candidate = sanitize_key( $plan['visual_plan'][ $slot ] ?? '' );
        $plan['visual_plan'][ $slot ] = in_array( $candidate, $selected_visual, true ) ? $candidate : '';
    }

    foreach ( array( 'atmosphere', 'texture', 'overlay_family' ) as $slot ) {
        $candidate = sanitize_key( $plan['visual_plan'][ $slot ] ?? '' );
        $plan['visual_plan'][ $slot ] = in_array( $candidate, $selected_visual, true ) ? $candidate : '';
    }

    $selected_scenes = array_values( array_intersect( $selected_visual, se_get_asmr_visual_scene_types() ) );
    $selected_landmarks = array_values( array_intersect( $selected_visual, se_get_asmr_visual_landmark_types() ) );
    $selected_support = array_values( array_intersect( $selected_visual, se_get_asmr_visual_support_types() ) );

    if ( empty( $plan['visual_plan']['primary_scene'] ) || ! in_array( $plan['visual_plan']['primary_scene'], $selected_scenes, true ) ) {
        $plan['visual_plan']['primary_scene'] = ! empty( $selected_scenes ) ? $selected_scenes[0] : '';
    }

    if ( empty( $plan['visual_plan']['landmark'] ) || ! in_array( $plan['visual_plan']['landmark'], $selected_landmarks, true ) ) {
        $plan['visual_plan']['landmark'] = ! empty( $selected_landmarks ) ? $selected_landmarks[0] : '';
    }
    if ( empty( $plan['visual_plan']['resolve_landmark'] ) || ! in_array( $plan['visual_plan']['resolve_landmark'], $selected_landmarks, true ) ) {
        $plan['visual_plan']['resolve_landmark'] = ! empty( $selected_landmarks ) ? end( $selected_landmarks ) : '';
    }

    if ( ! empty( $selected_landmarks ) && empty( $plan['visual_plan']['primary_scene'] ) && 1 === count( $selected_landmarks ) ) {
        $plan['visual_plan']['primary_scene'] = $selected_landmarks[0];
    }

    $overlay = sanitize_key( $plan['visual_plan']['overlay_family'] ?? '' );
    $plan['visual_plan']['overlay_family'] = in_array( $overlay, $selected_support, true ) ? $overlay : '';

    if ( se_is_gastown_route_selection( $selected_visual ) ) {
        if ( in_array( 'gastown_scene', $selected_visual, true ) ) {
            $plan['visual_plan']['primary_scene'] = 'gastown_scene';
        }
        if ( in_array( 'steam_clock_approach', $selected_visual, true ) ) {
            $plan['visual_plan']['landmark'] = 'steam_clock_approach';
            $plan['visual_plan']['resolve_landmark'] = 'steam_clock_approach';
        } elseif ( in_array( 'gastown_clock_silhouette', $selected_visual, true ) ) {
            $plan['visual_plan']['landmark'] = 'gastown_clock_silhouette';
            $plan['visual_plan']['resolve_landmark'] = 'gastown_clock_silhouette';
        }
        if ( in_array( 'water_street_corridor', $selected_visual, true ) ) {
            $plan['visual_plan']['motion'] = 'water_street_corridor';
        }
        if ( in_array( 'wet_cobble_axis', $selected_visual, true ) ) {
            $plan['visual_plan']['texture'] = 'wet_cobble_axis';
        }
        if ( in_array( 'lamp_rhythm_row', $selected_visual, true ) ) {
            $plan['visual_plan']['overlay_family'] = 'lamp_rhythm_row';
        }
    }

    $audio_slots = array( 'primary_bed', 'secondary_bed', 'transit_cue' );
    foreach ( $audio_slots as $slot ) {
        $candidate = sanitize_key( $plan['audio_plan'][ $slot ] ?? '' );
        $plan['audio_plan'][ $slot ] = in_array( $candidate, $selected_audio, true ) ? $candidate : '';
    }

    $signature = array_slice( array_values( array_filter( array_map( 'sanitize_key', (array) ( $plan['audio_plan']['signature_cues'] ?? array() ) ) ) ), 0, 3 );
    $plan['audio_plan']['signature_cues'] = array_values( array_intersect( $signature, $selected_audio ) );

    if ( empty( $plan['audio_plan']['primary_bed'] ) && ! empty( $selected_audio ) ) {
        $plan['audio_plan']['primary_bed'] = se_pick_random_from_allowed( $selected_audio, $selected_audio );
    }

    return $plan;
}

function se_build_asmr_style_tags( $decoded, $base_tags = array() ) {
    $base = array_values( array_filter( array_map( 'sanitize_key', (array) $base_tags ) ) );
    $existing = array_values( array_filter( array_map( 'sanitize_key', (array) ( $decoded['style_tags'] ?? array() ) ) ) );
    $broad_allowed = array( 'atmospheric', 'cinematic', 'coastal', 'urban_night', 'retro_arcade_crt', 'beat_compiled' );
    $broad = array_values( array_intersect( $existing, $broad_allowed ) );

    $plan = is_array( $decoded['generation_plan'] ?? null ) ? $decoded['generation_plan'] : array();
    $visual_plan = is_array( $plan['visual_plan'] ?? null ) ? $plan['visual_plan'] : array();
    $audio_plan = is_array( $plan['audio_plan'] ?? null ) ? $plan['audio_plan'] : array();

    $hero = array();
    foreach ( array( 'primary_scene', 'landmark' ) as $slot ) {
        $token = sanitize_key( $visual_plan[ $slot ] ?? '' );
        if ( $token ) {
            $hero[] = $token;
        }
    }
    $primary_bed = sanitize_key( $audio_plan['primary_bed'] ?? '' );
    if ( $primary_bed ) {
        $hero[] = $primary_bed;
    }

    $hero = array_slice( array_values( array_unique( $hero ) ), 0, 2 );
    return array_values( array_unique( array_merge( $base, $broad, $hero ) ) );
}

function se_validate_visual_timeline_matches_plan( $plan, $visual_events ) {
    $visual_plan = is_array( $plan['visual_plan'] ?? null ) ? $plan['visual_plan'] : array();
    $events = is_array( $visual_events ) ? $visual_events : array();

    $primary_scene = sanitize_key( $visual_plan['primary_scene'] ?? '' );
    $landmark = sanitize_key( $visual_plan['landmark'] ?? '' );
    $motion = sanitize_key( $visual_plan['motion'] ?? '' );
    $resolve_landmark = sanitize_key( $visual_plan['resolve_landmark'] ?? '' );

    $hero_types = se_get_asmr_visual_hero_types();
    $support_types = se_get_asmr_visual_support_types();

    $count_type = static function( $token ) use ( $events ) {
        if ( ! $token ) return 0;
        return count( array_filter( $events, static function( $event ) use ( $token ) { return sanitize_key( $event['visual_type'] ?? '' ) === $token; } ) );
    };

    $repairs = array();
    if ( $primary_scene && 0 === $count_type( $primary_scene ) ) {
        $repairs[] = array( 'time' => 0.2, 'duration' => 4.8, 'visual_type' => $primary_scene, 'intensity' => 0.76, 'params' => array(), 'sync_role' => 'repair_primary_scene' );
    }
    if ( $landmark && 0 === $count_type( $landmark ) ) {
        $repairs[] = array( 'time' => 9.4, 'duration' => 4.8, 'visual_type' => $landmark, 'intensity' => 0.82, 'params' => array(), 'sync_role' => 'repair_landmark_hero' );
    }
    if ( $motion && 0 === $count_type( $motion ) ) {
        $repairs[] = array( 'time' => 10.2, 'duration' => 3.4, 'visual_type' => $motion, 'intensity' => 0.4, 'params' => array(), 'sync_role' => 'repair_motion' );
    }
    if ( $resolve_landmark && 0 === $count_type( $resolve_landmark ) ) {
        $repairs[] = array( 'time' => 15.3, 'duration' => 4.1, 'visual_type' => $resolve_landmark, 'intensity' => 0.78, 'params' => array(), 'sync_role' => 'repair_resolve_landmark' );
    }

    $with_repairs = array_merge( $events, $repairs );

    $beat_windows = array(
        array( 'label' => 'Opening', 't0' => 0.0, 't1' => 5.0 ),
        array( 'label' => 'Arrival', 't0' => 5.0, 't1' => 10.0 ),
        array( 'label' => 'Lift', 't0' => 10.0, 't1' => 15.0 ),
        array( 'label' => 'Resolve', 't0' => 15.0, 't1' => 20.5 ),
    );

    foreach ( $beat_windows as $beat ) {
        $beat_events = array_values( array_filter( $with_repairs, static function( $event ) use ( $beat ) {
            $time = floatval( $event['time'] ?? 0 );
            return $time >= $beat['t0'] && $time < $beat['t1'];
        } ) );
        $hero_count = count( array_filter( $beat_events, static function( $event ) use ( $hero_types ) {
            return in_array( sanitize_key( $event['visual_type'] ?? '' ), $hero_types, true );
        } ) );
        $support_count = count( array_filter( $beat_events, static function( $event ) use ( $support_types ) {
            return in_array( sanitize_key( $event['visual_type'] ?? '' ), $support_types, true );
        } ) );

        if ( $support_count > ( $hero_count * 2 + 2 ) && ! empty( $beat_events ) ) {
            foreach ( $with_repairs as $idx => $event ) {
                if ( floatval( $event['time'] ?? 0 ) >= $beat['t0'] && floatval( $event['time'] ?? 0 ) < $beat['t1'] && in_array( sanitize_key( $event['visual_type'] ?? '' ), $support_types, true ) ) {
                    $with_repairs[ $idx ]['intensity'] = min( 0.18, floatval( $event['intensity'] ?? 0.2 ) );
                }
            }
        }
    }

    usort( $with_repairs, static function( $a, $b ) { return (float) $a['time'] <=> (float) $b['time']; } );
    return $with_repairs;
}

function se_compile_visual_events_from_plan( $plan, $runtime ) {
    $runtime = max( 10, min( 30, floatval( $runtime ) ) );
    $hero_types = se_get_asmr_visual_hero_types();
    $support_types = se_get_asmr_visual_support_types();
    $visual_plan = is_array( $plan['visual_plan'] ?? null ) ? $plan['visual_plan'] : array();

    $primary_scene = sanitize_key( $visual_plan['primary_scene'] ?? '' );
    $atmosphere = sanitize_key( $visual_plan['atmosphere'] ?? '' );
    $landmark = sanitize_key( $visual_plan['landmark'] ?? '' );
    $motion = sanitize_key( $visual_plan['motion'] ?? '' );
    $texture = sanitize_key( $visual_plan['texture'] ?? '' );
    $overlay = sanitize_key( $visual_plan['overlay_family'] ?? '' );
    $resolve_landmark = sanitize_key( $visual_plan['resolve_landmark'] ?? '' );

    if ( ! $resolve_landmark ) {
        $resolve_landmark = $landmark;
    }

    $events = array();
    $route_params = array();
    if ( se_is_gastown_route_selection( array( $primary_scene, $landmark, $motion, $texture, $overlay, $resolve_landmark ) ) || 'gastown_scene' === $primary_scene ) {
        $route_params = array(
            'route_id' => 'gastown_water_street_walk',
            'pov' => 'first_person_unseen',
            'movement' => 'forward_walk',
            'framing' => 'off_center_landmark',
            'corridor_bias' => 'strong',
            'asymmetry_bias' => 'high',
        );
    }

    if ( $atmosphere ) {
        $events[] = array( 'time' => 0.0, 'duration' => min( $runtime - 0.2, 7.6 ), 'visual_type' => $atmosphere, 'intensity' => 0.3, 'params' => $route_params, 'sync_role' => 'opening_atmosphere' );
    }

    if ( $primary_scene && in_array( $primary_scene, $hero_types, true ) ) {
        $events[] = array( 'time' => 0.2, 'duration' => 5.2, 'visual_type' => $primary_scene, 'intensity' => 0.76, 'params' => $route_params, 'sync_role' => 'opening_scene_hero' );
    }

    if ( $overlay && in_array( $overlay, $support_types, true ) ) {
        $events[] = array( 'time' => 0.4, 'duration' => 3.2, 'visual_type' => $overlay, 'intensity' => 0.08, 'params' => $route_params, 'sync_role' => 'opening_support' );
    }

    if ( $landmark && in_array( $landmark, $hero_types, true ) ) {
        $events[] = array( 'time' => 5.0, 'duration' => 4.2, 'visual_type' => $landmark, 'intensity' => 0.78, 'params' => $route_params, 'sync_role' => 'arrival_landmark_hero' );
        $events[] = array( 'time' => 9.4, 'duration' => 5.0, 'visual_type' => $landmark, 'intensity' => 0.86, 'params' => $route_params, 'sync_role' => 'lift_landmark_hero' );
    }

    if ( $motion ) {
        $events[] = array( 'time' => 10.2, 'duration' => 3.4, 'visual_type' => $motion, 'intensity' => 0.42, 'params' => $route_params, 'sync_role' => 'lift_motion' );
    }

    if ( $texture ) {
        $events[] = array( 'time' => 10.0, 'duration' => 4.0, 'visual_type' => $texture, 'intensity' => 0.2, 'params' => $route_params, 'sync_role' => 'lift_texture' );
    }


    if ( ! empty( $route_params ) ) {
        $events[] = array( 'time' => 0.0, 'duration' => 4.6, 'visual_type' => 'station_threshold_glow', 'intensity' => 0.42, 'params' => $route_params, 'sync_role' => 'route_start_threshold' );
        $events[] = array( 'time' => 3.8, 'duration' => 6.1, 'visual_type' => 'water_street_corridor', 'intensity' => 0.58, 'params' => $route_params, 'sync_role' => 'route_mid_corridor' );
        $events[] = array( 'time' => 8.4, 'duration' => 5.1, 'visual_type' => 'wet_cobble_axis', 'intensity' => 0.36, 'params' => $route_params, 'sync_role' => 'route_ground_motion' );
        $events[] = array( 'time' => 12.1, 'duration' => 4.9, 'visual_type' => 'steam_clock_approach', 'intensity' => 0.76, 'params' => $route_params, 'sync_role' => 'route_clock_reveal' );
        $events[] = array( 'time' => 15.1, 'duration' => 4.6, 'visual_type' => 'angled_building_split', 'intensity' => 0.54, 'params' => $route_params, 'sync_role' => 'route_end_split' );
    }

    if ( $resolve_landmark && in_array( $resolve_landmark, $hero_types, true ) ) {
        $events[] = array( 'time' => 15.2, 'duration' => 4.3, 'visual_type' => $resolve_landmark, 'intensity' => 0.74, 'params' => $route_params, 'sync_role' => 'resolve_landmark_hero' );
    } elseif ( $primary_scene && in_array( $primary_scene, $hero_types, true ) ) {
        $events[] = array( 'time' => 15.2, 'duration' => 4.3, 'visual_type' => $primary_scene, 'intensity' => 0.58, 'params' => $route_params, 'sync_role' => 'resolve_return' );
    }

    return se_validate_visual_timeline_matches_plan( $plan, $events );
}

function se_compile_audio_events_from_plan( $plan, $runtime ) {
    $runtime = max( 10, min( 30, floatval( $runtime ) ) );
    $audio_plan = is_array( $plan['audio_plan'] ?? null ) ? $plan['audio_plan'] : array();
    $primary_bed = sanitize_key( $audio_plan['primary_bed'] ?? '' );
    $secondary_bed = sanitize_key( $audio_plan['secondary_bed'] ?? '' );
    $transit_cue = sanitize_key( $audio_plan['transit_cue'] ?? '' );
    $signature_cues = array_slice( array_values( array_filter( array_map( 'sanitize_key', (array) ( $audio_plan['signature_cues'] ?? array() ) ) ) ), 0, 3 );

    $events = array();
    if ( $primary_bed ) {
        $events[] = array( 'time' => 0.0, 'duration' => min( $runtime - 0.1, 16.8 ), 'engine' => $primary_bed, 'intensity' => 0.4, 'params' => array( 'fade_out' => 2.6 ), 'sync_role' => 'primary_bed' );
    }
    if ( $secondary_bed ) {
        $events[] = array( 'time' => 4.3, 'duration' => 8.0, 'engine' => $secondary_bed, 'intensity' => 0.24, 'params' => array(), 'sync_role' => 'secondary_bed' );
    }
    if ( ! empty( $signature_cues ) ) {
        $cue_times = array( 5.2, 10.9, 17.2 );
        foreach ( $signature_cues as $i => $cue ) {
            $events[] = array( 'time' => $cue_times[ $i ], 'duration' => 2.2 + ( $i * 0.4 ), 'engine' => $cue, 'intensity' => 0.48 - ( $i * 0.06 ), 'params' => array(), 'sync_role' => ( 1 === $i ? 'lift_signature' : 'signature_cue' ) );
        }
    }
    if ( $transit_cue ) {
        $events[] = array( 'time' => 8.8, 'duration' => 2.0, 'engine' => $transit_cue, 'intensity' => 0.34, 'params' => array(), 'sync_role' => 'transit_cue' );
    }

    usort( $events, static function( $a, $b ) { return (float) $a['time'] <=> (float) $b['time']; } );
    return $events;
}

function se_validate_asmr_response( $decoded ) {
    if ( ! is_array( $decoded ) ) {
        return new WP_Error( 'asmr_invalid_json', __( 'ASMR Lab returned malformed JSON. Please regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
    }

    $required = array(
        'title',
        'runtime_seconds',
        'hook',
        'concept_summary',
        'logline',
        'story_beats',
        'style_tags',
        'audio_events',
        'visual_events',
        'sync_points',
        'end_card',
        'edit_rhythm',
        'presentation_note',
    );

    foreach ( $required as $required_key ) {
        if ( ! array_key_exists( $required_key, $decoded ) ) {
            return new WP_Error( 'asmr_shape_error', __( 'ASMR Lab response shape was unexpected. Please regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
        }
    }

    $decoded['runtime_seconds'] = max( 10, min( 30, absint( $decoded['runtime_seconds'] ) ) );

    $decoded['style_tags'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $decoded['style_tags'] ?? array() ) ) ) );

    $decoded['logline'] = sanitize_text_field( $decoded['logline'] ?? '' );

    $story_beats = is_array( $decoded['story_beats'] ?? null ) ? $decoded['story_beats'] : array();
    $decoded['story_beats'] = array_values( array_map( static function( $beat ) {
        if ( ! is_array( $beat ) ) {
            return array( 't0' => 0.0, 't1' => 0.0, 'beat' => '', 'intent' => '' );
        }
        return array(
            't0' => max( 0, floatval( $beat['t0'] ?? 0 ) ),
            't1' => max( 0, floatval( $beat['t1'] ?? 0 ) ),
            'beat' => sanitize_text_field( $beat['beat'] ?? '' ),
            'intent' => sanitize_text_field( $beat['intent'] ?? '' ),
        );
    }, $story_beats ) );

    $events = is_array( $decoded['audio_events'] ?? null ) ? $decoded['audio_events'] : array();
    $allowed_engines = se_get_asmr_allowed_engines();
    $sanitized_events = array();
    foreach ( $events as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }
        $engine = sanitize_key( $event['engine'] ?? '' );
        if ( ! in_array( $engine, $allowed_engines, true ) ) {
            continue;
        }
        $sanitized_events[] = array(
            'time' => max( 0, floatval( $event['time'] ?? 0 ) ),
            'engine' => $engine,
            'duration' => max( 0.03, min( 4, floatval( $event['duration'] ?? 0.2 ) ) ),
            'intensity' => max( 0, min( 1, floatval( $event['intensity'] ?? 0.5 ) ) ),
            'params' => is_array( $event['params'] ?? null ) ? $event['params'] : array(),
            'sync_role' => sanitize_text_field( $event['sync_role'] ?? '' ),
        );
    }

    if ( empty( $sanitized_events ) ) {
        return new WP_Error( 'asmr_sound_events_missing', __( 'Sound recipe had no usable events. Try regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
    }

    usort( $sanitized_events, static function( $a, $b ) {
        return ( $a['time'] <=> $b['time'] );
    } );
    $decoded['audio_events'] = $sanitized_events;

    $visual_allowed = se_get_asmr_visual_types();
    $visual_events = is_array( $decoded['visual_events'] ?? null ) ? $decoded['visual_events'] : array();
    $decoded['visual_events'] = array_values( array_filter( array_map( static function( $event ) use ( $visual_allowed ) {
        if ( ! is_array( $event ) ) {
            return null;
        }
        $visual_type = sanitize_key( $event['visual_type'] ?? '' );
        if ( ! in_array( $visual_type, $visual_allowed, true ) ) {
            return null;
        }
        return array(
            'time' => max( 0, floatval( $event['time'] ?? 0 ) ),
            'duration' => max( 0.03, min( 8, floatval( $event['duration'] ?? 0.5 ) ) ),
            'visual_type' => $visual_type,
            'intensity' => max( 0, min( 1, floatval( $event['intensity'] ?? 0.5 ) ) ),
            'params' => is_array( $event['params'] ?? null ) ? $event['params'] : array(),
            'sync_role' => sanitize_text_field( $event['sync_role'] ?? '' ),
        );
    }, $visual_events ) ) );
    usort( $decoded['visual_events'], static function( $a, $b ) {
        return ( $a['time'] <=> $b['time'] );
    } );

    $sync_points = is_array( $decoded['sync_points'] ?? null ) ? $decoded['sync_points'] : array();
    $decoded['sync_points'] = array_values( array_filter( array_map( static function( $point ) {
        if ( ! is_array( $point ) ) {
            return null;
        }
        return array(
            'time' => max( 0, floatval( $point['time'] ?? 0 ) ),
            'cue' => sanitize_text_field( $point['cue'] ?? '' ),
            'importance' => sanitize_text_field( $point['importance'] ?? '' ),
        );
    }, $sync_points ) ) );
    usort( $decoded['sync_points'], static function( $a, $b ) {
        return ( $a['time'] <=> $b['time'] );
    } );

    $end_card = is_array( $decoded['end_card'] ?? null ) ? $decoded['end_card'] : array();
    $decoded['end_card'] = array(
        'use_end_card' => ! empty( $end_card['use_end_card'] ),
        'text' => sanitize_textarea_field( $end_card['text'] ?? '' ),
        'reveal_style' => sanitize_text_field( $end_card['reveal_style'] ?? '' ),
    );

    $edit_rhythm = is_array( $decoded['edit_rhythm'] ?? null ) ? $decoded['edit_rhythm'] : array();
    $decoded['edit_rhythm'] = array(
        'pacing_note' => sanitize_text_field( $edit_rhythm['pacing_note'] ?? '' ),
        'silence_strategy' => sanitize_text_field( $edit_rhythm['silence_strategy'] ?? '' ),
        'release_strategy' => sanitize_text_field( $edit_rhythm['release_strategy'] ?? '' ),
    );

    $decoded['presentation_note'] = sanitize_text_field( $decoded['presentation_note'] ?? '' );

    return $decoded;
}

function se_handle_asmr_generate( WP_REST_Request $req ) {
    $params = $req->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = $req->get_params();
    }

    $allowed_locations = array( 'gastown', 'granville', 'north_shore' );
    $allowed_weather = array( 'snow', 'rain', 'fog', 'clear_cold' );
    $allowed_audio = se_get_asmr_audio_layers_allowed();
    $allowed_visual = se_get_asmr_visual_layers_allowed();
    $legacy_audio_map = se_get_asmr_legacy_audio_map();

    $raw_legacy_foley = array_map( 'sanitize_key', (array) ( $params['foley'] ?? array() ) );
    $legacy_audio_layers = array();
    foreach ( $raw_legacy_foley as $token ) {
        $legacy_audio_layers[] = $legacy_audio_map[ $token ] ?? $token;
    }

    $link_av = array_key_exists( 'link_av', $params ) ? filter_var( $params['link_av'], FILTER_VALIDATE_BOOLEAN ) : false;
    $audio_layers = array_values( array_intersect( $allowed_audio, array_map( 'sanitize_key', (array) ( $params['audio_layers'] ?? array() ) ) ) );
    if ( empty( $audio_layers ) && ! empty( $legacy_audio_layers ) ) {
        $audio_layers = array_values( array_intersect( $allowed_audio, $legacy_audio_layers ) );
    }

    $visual_layers = array_values( array_intersect( $allowed_visual, array_map( 'sanitize_key', (array) ( $params['visual_layers'] ?? array() ) ) ) );
    if ( empty( $visual_layers ) && ! empty( $raw_legacy_foley ) && $link_av ) {
        $visual_layers = array_values( array_intersect( $allowed_visual, se_get_asmr_mapped_visual_layers_from_audio( $legacy_audio_layers ) ) );
    }

    $vancouver_world_context = array();
    if ( is_array( $params['vancouver_world_context'] ?? null ) ) {
        $raw_context = $params['vancouver_world_context'];

        if ( is_array( $raw_context['scenes'] ?? null ) ) {
            $scenes = array();
            foreach ( $raw_context['scenes'] as $raw_scene ) {
                if ( ! is_array( $raw_scene ) ) {
                    continue;
                }

                $scene = array();

                if ( isset( $raw_scene['scene'] ) ) {
                    $scene_label = sanitize_text_field( $raw_scene['scene'] );
                    if ( '' !== $scene_label ) {
                        $scene['scene'] = $scene_label;
                    }
                }

                if ( is_array( $raw_scene['anchors'] ?? null ) ) {
                    $anchors = array();
                    foreach ( array_slice( $raw_scene['anchors'], 0, 3 ) as $raw_anchor ) {
                        if ( ! is_array( $raw_anchor ) ) {
                            continue;
                        }

                        $anchor = array();
                        if ( isset( $raw_anchor['label'] ) ) {
                            $label = sanitize_text_field( $raw_anchor['label'] );
                            if ( '' !== $label ) {
                                $anchor['label'] = $label;
                            }
                        }
                        if ( isset( $raw_anchor['kind'] ) ) {
                            $kind = sanitize_text_field( $raw_anchor['kind'] );
                            if ( '' !== $kind ) {
                                $anchor['kind'] = $kind;
                            }
                        }

                        if ( ! empty( $anchor ) ) {
                            $anchors[] = $anchor;
                        }
                    }

                    if ( ! empty( $anchors ) ) {
                        $scene['anchors'] = $anchors;
                    }
                }

                if ( is_array( $raw_scene['geometry_types'] ?? null ) ) {
                    $geometry_types = array();
                    foreach ( array_slice( $raw_scene['geometry_types'], 0, 3 ) as $raw_geometry ) {
                        $geometry = sanitize_text_field( (string) $raw_geometry );
                        if ( '' !== $geometry ) {
                            $geometry_types[] = $geometry;
                        }
                    }
                    if ( ! empty( $geometry_types ) ) {
                        $scene['geometry_types'] = $geometry_types;
                    }
                }

                if ( is_array( $raw_scene['hints'] ?? null ) ) {
                    $hints = array();
                    foreach ( array_slice( $raw_scene['hints'], 0, 3 ) as $raw_hint ) {
                        $hint = sanitize_text_field( (string) $raw_hint );
                        if ( '' !== $hint ) {
                            $hints[] = $hint;
                        }
                    }
                    if ( ! empty( $hints ) ) {
                        $scene['hints'] = $hints;
                    }
                }

                if ( ! empty( $scene ) ) {
                    $scenes[] = $scene;
                }
            }

            if ( ! empty( $scenes ) ) {
                $vancouver_world_context['scenes'] = $scenes;
            }
        }

        if ( isset( $raw_context['source'] ) ) {
            $source = sanitize_text_field( $raw_context['source'] );
            if ( '' !== $source ) {
                $vancouver_world_context['source'] = $source;
            }
        }

        if ( isset( $raw_context['note'] ) ) {
            $note = sanitize_text_field( $raw_context['note'] );
            if ( '' !== $note ) {
                $vancouver_world_context['note'] = $note;
            }
        }
    }


    $gastown_route_context = array();
    if ( is_array( $params['gastown_route_context'] ?? null ) ) {
        $raw_route = $params['gastown_route_context'];
        $route_id = sanitize_key( $raw_route['route_id'] ?? '' );
        if ( '' !== $route_id ) {
            $gastown_route_context['route_id'] = $route_id;
        }

        if ( isset( $raw_route['district'] ) ) {
            $district = sanitize_key( $raw_route['district'] );
            if ( '' !== $district ) {
                $gastown_route_context['district'] = $district;
            }
        }

        if ( is_array( $raw_route['continuity'] ?? null ) ) {
            $gastown_route_context['continuity'] = array(
                'single_district' => ! empty( $raw_route['continuity']['single_district'] ),
                'continuous_path' => ! empty( $raw_route['continuity']['continuous_path'] ),
                'reveal_mode' => sanitize_key( $raw_route['continuity']['reveal_mode'] ?? '' ),
                'disallow_unrelated_landmarks' => ! empty( $raw_route['continuity']['disallow_unrelated_landmarks'] ),
            );
        }

        if ( is_array( $raw_route['camera_grammar'] ?? null ) ) {
            $gastown_route_context['camera_grammar'] = array(
                'pov' => sanitize_key( $raw_route['camera_grammar']['pov'] ?? '' ),
                'movement' => sanitize_key( $raw_route['camera_grammar']['movement'] ?? '' ),
                'framing' => sanitize_key( $raw_route['camera_grammar']['framing'] ?? '' ),
                'corridor_bias' => sanitize_key( $raw_route['camera_grammar']['corridor_bias'] ?? '' ),
                'asymmetry_bias' => sanitize_key( $raw_route['camera_grammar']['asymmetry_bias'] ?? '' ),
            );
        }

        foreach ( array( 'waypoint_order', 'landmark_order' ) as $list_key ) {
            if ( is_array( $raw_route[ $list_key ] ?? null ) ) {
                $gastown_route_context[ $list_key ] = array_values( array_filter( array_map( 'sanitize_key', array_slice( $raw_route[ $list_key ], 0, 8 ) ) ) );
            }
        }
    }

    $payload = array(
        'concept' => sanitize_text_field( $params['concept'] ?? '' ),
        'object' => sanitize_text_field( $params['object'] ?? '' ),
        'setting' => sanitize_text_field( $params['setting'] ?? '' ),
        'mood' => sanitize_text_field( $params['mood'] ?? '' ),
        'duration' => max( 10, min( 30, absint( $params['duration'] ?? 20 ) ) ),
        'creative_goal' => sanitize_textarea_field( $params['creative_goal'] ?? '' ),
        'location' => sanitize_key( $params['location'] ?? '' ),
        'weather' => sanitize_key( $params['weather'] ?? '' ),
        'link_av' => $link_av,
        'audio_layers' => $audio_layers,
        'visual_layers' => $visual_layers,
        'foley' => $raw_legacy_foley,
        'sound_only' => ! empty( $params['sound_only'] ),
        'story_mode' => true,
        'use_end_card' => array_key_exists( 'use_end_card', $params ) ? filter_var( $params['use_end_card'], FILTER_VALIDATE_BOOLEAN ) : false,
    );

    $has_freeform = ! empty( $payload['concept'] ) || ! empty( $payload['object'] ) || ! empty( $payload['setting'] ) || ! empty( $payload['mood'] ) || ! empty( $payload['creative_goal'] );
    $has_legacy_location_weather = in_array( $payload['location'], $allowed_locations, true ) && in_array( $payload['weather'], $allowed_weather, true );

    if ( ! $has_freeform ) {
        $payload = se_get_asmr_vancouver_derived_payload( $payload );
        if ( $has_legacy_location_weather ) {
            $payload['style_tags_legacy'] = array( $payload['location'], $payload['weather'] );
        }
    }

    $payload['motif_brief'] = se_get_asmr_motif_brief( $payload );
    if ( ! empty( $vancouver_world_context ) ) {
        $payload['vancouver_world_context'] = $vancouver_world_context;
    }
    if ( ! empty( $gastown_route_context ) ) {
        $payload['gastown_route_context'] = $gastown_route_context;
    }

    $allowed_engines = implode( ', ', se_get_asmr_allowed_engines() );
    $system_prompt = 'You are ASMR Lab. Return strict JSON only. Compose a disciplined 20-second cinematic beat plan first, not an event-dense timeline. '
        . 'Use top-level keys: title, runtime_seconds, hook, concept_summary, logline, story_beats, style_tags, generation_plan, audio_events, visual_events, sync_points, end_card, edit_rhythm, presentation_note. '
        . 'generation_plan must include title, logline, story_beats (Opening, Arrival, Lift, Resolve), visual_plan(primary_scene, atmosphere, landmark, motion, texture, overlay_family), audio_plan(primary_bed, secondary_bed, signature_cues 0-3, transit_cue). '
        . 'Keep visual_plan and audio_plan sparse with null or one value per slot. signature_cues max 3. '
        . 'You may include optional audio_events and visual_events, but keep concise because deterministic compiler will build final timeline. '
        . 'Only use engine names: ' . $allowed_engines . '. '
        . 'Allowed visual_type values: ' . implode( ', ', se_get_asmr_visual_types() ) . '. '
        . 'Support-only visuals must not be hero reveals: ' . implode( ', ', se_get_asmr_visual_support_types() ) . '. '
        . 'Hero visuals are: ' . implode( ', ', se_get_asmr_visual_hero_types() ) . '. '
        . 'story_beats must have 4 entries with t0,t1,beat,intent and timings for 0-4,4-9,9-15,15-20 when runtime is 20. '
        . 'Default to end_card.use_end_card=false and keep text empty unless the user explicitly requests an end card. '
        . 'When vancouver_world_context is present, use it as soft grounding for scene specificity, texture, and landmark plausibility, while still prioritizing selected motifs and cinematic readability. '
        . 'Use it to make locations feel geographically anchored, avoid excessive verbatim reuse of raw labels, and never let it override motif constraints or story structure. '
        . 'When gastown_route_context is present, treat the piece as a first-person unseen camera traversal down one connected corridor route. '
        . 'Prioritize route scaffolding first and landmark second: start at the waterfront threshold, move through the brick/lamp/cobblestone corridor, reveal steam clock during approach, then resolve at the street split angled-building node. '
        . 'Keep one district only, one continuous path, one reveal at a time, and do not jump to unrelated Vancouver landmarks. '
        . 'Use camera grammar tokens from context (pov first_person_unseen, movement forward_walk/slow_glide, framing off_center_landmark, corridor_bias strong, asymmetry_bias medium/high). '
        . 'Favor asymmetrical vanishing-point corridor framing with facades cropped into left/right edges, and avoid dead-center hero placement unless explicitly requested. '
        . 'Resolve must simplify and avoid introducing a new landmark. Keep one dominant reveal at a time.';

    $response = se_openai_chat(
        array(
            array( 'role' => 'system', 'content' => $system_prompt ),
            array( 'role' => 'user', 'content' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
        ),
        array( 'model' => 'gpt-4o', 'temperature' => 0.7, 'max_tokens' => 1600 ),
        array( 'timeout' => 40 )
    );

    if ( is_wp_error( $response ) ) {
        $status = ( 'no_key' === $response->get_error_code() ) ? 503 : 500;
        return new WP_Error( 'asmr_openai_error', $response->get_error_message(), array( 'status' => $status ) );
    }

    $raw = trim( $response['choices'][0]['message']['content'] ?? '' );
    $json = se_extract_json_object( $raw );
    $decoded = json_decode( $json, true );
    if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
        return new WP_Error( 'asmr_decode_error', __( 'The lab output was malformed. Please regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
    }

    $runtime = max( 10, min( 30, absint( $decoded['runtime_seconds'] ?? $payload['duration'] ) ) );
    $plan = is_array( $decoded['generation_plan'] ?? null ) ? $decoded['generation_plan'] : array();
    $plan = se_restrict_generation_plan_to_selected_motifs( $plan, $audio_layers, $visual_layers );

    $decoded['title'] = sanitize_text_field( $decoded['title'] ?? 'ASMR Lab Sequence' );
    $decoded['runtime_seconds'] = $runtime;
    $decoded['hook'] = sanitize_text_field( $decoded['hook'] ?? 'Cinematic sensory short.' );
    $decoded['concept_summary'] = sanitize_text_field( $decoded['concept_summary'] ?? 'Disciplined beat-driven structure.' );
    $decoded['logline'] = sanitize_text_field( $decoded['logline'] ?? ( $plan['logline'] ?? '' ) );
    $decoded['story_beats'] = is_array( $decoded['story_beats'] ?? null ) && count( $decoded['story_beats'] ) === 4 ? $decoded['story_beats'] : se_get_asmr_default_story_beats( $runtime );
    $decoded['style_tags'] = se_build_asmr_style_tags( array_merge( $decoded, array( 'generation_plan' => $plan ) ), array( 'retro_arcade_crt', 'beat_compiled' ) );
    $decoded['audio_events'] = se_compile_audio_events_from_plan( $plan, $runtime );
    $decoded['visual_events'] = se_compile_visual_events_from_plan( $plan, $runtime );
    $decoded['sync_points'] = array(
        array( 'time' => 0.2, 'cue' => 'Opening anchor', 'importance' => 'high' ),
        array( 'time' => 5.2, 'cue' => 'Arrival handoff', 'importance' => 'medium' ),
        array( 'time' => 10.2, 'cue' => 'Lift reveal', 'importance' => 'high' ),
        array( 'time' => 17.2, 'cue' => 'Resolve cue', 'importance' => 'medium' ),
    );
    $decoded['end_card'] = is_array( $decoded['end_card'] ?? null ) ? $decoded['end_card'] : array();
    if ( empty( $payload['use_end_card'] ) ) {
        $decoded['end_card'] = array( 'use_end_card' => false, 'text' => '', 'reveal_style' => '' );
    }
    $decoded['edit_rhythm'] = is_array( $decoded['edit_rhythm'] ?? null ) ? $decoded['edit_rhythm'] : array( 'pacing_note' => 'Beat-plan compiled timeline with one reveal at a time.', 'silence_strategy' => 'Leave breathing space between reveals.', 'release_strategy' => 'Resolve simplifies to core bed and subject.' );
    $decoded['presentation_note'] = sanitize_text_field( $decoded['presentation_note'] ?? 'Compiled from generation_plan for controlled cinematic structure.' );
    $decoded['generation_plan'] = $plan;

    $validated = se_validate_asmr_response( $decoded );
    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    $validated['generation_plan'] = $plan;
    $validated = se_apply_asmr_semantic_cues( $validated, $payload );
    if ( empty( $payload['use_end_card'] ) ) {
        $validated['end_card'] = array( 'use_end_card' => false, 'text' => '', 'reveal_style' => '' );
    }

    return rest_ensure_response( $validated );
}


// =========================================
// 9. SECURITY HARDENING
// =========================================
// 9a) Disable XML-RPC completely
add_filter('xmlrpc_enabled', '__return_false');

// 9b) Hide REST API user endpoints
add_filter('rest_endpoints', function($endpoints) {
    unset($endpoints['/wp/v2/users']);
    unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    return $endpoints;
});

// 9c) Block author archives
add_action('template_redirect', function() {
    if ( is_author() ) {
        wp_redirect(home_url(), 301);
        exit;
    }
});

// Advertise Lousy Outages RSS feed for readers (auto-discovery)
add_action('wp_head', function () {
    if (function_exists('home_url')) {
        $href = esc_url( home_url('/outages/feed') );
        echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"Lousy Outages Alerts\" href=\"{$href}\" />\n";
    }
});

// =========================================
// 10. CRAWLABILITY / INDEXING CONTROLS
// =========================================
function se_get_utility_page_templates(): array {
    return array(
        'page-subscribe-thanks.php',
    );
}

function se_get_utility_page_ids(): array {
    $ids = array();
    foreach ( se_get_utility_page_templates() as $template ) {
        $pages = get_pages(
            array(
                'meta_key'    => '_wp_page_template',
                'meta_value'  => $template,
                'post_status' => 'publish',
                'fields'      => 'ids',
                'number'      => 100,
            )
        );

        if ( is_array( $pages ) ) {
            $ids = array_merge( $ids, $pages );
        }
    }

    return array_values( array_unique( array_map( 'absint', $ids ) ) );
}

add_filter(
    'wp_robots',
    function ( array $robots ): array {
        if ( is_page_template( se_get_utility_page_templates() ) ) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            unset( $robots['index'] );
            unset( $robots['follow'] );
        }

        return $robots;
    }
);

add_filter(
    'wp_sitemaps_posts_query_args',
    function ( array $args, string $post_type ): array {
        if ( 'page' !== $post_type ) {
            return $args;
        }

        $excluded_ids = se_get_utility_page_ids();
        if ( ! empty( $excluded_ids ) ) {
            $args['post__not_in'] = isset( $args['post__not_in'] )
                ? array_values( array_unique( array_merge( (array) $args['post__not_in'], $excluded_ids ) ) )
                : $excluded_ids;
        }

        return $args;
    },
    10,
    2
);
