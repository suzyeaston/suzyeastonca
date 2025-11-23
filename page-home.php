<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero hero-section">
        <div class="hero-grid">
            <?php
            $hero_eyebrow = apply_filters('se_home_hero_eyebrow', '');
            $hero_logo_label = apply_filters('se_home_hero_title', 'Suzanne (Suzy) Easton');
            $hero_logo_top = apply_filters('se_home_hero_logo_top', 'Suzanne');
            $hero_logo_mid = apply_filters('se_home_hero_logo_mid', '(Suzy)');
            $hero_logo_bottom = apply_filters('se_home_hero_logo_bottom', 'Easton');
            $hero_logo_top_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_top, 'UTF-8') : strtoupper($hero_logo_top);
            $hero_logo_mid_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_mid, 'UTF-8') : strtoupper($hero_logo_mid);
            $hero_logo_bottom_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_bottom, 'UTF-8') : strtoupper($hero_logo_bottom);
            $hero_copy    = apply_filters('se_home_hero_copy', 'Independent music, infrastructure nerdiness, and creative experiments direct from Vancouver.');
            ?>
            <div class="hero-main">
                <?php if (!empty($hero_eyebrow)) : ?>
                    <p class="hero-eyebrow pixel-font"><?php echo esc_html($hero_eyebrow); ?></p>
                <?php endif; ?>
                <h1 class="screen-reader-text"><?php echo esc_html($hero_logo_label); ?></h1>
                <div class="hero-wordmark-wrap">
                    <div class="hero-badge">
                        <p class="hero-wordmark" aria-label="<?php echo esc_attr($hero_logo_label); ?>">
                            <span class="line1">
                                <?php echo esc_html($hero_logo_top_text); ?>
                                <small><?php echo esc_html($hero_logo_mid_text); ?></small>
                            </span>
                            <span class="line2"><?php echo esc_html($hero_logo_bottom_text); ?></span>
                        </p>
                    </div>
                </div>
                <p class="hero-copy"><?php echo esc_html($hero_copy); ?></p>
            </div>
        </div>
        <section class="lo-callout lo-8bit">
          <h2>LOUSY OUTAGES<br><span>LIVE STATUS DASHBOARD</span></h2>
          <p>Track real-world outage chaos in arcade style.<br>
          Suzanne (Suzy) Easton watches the clouds, ISPs, and tools you rely on — while dropping new indie tracks from Vancouver.</p>
          <a class="btn-8bit" href="/lousy-outages/">OPEN THE LIVE DASHBOARD →</a>
        </section>
        <h2 class="retro-title glow-lite">Musician &amp; Creative Technologist</h2>
        <section class="pixel-intro" style="max-width: 720px; margin: 0 auto; line-height: 1.8; font-size: 1.05rem;">
    <p>I&rsquo;m a musician, technologist, and creative builder based in Vancouver.</p>

    <p>Toured nationally as a bassist, recorded with Steve Albini in Chicago, and appeared on MuchMusic. Today, I keep creating, playing around with programming/coding and releasing new music at night while shipping infrastructure code by day hah!</p>

    <p>i was going to run for vancouver city council in 2026, but instead i&rsquo;m going to focus on building weird/beautiful creative tools</p>
