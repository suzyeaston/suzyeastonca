(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(root);
  } else {
    var api = factory(root);
    root.LousyOutagesApp = api;
    if (root && root.document && root.LousyOutagesConfig) {
      api.init(root.LousyOutagesConfig);
    }
  }
})(typeof globalThis !== 'undefined' ? globalThis : (typeof self !== 'undefined' ? self : this), function (rootRef) {
  'use strict';

  var globalRoot = rootRef && typeof rootRef.setTimeout === 'function' ? rootRef : (typeof globalThis !== 'undefined' ? globalThis : rootRef);

  var POLL_MS = 10000;
  var MAX_DELAY = 60000;
  var STALE_REFRESH_THRESHOLD_MS = 6 * 60 * 1000;
  var STALE_REFRESH_COOLDOWN_MS = 3 * 60 * 1000;
  var DEGRADE_THRESHOLD = 0.5;
  var RECOVER_THRESHOLD = 0.7;
  var MANUAL_REFRESH_RETRY_DELAY = 1500;
  var MANUAL_REFRESH_MAX_ATTEMPTS = 4;

  var STATUS_MAP = {
    operational: { code: 'operational', label: 'Operational', className: 'status--operational' },
    degraded: { code: 'degraded', label: 'Degraded', className: 'status--degraded' },
    major: { code: 'major', label: 'Major Outage', className: 'status--outage' },
    outage: { code: 'outage', label: 'Outage', className: 'status--outage' },
    maintenance: { code: 'maintenance', label: 'Maintenance', className: 'status--maintenance' },
    unknown: { code: 'unknown', label: 'Unknown', className: 'status--unknown' }
  };

  var SNARKS = {
    aws: [
      'us-east-1 is a lifestyle choice.',
      'Somewhere a Lambda forgot to set its alarm.'
    ],
    github: ['Push it real good, but maybe later.'],
    slack: ['Time to revive the email thread.'],
    default: ['Hold tight—someone’s jiggling the ethernet cable.']
  };

  var state = {
    root: globalRoot,
    doc: null,
    fetchImpl: null,
    endpoint: '',
    refreshEndpoint: '',
    refreshNonce: '',
    subscribeEndpoint: '',
    baseDelay: POLL_MS,
    delay: POLL_MS,
    maxDelay: MAX_DELAY,
    timer: null,
    countdownTimer: null,
    nextRefreshAt: null,
    etag: null,
    visibilityPaused: false,
    container: null,
    grid: null,
    fetchedEl: null,
    fetchedLabelEl: null,
    fetchedLabel: 'Fetched',
    countdownEl: null,
    refreshButton: null,
    degradedBadge: null,
    trendingBanner: null,
    trendingText: null,
    trendingReasons: null,
    loadingEl: null,
    fetchedAt: null,
    isRefreshing: false,
    pendingManual: false,
    lastFetchStartedAt: null,
    manualQueued: false,
    staleRefreshQueued: false,
    staleRefreshInFlight: false,
    lastStaleRefreshAttempt: null,
    degradedDueToStale: false
  };

  function delay(ms) {
    return new Promise(function (resolve) {
      var timerRoot = state.root && typeof state.root.setTimeout === 'function'
        ? state.root
        : (globalRoot && typeof globalRoot.setTimeout === 'function' ? globalRoot : null);
      if (timerRoot) {
        timerRoot.setTimeout(resolve, ms);
      } else {
        setTimeout(resolve, ms);
      }
    });
  }

  function normalizeStatus(code) {
    var key = String(code || '').toLowerCase();

    switch (key) {
      case 'none':
        key = 'operational';
        break;
      case 'minor_outage':
      case 'partial_outage':
      case 'degraded_performance':
        key = 'degraded';
        break;
      case 'major_outage':
      case 'critical':
        key = 'major';
        break;
    }

    return STATUS_MAP[key] || STATUS_MAP.unknown;
  }

  function snarkOutage(provider, status, summary) {
    var normalized = normalizeStatus(status);
    if ('operational' === normalized.code || 'maintenance' === normalized.code) {
      return summary || normalized.label;
    }
    var providerKey = String(provider || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    var lines = SNARKS[providerKey] || SNARKS.default;
    if (!lines.length) {
      return summary || 'Something feels off.';
    }
    var index = Math.floor(Math.random() * lines.length);
    return lines[index] || summary || 'Something feels off.';
  }

  function escapeSelector(id) {
    if (typeof id !== 'string') {
      return '';
    }
    if (typeof CSS !== 'undefined' && CSS.escape) {
      return CSS.escape(id);
    }
    return id.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function appendQuery(url, key, value) {
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    return url + separator + key + '=' + encodeURIComponent(value);
  }

  function clearChildren(el) {
    if (!el) {
      return;
    }
    if (typeof el.innerHTML === 'string') {
      el.innerHTML = '';
    }
    if (Array.isArray(el.children)) {
      el.children.length = 0;
    }
  }

  function scheduleNext(delay) {
    if (state.timer && state.root) {
      state.root.clearTimeout(state.timer);
      state.timer = null;
    }
    if (!isDocumentVisible()) {
      state.visibilityPaused = true;
      state.nextRefreshAt = null;
      stopCountdown();
      if (state.countdownEl) {
        state.countdownEl.textContent = 'Auto-refresh paused';
      }
      return;
    }
    var base = state.baseDelay || POLL_MS;
    var hasExplicitDelay = typeof delay === 'number' && !Number.isNaN(delay);
    var desired = hasExplicitDelay ? delay : (state.delay || base);
    if (desired < 0) {
      desired = 0;
    }
    var minimum = hasExplicitDelay ? 0 : (base < POLL_MS ? Math.max(100, base) : 1000);
    var nextDelay = desired;
    if (!hasExplicitDelay && nextDelay < minimum) {
      nextDelay = minimum;
    }
    state.delay = nextDelay;
    state.visibilityPaused = false;
    state.nextRefreshAt = Date.now() + nextDelay;
    startCountdown();
    if (state.root) {
      state.timer = state.root.setTimeout(function () {
        state.timer = null;
        refreshSummary(false, false);
      }, nextDelay);
    }
  }

  function startCountdown() {
    if (!state.root || !state.countdownEl) {
      return;
    }
    if (state.countdownTimer) {
      state.root.clearInterval(state.countdownTimer);
    }
    updateCountdown();
    state.countdownTimer = state.root.setInterval(updateCountdown, 1000);
  }

  function stopCountdown() {
    if (state.countdownTimer && state.root) {
      state.root.clearInterval(state.countdownTimer);
      state.countdownTimer = null;
    }
  }

  function updateCountdown() {
    if (!state.countdownEl) {
      return;
    }
    if (!state.nextRefreshAt) {
      state.countdownEl.textContent = 'Auto-refresh paused';
      return;
    }
    var diff = state.nextRefreshAt - Date.now();
    if (diff <= 0) {
      state.countdownEl.textContent = 'Refreshing…';
      return;
    }
    state.countdownEl.textContent = '';
  }

  function updateFetched() {
    if (!state.fetchedEl || !state.fetchedAt) {
      return;
    }
    if (state.fetchedLabelEl) {
      state.fetchedLabelEl.textContent = state.fetchedLabel || 'Fetched';
    }
    var date = new Date(state.fetchedAt);
    if (Number.isNaN(date.getTime())) {
      state.fetchedEl.textContent = state.fetchedAt;
      return;
    }
    try {
      var formatted = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      }).format(date);
      state.fetchedEl.textContent = formatted;
    } catch (err) {
      state.fetchedEl.textContent = date.toISOString();
    }
  }

  function setLoading(isLoading) {
    if (!state.refreshButton) {
      return;
    }
    state.refreshButton.disabled = !!isLoading;
    state.refreshButton.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    state.refreshButton.textContent = isLoading ? 'Refreshing…' : 'Refresh now';
  }

  function toggleSpinner(visible) {
    if (!state.loadingEl) {
      return;
    }
    if (visible) {
      state.loadingEl.removeAttribute('hidden');
    } else {
      state.loadingEl.setAttribute('hidden', 'hidden');
    }
  }

  function showDegraded(active, message) {
    if (!state.degradedBadge) {
      return;
    }
    if (active) {
      state.degradedBadge.textContent = message || 'AUTO-REFRESH DEGRADED';
      state.degradedBadge.removeAttribute('hidden');
    } else {
      state.degradedBadge.textContent = '';
      state.degradedBadge.setAttribute('hidden', 'hidden');
    }
  }

  function triggerStaleRefresh() {
    if (!state.refreshEndpoint || !state.fetchImpl) {
      return Promise.resolve();
    }
    if (state.staleRefreshInFlight) {
      return Promise.resolve();
    }

    state.staleRefreshQueued = false;
    state.staleRefreshInFlight = true;

    return callRefreshEndpoint()
      .catch(function () {
        return null;
      })
      .then(function () {
        return refreshSummary(false, true);
      })
      .finally(function () {
        state.staleRefreshInFlight = false;
      });
  }

  function maybeQueueStaleRefresh(fetchedIso) {
    if (!state.refreshEndpoint || !state.fetchImpl) {
      return;
    }

    var iso = typeof fetchedIso === 'string' ? fetchedIso : '';
    if (!iso) {
      if (state.degradedDueToStale) {
        state.degradedDueToStale = false;
        showDegraded(false);
      }
      return;
    }

    var parsed = Date.parse(iso);
    if (!Number.isFinite(parsed)) {
      return;
    }

    var age = Date.now() - parsed;
    if (age < STALE_REFRESH_THRESHOLD_MS) {
      if (state.degradedDueToStale) {
        state.degradedDueToStale = false;
        showDegraded(false);
      }
      return;
    }

    if (state.staleRefreshInFlight) {
      return;
    }

    var now = Date.now();
    if (state.lastStaleRefreshAttempt && (now - state.lastStaleRefreshAttempt) < STALE_REFRESH_COOLDOWN_MS) {
      return;
    }

    state.lastStaleRefreshAttempt = now;
    state.degradedDueToStale = true;
    showDegraded(true, 'AUTO-REFRESH STALE — refreshing feed');

    if (state.isRefreshing) {
      state.staleRefreshQueued = true;
      return;
    }

    triggerStaleRefresh();
  }

  function renderProviders(list) {
    if (!Array.isArray(list)) {
      return;
    }
    var orderedCards = [];
    list.forEach(function (provider) {
      if (!provider) {
        return;
      }
      var id = provider.id || provider.provider;
      if (!id) {
        return;
      }
      var card = null;
      if (state.grid) {
        card = state.grid.querySelector('[data-provider-id="' + escapeSelector(String(id)) + '"]');
        if (!card) {
          card = state.grid.querySelector('.provider-card[data-id="' + String(id) + '"]');
        }
      }
      if (!card && state.doc) {
        card = state.doc.querySelector('[data-provider-id="' + escapeSelector(String(id)) + '"]') || state.doc.querySelector('.provider-card[data-id="' + String(id) + '"]');
      }
      if (!card) {
        return;
      }
      orderedCards.push(card);
      var normalized = normalizeStatus(provider.status || provider.overall || provider.overall_status || provider.stateCode);
      if (card.classList && card.classList.contains('provider-card')) {
        updateLegacyCard(card, provider, normalized);
      } else {
        updateModernCard(card, provider, normalized);
      }
    });
    if (state.loadingEl) {
      if (Array.isArray(list) && list.length) {
        toggleSpinner(false);
      } else {
        var hasAnyCard = false;
        if (state.grid) {
          hasAnyCard = !!state.grid.querySelector('[data-provider-id]');
        }
        toggleSpinner(!hasAnyCard);
      }
    }
    if (state.grid && orderedCards.length) {
      orderedCards.forEach(function (card) {
        if (card.parentNode === state.grid) {
          state.grid.appendChild(card);
        }
      });
    }
  }


  function getSummaryText(provider) {
    if (!provider) {
      return '';
    }
    return provider.summary || provider.message || '';
  }

  function updateLegacyCard(card, provider, normalized) {
    var badge = card.querySelector('.status-badge');
    if (badge) {
      badge.textContent = provider.status_label || normalized.label;
      badge.className = 'status-badge ' + (provider.status_class || normalized.className);
      if (badge.dataset) {
        badge.dataset.status = provider.status || provider.overall || provider.overall_status || normalized.code;
      }
    }
    var summary = card.querySelector('.provider-card__summary');
    if (summary) {
      summary.textContent = getSummaryText(provider);
    }
    var snark = card.querySelector('.provider-card__snark');
    if (snark) {
      snark.textContent = snarkOutage(provider.name || provider.provider || provider.id, normalized.label, getSummaryText(provider));
    }
    var incidentsWrap = card.querySelector('.incidents');
    if (incidentsWrap) {
      clearChildren(incidentsWrap);
      incidentsWrap.textContent = '';
      var incidents = Array.isArray(provider.incidents) ? provider.incidents : [];
      if (!incidents.length) {
        incidentsWrap.textContent = 'No active incidents. Go write a chorus.';
      } else if (state.doc && typeof state.doc.createElement === 'function') {
        incidents.forEach(function (incident) {
          var item = state.doc.createElement('p');
          var impact = incident.impact ? String(incident.impact).replace(/^[a-z]/, function (c) { return c.toUpperCase(); }) : 'Unknown';
          var updated = formatTimestamp(incident.updated_at || incident.updatedAt);
          var summaryText = incident.summary ? ' — ' + String(incident.summary) : '';
          item.textContent = impact + (updated ? ' • ' + updated : '') + summaryText;
          incidentsWrap.appendChild(item);
        });
      }
    }
    var link = card.querySelector('.provider-link');
    if (link && provider.url) {
      link.setAttribute('href', provider.url);
    }
  }

  function updateModernCard(card, provider, normalized) {
    var badge = card.querySelector('[data-lo-badge]');
    if (badge) {
      badge.textContent = provider.status_label || normalized.label;
      badge.className = 'lo-pill ' + (provider.status_class || normalized.className);
    }
    var summary = card.querySelector('[data-lo-summary]');
    if (summary) {
      summary.textContent = getSummaryText(provider);
    }
    var error = card.querySelector('[data-lo-error]');
    if (error) {
      var httpCode = typeof provider.http_code === 'number' ? provider.http_code : null;
      var hasError = !!provider.error && (httpCode === null || httpCode === 0 || httpCode >= 400);
      error.textContent = hasError ? String(provider.error) : '';
      if (hasError) {
        error.removeAttribute('hidden');
      } else {
        error.setAttribute('hidden', 'hidden');
      }
    }
    var componentsWrap = card.querySelector('[data-lo-components]');
    if (componentsWrap) {
      componentsWrap.innerHTML = '';
      var components = Array.isArray(provider.components) ? provider.components.filter(function (component) {
        if (!component) {
          return false;
        }
        var status = String(component.status || '').toLowerCase();
        return status && status !== 'operational';
      }) : [];
      if (components.length) {
        var title = state.doc.createElement('h4');
        title.className = 'lo-components__title';
        title.textContent = 'Impacted components';
        componentsWrap.appendChild(title);
        var listEl = state.doc.createElement('ul');
        listEl.className = 'lo-components__list';
        components.forEach(function (component) {
          var item = state.doc.createElement('li');
          var name = state.doc.createElement('span');
          name.className = 'lo-component-name';
          name.textContent = component.name || 'Component';
          item.appendChild(name);
          var statusEl = state.doc.createElement('span');
          statusEl.className = 'lo-component-status';
          statusEl.textContent = component.status_label || normalizeStatus(component.status).label;
          item.appendChild(statusEl);
          listEl.appendChild(item);
        });
        componentsWrap.appendChild(listEl);
      }
    }
    var incidentsWrap = card.querySelector('[data-lo-incidents]');
    if (incidentsWrap) {
      incidentsWrap.innerHTML = '';
      var incidents = Array.isArray(provider.incidents) ? provider.incidents : [];
      if (!incidents.length) {
        var empty = state.doc.createElement('p');
        empty.className = 'lo-empty';
        empty.textContent = 'No active incidents.';
        incidentsWrap.appendChild(empty);
      } else {
        var ul = state.doc.createElement('ul');
        ul.className = 'lo-inc-list';
        incidents.forEach(function (incident) {
          var li = state.doc.createElement('li');
          li.className = 'lo-inc-item';
          var title = state.doc.createElement('p');
          title.className = 'lo-inc-title';
          title.textContent = incident.name || 'Incident';
          li.appendChild(title);
          var meta = state.doc.createElement('p');
          meta.className = 'lo-inc-meta';
          var impact = incident.impact ? String(incident.impact).replace(/^[a-z]/, function (c) { return c.toUpperCase(); }) : 'Unknown';
          var updated = formatTimestamp(incident.updated_at || incident.updatedAt || incident.started_at || incident.startedAt);
          meta.textContent = impact + (updated ? ' • ' + updated : '');
          li.appendChild(meta);
          if (incident.summary) {
            var details = state.doc.createElement('p');
            details.className = 'lo-inc-summary';
            details.textContent = String(incident.summary);
            li.appendChild(details);
          }
          if (incident.url) {
            var link = state.doc.createElement('a');
            link.className = 'lo-status-link';
            link.href = incident.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'View incident';
            li.appendChild(link);
          }
          ul.appendChild(li);
        });
        incidentsWrap.appendChild(ul);
      }
    }
    var statusLink = card.querySelector('[data-lo-status-url]');
    if (statusLink) {
      var destination = provider.url || provider.link;
      if (destination) {
        statusLink.href = destination;
        statusLink.removeAttribute('hidden');
      } else {
        statusLink.setAttribute('hidden', 'hidden');
      }
    }
  }
  function formatTimestamp(iso) {
    if (!iso) {
      return '';
    }
    var date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
      return '';
    }
    try {
      return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      }).format(date);
    } catch (err) {
      return date.toISOString();
    }
  }

  function handleSubscribeSubmit(event) {
    event.preventDefault();
    var form = event.currentTarget;
    if (!form) {
      return;
    }
    var statusEl = form.querySelector('[data-lo-subscribe-status]');
    var emailInput = form.querySelector('input[name="email"]');
    var honeypot = form.querySelector('input[name="website"]');
    var nonceInput = form.querySelector('input[name="_wpnonce"]');
    var challengeInput = form.querySelector('input[name="challenge_response"]');
    var endpoint = form.getAttribute('action') || state.subscribeEndpoint;
    if (!endpoint || !state.fetchImpl) {
      return;
    }
    var email = emailInput ? emailInput.value.trim() : '';
    if (!email) {
      setSubscribeStatus(statusEl, 'Please enter your email.', true);
      return;
    }
    if (honeypot && honeypot.value) {
      setSubscribeStatus(statusEl, 'Submission blocked.', true);
      return;
    }
    if (!challengeInput || !challengeInput.value.trim()) {
      setSubscribeStatus(statusEl, 'Please answer the human check.', true);
      return;
    }
    setSubscribeStatus(statusEl, 'Sending…');
    form.querySelectorAll('button, input[type="submit"]').forEach(function (btn) {
      btn.disabled = true;
    });
    var payload = {
      email: email,
      website: honeypot ? honeypot.value : '',
      _wpnonce: nonceInput ? nonceInput.value : '',
      challenge_response: challengeInput ? challengeInput.value.trim() : ''
    };

    state.fetchImpl(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        if (!res) {
          throw new Error('No response');
        }
        return res.json().catch(function () { return {}; }).then(function (body) {
          var payload = body;
          if (body && typeof body === 'object' && body.data) {
            payload = body.data;
          }
          var message = payload && payload.message ? String(payload.message) : null;
          var success = res.ok && (!body || body.success !== false);
          if (!success) {
            throw new Error(message || 'Subscription failed.');
          }
          return { message: message || 'Check your email to confirm your subscription (peek at spam if it’s missing).' };
        });
      })
      .then(function (result) {
        setSubscribeStatus(statusEl, result && result.message ? result.message : 'Check your email to confirm your subscription (peek at spam if it’s missing).');
        form.reset();
      })
      .catch(function (error) {
        setSubscribeStatus(statusEl, error && error.message ? error.message : 'Subscription failed.', true);
      })
      .finally(function () {
        form.querySelectorAll('button, input[type="submit"]').forEach(function (btn) {
          btn.disabled = false;
        });
      });
  }

  function setSubscribeStatus(el, message, isError) {
    if (!el) {
      return;
    }
    el.textContent = message || '';
    if (isError) {
      el.classList.add('lo-subscribe__status--error');
    } else {
      el.classList.remove('lo-subscribe__status--error');
    }
  }

  function enhanceSubscribeForms() {
    if (!state.doc) {
      return;
    }
    var forms = state.doc.querySelectorAll('[data-lo-subscribe-form]');
    forms.forEach(function (form) {
      if (!form || form.dataset.loEnhanced) {
        return;
      }
      form.dataset.loEnhanced = '1';
      form.addEventListener('submit', handleSubscribeSubmit);
    });
  }

  function isDocumentVisible() {
    if (!state.doc || typeof state.doc.visibilityState !== 'string') {
      return true;
    }
    return state.doc.visibilityState === 'visible';
  }

  function updateTrendingBanner(data) {
    if (!state.trendingBanner) {
      return;
    }

    var info = data || {};
    var active = !!info.trending;
    var signals = Array.isArray(info.signals) ? info.signals.filter(Boolean) : [];

    if (active) {
      state.trendingBanner.removeAttribute('hidden');
    } else {
      state.trendingBanner.setAttribute('hidden', 'hidden');
    }

    if (state.trendingText) {
      state.trendingText.textContent = 'Potential widespread issues detected — check affected providers';
    }

    if (state.trendingReasons) {
      if (signals.length) {
        state.trendingReasons.textContent = 'Signals: ' + signals.slice(0, 6).join(', ');
        state.trendingReasons.removeAttribute('hidden');
      } else {
        state.trendingReasons.textContent = '';
        state.trendingReasons.setAttribute('hidden', 'hidden');
      }
    }

    if (info.generated_at && state.trendingBanner) {
      state.trendingBanner.setAttribute('data-lo-trending-generated', info.generated_at);
    }
  }

  function handleVisibilityChange() {
    if (isDocumentVisible()) {
      state.visibilityPaused = false;
      if (!state.isRefreshing) {
        refreshSummary(false, true);
      }
    } else {
      state.visibilityPaused = true;
      if (state.timer && state.root) {
        state.root.clearTimeout(state.timer);
        state.timer = null;
      }
      state.nextRefreshAt = null;
      stopCountdown();
      if (state.countdownEl) {
        state.countdownEl.textContent = 'Auto-refresh paused';
      }
    }
  }

  function resetAutoRefresh() {
    if (!state.baseDelay || state.baseDelay <= 0) {
      state.baseDelay = POLL_MS;
    }
    state.delay = state.baseDelay;
    state.degradedDueToStale = false;
    showDegraded(false);
  }

  function degradeAutoRefresh() {
    if (!state.baseDelay || state.baseDelay <= 0) {
      state.baseDelay = POLL_MS;
    }
    state.delay = state.baseDelay;
    state.degradedDueToStale = false;
    showDegraded(true);
  }

  function evaluateProviderHealth(list) {
    if (!Array.isArray(list) || !list.length) {
      degradeAutoRefresh();
      return;
    }

    var total = 0;
    var okCount = 0;
    list.forEach(function (provider) {
      if (!provider) {
        return;
      }
      total += 1;
      if (!provider.error) {
        okCount += 1;
      }
    });

    if (!total) {
      degradeAutoRefresh();
      return;
    }

    var ratio = okCount / total;
    if (ratio >= RECOVER_THRESHOLD) {
      resetAutoRefresh();
    } else if (ratio < DEGRADE_THRESHOLD) {
      degradeAutoRefresh();
    }
  }

  function refreshSummary(manual, force) {
    if (!state.fetchImpl || !state.endpoint) {
      return Promise.resolve();
    }
    if (state.isRefreshing) {
      if (manual) {
        state.pendingManual = true;
      }
      return Promise.resolve();
    }
    if (!manual && !force && !isDocumentVisible()) {
      scheduleNext(state.delay);
      return Promise.resolve();
    }

    state.isRefreshing = true;

    if (state.loadingEl && state.grid) {
      var hasCards = !!state.grid.querySelector('[data-provider-id]');
      if (!hasCards) {
        toggleSpinner(true);
      }
    }

    state.lastFetchStartedAt = Date.now();

    var headers = { Accept: 'application/json' };
    if (state.etag) {
      headers['If-None-Match'] = state.etag;
    }

    var requestUrl = state.endpoint;
    if (manual && requestUrl) {
      requestUrl = appendQuery(requestUrl, 'refresh', '1');
    }

    return state.fetchImpl(requestUrl, {
      credentials: 'same-origin',
      headers: headers
    })
      .then(function (res) {
        if (!res) {
          throw new Error('No response');
        }
        var etag = res.headers ? res.headers.get('ETag') : null;
        if (etag) {
          state.etag = etag;
        }
        if (res.status === 304) {
          resetAutoRefresh();
          scheduleNext(state.baseDelay);
          if (state.fetchedAt) {
            maybeQueueStaleRefresh(state.fetchedAt);
          }
          return null;
        }
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (body) {
        if (!body) {
          return;
        }
        if (Array.isArray(body.providers)) {
          evaluateProviderHealth(body.providers);
          renderProviders(body.providers);
        } else {
          resetAutoRefresh();
        }
        var fetched = body.fetched_at || (body.meta && (body.meta.fetched_at || body.meta.fetchedAt));
        if (fetched) {
          state.fetchedAt = fetched;
          updateFetched();
        }
        var responseSource = body.source || (body.meta && body.meta.source) || state.container && state.container.getAttribute && state.container.getAttribute('data-lo-source');
        if (responseSource) {
          var normalizedSource = String(responseSource).toLowerCase();
          state.fetchedLabel = normalizedSource === 'snapshot' ? 'Last fetched' : 'Fetched';
          if (state.container && state.container.setAttribute) {
            state.container.setAttribute('data-lo-source', String(responseSource));
          }
        } else {
          state.fetchedLabel = 'Fetched';
        }
        if (state.fetchedLabelEl) {
          state.fetchedLabelEl.textContent = state.fetchedLabel;
        }
        if (state.fetchedAt) {
          maybeQueueStaleRefresh(state.fetchedAt);
        }
        if (body.trending) {
          updateTrendingBanner(body.trending);
        } else {
          updateTrendingBanner({ trending: false, signals: [] });
        }
        var elapsed = 0;
        if (typeof state.lastFetchStartedAt === 'number' && state.lastFetchStartedAt > 0) {
          elapsed = Date.now() - state.lastFetchStartedAt;
        }
        var targetDelay = state.delay || state.baseDelay || POLL_MS;
        var remainder = targetDelay - elapsed;
        if (remainder < 0) {
          remainder = 0;
        }
        scheduleNext(remainder);
      })
      .catch(function () {
        degradeAutoRefresh();
        var retryDelay = state.delay || state.baseDelay || POLL_MS;
        if (typeof state.lastFetchStartedAt === 'number' && state.lastFetchStartedAt > 0) {
          var elapsedSinceStart = Date.now() - state.lastFetchStartedAt;
          if (elapsedSinceStart > 0 && elapsedSinceStart < retryDelay) {
            retryDelay = retryDelay - elapsedSinceStart;
          }
        }
        scheduleNext(Math.max(0, retryDelay));
      })
      .finally(function () {
        state.isRefreshing = false;
        var shouldTriggerStale = state.staleRefreshQueued && !state.staleRefreshInFlight;
        if (shouldTriggerStale) {
          state.staleRefreshQueued = false;
        }
        var replayManual = state.manualQueued;
        state.manualQueued = false;
        if (replayManual) {
          manualRefresh();
          return;
        }
        if (state.pendingManual) {
          state.pendingManual = false;
          refreshSummary(true, true);
          return;
        }
        if (shouldTriggerStale) {
          triggerStaleRefresh();
        }
      });
  }


  function callRefreshEndpoint() {
    if (!state.refreshEndpoint || !state.fetchImpl) {
      return Promise.resolve();
    }
    var headers = {};
    if (state.refreshNonce) {
      headers['X-WP-Nonce'] = state.refreshNonce;
    }
    return state.fetchImpl(state.refreshEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers
    }).then(function (res) {
      if (!res) {
        throw new Error('No response');
      }
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json().catch(function () { return null; });
    });
  }

  function manualRefresh() {
    if (state.isRefreshing) {
      state.manualQueued = true;
      return;
    }
    state.manualQueued = false;
    state.pendingManual = false;
    var previousFetched = state.fetchedAt;
    setLoading(true);
    var refreshFailed = false;
    callRefreshEndpoint()
      .catch(function () {
        refreshFailed = true;
        return null;
      })
      .then(function () {
        if (refreshFailed) {
          return refreshSummary(true, true);
        }
        function ensureUpdated(attempt) {
          return refreshSummary(true, true).then(function () {
            if (!previousFetched || !state.fetchedAt || state.fetchedAt !== previousFetched) {
              return null;
            }
            if (attempt >= MANUAL_REFRESH_MAX_ATTEMPTS) {
              return null;
            }
            return delay(MANUAL_REFRESH_RETRY_DELAY).then(function () {
              return ensureUpdated(attempt + 1);
            });
          });
        }
        return ensureUpdated(0);
      })
      .finally(function () {
        setLoading(false);
      });
  }

  function init(config) {
    config = config || {};
    state.doc = config.document || (state.root ? state.root.document : null);
    if (!state.doc) {
      return;
    }
    if (typeof config.window === 'object' && config.window) {
      state.root = config.window;
    }
    var fetchImpl = null;
    if (typeof config.fetch === 'function') {
      fetchImpl = config.fetch;
    } else if (state.root && typeof state.root.fetch === 'function') {
      fetchImpl = state.root.fetch.bind(state.root);
    } else if (typeof fetch === 'function') {
      fetchImpl = fetch.bind(globalRoot || null);
    }
    state.fetchImpl = fetchImpl;
    if (!state.fetchImpl) {
      return;
    }
    state.container = config.container || state.doc.querySelector('.lousy-outages') || state.doc.getElementById('lousy-outages') || state.doc.querySelector('.lousy-outages-board');
    if (!state.container) {
      return;
    }
    state.grid = state.container.querySelector('[data-lo-grid]') || state.container.querySelector('.providers-grid') || state.container;
    state.fetchedEl = state.container.querySelector('[data-lo-fetched]') || state.container.querySelector('.last-updated span');
    state.fetchedLabelEl = state.container.querySelector('[data-lo-fetched-label]');
    state.countdownEl = state.container.querySelector('[data-lo-countdown]') || state.container.querySelector('.board-subtitle');
    state.refreshButton = state.container.querySelector('[data-lo-refresh]') || state.container.querySelector('.coin-btn');
    state.degradedBadge = state.container.querySelector('[data-lo-degraded]');
    state.trendingBanner = state.container.querySelector('[data-lo-trending]');
    state.trendingText = state.container.querySelector('[data-lo-trending-text]');
    state.trendingReasons = state.container.querySelector('[data-lo-trending-reasons]');
    state.loadingEl = state.container.querySelector('[data-lo-loading]');
    state.endpoint = config.endpoint || '';
    state.refreshEndpoint = config.refreshEndpoint || '';
    state.refreshNonce = config.refreshNonce || '';
    state.subscribeEndpoint = config.subscribeEndpoint || '';
    var configuredDelay = parseInt(config.pollInterval || config.refreshInterval || config.pollMs || config.poll_delay, 10);
    if (!Number.isFinite(configuredDelay) || configuredDelay <= 0) {
      configuredDelay = POLL_MS;
    }
    state.baseDelay = configuredDelay;
    state.delay = state.baseDelay;
    state.maxDelay = Math.max(state.maxDelay || 0, MAX_DELAY);
    state.visibilityPaused = !isDocumentVisible();

    var initial = config.initial || {};
    var initialProviders = [];
    if (Array.isArray(initial.providers)) {
      initialProviders = initial.providers;
    } else if (Array.isArray(config.providers)) {
      initialProviders = config.providers;
    }
    var containerSource = state.container.getAttribute ? state.container.getAttribute('data-lo-source') : '';
    var initialSource = initial.source || (config.meta && config.meta.source) || containerSource || '';
    if (initialSource) {
      state.fetchedLabel = String(initialSource).toLowerCase() === 'snapshot' ? 'Last fetched' : 'Fetched';
    }
    if (state.fetchedLabelEl) {
      state.fetchedLabelEl.textContent = state.fetchedLabel;
    }
    if (initialProviders.length) {
      renderProviders(initialProviders);
    }
    if (state.loadingEl) {
      toggleSpinner(!initialProviders.length);
    }
    var initialFetched = initial.fetched_at || initial.fetchedAt;
    if (!initialFetched && config.meta && (config.meta.fetched_at || config.meta.fetchedAt)) {
      initialFetched = config.meta.fetched_at || config.meta.fetchedAt;
    }
    state.fetchedAt = initialFetched || null;
    updateFetched();

    var initialTrending = null;
    if (initial && typeof initial.trending === 'object') {
      initialTrending = initial.trending;
    } else if (config.meta && typeof config.meta.trending === 'object') {
      initialTrending = config.meta.trending;
    }
    updateTrendingBanner(initialTrending || { trending: false, signals: [] });

    if (state.refreshButton) {
      state.refreshButton.addEventListener('click', manualRefresh);
    }

    enhanceSubscribeForms();

    if (state.doc && typeof state.doc.addEventListener === 'function') {
      state.doc.addEventListener('visibilitychange', handleVisibilityChange);
    }

    scheduleNext(state.baseDelay);

    refreshSummary(false, true).catch(function () {
      // Ignore initial failure; countdown/backoff already handled inside refreshSummary.
    });
  }

  function stopAutoRefresh() {
    if (state.timer && state.root) {
      state.root.clearTimeout(state.timer);
      state.timer = null;
    }
    stopCountdown();
    state.nextRefreshAt = null;
    updateCountdown();
    showDegraded(false);
    state.visibilityPaused = true;
    state.delay = state.baseDelay;
  }

  return {
    init: init,
    stopAutoRefresh: stopAutoRefresh,
    normalizeStatus: normalizeStatus,
    snarkOutage: snarkOutage
  };
});
