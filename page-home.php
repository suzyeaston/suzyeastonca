<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-hero home-arcade-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="home-arcade-hero__cabinet" aria-label="Suzy Easton Pacific Static arcade screen">
            <div class="home-arcade-hero__marquee pixel-font" aria-hidden="true">
                <span><?php echo esc_html( 'PACIFIC STATIC' ); ?></span>
                <span><?php echo esc_html( '1UP: SUZY' ); ?></span>
                <span><?php echo esc_html( 'VANCOUVER' ); ?></span>
            </div>

            <div class="hero-grid home-arcade-hero__grid">
                <div class="hero-main home-arcade-hero__intro">
                    <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'music // machines // vancouver' ); ?></p>
                    <h1 id="home-hero-title" class="hero-core-headline home-arcade-title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                    <p class="home-arcade-subtitle"><?php echo esc_html( 'AI strategist. Musician. Creative technologist.' ); ?></p>
                    <p class="hero-copy home-arcade-copy"><?php echo esc_html( 'Songs, systems, outage static, civic ghosts, AI experiments, and little internet machines that start living on their own.' ); ?></p>
                    <div class="home-cta-row hero-cta-group home-arcade-start-row">
                        <a href="#mission-select" class="pixel-button hero-primary-cta home-arcade-start" data-arcade-start><?php echo esc_html( 'Start Mission' ); ?></a>
                        <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>" class="pixel-button pixel-button--secondary"><?php echo esc_html( 'Check Lousy Outages' ); ?></a>
                    </div>
                </div>

                <aside class="hero-side home-arcade-hero__screen" aria-label="Pacific Static arcade monitor">
                    <div class="hero-game-stage home-arcade-game" aria-label="Pacific Static mini arcade game. Press Start Mission, then use WASD or arrow keys to move, Space to fire, and Escape to quit." data-arcade-stage>
                        <p class="hero-game-stage__header pixel-font"><?php echo esc_html( 'PACIFIC STATIC' ); ?></p>
                        <div class="hero-game-stage__screen" role="img" aria-label="A green CRT arcade screen with stars, a small player ship, and outage alert blips.">
                            <p class="hero-game-stage__idle pixel-font"><?php echo wp_kses_post( 'OUTAGE BLIP SWEEP<br>WASD move // Space fire // Esc quit<br>No sleep till deploy' ); ?></p>
                            <div class="home-static-sprites" aria-hidden="true">
                                <span class="home-static-sprites__ship"></span>
                                <span class="home-static-sprites__enemy home-static-sprites__enemy--one"></span>
                                <span class="home-static-sprites__enemy home-static-sprites__enemy--two"></span>
                                <span class="home-static-sprites__reticle"></span>
                            </div>
                        </div>
                        <p class="hero-game-stage__mobile-note pixel-font"><?php echo esc_html( 'Tap Start Mission on a keyboard screen to play.' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <?php get_template_part( 'parts/lousy-outages-teaser' ); ?>

    <section id="mission-select" class="home-project-grid home-mission-select crt-block" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'SELECT SYSTEM' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'SELECT SYSTEM' ); ?></h2>
        <div class="selected-work__grid home-mission-grid">
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'status weather' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3><p><?php echo esc_html( 'Provider pages, outage signals, and internet weather without the corporate shrug.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>"><?php echo esc_html( 'Check status' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'civic ghost world' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3><p><?php echo esc_html( 'A browser-built Gastown made from civic data, routes, buildings, and memory.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/page-gastown-sim/' ) ); ?>"><?php echo esc_html( 'Enter Gastown' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'audio notes' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Track Analyzer' ); ?></h3><p><?php echo esc_html( 'Upload a rough mix and get direct, useful notes back.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>"><?php echo esc_html( 'Analyze track' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'recording oracle' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'What Would Steve Do' ); ?></h3><p><?php echo esc_html( 'A blunt little recording prompt machine for mixes with too much nonsense.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/albini-qa/' ) ); ?>"><?php echo esc_html( 'Ask Steve' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'soft machine' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'ASMR Lab' ); ?></h3><p><?php echo esc_html( 'Tiny browser rituals for texture, sound, and controlled weirdness.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>"><?php echo esc_html( 'Enter lab' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'discography' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Music / Records' ); ?></h3><p><?php echo esc_html( 'Songs, bands, records, touring, bass, and the handmade internet paths around them.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>"><?php echo esc_html( 'Listen' ); ?></a></article>
        </div>
    </section>

    <section id="music" class="music-world home-unlocks crt-block" aria-labelledby="music-world-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'music / contact' ); ?></p>
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html( 'Music, bio, contact' ); ?></h2>
        <p><?php echo esc_html( 'Songs, records, bands, touring, bass, audio experiments, and the handmade internet paths around them.' ); ?></p>
        <div class="home-cta-row">
            <a class="pixel-button" href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html( 'Bandcamp' ); ?></a>
            <a class="pixel-button" href="<?php echo esc_url( home_url( '/bio/' ) ); ?>"><?php echo esc_html( 'Bio' ); ?></a>
            <button class="pixel-button" type="button" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'Contact' ); ?></button>
        </div>
    </section>

    <section class="home-territory" aria-label="Territory acknowledgement">
        <p><?php echo esc_html( 'This site is made in Vancouver, on the shared, unceded, ancestral territories of the xʷməθkʷəy̓əm (Musqueam), Sḵwx̱wú7mesh Úxwumixw (Squamish Nation), and səlilwətaɬ (Tsleil-Waututh) Nations.' ); ?></p>
    </section>

</main>

<?php get_footer(); ?>
