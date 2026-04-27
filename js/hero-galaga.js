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
      rain: '#57f3ff',
      ferry: '#92ffba',
      magentaRain: '#ff48ce',
    };

    const canvas = document.createElement('canvas');
    canvas.className = 'hero-galaga-canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const ui = document.createElement('div');
    ui.className = 'hero-galaga-ui';
    ui.innerHTML = '' +
      '<p class="hero-galaga-status" data-galaga-status>Rain City Defense</p>' +
      '<p class="hero-galaga-scoreline">Score: <span data-galaga-score>000000</span> · Lives: <span data-galaga-lives>3</span> · Wave: <span data-galaga-wave>1</span></p>' +
      '<p class="hero-galaga-help">A/D or ←/→ · Space fire · Esc exit</p>' +
      '<p class="hero-galaga-wavecall" data-galaga-wavecall hidden></p>' +
      '<div class="hero-galaga-gameover" data-galaga-gameover hidden></div>';

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
    const waveCallEl = ui.querySelector('[data-galaga-wavecall]');
    const startButton = hint.querySelector('.hero-galaga-start');
    const hintTextEl = hint.querySelector('[data-galaga-hint-text]');

    const state = {
      mode: 'idle', // idle | playing | gameover
      width: 1,
      height: 1,
      dpr: Math.max(1, window.devicePixelRatio || 1),
      rafId: 0,
      score: 0,
      lives: 3,
      wave: 1,
      wavePending: false,
      player: null,
      playerBullets: [],
      enemyBullets: [],
      enemies: [],
      particles: [],
      enemyDir: 1,
      enemyStepTimer: 0,
      enemyFireTimer: 0,
      enemyDiveTimer: 0,
      keys: {
        left: false,
        right: false,
        fire: false,
      },
      lastShotAt: 0,
      lastFrame: 0,
      idleTick: 0,
      shakeUntil: 0,
      shakePower: 0,
      waveCallUntil: 0,
      playfield: {
        x: 0,
        y: 0,
        w: 1,
        h: 1,
      },
    };

    const waveCalls = [
      'WAVE 1 · RAIN CITY SIGNAL',
      'WAVE 2 · GASTOWN STATIC',
      'WAVE 3 · STEAM CLOCK SWARM',
      'WAVE 4 · ALIEN FOG ROLLING IN',
      'WAVE 5 · FALSE CREEK DISTRESS',
    ];

    function isPlaying() {
      return state.mode === 'playing';
    }

    function shouldAnimateIdle() {
      return !reducedMotionQuery.matches && desktopQuery.matches && state.mode === 'idle';
    }

    function nowMs() {
      return performance.now();
    }

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
      ui.style.maxWidth = Math.max(240, state.playfield.w - 24) + 'px';
      hint.style.left = state.playfield.x + 12 + 'px';
      hint.style.bottom = hintBottom + 'px';
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

      if (!isPlaying()) {
        render();
      }
    }

    function formatScore(value) {
      return String(Math.max(0, value)).padStart(6, '0');
    }

    function updateUI() {
      scoreEl.textContent = formatScore(state.score);
      livesEl.textContent = String(Math.max(0, state.lives));
      waveEl.textContent = String(state.wave);
    }

    function updateHint() {
      if (!hintTextEl || !startButton) {
        return;
      }

      if (isPlaying()) {
        hintTextEl.textContent = 'Esc to quit';
        startButton.hidden = true;
        return;
      }

      if (state.mode === 'gameover') {
        hintTextEl.textContent = 'Press G to reboot, or Esc to return';
      } else {
        hintTextEl.textContent = reducedMotionQuery.matches
          ? 'Press G to play (reduced motion)'
          : 'Press G to play';
      }
      startButton.hidden = false;
    }

    function showWaveCallout() {
      const index = Math.min(waveCalls.length - 1, Math.max(0, state.wave - 1));
      const label = waveCalls[index] || ('WAVE ' + state.wave + ' · RAIN CITY DEFENSE');
      if (waveCallEl) {
        waveCallEl.textContent = label;
        waveCallEl.hidden = false;
      }
      state.waveCallUntil = nowMs() + 1600;
    }

    function clearTransientFx() {
      state.particles = [];
      state.shakeUntil = 0;
      state.shakePower = 0;
      heroGrid.classList.remove('galaga-hit');
    }

    function makeEnemy(row, col, cfg) {
      const enemyW = 22;
      const enemyH = 16;
      const gapX = cfg.gapX;
      const gapY = cfg.gapY;
      const totalW = cfg.cols * enemyW + (cfg.cols - 1) * gapX;
      const startX = state.playfield.x + Math.max(10, (state.playfield.w - totalW) / 2);
      const startY = state.playfield.y + cfg.startY;
      const variants = ['drip', 'umbrella', 'ghost'];
      return {
        x: startX + col * (enemyW + gapX),
        y: startY + row * (enemyH + gapY),
        w: enemyW,
        h: enemyH,
        alive: true,
        type: variants[(row + col) % variants.length],
        baseX: startX + col * (enemyW + gapX),
        baseY: startY + row * (enemyH + gapY),
        diving: false,
        diveVx: 0,
        diveVy: 0,
      };
    }

    function spawnWave() {
      const cols = 7;
      const rows = 4;
      const cfg = {
        cols: cols,
        rows: rows,
        gapX: 18,
        gapY: 13,
        startY: 66,
      };

      state.enemies = [];
      for (let row = 0; row < rows; row += 1) {
        for (let col = 0; col < cols; col += 1) {
          state.enemies.push(makeEnemy(row, col, cfg));
        }
      }

      state.enemyDir = 1;
      state.enemyStepTimer = 0;
      state.enemyFireTimer = 0;
      state.enemyDiveTimer = 0;
      state.wavePending = false;
      showWaveCallout();
    }

    function initPlayer() {
      state.player = {
        w: 24,
        h: 14,
        x: state.playfield.x + state.playfield.w / 2 - 12,
        y: state.playfield.y + state.playfield.h - 14 - 18,
        speed: 320,
        invulnerableUntil: 0,
        hitFlashUntil: 0,
      };
      clampPlayerToPlayfield();
    }

    function resetGame() {
      state.score = 0;
      state.lives = 3;
      state.wave = 1;
      state.wavePending = false;
      state.playerBullets = [];
      state.enemyBullets = [];
      state.lastShotAt = 0;
      state.keys.left = false;
      state.keys.right = false;
      state.keys.fire = false;
      clearTransientFx();
      initPlayer();
      spawnWave();
      gameOverEl.hidden = true;
      gameOverEl.innerHTML = '';
      updateUI();
    }

    function ensureLoopRunning() {
      if (state.rafId || (!isPlaying() && !shouldAnimateIdle())) {
        return;
      }
      state.lastFrame = nowMs();
      state.rafId = window.requestAnimationFrame(loop);
    }

    function stopLoop() {
      if (state.rafId) {
        window.cancelAnimationFrame(state.rafId);
        state.rafId = 0;
      }
    }

    function initIdleScene() {
      state.playerBullets = [];
      state.enemyBullets = [];
      state.particles = [];
      initPlayer();
      state.enemies = [];
      for (let row = 0; row < 2; row += 1) {
        for (let col = 0; col < 5; col += 1) {
          state.enemies.push(makeEnemy(row, col, { cols: 5, gapX: 16, gapY: 12, startY: 82 }));
        }
      }
    }

    function enterIdleMode() {
      state.mode = 'idle';
      state.wavePending = false;
      state.keys.left = false;
      state.keys.right = false;
      state.keys.fire = false;
      state.idleTick = 0;
      clearTransientFx();

      window.__SE_GALAGA_ACTIVE = false;
      heroGrid.dataset.galagaActive = 'false';
      heroGrid.classList.remove('is-galaga');
      canvas.style.pointerEvents = 'none';
      gameOverEl.hidden = true;
      gameOverEl.innerHTML = '';
      if (waveCallEl) {
        waveCallEl.hidden = true;
      }
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
      if (!desktopQuery.matches || isPlaying()) {
        return;
      }

      stopLoop();
      canvas.tabIndex = 0;
      state.mode = 'playing';
      window.__SE_GALAGA_ACTIVE = true;
      heroGrid.dataset.galagaActive = 'true';
      heroGrid.classList.add('is-galaga');
      canvas.style.pointerEvents = 'auto';

      resetGame();
      updateHint();
      canvas.focus({ preventScroll: true });
      ensureLoopRunning();
    }

    function endGame(options) {
      if (!options || options.toIdle) {
        enterIdleMode();
        return;
      }

      state.mode = 'gameover';
      state.keys.left = false;
      state.keys.right = false;
      state.keys.fire = false;
      state.playerBullets = [];
      state.enemyBullets = [];
      window.__SE_GALAGA_ACTIVE = false;
      heroGrid.dataset.galagaActive = 'false';
      heroGrid.classList.remove('is-galaga');
      canvas.style.pointerEvents = 'none';

      gameOverEl.hidden = false;
      gameOverEl.innerHTML = '<strong>SIGNAL LOST</strong><br>Final score: ' + formatScore(state.score) + '<br>Press G to reboot or Esc to quit';
      updateHint();
      render();
      stopLoop();
    }

    function intersects(a, b) {
      return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
    }

    function circleOverlap(x1, y1, r1, x2, y2, r2) {
      const dx = x1 - x2;
      const dy = y1 - y2;
      return (dx * dx + dy * dy) <= (r1 + r2) * (r1 + r2);
    }

    function spawnExplosion(x, y, count, palette) {
      const colorset = palette || [colors.secondary, colors.accent, colors.rain, '#ffffff'];
      const total = count || 12;
      for (let i = 0; i < total; i += 1) {
        const a = Math.random() * Math.PI * 2;
        const speed = 40 + Math.random() * 180;
        state.particles.push({
          x: x,
          y: y,
          vx: Math.cos(a) * speed,
          vy: Math.sin(a) * speed,
          life: 0.28 + Math.random() * 0.36,
          ttl: 0.28 + Math.random() * 0.36,
          color: colorset[i % colorset.length],
          size: 1 + Math.random() * 2.6,
        });
      }
    }

    function triggerShake(power, durationMs) {
      if (reducedMotionQuery.matches) {
        return;
      }
      state.shakePower = Math.max(state.shakePower, power);
      state.shakeUntil = Math.max(state.shakeUntil, nowMs() + durationMs);
      heroGrid.classList.remove('galaga-hit');
      void heroGrid.offsetWidth;
      heroGrid.classList.add('galaga-hit');
    }

    function playerHitbox() {
      if (!state.player) {
        return null;
      }
      return {
        x: state.player.x + 5,
        y: state.player.y + 3,
        w: state.player.w - 10,
        h: state.player.h - 4,
      };
    }

    function isPlayerInvulnerable() {
      return Boolean(state.player && state.player.invulnerableUntil > nowMs());
    }

    function damagePlayer() {
      if (!state.player || isPlayerInvulnerable() || state.mode !== 'playing') {
        return;
      }

      state.lives -= 1;
      state.player.invulnerableUntil = nowMs() + 1500;
      state.player.hitFlashUntil = state.player.invulnerableUntil;
      spawnExplosion(state.player.x + state.player.w / 2, state.player.y + state.player.h / 2, 20, [colors.magentaRain, '#ffffff', colors.secondary]);
      triggerShake(4, 220);

      if (state.lives <= 0) {
        state.lives = 0;
        updateUI();
        endGame({ toIdle: false });
        return;
      }

      updateUI();
    }

    function destroyEnemy(enemy) {
      enemy.alive = false;
      const enemyScore = enemy.diving ? 250 : 100;
      state.score += enemyScore;
      spawnExplosion(enemy.x + enemy.w / 2, enemy.y + enemy.h / 2, enemy.diving ? 14 : 10);
    }

    function updateWaveProgression() {
      const aliveCount = state.enemies.filter(function (enemy) {
        return enemy.alive;
      }).length;

      if (aliveCount > 0 || state.wavePending || state.mode !== 'playing') {
        return;
      }

      state.wavePending = true;
      state.score += 150;
      updateUI();

      window.setTimeout(function () {
        if (state.mode !== 'playing') {
          return;
        }
        state.wave += 1;
        state.playerBullets = [];
        state.enemyBullets = [];
        spawnWave();
        updateUI();
      }, 850);
    }

    function handleKeys(dt, now) {
      if (!state.player) {
        return;
      }

      const dir = (state.keys.right ? 1 : 0) - (state.keys.left ? 1 : 0);
      state.player.x += dir * state.player.speed * dt;
      clampPlayerToPlayfield();

      if (state.keys.fire && now - state.lastShotAt > 170) {
        state.playerBullets.push({
          x: state.player.x + state.player.w / 2 - 2,
          y: state.player.y - 8,
          w: 4,
          h: 10,
          vy: -460,
        });
        state.lastShotAt = now;
      }
    }

    function updateEnemies(dt) {
      if (!state.enemies.length) {
        return;
      }

      const living = state.enemies.filter(function (enemy) {
        return enemy.alive;
      });
      if (!living.length) {
        return;
      }

      const stepInterval = Math.max(0.12, 0.44 - (state.wave - 1) * 0.03);
      state.enemyStepTimer += dt;

      if (state.enemyStepTimer >= stepInterval) {
        state.enemyStepTimer = 0;
        const stepX = 12 + state.wave * 1.4;
        const stepY = 11;

        let nextMinX = Infinity;
        let nextMaxX = -Infinity;

        living.forEach(function (enemy) {
          if (enemy.diving) {
            return;
          }
          const nextX = enemy.x + stepX * state.enemyDir;
          nextMinX = Math.min(nextMinX, nextX);
          nextMaxX = Math.max(nextMaxX, nextX + enemy.w);
        });

        const playfieldRight = state.playfield.x + state.playfield.w;
        const hitEdge = nextMinX <= state.playfield.x + 8 || nextMaxX >= playfieldRight - 8;

        living.forEach(function (enemy) {
          if (enemy.diving) {
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

      state.enemyDiveTimer += dt;
      const diveInterval = Math.max(0.9, 2.8 - state.wave * 0.22);
      if (state.enemyDiveTimer >= diveInterval) {
        state.enemyDiveTimer = 0;
        const candidates = living.filter(function (enemy) {
          return !enemy.diving;
        });

        if (candidates.length && Math.random() < Math.min(0.6, 0.22 + state.wave * 0.06)) {
          const diver = candidates[Math.floor(Math.random() * candidates.length)];
          const targetX = state.player ? state.player.x + state.player.w / 2 : diver.x + diver.w / 2;
          const fromX = diver.x + diver.w / 2;
          diver.diving = true;
          diver.diveVy = 120 + state.wave * 18;
          diver.diveVx = Math.max(-90, Math.min(90, (targetX - fromX) * 1.25));
        }
      }

      living.forEach(function (enemy) {
        if (!enemy.diving) {
          return;
        }

        enemy.x += enemy.diveVx * dt;
        enemy.y += enemy.diveVy * dt;
        enemy.diveVy += (110 + state.wave * 10) * dt;

        if (enemy.y > state.playfield.y + state.playfield.h + 20) {
          enemy.alive = false;
        }
      });

      state.enemyFireTimer += dt;
      const fireInterval = Math.max(0.35, 1.1 - (state.wave - 1) * 0.07);
      if (state.enemyFireTimer >= fireInterval) {
        state.enemyFireTimer = 0;
        const shooters = living.slice();
        if (shooters.length) {
          const shooter = shooters[Math.floor(Math.random() * shooters.length)];
          state.enemyBullets.push({
            x: shooter.x + shooter.w / 2 - 2,
            y: shooter.y + shooter.h,
            w: 4,
            h: 10,
            vy: 220 + state.wave * 24,
            rain: true,
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
          bullet.y + bullet.h > state.playfield.y &&
          bullet.y < state.playfield.y + state.playfield.h;
      });
    }

    function updateParticles(dt) {
      state.particles.forEach(function (p) {
        p.x += p.vx * dt;
        p.y += p.vy * dt;
        p.vx *= 0.98;
        p.vy *= 0.98;
        p.life -= dt;
      });
      state.particles = state.particles.filter(function (p) {
        return p.life > 0;
      });
    }

    function resolveCollisions() {
      const playerBox = playerHitbox();

      state.playerBullets = state.playerBullets.filter(function (bullet) {
        let hit = false;
        state.enemies.forEach(function (enemy) {
          if (!enemy.alive) {
            return;
          }
          const enemyHitbox = {
            x: enemy.x + 2,
            y: enemy.y + 1,
            w: enemy.w - 4,
            h: enemy.h - 2,
          };
          if (intersects(bullet, enemyHitbox)) {
            destroyEnemy(enemy);
            hit = true;
          }
        });
        return !hit;
      });

      if (state.player && playerBox) {
        state.enemyBullets = state.enemyBullets.filter(function (bullet) {
          if (bullet.y < state.playfield.y || bullet.y > state.playfield.y + state.playfield.h) {
            return false;
          }

          const hit = circleOverlap(
            bullet.x + bullet.w / 2,
            bullet.y + bullet.h / 2,
            3,
            playerBox.x + playerBox.w / 2,
            playerBox.y + playerBox.h / 2,
            Math.max(5, Math.min(playerBox.w, playerBox.h) * 0.36)
          );

          if (hit) {
            damagePlayer();
            return false;
          }
          return true;
        });

        state.enemies.forEach(function (enemy) {
          if (!enemy.alive) {
            return;
          }

          if (enemy.y > state.playfield.y + state.playfield.h || enemy.y + enemy.h < state.playfield.y) {
            return;
          }

          const enemyHitbox = {
            x: enemy.x + 2,
            y: enemy.y + 1,
            w: enemy.w - 4,
            h: enemy.h - 2,
          };
          if (intersects(playerBox, enemyHitbox)) {
            destroyEnemy(enemy);
            damagePlayer();
          }
        });
      }

      const invaded = state.enemies.some(function (enemy) {
        return enemy.alive && enemy.y + enemy.h >= state.playfield.y + state.playfield.h - 52;
      });

      if (invaded && state.mode === 'playing') {
        state.lives = 0;
        updateUI();
        endGame({ toIdle: false });
        return;
      }

      updateWaveProgression();
      updateUI();
    }

    function drawPlayer(now) {
      if (!state.player) {
        return;
      }

      const invulnerable = isPlayerInvulnerable();
      if (invulnerable && !reducedMotionQuery.matches) {
        const flicker = Math.floor(now / 80) % 2 === 0;
        if (!flicker) {
          return;
        }
      }

      const p = state.player;
      ctx.fillStyle = colors.ferry;
      ctx.fillRect(Math.round(p.x + 10), Math.round(p.y), 4, 3);
      ctx.fillRect(Math.round(p.x + 7), Math.round(p.y + 3), 10, 4);
      ctx.fillRect(Math.round(p.x + 3), Math.round(p.y + 7), 18, 4);
      ctx.fillRect(Math.round(p.x), Math.round(p.y + 11), 24, 3);
      ctx.fillStyle = colors.rain;
      ctx.fillRect(Math.round(p.x + 11), Math.round(p.y + 5), 2, 4);

      if (invulnerable) {
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.75)';
        ctx.lineWidth = 1;
        ctx.strokeRect(Math.round(p.x - 2), Math.round(p.y - 2), p.w + 4, p.h + 4);
      }
    }

    function drawEnemy(enemy, yOffset) {
      const drawY = enemy.y + (yOffset || 0);
      const isGhost = enemy.type === 'ghost';
      const isUmbrella = enemy.type === 'umbrella';
      const baseColor = isGhost ? '#b983ff' : (isUmbrella ? colors.secondary : colors.magentaRain);
      const eyeColor = isGhost ? colors.rain : colors.accent;

      ctx.fillStyle = baseColor;
      ctx.fillRect(Math.round(enemy.x + 2), Math.round(drawY), enemy.w - 4, 3);
      ctx.fillRect(Math.round(enemy.x), Math.round(drawY + 3), enemy.w, 5);
      ctx.fillRect(Math.round(enemy.x + 4), Math.round(drawY + 8), enemy.w - 8, 3);
      if (isUmbrella) {
        ctx.fillRect(Math.round(enemy.x + enemy.w / 2 - 1), Math.round(drawY + 11), 2, 4);
      }
      ctx.fillStyle = eyeColor;
      ctx.fillRect(Math.round(enemy.x + 5), Math.round(drawY + 4), 2, 2);
      ctx.fillRect(Math.round(enemy.x + enemy.w - 7), Math.round(drawY + 4), 2, 2);
    }

    function drawBullets() {
      ctx.fillStyle = colors.accent;
      state.playerBullets.forEach(function (bullet) {
        ctx.fillRect(Math.round(bullet.x), Math.round(bullet.y), bullet.w, bullet.h);
      });

      state.enemyBullets.forEach(function (bullet) {
        ctx.fillStyle = bullet.rain ? colors.magentaRain : colors.primary;
        ctx.fillRect(Math.round(bullet.x), Math.round(bullet.y), bullet.w, bullet.h);
        if (bullet.rain) {
          ctx.fillStyle = 'rgba(255, 255, 255, 0.72)';
          ctx.fillRect(Math.round(bullet.x + 1), Math.round(bullet.y + 2), 2, 2);
        }
      });
    }

    function drawParticles() {
      state.particles.forEach(function (p) {
        const alpha = Math.max(0, p.life / p.ttl);
        ctx.fillStyle = p.color;
        ctx.globalAlpha = alpha;
        ctx.fillRect(Math.round(p.x), Math.round(p.y), p.size, p.size);
      });
      ctx.globalAlpha = 1;
    }

    function getShakeOffset(now) {
      if (reducedMotionQuery.matches || now >= state.shakeUntil) {
        return { x: 0, y: 0 };
      }
      const power = state.shakePower * ((state.shakeUntil - now) / 220);
      return {
        x: (Math.random() * 2 - 1) * power,
        y: (Math.random() * 2 - 1) * power,
      };
    }

    function render(now) {
      const renderNow = now || nowMs();
      const shake = getShakeOffset(renderNow);

      if (renderNow >= state.shakeUntil) {
        state.shakePower = 0;
        heroGrid.classList.remove('galaga-hit');
      }

      if (waveCallEl && renderNow >= state.waveCallUntil) {
        waveCallEl.hidden = true;
      }

      ctx.clearRect(0, 0, state.width, state.height);
      ctx.save();
      ctx.translate(shake.x, shake.y);
      ctx.beginPath();
      ctx.rect(state.playfield.x, state.playfield.y, state.playfield.w, state.playfield.h);
      ctx.clip();
      ctx.fillStyle = isPlaying() ? 'rgba(0, 0, 0, 0.46)' : 'rgba(0, 0, 0, 0.18)';
      ctx.fillRect(state.playfield.x, state.playfield.y, state.playfield.w, state.playfield.h);

      drawPlayer(renderNow);

      const idleBob = isPlaying() ? 0 : Math.sin(state.idleTick * 1.35) * 3;
      state.enemies.forEach(function (enemy, index) {
        if (!enemy.alive) {
          return;
        }
        const offset = isPlaying()
          ? (enemy.diving ? Math.sin(renderNow * 0.015 + index) * 0.8 : 0)
          : idleBob + Math.sin(index * 0.7 + state.idleTick) * 1.25;
        drawEnemy(enemy, offset);
      });

      drawBullets();
      drawParticles();

      if (!isPlaying()) {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.22)';
        ctx.fillRect(state.playfield.x, state.playfield.y + state.playfield.h - 74, state.playfield.w, 74);
      }

      ctx.restore();
    }

    function loop(now) {
      const dt = Math.min(0.033, (now - state.lastFrame) / 1000 || 0.016);
      state.lastFrame = now;

      if (isPlaying()) {
        handleKeys(dt, now);
        updateEnemies(dt);
        updateBullets(dt);
        updateParticles(dt);
        resolveCollisions();
      } else if (state.mode === 'idle') {
        state.idleTick += dt;
      }

      render(now);

      if (isPlaying() || shouldAnimateIdle()) {
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
      if (!isPlaying()) {
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

      if (!typing && code === 'KeyG' && !isPlaying()) {
        startGame();
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      if (!isPlaying() && state.mode !== 'gameover') {
        return;
      }

      if (typing) {
        return;
      }

      if (code === 'Escape') {
        endGame({ toIdle: true });
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      if (!isPlaying()) {
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
      if (!isPlaying()) {
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
      if (!isPlaying()) {
        enterIdleMode();
      }
    });

    reducedMotionQuery.addEventListener('change', function () {
      updateHint();
      if (isPlaying()) {
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
    enterIdleMode();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroGalaga, { once: true });
  } else {
    initHeroGalaga();
  }
})();
