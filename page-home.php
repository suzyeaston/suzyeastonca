<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-hero home-arcade-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="home-arcade-hero__cabinet" aria-label="Suzy Easton arcade start screen">
            <div class="home-arcade-hero__marquee pixel-font" aria-hidden="true">
                <span><?php echo esc_html( '1UP: SUZY' ); ?></span>
                <span><?php echo esc_html( 'HIGH SCORE 1984' ); ?></span>
                <span><?php echo esc_html( 'VANCOUVER' ); ?></span>
            </div>

            <div class="hero-grid home-arcade-hero__grid">
                <div class="hero-main home-arcade-hero__intro">
                    <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'Vancouver · AI Strategist · Creative Technologist · Bass' ); ?></p>
                    <h1 id="home-hero-title" class="hero-core-headline home-arcade-title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                    <p class="home-arcade-subtitle pixel-font"><?php echo esc_html( 'Day: AI infrastructure / strategy. Night: bass, records, weird tools.' ); ?></p>
                    <p class="hero-copy home-arcade-copy"><?php echo esc_html( 'A playable personal operating system for AI builds, Vancouver experiments, loud bass, and useful glitches.' ); ?></p>
                    <div class="home-cta-row hero-cta-group home-arcade-start-row">
                        <a href="#mission-select" class="pixel-button hero-primary-cta home-arcade-start" data-arcade-start><?php echo esc_html( 'Start Mission' ); ?></a>
                    </div>
                </div>

                <aside class="hero-side home-arcade-hero__screen" aria-label="Pacific Static arcade panel">
                    <div class="hero-game-stage home-arcade-game" aria-label="Pacific Static mini arcade game. Press Start Mission, then use A and D or arrow keys to move and Space to fire." data-arcade-stage>
                        <p class="hero-game-stage__header pixel-font"><?php echo esc_html( 'PACIFIC STATIC // ORIGINAL MINI ARCADE' ); ?></p>
                        <div class="hero-game-stage__screen" role="img" aria-label="A green CRT arcade screen with stars, a small player ship, and incoming signal objects.">
                            <p class="hero-game-stage__idle pixel-font"><?php echo wp_kses_post( 'TAP START MISSION<br>Desktop unlocks playable mode.<br>A/D or arrows move // Space fires.' ); ?></p>
                            <div class="home-static-sprites" aria-hidden="true">
                                <span class="home-static-sprites__ship"></span>
                                <span class="home-static-sprites__enemy home-static-sprites__enemy--one"></span>
                                <span class="home-static-sprites__enemy home-static-sprites__enemy--two"></span>
                                <span class="home-static-sprites__reticle"></span>
                            </div>
                        </div>
                        <p class="hero-game-stage__mobile-note pixel-font"><?php echo esc_html( 'Mobile: attract mode only. Mission cards are fully tappable below.' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="home-terminal-strip home-operator-strip crt-block" aria-label="Identity readout">
        <div class="home-terminal-readout" role="presentation">
            <p><span class="home-terminal-key"><?php echo esc_html( 'OPERATOR' ); ?></span><span class="home-terminal-dots" aria-hidden="true"></span><span class="home-terminal-val"><?php echo esc_html( 'Suzy Easton' ); ?></span></p>
            <p><span class="home-terminal-key"><?php echo esc_html( 'DAY MODE' ); ?></span><span class="home-terminal-dots" aria-hidden="true"></span><span class="home-terminal-val"><?php echo esc_html( 'AI infrastructure · strategy · solutions engineering at Quercus IT' ); ?></span></p>
            <p><span class="home-terminal-key"><?php echo esc_html( 'NIGHT MODE' ); ?></span><span class="home-terminal-dots" aria-hidden="true"></span><span class="home-terminal-val"><?php echo esc_html( 'Bass · records · strange instruments · tools that should not work but do' ); ?></span></p>
        </div>
    </section>

    <section id="mission-select" class="home-project-grid home-mission-select crt-block" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( '// MISSION SELECT //' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'Choose your level' ); ?></h2>
        <div class="selected-work__grid home-mission-grid">
            <article class="home-project-card selected-work__card home-mission-card home-mission-card--boss">
                <p class="home-mission-card__label pixel-font"><?php echo esc_html( 'BOSS ALERT' ); ?></p>
                <h3 class="pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3>
                <p><?php echo esc_html( 'Official status pages lie. This one watches provider weirdness like a cranky radar screen.' ); ?></p>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>"><?php echo esc_html( 'Scan' ); ?></a>
            </article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'LEVEL 02' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3><p><?php echo esc_html( 'Vancouver in a browser: civic data, routes, buildings, and ghosts in the grid.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/page-gastown-sim/' ) ); ?>"><?php echo esc_html( 'Explore' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'LEVEL 03' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Track Analyzer' ); ?></h3><p><?php echo esc_html( 'Rough mix goes in. Practical notes come out. No fake studio mysticism.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>"><?php echo esc_html( 'Try it' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'LEVEL 04' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'What Would Steve Do' ); ?></h3><p><?php echo esc_html( 'Albini-ish prompts for when your mix needs fewer lies and better drums.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/albini-qa/' ) ); ?>"><?php echo esc_html( 'Ask' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'LEVEL 05' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'ASMR Lab' ); ?></h3><p><?php echo esc_html( 'Audio/visual experiments, soft chaos, and tiny browser rituals.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>"><?php echo esc_html( 'Enter' ); ?></a></article>
        </div>
    </section>

    <section id="music" class="music-world home-unlocks crt-block" aria-labelledby="music-world-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( '// UNLOCKS //' ); ?></p>
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html( 'Music, bio, contact' ); ?></h2>
        <p><?php echo esc_html( 'National touring bassist. Minto and The Smokes. Canada-wide, MuchMusic, recorded with Steve Albini in Chicago. Still releasing — somewhere between Sade, Grimes, and Metric.' ); ?></p>
        <div class="home-cta-row">
            <a class="pixel-button" href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html( 'Bandcamp' ); ?></a>
            <a class="pixel-button" href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>"><?php echo esc_html( 'Records' ); ?></a>
            <a class="pixel-button" href="<?php echo esc_url( home_url( '/bio/' ) ); ?>"><?php echo esc_html( 'Bio' ); ?></a>
            <button class="pixel-button" type="button" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'Contact' ); ?></button>
        </div>
    </section>

</main>

<?php get_footer(); ?>
