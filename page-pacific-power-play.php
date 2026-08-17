<?php
/* Template Name: Pacific Power Play */
get_header();
?>

<main id="pacific-power-play-content" class="power-play-page">
    <section class="power-play-page__hero" aria-labelledby="power-play-page-title">
        <div class="power-play-page__copy">
            <p class="power-play-page__era pixel-font"><?php echo esc_html( '1993–94 // PACIFIC COLISEUM' ); ?></p>
            <h1 id="power-play-page-title" class="pixel-font"><?php echo esc_html( 'Pacific Power Play' ); ?></h1>
            <p class="power-play-page__deck"><?php echo esc_html( 'Pick three Canucks. Face the KLM line — Krutov, Larionov, Makarov — with Tretiak in net.' ); ?></p>
            <ul class="power-play-page__facts pixel-font" aria-label="1993-94 line">
                <li><?php echo esc_html( 'CANUCKS: #10 Bure  #16 Linden  #1 McLean' ); ?></li>
                <li><?php echo esc_html( '#14 Courtnall  #7 Ronning  #29 Odjick' ); ?></li>
                <li><?php echo esc_html( 'USSR: #11 Larionov  #9 Krutov  #24 Makarov  #20 Tretiak' ); ?></li>
            </ul>
        </div>
        <div class="power-play-page__cabinet">
            <div class="hero-game-stage home-arcade-game" aria-label="Pacific Power Play 1993-94 Canucks roster cabinet. Tap a jersey to pick Bure, Linden, McLean, Courtnall, Ronning, or Odjick. Then skate with WASD or arrows, shoot with Space, use ability with E, and pause with Escape." data-arcade-stage>
                <p class="hero-game-stage__header pixel-font"><?php echo esc_html( 'PACIFIC COLISEUM // CANUCKS vs KLM' ); ?></p>
                <div class="hero-game-stage__screen" role="img" aria-label="Pacific Power Play arcade cabinet. Pick three 1993-94 Canucks to face the USSR KLM line.">
                    <p class="hero-game-stage__idle pixel-font"><?php echo wp_kses_post( 'INSERT COIN<br>OPEN ROSTER<br>#10 #16 #1 #14 #7 #29' ); ?></p>
                </div>
                <p class="hero-game-stage__mobile-note pixel-font"><?php echo esc_html( 'Same cabinet everywhere. Click or tap a skater, then drop the puck.' ); ?></p>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
