<?php
/* Template Name: Homepage */
get_header();
get_template_part( 'parts/home-yvr-ascii-art' );
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-arcade-title-screen crt-block home-amp-cabinet" aria-labelledby="home-hero-title" data-arcade-hero>
        <div class="home-arcade-screen__backdrop" role="img" aria-label="<?php echo esc_attr( 'ASCII art of Vancouver downtown skyline and harbour' ); ?>">
            <pre class="home-yvr-ascii"><?php echo esc_html( home_yvr_ascii_art() ); ?></pre>
            <div class="home-amp-stack">
                <div class="home-city-scanner" data-city-scanner aria-label="<?php echo esc_attr( 'YVR live band scanner' ); ?>">
                    <div class="home-city-scanner__header">
                        <span class="home-city-scanner__badge pixel-font"><?php echo esc_html( 'YVR SCAN' ); ?></span>
                        <span class="home-city-scanner__freq pixel-font" data-scanner-freq>154.220</span>
                    </div>
                    <div class="home-city-scanner__display">
                        <span class="home-city-scanner__channel pixel-font" data-scanner-channel><?php echo esc_html( 'BC FIRE DISP' ); ?></span>
                        <p class="home-city-scanner__caption" data-scanner-caption><?php echo esc_html( 'Live Kamloops Fire dispatch. Metro Vancouver police, SkyTrain & VFRS are encrypted. Hit UNMUTE for live dispatch audio.' ); ?></p>
                    </div>
                    <div class="home-city-scanner__bars" aria-hidden="true">
                        <span class="home-city-scanner__bar" data-scanner-bar></span>
                        <span class="home-city-scanner__bar" data-scanner-bar></span>
                        <span class="home-city-scanner__bar" data-scanner-bar></span>
                        <span class="home-city-scanner__bar" data-scanner-bar></span>
                        <span class="home-city-scanner__bar" data-scanner-bar></span>
                    </div>
                    <div class="home-city-scanner__controls">
                        <button type="button" class="home-city-scanner__btn home-city-scanner__scan pixel-font" data-scanner-scan><?php echo esc_html( 'SCAN' ); ?></button>
                        <button type="button" class="home-city-scanner__btn home-city-scanner__mute pixel-font" data-scanner-mute aria-pressed="true"><?php echo esc_html( 'UNMUTE' ); ?></button>
                    </div>
                    <audio data-scanner-audio preload="none" crossorigin="anonymous"></audio>
                </div>
            </div>
        </div>
        <div class="home-arcade-screen__panel">
            <p class="home-arcade-kicker pixel-font"><?php echo esc_html( 'founder // principal technologist' ); ?></p>
            <h1 id="home-hero-title" class="home-arcade-title pixel-font"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
            <p class="home-arcade-subtitle pixel-font"><?php echo esc_html( 'AI strategy // systems integration // creative technology' ); ?></p>
            <p class="home-arcade-positioning"><?php echo esc_html( 'Punk bassist brain. Builds infra, creative tech, browser tools and applications that are practical and cool.' ); ?></p>
            <div class="home-amp-console">
                <div class="home-title-screen-prompt pixel-font" role="status" aria-live="polite" data-arcade-status><?php echo esc_html( 'INSERT COIN' ); ?></div>
                <button type="button" class="pixel-button home-press-start" data-home-start data-start-label="PRESS START // VIEW WORK"><?php echo esc_html( 'PRESS START // VIEW WORK' ); ?></button>
            </div>
            <ul class="home-title-screen-meta pixel-font" aria-label="<?php echo esc_attr( 'Scene tags' ); ?>">
                <li><?php echo esc_html( 'Vancouver' ); ?></li>
                <li><?php echo esc_html( 'independent practice' ); ?></li>
                <li><?php echo esc_html( 'civic tech' ); ?></li>
                <li><?php echo esc_html( 'west coast' ); ?></li>
            </ul>
        </div>
    </section>

    <?php get_template_part( 'parts/lousy-outages-teaser' ); ?>

    <section id="mission-select" class="home-project-grid home-mission-select crt-block" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'LEVEL SELECT' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'CHOOSE YOUR SYSTEM' ); ?></h2>
        <div class="selected-work__grid home-mission-grid">
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'hockey arcade' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Pacific Power Play' ); ?></h3><p><?php echo esc_html( 'Choose your line, drop the puck, and survive the rain city static in a Vancouver hockey cabinet.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/pacific-power-play/' ) ); ?>"><?php echo esc_html( 'Play game' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'status weather' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3><p><?php echo esc_html( 'Independent outage intelligence for AI, cloud and creative tools, translated from status-page language into something humans can use.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>"><?php echo esc_html( 'Check status' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'civic arcade world' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3><p><?php echo esc_html( 'A playable Vancouver corridor built from civic data, route logic, street mood, and arcade-map obsession.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/gastown-sim/' ) ); ?>"><?php echo esc_html( 'Walk Gastown' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'audio notes' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Track Analyzer' ); ?></h3><p><?php echo esc_html( 'Upload an MP3 and get clear notes on feel, lyrics, structure, and what might actually help the track.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>"><?php echo esc_html( 'Analyze track' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'early music tool' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Loop Lab' ); ?></h3><p><?php echo esc_html( 'A browser tape deck for playing first, looping fast, and stacking little mistakes on purpose.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/loop-lab/' ) ); ?>"><?php echo esc_html( 'Make noise' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'recording oracle' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'What Would Steve Do' ); ?></h3><p><?php echo esc_html( 'A quote-backed recording prompt machine for mixes with too much nonsense and not enough nerve.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/albini-qa/' ) ); ?>"><?php echo esc_html( 'Ask Steve' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'soft machine' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'ASMR Lab' ); ?></h3><p><?php echo esc_html( 'Procedural sound and visual rituals, currently mutating into a stranger browser lab.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>"><?php echo esc_html( 'Enter lab' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'discography' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Music / Records' ); ?></h3><p><?php echo esc_html( 'Solo releases, past bands, touring history, and the music side of the machine.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>"><?php echo esc_html( 'Listen' ); ?></a></article>
        </div>
    </section>

    <section id="music" class="music-world home-unlocks crt-block" aria-labelledby="music-world-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'UNLOCKS' ); ?></p>
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html( 'Music / tools / contact' ); ?></h2>
        <p><?php echo esc_html( 'The records, the builds, and the places where those two things start interfering with each other.' ); ?></p>
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
