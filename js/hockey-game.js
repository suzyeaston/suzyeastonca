// Simple retro hockey game
// Inspired by classic 8-bit hockey titles

(function() {
  const canvas = document.getElementById('canucks-game');
  if (!canvas || !canvas.getContext) {
    console.warn('Game canvas not found');
    const overlayEl = document.getElementById('game-overlay');
    if (overlayEl) overlayEl.textContent = 'Game failed to load';
    return;
  }
  const ctx = canvas.getContext('2d');
  const scoreboardEl = document.getElementById('scoreboard');
  const overlay = document.getElementById('game-overlay');

  const WIDTH = 800;
  const HEIGHT = 400;
  canvas.width = WIDTH;
  canvas.height = HEIGHT;

  const goal = { x: WIDTH / 2 - 60, width: 120, height: 10 };

  const player = {
    x: WIDTH / 2 - 10,
    y: HEIGHT - 40,
    width: 20,
    height: 20,
    speed: 200,
    color: '#0055aa'
  };

  const puck = {
    x: player.x + player.width / 2,
    y: player.y - 5,
    radius: 6,
    vx: 0,
    vy: 0,
    color: '#000'
  };

  let lastTime = 0;
  let keys = {};
  let score = 0;
  let running = false;

  function drawRink() {
    ctx.fillStyle = '#eef';
    ctx.fillRect(0, 0, WIDTH, HEIGHT);
    ctx.strokeStyle = '#3399ff';
    ctx.lineWidth = 4;
    ctx.strokeRect(0, 0, WIDTH, HEIGHT);
    ctx.fillStyle = '#66bbff';
    ctx.fillRect(goal.x, 0, goal.width, goal.height);
  }

  function drawPlayer() {
    ctx.fillStyle = player.color;
    ctx.fillRect(player.x, player.y, player.width, player.height);
  }

  function drawPuck() {
    ctx.beginPath();
    ctx.arc(puck.x, puck.y, puck.radius, 0, Math.PI * 2);
    ctx.fillStyle = puck.color;
    ctx.fill();
  }

  function update(dt) {
    if (keys.ArrowLeft && player.x > 0) {
      player.x -= player.speed * dt;
    }
    if (keys.ArrowRight && player.x + player.width < WIDTH) {
      player.x += player.speed * dt;
    }
    if (!isPuckMoving()) {
      puck.x = player.x + player.width / 2;
      puck.y = player.y - 5;
    } else {
      puck.x += puck.vx * dt * 60;
      puck.y += puck.vy * dt * 60;

      if (puck.x - puck.radius < 0 || puck.x + puck.radius > WIDTH) {
        puck.vx *= -1;
      }
      if (puck.y - puck.radius < 0) {
        // goal?
        if (puck.x > goal.x && puck.x < goal.x + goal.width) {
          score += 1;
          resetPuck();
        } else {
          puck.vy *= -1;
        }
      }
      if (puck.y + puck.radius > HEIGHT) {
        resetPuck();
      }
    }
    scoreboardEl.textContent = `Score: ${score}`;
  }

  function resetPuck() {
    puck.vx = 0;
    puck.vy = 0;
    puck.x = player.x + player.width / 2;
    puck.y = player.y - 5;
  }

  function isPuckMoving() {
    return puck.vx !== 0 || puck.vy !== 0;
  }

  function shoot() {
    if (!isPuckMoving()) {
      puck.vy = -5;
      puck.vx = 0;
    }
  }

  function loop(timestamp) {
    if (!running) return;
    const dt = (timestamp - lastTime) / 1000;
    lastTime = timestamp;

    drawRink();
    drawPlayer();
    drawPuck();
    update(dt);

    requestAnimationFrame(loop);
  }

  function start() {
    if (!running) {
      running = true;
      lastTime = performance.now();
      overlay.style.display = 'none';
      requestAnimationFrame(loop);
    }
  }

  overlay.addEventListener('click', start);

  document.addEventListener('keydown', e => {
    if (e.code === 'Space') {
      shoot();
    } else {
      keys[e.code] = true;
    }
  });
  document.addEventListener('keyup', e => {
    keys[e.code] = false;
  });
})();
