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
                    <svg class="home-orca-sigil" viewBox="0 0 720 500" aria-hidden="true" focusable="false">
                        <defs>
                            <filter id="home-orca-crt-glow" x="-18%" y="-28%" width="136%" height="156%">
                                <feGaussianBlur stdDeviation="2.2" result="blur" />
                                <feColorMatrix in="blur" type="matrix" values="0 0 0 0 0.2  0 0 0 0 1  0 0 0 0 0.82  0 0 0 .52 0" result="cyanGlow" />
                                <feMerge><feMergeNode in="cyanGlow" /><feMergeNode in="SourceGraphic" /></feMerge>
                            </filter>
                            <linearGradient id="home-harbour-skyline" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0" stop-color="#06111d" />
                                <stop offset=".5" stop-color="#09252f" />
                                <stop offset="1" stop-color="#050811" />
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
                            <circle cx="590" cy="126" r="1.4" /><circle cx="420" cy="108" r="1.2" /><circle cx="236" cy="34" r="1.1" />
                        </g>
                        <g class="home-harbour-gulls">
                            <path d="M118 126q18-14 36 0q18-14 36 0" />
                            <path d="M548 114q14-10 28 0q14-10 28 0" />
                            <path d="M448 166q10-8 20 0q10-8 20 0" />
                        </g>
                        <path class="home-harbour-mountains" d="M0 236 62 198 118 220 184 150 252 226 316 174 382 232 454 132 536 230 606 176 720 220 720 300 0 300Z" />
                        <g class="home-harbour-skyline">
                            <path class="home-harbour-city" d="M0 302h42v-46h34v-30h32v76h28v-100h44v100h24v-70h38v70h24v-118h46v118h22v-84h40v84h16v-52h28l18-38 18 38h16l18-44 20 44h18l18-34 20 34h24v-80h40v80h30v52h72v58H0Z" />
                            <path class="home-harbour-sails" d="M428 260 454 204 482 260M468 260 498 194 526 260M508 260 540 208 564 260" />
                            <path class="home-harbour-lights" d="M58 288h10M146 282h10M282 272h10M620 286h10M654 286h10" />
                        </g>
                        <g class="home-harbour-reflection">
                            <path d="M36 330h132M206 344h190M430 336h210" />
                            <path d="M92 364h84M232 378h148M472 368h112" />
                            <path d="M410 312l-18 38M454 306l-28 74M500 310l-36 86M542 315l-18 46" />
                        </g>
                        <g class="home-orca-sprite" filter="url(#home-orca-crt-glow)">
                            <path class="home-orca-tail" d="M470 280c34-29 70-47 110-54-8 32-26 56-54 72 28 14 48 36 62 66-40-3-76-18-108-46l-44-14 2-20Z" />
                            <path class="home-orca-body" d="M104 288c14-48 64-83 138-101 76-18 155-1 235 52 28 18 46 34 56 48-20 18-48 31-84 39-70 15-142 23-216 24-62 0-105-20-129-62Z" />
                            <path class="home-orca-belly" d="M126 298c28 23 68 34 120 32 68-2 132-14 190-36-34 38-86 61-156 69-68 8-119-14-154-65Z" />
                            <path class="home-orca-saddle" d="M328 200c42 6 78 20 108 42-34-4-59 3-78 20-22 19-50 12-62-16 5-20 16-36 32-46Z" />
                            <path class="home-orca-dorsal" d="M288 190c10-54 38-89 82-108 2 58-27 94-82 108Z" />
                            <path class="home-orca-pectoral" d="M278 337c-8 42-34 72-78 91-2-48 24-78 78-91Z" />
                            <path class="home-orca-eye" d="M150 260c24-20 56-25 92-14-20 24-51 32-88 25Z" />
                            <path class="home-orca-eye-dot" d="M178 260h8v8h-8z" />
                        </g>
                        <g class="home-orca-waterlines">
                            <path d="M70 398c42-12 70 12 112 0s70-12 112 0 70 12 112 0 70-12 112 0" />
                            <path d="M118 430c32-8 54 8 86 0s54-8 86 0 54 8 86 0 54-8 86 0" />
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
