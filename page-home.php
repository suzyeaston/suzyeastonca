<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-creative-system">

    <section class="home-title-screen" aria-labelledby="home-hero-title">
        <div class="home-title-screen__marquee pixel-font" aria-label="Site themes">
            <span><?php echo esc_html( 'MUSIC // MACHINES // VANCOUVER' ); ?></span>
            <span><?php echo esc_html( 'OUTAGES // RECORDS // GASTOWN' ); ?></span>
            <span><?php echo esc_html( 'PUBLIC INTERNET // PRIVATE STATIC' ); ?></span>
            <span><?php echo esc_html( 'AI // AUDIO // CIVIC GHOSTS' ); ?></span>
        </div>

        <div class="home-title-screen__grid">
            <div class="home-title-screen__intro">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'music // machines // vancouver' ); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline home-title-screen__title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                <p class="home-title-screen__identity"><?php echo esc_html( 'AI strategist. Musician. Creative technologist.' ); ?></p>
                <p class="home-title-screen__copy"><?php echo esc_html( 'Building small public systems for outages, songs, civic data, ghosts in the browser, and whatever starts talking back.' ); ?></p>
                <div class="home-cta-row hero-cta-group home-title-screen__actions">
                    <a href="#public-systems" class="pixel-button hero-primary-cta"><?php echo esc_html( 'Enter Site' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>" class="pixel-button pixel-button--secondary"><?php echo esc_html( 'Check Lousy Outages' ); ?></a>
                </div>
            </div>

            <?php get_template_part( 'parts/lousy-outages-teaser' ); ?>
        </div>
    </section>

    <section class="home-status-strip" aria-label="Site status">
        <p><span><?php echo esc_html( 'FROM:' ); ?></span> <?php echo esc_html( 'Vancouver' ); ?></p>
        <p><span><?php echo esc_html( 'MODE:' ); ?></span> <?php echo esc_html( 'music / machines / civic ghosts' ); ?></p>
        <p><span><?php echo esc_html( 'CURRENT SYSTEM:' ); ?></span> <?php echo esc_html( 'Lousy Outages' ); ?></p>
        <p><span><?php echo esc_html( 'SIDE QUEST:' ); ?></span> <?php echo esc_html( 'Canucks emotional support' ); ?></p>
    </section>

    <section id="public-systems" class="home-project-grid home-public-systems" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'public systems' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'things that escaped the lab' ); ?></h2>
        <div class="selected-work__grid home-mission-grid">
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'status weather' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3><p><?php echo esc_html( 'Provider pages, outage signals, and internet weather without the corporate shrug.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>"><?php echo esc_html( 'Check status' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'civic ghost world' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3><p><?php echo esc_html( 'A browser-built Gastown made from civic data, routes, buildings, and memory.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/page-gastown-sim/' ) ); ?>"><?php echo esc_html( 'Enter Gastown' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'audio notes' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Track Analyzer' ); ?></h3><p><?php echo esc_html( 'Upload a rough mix and get direct, useful notes back.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>"><?php echo esc_html( 'Analyze track' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'recording oracle' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'What Would Steve Do' ); ?></h3><p><?php echo esc_html( 'A blunt little recording prompt machine for mixes with too much nonsense.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/albini-qa/' ) ); ?>"><?php echo esc_html( 'Ask Steve' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'soft machine' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'ASMR Lab' ); ?></h3><p><?php echo esc_html( 'Tiny browser rituals for texture, sound, and controlled weirdness.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>"><?php echo esc_html( 'Enter lab' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'discography' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Music / Records' ); ?></h3><p><?php echo esc_html( 'Bands, records, touring, bass, songs, and the long tail of making noise.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>"><?php echo esc_html( 'Listen' ); ?></a></article>
        </div>
    </section>

    <section id="music" class="music-world home-unlocks" aria-labelledby="music-world-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'records / contact / next noise' ); ?></p>
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
