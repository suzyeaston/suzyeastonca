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
                <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'music // practical ai // weird useful tools' ); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline home-arcade-title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                <p class="hero-copy home-arcade-copy"><?php echo esc_html( 'I build practical AI workflows, music tools, outage dashboards, and strange Vancouver web experiments.' ); ?></p>
                <div class="home-orca-actions home-cta-row hero-cta-group">
                    <a href="#mission-select" class="pixel-button hero-primary-cta"><?php echo esc_html( 'Enter the lab' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>" class="pixel-button pixel-button--secondary"><?php echo esc_html( 'Check Lousy Outages' ); ?></a>
                </div>
            </div>
            <div class="home-orca-art" aria-hidden="true">
                <div class="home-orca-mark" role="img" aria-label="<?php echo esc_attr( 'One retro arcade killer whale gliding through Burrard Inlet with CRT glow.' ); ?>">
                    <svg class="home-orca-sigil" viewBox="0 0 620 430" aria-hidden="true" focusable="false">
                        <defs>
                            <filter id="home-orca-crt-glow" x="-18%" y="-28%" width="136%" height="156%">
                                <feGaussianBlur stdDeviation="2.1" result="blur" />
                                <feColorMatrix in="blur" type="matrix" values="0 0 0 0 0.2  0 0 0 0 1  0 0 0 0 0.82  0 0 0 .48 0" result="cyanGlow" />
                                <feMerge><feMergeNode in="cyanGlow" /><feMergeNode in="SourceGraphic" /></feMerge>
                            </filter>
                            <linearGradient id="home-orca-water" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0" stop-color="#39ff14" stop-opacity="0" />
                                <stop offset=".45" stop-color="#57f3ff" stop-opacity=".72" />
                                <stop offset="1" stop-color="#39ff14" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <g class="home-orca-sprite" filter="url(#home-orca-crt-glow)">
                            <path class="home-orca-tail" d="M455 190c34-26 72-42 114-48-11 34-32 57-63 70 34 10 60 30 79 60-43 2-82-10-117-36l-54-16 2-22Z" />
                            <path class="home-orca-body" d="M67 218c11-56 61-98 140-121 76-22 161-10 250 48 29 19 50 36 63 51-21 18-48 31-80 39-61 16-129 28-203 37-78 9-134-9-170-54Z" />
                            <path class="home-orca-belly" d="M91 224c27 23 66 34 117 32 65-2 126-13 184-33-33 39-84 64-153 76-66 12-116-13-148-75Z" />
                            <path class="home-orca-saddle" d="M318 106c42 8 78 23 108 45-34-5-60 1-78 18-21 19-50 13-63-15 5-22 16-38 33-48Z" />
                            <path class="home-orca-dorsal" d="M260 98c9-60 37-101 85-124 5 65-24 107-85 124Z" />
                            <path class="home-orca-pectoral" d="M248 252c-7 47-32 82-75 105-6-53 18-88 75-105Z" />
                            <path class="home-orca-eye" d="M112 180c25-21 57-26 95-16-21 25-52 34-91 28Z" />
                            <path class="home-orca-eye-dot" d="M142 181h9v8h-9z" />
                            <path class="home-orca-pixel-glint" d="M184 250h24v10h-24zM332 88h16v10h-16z" />
                        </g>
                        <g class="home-orca-waterlines">
                            <path d="M34 314c42-12 70 12 112 0s70-12 112 0 70 12 112 0 70-12 112 0" />
                            <path d="M82 348c32-8 54 8 86 0s54-8 86 0 54 8 86 0 54-8 86 0" />
                            <path d="M146 378c24-6 40 6 64 0s40-6 64 0 40 6 64 0" />
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
