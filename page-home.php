<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero hero-section">
        <div class="hero-grid">
            <?php
            $hero_eyebrow = apply_filters('se_home_hero_eyebrow', 'Vancouver, BC • Built in public');
            $hero_logo_label = apply_filters('se_home_hero_title', 'Suzy Easton');
            $hero_logo_top = apply_filters('se_home_hero_logo_top', 'Suzy');
            $hero_logo_mid = apply_filters('se_home_hero_logo_mid', '');
            $hero_logo_bottom = apply_filters('se_home_hero_logo_bottom', 'Easton');
            $hero_logo_top_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_top, 'UTF-8') : strtoupper($hero_logo_top);
            $hero_logo_mid_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_mid, 'UTF-8') : strtoupper($hero_logo_mid);
            $hero_logo_bottom_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_bottom, 'UTF-8') : strtoupper($hero_logo_bottom);
            $hero_copy = apply_filters('se_home_hero_copy', 'Musician, creative technologist, and builder of strange useful things.');
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
                                <?php if (!empty($hero_logo_mid_text)) : ?>
                                    <small><?php echo esc_html($hero_logo_mid_text); ?></small>
                                <?php endif; ?>
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

        <section class="pixel-intro hero-intro" aria-labelledby="home-positioning-title">
            <h2 id="home-positioning-title" class="retro-title glow-lite">Musician, creative technologist, and builder of weird useful internet things</h2>
            <p class="hero-headline pixel-font">This is Suzy&rsquo;s public lab: AI experiments, music tools, prototypes, and odd little upgrades made in Vancouver.</p>
            <p class="hero-subhead">I build in public, ship constantly, and share the process as I go. Code, sounds, experiments, and occasional chaos.</p>
            <p class="hero-subhead">PSA: every time you visit, clear your cache and cookies first. lol</p>
            <div class="hero-cta-group">
                <a href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>" class="pixel-button hero-primary-cta">Start with Gastown Simulator</a>
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="pixel-button hero-secondary-cta">Explore the Lab</a>
                <a href="<?php echo esc_url(home_url('/bio/')); ?>" class="pixel-button hero-bio-button">Read full bio</a>
            </div>
            <p class="hero-collab-link"><a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>">Want to build something cool together? Side-quest door is here.</a></p>
        </section>

        <p class="arcade-subtext">Insert coin to explore</p>
    </div>

    <section class="selected-work crt-block" aria-labelledby="selected-work-title">
        <h2 id="selected-work-title" class="pixel-font">Featured builds / lab highlights</h2>
        <p class="selected-work__intro">Flagship public builds: playful, practical, and always evolving in the open.</p>
        <div class="selected-work__grid">
            <article class="selected-work__card">
                <h3 class="pixel-font">Gastown Simulator</h3>
                <p>Flagship first-person Vancouver prototype with live worldbuilding, rapid iteration, and CRT-night atmosphere.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>">Enter Gastown</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Track Analyzer</h3>
                <p>AI feedback tool for musicians: upload a track, get useful signal fast, and iterate your mix with confidence.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>">Analyze a Track</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Lousy Outages</h3>
                <p>Retro terminal outage tracker built for modern service chaos: alerting, status clarity, and practical public utility.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>">View Outages</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Rain City Roll Reserve</h3>
                <p>AI Film Club short set in the Rain City universe: weird cinema experiments where storyboards meet machine dreams.</p>
                <a class="pixel-button" href="https://www.youtube.com/watch?v=FrjuKGj91Pw" target="_blank" rel="noopener noreferrer">Watch the short film</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">ASMR Lab (legacy prototype)</h3>
                <p>Earlier audio-visual experiment currently in rebuild mode, still archived as part of the lab&rsquo;s public timeline.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/asmr-lab/')); ?>">View prototype log</a>
            </article>
        </div>
    </section>

    <section class="build-public-home crt-block" aria-labelledby="build-public-title">
        <h2 id="build-public-title" class="pixel-font">Build in public / repo receipts</h2>
        <p class="home-section-legend-links" aria-label="Build in public quick links">
            <a href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer">GitHub repo</a>
            <span aria-hidden="true">//</span>
            <a href="<?php echo esc_url(home_url('/projects/')); ?>">Project build logs</a>
            <span aria-hidden="true">//</span>
            <a href="<?php echo esc_url(home_url('/blog/')); ?>">Latest lab notes</a>
        </p>
        <p>Everything here ships in the open: code in public repos, experiments updated often, and build notes that show the real process.</p>
    </section>

    <section class="music-world crt-block" aria-labelledby="music-world-title">
        <h2 id="music-world-title" class="pixel-font">Music / media / world</h2>
        <p class="home-section-legend-links" aria-label="Music and media quick links">
            <a href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer">Bandcamp</a>
            <span aria-hidden="true">//</span>
            <a href="<?php echo esc_url(home_url('/podcast/')); ?>">Easy Living podcast</a>
            <span aria-hidden="true">//</span>
            <a href="https://instagram.com/officialsuzyeaston" target="_blank" rel="noopener noreferrer">Instagram</a>
            <span aria-hidden="true">//</span>
            <a href="https://www.youtube.com/@suzyeaston" target="_blank" rel="noopener noreferrer">YouTube</a>
            <span aria-hidden="true">//</span>
            <a href="https://soundcloud.com/suzyeaston" target="_blank" rel="noopener noreferrer">SoundCloud</a>
        </p>
        <p>The creative universe behind the builds: songs, podcast conversations, AI film experiments, and dispatches from the lab.</p>
    </section>

    <section class="collab-invite-home crt-block" aria-labelledby="collab-invite-title">
        <h2 id="collab-invite-title" class="pixel-font">Occasional collaborations</h2>
        <p>Most of this site is me building in public. Sometimes I also take on the right paid side quest: debugging snarled systems, tightening QA, or prototyping something sharp and strange.</p>
        <div class="collab-invite-home__actions">
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button">Collaborate With Suzy</a>
            <a href="mailto:suzyeaston@icloud.com?subject=Side%20Quest%20Collaboration" class="pixel-button">Email Suzy</a>
        </div>
    </section>

    <?php
    $visitor_data = include get_template_directory() . '/visitor-tracker.php';
    $total = isset($visitor_data['count']) ? (int) $visitor_data['count'] : 0;
    $total = max(0, $total);
    ?>
    <section class="utility-nav-home crt-block" aria-labelledby="utility-nav-title">
        <h2 id="utility-nav-title" class="pixel-font">Quick links</h2>
        <p class="utility-nav-home__counter"><?php echo esc_html(sprintf('👁️ %d drop-in(s) logged so far.', $total)); ?></p>
        <div class="utility-nav-home__links">
            <a href="<?php echo esc_url(home_url('/bio/')); ?>">Bio</a>
            <a href="<?php echo esc_url(home_url('/projects/')); ?>">Projects</a>
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>">Collaborations</a>
            <a href="<?php echo esc_url(home_url('/podcast/')); ?>">Podcast</a>
            <a href="<?php echo esc_url(home_url('/coffee-for-builders/')); ?>">Coffee for Builders</a>
            <a href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer">Public repo</a>
            <a href="https://buymeacoffee.com/wi0amge" target="_blank" rel="noopener noreferrer">Buy me a coffee</a>
        </div>
    </section>
</main>

<?php get_footer(); ?>
