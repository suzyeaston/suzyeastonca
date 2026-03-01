(function () {
  function initHeroGalaga() {
    const heroGrid = document.querySelector('.hero-grid');
    const desktopQuery = window.matchMedia('(min-width: 860px)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    window.__SE_GALAGA_ACTIVE = false;

    if (heroGrid) {
      heroGrid.dataset.galagaReady = 'true';
      heroGrid.dataset.galagaActive = 'false';
    }

    if (!heroGrid || !desktopQuery.matches) {
    return;
  }

  const rootStyles = window.getComputedStyle(document.documentElement);
  const colors = {
    primary: (rootStyles.getPropertyValue('--primary-color') || '#00ff00').trim(),
    secondary: (rootStyles.getPropertyValue('--secondary-color') || '#e60073').trim(),
    accent: (rootStyles.getPropertyValue('--accent-color') || '#ffff00').trim(),
  };

  const canvas = document.createElement('canvas');
  canvas.className = 'hero-galaga-canvas';
  canvas.setAttribute('aria-hidden', 'true');

  const ui = document.createElement('div');
  ui.className = 'hero-galaga-ui';
  ui.innerHTML = '<p class="hero-galaga-status">Score: <span data-galaga-score>0</span> · Lives: <span data-galaga-lives>3</span> · Wave: <span data-galaga-wave>1</span></p><p class="hero-galaga-help">Move: ←/→ or A/D · Shoot: Space · Esc: Quit</p><div class="hero-galaga-gameover" data-galaga-gameover hidden></div>';

  const hint = document.createElement('div');
  hint.className = 'hero-galaga-hint';
  hint.innerHTML = '<span class="hero-galaga-hint-text">'
    + (reducedMotionQuery.matches ? 'Press G to play (manual motion)' : 'Press G to play')
    + '</span><button type="button" class="hero-galaga-start">Play</button>';

  heroGrid.appendChild(canvas);
  heroGrid.appendChild(ui);
  heroGrid.appendChild(hint);

  const ctx = canvas.getContext('2d', { alpha: true });
  if (!ctx) {
    return;
  }

  ctx.imageSmoothingEnabled = false;

  const scoreEl = ui.querySelector('[data-galaga-score]');
  const livesEl = ui.querySelector('[data-galaga-lives]');
  const waveEl = ui.querySelector('[data-galaga-wave]');
  const gameOverEl = ui.querySelector('[data-galaga-gameover]');
  const startButton = hint.querySelector('.hero-galaga-start');

  const state = {
    active: false,
    running: false,
    gameOver: false,
    width: 1,
    height: 1,
    dpr: Math.max(1, window.devicePixelRatio || 1),
    rafId: 0,
    score: 0,
    lives: 3,
    wave: 1,
    player: null,
    playerBullets: [],
    enemyBullets: [],
    enemies: [],
    enemyDir: 1,
    enemyStepTimer: 0,
    enemyFireTimer: 0,
    keys: {
      left: false,
      right: false,
      fire: false,
    },
    lastShotAt: 0,
    lastFrame: 0,
  };

  function sizeCanvas() {
    state.dpr = Math.max(1, window.devicePixelRatio || 1);
    const width = Math.max(1, heroGrid.clientWidth);
    const height = Math.max(1, heroGrid.clientHeight);
    state.width = width;
    state.height = height;
    canvas.width = Math.round(width * state.dpr);
    canvas.height = Math.round(height * state.dpr);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    ctx.setTransform(state.dpr, 0, 0, state.dpr, 0, 0);
    ctx.imageSmoothingEnabled = false;

    if (state.player) {
      state.player.x = Math.min(state.width - state.player.w, Math.max(0, state.player.x));
      state.player.y = state.height - 64;
    }
  }

  function updateUI() {
    scoreEl.textContent = String(state.score);
    livesEl.textContent = String(state.lives);
    waveEl.textContent = String(state.wave);
  }

  function spawnWave() {
    const cols = 8;
    const rows = 4;
    const enemyW = 22;
    const enemyH = 16;
    const gapX = 18;
    const gapY = 14;
    const totalW = cols * enemyW + (cols - 1) * gapX;
    const startX = Math.max(20, (state.width - totalW) / 2);
    const startY = 56;

    state.enemies = [];
    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < cols; col += 1) {
        state.enemies.push({
          x: startX + col * (enemyW + gapX),
          y: startY + row * (enemyH + gapY),
          w: enemyW,
          h: enemyH,
          alive: true,
        });
      }
    }

    state.enemyDir = 1;
    state.enemyStepTimer = 0;
    state.enemyFireTimer = 0;
  }

  function resetGame() {
    state.score = 0;
    state.lives = 3;
    state.wave = 1;
    state.gameOver = false;
    state.playerBullets = [];
    state.enemyBullets = [];
    state.player = {
      w: 24,
      h: 14,
      x: state.width / 2 - 12,
      y: state.height - 64,
      speed: 300,
    };

    spawnWave();
    updateUI();
    gameOverEl.hidden = true;
    gameOverEl.textContent = '';
  }

  function startGame() {
    if (state.active || !desktopQuery.matches) {
      return;
    }

    canvas.tabIndex = 0;
    state.active = true;
    state.running = true;
    window.__SE_GALAGA_ACTIVE = true;
    heroGrid.dataset.galagaActive = 'true';
    heroGrid.classList.add('is-galaga');
    resetGame();
    canvas.focus({ preventScroll: true });
    state.lastFrame = performance.now();
    state.rafId = window.requestAnimationFrame(loop);
  }

  function stopGame() {
    state.active = false;
    state.running = false;
    state.gameOver = false;
    state.keys.left = false;
    state.keys.right = false;
    state.keys.fire = false;
    window.__SE_GALAGA_ACTIVE = false;
    heroGrid.dataset.galagaActive = 'false';
    heroGrid.classList.remove('is-galaga');
    if (state.rafId) {
      window.cancelAnimationFrame(state.rafId);
      state.rafId = 0;
    }
    ctx.clearRect(0, 0, state.width, state.height);
    gameOverEl.hidden = true;
    gameOverEl.textContent = '';
  }

  function intersects(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  }

  function handleKeys(dt, now) {
    if (!state.player) {
      return;
    }

    const dir = (state.keys.right ? 1 : 0) - (state.keys.left ? 1 : 0);
    state.player.x += dir * state.player.speed * dt;
    state.player.x = Math.max(0, Math.min(state.width - state.player.w, state.player.x));

    if (state.keys.fire && now - state.lastShotAt > 180) {
      state.playerBullets.push({ x: state.player.x + state.player.w / 2 - 2, y: state.player.y - 8, w: 4, h: 10, vy: -430 });
      state.lastShotAt = now;
    }
  }

  function updateEnemies(dt) {
    if (!state.enemies.length) {
      return;
    }

    const stepInterval = Math.max(0.14, 0.5 - (state.wave - 1) * 0.04);
    state.enemyStepTimer += dt;

    if (state.enemyStepTimer >= stepInterval) {
      state.enemyStepTimer = 0;
      const stepX = 14 + state.wave * 1.2;
      const stepY = 14;

      let nextMinX = Infinity;
      let nextMaxX = -Infinity;
      state.enemies.forEach(function (enemy) {
        if (!enemy.alive) {
          return;
        }
        const nextX = enemy.x + stepX * state.enemyDir;
        nextMinX = Math.min(nextMinX, nextX);
        nextMaxX = Math.max(nextMaxX, nextX + enemy.w);
      });

      const hitEdge = nextMinX <= 8 || nextMaxX >= state.width - 8;

      state.enemies.forEach(function (enemy) {
        if (!enemy.alive) {
          return;
        }
        if (hitEdge) {
          enemy.y += stepY;
        } else {
          enemy.x += stepX * state.enemyDir;
        }
      });

      if (hitEdge) {
        state.enemyDir *= -1;
      }
    }

    state.enemyFireTimer += dt;
    const fireInterval = Math.max(0.45, 1.2 - (state.wave - 1) * 0.08);
    if (state.enemyFireTimer >= fireInterval) {
      state.enemyFireTimer = 0;
      const aliveEnemies = state.enemies.filter(function (enemy) {
        return enemy.alive;
      });
      if (aliveEnemies.length) {
        const shooter = aliveEnemies[Math.floor(Math.random() * aliveEnemies.length)];
        state.enemyBullets.push({
          x: shooter.x + shooter.w / 2 - 2,
          y: shooter.y + shooter.h,
          w: 4,
          h: 10,
          vy: 240 + state.wave * 22,
        });
      }
    }
  }

  function updateBullets(dt) {
    state.playerBullets.forEach(function (bullet) {
      bullet.y += bullet.vy * dt;
    });
    state.enemyBullets.forEach(function (bullet) {
      bullet.y += bullet.vy * dt;
    });

    state.playerBullets = state.playerBullets.filter(function (bullet) {
      return bullet.y + bullet.h > 0;
    });
    state.enemyBullets = state.enemyBullets.filter(function (bullet) {
      return bullet.y < state.height + bullet.h;
    });
  }

  function resolveCollisions() {
    state.playerBullets = state.playerBullets.filter(function (bullet) {
      let hit = false;
      state.enemies.forEach(function (enemy) {
        if (enemy.alive && intersects(bullet, enemy)) {
          enemy.alive = false;
          hit = true;
          state.score += 100;
        }
      });
      return !hit;
    });

    if (state.player) {
      state.enemyBullets = state.enemyBullets.filter(function (bullet) {
        if (intersects(bullet, state.player)) {
          state.lives -= 1;
          return false;
        }
        return true;
      });
    }

    if (state.lives <= 0 && !state.gameOver) {
      state.running = false;
      state.gameOver = true;
      gameOverEl.hidden = false;
      gameOverEl.textContent = 'Game Over · Score ' + state.score + ' · Press Enter to restart or Esc to quit';
    }

    const aliveCount = state.enemies.filter(function (enemy) {
      return enemy.alive;
    }).length;

    if (aliveCount === 0 && !state.gameOver) {
      state.wave += 1;
      state.playerBullets = [];
      state.enemyBullets = [];
      spawnWave();
    }

    const invaded = state.enemies.some(function (enemy) {
      return enemy.alive && enemy.y + enemy.h >= state.height - 56;
    });

    if (invaded && !state.gameOver) {
      state.lives = 0;
      state.running = false;
      state.gameOver = true;
      gameOverEl.hidden = false;
      gameOverEl.textContent = 'Game Over · Invaded! · Press Enter to restart or Esc to quit';
    }

    updateUI();
  }

  function drawPlayer() {
    if (!state.player) {
      return;
    }

    const p = state.player;
    ctx.fillStyle = colors.primary;
    ctx.fillRect(Math.round(p.x + 10), Math.round(p.y), 4, 3);
    ctx.fillRect(Math.round(p.x + 7), Math.round(p.y + 3), 10, 4);
    ctx.fillRect(Math.round(p.x + 3), Math.round(p.y + 7), 18, 4);
    ctx.fillRect(Math.round(p.x), Math.round(p.y + 11), 24, 3);
    ctx.fillStyle = colors.accent;
    ctx.fillRect(Math.round(p.x + 11), Math.round(p.y + 5), 2, 4);
  }

  function drawEnemy(enemy) {
    ctx.fillStyle = colors.secondary;
    ctx.fillRect(Math.round(enemy.x + 2), Math.round(enemy.y), enemy.w - 4, 3);
    ctx.fillRect(Math.round(enemy.x), Math.round(enemy.y + 3), enemy.w, 5);
    ctx.fillRect(Math.round(enemy.x + 4), Math.round(enemy.y + 8), enemy.w - 8, 3);
    ctx.fillStyle = colors.accent;
    ctx.fillRect(Math.round(enemy.x + 5), Math.round(enemy.y + 4), 2, 2);
    ctx.fillRect(Math.round(enemy.x + enemy.w - 7), Math.round(enemy.y + 4), 2, 2);
  }

  function drawBullets() {
    ctx.fillStyle = colors.accent;
    state.playerBullets.forEach(function (bullet) {
      ctx.fillRect(Math.round(bullet.x), Math.round(bullet.y), bullet.w, bullet.h);
    });

    ctx.fillStyle = colors.primary;
    state.enemyBullets.forEach(function (bullet) {
      ctx.fillRect(Math.round(bullet.x), Math.round(bullet.y), bullet.w, bullet.h);
    });
  }

  function render() {
    ctx.clearRect(0, 0, state.width, state.height);
    ctx.fillStyle = 'rgba(0, 0, 0, 0.72)';
    ctx.fillRect(0, 0, state.width, state.height);

    drawPlayer();

    state.enemies.forEach(function (enemy) {
      if (enemy.alive) {
        drawEnemy(enemy);
      }
    });

    drawBullets();

    if (!state.running && !state.gameOver) {
      ctx.fillStyle = colors.primary;
      ctx.font = '12px "Press Start 2P", monospace';
      ctx.textAlign = 'center';
      ctx.fillText('Press G to Start', state.width / 2, state.height / 2);
    }
  }

  function loop(now) {
    const dt = Math.min(0.033, (now - state.lastFrame) / 1000 || 0.016);
    state.lastFrame = now;

    if (state.active && state.running) {
      handleKeys(dt, now);
      updateEnemies(dt);
      updateBullets(dt);
      resolveCollisions();
    }

    render();

    if (state.active) {
      state.rafId = window.requestAnimationFrame(loop);
    }
  }

  function isTypingTarget(target) {
    if (!target) {
      return false;
    }
    const tag = target.tagName;
    return target.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
  }

  function shouldBlockGameplayEvent(event) {
    if (!state.active) {
      return false;
    }

    const code = event.code;
    return code === 'ArrowLeft' || code === 'ArrowRight' || code === 'KeyA' || code === 'KeyD' || code === 'Space';
  }

  if (startButton) {
    startButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      startGame();
      canvas.focus({ preventScroll: true });
    });
  }

  window.addEventListener('keydown', function (event) {
    const code = event.code;
    const typing = isTypingTarget(event.target);

    if (!state.active && !typing && code === 'KeyG') {
      startGame();
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    if (!state.active) {
      return;
    }

    if (typing) {
      return;
    }

    if (code === 'Escape') {
      stopGame();
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    if (state.gameOver && code === 'Enter') {
      resetGame();
      state.running = true;
      state.lastFrame = performance.now();
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    if (code === 'ArrowLeft' || code === 'KeyA') {
      state.keys.left = true;
    }
    if (code === 'ArrowRight' || code === 'KeyD') {
      state.keys.right = true;
    }
    if (code === 'Space') {
      state.keys.fire = true;
    }

    if (shouldBlockGameplayEvent(event)) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, { capture: true });

  window.addEventListener('keyup', function (event) {
    if (!state.active) {
      return;
    }

    if (isTypingTarget(event.target)) {
      return;
    }

    const code = event.code;
    if (code === 'ArrowLeft' || code === 'KeyA') {
      state.keys.left = false;
    }
    if (code === 'ArrowRight' || code === 'KeyD') {
      state.keys.right = false;
    }
    if (code === 'Space') {
      state.keys.fire = false;
    }

    if (shouldBlockGameplayEvent(event)) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, { capture: true });

  desktopQuery.addEventListener('change', function (event) {
    if (!event.matches && state.active) {
      stopGame();
    }
  });

  const resizeObserver = 'ResizeObserver' in window
    ? new ResizeObserver(function () {
      sizeCanvas();
    })
    : null;

  if (resizeObserver) {
    resizeObserver.observe(heroGrid);
  } else {
    window.addEventListener('resize', sizeCanvas, { passive: true });
  }

  sizeCanvas();
  updateUI();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroGalaga, { once: true });
  } else {
    initHeroGalaga();
  }
})();
