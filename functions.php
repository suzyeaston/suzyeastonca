<?php

// Load styles and scripts for the theme
function retro_game_music_theme_scripts() {
    wp_enqueue_style('retro-font', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
    wp_enqueue_style('main-styles', get_stylesheet_uri());
    wp_enqueue_script('piano-script', get_template_directory_uri() . '/js/piano-script.js', array(), '1.0.1', true);

    // Load `game-init.js` only on the front page
    if (is_front_page()) {
        wp_enqueue_script('game-init', get_template_directory_uri() . '/js/game-init.js', array(), '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'retro_game_music_theme_scripts');

// Disable automatic paragraph formatting in content
function disable_autop_formatting() {
    remove_filter('the_content', 'wpautop');
    remove_filter('the_excerpt', 'wpautop');
}
add_action('init', 'disable_autop_formatting');

// Fetch the Canucks schedule data
function get_canucks_schedule() {
    // Check if cached data exists
    $cached_data = get_transient('canucks_schedule');
    if ($cached_data) {
        return $cached_data;
    }

    // API endpoint for the Canucks' schedule
    $api_url = 'https://api-web.nhle.com/v1/schedule?teamId=23'; // 23 is the team ID for the Vancouver Canucks

    // Fetch data from the NHL API
    $response = wp_remote_get($api_url);

    // Handle API errors
    if (is_wp_error($response)) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    // Decode the JSON response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check for valid data
    if (empty($data) || !isset($data['dates'])) {
        return 'No data available.';
    }

    // Cache the data for 10 minutes to reduce API requests
    set_transient('canucks_schedule', $data, 10 * MINUTE_IN_SECONDS);

    return $data;
}

// Shortcode to display the Canucks schedule
function canucks_schedule_shortcode() {
    // Fetch the schedule data
    $data = get_canucks_schedule();

    // Handle errors and return early
    if (is_string($data)) {
        return '<div class="canucks-error">' . esc_html($data) . '</div>';
    }

    // Build the HTML for the schedule
    $html = '<div class="canucks-schedule">';
    $html .= '<h2>Canucks Retro Schedule</h2>';

    // Loop through the schedule and display each game
    foreach ($data['dates'] as $date) {
        foreach ($date['games'] as $game) {
            $html .= '<div class="canucks-game">';
            $html .= '<p><strong>Date:</strong> ' . esc_html(date('F j, Y', strtotime($game['gameDate']))) . '</p>';
            $html .= '<p><strong>Matchup:</strong> ' . esc_html($game['teams']['away']['team']['name']) . ' at ' . esc_html($game['teams']['home']['team']['name']) . '</p>';
            $html .= '<p><strong>Status:</strong> ' . esc_html($game['status']['detailedState']) . '</p>';
            $html .= '</div>';
        }
    }

    $html .= '</div>';
    return $html;
}
add_shortcode('canucks_schedule', 'canucks_schedule_shortcode');
?>
