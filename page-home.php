<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-yvr-radar-deck" aria-labelledby="home-hero-title" data-arcade-hero>
        <header class="home-yvr-radar-deck__mast">
            <p class="home-yvr-radar-deck__kicker pixel-font"><?php echo esc_html( 'founder // principal technologist' ); ?></p>
            <h1 id="home-hero-title" class="home-yvr-radar-deck__title pixel-font"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
        </header>

        <div class="home-yvr-radar-deck__radar-unit">
            <div class="home-yvr-radar-deck__unit-head pixel-font">
                <span class="home-yvr-radar-deck__unit-badge"><?php echo esc_html( 'YVR RADAR' ); ?></span>
                <span class="home-yvr-radar-deck__unit-status"><?php echo esc_html( 'live greater vancouver' ); ?></span>
            </div>
            <div class="home-yvr-radar-deck__bezel">
                <div class="home-yvr-radar-deck__crt-ring">
                    <div class="home-yvr-radar-deck__map-wrap">
                        <div class="home-yvr-radar-deck__map" data-home-hero-map></div>
                    </div>
                    <div class="home-yvr-radar-deck__grid" aria-hidden="true"></div>
                    <div class="home-yvr-radar-deck__sweep" aria-hidden="true"></div>
                    <div class="home-yvr-radar-deck__scan" aria-hidden="true"></div>
                    <p class="home-yvr-radar-deck__ring-label pixel-font" data-yvr-ring-label><?php echo esc_html( 'drag · tap pins' ); ?></p>
                    <div class="home-yvr-radar-deck__wander pixel-font" data-yvr-wander-hint hidden>
                        <p class="home-yvr-radar-deck__wander-kicker"><?php echo esc_html( 'you wandered in.' ); ?></p>
                        <p class="home-yvr-radar-deck__wander-body"><?php echo esc_html( 'drag the scope. tap a pin — bulletin text plus matched live scanner.' ); ?></p>
                        <button type="button" class="home-yvr-radar-deck__wander-dismiss pixel-font" data-yvr-wander-dismiss><?php echo esc_html( 'got it' ); ?></button>
                    </div>
                </div>
                <div class="home-yvr-radar-deck__knobs" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
            </div>
            <p class="home-yvr-radar-deck__hint pixel-font"><?php echo esc_html( "pins = channels — wildfire, skytrain, marine scan, liveatc" ); ?></p>
        </div>

        <div class="home-yvr-radar-deck__console">
            <div class="home-yvr-radar-deck__rack">
                <div class="home-yvr-rack">
                <div class="home-yvr-broadcaster" data-yvr-broadcaster tabindex="-1" aria-label="<?php echo esc_attr( 'YVR broadcaster — live feeds and radio' ); ?>">
                    <div class="home-yvr-broadcaster__mast">
                        <div class="home-yvr-broadcaster__avatar" data-broadcaster-face data-broadcaster-dave-cycle title="<?php echo esc_attr( 'Tap Dave to cycle ambient beds' ); ?>" aria-label="<?php echo esc_attr( 'Dave — tap to cycle ambient sound' ); ?>">
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
                        <p class="home-yvr-broadcaster__attribution pixel-font" data-broadcaster-attribution hidden></p>
                        <p class="home-yvr-teleprompt__script" data-broadcaster-script><?php echo esc_html( 'tap a pin — bulletin lands here. PLAY for live audio.' ); ?></p>
                        <ul class="home-yvr-teleprompt__meta" data-broadcaster-meta hidden></ul>
                        <div class="home-yvr-broadcaster__crt-scan" aria-hidden="true"></div>
                    </div>
                    <?php get_template_part( 'parts/home-yvr-channel-buttons' ); ?>
                    <div class="home-yvr-broadcaster__transport">
                        <button type="button" class="home-yvr-broadcaster__btn home-yvr-broadcaster__play pixel-font" data-broadcaster-play aria-pressed="false" aria-label="<?php echo esc_attr( 'Play or pause live audio' ); ?>">
                            <span class="home-yvr-broadcaster__play-label" data-broadcaster-play-label><?php echo esc_html( 'PLAY' ); ?></span>
                            <span class="home-yvr-broadcaster__play-hint" data-broadcaster-play-hint><?php echo esc_html( 'start live audio' ); ?></span>
                        </button>
                        <button type="button" class="home-yvr-broadcaster__btn home-yvr-broadcaster__stop pixel-font" data-broadcaster-stop aria-label="<?php echo esc_attr( 'Stop audio' ); ?>">
                            <span class="home-yvr-broadcaster__stop-label"><?php echo esc_html( 'STOP' ); ?></span>
                            <span class="home-yvr-broadcaster__stop-hint"><?php echo esc_html( 'silence deck' ); ?></span>
                        </button>
                        <div class="home-yvr-broadcaster__speed pixel-font" aria-label="<?php echo esc_attr( 'Playback speed' ); ?>">
                            <span class="home-yvr-broadcaster__speed-label"><?php echo esc_html( 'speed' ); ?></span>
                            <button type="button" class="home-yvr-broadcaster__speed-btn" data-broadcaster-speed="0.75" aria-label="<?php echo esc_attr( 'Slower' ); ?>">−</button>
                            <span class="home-yvr-broadcaster__speed-readout" data-broadcaster-speed-label>1×</span>
                            <button type="button" class="home-yvr-broadcaster__speed-btn" data-broadcaster-speed="1" aria-label="<?php echo esc_attr( 'Normal speed' ); ?>">1×</button>
                            <button type="button" class="home-yvr-broadcaster__speed-btn" data-broadcaster-speed="1.25" aria-label="<?php echo esc_attr( 'Faster' ); ?>">+</button>
                            <button type="button" class="home-yvr-broadcaster__speed-btn" data-broadcaster-speed="1.5" aria-label="<?php echo esc_attr( 'Fast' ); ?>">++</button>
                        </div>
                    </div>
                    <audio class="home-yvr-broadcaster__native" data-broadcaster-audio controls preload="none" playsinline webkit-playsinline controlslist="nodownload noplaybackrate"></audio>
                </div>
                </div>
            </div>
            <div class="home-yvr-radar-deck__cta">
                <p class="home-yvr-radar-deck__subtitle pixel-font"><?php echo esc_html( 'AI strategy // systems integration // creative technology' ); ?></p>
                <p class="home-yvr-radar-deck__positioning"><?php echo esc_html( 'Punk bassist brain. Builds infra, creative tech, browser tools and applications that are practical and cool.' ); ?></p>
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
