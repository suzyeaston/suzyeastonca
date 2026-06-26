<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-orca-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="home-orca-stage">
            <span class="screen-reader-text"><?php echo esc_html( 'Two distinct orcas circling with North Shore mountains and CRT stars.' ); ?></span>
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
                <div class="home-orca-mark" role="img" aria-label="<?php echo esc_attr( 'Two distinct orcas circling in a CRT-style Burrard Inlet sigil.' ); ?>">
                    <svg class="home-orca-sigil" viewBox="0 0 560 560" aria-hidden="true" focusable="false">
                        <defs>
                            <filter id="home-orca-crt-glow" x="-18%" y="-18%" width="136%" height="136%">
                                <feGaussianBlur stdDeviation="2.2" result="blur" />
                                <feColorMatrix in="blur" type="matrix" values="0 0 0 0 0.2  0 0 0 0 1  0 0 0 0 0.82  0 0 0 .42 0" result="cyanGlow" />
                                <feMerge><feMergeNode in="cyanGlow" /><feMergeNode in="SourceGraphic" /></feMerge>
                            </filter>
                            <linearGradient id="home-orca-water" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0" stop-color="#39ff14" stop-opacity="0" />
                                <stop offset=".5" stop-color="#57f3ff" stop-opacity=".72" />
                                <stop offset="1" stop-color="#39ff14" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <g class="home-orca-rings" fill="none">
                            <circle cx="280" cy="280" r="214" />
                            <circle cx="280" cy="280" r="158" />
                        </g>
                        <path class="home-orca-mountain" d="M88 164 144 104l38 44 38-64 58 82 48-54 42 54 42-34 64 62" />
                        <g class="home-orca-pair" filter="url(#home-orca-crt-glow)">
                            <g class="home-orca home-orca--upper">
                                <path class="home-orca-body" d="M404 154c-79-45-178-28-242 39-28 30-44 66-43 96 45-50 101-77 169-82 52-4 93-23 116-53Z" />
                                <path class="home-orca-tail" d="M417 142c33-29 68-43 105-44-18 28-39 47-65 59 28 4 51 17 70 40-39 4-76-6-111-31Z" />
                                <path class="home-orca-belly" d="M154 250c42-31 86-47 133-48 35-1 66-10 91-27-14 32-49 55-96 62-52 8-94 31-127 70-9-17-9-36-1-57Z" />
                                <path class="home-orca-saddle" d="M284 145c34-2 67 7 99 28-33-6-60-1-82 13-18 11-38 4-45-12 5-13 14-22 28-29Z" />
                                <path class="home-orca-fin" d="M226 202c-6-50 10-87 48-112 4 50-12 88-48 112Z" />
                                <ellipse class="home-orca-eye" cx="378" cy="160" rx="10" ry="6" transform="rotate(22 378 160)" />
                            </g>
                            <g class="home-orca home-orca--lower">
                                <path class="home-orca-body" d="M154 402c69 40 157 28 214-31 25-26 39-58 38-85-40 44-90 68-150 73-46 4-82 20-102 43Z" />
                                <path class="home-orca-tail" d="M141 414c-29 25-61 38-94 39 16-25 35-42 58-52-25-4-45-15-62-35 35-4 68 5 99 27Z" />
                                <path class="home-orca-belly" d="M374 320c-37 27-76 41-118 43-31 1-58 9-80 24 13-29 44-49 85-55 46-7 84-27 113-62 8 15 8 32 0 50Z" />
                                <path class="home-orca-saddle" d="M262 410c-31 2-60-7-88-25 29 5 53 1 73-11 16-10 34-4 40 10-4 11-12 20-25 26Z" />
                                <path class="home-orca-fin" d="M316 360c5 44-9 77-43 99-3-44 11-78 43-99Z" />
                                <ellipse class="home-orca-eye" cx="176" cy="398" rx="9" ry="5.5" transform="rotate(202 176 398)" />
                            </g>
                        </g>
                        <g class="home-orca-waterlines"><path d="M88 444c44-12 74 12 118 0s74-12 118 0 74 12 118 0" /><path d="M116 470c32-8 54 8 86 0s54-8 86 0 54 8 86 0" /></g>
                        <text class="home-orca-location" x="280" y="514" text-anchor="middle">BURRARD INLET</text>
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
