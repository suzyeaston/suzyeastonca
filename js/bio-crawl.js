document.addEventListener('DOMContentLoaded', () => {
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const holdMs = 1800;
  const speed = 18; // px/sec
  const resumeDelayMs = 900;
  const dragThreshold = 6;

  let rafId = null;
  let lastTime = null;
  let holdUntil = performance.now() + holdMs;
  let autoScrollEnabled = !prefersReducedMotion;

  let isPointerDown = false;
  let isDragging = false;
  let activePointerId = null;
  let startY = 0;
  let startScrollTop = 0;
  let maxMovement = 0;
  let suppressClick = false;
  let resumeTimer = null;
  let hintTimer = null;
  let pointerHintShown = false;

  const hint = document.createElement('div');
  hint.className = 'bio-crawl-hint';
  hint.textContent = 'Drag / Scroll';
  wrap.appendChild(hint);

  const resetToStart = () => {
    wrap.scrollTop = 0;
    window.scrollTo(0, 0);
  };

  const ensureTitleVisible = () => {
    const title = wrap.querySelector('.bio-crawl-title');
    if (!title) {
      return;
    }

    const titleRect = title.getBoundingClientRect();
    const wrapRect = wrap.getBoundingClientRect();
    if (titleRect.top > wrapRect.bottom - 40) {
      wrap.scrollTop = Math.max(0, title.offsetTop - Math.round(wrap.clientHeight * 0.35));
    }
  };

  const resetAndReveal = () => {
    resetToStart();
    ensureTitleVisible();
  };

  const showHintBriefly = (duration = 2500) => {
    hint.classList.remove('is-hidden');
    window.clearTimeout(hintTimer);
    hintTimer = window.setTimeout(() => {
      hint.classList.add('is-hidden');
    }, duration);
  };

  resetAndReveal();
  requestAnimationFrame(() => {
    resetAndReveal();
    requestAnimationFrame(resetAndReveal);
  });
  window.setTimeout(resetAndReveal, 50);
  window.setTimeout(resetAndReveal, 250);
  window.addEventListener('pageshow', resetAndReveal);
  showHintBriefly();

  const maxScrollTop = () => wrap.scrollHeight - wrap.clientHeight;

  const pauseAutoScroll = () => {
    autoScrollEnabled = false;
  };

  const resumeAutoScroll = (delay = 0) => {
    if (prefersReducedMotion) {
      return;
    }

    window.clearTimeout(resumeTimer);
    resumeTimer = window.setTimeout(() => {
      autoScrollEnabled = true;
      holdUntil = performance.now();
      if (!rafId) {
        rafId = requestAnimationFrame(step);
      }
    }, delay);
  };

  const step = (now) => {
    if (lastTime === null) {
      lastTime = now;
    }

    const deltaSeconds = Math.max(0, now - lastTime) / 1000;
    lastTime = now;

    if (autoScrollEnabled && !isDragging && now >= holdUntil) {
      const nextScrollTop = Math.min(maxScrollTop(), wrap.scrollTop + speed * deltaSeconds);
      wrap.scrollTop = nextScrollTop;

      if (wrap.scrollTop >= maxScrollTop() - 2) {
        rafId = null;
        return;
      }
    }

    rafId = requestAnimationFrame(step);
  };

  const endDrag = () => {
    if (!isPointerDown) {
      return;
    }

    isPointerDown = false;
    activePointerId = null;
    wrap.classList.remove('is-dragging');

    if (isDragging) {
      suppressClick = maxMovement >= dragThreshold;
      window.setTimeout(() => {
        suppressClick = false;
      }, 0);
    }

    isDragging = false;
    resumeAutoScroll(resumeDelayMs);
  };

  wrap.addEventListener('pointerdown', (event) => {
    if (event.button !== 0) {
      return;
    }

    if (event.target.closest('a')) {
      return;
    }

    if (!pointerHintShown) {
      pointerHintShown = true;
      showHintBriefly(1200);
    }

    window.clearTimeout(resumeTimer);
    pauseAutoScroll();

    isPointerDown = true;
    isDragging = false;
    activePointerId = event.pointerId;
    startY = event.clientY;
    startScrollTop = wrap.scrollTop;
    maxMovement = 0;

    wrap.setPointerCapture(event.pointerId);
  });

  wrap.addEventListener('pointermove', (event) => {
    if (!isPointerDown || event.pointerId !== activePointerId) {
      return;
    }

    const movement = Math.abs(event.clientY - startY);
    maxMovement = Math.max(maxMovement, movement);

    if (movement >= dragThreshold) {
      isDragging = true;
      wrap.classList.add('is-dragging');
    }

    if (isDragging) {
      event.preventDefault();
      wrap.scrollTop = startScrollTop - (event.clientY - startY);
    }
  });

  wrap.addEventListener('pointerup', (event) => {
    if (event.pointerId !== activePointerId) {
      return;
    }

    if (wrap.hasPointerCapture(event.pointerId)) {
      wrap.releasePointerCapture(event.pointerId);
    }

    endDrag();
  });

  wrap.addEventListener('pointercancel', endDrag);
  wrap.addEventListener('lostpointercapture', endDrag);

  wrap.addEventListener('wheel', () => {
    window.clearTimeout(resumeTimer);
    pauseAutoScroll();
    resumeAutoScroll(resumeDelayMs);
  }, { passive: true });

  wrap.addEventListener('click', (event) => {
    if (!suppressClick) {
      return;
    }

    const link = event.target.closest('a');
    if (link && maxMovement >= dragThreshold) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);

  if (!prefersReducedMotion) {
    rafId = requestAnimationFrame(step);
  }
});
