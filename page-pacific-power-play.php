<?php
/* Template Name: Pacific Power Play */
get_header();
?>

<main id="pacific-power-play-content" class="power-play-page home-arcade-layout">
    <section class="power-play-page__hero crt-block" aria-labelledby="power-play-page-title">
        <div class="power-play-page__copy">
            <p class="home-section-kicker pixel-font"><?php echo esc_html( 'BONUS LEVEL' ); ?></p>
            <h1 id="power-play-page-title" class="pixel-font"><?php echo esc_html( 'Pacific Power Play' ); ?></h1>
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
</main>

<?php get_footer(); ?>
