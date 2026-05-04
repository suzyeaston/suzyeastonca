<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout">
    <section class="home-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="hero-grid">
            <div class="hero-main">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html('Vancouver, BC · IT operations · QA automation · music tech'); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline"><?php echo esc_html('I build tools for the stuff that breaks.'); ?></h1>
                <p class="hero-copy"><?php echo esc_html('I’m Suzy Easton. I work in IT operations and QA automation, and I build WordPress plugins, outage tools, and AI-assisted music projects on the side. I like projects where the logs are messy, the problem is real, and the fix needs to be clear.'); ?></p>
                <div class="home-cta-row hero-cta-group">
                    <a href="<?php echo esc_url(home_url('/lousy-outages/')); ?>" class="pixel-button hero-primary-cta"><?php echo esc_html('See Lousy Outages'); ?></a>
                    <a href="<?php echo esc_url(home_url('/resume/')); ?>" class="pixel-button hero-secondary-cta"><?php echo esc_html('Resume'); ?></a>
                    <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="pixel-button hero-secondary-cta"><?php echo esc_html('Projects'); ?></a>
                </div>
            </div>
            <aside class="hero-side hero-photo-card" aria-label="Hero portrait">
                <div class="hero-photo-frame">
                    <a class="hero-photo-link hero-photo-link-block" href="<?php echo esc_url(home_url('/bio/')); ?>">
                        <img class="hero-photo" src="<?php echo esc_url(home_url('/wp-content/uploads/2026/01/IMG_9003.jpg')); ?>" alt="<?php echo esc_attr('Suzy Easton smiling with a guitar'); ?>" loading="lazy" decoding="async">
                    </a>
                </div>
                <p class="hero-photo-caption pixel-font"><a class="hero-photo-link" href="<?php echo esc_url(home_url('/bio/')); ?>"><?php echo esc_html('Vancouver, BC / Read bio →'); ?></a></p>
            </aside>
        </div>
    </section>

    <section class="home-featured-build home-lousy-outages crt-block" aria-labelledby="home-featured-title">
        <p class="pixel-font home-section-kicker"><?php echo esc_html('Featured project'); ?></p>
        <h2 id="home-featured-title" class="pixel-font"><?php echo esc_html('Lousy Outages'); ?></h2>
        <p class="home-featured-subtitle"><?php echo esc_html('A WordPress plugin for tracking service problems before they become everybody’s problem.'); ?></p>
        <p><?php echo esc_html('I started Lousy Outages because official status pages are useful, but they are not always fast enough. The plugin tracks provider updates, community reports, external signals, and lightweight checks. It is built to show possible issues clearly without treating every report like confirmed fact.'); ?></p>
        <ul class="home-feature-list">
            <li><?php echo esc_html('Monitors provider status feeds and incidents'); ?></li>
            <li><?php echo esc_html('Lets subscribers choose the services they care about'); ?></li>
            <li><?php echo esc_html('Collects community reports without overclaiming'); ?></li>
            <li><?php echo esc_html('Combines official updates, reports, external signals, and canary checks'); ?></li>
            <li><?php echo esc_html('Runs as a standalone WordPress plugin with admin screens and REST endpoints'); ?></li>
        </ul>
        <div class="home-badge-list" aria-label="Lousy Outages badges">
            <span>WordPress plugin</span><span>Outage monitoring</span><span>Early warning</span><span>REST API</span><span>Built in Vancouver</span>
        </div>
        <div class="home-cta-row">
            <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>"><?php echo esc_html('Open Lousy Outages'); ?></a>
            <a class="pixel-button" href="<?php echo esc_url('https://github.com/suzyeaston/suzyeastonca'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('View GitHub'); ?></a>
        </div>
    </section>

    <section class="home-project-grid crt-block" aria-labelledby="selected-projects-title">
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html('Selected Projects'); ?></h2>
        <div class="selected-work__grid">
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Lousy Outages'); ?></h3>
                <p><?php echo esc_html('Outage monitoring, alert preferences, and early warning signals in a WordPress plugin I’m actively building.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>"><?php echo esc_html('Open'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Gastown Simulator'); ?></h3>
                <p><?php echo esc_html('A browser-based Vancouver prototype with maps, routes, civic data, and game-style navigation.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>"><?php echo esc_html('Explore'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Track Analyzer'); ?></h3>
                <p><?php echo esc_html('An AI-assisted music feedback tool for rough mixes and songwriting notes.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>"><?php echo esc_html('Try it'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('AI/Audio Experiments'); ?></h3>
                <p><?php echo esc_html('Small experiments with generated sound, web visuals, and AI-assisted storytelling.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/asmr-lab/')); ?>"><?php echo esc_html('Read more'); ?></a>
            </article>
        </div>
    </section>

    <section class="music-world crt-block" aria-labelledby="music-world-title">
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html('Music'); ?></h2>
        <p><?php echo esc_html('Music is still a big part of how I work. I spent years playing bass, recording, touring, and learning how to listen closely. That shows up in the technical work too: debugging, testing, timing, collaboration, and noticing when something is off.'); ?></p>
        <p class="home-section-legend-links" aria-label="Music and media links">
            <a href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer">Bandcamp</a>
            <span aria-hidden="true">//</span>
            <a href="https://soundcloud.com/suzyeaston" target="_blank" rel="noopener noreferrer">SoundCloud</a>
            <span aria-hidden="true">//</span>
            <a href="https://www.youtube.com/@suzyeaston" target="_blank" rel="noopener noreferrer">YouTube</a>
            <span aria-hidden="true">//</span>
            <a href="<?php echo esc_url(home_url('/bio/')); ?>">Bio</a>
        </p>
    </section>

    <section class="collab-invite-home crt-block" aria-labelledby="work-pain-title">
        <h2 id="work-pain-title" class="pixel-font"><?php echo esc_html('What I’m good at'); ?></h2>
        <p><?php echo esc_html('I’m useful when a problem crosses support, QA, operations, and development. I can investigate what is going wrong, write tests, automate repetitive work, clean up handoffs, and turn a rough idea into a working demo.'); ?></p>
        <p><?php echo esc_html('I’m open to QA automation, IT/cloud operations, WordPress/plugin work, AI-assisted prototyping, and practical automation projects.'); ?></p>
        <div class="home-cta-row collab-invite-home__actions">
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button"><?php echo esc_html('Work with me'); ?></a>
            <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="pixel-button"><?php echo esc_html('Contact'); ?></a>
            <a href="<?php echo esc_url(home_url('/resume/')); ?>" class="pixel-button"><?php echo esc_html('Resume'); ?></a>
        </div>
    </section>
</main>

<?php get_footer(); ?>
