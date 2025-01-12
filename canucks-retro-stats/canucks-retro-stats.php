<?php
/**
 * Plugin Name: Canucks Retro Stats
 * Description: Display Vancouver Canucks stats in a sweet 90s scoreboard style.
 * Version: 0.1
 * Author: Suzy Easton
 */

// Fetch the Canucks scoreboard data
function get_canucks_scoreboard() {
    // Check if cached data exists
    $cached_data = get_transient( 'canucks_scoreboard' );
    if ( $cached_data ) {
        return $cached_data;
    }

    // API endpoint for Canucks scoreboard
    $api_url = 'https://api-web.nhle.com/v1/scoreboard/VAN/now';

    // Fetch data from the NHL API
    $response = wp_remote_get( $api_url );

    // Handle API errors
    if ( is_wp_error( $response ) ) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    // Decode the JSON response
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body );

    // Check for valid data
    if ( empty( $data ) || !isset( $data->games ) ) {
        return 'No data available.';
    }

    // Cache the data for 10 minutes to reduce API requests
    set_transient( 'canucks_scoreboard', $data, 10 * MINUTE_IN_SECONDS );

    return $data;
}

// Shortcode to display the Canucks scoreboard
function canucks_scoreboard_shortcode() {
    // Fetch the scoreboard data
    $data = get_canucks_scoreboard();

    // Handle errors and return early
    if ( is_string( $data ) ) {
        return '<div class="canucks-error">' . esc_html( $data ) . '</div>';
    }

    // Build the HTML for the scoreboard
    $html = '<div class="canucks-scoreboard">';
    $html .= '<h2>Canucks Retro Scoreboard</h2>';

    // Loop through games and display each one
    foreach ( $data->games as $game ) {
        $html .= '<div class="canucks-game">';
        $html .= '<p><strong>Opponent:</strong> ' . esc_html( $game->opponent->name ) . '</p>';
        $html .= '<p><strong>Score:</strong> ' . esc_html( $game->score->canucks ) . ' - ' . esc_html( $game->score->opponent ) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . esc_html( $game->status ) . '</p>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
add_shortcode( 'canucks_scoreboard', 'canucks_scoreboard_shortcode' );

// Enqueue styles for the retro scoreboard
function canucks_retro_enqueue_scripts() {
    // Enqueue the retro font
    wp_enqueue_style( 'press-start-2p', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap', [], null );

    // Enqueue the custom CSS for the scoreboard
    wp_enqueue_style( 'retro-scoreboard', plugin_dir_url( __FILE__ ) . 'css/retro-scoreboard.css', [], '1.0' );
}
add_action( 'wp_enqueue_scripts', 'canucks_retro_enqueue_scripts' );
