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
  const teamDisplayEl = document.getElementById('team-display');
  const overlay = document.getElementById('game-overlay');
  let startButton = document.getElementById('start-button');
  const overlayDefault = overlay.innerHTML;
  const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

  const maxW = 480;               // cap phone portrait
  function sizeCanvas() {
    const w = Math.min(window.innerWidth * 0.9, maxW);
    const h = w * 0.5;            // keep 2:1 aspect
    canvas.width  = w;
    canvas.height = h;
  }
  sizeCanvas();
  window.addEventListener('resize', sizeCanvas);

  const goal = { x: canvas.width / 2 - 60, width: 120, height: 10 };

  const teams = ['Oilers','Flames','Leafs','Bruins','Sharks','Jets','Senators','Kings'];
  let opponent = teams[Math.floor(Math.random()*teams.length)];

  const goalie = {
    x: goal.x + goal.width / 2 - 20,
    y: 10,
    width: 40,
    height: 10,
    speed: 120,
    dir: 1,
    color: '#ff5555'
  };

  const player = {
    x: canvas.width / 2 - 10,
    y: canvas.height - 40,
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
  let canucksScore = 0;
  let opponentScore = 0;
  let timeLeft = 60;
  let running = false;
  let shotTimer = 0;

  function drawRink() {
    ctx.fillStyle = '#eef';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#3399ff';
    ctx.lineWidth = 4;
    ctx.strokeRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#66bbff';
    ctx.fillRect(goal.x, 0, goal.width, goal.height);
  }

  function drawPlayer() {
    ctx.fillStyle = player.color;
    ctx.fillRect(player.x, player.y, player.width, player.height);
  }

  function drawGoalie() {
    ctx.fillStyle = goalie.color;
    ctx.fillRect(goalie.x, goalie.y, goalie.width, goalie.height);
  }

  function updateGoalie(dt) {
    goalie.x += goalie.speed * dt * goalie.dir;
    if (goalie.x < goal.x || goalie.x + goalie.width > goal.x + goal.width) {
      goalie.dir *= -1;
      goalie.x = Math.max(goal.x, Math.min(goalie.x, goal.x + goal.width - goalie.width));
    }
  }

  function drawPuck() {
    ctx.beginPath();
    ctx.arc(puck.x, puck.y, puck.radius, 0, Math.PI * 2);
    ctx.fillStyle = puck.color;
    ctx.fill();
  }

  function update(dt) {
    if ((keys.ArrowLeft || keys.KeyA) && player.x > 0) {
      player.x -= player.speed * dt;
    }
    if ((keys.ArrowRight || keys.KeyD) && player.x + player.width < canvas.width) {
      player.x += player.speed * dt;
    }

    if (!isPuckMoving()) {
      puck.x = player.x + player.width / 2;
      puck.y = player.y - 5;
      shotTimer = 0;
    } else {
      shotTimer += dt;
      puck.x += puck.vx * dt * 60;
      puck.y += puck.vy * dt * 60;

      // friction
      puck.vx *= 0.99;
      puck.vy *= 0.99;

      if (puck.x - puck.radius < 0) {
        puck.x = puck.radius;
        puck.vx *= -1;
      }
      if (puck.x + puck.radius > canvas.width) {
        puck.x = canvas.width - puck.radius;
        puck.vx *= -1;
      }
      if (puck.y - puck.radius < 0) {
        if (puck.x > goal.x && puck.x < goal.x + goal.width && !(puck.x > goalie.x && puck.x < goalie.x + goalie.width)) {
          canucksScore += 1;
          showGoal();
          resetPuck();
        } else {
          puck.y = puck.radius;
          puck.vy *= -1;
        }
      }
      if (puck.y + puck.radius > canvas.height) {
        opponentScore += 1;
        resetPuck();
      }

      // goalie collision
      if (puck.y - puck.radius <= goalie.y + goalie.height &&
          puck.y - puck.radius >= goalie.y &&
          puck.x > goalie.x && puck.x < goalie.x + goalie.width && puck.vy < 0) {
        puck.vy *= -1;
        puck.y = goalie.y + goalie.height + puck.radius;
      }

      if (shotTimer > 2 && Math.abs(puck.vx) < 0.2 && Math.abs(puck.vy) < 0.2) {
        resetPuck();
      }
    }

    timeLeft -= dt;
    if (timeLeft <= 0) {
      endGame();
    }

    scoreboardEl.textContent = `Canucks ${canucksScore} – ${opponent} ${opponentScore} | ${Math.ceil(timeLeft)}`;
  }

  function resetPuck() {
    puck.vx = 0;
    puck.vy = 0;
    puck.x = player.x + player.width / 2;
    puck.y = player.y - 5;
  }

  function playGoalMelody() {
    const melody = [
      // First two notes hold longer like "Don't" and "you"
      [466.16, 600], // Bb4
      [392.0,  600], // G4
      // Remaining notes play quicker
      [349.23, 250], // F4
      [392.0,  250], // G4
      [440.0,  250], // A4
      [392.0,  250], // G4
      [349.23, 350], // F4
    ];

    let startTime = audioCtx.currentTime;
    melody.forEach(([freq, dur], idx) => {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = 'square';
      osc.frequency.setValueAtTime(freq, startTime);
      gain.gain.setValueAtTime(0.25, startTime);
      osc.connect(gain).connect(audioCtx.destination);
      osc.start(startTime);
      osc.stop(startTime + dur / 1000);
      // Add a small pause after the second note
      startTime += (dur + (idx === 1 ? 400 : 40)) / 1000;
    });
  }

  function showGoal() {
    overlay.innerHTML = '<div class="overlay-content goal-animation"><p>GOAL!</p></div>';
    overlay.style.display = 'flex';
    playGoalMelody();
    setTimeout(() => {
      overlay.style.display = 'none';
      overlay.innerHTML = overlayDefault;
    }, 800);
  }

  function endGame() {
    running = false;
    const result = canucksScore >= opponentScore ? 'You Win!' : 'You Lose!';
    overlay.innerHTML = `<div class="overlay-content"><p>${result}<br>Final: Canucks ${canucksScore} - ${opponent} ${opponentScore}</p><button id="start-button" class="pixel-button">Play Again</button></div>`;
    overlay.style.display = 'flex';
    document.getElementById('start-button').addEventListener('click', () => {
      overlay.innerHTML = overlayDefault;
      startButton = document.getElementById('start-button');
      startButton.addEventListener('click', start);
      start();
    });
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
    drawGoalie();
    drawPuck();
    updateGoalie(dt);
    update(dt);

    requestAnimationFrame(loop);
  }

  function start() {
    if (!running) {
      canucksScore = 0;
      opponentScore = 0;
      timeLeft = 60;
      opponent = teams[Math.floor(Math.random()*teams.length)];
      teamDisplayEl.textContent = `Vancouver Canucks vs. ${opponent}`;
      scoreboardEl.textContent = `Canucks 0 – ${opponent} 0 | 60`;
      running = true;
      lastTime = performance.now();
      overlay.style.display = 'none';
      requestAnimationFrame(loop);
    }
  }

  startButton.addEventListener('click', start);

  function handleKeyDown(e) {
    if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Space','KeyA','KeyD','KeyW','KeyS'].includes(e.code)) {
      e.preventDefault();
    }
    if (e.code === 'Space' || e.code === 'ArrowUp' || e.code === 'KeyW') {
      shoot();
    }
    keys[e.code] = true;
  }

  function handleKeyUp(e) {
    if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Space','KeyA','KeyD','KeyW','KeyS'].includes(e.code)) {
      e.preventDefault();
    }
    keys[e.code] = false;
  }

  document.addEventListener('keydown', handleKeyDown, {passive:false});
  document.addEventListener('keyup', handleKeyUp, {passive:false});

  if (window.matchMedia('(hover: none)').matches) {
    const tc = document.createElement('div');
    tc.id = 'touch-controls';
    tc.innerHTML = `
      <button id="btn-up">⬆️</button>
      <div>
        <button id="btn-left">⬅️</button>
        <button id="btn-shoot">🏒</button>
        <button id="btn-right">➡️</button>
      </div>
      <button id="btn-down">⬇️</button>
    `;
    document.body.appendChild(tc);
    const map = {
      'btn-up': 'ArrowUp',
      'btn-down': 'ArrowDown',
      'btn-left': 'ArrowLeft',
      'btn-right': 'ArrowRight',
      'btn-shoot': 'Space'
    };
    Object.entries(map).forEach(([id, code]) => {
      const el = document.getElementById(id);
      el.addEventListener('touchstart', e => { e.preventDefault(); handleKeyDown({code}); });
      el.addEventListener('touchend', e => { e.preventDefault(); handleKeyUp({code}); });
    });
  }
})();
