// Simple retro hockey game
// Inspired by classic 8-bit hockey titles

(function () {
  const canvas = document.getElementById("canucks-game");
  if (!canvas || !canvas.getContext) {
    console.warn("Game canvas not found");
    const overlayEl = document.getElementById("game-overlay");
    if (overlayEl) overlayEl.textContent = "Game failed to load";
    return;
  }
  const ctx = canvas.getContext("2d");
  const scoreboardEl = document.getElementById("scoreboard");
  const teamDisplayEl = document.getElementById("team-display");
  const overlay = document.getElementById("game-overlay");
  let startButton = document.getElementById("start-button");
  const testGoalSoundButton = document.getElementById("test-goal-sound");
  const overlayDefault = overlay.innerHTML;
  const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

  // ensure audio context is running on first user interaction
  let audioUnlocked = false;
  function unlockAudio() {
    if (!audioUnlocked && audioCtx.state !== "running") {
      audioCtx.resume().then(() => {
        audioUnlocked = true;
      });
    }
  }
  document.addEventListener("click", unlockAudio, { once: true });
  document.addEventListener("touchstart", unlockAudio, { once: true });


  const maxW = 480; // cap phone portrait
  function sizeCanvas() {
    const w = Math.min(window.innerWidth * 0.9, maxW);
    const h = w * 0.5; // keep 2:1 aspect
    canvas.width = w;
    canvas.height = h;
  }
  sizeCanvas();
  window.addEventListener("resize", sizeCanvas);

  const goal = { x: canvas.width / 2 - 60, width: 120, height: 10 };

  const teams = [
    "Oilers",
    "Flames",
    "Leafs",
    "Bruins",
    "Sharks",
    "Jets",
    "Senators",
    "Kings",
  ];
  let opponent = teams[Math.floor(Math.random() * teams.length)];

  const goalie = {
    x: goal.x + goal.width / 2 - 20,
    y: 10,
    width: 40,
    height: 10,
    speed: 120,
    dir: 1,
    color: "#ff5555",
  };

  const player = {
    x: canvas.width / 2 - 10,
    y: canvas.height - 40,
    width: 20,
    height: 20,
    speed: 200,
    color: "#0055aa",
  };

  const puck = {
    x: player.x + player.width / 2,
    y: player.y - 5,
    radius: 6,
    vx: 0,
    vy: 0,
    color: "#000",
  };

  let lastTime = 0;
  let keys = {};
  let canucksScore = 0;
  let opponentScore = 0;
  let timeLeft = 60;
  let running = false;
  let shotTimer = 0;
  let canucksShots = 0;
  let isDragging = false;

  function drawRink() {
    ctx.fillStyle = "#eef";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = "#3399ff";
    ctx.lineWidth = 4;
    ctx.strokeRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "#66bbff";
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
    // subtle difficulty ramp as the clock winds down
    if (timeLeft < 20) {
      goalie.speed = 180;
    } else if (timeLeft < 40) {
      goalie.speed = 150;
    } else {
      goalie.speed = 120;
    }

    goalie.x += goalie.speed * dt * goalie.dir;
    if (goalie.x < goal.x || goalie.x + goalie.width > goal.x + goal.width) {
      goalie.dir *= -1;
      goalie.x = Math.max(
        goal.x,
        Math.min(goalie.x, goal.x + goal.width - goalie.width),
      );
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
    if (
      (keys.ArrowRight || keys.KeyD) &&
      player.x + player.width < canvas.width
    ) {
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
        if (
          puck.x > goal.x &&
          puck.x < goal.x + goal.width &&
          !(puck.x > goalie.x && puck.x < goalie.x + goalie.width)
        ) {
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
      if (
        puck.y - puck.radius <= goalie.y + goalie.height &&
        puck.y - puck.radius >= goalie.y &&
        puck.x > goalie.x &&
        puck.x < goalie.x + goalie.width &&
        puck.vy < 0
      ) {
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

    scoreboardEl.textContent = `Canucks ${canucksScore} – ${opponent} ${opponentScore} | ${Math.ceil(timeLeft)}s | Shots: ${canucksShots}, Goals: ${canucksScore}`;
  }

  function resetPuck() {
    puck.vx = 0;
    puck.vy = 0;
    puck.x = player.x + player.width / 2;
    puck.y = player.y - 5;
  }

  function playGoalMelody() {
    if (audioCtx.state === "suspended") {
      audioCtx.resume();
    }

    // upbeat two-bar hook (G major) with a stadium-ready synth texture
    const melody = [
      { freq: 392.0, duration: 0.42, start: 0 }, // G4 root hit
      { freq: 587.33, duration: 0.32, start: 0.48 }, // D5 fifth
      { freq: 659.25, duration: 0.28, start: 0.82 }, // E5 sixth lift
      { freq: 587.33, duration: 0.26, start: 1.08 }, // D5 back to fifth
      { freq: 392.0, duration: 0.24, start: 1.42 }, // G4 reset
      { freq: 493.88, duration: 0.26, start: 1.68 }, // B4 third
      { freq: 587.33, duration: 0.28, start: 1.94 }, // D5 fifth
      { freq: 784.0, duration: 0.44, start: 2.2 }, // G5 octave punch
    ];

    const baseTime = audioCtx.currentTime;
    const peakGain = 0.42;
    let melodyEnd = 0;

    melody.forEach(function (note) {
      const startTime = baseTime + note.start;
      const endTime = startTime + note.duration;
      melodyEnd = Math.max(melodyEnd, endTime);

      const filter = audioCtx.createBiquadFilter();
      filter.type = "lowpass";
      filter.frequency.setValueAtTime(2600, startTime);

      const gain = audioCtx.createGain();
      gain.gain.setValueAtTime(0, startTime);
      gain.gain.linearRampToValueAtTime(peakGain, startTime + 0.01);
      gain.gain.linearRampToValueAtTime(0, endTime - 0.02);

      const oscMain = audioCtx.createOscillator();
      oscMain.type = "sawtooth";
      oscMain.frequency.setValueAtTime(note.freq, startTime);

      const oscHorn = audioCtx.createOscillator();
      oscHorn.type = "square";
      oscHorn.frequency.setValueAtTime(note.freq / 2, startTime);

      const hornGain = audioCtx.createGain();
      hornGain.gain.setValueAtTime(0.2, startTime);

      oscMain.connect(filter);
      oscHorn.connect(hornGain).connect(filter);
      filter.connect(gain).connect(audioCtx.destination);

      oscMain.start(startTime);
      oscHorn.start(startTime);
      oscMain.stop(endTime + 0.1);
      oscHorn.stop(endTime + 0.1);
    });

    function playCrowdNoise(startTime) {
      const duration = 0.45;
      const bufferSize = Math.floor(audioCtx.sampleRate * duration);
      const noiseBuffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
      const data = noiseBuffer.getChannelData(0);
      for (let i = 0; i < bufferSize; i++) {
        data[i] = Math.random() * 2 - 1;
      }

      const noise = audioCtx.createBufferSource();
      noise.buffer = noiseBuffer;

      const bandpass = audioCtx.createBiquadFilter();
      bandpass.type = "bandpass";
      bandpass.frequency.setValueAtTime(1400, startTime);
      bandpass.Q.setValueAtTime(0.9, startTime);

      const gain = audioCtx.createGain();
      gain.gain.setValueAtTime(0, startTime);
      gain.gain.linearRampToValueAtTime(0.18, startTime + 0.06);
      gain.gain.linearRampToValueAtTime(0, startTime + duration);

      noise.connect(bandpass).connect(gain).connect(audioCtx.destination);
      noise.start(startTime);
      noise.stop(startTime + duration + 0.05);
    }

    playCrowdNoise(baseTime + melodyEnd + 0.05);
  }

  function showGoal() {
    overlay.innerHTML =
      '<div class="overlay-content goal-animation"><p>GOAL!</p></div>';
    overlay.style.display = "flex";
    playGoalMelody();
    setTimeout(() => {
      overlay.style.display = "none";
      overlay.innerHTML = overlayDefault;
    }, 800);
  }

  function endGame() {
    running = false;
    const result = canucksScore >= opponentScore ? "You Win!" : "You Lose!";
    overlay.innerHTML = `<div class="overlay-content"><p>${result}<br>Final: Canucks ${canucksScore} - ${opponent} ${opponentScore}</p><button id="start-button" class="pixel-button">Play Again</button></div>`;
    overlay.style.display = "flex";
    document.getElementById("start-button").addEventListener("click", () => {
      overlay.innerHTML = overlayDefault;
      startButton = document.getElementById("start-button");
      startButton.addEventListener("click", start);
      start();
    });
  }

  function isPuckMoving() {
    return puck.vx !== 0 || puck.vy !== 0;
  }

  function shoot() {
    if (!isPuckMoving()) {
      canucksShots += 1;
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
      canucksShots = 0;
      timeLeft = 60;
      opponent = teams[Math.floor(Math.random() * teams.length)];
      teamDisplayEl.textContent = `Vancouver Canucks vs. ${opponent}`;
      scoreboardEl.textContent = `Canucks 0 – ${opponent} 0 | 60s | Shots: 0, Goals: 0`;
      running = true;
      lastTime = performance.now();
      overlay.style.display = "none";
      requestAnimationFrame(loop);
    }
  }

  startButton.addEventListener("click", start);
  if (testGoalSoundButton) {
    testGoalSoundButton.addEventListener("click", function () {
      unlockAudio();
      playGoalMelody();
    });
  }

  function handleKeyDown(e) {
    if (
      [
        "ArrowLeft",
        "ArrowRight",
        "ArrowUp",
        "ArrowDown",
        "Space",
        "KeyA",
        "KeyD",
        "KeyW",
        "KeyS",
      ].includes(e.code)
    ) {
      e.preventDefault();
    }
    if (e.code === "Space" || e.code === "ArrowUp" || e.code === "KeyW") {
      shoot();
    }
    keys[e.code] = true;
  }

  function handleKeyUp(e) {
    if (
      [
        "ArrowLeft",
        "ArrowRight",
        "ArrowUp",
        "ArrowDown",
        "Space",
        "KeyA",
        "KeyD",
        "KeyW",
        "KeyS",
      ].includes(e.code)
    ) {
      e.preventDefault();
    }
    keys[e.code] = false;
  }

  document.addEventListener("keydown", handleKeyDown, { passive: false });
  document.addEventListener("keyup", handleKeyUp, { passive: false });

  const keyMap = {
    "btn-up": "ArrowUp",
    "btn-down": "ArrowDown",
    "btn-left": "ArrowLeft",
    "btn-right": "ArrowRight",
    "btn-shoot": "Space",
  };
  Object.keys(keyMap).forEach((id) => {
    const btn = document.getElementById(id);
    if (!btn) return;
    ["touchstart", "mousedown"].forEach((evt) =>
      btn.addEventListener(
        evt,
        (e) => {
          e.preventDefault();
          handleKeyDown({ code: keyMap[id] });
        },
        { passive: false },
      ),
    );
    ["touchend", "mouseup", "mouseleave"].forEach((evt) =>
      btn.addEventListener(evt, () => handleKeyUp({ code: keyMap[id] })),
    );
  });
  const rect = () => canvas.getBoundingClientRect();

  canvas.addEventListener(
    "touchstart",
    (e) => {
      e.preventDefault();
      const touchX = e.touches[0].clientX - rect().left;
      player.x = touchX - player.width / 2;
      shoot();
      isDragging = true;
    },
    { passive: false },
  );

  canvas.addEventListener(
    "touchmove",
    (e) => {
      e.preventDefault();
      if (!isDragging) return;
      const touchX = e.touches[0].clientX - rect().left;
      player.x = touchX - player.width / 2;
    },
    { passive: false },
  );

  canvas.addEventListener("touchend", () => {
    isDragging = false;
  });

  canvas.addEventListener("mousedown", (e) => {
    const mouseX = e.clientX - rect().left;
    player.x = mouseX - player.width / 2;
    isDragging = true;
  });
  canvas.addEventListener("mousemove", (e) => {
    if (!isDragging) return;
    const mouseX = e.clientX - rect().left;
    player.x = mouseX - player.width / 2;
  });
  canvas.addEventListener("mouseup", () => {
    isDragging = false;
  });
})();
