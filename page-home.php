<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout home-arcade-layout">

    <section class="home-arcade-title-screen crt-block" aria-labelledby="home-hero-title" data-arcade-hero>
        <div class="home-arcade-screen__backdrop" aria-hidden="true">
            <span class="home-pixel-star home-pixel-star--one"></span>
            <span class="home-pixel-star home-pixel-star--two"></span>
            <span class="home-pixel-star home-pixel-star--three"></span>
            <span class="home-pixel-rain home-pixel-rain--one"></span>
            <span class="home-pixel-rain home-pixel-rain--two"></span>
            <span class="home-arcade-moon"></span>
            <svg class="home-arcade-vancouver" viewBox="0 0 1200 280" focusable="false">
                <path class="home-arcade-vancouver__mountains" d="M0 154 78 126 146 82 215 122 286 48 360 132 428 84 508 140 584 76 646 126 718 52 794 136 878 82 948 130 1016 58 1094 136 1200 94 1200 280 0 280Z" />
                <path class="home-arcade-vancouver__city" d="M0 214h46v-34h34v-24h28v58h34v-82h38v82h24v-48h36v48h24v-92h40v92h24v-58h34v58h22v-40h28v40h30v-74h22v-20h30v20h22v74h18v-42h28v42h30l18-38 18 38h20l22-52 24 52h18l18-34 24 34h26v-62h44v62h30v48h112v66H0Z" />
                <path class="home-arcade-vancouver__sails" d="M760 204 790 138 820 204Zm48 0 36-82 34 82Zm58 0 40-68 30 68Z" />
                <path class="home-arcade-vancouver__water" d="M64 242h164M282 252h240M580 240h332M182 266h190M464 270h310" />
            </svg>
        </div>
        <div class="home-arcade-screen__panel">
            <p class="home-arcade-kicker pixel-font"><?php echo esc_html( 'VANCOUVER CABINET // SYSTEM BOOT' ); ?></p>
            <h1 id="home-hero-title" class="home-arcade-title pixel-font"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
            <p class="home-arcade-subtitle pixel-font"><?php echo esc_html( 'music // AI strategy // creative technology' ); ?></p>
            <div class="home-title-screen-prompt pixel-font" role="status" aria-live="polite" data-arcade-status><?php echo esc_html( 'INSERT COIN' ); ?></div>
            <button type="button" class="pixel-button home-press-start" data-home-start data-start-label="PRESS START"><?php echo esc_html( 'PRESS START' ); ?></button>
            <div class="home-title-screen-meta pixel-font" aria-hidden="true"><span>1 PLAYER</span><span>RAIN CITY</span><span>MUSIC / AI / TOOLS</span></div>
        </div>
    </section>

    <div class="home-level-intro home-level-intro--outages pixel-font" aria-hidden="true"><span>LEVEL 01</span><strong>STATUS BOARD FOR MODERN CHAOS</strong><em>LIVE OUTAGE SIGNAL</em></div>

    <?php get_template_part( 'parts/lousy-outages-teaser' ); ?>

    <section class="home-play-mode crt-block" aria-labelledby="home-play-mode-title">
        <div class="home-play-mode__copy">
            <p class="home-section-kicker pixel-font"><?php echo esc_html( 'BONUS LEVEL' ); ?></p>
            <h2 id="home-play-mode-title" class="pixel-font"><?php echo esc_html( 'Pacific Power Play' ); ?></h2>
            <p><?php echo esc_html( 'Choose your line, drop the puck, and survive the rain city static.' ); ?></p>
            <button type="button" class="pixel-button home-arcade-start" data-arcade-start><?php echo esc_html( 'Choose Your Line' ); ?></button>
        </div>
        <div class="hero-game-stage home-arcade-game" aria-label="Pacific Power Play Vancouver hockey arcade character select and rink game. Use WASD or arrow keys to choose or skate, Enter to confirm, Space to shoot, E for ability, and Escape to pause or go back." data-arcade-stage>
            <p class="hero-game-stage__header pixel-font"><?php echo esc_html( 'PACIFIC POWER PLAY' ); ?></p>
            <div class="hero-game-stage__screen" role="img" aria-label="Pacific Power Play arcade cabinet screen with attract mode, character-select cards, versus splash, and a neon rain city hockey rink.">
                <p class="hero-game-stage__idle pixel-font"><?php echo wp_kses_post( 'INSERT COIN<br>CHOOSE YOUR LINE<br>VANCOUVER HOCKEY ARCADE' ); ?></p>
                <div class="home-static-sprites" aria-hidden="true">
                    <span class="home-static-sprites__ship"></span>
                    <span class="home-static-sprites__enemy home-static-sprites__enemy--one"></span>
                    <span class="home-static-sprites__enemy home-static-sprites__enemy--two"></span>
                    <span class="home-static-sprites__reticle"></span>
                </div>
            </div>
            <p class="hero-game-stage__mobile-note pixel-font"><?php echo esc_html( 'Best with keyboard. Tap cards to choose your line.' ); ?></p>
        </div>
    </section>

    <section id="mission-select" class="home-project-grid home-mission-select crt-block" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( 'LEVEL SELECT' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'CHOOSE YOUR SYSTEM' ); ?></h2>
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
