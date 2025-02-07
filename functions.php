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

// Fetch the Canucks schedule data using a newer endpoint and normalize the response
function get_canucks_schedule() {
    // Check if cached data exists
    $cached_data = get_transient('canucks_schedule');
    if ($cached_data) {
        return $cached_data;
    }

    // Updated API endpoint using the team's three-letter code "VAN" (for Vancouver Canucks)
    $api_url = 'https://api-web.nhle.com/v1/club-schedule-season/VAN/now';

    // Fetch data from the NHL API
    $response = wp_remote_get($api_url);

    // Handle API errors
    if (is_wp_error($response)) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    // Decode the JSON response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Log the raw API response for debugging
    error_log("Canucks API response: " . print_r($data, true));

    if (empty($data)) {
        return 'No data available from API.';
    }

    // Normalize the data: if the response contains a "dates" key, flatten it;
    // otherwise, assume it's already a list of game objects.
    $games = array();
    if (isset($data['dates']) && is_array($data['dates'])) {
        foreach ($data['dates'] as $date_entry) {
            if (isset($date_entry['games']) && is_array($date_entry['games'])) {
                foreach ($date_entry['games'] as $game) {
                    $games[] = $game;
                }
            }
        }
    } elseif (is_array($data)) {
        $games = $data;
    }

    if (empty($games)) {
        return 'No games found in the schedule data.';
    }

    // Cache the normalized games data for 10 minutes
    set_transient('canucks_schedule', $games, 10 * MINUTE_IN_SECONDS);

    return $games;
}

// Shortcode to display the Canucks schedule (registered as "canucks_scoreboard")
function canucks_schedule_shortcode() {
    // Fetch the normalized schedule data (flat list of games)
    $games = get_canucks_schedule();

    // If an error message (string) is returned, show it
    if (is_string($games)) {
        return '<div class="canucks-error">' . esc_html($games) . '</div>';
    }

    $html = '<div class="canucks-schedule">';
    $html .= '<h2>Canucks Retro Schedule</h2>';

    // Loop through each game and output details
    foreach ($games as $game) {
        // Extract game details using safe defaults
        $gameDate = isset($game['gameDate']) ? $game['gameDate'] : '';
        $formattedDate = $gameDate ? esc_html(date('F j, Y', strtotime($gameDate))) : 'Unknown Date';

        // Extract team names; adjust keys if necessary based on your API's structure
        $awayTeam = isset($game['teams']['away']['team']['name']) ? $game['teams']['away']['team']['name'] : 'Unknown';
        $homeTeam = isset($game['teams']['home']['team']['name']) ? $game['teams']['home']['team']['name'] : 'Unknown';
        $status = isset($game['status']['detailedState']) ? $game['status']['detailedState'] : 'Status Unknown';

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
?>
