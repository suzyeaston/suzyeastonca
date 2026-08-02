<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-arcade-title-screen crt-block home-amp-cabinet" aria-labelledby="home-hero-title" data-arcade-hero>
        <div class="home-arcade-screen__backdrop" aria-label="<?php echo esc_attr( 'YVR live feed radio rack' ); ?>">
            <div class="home-yvr-rack">
                <div class="home-yvr-broadcaster" data-yvr-broadcaster aria-label="<?php echo esc_attr( 'YVR broadcaster — live feeds and radio' ); ?>">
                    <div class="home-yvr-broadcaster__mast">
                        <div class="home-yvr-broadcaster__avatar" data-broadcaster-face aria-hidden="true">
                            <div class="home-yvr-broadcaster__avatar-frame home-yvr-broadcaster__pigeon">
                                <div class="home-yvr-broadcaster__pigeon-tail"></div>
                                <div class="home-yvr-broadcaster__pigeon-body"></div>
                                <div class="home-yvr-broadcaster__pigeon-wing"></div>
                                <div class="home-yvr-broadcaster__pigeon-head">
                                    <span class="home-yvr-broadcaster__eye"></span>
                                    <div class="home-yvr-broadcaster__pigeon-beak">
                                        <span class="home-yvr-broadcaster__pigeon-beak-top"></span>
                                        <span class="home-yvr-broadcaster__mouth" data-broadcaster-mouth></span>
                                    </div>
                                </div>
                                <div class="home-yvr-broadcaster__pigeon-mic" aria-hidden="true"></div>
                            </div>
                            <p class="home-yvr-broadcaster__avatar-name pixel-font"><?php echo esc_html( 'DAVE' ); ?></p>
                        </div>
                        <div class="home-yvr-broadcaster__mast-info">
                            <div class="home-yvr-broadcaster__header">
                                <span class="home-yvr-broadcaster__badge pixel-font"><?php echo esc_html( 'YVR BCAST' ); ?></span>
                                <span class="home-yvr-broadcaster__on-air" role="status" aria-live="polite">
                                    <span class="home-yvr-broadcaster__led" aria-hidden="true"></span>
                                    <span class="home-yvr-broadcaster__channel pixel-font" data-broadcaster-channel><?php echo esc_html( 'STANDBY' ); ?></span>
                                </span>
                                <span class="home-yvr-broadcaster__freq pixel-font" data-broadcaster-freq>000.000</span>
                            </div>
                            <div class="home-yvr-broadcaster__bars" aria-hidden="true">
                                <span class="home-yvr-broadcaster__bar" data-broadcaster-bar></span>
                                <span class="home-yvr-broadcaster__bar" data-broadcaster-bar></span>
                                <span class="home-yvr-broadcaster__bar" data-broadcaster-bar></span>
                                <span class="home-yvr-broadcaster__bar" data-broadcaster-bar></span>
                                <span class="home-yvr-broadcaster__bar" data-broadcaster-bar></span>
                            </div>
                        </div>
                    </div>
                    <div class="home-yvr-broadcaster__crt" data-broadcaster-teleprompt>
                        <div class="home-yvr-broadcaster__map-wrap" data-broadcaster-map-wrap hidden>
                            <div class="home-yvr-broadcaster__map-head pixel-font">
                                <span class="home-yvr-broadcaster__map-kicker"><?php echo esc_html( 'greater vancouver // yvr bcast' ); ?></span>
                                <span class="home-yvr-broadcaster__map-legend" data-broadcaster-map-legend aria-hidden="true"></span>
                            </div>
                            <div class="home-yvr-broadcaster__map-stage" data-broadcaster-map-stage>
                                <svg class="home-yvr-broadcaster__map-svg" data-broadcaster-map-svg viewBox="0 0 360 200" preserveAspectRatio="xMidYMid meet" role="img" aria-label="<?php echo esc_attr( 'Greater Vancouver broadcaster map' ); ?>"></svg>
                                <div class="home-yvr-broadcaster__map-tooltip pixel-font" data-broadcaster-map-tooltip hidden></div>
                                <div class="home-yvr-broadcaster__map-scan" aria-hidden="true"></div>
                            </div>
                        </div>
                        <p class="home-yvr-teleprompt__script" data-broadcaster-script><?php echo esc_html( 'Tap a channel. Dave reads the feed — words light up.' ); ?></p>
                        <ul class="home-yvr-teleprompt__meta" data-broadcaster-meta hidden></ul>
                        <div class="home-yvr-broadcaster__crt-scan" aria-hidden="true"></div>
                    </div>
                    <div class="home-yvr-broadcaster__feeds">
                        <div class="home-yvr-broadcaster__channels">
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="translink" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'SkyTrain' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'TransLink alerts' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="drivers" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'DriveBC' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Road incidents' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="ferries" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'BC Ferries' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Sailings & fill' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="weather" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'Weather' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'EC warnings' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="wildfire" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'Wildfire' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Coastal status' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="air" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'Air quality' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Metro AQHI' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch home-yvr-broadcaster__ch--radio" data-broadcaster-channel-btn="cknw" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'CKNW 980' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Live radio' ); ?></span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="home-yvr-broadcaster__btn home-yvr-broadcaster__stop pixel-font" data-broadcaster-stop aria-label="<?php echo esc_attr( 'Stop audio' ); ?>">
                        <span class="home-yvr-broadcaster__stop-label"><?php echo esc_html( 'STOP' ); ?></span>
                        <span class="home-yvr-broadcaster__stop-hint"><?php echo esc_html( 'silence dave' ); ?></span>
                    </button>
                    <audio data-broadcaster-audio preload="none" crossorigin="anonymous"></audio>
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
