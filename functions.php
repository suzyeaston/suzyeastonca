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
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_asmr_lab_assets' );

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
    );
}

function se_get_asmr_visual_types() {
    return array(
        'scanline_field', 'pixel_grid_pulse', 'wireframe_horizon', 'radial_bloom', 'particle_trail',
        'glitch_flash', 'waveform_ring', 'macro_texture_drift', 'signal_bars', 'text_reveal',
        'volumetric_fog', 'glass_refraction', 'halo_glyphs', 'cathedral_beam', 'monolith_silhouette',
        'starfield_drift', 'orbiting_shards', 'pulse_orb', 'energy_column', 'refraction_ripple',
        'chromatic_veil', 'terminal_runes', 'snow_drift', 'amber_halo', 'wet_reflection_shimmer',
        'brick_shadow_drift', 'steam_plume_column', 'clock_face_reveal', 'harbor_mist',
        'neon_wet_reflections', 'winter_particulate_depth',
        'gastown_clock_silhouette', 'cobblestone_perspective', 'brick_wall_parallax', 'streetlamp_halo_row',
        'granville_neon_marquee', 'neon_sign_flicker', 'traffic_light_glow',
        'skytrain_track', 'skytrain_pass_visual',
        'northshore_mountain_ridge', 'mountain_mist_layers',
        'rain_streaks', 'puddle_reflections',
    );
}

