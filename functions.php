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

// Fetch the Canucks game data
function get_canucks_game_data() {
    // Check if cached data exists
    $cached_data = get_transient('canucks_game_data');
    if ($cached_data) {
        return $cached_data;
    }

    // API endpoint for the Canucks schedule
    $api_url = 'https://statsapi.web.nhl.com/api/v1/schedule?teamId=23&startDate=' . date('Y-m-d') . '&endDate=' . date('Y-m-d');

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
    if (empty($data) || !isset($data['dates'][0]['games'])) {
        return 'No games scheduled for today.';
    }

    // Cache the data for 10 minutes to reduce API requests
    set_transient('canucks_game_data', $data['dates'][0]['games'][0], 10 * MINUTE_IN_SECONDS);

    return $data['dates'][0]['games'][0];
}

// Fetch Canucks advanced stats
function get_canucks_advanced_stats() {
    // Check if cached data exists
    $cached_data = get_transient('canucks_advanced_stats');
    if ($cached_data) {
        return $cached_data;
    }

    // API endpoint for team stats
    $api_url = 'https://statsapi.web.nhl.com/api/v1/teams/23/stats';

    // Fetch data from the NHL API
    $response = wp_remote_get($api_url);

    // Handle API errors
    if (is_wp_error($response)) {
        return 'Error fetching advanced stats: ' . $response->get_error_message();
    }

    // Decode the JSON response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check for valid data
    if (empty($data) || !isset($data['stats'][0]['splits'][0]['stat'])) {
        return 'No advanced stats available.';
    }

    // Cache the data for 10 minutes to reduce API requests
    set_transient('canucks_advanced_stats', $data['stats'][0]['splits'][0]['stat'], 10 * MINUTE_IN_SECONDS);

    return $data['stats'][0]['splits'][0]['stat'];
}

// Shortcode to display the Canucks scoreboard and analytics
function canucks_scoreboard_shortcode() {
    // Fetch the game data
    $game = get_canucks_game_data();

    // Handle errors and return early
    if (is_string($game)) {
        return '<div class="canucks-error">' . esc_html($game) . '</div>';
    }

    // Fetch the advanced stats
    $stats = get_canucks_advanced_stats();

    // Build the HTML for the scoreboard
    $html = '<div class="canucks-scoreboard">';
    $html .= '<h2>Canucks Retro Scoreboard</h2>';
    $html .= '<p><strong>Matchup:</strong> ' . esc_html($game['teams']['away']['team']['name']) . ' at ' . esc_html($game['teams']['home']['team']['name']) . '</p>';
    $html .= '<p><strong>Game Time:</strong> ' . esc_html(date('g:i A', strtotime($game['gameDate']))) . '</p>';
    $html .= '<p><strong>Status:</strong> ' . esc_html($game['status']['detailedState']) . '</p>';

    // Display score if the game has started
    if (isset($game['linescore'])) {
        $html .= '<p><strong>Score:</strong> ' . esc_html($game['teams']['away']['score']) . ' - ' . esc_html($game['teams']['home']['score']) . '</p>';
    }

    // Display advanced stats
    if (!is_string($stats)) {
        $html .= '<div class="canucks-analytics">';
        $html .= '<h3>Advanced Stats</h3>';
        $html .= '<p><strong>Goals Per Game:</strong> ' . esc_html($stats['goalsPerGame']) . '</p>';
        $html .= '<p><strong>Power Play Percentage:</strong> ' . esc_html($stats['powerPlayPercentage']) . '%</p>';
        $html .= '<p><strong>Penalty Kill Percentage:</strong> ' . esc_html($stats['penaltyKillPercentage']) . '%</p>';
        $html .= '<p><strong>Shots Per Game:</strong> ' . esc_html($stats['shotsPerGame']) . '</p>';
        $html .= '<p><strong>Shots Allowed:</strong> ' . esc_html($stats['shotsAllowed']) . '</p>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
add_shortcode('canucks_scoreboard', 'canucks_scoreboard_shortcode');
?>
