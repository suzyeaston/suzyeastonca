<?php
/**
 * Functions file for Suzy’s Music Theme – Updated Canucks App Integration (News + Betting Only)
 */

// =========================================
// 1. THEME SCRIPTS & STYLES
// =========================================
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

// =========================================
// 2. DISABLE AUTOMATIC PARAGRAPH FORMATTING
// =========================================
function disable_autop_formatting() {
    remove_filter('the_content', 'wpautop');
    remove_filter('the_excerpt', 'wpautop');
}
add_action('init', 'disable_autop_formatting');

// =========================================
// 3. ON-DEMAND DATA UPDATE (NO CRON)
//    Fetches Canucks news and betting odds
// =========================================
function update_canucks_data() {
    // --- Update News Data using rss2json conversion of The Province's feed ---
    $news_api = 'https://api.rss2json.com/v1/api.json?rss_url=https://theprovince.com/category/sports/hockey/nhl/vancouver-canucks/feed';
    $news_response = wp_remote_get($news_api);

    if ( ! is_wp_error($news_response) ) {
        $news_body = wp_remote_retrieve_body($news_response);
        $news_data = json_decode($news_body, true);

        if ( isset($news_data['items']) && json_last_error() === JSON_ERROR_NONE ) {
            set_transient('canucks_news_data', $news_data['items'], HOUR_IN_SECONDS);
        }
    }

    // --- Update Betting Data using The Odds API (with American odds) ---
    //    Feel free to replace YOUR_API_KEY with your actual API key
    $betting_api = 'https://api.the-odds-api.com/v4/sports/icehockey_nhl/odds?regions=us&markets=h2h,spreads,totals&oddsFormat=american&apiKey=c7b1ad088542ae4e9262844141ecb250';
    $betting_response = wp_remote_get($betting_api);

    if ( ! is_wp_error($betting_response) ) {
         $betting_body = wp_remote_retrieve_body($betting_response);
         $betting_data_all = json_decode($betting_body, true);

         if ( json_last_error() === JSON_ERROR_NONE ) {
              // If you want to show *all* NHL games, remove the filter below
              // For now, let's keep it so we only show games specifically mentioning "Canucks".
              $canucks_betting = array_filter($betting_data_all, function($game) {
                  $home = isset($game['home_team']) ? $game['home_team'] : '';
                  $away = isset($game['away_team']) ? $game['away_team'] : '';
                  return (stripos($home, 'Canucks') !== false || stripos($away, 'Canucks') !== false);
              });

              set_transient('canucks_betting_data', $canucks_betting, HOUR_IN_SECONDS);
         }
    }
}

// =========================================
// 4. CUSTOM CANUCKS API ENDPOINTS
//    News & Betting Only
// =========================================
function register_canucks_api_endpoints() {
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

/**
 * Retrieve news data on demand
 */
function get_custom_canucks_news(WP_REST_Request $request) {
    $data = get_transient('canucks_news_data');

    // If missing/expired, fetch fresh data
    if ( false === $data ) {
        update_canucks_data();
        $data = get_transient('canucks_news_data');
    }
    return rest_ensure_response($data);
}

/**
 * Retrieve betting data on demand
 */
function get_custom_canucks_betting(WP_REST_Request $request) {
    $data = get_transient('canucks_betting_data');

    // If missing/expired, fetch fresh data
    if ( false === $data ) {
        update_canucks_data();
        $data = get_transient('canucks_betting_data');
    }
    return rest_ensure_response($data);
}

// =========================================
// 5. SHORTCODE: DISPLAY THE CANUCKS APP
//    (News + Betting in that order)
// =========================================
function canucks_app_shortcode() {
    // Attempt to retrieve data from transients
    $news_data    = get_transient('canucks_news_data');
    $betting_data = get_transient('canucks_betting_data');

    // If something is missing, force an update
    if ( false === $news_data || false === $betting_data ) {
        update_canucks_data();
        $news_data    = get_transient('canucks_news_data');
        $betting_data = get_transient('canucks_betting_data');
    }

    $output = '<div class="canucks-app">';

    // --- News Section ---
    $output .= '<h2>Latest News</h2>';
    if ( empty($news_data) ) {
         $output .= '<p>No news data available at the moment.</p>';
    } else {
         foreach ( $news_data as $news_item ) {
             $title   = isset($news_item['title']) ? $news_item['title'] : 'No Title';
             $link    = isset($news_item['link']) ? $news_item['link'] : '#';
             $pubDate = isset($news_item['pubDate']) ? $news_item['pubDate'] : '';
             $output .= '<div class="canucks-news-item">';
             $output .= '<p><a href="' . esc_url($link) . '" target="_blank">' . esc_html($title) . '</a></p>';
             if ( $pubDate ) {
                 $output .= '<p>' . esc_html($pubDate) . '</p>';
             }
             $output .= '</div>';
         }
    }

    // --- Betting Section ---
    $output .= '<h2>Latest Betting Odds</h2>';
    if ( empty($betting_data) ) {
         $output .= '<p>No betting data available at the moment.</p>';
    } else {
         foreach ( $betting_data as $bet ) {
              $home_team = isset($bet['home_team']) ? $bet['home_team'] : 'Unknown';
              $away_team = isset($bet['away_team']) ? $bet['away_team'] : 'Unknown';
              $commence_time = isset($bet['commence_time']) ? $bet['commence_time'] : '';
              
              $output .= '<div class="canucks-betting">';
              $output .= '<p><strong>Matchup:</strong> ' . esc_html($away_team) . ' @ ' . esc_html($home_team) . '</p>';

              // Show date/time if available
              if ( $commence_time ) {
                  $formatted_time = date_i18n( 'F j, Y g:i a', strtotime($commence_time) );
                  $output .= '<p><strong>Start Time:</strong> ' . esc_html($formatted_time) . '</p>';
              }

              if ( isset($bet['bookmakers']) && is_array($bet['bookmakers']) ) {
                   foreach ( $bet['bookmakers'] as $bookmaker ) {
                        $bm_name = isset($bookmaker['title']) ? $bookmaker['title'] : 'Unknown Bookmaker';

                        if ( isset($bookmaker['markets']) && is_array($bookmaker['markets']) ) {
                             foreach ( $bookmaker['markets'] as $market ) {
                                  $market_key = isset($market['key']) ? $market['key'] : '';
                                  $outcome_info = '';

                                  if ( isset($market['outcomes']) && is_array($market['outcomes']) ) {
                                       foreach ( $market['outcomes'] as $outcome ) {
                                            $team  = isset($outcome['name'])  ? $outcome['name']  : '';
                                            $price = isset($outcome['price']) ? $outcome['price'] : '';
                                            $point = isset($outcome['point']) ? $outcome['point'] : '';

                                            // Build a string for each outcome: e.g. "Canucks (-110, +1.5)"
                                            // Only show point if it exists (for spreads, totals)
                                            $details = $team . ' (' . $price;
                                            if ( $point !== '' ) {
                                                $details .= ', ' . $point;
                                            }
                                            $details .= ') ';

                                            $outcome_info .= esc_html($details);
                                       }
                                  }

                                  // e.g. "DraftKings – h2h: Canucks (-110) Maple Leafs (+120)"
                                  $output .= '<p><strong>' . esc_html($bm_name) . '</strong> – ' . esc_html($market_key) . ': ' . $outcome_info . '</p>';
                             }
                        }
                   }
              }
              $output .= '</div>'; // close .canucks-betting
         }
    }

    $output .= '</div>'; // close .canucks-app
    return $output;
}
add_shortcode('canucks_app', 'canucks_app_shortcode');
?>
