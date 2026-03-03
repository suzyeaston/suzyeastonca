document.addEventListener('DOMContentLoaded', () => {
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const holdMs = 1400;
  const speedPxPerSec = 14;
  const pauseMs = 900;
  const dragThreshold = 6;
  const pageScroller = document.scrollingElement || document.documentElement;
  const debugEnabled = new URLSearchParams(window.location.search).get('debug') === '1';

  let scroller = null;
  let rafId = 0;
  let lastTs = 0;
  let pausedUntil = 0;

  let isPointerDown = false;
  let isDragging = false;
  let activePointerId = null;
  let startY = 0;
  let startScrollTop = 0;
  let maxMovement = 0;
  let suppressClick = false;
  let holdTimer = 0;
  let hintTimer = 0;
  let pointerHintShown = false;

  const hint = document.createElement('div');
  hint.className = 'bio-crawl-hint';
  hint.textContent = 'Drag / Scroll';
  wrap.appendChild(hint);

  function getScroller(candidateWrap) {
    if (!candidateWrap) {
      return pageScroller;
    }

    if (candidateWrap.scrollHeight > candidateWrap.clientHeight + 2) {
      return candidateWrap;
    }

    return pageScroller;
  }

  function refreshScroller() {
    scroller = getScroller(wrap);
  }

  function atEnd(targetScroller) {
    return targetScroller.scrollTop >= (targetScroller.scrollHeight - targetScroller.clientHeight - 2);
  }

  function showHintBriefly(duration = 2500) {
    hint.classList.remove('is-hidden');
    window.clearTimeout(hintTimer);
    hintTimer = window.setTimeout(() => {
      hint.classList.add('is-hidden');
    }, duration);
  }

  function pauseAutoScroll(delay = pauseMs) {
    pausedUntil = performance.now() + delay;
  }

  function tick(ts) {
    if (!lastTs) {
      lastTs = ts;
    }

    const dt = (ts - lastTs) / 1000;
    lastTs = ts;

    if (!reduceMotion && ts > pausedUntil && !atEnd(scroller)) {
      scroller.scrollTop += speedPxPerSec * dt;
    }

    if (!atEnd(scroller)) {
      rafId = requestAnimationFrame(tick);
    } else {
      rafId = 0;
    }
  }

  function startAuto() {
    window.clearTimeout(holdTimer);
    cancelAnimationFrame(rafId);
    rafId = 0;
    lastTs = 0;

    holdTimer = window.setTimeout(() => {
      if (!reduceMotion) {
        rafId = requestAnimationFrame(tick);
      }
    }, holdMs);
  }

  function resetToStart() {
    refreshScroller();
    window.scrollTo(0, 0);
    scroller.scrollTop = 0;
  }

  function endDrag() {
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
    pauseAutoScroll();
  }

  resetToStart();
  if (debugEnabled) {
    console.log('[bio-crawl] wrap metrics', {
      scrollHeight: wrap.scrollHeight,
      clientHeight: wrap.clientHeight,
      usingPageScroller: scroller !== wrap,
    });
  }
  requestAnimationFrame(() => {
    resetToStart();
    requestAnimationFrame(resetToStart);
  });
  window.setTimeout(resetToStart, 50);
  window.setTimeout(resetToStart, 250);
  window.addEventListener('pageshow', () => {
    resetToStart();
    startAuto();
  });

  showHintBriefly();
  startAuto();

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

    pauseAutoScroll();

    isPointerDown = true;
    isDragging = false;
    activePointerId = event.pointerId;
    startY = event.clientY;
    startScrollTop = scroller.scrollTop;
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
      scroller.scrollTop = startScrollTop - (event.clientY - startY);
      pauseAutoScroll();
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
    pauseAutoScroll();
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

  window.addEventListener('resize', refreshScroller, { passive: true });
});
