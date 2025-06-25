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
                    <canvas id="canucks-game" class="game-canvas"></canvas>
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
    y: CANVAS_HEIGHT - 70,
    radius: 10,
    speedX: 0,
    speedY: 0,
    color: '#FFFFFF',
    inMotion: false
};

// Game state
let gameRunning = true;
let score = 0;
let goalMessageTimer = 0;
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

    // Draw goal message if needed
    if (goalMessageTimer > 0) {
        ctx.fillStyle = '#ff0';
        ctx.font = '30px pixel-font';
        ctx.fillText('Goal!', CANVAS_WIDTH / 2 - 50, CANVAS_HEIGHT / 2);
        goalMessageTimer -= 1;
    }

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
    // Player movement
    if (keys.ArrowLeft && player.x > 0) {
        player.x -= player.speed * (deltaTime / 16);
    }
    if (keys.ArrowRight && player.x < CANVAS_WIDTH - player.width) {
        player.x += player.speed * (deltaTime / 16);
    }

    if (puck.inMotion) {
        // Move puck
        puck.x += puck.speedX;
        puck.y += puck.speedY;

        // Puck collision with walls
        if (puck.x + puck.radius > CANVAS_WIDTH || puck.x - puck.radius < 0) {
            puck.speedX *= -1;
        }

        // Goal scored
        if (puck.y - puck.radius <= 0) {
            score++;
            goalMessageTimer = 60;
            resetPuck();
            return;
        }

        // Missed shot
        if (puck.y + puck.radius >= CANVAS_HEIGHT) {
            resetPuck();
            return;
        }

        // Puck collision with player
        if (puck.speedY > 0 &&
            puck.y + puck.radius > player.y &&
            puck.y - puck.radius < player.y + player.height &&
            puck.x > player.x &&
            puck.x < player.x + player.width) {
            puck.speedY *= -1;
            puck.y = player.y - puck.radius;
        }
    } else {
        // Keep puck on player's stick before shooting
        puck.x = player.x + player.width / 2;
        puck.y = player.y - puck.radius;
    }
}

function resetPuck() {
    puck.inMotion = false;
    puck.speedX = 0;
    puck.speedY = 0;
}

function shootPuck() {
    if (!puck.inMotion) {
        puck.inMotion = true;
        puck.speedX = Math.random() * 4 - 2;
        puck.speedY = -5;
    }
}

// Event listeners
document.addEventListener('keydown', (e) => {
    keys[e.code] = true;
    if (['ArrowLeft', 'ArrowRight', 'Space'].includes(e.code)) {
        e.preventDefault();
    }
    if (e.code === 'Space') {
        shootPuck();
    }
});

document.addEventListener('keyup', (e) => {
    keys[e.code] = false;
});

// Start game
resetPuck();
requestAnimationFrame(gameLoop);
</script>

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
