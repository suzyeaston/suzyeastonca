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
    $api_url = 'https://api-web.nhle.com/v1/scoreboard/VAN/now';
    $response = wp_remote_get($api_url);

    // Handle API errors
    if (is_wp_error($response)) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    // Decode JSON response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Validate data
    if (empty($data) || !isset($data['games'])) {
        return 'No data available.';
    }

    // Cache the data for 10 minutes
    set_transient('canucks_scoreboard', $data, 10 * MINUTE_IN_SECONDS);
    return $data;
}

// Fetch Canucks advanced stats
function get_canucks_analytics() {
    $api_url = 'https://api.nhle.com/stats/rest/en/team/summary?cayenneExp=seasonId=20232024%20and%20teamId=23';
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return 'Error fetching advanced stats: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !isset($data['data'][0])) {
        return 'No advanced stats available.';
    }
    return $data['data'][0];
}

// Shortcode to display the Canucks scoreboard and analytics
function canucks_scoreboard_shortcode() {
    $data = get_canucks_scoreboard();
    $analytics = get_canucks_analytics();

    if (is_string($data)) {
        return '<div class="canucks-error">' . esc_html($data) . '</div>';
    }

    $html = '<div class="canucks-scoreboard">';
    $html .= '<h2>Canucks Retro Scoreboard</h2>';

    foreach ($data['games'] as $game) {
        $html .= '<div class="canucks-game">';
        $html .= '<p><strong>Opponent:</strong> ' . esc_html($game['opponent']['name']) . '</p>';
        $html .= '<p><strong>Score:</strong> ' . esc_html($game['score']['canucks']) . ' - ' . esc_html($game['score']['opponent']) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . esc_html($game['status']) . '</p>';
        $html .= '</div>';
    }

    if (!is_string($analytics)) {
        $html .= '<div class="canucks-analytics">';
        $html .= '<h3>Advanced Stats</h3>';
        $html .= '<p><strong>Corsi For %:</strong> ' . esc_html($analytics['corsiForPercentage']) . '%</p>';
        $html .= '<p><strong>Expected Goals %:</strong> ' . esc_html($analytics['expectedGoalsPercentage']) . '%</p>';
        $html .= '<p><strong>Power Play %:</strong> ' . esc_html($analytics['powerPlayPercentage']) . '%</p>';
        $html .= '<p><strong>Penalty Kill %:</strong> ' . esc_html($analytics['penaltyKillPercentage']) . '%</p>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
add_shortcode('canucks_scoreboard', 'canucks_scoreboard_shortcode');
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
    $api_url = 'https://api-web.nhle.com/v1/scoreboard/VAN/now';
    $response = wp_remote_get($api_url);

    // Handle API errors
    if (is_wp_error($response)) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    // Decode JSON response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Validate data
    if (empty($data) || !isset($data['games'])) {
        return 'No data available.';
    }

    // Cache the data for 10 minutes
    set_transient('canucks_scoreboard', $data, 10 * MINUTE_IN_SECONDS);
    return $data;
}

// Fetch Canucks advanced stats
function get_canucks_analytics() {
    $api_url = 'https://api.nhle.com/stats/rest/en/team/summary?cayenneExp=seasonId=20232024%20and%20teamId=23';
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return 'Error fetching advanced stats: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !isset($data['data'][0])) {
        return 'No advanced stats available.';
    }
    return $data['data'][0];
}

// Shortcode to display the Canucks scoreboard and analytics
function canucks_scoreboard_shortcode() {
    $data = get_canucks_scoreboard();
    $analytics = get_canucks_analytics();

    if (is_string($data)) {
        return '<div class="canucks-error">' . esc_html($data) . '</div>';
    }

    $html = '<div class="canucks-scoreboard">';
    $html .= '<h2>Canucks Retro Scoreboard</h2>';

    foreach ($data['games'] as $game) {
        $html .= '<div class="canucks-game">';
        $html .= '<p><strong>Opponent:</strong> ' . esc_html($game['opponent']['name']) . '</p>';
        $html .= '<p><strong>Score:</strong> ' . esc_html($game['score']['canucks']) . ' - ' . esc_html($game['score']['opponent']) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . esc_html($game['status']) . '</p>';
        $html .= '</div>';
    }

    if (!is_string($analytics)) {
        $html .= '<div class="canucks-analytics">';
        $html .= '<h3>Advanced Stats</h3>';
        $html .= '<p><strong>Corsi For %:</strong> ' . esc_html($analytics['corsiForPercentage']) . '%</p>';
        $html .= '<p><strong>Expected Goals %:</strong> ' . esc_html($analytics['expectedGoalsPercentage']) . '%</p>';
        $html .= '<p><strong>Power Play %:</strong> ' . esc_html($analytics['powerPlayPercentage']) . '%</p>';
        $html .= '<p><strong>Penalty Kill %:</strong> ' . esc_html($analytics['penaltyKillPercentage']) . '%</p>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
add_shortcode('canucks_scoreboard', 'canucks_scoreboard_shortcode');