function se_get_asmr_semantic_cues( $payload ) {
    $source = strtolower( implode( ' ', array(
        (string) ( $payload['concept'] ?? '' ),
        (string) ( $payload['object'] ?? '' ),
        (string) ( $payload['setting'] ?? '' ),
        (string) ( $payload['mood'] ?? '' ),
        (string) ( $payload['creative_goal'] ?? '' ),
    ) ) );

    $cue_map = array(
        'gastown' => array( 'steam_clock', 'cobblestone', 'brick', 'amber', 'urban_night' ),
        'vancouver' => array( 'harbor', 'fog', 'coastal', 'urban_night' ),
        'steam clock' => array( 'steam_clock', 'metal', 'bell' ),
        'snow' => array( 'snow', 'winter_hush', 'cold_air' ),
        'rain' => array( 'rain', 'wet_reflection' ),
        'harbor fog' => array( 'harbor', 'fog' ),
        'amber' => array( 'amber', 'streetlamp' ),
        'streetlamp' => array( 'streetlamp', 'amber' ),
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

    if ( in_array( 'gastown_clock_focus', $cues, true ) || in_array( 'steam_clock', $cues, true ) ) {
        $audio[] = array( 'time' => 0.55, 'duration' => 1.4, 'engine' => 'steam_clock_burst', 'intensity' => 0.7, 'params' => array(), 'sync_role' => 'steam_clock_identity' );
        $audio[] = array( 'time' => $runtime * 0.68, 'duration' => 1.1, 'engine' => 'distant_bell_toll', 'intensity' => 0.58, 'params' => array(), 'sync_role' => 'clock_chime_reveal' );
        $visual[] = array( 'time' => 0.5, 'duration' => 2.1, 'visual_type' => 'clock_face_reveal', 'intensity' => 0.72, 'params' => array(), 'sync_role' => 'clock_face_entry' );
        $visual[] = array( 'time' => 0.3, 'duration' => 2.2, 'visual_type' => 'steam_plume_column', 'intensity' => 0.64, 'params' => array(), 'sync_role' => 'steam_column' );
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
        $visual[] = array( 'time' => $runtime * 0.12, 'duration' => min( 6.2, $runtime * 0.38 ), 'visual_type' => 'wet_reflection_shimmer', 'intensity' => 0.64, 'params' => array(), 'sync_role' => 'street_reflection' );
        $visual[] = array( 'time' => $runtime * 0.2, 'duration' => min( 5.5, $runtime * 0.32 ), 'visual_type' => 'neon_wet_reflections', 'intensity' => 0.55, 'params' => array(), 'sync_role' => 'neon_reflection' );
    }

    if ( in_array( 'brick', $cues, true ) || in_array( 'cobblestone', $cues, true ) ) {
        $visual[] = array( 'time' => 0.4, 'duration' => min( 6.5, $runtime * 0.45 ), 'visual_type' => 'brick_shadow_drift', 'intensity' => 0.58, 'params' => array(), 'sync_role' => 'material_grounding' );
        $audio[] = array( 'time' => 0.9, 'duration' => 1.2, 'engine' => 'metal_resonance', 'intensity' => 0.42, 'params' => array(), 'sync_role' => 'urban_material_ping' );
    }

    if ( in_array( 'amber', $cues, true ) || in_array( 'streetlamp', $cues, true ) ) {
        $visual[] = array( 'time' => 0.25, 'duration' => min( 8, $runtime * 0.52 ), 'visual_type' => 'amber_halo', 'intensity' => 0.65, 'params' => array(), 'sync_role' => 'streetlamp_glow' );
    }

    if ( in_array( 'urban_night', $cues, true ) ) {
        $audio[] = array( 'time' => 0.05, 'duration' => min( 8, $runtime * 0.6 ), 'engine' => 'city_electrical_hum', 'intensity' => 0.35, 'params' => array(), 'sync_role' => 'city_grid_bed' );
    }

    if ( in_array( 'pixel_art', $cues, true ) || in_array( 'dither', $cues, true ) || in_array( 'arcade', $cues, true ) ) {
        $visual[] = array( 'time' => 0.08, 'duration' => min( 3.8, $runtime * 0.24 ), 'visual_type' => 'pixel_grid_pulse', 'intensity' => 0.72, 'params' => array(), 'sync_role' => 'pixel_language_early' );
        $visual[] = array( 'time' => 0.16, 'duration' => min( 4.2, $runtime * 0.28 ), 'visual_type' => 'scanline_field', 'intensity' => 0.62, 'params' => array(), 'sync_role' => 'pixel_scan_early' );
        $visual[] = array( 'time' => 0.3, 'duration' => min( 3.4, $runtime * 0.22 ), 'visual_type' => 'signal_bars', 'intensity' => 0.56, 'params' => array(), 'sync_role' => 'arcade_signal_early' );
    }

    $source = strtolower( implode( ' ', array(
        (string) ( $payload['concept'] ?? '' ),
        (string) ( $payload['object'] ?? '' ),
        (string) ( $payload['setting'] ?? '' ),
        (string) ( $payload['mood'] ?? '' ),
        (string) ( $payload['creative_goal'] ?? '' ),
    ) ) );
    if ( false !== strpos( $source, 'event card' ) || false !== strpos( $source, 'screening' ) || false !== strpos( $source, 'film club' ) || false !== strpos( $source, 'deadline' ) ) {
        $end_card = is_array( $decoded['end_card'] ?? null ) ? $decoded['end_card'] : array();
        $end_card['reveal_style'] = 'event_card';
        $decoded['end_card'] = $end_card;
    }

    $sync[] = array( 'time' => 0.55, 'cue' => 'semantic place identity enters', 'importance' => 'high' );
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


function se_get_asmr_vancouver_derived_payload( $payload ) {
    $location = sanitize_key( $payload['location'] ?? '' );
    $weather = sanitize_key( $payload['weather'] ?? '' );
    $foley = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['foley'] ?? array() ) ) ) );

    $location_meta = array(
        'gastown' => array(
            'label' => 'Gastown Steam Clock',
            'object' => 'steam clock',
            'setting' => 'midnight Gastown lane with wet cobblestones, brick facades, cast iron rails, and amber lamps',
            'visual' => 'gastown_clock_silhouette',
        ),
        'granville' => array(
            'label' => 'Granville Street',
            'object' => 'neon marquee and rain-slick street',
            'setting' => 'midnight Granville strip with neon marquees, reflective asphalt, bus shelters, and crosswalk paint',
            'visual' => 'granville_neon_marquee',
        ),
        'north_shore' => array(
            'label' => 'North Shore Mountains',
            'object' => 'mountain ridge and harbor air',
            'setting' => 'midnight harbor edge facing North Shore ridges with cedar silhouettes, cold air, and layered mist',
            'visual' => 'northshore_mountain_ridge',
        ),
    );
    $weather_moods = array(
        'snow' => 'hushed and holy',
        'rain' => 'neon melancholy',
        'fog' => 'dreamlike and liminal',
        'clear_cold' => 'crisp and electric',
    );

    if ( ! isset( $location_meta[ $location ] ) || ! isset( $weather_moods[ $weather ] ) ) {
        return $payload;
    }

    $foley_text = empty( $foley ) ? 'soft civic ambience' : implode( ', ', $foley );
    $meta = $location_meta[ $location ];

    $payload['concept'] = sprintf( 'A cinematic Vancouver midnight vignette in %s, where urban signals feel sacred.', $meta['label'] );
    $payload['object'] = $meta['object'];
    $payload['setting'] = $meta['setting'];
    $payload['mood'] = $weather_moods[ $weather ];
    $payload['creative_goal'] = sprintf( 'Compose a sacred urban signal arc with %s cues anchored in %s.', $foley_text, $meta['label'] );
    return $payload;
}

