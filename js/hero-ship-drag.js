(function () {
  const ship = document.querySelector('.hero-ship');
  const heroGrid = document.querySelector('.hero-grid');
  const desktopQuery = window.matchMedia('(min-width: 860px)');
  const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

  if (!ship || !heroGrid || !desktopQuery.matches) {
    return;
  }

  const canvas = document.createElement('canvas');
  canvas.className = 'hero-ship-trail';
  canvas.setAttribute('aria-hidden', 'true');
  heroGrid.insertBefore(canvas, ship);

  const ctx = canvas.getContext('2d', { alpha: true });
  const particles = [];
  const margin = 12;
  let dpr = Math.max(1, window.devicePixelRatio || 1);
  let rafId = 0;
  let dragging = false;
  let pointerId = null;
  let offsetX = 0;
  let offsetY = 0;
  let prevX = null;
  let prevY = null;
  let resumeTimeout = 0;

  const getReducedMotion = () => reducedMotionQuery.matches;

  function resizeCanvas() {
    dpr = Math.max(1, window.devicePixelRatio || 1);
    const width = heroGrid.clientWidth;
    const height = heroGrid.clientHeight;
    canvas.width = Math.max(1, Math.round(width * dpr));
    canvas.height = Math.max(1, Math.round(height * dpr));
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';

    const left = parseFloat(ship.style.left);
    const top = parseFloat(ship.style.top);
    if (!Number.isNaN(left) && !Number.isNaN(top)) {
      setShipPosition(clampX(left), clampY(top));
    }
  }

  function clampX(x) {
    return Math.min(heroGrid.clientWidth - margin, Math.max(margin, x));
  }

  function clampY(y) {
    return Math.min(heroGrid.clientHeight - margin, Math.max(margin, y));
  }

  function setShipPosition(x, y) {
    ship.style.left = x + 'px';
    ship.style.top = y + 'px';
  }

  function getLocalPointerPosition(event) {
    const rect = heroGrid.getBoundingClientRect();
    return {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };
  }

  function spawnTrail(x, y, dx, dy) {
    if (getReducedMotion()) {
      return;
    }

    const speed = Math.hypot(dx, dy);
    if (speed < 0.8) {
      return;
    }

    const nx = dx / speed;
    const ny = dy / speed;
    const thrusterX = x;
    const thrusterY = y + ship.offsetHeight * 0.35;
    const count = Math.min(8, 2 + Math.floor(speed / 3));

    for (let i = 0; i < count; i += 1) {
      const jitter = (Math.random() - 0.5) * 0.6;
      const spread = (Math.random() - 0.5) * 1.2;
      const size = 1 + Math.floor(Math.random() * 3);
      const life = 10 + Math.random() * 10;
      particles.push({
        x: thrusterX + spread * 10,
        y: thrusterY + spread * 6,
        vx: (-nx + jitter) * (0.9 + Math.random() * 1.1),
        vy: (-ny + jitter * 0.7) * (0.9 + Math.random() * 1.1) + 0.2,
        life,
        maxLife: life,
        size,
        color: Math.random() > 0.45 ? '#9dfff8' : '#57f5ff',
      });
    }
  }

  function draw() {
    if (!ctx) {
      return;
    }

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (let i = particles.length - 1; i >= 0; i -= 1) {
      const p = particles[i];
      p.x += p.vx;
      p.y += p.vy;
      p.vx *= 0.96;
      p.vy *= 0.96;
      p.life -= 1;

      if (p.life <= 0) {
        particles.splice(i, 1);
        continue;
      }

      const alpha = p.life / p.maxLife;
      ctx.globalAlpha = alpha;
      ctx.fillStyle = p.color;
      ctx.fillRect(Math.round(p.x * dpr), Math.round(p.y * dpr), Math.max(1, Math.round(p.size * dpr)), Math.max(1, Math.round(p.size * dpr)));
    }

    ctx.globalAlpha = 1;

    if (dragging || particles.length) {
      rafId = window.requestAnimationFrame(draw);
    } else {
      rafId = 0;
    }
  }

  function startLoopIfNeeded() {
    if (!rafId && (dragging || particles.length)) {
      rafId = window.requestAnimationFrame(draw);
    }
  }

  function onPointerMove(event) {
    if (!dragging || event.pointerId !== pointerId) {
      return;
    }

    const local = getLocalPointerPosition(event);
    const x = clampX(local.x + offsetX);
    const y = clampY(local.y + offsetY);

    const dx = prevX === null ? 0 : x - prevX;
    const dy = prevY === null ? 0 : y - prevY;

    setShipPosition(x, y);
    spawnTrail(x, y, dx, dy);
    prevX = x;
    prevY = y;
    startLoopIfNeeded();
  }

  function endDrag(event) {
    if (!dragging || event.pointerId !== pointerId) {
      return;
    }

    dragging = false;
    ship.classList.remove('is-dragging');

    if (ship.hasPointerCapture(pointerId)) {
      ship.releasePointerCapture(pointerId);
    }

    pointerId = null;
    prevX = null;
    prevY = null;

    window.clearTimeout(resumeTimeout);
    if (!getReducedMotion()) {
      resumeTimeout = window.setTimeout(function () {
        ship.classList.add('hero-ship--autopilot');
        ship.style.left = '';
        ship.style.top = '';
      }, 1200);
    }

    startLoopIfNeeded();
  }

  ship.addEventListener('pointerdown', function (event) {
    if (event.button !== 0 && event.pointerType !== 'touch' && event.pointerType !== 'pen') {
      return;
    }

    event.preventDefault();
    window.clearTimeout(resumeTimeout);
    ship.classList.remove('hero-ship--autopilot');
    ship.classList.add('is-dragging');

    dragging = true;
    pointerId = event.pointerId;

    const rect = ship.getBoundingClientRect();
    offsetX = rect.left + rect.width / 2 - event.clientX;
    offsetY = rect.top + rect.height / 2 - event.clientY;

    ship.setPointerCapture(pointerId);

    const local = getLocalPointerPosition(event);
    prevX = clampX(local.x + offsetX);
    prevY = clampY(local.y + offsetY);
    setShipPosition(prevX, prevY);
    startLoopIfNeeded();
  });

  ship.addEventListener('pointermove', onPointerMove);
  ship.addEventListener('pointerup', endDrag);
  ship.addEventListener('pointercancel', endDrag);

  window.addEventListener('resize', resizeCanvas, { passive: true });
  desktopQuery.addEventListener('change', function (event) {
    if (!event.matches) {
      window.clearTimeout(resumeTimeout);
      dragging = false;
      particles.length = 0;
      ship.classList.remove('is-dragging');
      ship.classList.add('hero-ship--autopilot');
      ship.style.left = '';
      ship.style.top = '';
      if (rafId) {
        window.cancelAnimationFrame(rafId);
        rafId = 0;
      }
      if (ctx) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
      }
    }
  });

  resizeCanvas();
})();
