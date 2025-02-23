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
    register_rest_route('canucks/v1', '/betting', array(
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_betting'
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

function get_custom_canucks_betting(WP_REST_Request $request) {
    $data = get_option('canucks_betting_data', array());
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
    // --- Update Schedule Data using NHL API ---
    $schedule_api = 'https://statsapi.web.nhl.com/api/v1/schedule?teamId=26&startDate=' . date('Y-m-d') . '&endDate=' . date('Y-m-d', strtotime('+7 days'));
    $schedule_response = wp_remote_get($schedule_api);
    if ( ! is_wp_error($schedule_response) ) {
        $schedule_body = wp_remote_retrieve_body($schedule_response);
        $schedule_data = json_decode($schedule_body, true);
        // For simplicity, we store the raw schedule data; you might want to filter/format it.
        if ( json_last_error() === JSON_ERROR_NONE ) {
            update_option('canucks_schedule_data', $schedule_data);
        }
    }

    // --- Update News Data ---
    // Replace this with actual API
    $news_api = 'https://api.alternative-source.com/v1/news?team=Vancouver+Canucks';
    $news_response = wp_remote_get($news_api);
    if ( ! is_wp_error($news_response) ) {
        $news_body = wp_remote_retrieve_body($news_response);
        $news_data = json_decode($news_body, true);
        if ( json_last_error() === JSON_ERROR_NONE ) {
            update_option('canucks_news_data', $news_data);
        }
    }

    // --- Update Betting Data using TheOddsAPI ---
    
    $betting_api = 'https://api.the-odds-api.com/v4/sports/ice_hockey_nhl/odds?regions=us&markets=h2h,spreads&apiKey=c7b1ad088542ae4e9262844141ecb250';
    $betting_response = wp_remote_get($betting_api);
    if ( ! is_wp_error($betting_response) ) {
         $betting_body = wp_remote_retrieve_body($betting_response);
         $betting_data_all = json_decode($betting_body, true);
         if ( json_last_error() === JSON_ERROR_NONE ) {
              // Filter for games that include the Canucks
              $canucks_betting = array_filter($betting_data_all, function($game) {
                  $home = isset($game['home_team']) ? $game['home_team'] : '';
                  $away = isset($game['away_team']) ? $game['away_team'] : '';
                  return (stripos($home, 'Canucks') !== false || stripos($away, 'Canucks') !== false);
              });
              update_option('canucks_betting_data', $canucks_betting);
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
    $betting_data = get_option('canucks_betting_data', array());

    $output = '<div class="canucks-app">';

    // --- News Section (at the top) ---
    $output .= '<h2>Latest News</h2>';
    if ( empty($news_data) ) {
         $output .= '<p>No news data available at the moment.</p>';
    } else {
         // Adjust loop
         foreach ( $news_data as $news_item ) {
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
         // Assuming NHL API returns a "dates" array lolol
         if ( isset($schedule_data['dates']) && is_array($schedule_data['dates']) ) {
             foreach ( $schedule_data['dates'] as $date ) {
                 if ( isset($date['games']) && is_array($date['games']) ) {
                     foreach ( $date['games'] as $game ) {
                         $gameDate = isset($game['gameDate']) ? $game['gameDate'] : 'Unknown Date';
                         $awayTeam = isset($game['teams']['away']['team']['name']) ? $game['teams']['away']['team']['name'] : 'Unknown';
                         $homeTeam = isset($game['teams']['home']['team']['name']) ? $game['teams']['home']['team']['name'] : 'Unknown';
                         $status = isset($game['status']['detailedState']) ? $game['status']['detailedState'] : 'Status Unknown';
                         
                         $output .= '<div class="canucks-game">';
                         $output .= '<p><strong>Date:</strong> ' . esc_html($gameDate) . '</p>';
                         $output .= '<p><strong>Matchup:</strong> ' . esc_html($awayTeam) . ' @ ' . esc_html($homeTeam) . '</p>';
                         $output .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
                         $output .= '</div>';
                     }
                 }
             }
         } else {
             $output .= '<p>No upcoming games found.</p>';
         }
    }

    // --- Betting Section ---
    $output .= '<h2>Canucks Betting Odds</h2>';
    if ( empty($betting_data) ) {
         $output .= '<p>No betting data available at the moment.</p>';
    } else {
         foreach ( $betting_data as $bet ) {
              $home_team = isset($bet['home_team']) ? $bet['home_team'] : 'Unknown';
              $away_team = isset($bet['away_team']) ? $bet['away_team'] : 'Unknown';
              $output .= '<div class="canucks-betting">';
              $output .= '<p><strong>' . esc_html($away_team) . ' @ ' . esc_html($home_team) . '</strong></p>';
              if ( isset($bet['bookmakers']) && is_array($bet['bookmakers']) ) {
                   foreach ( $bet['bookmakers'] as $bookmaker ) {
                        $bm_name = isset($bookmaker['title']) ? $bookmaker['title'] : 'Unknown Bookmaker';
                        if ( isset($bookmaker['markets']) && is_array($bookmaker['markets']) ) {
                             foreach ( $bookmaker['markets'] as $market ) {
                                  $market_key = isset($market['key']) ? $market['key'] : '';
                                  $outcome_info = '';
                                  if ( isset($market['outcomes']) && is_array($market['outcomes']) ) {
                                       foreach ( $market['outcomes'] as $outcome ) {
                                            $team = isset($outcome['name']) ? $outcome['name'] : '';
                                            $price = isset($outcome['price']) ? $outcome['price'] : '';
                                            $outcome_info .= esc_html($team . ': ' . $price) . ' ';
                                       }
                                  }
                                  $output .= '<p>' . esc_html($bm_name) . ' - ' . esc_html($market_key) . ': ' . $outcome_info . '</p>';
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
?>

    return $output;
}
add_shortcode('canucks_app', 'canucks_app_shortcode');
?>
