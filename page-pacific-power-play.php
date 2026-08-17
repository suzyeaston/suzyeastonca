<?php
/* Template Name: Pacific Power Play */
get_header();
?>

<main id="pacific-power-play-content" class="power-play-page">
    <section class="power-play-page__hero" aria-labelledby="power-play-page-title">
        <div class="power-play-page__copy">
            <p class="power-play-page__era pixel-font"><?php echo esc_html( '1994 // PACIFIC COLISEUM' ); ?></p>
            <h1 id="power-play-page-title" class="pixel-font"><?php echo esc_html( 'Pacific Power Play' ); ?></h1>
            <p class="power-play-page__deck"><?php echo esc_html( 'Pick your line. Drop the puck. Survive the rain city static before the feed cuts out.' ); ?></p>
            <ul class="power-play-page__facts pixel-font" aria-label="Cabinet notes">
                <li><?php echo esc_html( 'Flying Skate roster cards' ); ?></li>
                <li><?php echo esc_html( 'Keyboard cabinet — tap cards on mobile' ); ?></li>
            </ul>
        </div>
        <div class="power-play-page__cabinet">
            <div class="hero-game-stage home-arcade-game" aria-label="Pacific Power Play Vancouver hockey arcade character select and rink game. Use WASD or arrow keys to choose or skate, Enter to confirm, Space to shoot, E for ability, and Escape to pause or go back." data-arcade-stage>
                <p class="hero-game-stage__header pixel-font"><?php echo esc_html( 'PACIFIC COLISEUM // HOME ICE' ); ?></p>
                <div class="hero-game-stage__screen" role="img" aria-label="Pacific Power Play arcade cabinet screen with attract mode, character-select cards, versus splash, and a neon rain city hockey rink.">
                    <p class="hero-game-stage__idle pixel-font"><?php echo wp_kses_post( 'INSERT COIN<br>CHOOSE YOUR LINE<br>FLYING SKATE ROSTER' ); ?></p>
                </div>
                <p class="hero-game-stage__mobile-note pixel-font"><?php echo esc_html( 'Best with keyboard. Tap cards to pick your skater.' ); ?></p>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
