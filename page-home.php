<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-orca-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="home-orca-stage">
            <span class="screen-reader-text"><?php echo esc_html( 'Two orcas circling in a CRT-style Burrard Inlet title mark.' ); ?></span>
            <div class="home-orca-sky" aria-hidden="true">
                <span class="home-orca-starname">SUZY EASTON</span>
            </div>
            <svg class="home-orca-north-shore" viewBox="0 0 1200 260" aria-hidden="true" focusable="false">
                <path class="home-orca-north-shore__back" d="M0 176 74 154 142 102 208 137 283 58 353 134 428 84 508 145 579 92 642 132 714 64 796 142 878 96 940 138 1014 72 1094 146 1200 110" />
                <path class="home-orca-north-shore__front" d="M0 205 96 174 176 146 253 166 334 112 420 174 498 139 585 178 668 132 753 176 846 126 930 172 1028 132 1114 180 1200 152" />
            </svg>
            <div class="home-orca-mark" role="img" aria-label="<?php echo esc_attr( 'Two orcas circling in a CRT-style Burrard Inlet title mark.' ); ?>">
                <svg class="home-orca-sigil" viewBox="0 0 520 520" aria-hidden="true" focusable="false">
                    <defs>
                        <filter id="home-orca-crt-glow" x="-35%" y="-35%" width="170%" height="170%">
                            <feGaussianBlur stdDeviation="4" result="blur" />
                            <feColorMatrix in="blur" type="matrix" values="0 0 0 0 0.2  0 0 0 0 1  0 0 0 0 0.82  0 0 0 .7 0" result="cyanGlow" />
                            <feMerge>
                                <feMergeNode in="cyanGlow" />
                                <feMergeNode in="SourceGraphic" />
                            </feMerge>
                        </filter>
                        <linearGradient id="home-orca-water" x1="0" x2="1" y1="0" y2="0">
                            <stop offset="0" stop-color="#39ff14" stop-opacity="0" />
                            <stop offset=".5" stop-color="#57f3ff" stop-opacity=".85" />
                            <stop offset="1" stop-color="#39ff14" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <g class="home-orca-rings" fill="none">
                        <circle cx="260" cy="260" r="196" />
                        <circle cx="260" cy="260" r="150" />
                    </g>
                    <path class="home-orca-mountain" d="M72 160 132 96l38 42 34-58 56 78 44-46 46 54 42-34 56 54" />
                    <g class="home-orca-pair" filter="url(#home-orca-crt-glow)">
                        <g class="home-orca home-orca--upper">
                            <path class="home-orca-body" d="M384 126c-89-31-188 5-239 86-18 29-25 59-18 82 59-58 126-76 206-54 28 8 56 5 75-9 21-15 27-39 16-62-7-16-20-31-40-43Z" />
                            <path class="home-orca-tail" d="M424 169c29-8 54-25 72-52-34 4-59 13-76 28 10-23 12-47 3-72-21 21-33 48-39 80Z" />
                            <path class="home-orca-belly" d="M173 243c48-30 106-37 171-20 27 7 50 3 66-12-8 29-42 48-84 42-58-9-107 4-153 47-10-16-10-35 0-57Z" />
                            <path class="home-orca-saddle" d="M298 143c38 0 72 13 99 35-34-9-65-8-93 3-19 8-36-3-40-19 8-9 19-15 34-19Z" />
                            <ellipse class="home-orca-eye" cx="366" cy="166" rx="12" ry="7" transform="rotate(22 366 166)" />
                            <path class="home-orca-fin" d="M239 209c-11-48 0-86 34-114 10 47 2 85-34 114Z" />
                        </g>
                        <g class="home-orca home-orca--lower">
                            <path class="home-orca-body" d="M136 394c89 31 188-5 239-86 18-29 25-59 18-82-59 58-126 76-206 54-28-8-56-5-75 9-21 15-27 39-16 62 7 16 20 31 40 43Z" />
                            <path class="home-orca-tail" d="M96 351c-29 8-54 25-72 52 34-4 59-13 76-28-10 23-12 47-3 72 21-21 33-48 39-80Z" />
                            <path class="home-orca-belly" d="M347 277c-48 30-106 37-171 20-27-7-50-3-66 12 8-29 42-48 84-42 58 9 107-4 153-47 10 16 10 35 0 57Z" />
                            <path class="home-orca-saddle" d="M222 377c-38 0-72-13-99-35 34 9 65 8 93-3 19-8 36 3 40 19-8 9-19 15-34 19Z" />
                            <ellipse class="home-orca-eye" cx="154" cy="354" rx="12" ry="7" transform="rotate(22 154 354)" />
                            <path class="home-orca-fin" d="M281 311c11 48 0 86-34 114-10-47-2-85 34-114Z" />
                        </g>
                    </g>
                    <g class="home-orca-waterlines">
                        <path d="M74 404c46-12 78 12 124 0s78-12 124 0 78 12 124 0" />
                        <path d="M102 430c34-8 58 8 92 0s58-8 92 0 58 8 92 0" />
                    </g>
                    <text class="home-orca-location" x="260" y="474" text-anchor="middle">BURRARD INLET</text>
                </svg>
            </div>
            <div class="home-orca-copy">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'music // practical ai // weird useful tools' ); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline home-arcade-title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                <p class="hero-copy home-arcade-copy"><?php echo esc_html( 'I build practical AI workflows, music tools, outage dashboards, and strange Vancouver web experiments.' ); ?></p>
                <div class="home-orca-actions home-cta-row hero-cta-group">
                    <a href="#mission-select" class="pixel-button hero-primary-cta"><?php echo esc_html( 'Enter the lab' ); ?></a>
                    <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>" class="pixel-button pixel-button--secondary"><?php echo esc_html( 'Check Lousy Outages' ); ?></a>
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
