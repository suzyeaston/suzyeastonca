<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero hero-section">
        <div class="hero-grid">
            <?php
            $hero_eyebrow = apply_filters('se_home_hero_eyebrow', 'Vancouver, BC • Built in public');
            $hero_logo_label = apply_filters('se_home_hero_title', 'Suzanne (Suzy) Easton');
            $hero_logo_top = apply_filters('se_home_hero_logo_top', 'Suzanne');
            $hero_logo_mid = apply_filters('se_home_hero_logo_mid', '(Suzy)');
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

        <section class="pixel-intro hero-intro" aria-labelledby="home-positioning-title">
            <h2 id="home-positioning-title" class="retro-title glow-lite">Musician, creative technologist, and practical AI builder</h2>
            <p class="hero-headline pixel-font">I make AI experiments, public prototypes, music tools, and messy systems behave.</p>
            <p class="hero-subhead">I am a Vancouver-born generalist: bassist, songwriter, debugger, QA-minded fixer, and person you call when your workflow is chaos and you still need to ship.</p>
            <div class="hero-cta-group">
                <a href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>" class="pixel-button hero-primary-cta">Start with Gastown Simulator</a>
                <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button hero-secondary-cta">Select Side Projects</a>
                <a href="<?php echo esc_url(home_url('/bio/')); ?>" class="pixel-button hero-bio-button">Read full bio</a>
            </div>
        </section>

        <p class="arcade-subtext">Insert coin to explore</p>
    </div>

    <section class="selected-work crt-block" aria-labelledby="selected-work-title">
        <h2 id="selected-work-title" class="pixel-font">What I build / selected work</h2>
        <p class="selected-work__intro">A few projects that show how I blend creative internet energy with useful technical execution.</p>
        <div class="selected-work__grid">
            <article class="selected-work__card">
                <h3 class="pixel-font">Gastown Simulator</h3>
                <p>Flagship first-person Vancouver prototype: live worldbuilding, rapid iteration, and weird atmospheric details.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>">Enter Gastown</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Track Analyzer</h3>
                <p>Upload an MP3 and get practical AI mix feedback fast. Built for musicians who want signal, not fluff.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>">Analyze a Track</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Lousy Outages</h3>
                <p>Retro terminal-style outage tracking with alerting, incident clarity, and public utility for modern service chaos.</p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>">View Outages</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">AI Film Club shorts</h3>
                <p>Short-form AI films made for BC + AI Film Club prompt challenges: weird, cinematic, and production-minded.</p>
                <a class="pixel-button" href="https://www.youtube.com/watch?v=FrjuKGj91Pw" target="_blank" rel="noopener noreferrer">Watch the short</a>
            </article>
        </div>
    </section>

    <section class="work-with-suzy-home crt-block" aria-labelledby="work-with-suzy-title">
        <p class="work-with-suzy-home__eyebrow pixel-font">Established builder • available for the right paid side quest</p>
        <h2 id="work-with-suzy-title" class="pixel-font">Select Side Projects</h2>
        <p>I already have a career. I also take on a limited number of freelance collaborations, one-off debugging sessions, QA/automation support, and creative-technical builds for people who need sharp execution.</p>
        <ul class="work-with-suzy-home__list">
            <li>One-off debugging and untangling weird production bugs</li>
            <li>QA strategy, automation setup, and release confidence checks</li>
            <li>Workflow cleanup and practical automation for lean teams</li>
            <li>Advisory + build support for AI-assisted prototypes that need to ship</li>
            <li>WordPress and custom site improvements without killing your vibe</li>
            <li>Technical translation between operators, creatives, and developers</li>
        </ul>
        <div class="work-with-suzy-home__actions">
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button">Collaborate With Suzy</a>
            <a href="mailto:suzyeaston@icloud.com?subject=Book%20a%20Side%20Quest%20%E2%80%94%20%5Bproject%5D" class="pixel-button">Book a Side Quest</a>
        </div>
    </section>

    <section class="music-world crt-block" aria-labelledby="music-world-title">
        <h2 id="music-world-title" class="pixel-font">Music / media / world</h2>
        <p>This is the creative universe behind the builds: songs, podcast chats, film experiments, and social dispatches.</p>
        <div class="music-world__links">
            <a href="https://suzyeaston.bandcamp.com" class="pixel-button" target="_blank" rel="noopener noreferrer">Bandcamp</a>
            <a href="<?php echo esc_url(home_url('/podcast/')); ?>" class="pixel-button">Easy Living podcast</a>
            <a href="https://instagram.com/officialsuzyeaston" class="pixel-button" target="_blank" rel="noopener noreferrer">Instagram</a>
            <a href="https://www.youtube.com/@suzyeaston" class="pixel-button" target="_blank" rel="noopener noreferrer">YouTube</a>
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
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>">Select Side Projects</a>
            <a href="<?php echo esc_url(home_url('/asmr-lab/')); ?>">ASMR Lab</a>
            <a href="<?php echo esc_url(home_url('/riff-generator/')); ?>">Riff Generator</a>
            <a href="<?php echo esc_url(home_url('/coffee-for-builders/')); ?>">Coffee for Builders</a>
            <a href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer">Build logs on GitHub</a>
            <a href="https://buymeacoffee.com/wi0amge" target="_blank" rel="noopener noreferrer">Buy me a coffee</a>
        </div>
    </section>
</main>

<?php get_footer(); ?>
