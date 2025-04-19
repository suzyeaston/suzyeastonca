<?php
/**
 * Functions file for Suzy’s Music Theme
 *   - Canucks App Integration (News + Betting)
 *   - Albini Q&A React widget
 */

// =========================================
// 1. THEME SCRIPTS & STYLES
// =========================================
function retro_game_music_theme_scripts() {
    // Retro font + main stylesheet
    wp_enqueue_style('retro-font', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
    wp_enqueue_style('main-styles', get_stylesheet_uri());

    // Your existing piano script & game init
    wp_enqueue_script('piano-script', get_template_directory_uri() . '/js/piano-script.js', array(), '1.0.1', true);
    if ( is_front_page() ) {
        wp_enqueue_script('game-init', get_template_directory_uri() . '/js/game-init.js', array(), '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'retro_game_music_theme_scripts');

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
//    Fetches Canucks news and betting odds
// =========================================
function update_canucks_data() {
    // News via rss2json
    $news_api      = 'https://api.rss2json.com/v1/api.json?rss_url=https://theprovince.com/category/sports/hockey/nhl/vancouver-canucks/feed';
    $news_response = wp_remote_get($news_api);
    if ( ! is_wp_error($news_response) ) {
        $news_body = wp_remote_retrieve_body($news_response);
        $news_data = json_decode($news_body, true);
        if ( isset($news_data['items']) && json_last_error() === JSON_ERROR_NONE ) {
            set_transient('canucks_news_data', $news_data['items'], HOUR_IN_SECONDS);
        }
    }

    // Betting via The Odds API (American odds)
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
                return (stripos($home, 'Canucks') !== false || stripos($away, 'Canucks') !== false);
            });
            set_transient('canucks_betting_data', $canucks_betting, HOUR_IN_SECONDS);
        }
    }
}

// =========================================
// 4. CUSTOM CANUCKS API ENDPOINTS
// =========================================
function register_canucks_api_endpoints() {
    register_rest_route('canucks/v1', '/news', array(
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_news'
    ));
    register_rest_route('canucks/v1', '/betting', array(
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_betting'
    ));
}
add_action('rest_api_init', 'register_canucks_api_endpoints');

function get_custom_canucks_news(WP_REST_Request $request) {
    $data = get_transient('canucks_news_data');
    if ( false === $data ) {
        update_canucks_data();
        $data = get_transient('canucks_news_data');
    }
    return rest_ensure_response($data);
}

function get_custom_canucks_betting(WP_REST_Request $request) {
    $data = get_transient('canucks_betting_data');
    if ( false === $data ) {
        update_canucks_data();
        $data = get_transient('canucks_betting_data');
    }
    return rest_ensure_response($data);
}

// =========================================
// 5. SHORTCODE: DISPLAY THE CANUCKS APP
// =========================================
function canucks_app_shortcode() {
    $news_data    = get_transient('canucks_news_data');
    $betting_data = get_transient('canucks_betting_data');

    if ( false === $news_data || false === $betting_data ) {
        update_canucks_data();
        $news_data    = get_transient('canucks_news_data');
        $betting_data = get_transient('canucks_betting_data');
    }

    $output = '<div class="canucks-app">';
    // News
    $output .= '<h2>Latest News</h2>';
    if ( empty($news_data) ) {
        $output .= '<p>No news data available at the moment.</p>';
    } else {
        foreach ( $news_data as $item ) {
            $t = esc_html( $item['title'] ?? 'No Title' );
            $u = esc_url( $item['link'] ?? '#' );
            $d = esc_html( $item['pubDate'] ?? '' );
            $output .= "<div class=\"canucks-news-item\"><p><a href=\"{$u}\" target=\"_blank\">{$t}</a></p>";
            if ( $d ) $output .= "<p>{$d}</p>";
            $output .= '</div>';
        }
    }
    // Betting
    $output .= '<h2>Latest Betting Odds</h2>';
    if ( empty($betting_data) ) {
        $output .= '<p>No betting data available at the moment.</p>';
    } else {
        foreach ( $betting_data as $bet ) {
            $home = esc_html( $bet['home_team'] ?? 'Unknown' );
            $away = esc_html( $bet['away_team'] ?? 'Unknown' );
            $time = $bet['commence_time'] ?? '';
            $output .= "<div class=\"canucks-betting\"><p><strong>Matchup:</strong> {$away} @ {$home}</p>";
            if ( $time ) {
                $fmt  = date_i18n('F j, Y g:i a', strtotime($time));
                $output .= "<p><strong>Start Time:</strong> {$fmt}</p>";
            }
            if ( ! empty($bet['bookmakers']) ) {
                foreach ( $bet['bookmakers'] as $bm ) {
                    $bm_name = esc_html( $bm['title'] ?? 'Bookmaker' );
                    if ( ! empty($bm['markets']) ) {
                        foreach ( $bm['markets'] as $mkt ) {
                            $key  = esc_html( $mkt['key'] ?? '' );
                            $info = '';
                            if ( ! empty($mkt['outcomes']) ) {
                                foreach ( $mkt['outcomes'] as $o ) {
                                    $team  = esc_html( $o['name'] ?? '' );
                                    $price = esc_html( $o['price'] ?? '' );
                                    $pt    = $o['point'] !== '' ? ', ' . esc_html($o['point']) : '';
                                    $info .= "{$team} ({$price}{$pt}) ";
                                }
                            }
                            $output .= "<p><strong>{$bm_name}</strong> – {$key}: {$info}</p>";
                        }
                    }
                }
            }
            $output .= '</div>';
        }
    }
    $output .= '</div>';
    return $output;
}
add_shortcode('canucks_app', 'canucks_app_shortcode');


// =========================================
// 6. ALBINI Q&A SHORTCODE & ASSETS
// =========================================
// Shortcode: mounts your React app
function albini_qa_shortcode() {
    return '<div id="albini-qa-root"></div>';
}
add_shortcode('albini_qa', 'albini_qa_shortcode');

// Enqueue the React build (assumes your build is in /albini-qa/)
function enqueue_albini_qa_assets() {
    // JS bundle
    wp_enqueue_script(
        'albini-qa-js',
        get_template_directory_uri() . '/albini-qa/static/js/main.js',
        array(), null, true
    );
    // CSS bundle
    wp_enqueue_style(
        'albini-qa-css',
        get_template_directory_uri() . '/albini-qa/static/css/main.css',
        array(), null
    );
}
add_action('wp_enqueue_scripts', 'enqueue_albini_qa_assets');
