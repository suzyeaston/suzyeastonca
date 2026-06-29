(function () {
  function initHomeArcadeStart() {
    var hero = document.querySelector('[data-arcade-hero]');
    var button = document.querySelector('[data-home-start]');
    var status = document.querySelector('[data-arcade-status]');
    var target = document.getElementById('lousy-outages-teaser');
    if (!hero || !button || !target) return;

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var started = false;

    function scrollToLevel() {
      target.setAttribute('tabindex', '-1');
      target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
      window.setTimeout(function () { target.focus({ preventScroll: true }); }, reduceMotion ? 0 : 520);
    }

    button.addEventListener('click', function () {
      if (started) {
        scrollToLevel();
        return;
      }
      started = true;
      document.body.classList.add('is-game-started');
      hero.classList.add('is-starting');
      button.textContent = 'PLAYER 1 READY';
      button.setAttribute('aria-disabled', 'true');
      if (status) status.textContent = 'COIN ACCEPTED';

      if (reduceMotion) {
        if (status) status.textContent = 'LEVEL 01';
        scrollToLevel();
        return;
      }

      window.setTimeout(function () {
        hero.classList.add('is-signal-locking');
        if (status) status.textContent = 'LEVEL 01';
      }, 420);
      window.setTimeout(function () {
        if (status) status.textContent = 'LEVEL 01 // LOUSY OUTAGES';
        scrollToLevel();
      }, 980);
      window.setTimeout(function () {
        hero.classList.remove('is-starting', 'is-signal-locking');
        button.removeAttribute('aria-disabled');
        button.textContent = button.getAttribute('data-start-label') || 'PRESS START';
      }, 1500);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initHomeArcadeStart);
  else initHomeArcadeStart();
}());
