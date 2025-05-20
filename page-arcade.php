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

<script>
// Canucks Puck Bash Game
const gameCanvas = document.getElementById('canucks-game');
const ctx = gameCanvas.getContext('2d');

// Game dimensions
const CANVAS_WIDTH = 800;
const CANVAS_HEIGHT = 400;
gameCanvas.width = CANVAS_WIDTH;
gameCanvas.height = CANVAS_HEIGHT;

// Player properties
const player = {
    x: CANVAS_WIDTH / 2,
    y: CANVAS_HEIGHT - 50,
    width: 20,
    height: 20,
    speed: 5,
    color: '#001F5B'
};

// Puck properties
const puck = {
    x: CANVAS_WIDTH / 2,
    y: 50,
    radius: 5,
    speedX: 3,
    speedY: 3,
    color: '#FFFFFF'
};

// Game state
let gameRunning = true;
let score = 0;

// Draw player
function drawPlayer() {
    ctx.fillStyle = player.color;
    ctx.fillRect(player.x, player.y, player.width, player.height);
}

// Draw puck
function drawPuck() {
    ctx.beginPath();
    ctx.arc(puck.x, puck.y, puck.radius, 0, Math.PI * 2);
    ctx.fillStyle = puck.color;
    ctx.fill();
}

// Update game state
function updateGame() {
    // Move puck
    puck.x += puck.speedX;
    puck.y += puck.speedY;

    // Puck collision with walls
    if (puck.x + puck.radius > CANVAS_WIDTH || puck.x - puck.radius < 0) {
        puck.speedX *= -1;
    }
    
    if (puck.y + puck.radius > CANVAS_HEIGHT || puck.y - puck.radius < 0) {
        puck.speedY *= -1;
    }

    // Player movement
    if (keys.ArrowLeft && player.x > 0) {
        player.x -= player.speed;
    }
    if (keys.ArrowRight && player.x < CANVAS_WIDTH - player.width) {
        player.x += player.speed;
    }

    // Score when puck hits bottom
    if (puck.y + puck.radius > CANVAS_HEIGHT) {
        score++;
        puck.y = 50;
        puck.speedX = Math.random() * 6 - 3;
        puck.speedY = 3;
    }
}

// Draw game
function drawGame() {
    ctx.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    drawPlayer();
    drawPuck();
    
    // Draw score
    ctx.fillStyle = '#FFFFFF';
    ctx.font = '16px Press Start 2P';
    ctx.fillText(`Score: ${score}`, 10, 20);
}

// Game loop
function gameLoop() {
    if (!gameRunning) return;
    updateGame();
    drawGame();
    requestAnimationFrame(gameLoop);
}

// Keyboard controls
const keys = {};
window.addEventListener('keydown', (e) => {
    keys[e.key] = true;
});
window.addEventListener('keyup', (e) => {
    keys[e.key] = false;
});

// Start game
window.addEventListener('load', () => {
    gameLoop();
});

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
