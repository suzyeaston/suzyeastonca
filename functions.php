<?php
/**
 * Functions file for Suzys Music Theme – Updated Canucks App Integration
 */

// =========================
// 1. THEME SCRIPTS & STYLES
// =========================
function retro_game_music_theme_scripts() {
    wp_enqueue_style('retro-font', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
    wp_enqueue_style('main-styles', get_stylesheet_uri());
    wp_enqueue_script('piano-script', get_template_directory_uri() . '/js/piano-script.js', array(), '1.0.1', true);

    // Load game-init.js only on the front page
    if ( is_front_page() ) {
        wp_enqueue_script('game-init', get_template_directory_uri() . '/js/game-init.js', array(), '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'retro_game_music_theme_scripts');

// =============================================
// 2. DISABLE AUTOMATIC PARAGRAPH FORMATTING
// =============================================
function disable_autop_formatting() {
    remove_filter('the_content', 'wpautop');
    remove_filter('the_excerpt', 'wpautop');
}
add_action('init', 'disable_autop_formatting');

// =============================================
// 3. CUSTOM CANUCKS API ENDPOINTS
// =============================================
function register_canucks_api_endpoints() {
    register_rest_route('canucks/v1', '/schedule', array(
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_schedule'
    ));
    register_rest_route('canucks/v1', '/news', array(
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_news'
    ));
}
add_action('rest_api_init', 'register_canucks_api_endpoints');

function get_custom_canucks_schedule(WP_REST_Request $request) {
    $data = get_option('canucks_schedule_data', array());
    return rest_ensure_response($data);
}

function get_custom_canucks_news(WP_REST_Request $request) {
    $data = get_option('canucks_news_data', array());
    return rest_ensure_response($data);
}

// =============================================
// 4. UPDATE CANUCKS DATA VIA WP‑CRON
// =============================================
// Schedule the event if not already scheduled.
if ( ! wp_next_scheduled('update_canucks_data_event') ) {
    wp_schedule_event(time(), 'hourly', 'update_canucks_data_event');
}
add_action('update_canucks_data_event', 'update_canucks_data');

function update_canucks_data() {
    // --- Update Schedule Data ---
    $schedule_api = 'https://api.alternative-source.com/v1/schedule'; // REPLACE with your actual API endpoint
    $schedule_response = wp_remote_get($schedule_api);
    if ( ! is_wp_error($schedule_response) ) {
        $schedule_body = wp_remote_retrieve_body($schedule_response);
        $schedule_data = json_decode($schedule_body, true);
        if ( json_last_error() === JSON_ERROR_NONE ) {
            update_option('canucks_schedule_data', $schedule_data);
        }
    }

    // --- Update News Data ---
    $news_api = 'https://api.alternative-source.com/v1/news'; // REPLACE with your actual API endpoint
    $news_response = wp_remote_get($news_api);
    if ( ! is_wp_error($news_response) ) {
        $news_body = wp_remote_retrieve_body($news_response);
        $news_data = json_decode($news_body, true);
        if ( json_last_error() === JSON_ERROR_NONE ) {
            update_option('canucks_news_data', $news_data);
        }
    }
}

// =============================================
// 5. SHORTCODE: DISPLAY THE CANUCKS APP
// =============================================
function canucks_app_shortcode() {
    // Retrieve stored data
    $schedule_data = get_option('canucks_schedule_data', array());
    $news_data = get_option('canucks_news_data', array());

    $output = '<div class="canucks-app">';

    // --- News Section (now at the top) ---
    $output .= '<h2>Latest News</h2>';
    if ( empty($news_data) ) {
         $output .= '<p>No news data available at the moment.</p>';
    } else {
         foreach ( $news_data as $news_item ) {
             // Adjust keys as per your API response.
             $title = isset($news_item['title']) ? $news_item['title'] : 'No Title';
             $link  = isset($news_item['link']) ? $news_item['link'] : '#';
             $date  = isset($news_item['date']) ? $news_item['date'] : '';
             $output .= '<div class="canucks-news-item">';
             $output .= '<p><a href="' . esc_url($link) . '" target="_blank">' . esc_html($title) . '</a></p>';
             if ( $date ) {
                 $output .= '<p>' . esc_html($date) . '</p>';
             }
             $output .= '</div>';
         }
    }

    // --- Schedule Section ---
    $output .= '<h2>Canucks Schedule</h2>';
    if ( empty($schedule_data) ) {
         $output .= '<p>No schedule data available at the moment.</p>';
    } else {
         foreach ( $schedule_data as $game ) {
             // Adjust the keys according to your API response structure.
             $date   = isset($game['date']) ? $game['date'] : 'Unknown Date';
             $away   = isset($game['awayTeam']) ? $game['awayTeam'] : 'Unknown';
             $home   = isset($game['homeTeam']) ? $game['homeTeam'] : 'Unknown';
             $status = isset($game['status']) ? $game['status'] : 'Status Unknown';
             $score  = isset($game['score']) ? $game['score'] : '';
             $output .= '<div class="canucks-game">';
             $output .= '<p><strong>Date:</strong> ' . esc_html($date) . '</p>';
             $output .= '<p><strong>Matchup:</strong> ' . esc_html($away) . ' @ ' . esc_html($home) . '</p>';
             $output .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
             if ( ! empty($score) ) {
                 $output .= '<p><strong>Score:</strong> ' . esc_html($score) . '</p>';
             }
             $output .= '</div>';
         }
    }
    $output .= '</div>';
    return $output;
}
add_shortcode('canucks_app', 'canucks_app_shortcode');
?>
