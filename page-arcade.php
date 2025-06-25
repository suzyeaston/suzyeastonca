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
                    <div id="canucks-game" class="game-canvas"></div>
                    <div id="scoreboard" class="scoreboard">Score: 0</div>
                    <div id="game-overlay" class="game-overlay">Click to Start</div>
                    <div class="controls">
                        <p class="pixel-font">Controls:</p>
                        <p>Arrow Keys - Move</p>
                        <p>Space - Shoot</p>
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
