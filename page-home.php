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
            $hero_copy    = apply_filters('se_home_hero_copy', 'Independent music, infrastructure nerdery, and creative experiments from downtown Vancouver.');
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
            <div class="hero-side hero-photo-card">
                <p class="hero-side-header">
                    <a class="hero-photo-link" href="<?php echo esc_url(home_url('/bio/')); ?>">
                        <?php echo esc_html('About Suzy'); ?>
                    </a>
                </p>
                <div class="hero-photo-frame">
                    <a class="hero-photo-link hero-photo-link-block" href="<?php echo esc_url(home_url('/bio/')); ?>">
                        <img class="hero-photo" src="<?php echo esc_url(home_url('/wp-content/uploads/2026/01/IMG_9003.jpg')); ?>" alt="<?php echo esc_attr('Suzy Easton smiling with a guitar'); ?>" loading="lazy" decoding="async">
                    </a>
                </div>
                <p class="hero-photo-caption pixel-font">
                    <a class="hero-photo-link" href="<?php echo esc_url(home_url('/bio/')); ?>">
                        <?php echo esc_html('Vancouver, BC / Read bio →'); ?>
                    </a>
                </p>
            </div>
        </div>
        <h2 class="retro-title glow-lite">Musician &amp; Creative Technologist</h2>
        <section class="pixel-intro hero-intro">
            <p class="hero-headline pixel-font">Hi, I’m Suzy Easton — musician and creative technologist.</p>
            <p class="hero-subhead">Vancouver-born, generalist by nature. I make indie rock, small tools, and creative experiments — the kind that (hopefully) make life feel a bit more grounded.</p>
            <p class="hero-microproof">I’ve toured, landed on MuchMusic, and recorded in Chicago with Steve Albini. These days I’m building Track Analyzer, making short AI film experiments with BC + AI Film Club, and writing new songs.</p>
            <div class="hero-lanes">
                <div class="hero-lane">
                    <p class="hero-lane-title pixel-font">Music</p>
                    <p>Indie rock, covers, originals. Always down to jam. New stuff always loading.</p>
                </div>
                <div class="hero-lane">
                    <p class="hero-lane-title pixel-font">Tools</p>
                    <p>Track Analyzer, Lousy Outages, AI Film Club shorts, and other experiments.</p>
                </div>
                <div class="hero-lane">
                    <p class="hero-lane-title pixel-font">Work with me</p>
                    <p>QA, automation, reliability. Help for teams who want fewer WTF just happened afternoons.</p>
                </div>
            </div>
            <div class="hero-cta-group">
                <a href="/suzys-track-analyzer/" class="pixel-button hero-primary-cta">Start here: Track Analyzer</a>
                <a href="/lousy-outages/" class="pixel-button hero-secondary-cta">Open Lousy Outages</a>
                <a href="/bio/" class="pixel-button hero-bio-button">Read full bio</a>
            </div>
        </section>

        <p class="arcade-subtext">Insert coin to explore</p>
        <div class="puck-icon" role="img" aria-label="retro hockey puck icon">🏒</div>
        <?php
        $visitor_data = include get_template_directory() . '/visitor-tracker.php';
        ?>
        <div class="visitor-counter">
          <?php
            $total = isset($visitor_data['count']) ? (int) $visitor_data['count'] : 0;
            $total = max(0, $total);
            $globalPhrases = [
                '👁️ %d drop-in(s) so far.',
                '⚡ %d pixel ping(s) logged.',
                '🎸 %d riff request(s) processed.',
                '🚀 %d prompt(s) launched.',
                '🔥 %d spark(s) on the dashboard.'
            ];
            $phrase = $globalPhrases[array_rand($globalPhrases)];
          ?>
          <p><?php printf(esc_html($phrase), $total); ?></p>
        </div>



        <div class="button-cluster">
            <!-- Put the human stuff first. -->
            <div class="button-group">
                <h3 class="group-title">📚 About &amp; Info</h3>
                <div class="group-buttons">
                    <a href="/bio" class="pixel-button">About Suzy</a>
                    <a href="https://buymeacoffee.com/wi0amge" class="pixel-button" target="_blank" rel="noopener noreferrer">Support</a>
                </div>
            </div>

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
                    <a href="/suzys-track-analyzer/" class="pixel-button">Track Analyzer</a>
                    <a href="/riff-generator" class="pixel-button">Riff Generator</a>
                    <a href="/arcade" class="pixel-button">Canucks Game</a>
                    <a href="/canucks-stats" class="pixel-button">Canucks Stats</a>
                    <a href="/albini-qa" class="pixel-button">Albini Q&amp;A</a>
                </div>
            </div>

        </div>

    </div>

    <section class="ai-film-feature crt-block">
        <div class="ai-film-feature__video">
            <div class="ai-film-feature__embed-wrap">
                <iframe
                    src="<?php echo esc_url('https://www.youtube-nocookie.com/embed/FrjuKGj91Pw'); ?>"
                    title="<?php echo esc_attr('AI Film Club: Rain City Roll Reserve'); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen>
                </iframe>
            </div>
        </div>
        <div class="ai-film-feature__copy">
            <h2 class="pixel-font">AI Film Club: Rain City Roll Reserve</h2>
            <p class="pixel-font ai-film-feature__kicker">BC + AI Film Club Prompt Challenge. Tiny Ghost Studios</p>
            <p>My first AI short got screened in a room full of filmmakers. It is a 19 second retro future Vancouver toilet paper ad. More experiments incoming.</p>
            <p class="ai-film-feature__credit">
                Built for
                <a href="<?php echo esc_url('https://vancouver.bc-ai.net/ai-film-club-launch'); ?>" target="_blank" rel="noopener noreferrer">BC + AI Film Club Prompt Challenge. Tiny Ghost Studios</a>
            </p>
            <div class="ai-film-feature__actions">
                <a href="<?php echo esc_url('https://www.youtube.com/watch?v=FrjuKGj91Pw'); ?>" target="_blank" rel="noopener noreferrer" class="pixel-button">Watch on YouTube</a>
                <a href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>" class="pixel-button">More AI Builds</a>
            </div>
        </div>
    </section>

    <section class="track-analyzer-feature">
        <h2 class="pixel-font">Track Analyzer</h2>
        <p class="pixel-font">Upload an MP3 and get instant feedback on your mix.</p>
        <a href="/suzys-track-analyzer/" class="pixel-button analyzer-cta">Analyze Your Track</a>
    </section>

    <section class="party-announcement crt-block">
        <h2>Grunge &amp; Rock Video Party (Instagram)</h2>
        <p>We’re cueing up a feed of loud guitars, grainy VHS vibes, and deep cuts.
           Follow <a href="https://instagram.com/officialsuzyeaston" target="_blank">@officialsuzyeaston</a>
           for the kickoff date &amp; set list. Requests welcome. Bring flannel.</p>
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

<?php get_footer(); ?>
