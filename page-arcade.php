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
    width: 40,
    height: 20,
    speed: 5,
    color: '#001F5B'
};

// Puck properties
const puck = {
    x: CANVAS_WIDTH / 2,
    y: 50,
    radius: 10,
    speedX: 3,
    speedY: 3,
    color: '#FFFFFF'
};

// Game state
let gameRunning = true;
let score = 0;
let keys = {};
let lastTime = 0;

// Game loop
function gameLoop(timestamp) {
    const deltaTime = timestamp - lastTime;
    lastTime = timestamp;

    // Clear canvas
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

    // Draw score
    ctx.fillStyle = '#fff';
    ctx.font = '20px pixel-font';
    ctx.fillText(`Score: ${score}`, 10, 20);

    // Draw player
    drawPlayer();

    // Draw puck
    drawPuck();

    // Update game state
    updateGame(deltaTime);

    if (gameRunning) {
        requestAnimationFrame(gameLoop);
    }
}

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
function updateGame(deltaTime) {
    // Move puck
    puck.x += puck.speedX;
    puck.y += puck.speedY;

    // Puck collision with walls
    if (puck.x + puck.radius > CANVAS_WIDTH || puck.x - puck.radius < 0) {
        puck.speedX *= -1;
    }
    
    if (puck.y - puck.radius < 0) {
        puck.speedY *= -1;
    }

    // Player movement
    if (keys.ArrowLeft && player.x > 0) {
        player.x -= player.speed * (deltaTime / 1000);
    }
    if (keys.ArrowRight && player.x < CANVAS_WIDTH - player.width) {
        player.x += player.speed * (deltaTime / 1000);
    }

    // Puck collision with player
    if (puck.y + puck.radius > player.y &&
        puck.y - puck.radius < player.y + player.height &&
        puck.x > player.x &&
        puck.x < player.x + player.width) {
        puck.speedY *= -1;
        score++;
        puck.speedX += Math.sign(puck.speedX) * 0.2; // Increase speed
        puck.speedY += Math.sign(puck.speedY) * 0.2;
    }

    // Score when puck hits bottom
    if (puck.y + puck.radius > CANVAS_HEIGHT) {
        puck.x = CANVAS_WIDTH / 2;
        puck.y = 50;
        puck.speedX = Math.random() * 6 - 3;
        puck.speedY = 3;
        score = 0;
    }
}

// Event listeners
document.addEventListener('keydown', (e) => {
    keys[e.code] = true;
});

document.addEventListener('keyup', (e) => {
    keys[e.code] = false;
});

// Start game
requestAnimationFrame(gameLoop);
</script>

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
