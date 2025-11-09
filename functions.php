<?php
// Auto-configure OpenAI key from environment if not defined
if ( ! defined( 'OPENAI_API_KEY' ) ) {
    $env_key = getenv( 'OPENAI_API_KEY' );
    if ( $env_key ) {
        define( 'OPENAI_API_KEY', $env_key );
    }
}
/**
 * Functions file for Suzy’s Music Theme
 *   - Canucks App Integration (News + Betting)
 *   - Albini Q&A React widget
 *   - Security hardening: disable XML-RPC, hide users, block author archives
 */

// =========================================
// 1. THEME SCRIPTS & STYLES
// =========================================
function retro_game_music_theme_scripts() {
    // Retro font + main stylesheet
    wp_enqueue_style('retro-font', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
    wp_enqueue_style('main-styles', get_stylesheet_uri());
    wp_enqueue_style(
        'buttons',
        get_template_directory_uri() . '/assets/css/buttons.css',
        [],
        filemtime( get_template_directory() . '/assets/css/buttons.css' )
    );
    if ( is_front_page() ) {
        wp_enqueue_style(
            'retro-title',
            get_template_directory_uri() . '/assets/css/retro-title.css',
            [],
            filemtime( get_template_directory() . '/assets/css/retro-title.css' )
        );
        wp_enqueue_style(
            'lousy-outages-teaser',
            get_template_directory_uri() . '/assets/css/lousy-outages-teaser.css',
            [],
            filemtime( get_template_directory() . '/assets/css/lousy-outages-teaser.css' )
        );
    }

    // Game & piano scripts
    wp_enqueue_script('piano-script', get_template_directory_uri() . '/js/piano-script.js', [], '1.0.1', true);
    wp_enqueue_script('bio-sections', get_template_directory_uri() . '/js/bio-sections.js', [], '1.0.0', true);
    if ( is_front_page() ) {
        wp_enqueue_script('game-init', get_template_directory_uri() . '/js/game-init.js', [], '1.0.0', true);
        wp_enqueue_script('title-color', get_template_directory_uri() . '/js/title-color.js', [], '1.0.0', true);
        wp_enqueue_script(
            'lousy-outages-teaser',
            get_template_directory_uri() . '/assets/js/lousy-outages-teaser.js',
            [],
            filemtime( get_template_directory() . '/assets/js/lousy-outages-teaser.js' ),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'retro_game_music_theme_scripts');

function suzy_enqueue_scripts() {
    $ver = wp_get_theme()->get( 'Version' ) . '-' . substr( md5( filemtime( get_stylesheet_directory() . '/assets/js/canucksPuckBash.js' ) ), 0, 8 );
    wp_enqueue_style( 'suzy-style', get_stylesheet_uri(), [], $ver );

    if ( is_page_template( 'page-arcade.php' ) ) {
        wp_enqueue_script( 'canucks-game', get_stylesheet_directory_uri() . '/assets/js/canucksPuckBash.js', [], $ver, true );
    }

    if ( is_page_template( 'page-albini-qa.php' ) ) {
        $build_dir = get_template_directory_uri() . '/albini-qa/build';
        wp_enqueue_script( 'albini-qa-js',  $build_dir . '/static/js/main.js', [], null, true );
        wp_enqueue_style( 'albini-qa-css',  $build_dir . '/static/css/main.css', [], null );
    }
}
add_action( 'wp_enqueue_scripts', 'suzy_enqueue_scripts' );

function se_enqueue_brand_assets() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style(
        'se-brand-logo',
        $uri . '/assets/brand/brand-logo.css',
        array(),
        file_exists( $dir . '/assets/brand/brand-logo.css' ) ? filemtime( $dir . '/assets/brand/brand-logo.css' ) : null
    );
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_brand_assets' );

function se_enqueue_hero_wordmark_styles() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    $path = '/assets/brand/hero-wordmark.css';
    if ( file_exists( $dir . $path ) ) {
        wp_enqueue_style('se-hero-wordmark', $uri . $path, array(), filemtime($dir . $path));
    }
}
add_action('wp_enqueue_scripts', 'se_enqueue_hero_wordmark_styles');

function se_enqueue_lousy_outages_page_styles() {
    if ( is_page_template( 'page-lousy-outages.php' ) || is_page( 'lousy-outages' ) ) {
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        $path = '/assets/css/lousy-outages-page.css';
        $version = file_exists( $dir . $path ) ? filemtime( $dir . $path ) : null;
        wp_enqueue_style( 'se-lousy-outages-page', $uri . $path, array(), $version );
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_lousy_outages_page_styles' );

// LO: trim header spacing for outages dashboard only.
function se_enqueue_header_tweak_css() {
    if ( is_admin() ) {
        return;
    }
    if ( ! is_page_template( 'page-lousy-outages.php' ) && ! is_page( 'lousy-outages' ) ) {
        return;
    }

    $dir  = get_stylesheet_directory();
    $uri  = get_stylesheet_directory_uri();
    $path = '/assets/brand/header-tweak.css';
    if ( file_exists( $dir . $path ) ) {
        $handle = 'se-header-tweak';
        wp_enqueue_style( $handle, $uri . $path, [], filemtime( $dir . $path ) );

        // LO: provide inline fallback for classless template renderings.
        $page = get_page_by_path( 'lousy-outages' );
        if ( $page instanceof WP_Post ) {
            $id     = (int) $page->ID;
            $inline = '.page-id-' . $id . ' .main-header{min-height:auto;padding-block:8px 12px}'
                . '.page-id-' . $id . ' .main-header .logo img{max-height:48px;height:auto}'
                . '.page-id-' . $id . ' .entry-header h1{margin-top:.5rem;line-height:1.15;overflow:visible}'
                . '.page-id-' . $id . ' .entry-header{scroll-margin-top:80px}';
            wp_add_inline_style( $handle, $inline );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_header_tweak_css' );

// Load bundled Lousy Outages plugin so shortcode and REST endpoint work
$lousy_outages = get_template_directory() . '/lousy-outages/lousy-outages.php';
if ( file_exists( $lousy_outages ) ) {
    require_once $lousy_outages;
}

add_filter( 'lousy_outages_voice_enabled', '__return_true' );

add_action( 'phpmailer_init', function( $phpmailer ) {
    $host = getenv( 'SMTP_HOST' );
    if ( ! $host ) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;

    $auth_env = getenv( 'SMTP_AUTH' );
    if ( false === $auth_env || null === $auth_env ) {
        $phpmailer->SMTPAuth = true;
    } else {
        $phpmailer->SMTPAuth = filter_var( $auth_env, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        if ( null === $phpmailer->SMTPAuth ) {
            $phpmailer->SMTPAuth = true;
        }
    }

    $phpmailer->Port       = (int) ( getenv( 'SMTP_PORT' ) ?: 587 );
    $phpmailer->SMTPSecure = getenv( 'SMTP_SECURE' ) ?: 'tls';
    $phpmailer->Username   = getenv( 'SMTP_USER' ) ?: '';
    $phpmailer->Password   = getenv( 'SMTP_PASS' ) ?: '';
    $phpmailer->Timeout    = 10;
} );

function lousy_outages_feed_autodiscovery() {
    if ( is_front_page() || is_page_template( 'page-lousy-outages.php' ) || is_page( 'lousy-outages' ) ) {
        echo '\n<link rel="alternate" type="application/rss+xml" title="Lousy Outages" href="' . esc_url( home_url( '/lousy-outages/feed/' ) ) . '" />\n';
    }
}
add_action( 'wp_head', 'lousy_outages_feed_autodiscovery' );

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
// =========================================
function update_canucks_data() {
    // --- News via rss2json ---
    $news_api      = 'https://api.rss2json.com/v1/api.json?rss_url=https://theprovince.com/category/sports/hockey/nhl/vancouver-canucks/feed';
    $news_response = wp_remote_get($news_api);

    if ( ! is_wp_error($news_response) ) {
        $news_body = wp_remote_retrieve_body($news_response);
        $news_data = json_decode($news_body, true);
        if ( isset($news_data['items']) && json_last_error() === JSON_ERROR_NONE ) {
            set_transient('suzyeaston_canucks_news', $news_data['items'], HOUR_IN_SECONDS);
        }
    }

    // --- Betting via The Odds API ---
    $betting_api      = 'https://api.the-odds-api.com/v4/sports/icehockey_nhl/odds?regions=us&markets=h2h,spreads,totals&oddsFormat=american&apiKey=c7b1ad088542ae4e9262844141ecb250';
    $betting_response = wp_remote_get($betting_api);

    if ( ! is_wp_error($betting_response) ) {
        $betting_body     = wp_remote_retrieve_body($betting_response);
        $betting_data_all = json_decode($betting_body, true);
        if ( json_last_error() === JSON_ERROR_NONE ) {
            // Filter for Canucks games only
            $canucks_betting = array_filter($betting_data_all, function($game) {
                $home = $game['home_team'] ?? '';
                $away = $game['away_team'] ?? '';
                return stripos($home, 'Canucks') !== false || stripos($away, 'Canucks') !== false;
            });
            set_transient('suzyeaston_canucks_betting', $canucks_betting, HOUR_IN_SECONDS);
        }
    }
}

// =========================================
// 4. REGISTER CUSTOM REST API ENDPOINTS
// =========================================
add_action('rest_api_init', function() {
    register_rest_route('canucks/v1', '/news', [
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_news'
    ]);

    register_rest_route('canucks/v1', '/betting', [
        'methods'  => 'GET',
        'callback' => 'get_custom_canucks_betting'
    ]);

    register_rest_route('albini/v1', '/ask', [
        'methods'             => 'POST',
        'callback'            => 'albini_handle_query',
        'permission_callback' => '__return_true',
    ]);
});

// =========================================
// 5. ENDPOINT CALLBACKS: CANUCKS DATA
// =========================================
function get_custom_canucks_news( WP_REST_Request $request ) {
    $items = get_transient('suzyeaston_canucks_news');
    if ( false === $items ) {
        update_canucks_data();
        $items = get_transient('suzyeaston_canucks_news');
    }
    return rest_ensure_response( $items );
}

function get_custom_canucks_betting( WP_REST_Request $request ) {
    $odds = get_transient('suzyeaston_canucks_betting');
    if ( false === $odds ) {
        update_canucks_data();
        $odds = get_transient('suzyeaston_canucks_betting');
    }
    return rest_ensure_response( $odds );
}

function enqueue_starfield() {
    wp_enqueue_script(
        'starfield',
        get_template_directory_uri() . '/js/starfield.js',
        [], '1.0', true
    );
}
add_action('wp_enqueue_scripts', 'enqueue_starfield');

function enqueue_now_playing() {
    wp_enqueue_script(
        'now-playing',
        get_template_directory_uri() . '/js/now-playing.js',
        [], '1.0', true
    );
    wp_localize_script('now-playing', 'nowPlaying', [
        'username' => 'suzyeaston',
        'api_key'  => 'b8c00d13eccb3a3973dd087d84c0e5b3'
    ]);
}
add_action('wp_enqueue_scripts', 'enqueue_now_playing');

// =========================================
// 6. SHORTCODE: DISPLAY THE CANUCKS APP
// =========================================
function canucks_app_shortcode() {
    $news_data    = get_transient('suzyeaston_canucks_news');
    $betting_data = get_transient('suzyeaston_canucks_betting');

    if ( false === $news_data || false === $betting_data ) {
        update_canucks_data();
        $news_data    = get_transient('suzyeaston_canucks_news');
        $betting_data = get_transient('suzyeaston_canucks_betting');
    }

    ob_start();
    ?>
    <div class="canucks-app">
      <h2>Latest News</h2>
      <?php if ( empty($news_data) ): ?>
        <p>No news data available at the moment.</p>
      <?php else: foreach ( $news_data as $item ): ?>
        <div class="canucks-news-item">
          <p><a href="<?php echo esc_url($item['link']); ?>" target="_blank"><?php echo esc_html($item['title']); ?></a></p>
          <?php if ( ! empty($item['pubDate']) ): ?>
            <p><?php echo esc_html($item['pubDate']); ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>

      <h2>Latest Betting Odds</h2>
      <?php if ( empty($betting_data) ): ?>
        <p>No betting data available at the moment.</p>
      <?php else: foreach ( $betting_data as $bet ): ?>
        <div class="canucks-betting">
          <p><strong>Matchup:</strong> <?php echo esc_html($bet['away_team']); ?> @ <?php echo esc_html($bet['home_team']); ?></p>
          <?php if ( ! empty($bet['commence_time']) ): ?>
            <p><strong>Start Time:</strong> <?php echo date_i18n('F j, Y g:i a', strtotime($bet['commence_time'])); ?></p>
          <?php endif; ?>
          <?php if ( ! empty($bet['bookmakers']) ): foreach ( $bet['bookmakers'] as $bm ): ?>
            <?php if ( ! empty($bm['markets']) ): foreach ( $bm['markets'] as $mkt ): ?>
              <p><strong><?php echo esc_html($bm['title']); ?></strong> – <?php echo esc_html($mkt['key']); ?>:
                <?php
                  $info = array_map(function($o){
                    $pt = (isset($o['point']) && $o['point'] !== '') ? ', ' . esc_html($o['point']) : '';
                    return esc_html($o['name']) . ' (' . esc_html($o['price']) . $pt . ')';
                  }, $mkt['outcomes']);
                  echo implode(' ', $info);
                ?>
              </p>
            <?php endforeach; endif; ?>
          <?php endforeach; endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('canucks_app', 'canucks_app_shortcode');

// =========================================
// 7. ALBINI Q&A SHORTCODE & ASSETS
// =========================================
function albini_qa_shortcode() {
    return '<div id="albini-qa-root"></div>';
}
add_shortcode('albini_qa', 'albini_qa_shortcode');

// =========================================
// 8. ALBINI HANDLER (OpenAI Proxy)
// =========================================
function albini_handle_query( WP_REST_Request $req ) {
    $question = sanitize_textarea_field( $req->get_param('question') );

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . OPENAI_API_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode([
            'model'    => 'gpt-4o',
            'messages' => [
                ['role'=>'system', 'content'=>"You are Steve Albini, legendary producer—blunt, no-BS. Answer questions as he would."],
                ['role'=>'user',   'content'=>$question],
            ],
            'max_tokens' => 300,
        ]),
        'timeout' => 15,
    ]);

    if ( is_wp_error($response) ) {
        return new WP_Error('openai_error', $response->get_error_message(), ['status'=>500]);
    }

    $body   = wp_remote_retrieve_body($response);
    $data   = json_decode($body, true);
    $answer = $data['choices'][0]['message']['content'] ?? 'Hmm, I got nothing back.';

    return rest_ensure_response([ 'answer' => wp_kses_post($answer) ]);
}

// =========================================
// 9. SECURITY HARDENING
// =========================================
// 9a) Disable XML-RPC completely
add_filter('xmlrpc_enabled', '__return_false');

// 9b) Hide REST API user endpoints
add_filter('rest_endpoints', function($endpoints) {
    unset($endpoints['/wp/v2/users']);
    unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    return $endpoints;
});

// 9c) Block author archives
add_action('template_redirect', function() {
    if ( is_author() ) {
        wp_redirect(home_url(), 301);
        exit;
    }
});

// Advertise Lousy Outages RSS feed for readers (auto-discovery)
add_action('wp_head', function () {
    if (function_exists('home_url')) {
        $href = esc_url( home_url('/outages/feed') );
        echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"Lousy Outages Alerts\" href=\"{$href}\" />\n";
    }
});

function se_enqueue_header_tweak_css() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    $path = '/assets/brand/header-tweak.css';
    if ( file_exists( $dir . $path ) ) {
        wp_enqueue_style('se-header-tweak', $uri . $path, array(), filemtime($dir . $path));
    }
}
add_action('wp_enqueue_scripts', 'se_enqueue_header_tweak_css');

