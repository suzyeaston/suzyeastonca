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

// Fetch the Canucks scoreboard data
function get_canucks_scoreboard() {
    // Check if cached data exists
    $cached_data = get_transient('canucks_scoreboard');
    if ($cached_data) {
        return $cached_data;
    }

    // API endpoint for today's schedule
    $api_url = 'https://statsapi.web.nhl.com/api/v1/schedule';

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

    // Find the Canucks game
    $canucks_game = null;
    foreach ($data['dates'][0]['games'] as $game) {
        if ($game['teams']['home']['team']['name'] == 'Vancouver Canucks' || $game['teams']['away']['team']['name'] == 'Vancouver Canucks') {
            $canucks_game = $game;
            break;
        }
    }

    if (!$canucks_game) {
        return 'No Canucks game scheduled for today.';
    }

    // Cache the data for 10 minutes to reduce API requests
    set_transient('canucks_scoreboard', $canucks_game, 10 * MINUTE_IN_SECONDS);

    return $canucks_game;
}

// Shortcode to display the Canucks scoreboard
function canucks_scoreboard_shortcode() {
    // Fetch the scoreboard data
    $game = get_canucks_scoreboard();

    // Handle errors and return early
    if (is_string($game)) {
        return '<div class="canucks-error">' . esc_html($game) . '</div>';
    }

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

    $html .= '</div>';
    return $html;
}
add_shortcode('canucks_scoreboard', 'canucks_scoreboard_shortcode');
