(function (root) {
  'use strict';

  if (!root || !root.document) {
    return;
  }

  var doc = root.document;
  var apiConfig = root.LoLyricCaptcha || {};

  function toMsSeconds(value) {
    var ttl = parseInt(value, 10);
    if (!Number.isFinite(ttl) || ttl <= 0) {
      ttl = 120;
    }
    return ttl * 1000;
  }

  function parseExpiry(value) {
    if (typeof value !== 'string' || value === '') {
      return null;
    }
    var time = Date.parse(value);
    if (Number.isNaN(time)) {
      return null;
    }
    return time;
  }

  function formatRemaining(ms) {
    if (ms <= 0) {
      return 'expired';
    }
    var totalSeconds = Math.floor(ms / 1000);
    var minutes = Math.floor(totalSeconds / 60);
    var seconds = totalSeconds % 60;
    var sec = seconds < 10 ? '0' + seconds : '' + seconds;
    return minutes + ':' + sec + ' remaining';
  }

  function initCaptcha(form) {
    if (!form) {
      return;
    }
    var container = form.querySelector('[data-lo-captcha]');
    if (!container) {
      return;
    }

    var promptEl = container.querySelector('[data-lo-captcha-prompt]');
    var fragmentEl = container.querySelector('[data-lo-captcha-fragment]');
    var refreshBtn = container.querySelector('[data-lo-captcha-refresh]');
    var inputEl = container.querySelector('[data-lo-captcha-input]');
    var tokenInput = form.querySelector('[data-lo-captcha-token]');
    var noteEl = container.querySelector('.lo-subscribe__note');
    var baseNote = noteEl ? noteEl.textContent : '';
    var countdownTimer = null;
    var currentExpiry = parseExpiry(container.getAttribute('data-expires'));
    var requestInFlight = false;

    function clearCountdown() {
      if (countdownTimer) {
        root.clearInterval(countdownTimer);
        countdownTimer = null;
      }
    }

    function updateCountdown() {
      if (!noteEl) {
        return;
      }
      if (!currentExpiry) {
        noteEl.textContent = baseNote;
        return;
      }
      var remaining = currentExpiry - Date.now();
      if (remaining <= 0) {
        noteEl.textContent = baseNote + ' (expired)';
        return;
      }
      noteEl.textContent = baseNote + ' (' + formatRemaining(remaining) + ')';
    }

    function startCountdown() {
      clearCountdown();
      updateCountdown();
      if (!currentExpiry) {
        return;
      }
      countdownTimer = root.setInterval(function () {
        if (doc.hidden) {
          return;
        }
        updateCountdown();
        if (currentExpiry && currentExpiry <= Date.now()) {
          clearCountdown();
          updateCountdown();
        }
      }, 1000);
    }

    function applyChallenge(challenge) {
      if (!challenge || typeof challenge !== 'object') {
        return;
      }
      var fragment = String(challenge.fragment || '');
      var prompt = String(challenge.prompt || (promptEl ? promptEl.textContent : ''));
      var token = String(challenge.token || '');
      var expiresAt = parseExpiry(challenge.expires_at);
      if (!expiresAt) {
        expiresAt = Date.now() + toMsSeconds(challenge.ttl || apiConfig.ttl);
      }
      currentExpiry = expiresAt;

      container.setAttribute('data-token', token);
      container.setAttribute('data-expires', new Date(expiresAt).toISOString());

      if (promptEl) {
        promptEl.textContent = prompt;
      }
      if (fragmentEl) {
        fragmentEl.textContent = '“' + fragment + '” …';
      }
      if (tokenInput) {
        tokenInput.value = token;
      }
      if (inputEl) {
        inputEl.value = '';
        try {
          inputEl.focus();
        } catch (err) {
          // ignore focus errors
        }
      }

      startCountdown();
    }

    function toggleRefresh(disabled) {
      if (!refreshBtn) {
        return;
      }
      refreshBtn.disabled = !!disabled;
      refreshBtn.setAttribute('aria-busy', disabled ? 'true' : 'false');
    }

    function fetchChallenge() {
      if (!apiConfig.endpoint || requestInFlight) {
        return;
      }
      requestInFlight = true;
      toggleRefresh(true);

      var headers = {};
      if (apiConfig.nonce) {
        headers['X-WP-Nonce'] = apiConfig.nonce;
      }

      root.fetch(apiConfig.endpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: headers
      }).then(function (response) {
        if (!response || typeof response.json !== 'function') {
          throw new Error('invalid response');
        }
        return response.json();
      }).then(function (payload) {
        applyChallenge(payload);
      }).catch(function () {
        // keep existing challenge on failure
      }).finally(function () {
        requestInFlight = false;
        toggleRefresh(false);
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', function (event) {
        event.preventDefault();
        fetchChallenge();
      });
    }

    doc.addEventListener('visibilitychange', function () {
      if (!doc.hidden) {
        startCountdown();
      }
    });

    updateCountdown();
    startCountdown();
  }

  var forms = doc.querySelectorAll('[data-lo-subscribe-form]');
  if (!forms || !forms.length) {
    return;
  }

  Array.prototype.forEach.call(forms, initCaptcha);
})(typeof window !== 'undefined' ? window : this);
