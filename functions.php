<?php
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

// =============================
// 3. FETCH & CACHE CANUCKS GAMES
// =============================
function get_canucks_schedule() {
    // Try pulling from WordPress transient first
    $cached_data = get_transient('canucks_schedule');
    if ( $cached_data ) {
        return $cached_data;
    }

    // The schedule endpoint for the Vancouver Canucks
    $api_url = 'https://api-web.nhle.com/v1/club-schedule-season/VAN/now';
    $response = wp_remote_get($api_url);

    if ( is_wp_error($response) ) {
        return 'Error fetching data: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return 'Error decoding JSON data: ' . json_last_error_msg();
    }

    if ( empty($data) || !isset($data['games']) ) {
        return 'No data available from API.';
    }

    $games = $data['games'];

    // Cache for 1 hour
    set_transient('canucks_schedule', $games, HOUR_IN_SECONDS);

    return $games;
}

// =======================
// 4. FETCH LOCAL NEWS (RSS)
// =======================
// Example using The Province’s Canucks RSS feed.
// If you find a better or more official feed, just swap the URL below.
function get_canucks_news() {
    // Transient for caching news for 10 minutes
    $cached_news = get_transient('canucks_news');
    if ( $cached_news ) {
        return $cached_news;
    }

    // Example feed: The Province - Vancouver Canucks
    // If this feed changes or you want a different source, update the URL:
    $feed_url = 'https://theprovince.com/category/sports/hockey/nhl/vancouver-canucks/feed';

    // WordPress has a built-in feed parser if SimplePie is enabled
    include_once( ABSPATH . WPINC . '/feed.php' );
    $feed = fetch_feed($feed_url);

    if ( is_wp_error($feed) ) {
        return 'Error fetching news: ' . $feed->get_error_message();
    }

    $maxitems = $feed->get_item_quantity(5); // Number of items to fetch
    $items = $feed->get_items(0, $maxitems);

    // We'll build a simple array of [ 'title' => ..., 'link' => ..., 'date' => ... ]
    $news_items = array();
    foreach ( $items as $item ) {
        $news_items[] = array(
            'title' => $item->get_title(),
            'link'  => $item->get_permalink(),
            'date'  => $item->get_date('F j, Y g:ia')
        );
    }

    // Cache for 10 minutes
    set_transient('canucks_news', $news_items, 10 * MINUTE_IN_SECONDS);

    return $news_items;
}

// ==========================
// 5. SHORTCODE: CANUCKS APP
// ==========================
// This single shortcode will display both the schedule and news in one go.
// Alternatively, you could separate them into multiple shortcodes if you prefer.
if ( ! is_admin() ) {
    function canucks_app_shortcode() {
        // 5A. Get the schedule data
        $games = get_canucks_schedule();

        // If $games is a string, it's an error message
        if ( is_string($games) ) {
            $schedule_html = '<div class="canucks-error">' . esc_html($games) . '</div>';
        } else {
            // Filter out past games
            $today = new DateTime('today', new DateTimeZone('America/Vancouver'));
            $upcoming_games = array_filter($games, function($game) use ($today) {
                if (!isset($game['gameDate'])) return false;
                $gameDateTime = new DateTime($game['gameDate'], new DateTimeZone('UTC'));
                // Convert to Pacific
                $gameDateTime->setTimezone(new DateTimeZone('America/Vancouver'));
                return $gameDateTime >= $today;
            });

            // Random fun facts / quips
            $fun_facts = array(
                "Towel Power originated in Vancouver back in '82! Same year I was born, baby!",
            );
            $random_fact = $fun_facts[array_rand($fun_facts)];

            $schedule_html = '<div class="canucks-scoreboard">';
            $schedule_html .= '<h2>Canucks Retro Schedule</h2>';

            // Show random fun fact
            $schedule_html .= '<div class="canucks-fun-fact">';
            $schedule_html .= '<p><em>Did you know? ' . esc_html($random_fact) . '</em></p>';
            $schedule_html .= '</div>';

            if (empty($upcoming_games)) {
                $schedule_html .= '<div class="canucks-game"><p>No upcoming games found.</p></div>';
            } else {
                foreach ( $upcoming_games as $game ) {
                    $gameDateTime = new DateTime($game['gameDate'], new DateTimeZone('UTC'));
                    $gameDateTime->setTimezone(new DateTimeZone('America/Vancouver'));
                    $formattedDate = $gameDateTime->format('F j, Y, g:ia T');

                    $awayTeam = 'Unknown';
                    $homeTeam = 'Unknown';
                    if (isset($game['awayTeam']['placeName']['default'], $game['awayTeam']['commonName']['default'])) {
                        $awayTeam = $game['awayTeam']['placeName']['default'] . ' ' . $game['awayTeam']['commonName']['default'];
                    }
                    if (isset($game['homeTeam']['placeName']['default'], $game['homeTeam']['commonName']['default'])) {
                        $homeTeam = $game['homeTeam']['placeName']['default'] . ' ' . $game['homeTeam']['commonName']['default'];
                    }

                    $status = isset($game['gameState']) ? $game['gameState'] : 'Status Unknown';
                    $awayScore = isset($game['awayTeam']['score']) ? $game['awayTeam']['score'] : '';
                    $homeScore = isset($game['homeTeam']['score']) ? $game['homeTeam']['score'] : '';

                    // Start building the single game block
                    $schedule_html .= '<div class="canucks-game">';
                    $schedule_html .= '<p><strong>Date:</strong> ' . esc_html($formattedDate) . '</p>';
                    $schedule_html .= '<p><strong>Matchup:</strong> ' . esc_html($awayTeam) . ' at ' . esc_html($homeTeam) . '</p>';
                    $schedule_html .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';

                    // If we have scores, show them
                    if ($awayScore !== '' && $homeScore !== '') {
                        // If the game is in progress or final, highlight it
                        $schedule_html .= '<p><strong>Score:</strong> ' . esc_html($awayScore) . ' - ' . esc_html($homeScore) . '</p>';
                    }
                    $schedule_html .= '</div>'; // .canucks-game
                }
            }
            $schedule_html .= '</div>'; // .canucks-scoreboard
        }

        // 5B. Get the local news
        $news_items = get_canucks_news();
        if ( is_string($news_items) ) {
            // It's an error message
            $news_html = '<div class="canucks-error">' . esc_html($news_items) . '</div>';
        } else if ( empty($news_items) ) {
            $news_html = '<div class="canucks-news"><p>No current news available.</p></div>';
        } else {
            // Build HTML for the news feed
            $news_html = '<div class="canucks-news">';
            $news_html .= '<h2>Latest Canucks Headlines</h2>';
            foreach ( $news_items as $item ) {
                $news_html .= '<div class="canucks-news-item">';
                $news_html .= '<p><a href="' . esc_url($item['link']) . '" target="_blank">';
                $news_html .= esc_html($item['title']);
                $news_html .= '</a></p>';
                $news_html .= '<p class="news-date">' . esc_html($item['date']) . '</p>';
                $news_html .= '</div>';
            }
            $news_html .= '</div>';
        }

        // Return final output: the schedule + the news
        return $schedule_html . $news_html;
    }
    add_shortcode('canucks_app', 'canucks_app_shortcode');
}
?>
