<?php
/*
Template Name: Arcade
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="arcade-container">
            <h1 class="pixel-font">Suzy's Retro Arcade</h1>
            
            <div class="game-selection">
                <div class="game-card">
                    <h2 class="pixel-font">Canucks Puck Bash</h2>

                    <div class="game-header">
                        <div id="team-display" class="team-display"></div>
                        <div id="scoreboard" class="scoreboard"></div>
                    </div>

                    <canvas id="canucks-game" class="game-canvas" role="img" aria-label="Canucks retro hockey game"></canvas>

                    <div class="controls">
                        <p class="pixel-font">CONTROLS:</p>
                        <p>Arrow Keys – Move Player</p>
                        <p>Spacebar – Shoot Puck</p>
                        <button id="test-goal-sound" class="pixel-button">Test goal sound</button>
                    </div>

                    <div id="game-overlay" class="game-overlay">
                        <div class="overlay-content">
                            <p class="pixel-font">CONTROLS:<br>Arrow Keys – Move Player<br>Spacebar – Shoot Puck<br><br>Score as many goals as you can before time runs out!</p>
                            <button id="start-button" class="pixel-button">Start Game</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo get_template_directory_uri(); ?>/js/hockey-game.js"></script>

<script>
// Share game
function shareGame() {
    const shareData = {
        title: 'Canucks Puck Bash - Suzy Easton Arcade',
        text: 'Check out this retro Canucks hockey game! Score points and help Suzy Easton fight for affordable housing.',
        url: window.location.href
    };

    if (navigator.share) {
        navigator.share(shareData)
            .catch(console.error);
    }
}
</script>

<?php get_footer(); ?>
