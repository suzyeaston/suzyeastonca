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

    const heroMain = heroGrid.querySelector('.hero-main');

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
    ui.innerHTML = '<p class="hero-galaga-status">Score: <span data-galaga-score>0</span> · Lives: <span data-galaga-lives>3</span> · Wave: <span data-galaga-wave>1</span></p><p class="hero-galaga-help">←/→ (A/D) · Space · Esc</p><div class="hero-galaga-gameover" data-galaga-gameover hidden></div>';

    const hint = document.createElement('div');
    hint.className = 'hero-galaga-hint';
    hint.innerHTML = '<span class="hero-galaga-hint-text" data-galaga-hint-text></span><button type="button" class="hero-galaga-start">Play</button>';

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
    const hintTextEl = hint.querySelector('[data-galaga-hint-text]');

    const state = {
      mode: 'idle',
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
      idleTick: 0,
      playfield: {
        x: 0,
        y: 0,
        w: 1,
        h: 1,
      },
    };

    function clampPlayerToPlayfield() {
      if (!state.player) {
        return;
      }

      const maxX = state.playfield.x + state.playfield.w - state.player.w;
      state.player.x = Math.max(state.playfield.x, Math.min(maxX, state.player.x));
      state.player.y = state.playfield.y + state.playfield.h - state.player.h - 18;
    }

    function positionOverlayUI() {
      const hintBottom = Math.max(12, state.height - (state.playfield.y + state.playfield.h) + 12);
      ui.style.left = state.playfield.x + 12 + 'px';
      ui.style.top = state.playfield.y + 12 + 'px';
      ui.style.maxWidth = Math.max(200, state.playfield.w - 24) + 'px';

      hint.style.left = state.playfield.x + 12 + 'px';
      hint.style.bottom = hintBottom + 'px';
    }

    function isActiveMode() {
      return state.mode === 'active';
    }

    function shouldAnimateIdle() {
      return !reducedMotionQuery.matches && desktopQuery.matches && state.mode === 'idle';
    }

    function sizeCanvas() {
      state.dpr = Math.max(1, window.devicePixelRatio || 1);
      const width = Math.max(1, heroGrid.clientWidth);
      const height = Math.max(1, heroGrid.clientHeight);
      const heroGridRect = heroGrid.getBoundingClientRect();
      const mainRect = heroMain ? heroMain.getBoundingClientRect() : heroGridRect;
      const visibleH = Math.min(mainRect.height, window.innerHeight - mainRect.top - 16);
      state.width = width;
      state.height = height;
      state.playfield = {
        x: Math.max(0, Math.round(mainRect.left - heroGridRect.left)),
        y: Math.max(0, Math.round(mainRect.top - heroGridRect.top)),
        w: Math.min(state.width, Math.max(1, Math.round(mainRect.width))),
        h: Math.min(state.height, Math.max(260, Math.round(visibleH))),
      };
      canvas.width = Math.round(width * state.dpr);
      canvas.height = Math.round(height * state.dpr);
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      ctx.setTransform(state.dpr, 0, 0, state.dpr, 0, 0);
      ctx.imageSmoothingEnabled = false;

      clampPlayerToPlayfield();
      positionOverlayUI();

      if (!isActiveMode()) {
        render();
      }
    }

    function updateUI() {
      scoreEl.textContent = String(state.score);
      livesEl.textContent = String(state.lives);
      waveEl.textContent = String(state.wave);
    }

    function updateHint() {
      if (!hintTextEl || !startButton) {
        return;
      }

      if (isActiveMode()) {
        hintTextEl.textContent = 'Esc to quit';
        startButton.hidden = true;
      } else {
        hintTextEl.textContent = reducedMotionQuery.matches
          ? 'Press G to play (manual motion)'
          : 'Press G to play';
        startButton.hidden = false;
      }
    }

    function spawnWave(config) {
      const options = config || {};
      const cols = options.cols || 8;
      const rows = options.rows || 4;
      const enemyW = 22;
      const enemyH = 16;
      const gapX = options.gapX || 18;
      const gapY = options.gapY || 14;
      const totalW = cols * enemyW + (cols - 1) * gapX;
      const startX = state.playfield.x + Math.max(10, (state.playfield.w - totalW) / 2);
      const startY = state.playfield.y + (options.startY || 70);

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

    function initScene() {
      state.playerBullets = [];
      state.enemyBullets = [];
      state.player = {
        w: 24,
        h: 14,
        x: state.playfield.x + state.playfield.w / 2 - 12,
        y: state.playfield.y + state.playfield.h - 14 - 18,
        speed: 300,
      };
      spawnWave();
      clampPlayerToPlayfield();
    }

    function initIdleScene() {
      state.playerBullets = [];
      state.enemyBullets = [];
      state.player = {
        w: 24,
        h: 14,
        x: state.playfield.x + state.playfield.w / 2 - 12,
        y: state.playfield.y + state.playfield.h - 14 - 18,
        speed: 300,
      };
      spawnWave({ cols: 5, rows: 2, gapX: 16, gapY: 12, startY: 86 });
      clampPlayerToPlayfield();
    }

    function resetGame() {
      state.score = 0;
      state.lives = 3;
      state.wave = 1;
      state.gameOver = false;
      state.lastShotAt = 0;
      initScene();
      updateUI();
      gameOverEl.hidden = true;
      gameOverEl.textContent = '';
    }

    function ensureLoopRunning() {
      if (state.rafId || (!isActiveMode() && !shouldAnimateIdle())) {
        return;
      }
      state.lastFrame = performance.now();
      state.rafId = window.requestAnimationFrame(loop);
    }

    function stopLoop() {
      if (state.rafId) {
        window.cancelAnimationFrame(state.rafId);
        state.rafId = 0;
      }
    }

    function enterIdleMode() {
      state.mode = 'idle';
      state.running = false;
      state.gameOver = false;
      state.keys.left = false;
      state.keys.right = false;
      state.keys.fire = false;
      state.playerBullets = [];
      state.enemyBullets = [];
      state.idleTick = 0;

      window.__SE_GALAGA_ACTIVE = false;
      heroGrid.dataset.galagaActive = 'false';
      heroGrid.classList.remove('is-galaga');
      canvas.style.pointerEvents = 'none';
      gameOverEl.hidden = true;
      gameOverEl.textContent = '';
      initIdleScene();

      updateHint();
      render();
      if (shouldAnimateIdle()) {
        ensureLoopRunning();
      } else {
        stopLoop();
      }
    }

    function startGame() {
      if (!desktopQuery.matches || isActiveMode()) {
        return;
      }

      stopLoop();
      canvas.tabIndex = 0;
      state.mode = 'active';
      state.running = true;
      window.__SE_GALAGA_ACTIVE = true;
      heroGrid.dataset.galagaActive = 'true';
      heroGrid.classList.add('is-galaga');
      canvas.style.pointerEvents = 'auto';

      resetGame();
      updateHint();
      canvas.focus({ preventScroll: true });
      ensureLoopRunning();
    }

    function stopGame() {
      if (!isActiveMode()) {
        return;
      }
      enterIdleMode();
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
      clampPlayerToPlayfield();

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

        const playfieldRight = state.playfield.x + state.playfield.w;
        const hitEdge = nextMinX <= state.playfield.x + 8 || nextMaxX >= playfieldRight - 8;

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
        return bullet.x + bullet.w > state.playfield.x &&
          bullet.x < state.playfield.x + state.playfield.w &&
          bullet.y + bullet.h > state.playfield.y &&
          bullet.y < state.playfield.y + state.playfield.h;
      });
      state.enemyBullets = state.enemyBullets.filter(function (bullet) {
        return bullet.x + bullet.w > state.playfield.x &&
          bullet.x < state.playfield.x + state.playfield.w &&
          bullet.y < state.playfield.y + state.playfield.h &&
          bullet.y + bullet.h > state.playfield.y;
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
        return enemy.alive && enemy.y + enemy.h >= state.playfield.y + state.playfield.h - 56;
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

    function drawEnemy(enemy, yOffset) {
      const drawY = enemy.y + (yOffset || 0);
      ctx.fillStyle = colors.secondary;
      ctx.fillRect(Math.round(enemy.x + 2), Math.round(drawY), enemy.w - 4, 3);
      ctx.fillRect(Math.round(enemy.x), Math.round(drawY + 3), enemy.w, 5);
      ctx.fillRect(Math.round(enemy.x + 4), Math.round(drawY + 8), enemy.w - 8, 3);
      ctx.fillStyle = colors.accent;
      ctx.fillRect(Math.round(enemy.x + 5), Math.round(drawY + 4), 2, 2);
      ctx.fillRect(Math.round(enemy.x + enemy.w - 7), Math.round(drawY + 4), 2, 2);
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
      ctx.save();
      ctx.beginPath();
      ctx.rect(state.playfield.x, state.playfield.y, state.playfield.w, state.playfield.h);
      ctx.clip();
      ctx.fillStyle = isActiveMode() ? 'rgba(0, 0, 0, 0.45)' : 'rgba(0, 0, 0, 0.18)';
      ctx.fillRect(state.playfield.x, state.playfield.y, state.playfield.w, state.playfield.h);

      drawPlayer();

      const idleBob = isActiveMode() ? 0 : Math.sin(state.idleTick * 1.35) * 3;
      state.enemies.forEach(function (enemy, index) {
        if (enemy.alive) {
          const offset = isActiveMode() ? 0 : idleBob + Math.sin(index * 0.7 + state.idleTick) * 1.25;
          drawEnemy(enemy, offset);
        }
      });

      drawBullets();

      if (!isActiveMode()) {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.22)';
        ctx.fillRect(state.playfield.x, state.playfield.y + state.playfield.h - 74, state.playfield.w, 74);
      }

      ctx.restore();
    }

    function loop(now) {
      const dt = Math.min(0.033, (now - state.lastFrame) / 1000 || 0.016);
      state.lastFrame = now;

      if (isActiveMode() && state.running) {
        handleKeys(dt, now);
        updateEnemies(dt);
        updateBullets(dt);
        resolveCollisions();
      } else if (state.mode === 'idle') {
        state.idleTick += dt;
      }

      render();

      if (isActiveMode() || shouldAnimateIdle()) {
        state.rafId = window.requestAnimationFrame(loop);
      } else {
        state.rafId = 0;
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
      if (!isActiveMode()) {
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
      });
    }

    window.addEventListener('keydown', function (event) {
      const code = event.code;
      const typing = isTypingTarget(event.target);

      if (!isActiveMode() && !typing && code === 'KeyG') {
        startGame();
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      if (!isActiveMode()) {
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
      if (!isActiveMode()) {
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
      if (!event.matches) {
        stopLoop();
        window.__SE_GALAGA_ACTIVE = false;
        heroGrid.classList.remove('is-galaga');
        heroGrid.classList.remove('has-galaga');
        heroGrid.dataset.galagaActive = 'false';
        return;
      }

      heroGrid.classList.add('has-galaga');
      if (!isActiveMode()) {
        enterIdleMode();
      }
    });

    reducedMotionQuery.addEventListener('change', function () {
      updateHint();
      if (isActiveMode()) {
        return;
      }
      if (shouldAnimateIdle()) {
        ensureLoopRunning();
      } else {
        stopLoop();
        render();
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

    heroGrid.classList.add('has-galaga');
    sizeCanvas();
    updateUI();
    initScene();
    enterIdleMode();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroGalaga, { once: true });
  } else {
    initHeroGalaga();
  }
})();
