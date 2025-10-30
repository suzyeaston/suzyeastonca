(function (root) {
  'use strict';

  if (!root || !root.document) {
    return;
  }

  var doc = root.document;

  var STATUS_CLASS_MAP = {
    operational: 'status--operational',
    degraded: 'status--degraded',
    outage: 'status--outage',
    maintenance: 'status--maintenance',
    unknown: 'status--unknown'
  };

  function normalizeService(service) {
    if (!service || typeof service !== 'object') {
      return null;
    }
    var status = String(service.status || service.stateCode || service.state || 'unknown').toLowerCase();
    if (status === 'major_outage' || status === 'critical') {
      status = 'outage';
    } else if (status === 'minor_outage' || status === 'degraded_performance' || status === 'partial_outage') {
      status = 'degraded';
    }
    var statusText = String(service.status_text || service.state || service.status_label || '').trim();
    if (!statusText) {
      statusText = status.replace(/_/g, ' ');
      statusText = statusText.charAt(0).toUpperCase() + statusText.slice(1);
    }
    return {
      id: String(service.id || service.provider || ''),
      name: String(service.name || service.provider || ''),
      status: status || 'unknown',
      status_text: statusText,
      summary: String(service.summary || service.message || ''),
      url: String(service.url || ''),
      updated_at: String(service.updated_at || service.updatedAt || ''),
      risk: parseInt(service.risk, 10) || 0
    };
  }

  function normalizeSnapshot(payload, fallbackTtl) {
    var ttl = parseInt(payload && payload.ttl_seconds, 10);
    if (!Number.isFinite(ttl) || ttl <= 0) {
      ttl = Math.max(30, Math.round((fallbackTtl || 60000) / 1000));
    }
    var services = Array.isArray(payload && payload.services) ? payload.services.map(normalizeService).filter(Boolean) : [];
    return {
      updated_at: (payload && payload.updated_at) ? String(payload.updated_at) : new Date().toISOString(),
      ttl_seconds: ttl,
      services: services,
      stale: !!(payload && payload.stale)
    };
  }

  function formatDate(value) {
    if (!value) {
      return '';
    }
    var parsed = Date.parse(value);
    if (Number.isNaN(parsed)) {
      return value;
    }
    try {
      return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      }).format(new Date(parsed));
    } catch (err) {
      return new Date(parsed).toISOString();
    }
  }

  function escapeId(value) {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
      return CSS.escape(value);
    }
    return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function applyStatusClass(el, status) {
    if (!el || !el.classList) {
      return;
    }
    var classes = Object.values(STATUS_CLASS_MAP);
    classes.forEach(function (cls) { el.classList.remove(cls); });
    var mapped = STATUS_CLASS_MAP[status] || STATUS_CLASS_MAP.unknown;
    el.classList.add(mapped);
  }

  function setupHud(container, config) {
    if (!container) {
      return;
    }
    container.classList.add('lo-hud');

    var refreshBtn = container.querySelector('[data-lo-refresh]');
    var countdownEl = container.querySelector('[data-lo-countdown]');
    var fetchedEl = container.querySelector('[data-lo-fetched]');
    var fetchedLabelEl = container.querySelector('[data-lo-fetched-label]');
    var degradedEl = container.querySelector('[data-lo-degraded]');
    var gridEl = container.querySelector('[data-lo-grid]');
    var baseCountdown = countdownEl ? countdownEl.textContent : 'Auto-refresh ready';
    var snapshotEndpoint = container.getAttribute('data-lo-snapshot') || config.snapshotEndpoint || '';
    var refreshInterval = parseInt(container.getAttribute('data-lo-refresh-interval'), 10);
    if (!Number.isFinite(refreshInterval) || refreshInterval <= 0) {
      refreshInterval = parseInt(config.pollInterval, 10);
    }
    if (!Number.isFinite(refreshInterval) || refreshInterval <= 0) {
      refreshInterval = 60000;
    }

    var spinnerEl = doc.createElement('span');
    spinnerEl.className = 'lo-hud-spinner';
    spinnerEl.setAttribute('aria-hidden', 'true');
    spinnerEl.textContent = 'Dialing…';
    if (refreshBtn && refreshBtn.parentNode) {
      refreshBtn.parentNode.insertBefore(spinnerEl, refreshBtn.nextSibling);
    } else {
      container.appendChild(spinnerEl);
    }

    var state = {
      fetchInFlight: false,
      refreshTimer: null,
      countdownTimer: null,
      nextRefreshAt: null,
      lastSnapshot: null
    };

    function setSpinner(active) {
      if (active) {
        container.classList.add('lo-hud--dialing');
      } else {
        container.classList.remove('lo-hud--dialing');
      }
    }

    function setRefreshing(active) {
      if (!refreshBtn) {
        return;
      }
      refreshBtn.disabled = !!active;
      refreshBtn.setAttribute('aria-busy', active ? 'true' : 'false');
      refreshBtn.textContent = active ? 'Refreshing…' : 'Refresh now';
    }

    function setDegraded(active, message) {
      if (!degradedEl) {
        return;
      }
      if (active) {
        degradedEl.textContent = message || 'Auto-refresh degraded';
        degradedEl.removeAttribute('hidden');
      } else {
        degradedEl.textContent = '';
        degradedEl.setAttribute('hidden', 'hidden');
      }
    }

    function clearTimers() {
      if (state.refreshTimer) {
        root.clearTimeout(state.refreshTimer);
        state.refreshTimer = null;
      }
      if (state.countdownTimer) {
        root.clearInterval(state.countdownTimer);
        state.countdownTimer = null;
      }
      state.nextRefreshAt = null;
    }

    function updateCountdown() {
      if (!countdownEl) {
        return;
      }
      if (!state.nextRefreshAt) {
        countdownEl.textContent = doc.hidden ? 'Auto-refresh paused' : baseCountdown;
        return;
      }
      var diff = state.nextRefreshAt - Date.now();
      if (diff <= 0) {
        countdownEl.textContent = 'Refreshing…';
        return;
      }
      countdownEl.textContent = '';
    }

    function startCountdown() {
      if (state.countdownTimer) {
        root.clearInterval(state.countdownTimer);
      }
      updateCountdown();
      state.countdownTimer = root.setInterval(updateCountdown, 1000);
    }

    function scheduleRefresh(delay) {
      if (doc.hidden) {
        clearTimers();
        updateCountdown();
        return;
      }
      if (!Number.isFinite(delay) || delay <= 0) {
        delay = refreshInterval;
      }
      if (state.refreshTimer) {
        root.clearTimeout(state.refreshTimer);
      }
      state.nextRefreshAt = Date.now() + delay;
      startCountdown();
      state.refreshTimer = root.setTimeout(function () {
        state.refreshTimer = null;
        fetchSnapshot(false);
      }, delay);
    }

    function updateFetchedLabel() {
      if (!fetchedEl || !state.lastSnapshot) {
        return;
      }
      fetchedEl.textContent = formatDate(state.lastSnapshot.updated_at);
      if (fetchedLabelEl) {
        fetchedLabelEl.textContent = state.lastSnapshot.stale ? 'Last fetched (stale)' : 'Last fetched';
      }
    }

    function updateCards(services) {
      if (!Array.isArray(services)) {
        return;
      }
      services.forEach(function (service) {
        if (!service || !service.id) {
          return;
        }
        var selector = '[data-provider-id="' + escapeId(service.id) + '"]';
        var card = gridEl ? gridEl.querySelector(selector) : null;
        if (!card) {
          card = container.querySelector(selector);
        }
        if (!card) {
          return;
        }
        var badge = card.querySelector('[data-lo-badge]') || card.querySelector('.status-badge');
        if (badge) {
          badge.textContent = service.status_text;
          applyStatusClass(badge, service.status);
          badge.setAttribute('data-status', service.status);
        }
        var summary = card.querySelector('[data-lo-summary]') || card.querySelector('.provider-card__summary');
        if (summary) {
          summary.textContent = service.summary || '';
        }
        var link = card.querySelector('[data-lo-status-url]') || card.querySelector('.provider-link');
        if (link && service.url) {
          link.setAttribute('href', service.url);
        }
      });
    }

    function applySnapshot(snapshot) {
      state.lastSnapshot = snapshot;
      updateFetchedLabel();
      updateCards(snapshot.services);
      if (snapshot.stale) {
        setDegraded(true, 'Showing cached data – refreshing soon');
      } else {
        setDegraded(false);
      }
    }

    function fetchSnapshot(manual) {
      if (!snapshotEndpoint || state.fetchInFlight) {
        return;
      }
      state.fetchInFlight = true;
      setRefreshing(true);
      setSpinner(true);

      root.fetch(snapshotEndpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      }).then(function (response) {
        if (!response || typeof response.json !== 'function') {
          throw new Error('invalid response');
        }
        return response.json();
      }).then(function (payload) {
        var snapshot = normalizeSnapshot(payload, refreshInterval);
        applySnapshot(snapshot);
        scheduleRefresh(refreshInterval);
      }).catch(function () {
        setDegraded(true, 'Auto-refresh unavailable – retrying');
        scheduleRefresh(Math.min(refreshInterval, 30000));
      }).finally(function () {
        state.fetchInFlight = false;
        setRefreshing(false);
        setSpinner(false);
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', function (event) {
        event.preventDefault();
        if (state.fetchInFlight) {
          return;
        }
        fetchSnapshot(true);
      });
    }

    doc.addEventListener('visibilitychange', function () {
      if (doc.hidden) {
        clearTimers();
        updateCountdown();
      } else {
        if (state.fetchInFlight) {
          return;
        }
        if (state.lastSnapshot && state.lastSnapshot.stale) {
          fetchSnapshot(false);
        } else {
          scheduleRefresh(Math.min(5000, refreshInterval));
        }
      }
    });

    var initialSnapshot = null;
    var configInitial = config && config.initial ? config.initial : null;
    if (configInitial && Array.isArray(configInitial.providers)) {
      initialSnapshot = normalizeSnapshot({
        updated_at: configInitial.fetched_at || configInitial.fetchedAt,
        services: configInitial.providers,
        stale: false,
        ttl_seconds: Math.round(refreshInterval / 1000)
      }, refreshInterval);
    }

    if (initialSnapshot) {
      applySnapshot(initialSnapshot);
    }

    scheduleRefresh(refreshInterval);
  }

  function init() {
    var config = root.LousyOutagesConfig || {};
    var containers = doc.querySelectorAll('.lousy-outages');
    if (!containers || !containers.length) {
      return;
    }
    Array.prototype.forEach.call(containers, function (container) {
      setupHud(container, config);
    });
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : this);
