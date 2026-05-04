<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout">
    <section class="home-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="hero-grid">
            <div class="hero-main">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html('Vancouver, BC · QA automation · IT ops · plugins · music tech'); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline"><?php echo esc_html('Useful tools for noisy systems, strange ideas, and the occasional guitar amp.'); ?></h1>
                <p class="hero-copy"><?php echo esc_html('I’m Suzy Easton. I build things that make broken systems easier to see: outage dashboards, QA automation, WordPress plugins, AI-assisted music tools, and Vancouver-flavoured web experiments.'); ?></p>
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
        <p class="pixel-font home-section-kicker"><?php echo esc_html('Featured build'); ?></p>
        <h2 id="home-featured-title" class="pixel-font"><?php echo esc_html('Lousy Outages'); ?></h2>
        <p class="home-featured-subtitle"><?php echo esc_html('Because waiting for a vendor status page to admit reality is not much of a strategy.'); ?></p>
        <p><?php echo esc_html('Lousy Outages started as a loud little status board and is turning into a standalone WordPress plugin for outage monitoring. It watches provider status feeds, community reports, external signals, and lightweight canary checks, then shows cautious early warnings without pretending every rumour is a confirmed outage.'); ?></p>
        <ul class="home-feature-list">
            <li><?php echo esc_html('Provider status monitoring without the corporate fog machine'); ?></li>
            <li><?php echo esc_html('Subscriber alerts by provider, not inbox confetti'); ?></li>
            <li><?php echo esc_html('Community reports labelled as unconfirmed, because lawsuits are boring'); ?></li>
            <li><?php echo esc_html('External + synthetic signals for early warning smoke'); ?></li>
            <li><?php echo esc_html('A real plugin package with admin screens, REST routes, and a ZIP build'); ?></li>
        </ul>
        <div class="home-badge-list" aria-label="Lousy Outages badges">
            <span>WordPress Plugin</span><span>Ops Radar</span><span>Early Warning</span><span>Built in Vancouver</span><span>Demo-ready</span>
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
                <p><?php echo esc_html('Outage monitoring, community signals, and vendor-accountability energy in plugin form.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>"><?php echo esc_html('Open'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Gastown Simulator'); ?></h3>
                <p><?php echo esc_html('A browser-based Vancouver prototype with maps, routes, civic data, and just enough chaos.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>"><?php echo esc_html('Explore'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Track Analyzer'); ?></h3>
                <p><?php echo esc_html('Upload a track, get AI-assisted feedback that is more useful than “sounds cool, bro.”'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>"><?php echo esc_html('Try it'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('AI/Audio Experiments'); ?></h3>
                <p><?php echo esc_html('Procedural sound, little film-club robots, ASMR weirdness, and other lab-table sparks.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/asmr-lab/')); ?>"><?php echo esc_html('Read more'); ?></a>
            </article>
        </div>
    </section>

    <section class="music-world crt-block" aria-labelledby="music-world-title">
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html('Music + Creative Tech'); ?></h2>
        <p><?php echo esc_html('Before I was debugging SaaS workflows, I was hauling bass gear into vans and recording loud songs. The music brain is still in the code: timing, feel, feedback loops, knowing when the whole thing is about to fall apart.'); ?></p>
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
        <h2 id="work-pain-title" class="pixel-font"><?php echo esc_html('I like messy systems.'); ?></h2>
        <p><?php echo esc_html('QA, IT ops, cloud tools, support escalations, WordPress plugins, AI experiments — I’m good where the docs are incomplete, the logs are noisy, and somebody needs to make the thing understandable.'); ?></p>
        <p><?php echo esc_html('Available for QA automation, IT/cloud ops, WordPress/plugin work, AI-assisted prototyping, and practical automation projects.'); ?></p>
        <div class="home-cta-row collab-invite-home__actions">
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button"><?php echo esc_html('Work with me'); ?></a>
            <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="pixel-button"><?php echo esc_html('Contact'); ?></a>
            <a href="<?php echo esc_url(home_url('/resume/')); ?>" class="pixel-button"><?php echo esc_html('Resume'); ?></a>
        </div>
    </section>
</main>

<?php get_footer(); ?>