</section>

        <p class="arcade-subtext">Insert coin to explore</p>
        <div class="puck-icon" role="img" aria-label="retro hockey puck icon">🏒</div>
        <?php
        $visitor_data = include get_template_directory() . '/visitor-tracker.php';
        $phrases = [
            '⚡ %d poor souls from %s woke the Albini bot today.',
            '🎸 %d noise-makers from %s just asked why their band sucks.',
            '🤖 Albini\'s circuit board has processed %d fragile egos from %s.',
            '👁️ %d lurkers from %s looking for validation. None found.',
            '🔥 %d punks from %s have faced the wrath of Albini\'s sarcasm.',
            '💀 %d existential crises triggered in %s.',
            '🚀 %s launched %d useless questions into the void.'
        ];
        ?>
        <div class="visitor-counter">
          <?php foreach ($visitor_data['locations'] as $c => $cnt): ?>
            <p><?php printf(esc_html($phrases[array_rand($phrases)]), $cnt, $c); ?></p>
          <?php endforeach; ?>
          <?php if (!empty($visitor_data['locations'])): ?>
            <p>
              <?php
              arsort($visitor_data['locations']);
              $leaders = array_slice($visitor_data['locations'], 0, 3, true);
              $leaderboard = [];
              foreach ($leaders as $cc => $ct) {
                  $leaderboard[] = "$cc ($ct)";
              }
              echo 'Leaderboard: ' . esc_html(implode(', ', $leaderboard));
              ?>
            </p>
          <?php endif; ?>
        </div>



        <div class="button-cluster">
            <div class="button-group">
                <h3 class="group-title">🎵 Music &amp; Podcasts</h3>
                <div class="group-buttons">
                    <a href="https://suzyeaston.bandcamp.com" class="pixel-button" target="_blank">Bandcamp</a>
                    <a href="/podcast" class="pixel-button">Podcast: Easy Living</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">🎮 Games &amp; Tools</h3>
                <div class="group-buttons">
                    <a href="/riff-generator" class="pixel-button">Riff Generator</a>
                    <a href="/arcade" class="pixel-button">Canucks Game</a>
                    <a href="/canucks-stats" class="pixel-button">Canucks Stats</a>
                    <a href="/albini-qa" class="pixel-button">Albini Q&amp;A</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">📺 Livestream &amp; Events</h3>
                <div class="group-buttons">
                    <a href="/social-media" class="pixel-button">Livestream</a>
                    <a href="/music-releases" class="pixel-button">Upcoming Events</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">📚 About &amp; Info</h3>
                <div class="group-buttons">
                    <a href="/bio" class="pixel-button">About Suzy</a>
                </div>
            </div>
        </div>

    </div>

    <?php get_template_part('parts/lousy-outages-teaser'); ?>

    <section class="now-listening">
        <h2 class="pixel-font">Now Listening</h2>
        <div class="now-listening-item">
            <iframe width="100%" height="360" src="https://www.youtube.com/embed/GwetNnBkgQM?autoplay=0" title="Metric – Full Performance (Live on KEXP)" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p class="pixel-font now-listening-caption">🎶 Metric — Live on KEXP. stripped-down, all-acoustic set. no AI-weapons funding here.</p>
        </div>
        <?php
        $willie_heading = apply_filters('se_home_willie_heading', 'Now Playing: Willie Nelson — “Hands on the Wheel”');
        $willie_body    = apply_filters('se_home_willie_body', 'At a moment when the world feels off-kilter, “Hands on the Wheel” brings it back to center. Minimal production, maximum heart. Love steadies the hands.');
        ?>
        <div class="now-listening-item">
            <h3 class="pixel-font now-listening-subhead"><?php echo esc_html($willie_heading); ?></h3>
            <p class="pixel-font now-listening-caption"><?php echo esc_html($willie_body); ?></p>
            <iframe width="100%" height="360" src="https://www.youtube.com/embed/71cIYDnDZUk?autoplay=0" title="Willie Nelson — Hands on the Wheel" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </section>

    <?php
    $grimes_note = apply_filters(
        'se_home_grimes_note',
        'Spotify founder Daniel Ek’s fund Prima Materia poured €600M into defense-AI company Helsing in 2025. Indie mainstays like Deerhoof yanked catalogues in protest, calling out the weapons tie-in. Sharing Grimes here keeps the conversation loud: artists deserve platforms that aren’t bankrolled by war tech.'
    );
    $grimes_heading = apply_filters('se_home_grimes_heading', 'Spotlight: Grimes — “Artificial Angels”');
    ?>
    <section class="now-listening grimes-feature">
        <div class="info-callout pixel-font">
            <p><?php echo wp_kses_post($grimes_note); ?></p>
        </div>
        <h2 class="pixel-font"><?php echo esc_html($grimes_heading); ?></h2>
        <iframe width="100%" height="360" src="https://www.youtube.com/embed/tvGnYM14-1A?autoplay=0" title="Grimes — Artificial Angels (Official Video)" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </section>

    <section class="track-analyzer-feature">
        <h2 class="pixel-font">Track Analyzer</h2>
        <p class="pixel-font">Upload an MP3 and get instant feedback on your mix.</p>
        <a href="/suzys-track-analyzer" class="pixel-button analyzer-cta">Analyze Your Track</a>
    </section>

    <section class="party-announcement crt-block">
        <h2>Grunge &amp; Rock Video Party (Instagram)</h2>
        <p>We’re cueing up a feed of loud guitars, grainy VHS vibes, and deep cuts.
           Follow <a href="https://instagram.com/officialsuzyeaston" target="_blank">@officialsuzyeaston</a>
           for the kickoff date &amp; set list. Requests welcome. Bring flannel.</p>
    </section>


    <section class="support-section">
        <h2 class="pixel-font">Support My Creative Journey</h2>
        <p class="pixel-font">For collaborations or just to chat, reach out at <a href="mailto:suzyeaston@icloud.com" class="support-link">suzyeaston@icloud.com</a></p>
        <p style="text-align:center;">🎶 <a href="https://suzyeaston.bandcamp.com" target="_blank">Support my music on Bandcamp</a></p>
        <p style="text-align:center;">New demo drops this weekend. Stay noisy.</p>
    </section>
</main>

    <script>
      (function () {
        var ticker = document.querySelector('[data-influence-rotator]');
        if (!ticker) {
          return;
        }
        var influences;
        try {
          influences = JSON.parse(ticker.getAttribute('data-influences') || '[]');
        } catch (err) {
          influences = [];
        }
        if (!Array.isArray(influences) || influences.length === 0) {
          return;
        }
        var index = 0;
        var mq = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
        var update = function () {
          ticker.classList.remove('influence-swap');
          void ticker.offsetWidth;
          ticker.textContent = influences[index];
          ticker.classList.add('influence-swap');
          index = (index + 1) % influences.length;
        };
        if (!mq || !mq.matches) {
          update();
          window.setInterval(update, 7000);
        } else {
          ticker.textContent = influences[0];
        }
      }());
    </script>

    <script>
      (function () {
        var badge = document.querySelector('[data-lo-home-badge]');
        if (!badge || typeof window.fetch !== 'function') {
          return;
        }
        var endpoint = '<?php echo esc_url_raw(rest_url('lousy-outages/v1/summary')); ?>?lite=1';
        fetch(endpoint, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store'
        })
          .then(function (res) {
            if (!res) {
              return null;
            }
            if (res.status === 204) {
              return null;
            }
            if (res.status >= 200 && res.status < 300) {
              return res.json().catch(function () { return null; });
            }
            return null;
          })
          .then(function (data) {
            if (data && data.trending) {
              badge.removeAttribute('hidden');
            }
          })
          .catch(function () {
            // ignore errors on homepage badge
          });
      }());
    </script>
<?php get_footer(); ?>