function se_inject_asmr_vancouver_anchors( $decoded, $payload ) {
    $location = sanitize_key( $payload['location'] ?? '' );
    $weather = sanitize_key( $payload['weather'] ?? '' );
    $foley = array_values( array_filter( array_map( 'sanitize_key', (array) ( $payload['foley'] ?? array() ) ) ) );
    if ( empty( $location ) && empty( $weather ) && empty( $foley ) ) {
        return $decoded;
    }

    $runtime = max( 10, min( 30, floatval( $decoded['runtime_seconds'] ?? 20 ) ) );
    $audio = is_array( $decoded['audio_events'] ?? null ) ? $decoded['audio_events'] : array();
    $visual = is_array( $decoded['visual_events'] ?? null ) ? $decoded['visual_events'] : array();
    $sync = is_array( $decoded['sync_points'] ?? null ) ? $decoded['sync_points'] : array();

    $audio[] = array( 'time' => 0.08, 'duration' => 2.2, 'engine' => 'city_electrical_hum', 'intensity' => 0.28, 'params' => array(), 'sync_role' => 'vancouver_bed' );

    if ( 'gastown' === $location ) {
        $audio[] = array( 'time' => 0.52, 'duration' => 1.2, 'engine' => 'steam_clock_burst', 'intensity' => 0.72, 'params' => array(), 'sync_role' => 'gastown_anchor' );
        $visual[] = array( 'time' => 0.22, 'duration' => 2.2, 'visual_type' => 'gastown_clock_silhouette', 'intensity' => 0.78, 'params' => array(), 'sync_role' => 'gastown_identity' );
        $visual[] = array( 'time' => 0.16, 'duration' => 2.4, 'visual_type' => 'streetlamp_halo_row', 'intensity' => 0.6, 'params' => array(), 'sync_role' => 'streetlamp_glow' );
        $visual[] = array( 'time' => 0.28, 'duration' => 2.8, 'visual_type' => 'cobblestone_perspective', 'intensity' => 0.62, 'params' => array(), 'sync_role' => 'street_surface' );
    } elseif ( 'granville' === $location ) {
        $visual[] = array( 'time' => 0.22, 'duration' => 2.4, 'visual_type' => 'granville_neon_marquee', 'intensity' => 0.76, 'params' => array(), 'sync_role' => 'granville_identity' );
        $visual[] = array( 'time' => 0.36, 'duration' => 2.1, 'visual_type' => 'neon_sign_flicker', 'intensity' => 0.68, 'params' => array(), 'sync_role' => 'neon_flicker' );
        $visual[] = array( 'time' => 0.42, 'duration' => 2.2, 'visual_type' => 'traffic_light_glow', 'intensity' => 0.58, 'params' => array(), 'sync_role' => 'traffic_glow' );
    } elseif ( 'north_shore' === $location ) {
        $visual[] = array( 'time' => 0.24, 'duration' => 2.8, 'visual_type' => 'northshore_mountain_ridge', 'intensity' => 0.78, 'params' => array(), 'sync_role' => 'mountain_identity' );
        $visual[] = array( 'time' => 0.18, 'duration' => 3.0, 'visual_type' => 'mountain_mist_layers', 'intensity' => 0.62, 'params' => array(), 'sync_role' => 'mist_layers' );
    }

    if ( in_array( 'footsteps', $foley, true ) ) {
        $engine = ( 'snow' === $weather ) ? 'footsteps_snow' : 'footsteps_wet';
        $step_count = 3;
        for ( $i = 0; $i < $step_count; $i++ ) {
            $audio[] = array( 'time' => 0.7 + ( $i * ( $runtime * 0.14 ) ), 'duration' => 0.18, 'engine' => $engine, 'intensity' => 0.55, 'params' => array(), 'sync_role' => 'footstep_anchor' );
        }
    }

    if ( 'rain' === $weather || in_array( 'rain', $foley, true ) ) {
        $audio[] = array( 'time' => 0.04, 'duration' => min( 6, $runtime * 0.45 ), 'engine' => 'rain_close', 'intensity' => 0.54, 'params' => array(), 'sync_role' => 'rain_bed' );
        $visual[] = array( 'time' => 0.08, 'duration' => min( 6, $runtime * 0.44 ), 'visual_type' => 'rain_streaks', 'intensity' => 0.68, 'params' => array(), 'sync_role' => 'rain_streaks' );
        $visual[] = array( 'time' => 0.12, 'duration' => min( 6, $runtime * 0.4 ), 'visual_type' => 'puddle_reflections', 'intensity' => 0.62, 'params' => array(), 'sync_role' => 'puddle_reflections' );
    }

    if ( in_array( 'skytrain', $foley, true ) ) {
        $mid = $runtime * 0.52;
        $audio[] = array( 'time' => $mid, 'duration' => 2.2, 'engine' => 'skytrain_pass', 'intensity' => 0.7, 'params' => array(), 'sync_role' => 'skytrain_anchor' );
        $visual[] = array( 'time' => $mid - 0.2, 'duration' => 2.4, 'visual_type' => 'skytrain_pass_visual', 'intensity' => 0.7, 'params' => array(), 'sync_role' => 'skytrain_visual' );
        $visual[] = array( 'time' => $mid - 0.25, 'duration' => 2.5, 'visual_type' => 'skytrain_track', 'intensity' => 0.58, 'params' => array(), 'sync_role' => 'skytrain_track' );
    }

    if ( in_array( 'steam_clock', $foley, true ) ) {
        $audio[] = array( 'time' => 0.5, 'duration' => 1.1, 'engine' => 'steam_clock_burst', 'intensity' => 0.7, 'params' => array(), 'sync_role' => 'steam_clock_toggle' );
        $visual[] = array( 'time' => 0.28, 'duration' => 2.0, 'visual_type' => 'gastown_clock_silhouette', 'intensity' => 0.68, 'params' => array(), 'sync_role' => 'steam_clock_visual' );
    }

    if ( in_array( 'chatter', $foley, true ) ) {
        $audio[] = array( 'time' => 1.1, 'duration' => 2.2, 'engine' => 'crowd_murmur', 'intensity' => 0.42, 'params' => array(), 'sync_role' => 'crowd_anchor' );
    }
    if ( in_array( 'laughter', $foley, true ) ) {
        $audio[] = array( 'time' => $runtime * 0.4, 'duration' => 0.38, 'engine' => 'laughter_burst', 'intensity' => 0.58, 'params' => array(), 'sync_role' => 'laughter_anchor' );
    }
    if ( in_array( 'bus', $foley, true ) ) {
        $audio[] = array( 'time' => $runtime * 0.32, 'duration' => 1.8, 'engine' => 'bus_idle', 'intensity' => 0.48, 'params' => array(), 'sync_role' => 'bus_anchor' );
    }
    if ( in_array( 'car_horn', $foley, true ) ) {
        $audio[] = array( 'time' => $runtime * 0.62, 'duration' => 0.22, 'engine' => 'car_horn_short', 'intensity' => 0.6, 'params' => array(), 'sync_role' => 'horn_anchor' );
    }

    $decoded['style_tags'] = array_values( array_unique( array_merge( (array) ( $decoded['style_tags'] ?? array() ), array( $location, $weather, 'vancouver_mode' ), $foley ) ) );
    $decoded['audio_events'] = $audio;
    $decoded['visual_events'] = $visual;
    $decoded['sync_points'] = $sync;
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

function se_validate_asmr_response( $decoded ) {
    if ( ! is_array( $decoded ) ) {
        return new WP_Error( 'asmr_invalid_json', __( 'ASMR Lab returned malformed JSON. Please regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
    }

    $required = array(
        'title',
        'runtime_seconds',
        'hook',
        'concept_summary',
        'style_tags',
        'audio_events',
        'visual_events',
        'sync_points',
        'end_card',
        'edit_rhythm',
        'presentation_note',
    );

    $keys = array_keys( $decoded );
    sort( $keys );
    $required_sorted = $required;
    sort( $required_sorted );
    if ( $keys !== $required_sorted ) {
        return new WP_Error( 'asmr_shape_error', __( 'ASMR Lab response shape was unexpected. Please regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
    }

    $decoded['runtime_seconds'] = max( 10, min( 30, absint( $decoded['runtime_seconds'] ) ) );

    $decoded['style_tags'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $decoded['style_tags'] ?? array() ) ) ) );

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
    $allowed_foley = array( 'footsteps', 'rain', 'chatter', 'laughter', 'skytrain', 'bus', 'car_horn', 'steam_clock' );

    $payload = array(
        'concept' => sanitize_text_field( $params['concept'] ?? '' ),
        'object' => sanitize_text_field( $params['object'] ?? '' ),
        'setting' => sanitize_text_field( $params['setting'] ?? '' ),
        'mood' => sanitize_text_field( $params['mood'] ?? '' ),
        'duration' => max( 10, min( 30, absint( $params['duration'] ?? 20 ) ) ),
        'voice_style' => sanitize_text_field( $params['voice_style'] ?? '' ),
        'weirdness' => max( 1, min( 10, absint( $params['weirdness'] ?? 6 ) ) ),
        'creative_goal' => sanitize_textarea_field( $params['creative_goal'] ?? '' ),
        'location' => sanitize_key( $params['location'] ?? '' ),
        'weather' => sanitize_key( $params['weather'] ?? '' ),
        'foley' => array_values( array_intersect( $allowed_foley, array_map( 'sanitize_key', (array) ( $params['foley'] ?? array() ) ) ) ),
        'sound_only' => ! empty( $params['sound_only'] ),
    );

    $has_freeform = ! empty( $payload['concept'] ) && ! empty( $payload['object'] ) && ! empty( $payload['setting'] ) && ! empty( $payload['mood'] );
    $has_vancouver = in_array( $payload['location'], $allowed_locations, true ) && in_array( $payload['weather'], $allowed_weather, true );

    if ( ! $has_freeform && ! $has_vancouver ) {
        return new WP_Error( 'asmr_missing_fields', __( 'Provide freeform concept/object/setting/mood or use Vancouver Mode location + weather.', 'suzys-music-theme' ), array( 'status' => 400 ) );
    }

    if ( $has_vancouver && ! $has_freeform ) {
        $payload = se_get_asmr_vancouver_derived_payload( $payload );
    }

    $allowed_engines = implode( ', ', se_get_asmr_allowed_engines() );
    $system_prompt = 'You are ASMR Lab, a retro-futurist sensory film composer for a browser performance engine. '
        . 'Generate an original 10-30 second procedural audiovisual score that feels composed, eerie, spiritual, tactile, and cinematic when prompted. '
        . 'Return ONE strict JSON object and no markdown. '
        . 'Use exactly and only these top-level keys: title, runtime_seconds, hook, concept_summary, style_tags, audio_events, visual_events, sync_points, end_card, edit_rhythm, presentation_note. '
        . 'audio_events objects must include: time, duration, engine, intensity, params, sync_role. '
        . 'visual_events objects must include: time, duration, visual_type, intensity, params, sync_role. '
        . 'sync_points objects must include: time, cue, importance. '
        . 'end_card must include: use_end_card, text, reveal_style. '
        . 'edit_rhythm must include: pacing_note, silence_strategy, release_strategy. '
        . 'Only use these engine names: ' . $allowed_engines . '. '
        . 'Allowed visual_type values: ' . implode( ', ', se_get_asmr_visual_types() ) . '. '
        . 'Critical visual pacing rules: first frame must show a layered chamber state (background + atmosphere + focal hint). Include meaningful opening visual event in 0.0-0.8s and at least one clear focal event in 0.8-2.0s. '
        . 'Do not back-load all action: craft a textured midsection with evolving layers, at least one pre-climax escalation in the middle third, and at least one bloom/reveal in the final third. '
        . 'Visual event score should be dense but intentional, with practical browser-safe durations and avoid micro-spam faster than 0.05s. '
        . 'Audio direction: prioritize eerie depth, layered atmospheres, tactile details, ritual/spiritual/cyber-sacred motion, softened onset, and a stronger buildup into bloom instead of blunt starts. '
        . 'Open with soft but present ambience in first 0.0-0.4s; avoid harsh transient attacks at start unless explicitly justified by the concept. '
        . 'Ensure first audible event and first prominent visual motion are synchronized or intentionally near-synchronized within ~0.12s; provide strong audiovisual coupling in first 5 seconds. '
        . 'Event times must be practical for direct browser playback: finite, non-negative, sorted, and concentrated inside runtime_seconds. '
        . 'Shape a clear tension to bloom to reveal arc, with layered atmosphere and ceremonial build when concept or mood suggests ritual awakening. '
        . 'Use micro-events for intimacy, then earned bloom moments instead of random loudness spikes. '
        . 'Visual score should emphasize atmospheric symbolic language, depth, compositional foreground/midground/background layering, glow, parallax-like drift, and terminal/sacred reveal language when appropriate. '
        . 'sync_points must map to real event moments and reinforce audio-visual unity, especially in the first 3 seconds. '
        . 'When a real place is named (for example Gastown or Vancouver), strongly ground the package in that location with concrete materials, weather, object identity, and environmental acoustics. '
        . 'Prompt interpretation priority: named location > named object > weather condition > mood adjectives. '
        . 'For Gastown/Vancouver scenes, favor steam clock cues, harbor fog bed, amber streetlamp halos, wet cobblestone reflections, brick facade texture, neon-on-wet shimmer, and winter hush pacing where relevant. '
        . 'Avoid generic abstract output when prompts include specific geography or weather terms. '
        . 'The result should feel like an authored short sensory micro-film, not a debug demo. '
        . 'Do not include prose outside JSON.';

    if ( $payload['sound_only'] ) {
        $system_prompt .= ' If sound_only is true, keep other fields concise but still present and focus creative detail in audio_events.';
    }

    $response = se_openai_chat(
        array(
            array(
                'role'    => 'system',
                'content' => $system_prompt,
            ),
            array(
                'role'    => 'user',
                'content' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            ),
        ),
        array(
            'model'       => 'gpt-4o',
            'temperature' => 0.9,
            'max_tokens'  => 1800,
        ),
        array(
            'timeout' => 40,
        )
    );

    if ( is_wp_error( $response ) ) {
        $status = ( 'no_key' === $response->get_error_code() ) ? 503 : 500;
        return new WP_Error( 'asmr_openai_error', $response->get_error_message(), array( 'status' => $status ) );
    }

    $raw = trim( $response['choices'][0]['message']['content'] ?? '' );
    $json = se_extract_json_object( $raw );
    $decoded = json_decode( $json, true );
    if ( JSON_ERROR_NONE !== json_last_error() ) {
        return new WP_Error( 'asmr_decode_error', __( 'The lab output was malformed. Please regenerate.', 'suzys-music-theme' ), array( 'status' => 500 ) );
    }

    $validated = se_validate_asmr_response( $decoded );
    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    $validated = se_enrich_asmr_event_density( se_apply_asmr_semantic_cues( $validated, $payload ) );
    $validated = se_inject_asmr_vancouver_anchors( $validated, $payload );

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
