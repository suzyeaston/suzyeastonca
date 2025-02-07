<?php
// Load styles and scripts for the theme
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

// Disable automatic paragraph formatting in content
function disable_autop_formatting() {
    remove_filter('the_content', 'wpautop');
    remove_filter('the_excerpt', 'wpautop');
}
add_action('init', 'disable_autop_formatting');

// Fetch the Canucks schedule data using the updated API endpoint and normalize the response
function get_canucks_schedule() {
    $cached_data = get_transient('canucks_schedule');
    if ( $cached_data ) {
        return $cached_data;
    }

    // Updated API endpoint using the team code "VAN" for Vancouver Canucks
    $api_url = 'https://api-web.nhle.com/v1/club-schedule-season/VAN/now';
    $response = wp_remote_get($api_url);

    if ( is_wp_error($response) ) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);

    // Log the raw API response
    error_log("Raw API Response: " . $body);

    $data = json_decode($body, true);

    // Check for JSON decoding errors
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        error_log("JSON Decode Error: " . json_last_error_msg());
        return 'Error decoding JSON data.';
    }

    if ( empty($data) || !isset($data['games']) ) {
        return 'No data available from API.';
    }

    $games = $data['games'];

    // Store in cache for 1 hour
    set_transient('canucks_schedule', $games, HOUR_IN_SECONDS);
    return $games;
}

// Register the shortcode only on the front end
if ( ! is_admin() ) {
    function canucks_schedule_shortcode() {
        $games = get_canucks_schedule();

        // Check if $games is a string (error message) and return it
        if ( is_string($games) ) {
            return '<div class="canucks-error">' . esc_html($games) . '</div>';
        }

        $html = '<div class="canucks-scoreboard">';
        $html .= '<h2>Canucks Retro Schedule</h2>';

        foreach ( $games as $game ) {
            // Extract and format the game date
            $gameDate = isset($game['gameDate']) ? $game['gameDate'] : 'Unknown Date';
            $formattedDate = esc_html($gameDate ? date('F j, Y', strtotime($gameDate)) : 'Unknown Date');

            // Extract team names safely
            $awayTeam = isset($game['awayTeam']['placeName']['default']) && isset($game['awayTeam']['commonName']['default']) ? $game['awayTeam']['placeName']['default'] . ' ' . $game['awayTeam']['commonName']['default'] : 'Unknown';
            $homeTeam = isset($game['homeTeam']['placeName']['default']) && isset($game['homeTeam']['commonName']['default']) ? $game['homeTeam']['placeName']['default'] . ' ' . $game['homeTeam']['commonName']['default'] : 'Unknown';

            // Extract status safely
            $status = isset($game['gameState']) ? $game['gameState'] : 'Status Unknown';

            // Build game display
            $html .= '<div class="canucks-game">';
            $html .= '<p><strong>Date:</strong> ' . $formattedDate . '</p>';
            $html .= '<p><strong>Matchup:</strong> ' . esc_html($awayTeam) . ' at ' . esc_html($homeTeam) . '</p>';
            $html .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
    add_shortcode('canucks_scoreboard', 'canucks_schedule_shortcode');
}
?>
