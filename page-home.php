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
            $hero_copy    = apply_filters('se_home_hero_copy', 'Music and tech experiments from Vancouver, BC.');
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
            <button class="hero-deco hero-ship hero-ship--autopilot" type="button" aria-label="Drag the spaceship" title="Drag me" tabindex="0"></button>
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
            <p class="hero-subhead">Born in Vancouver, BC and a generalist by nature, I build things, play guitars, and live by one motto: always be creating. My background spans classical music training, plus five years behind the counter at HMV talking records and discovering new sounds.</p>
            <p class="hero-microproof">I’ve toured across Canada as a bassist, appeared on MuchMusic, and recorded in Chicago with Steve Albini. These days, I’m building apps, exploring AI models, experimenting with short AI films through BC + AI Film Club, and writing whatever songs show up next.</p>
            <div class="hero-cta-group">
                <a href="/suzys-track-analyzer/" class="pixel-button hero-primary-cta">Start with: Track Analyzer</a>
                <a href="/asmr-lab/" class="pixel-button hero-secondary-cta">Try: ASMR Lab</a>
                <a href="/lousy-outages/" class="pixel-button hero-secondary-cta">Or explore: Lousy Outages</a>
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
                    <a href="/asmr-lab/" class="pixel-button">ASMR Lab</a>
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

    <section class="asmr-lab-teaser crt-block" aria-labelledby="asmr-lab-teaser-title">
        <p class="asmr-lab-teaser__eyebrow pixel-font">Experimental Machine Online</p>
        <h2 id="asmr-lab-teaser-title" class="pixel-font">ASMR Lab</h2>
        <p class="asmr-lab-teaser__description">Step into the noise chamber: AI-generated sensory ad concepts, 8-bit foley recipes, and model-ready video prompts wired for curious makers.</p>
        <a href="<?php echo esc_url(home_url('/asmr-lab/')); ?>" class="pixel-button asmr-lab-teaser__cta">Enter ASMR Lab</a>
    </section>

    <section class="gastown-sim-teaser crt-block" aria-labelledby="gastown-sim-teaser-title">
        <p class="gastown-sim-teaser__eyebrow pixel-font"><span class="gastown-sim-teaser__badge">New</span> In progress. Shipping weird little upgrades constantly.</p>
        <h2 id="gastown-sim-teaser-title" class="pixel-font">New: Gastown First-Person Simulator</h2>
        <p class="gastown-sim-teaser__description">A first-person Vancouver experiment inspired by the ASMR Lab and now mutating into a playable Gastown walk. Start near Waterfront Station and head toward Water Street and the Steam Clock.</p>
        <p class="gastown-sim-teaser__microcopy">Getting tuned up often, sometimes daily. If the world looks haunted, hard refresh or hop into a private window and dive back in.</p>
        <a href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>" class="pixel-button gastown-sim-teaser__cta">Enter Gastown</a>
    </section>

    <section class="track-analyzer-feature">
        <h2 class="pixel-font">Track Analyzer</h2>
        <p class="pixel-font">Upload an MP3 and get instant feedback on your mix.</p>
        <a href="/suzys-track-analyzer/" class="pixel-button analyzer-cta">Analyze Your Track</a>
    </section>

    <section class="creative-tool-links crt-block" aria-labelledby="creative-tool-links-title">
        <h2 id="creative-tool-links-title" class="pixel-font">Creative Tool Deck</h2>
        <p>Prefer crawl-first browsing? Jump straight to the public tool pages:</p>
        <ul>
            <li><a href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>">Gastown First-Person Simulator</a> — first-person Vancouver prototype, currently in active daily-ish mutation mode.</li>
            <li><a href="<?php echo esc_url(home_url('/asmr-lab/')); ?>">ASMR Lab</a> — AI sensory ad concepts, tactile beat sheets, and retro-foley prompt kits.</li>
            <li><a href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>">Track Analyzer</a> — upload a mix and get fast AI feedback.</li>
            <li><a href="<?php echo esc_url(home_url('/riff-generator/')); ?>">Riff Generator</a> — generate guitar ideas in a retro experiment shell.</li>
            <li><a href="<?php echo esc_url(home_url('/lousy-outages/')); ?>">Lousy Outages</a> — outage tracker with a throwback command-line vibe.</li>
        </ul>
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
