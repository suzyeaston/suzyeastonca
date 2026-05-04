<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout">
    <section class="home-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="hero-grid">
            <div class="hero-main">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html('Vancouver, BC • QA automation • IT operations • Creative AI'); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline"><?php echo esc_html('Creative technologist building outage intelligence, AI-assisted tools, and weird useful web things.'); ?></h1>
                <p class="hero-copy"><?php echo esc_html('I’m Suzy Easton, a Vancouver-based IT operations analyst, QA automation builder, musician, and WordPress/plugin tinkerer turning messy systems into useful products.'); ?></p>
                <div class="home-cta-row hero-cta-group">
                    <a href="<?php echo esc_url(home_url('/lousy-outages/')); ?>" class="pixel-button hero-primary-cta"><?php echo esc_html('View Lousy Outages'); ?></a>
                    <a href="<?php echo esc_url(home_url('/resume/')); ?>" class="pixel-button hero-secondary-cta"><?php echo esc_html('View Resume'); ?></a>
                </div>
                <p class="hero-collab-link"><a href="<?php echo esc_url(home_url('/projects/')); ?>"><?php echo esc_html('See projects →'); ?></a></p>
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
        <p class="pixel-font home-section-kicker"><?php echo esc_html('PRIMARY BUILD'); ?></p>
        <h2 id="home-featured-title" class="pixel-font"><?php echo esc_html('Featured Build: Lousy Outages'); ?></h2>
        <p class="home-featured-subtitle"><?php echo esc_html('WordPress-native outage intelligence for third-party services.'); ?></p>
        <p><?php echo esc_html('Lousy Outages tracks provider status, community reports, external signals, and lightweight synthetic checks to surface possible issues before they become everyone’s problem. It’s built as a standalone WordPress plugin with public REST endpoints, subscriber preferences, admin diagnostics, and demo-safe early-warning logic.'); ?></p>
        <ul class="home-feature-list">
            <li><?php echo esc_html('Status monitoring for cloud/SaaS providers'); ?></li>
            <li><?php echo esc_html('Subscriber preferences for provider-specific alerts'); ?></li>
            <li><?php echo esc_html('Community issue reporting with cautious/unconfirmed language'); ?></li>
            <li><?php echo esc_html('Fused signal engine combining official, community, external, and synthetic checks'); ?></li>
            <li><?php echo esc_html('Plugin-style admin dashboard and ZIP packaging'); ?></li>
        </ul>
        <div class="home-badge-list" aria-label="Lousy Outages badges">
            <span>WordPress Plugin</span><span>Outage Intelligence</span><span>Community Signals</span><span>QA/Ops Automation</span><span>Built in Vancouver</span>
        </div>
        <div class="home-cta-row">
            <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>"><?php echo esc_html('Open Lousy Outages'); ?></a>
            <a class="pixel-button" href="<?php echo esc_url('https://github.com/suzyeaston/suzyeastonca'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('View GitHub'); ?></a>
        </div>
    </section>

    <section class="home-project-grid crt-block" aria-labelledby="selected-projects-title">
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html('Selected Projects'); ?></h2>
        <p class="selected-work__intro"><?php echo esc_html('A compact view of the builds I use most in interviews and consulting conversations.'); ?></p>
        <div class="selected-work__grid">
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Lousy Outages'); ?></h3>
                <p><?php echo esc_html('A productizing plugin build for outage monitoring, alert preferences, and fused incident signals.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>"><?php echo esc_html('Open project'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Gastown Simulator'); ?></h3>
                <p><?php echo esc_html('A Vancouver browser-sim prototype blending civic data, world-building, and game-like UX.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>"><?php echo esc_html('Enter Gastown'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('Track Analyzer'); ?></h3>
                <p><?php echo esc_html('AI-assisted music feedback that helps creators get from vague friction to actionable mix changes.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>"><?php echo esc_html('Analyze a track'); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html('AI/Audio Experiments'); ?></h3>
                <p><?php echo esc_html('ASMR Lab and related creative-tech experiments in procedural sound, visuals, and AI-assisted prototyping.'); ?></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/asmr-lab/')); ?>"><?php echo esc_html('Explore experiments'); ?></a>
            </article>
        </div>
    </section>

    <section class="music-world crt-block" aria-labelledby="music-world-title">
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html('Music + Creative Tech'); ?></h2>
        <p><?php echo esc_html('I still build in public as a musician: songs, audio tools, and weird web narratives feed the same product instincts as my ops and QA work.'); ?></p>
        <p class="home-section-legend-links" aria-label="Music and media links">
            <a href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer">Bandcamp</a>
            <span aria-hidden="true">//</span>
            <a href="https://soundcloud.com/suzyeaston" target="_blank" rel="noopener noreferrer">SoundCloud</a>
            <span aria-hidden="true">//</span>
            <a href="https://www.youtube.com/@suzyeaston" target="_blank" rel="noopener noreferrer">YouTube</a>
            <span aria-hidden="true">//</span>
            <a href="<?php echo esc_url(home_url('/podcast/')); ?>">Podcast</a>
        </p>
    </section>

    <section class="collab-invite-home crt-block" aria-labelledby="work-pain-title">
        <h2 id="work-pain-title" class="pixel-font"><?php echo esc_html('Built from real operations pain'); ?></h2>
        <p><?php echo esc_html('My work sits where QA, IT operations, cloud tooling, automation, and creative product thinking overlap. I build tools that make noisy systems easier to understand.'); ?></p>
        <p><?php echo esc_html('I’m available for QA automation, cloud/IT operations support, WordPress/plugin engineering, AI-assisted development, and creative-tech collaborations.'); ?></p>
        <div class="home-cta-row collab-invite-home__actions">
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button"><?php echo esc_html('Work With Me'); ?></a>
            <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="pixel-button"><?php echo esc_html('Contact'); ?></a>
            <a href="<?php echo esc_url(home_url('/bio/')); ?>" class="pixel-button"><?php echo esc_html('Read Bio'); ?></a>
        </div>
    </section>
</main>

<?php get_footer(); ?>
