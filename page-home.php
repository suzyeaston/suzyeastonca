<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-orca-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="home-orca-stage">
            <span class="screen-reader-text"><?php echo esc_html( 'One retro arcade killer whale gliding beside North Shore mountains, CRT stars, and Burrard Inlet water.' ); ?></span>
            <div class="home-orca-sky" aria-hidden="true">
                <span class="home-orca-starname">SUZY EASTON</span>
            </div>
            <svg class="home-orca-north-shore" viewBox="0 0 1200 260" aria-hidden="true" focusable="false">
                <path class="home-orca-north-shore__back" d="M0 176 74 154 142 102 208 137 283 58 353 134 428 84 508 145 579 92 642 132 714 64 796 142 878 96 940 138 1014 72 1094 146 1200 110" />
                <path class="home-orca-north-shore__front" d="M0 205 96 174 176 146 253 166 334 112 420 174 498 139 585 178 668 132 753 176 846 126 930 172 1028 132 1114 180 1200 152" />
            </svg>
            <div class="home-orca-copy">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'music // ai strategy // creative technology' ); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline home-arcade-title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                <p class="hero-copy home-arcade-copy"><?php echo esc_html( 'I build practical AI workflows, music tools, outage dashboards, and digital systems for creative and technical teams.' ); ?></p>
                <div class="home-orca-actions home-cta-row hero-cta-group">
                    <a href="#mission-select" class="pixel-button hero-primary-cta"><?php echo esc_html( 'Enter the lab' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>" class="pixel-button pixel-button--secondary"><?php echo esc_html( 'Check Lousy Outages' ); ?></a>
                </div>
            </div>
            <div class="home-orca-art" aria-hidden="true">
                <div class="home-orca-mark" role="img" aria-label="<?php echo esc_attr( 'One retro arcade killer whale gliding through Burrard Inlet with CRT glow.' ); ?>">
                    <svg class="home-orca-sigil" viewBox="0 0 760 520" aria-hidden="true" focusable="false">
                        <defs>
                            <filter id="home-orca-crt-glow" x="-18%" y="-28%" width="136%" height="156%">
                                <feGaussianBlur stdDeviation="2.2" result="blur" />
                                <feColorMatrix in="blur" type="matrix" values="0 0 0 0 0.2  0 0 0 0 1  0 0 0 0 0.82  0 0 0 .52 0" result="cyanGlow" />
                                <feMerge><feMergeNode in="cyanGlow" /><feMergeNode in="SourceGraphic" /></feMerge>
                            </filter>
                            <linearGradient id="home-harbour-skyline" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0" stop-color="#030914" />
                                <stop offset=".5" stop-color="#07303a" />
                                <stop offset="1" stop-color="#02040c" />
                            </linearGradient>
                            <linearGradient id="home-harbour-mountain-glow" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0" stop-color="#17285a" stop-opacity=".9" />
                                <stop offset="1" stop-color="#050d1f" stop-opacity=".96" />
                            </linearGradient>
                            <linearGradient id="home-orca-water" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0" stop-color="#39ff14" stop-opacity="0" />
                                <stop offset=".45" stop-color="#57f3ff" stop-opacity=".74" />
                                <stop offset=".68" stop-color="#ff4fd8" stop-opacity=".28" />
                                <stop offset="1" stop-color="#39ff14" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <g class="home-harbour-stars">
                            <circle cx="74" cy="55" r="2" /><circle cx="178" cy="88" r="1.6" /><circle cx="304" cy="44" r="1.8" /><circle cx="510" cy="70" r="1.5" /><circle cx="642" cy="38" r="2" />
                            <circle cx="704" cy="92" r="1.4" /><circle cx="590" cy="126" r="1.4" /><circle cx="420" cy="108" r="1.2" /><circle cx="236" cy="34" r="1.1" /><circle cx="118" cy="154" r="1.2" />
                        </g>
                        <g class="home-harbour-gulls">
                            <path d="M108 132q14-11 28 0q14-11 28 0" />
                            <path d="M548 112q12-9 24 0q12-9 24 0" />
                            <path d="M642 158q10-7 20 0q10-7 20 0" />
                            <path d="M430 174q8-6 16 0q8-6 16 0" />
                        </g>
                        <path class="home-harbour-mountains" d="M0 240 68 204 122 218 188 168 244 214 314 145 382 226 448 156 520 232 600 150 674 216 760 184 760 315 0 315Z" />
                        <g class="home-harbour-skyline">
                            <path class="home-harbour-city" d="M0 306h34v-38h38v-28h30v66h28v-92h42v92h22v-58h36v58h24v-104h38v104h22v-72h34v72h18v-42h28v42h24v-86h20v-20h24v20h20v86h18v-46h24v46h22v-34h18l18-40 18 40h12l20-52 20 52h14l20-44 22 44h18l18-34 22 34h28v-70h42v70h28v52h84v62H0Z" />
                            <g class="home-harbour-lookout">
                                <path d="M444 306V210" />
                                <path d="M412 210h64l-12-18h-40Z" />
                                <path d="M426 190h36v-12h-36Z" />
                            </g>
                            <g class="home-harbour-sails">
                                <path d="M536 285 558 226 582 285Z" />
                                <path d="M572 285 598 216 622 285Z" />
                                <path d="M610 285 640 224 664 285Z" />
                                <path d="M650 285 680 236 700 285Z" />
                            </g>
                            <path class="home-harbour-lights" d="M58 292h10M146 286h10M282 276h10M392 288h10M712 290h10" />
                            <path class="home-harbour-ferry" d="M86 322h62l18-14h34l10 14h34l-14 18H100Z" />
                        </g>
                        <g class="home-harbour-reflection">
                            <path d="M34 346h138M204 356h196M438 348h254" />
                            <path d="M72 376h110M232 390h164M480 382h144" />
                            <path d="M532 314l-20 44M572 314l-28 82M614 316l-34 92M658 322l-22 58" />
                            <path d="M414 318h70M424 335h46" />
                        </g>
                        <g class="home-orca-sprite" filter="url(#home-orca-crt-glow)">
                            <path class="home-orca-tail" d="M490 306c28-24 58-39 92-45-7 27-22 47-45 60 24 12 41 31 52 56-34-3-64-16-90-39l-37-12 2-17Z" />
                            <path class="home-orca-body" d="M182 314c12-40 54-70 116-85 64-15 130-1 198 44 24 15 39 29 47 40-17 15-41 26-71 33-59 13-120 19-182 20-52 0-88-17-108-52Z" />
                            <path class="home-orca-belly" d="M200 322c24 19 57 29 101 27 57-2 111-12 160-30-29 32-72 51-131 58-57 7-100-12-130-55Z" />
                            <path class="home-orca-saddle" d="M370 240c35 5 65 17 91 35-29-3-50 3-66 17-18 16-42 10-52-13 4-17 13-30 27-39Z" />
                            <path class="home-orca-dorsal" d="M336 232c8-45 32-75 69-91 2 49-23 79-69 91Z" />
                            <path class="home-orca-pectoral" d="M328 356c-7 35-29 60-66 77-2-41 20-66 66-77Z" />
                            <path class="home-orca-eye" d="M220 290c20-17 47-21 77-12-17 20-43 27-74 21Z" />
                            <path class="home-orca-eye-dot" d="M244 290h7v7h-7z" />
                        </g>
                        <g class="home-orca-waterlines">
                            <path d="M98 404c42-12 70 12 112 0s70-12 112 0 70 12 112 0 70-12 112 0" />
                            <path d="M156 434c32-8 54 8 86 0s54-8 86 0 54 8 86 0 54-8 86 0" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <?php get_template_part( 'parts/lousy-outages-teaser' ); ?>

    <section class="home-play-mode crt-block" aria-labelledby="home-play-mode-title">
        <div class="home-play-mode__copy">
            <p class="home-section-kicker pixel-font"><?php echo esc_html( 'PLAY MODE' ); ?></p>
            <h2 id="home-play-mode-title" class="pixel-font"><?php echo esc_html( 'Pacific Static' ); ?></h2>
            <p><?php echo esc_html( 'A compact outage-blip arcade sketch: move with WASD or arrow keys, fire with Space, quit with Escape.' ); ?></p>
            <button type="button" class="pixel-button home-arcade-start" data-arcade-start><?php echo esc_html( 'Play Pacific Static' ); ?></button>
        </div>
        <div class="hero-game-stage home-arcade-game" aria-label="Pacific Static mini arcade game. Use WASD or arrow keys to move, Space to fire, and Escape to quit." data-arcade-stage>
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
            <p class="hero-game-stage__mobile-note pixel-font"><?php echo esc_html( 'Best on a keyboard screen.' ); ?></p>
        </div>
    </section>

    <section id="mission-select" class="home-project-grid home-mission-select crt-block" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'SELECT SYSTEM' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'SELECT SYSTEM' ); ?></h2>
        <div class="selected-work__grid home-mission-grid">
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'status weather' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3><p><?php echo esc_html( 'A status board for SaaS weirdness, provider incidents, and the moments official pages get too polite.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>"><?php echo esc_html( 'Check status' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'civic arcade world' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3><p><?php echo esc_html( 'A playable Vancouver corridor built from civic data, route logic, street mood, and arcade-map obsession.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/gastown-sim/' ) ); ?>"><?php echo esc_html( 'Walk Gastown' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'audio notes' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Track Analyzer' ); ?></h3><p><?php echo esc_html( 'Upload an MP3 and get clear notes on feel, lyrics, structure, and what might actually help the track.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>"><?php echo esc_html( 'Analyze track' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'recording oracle' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'What Would Steve Do' ); ?></h3><p><?php echo esc_html( 'A quote-backed recording prompt machine for mixes with too much nonsense and not enough nerve.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/albini-qa/' ) ); ?>"><?php echo esc_html( 'Ask Steve' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'soft machine' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'ASMR Lab' ); ?></h3><p><?php echo esc_html( 'Procedural sound and visual rituals, currently mutating into a stranger browser lab.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>"><?php echo esc_html( 'Enter lab' ); ?></a></article>
            <article class="home-project-card selected-work__card home-mission-card"><p class="home-mission-card__label pixel-font"><?php echo esc_html( 'discography' ); ?></p><h3 class="pixel-font"><?php echo esc_html( 'Music / Records' ); ?></h3><p><?php echo esc_html( 'Solo releases, past bands, touring history, and the music side of the machine.' ); ?></p><a class="pixel-button" href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>"><?php echo esc_html( 'Listen' ); ?></a></article>
        </div>
    </section>

    <section id="music" class="music-world home-unlocks crt-block" aria-labelledby="music-world-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'music / tools / contact' ); ?></p>
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html( 'Music, tools, contact' ); ?></h2>
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
