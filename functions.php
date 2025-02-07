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
    $data = json_decode($body, true);
    error_log("Canucks API response: " . print_r($data, true));

    if ( empty($data) ) {
        return 'No data available from API.';
    }

    $games = array();
    if ( isset($data['dates']) && is_array($data['dates']) ) {
        foreach ( $data['dates'] as $date_entry ) {
            if ( isset($date_entry['games']) && is_array($date_entry['games']) ) {
                foreach ( $date_entry['games'] as $game ) {
                    $games[] = $game;
                }
            }
        }
    } elseif ( isset($data['games']) && is_array($data['games']) ) {
        $games = $data['games'];
    } elseif ( is_array($data) ) {
        $games = $data;
    }

    if ( empty($games) ) {
        return 'No games found in the schedule data.';
    }

    set_transient('canucks_schedule', $games, 10 * MINUTE_IN_SECONDS);
    return $games;
}

// Register the shortcode only on the front end
if ( ! is_admin() ) {
    function canucks_schedule_shortcode() {
        $games = get_canucks_schedule();
        if ( is_string($games) ) {
            return '<div class="canucks-error">' . esc_html($games) . '</div>';
        }

        $html = '<div class="canucks-scoreboard">';
        $html .= '<h2>Canucks Retro Schedule</h2>';

        foreach ( $games as $game ) {
            $gameDate = '';
            if ( isset($game['gameDate']) ) {
                $gameDate = $game['gameDate'];
            } elseif ( isset($game['date']) ) {
                $gameDate = $game['date'];
            } elseif ( isset($game['startTime']) ) {
                $gameDate = $game['startTime'];
            }
            $formattedDate = $gameDate ? esc_html(date('F j, Y', strtotime($gameDate))) : 'Unknown Date';

            $awayTeam = '';
            $homeTeam = '';
            if ( isset($game['teams']) ) {
                if ( isset($game['teams']['away']) ) {
                    if ( isset($game['teams']['away']['team']['name']) ) {
                        $awayTeam = $game['teams']['away']['team']['name'];
                    } elseif ( isset($game['teams']['away']['teamName']) ) {
                        $awayTeam = $game['teams']['away']['teamName'];
                    }
                }
                if ( isset($game['teams']['home']) ) {
                    if ( isset($game['teams']['home']['team']['name']) ) {
                        $homeTeam = $game['teams']['home']['team']['name'];
                    } elseif ( isset($game['teams']['home']['teamName']) ) {
                        $homeTeam = $game['teams']['home']['teamName'];
                    }
                }
            }
            if ( ! $awayTeam && isset($game['awayTeam']) ) {
                $awayTeam = $game['awayTeam'];
            }
            if ( ! $homeTeam && isset($game['homeTeam']) ) {
                $homeTeam = $game['homeTeam'];
            }
            if ( ! $awayTeam ) {
                $awayTeam = 'Unknown';
            }
            if ( ! $homeTeam ) {
                $homeTeam = 'Unknown';
            }

            $status = 'Status Unknown';
            if ( isset($game['status']) ) {
                if ( is_array($game['status']) ) {
                    if ( isset($game['status']['detailedState']) ) {
                        $status = $game['status']['detailedState'];
                    } elseif ( isset($game['status']['state']) ) {
                        $status = $game['status']['state'];
                    }
                } else {
                    $status = $game['status'];
                }
            }

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
