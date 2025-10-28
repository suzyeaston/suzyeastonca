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

  var POLL_MS = 60000;
  var MAX_BACKOFF_MS = 300000;

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
    pollInterval: POLL_MS,
    timer: null,
    countdownTimer: null,
    nextRefreshAt: null,
    etag: null,
    backoff: POLL_MS,
    container: null,
    grid: null,
    fetchedEl: null,
    countdownEl: null,
    refreshButton: null,
    degradedBadge: null,
    fetchedAt: null,
    isRefreshing: false,
    pendingManual: false
  };

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
    if (state.timer) {
      state.root.clearTimeout(state.timer);
      state.timer = null;
    }
    var nextDelay = Math.max(10, delay);
    state.nextRefreshAt = Date.now() + nextDelay;
    state.timer = state.root.setTimeout(function () {
      state.timer = null;
      refreshSummary(false);
    }, nextDelay);
    startCountdown();
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
    var seconds = Math.round(diff / 1000);
    if (seconds < 60) {
      state.countdownEl.textContent = 'Next refresh in ' + seconds + 's';
      return;
    }
    var minutes = Math.floor(seconds / 60);
    var rem = seconds % 60;
    state.countdownEl.textContent = 'Next refresh in ' + minutes + 'm ' + rem + 's';
  }

  function updateFetched() {
    if (!state.fetchedEl || !state.fetchedAt) {
      return;
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

  function showDegraded(active) {
    if (!state.degradedBadge) {
      return;
    }
    if (active) {
      state.degradedBadge.removeAttribute('hidden');
    } else {
      state.degradedBadge.setAttribute('hidden', 'hidden');
    }
  }

  function renderProviders(list) {
    if (!Array.isArray(list)) {
      return;
    }
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
      var normalized = normalizeStatus(provider.overall || provider.overall_status || provider.status || provider.stateCode);
      if (card.classList && card.classList.contains('provider-card')) {
        updateLegacyCard(card, provider, normalized);
      } else {
        updateModernCard(card, provider, normalized);
      }
    });
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
        badge.dataset.status = provider.overall || provider.overall_status || normalized.code;
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
      var hasError = !!provider.error;
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
      if (provider.url) {
        statusLink.href = provider.url;
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
    setSubscribeStatus(statusEl, 'Sending…');
    form.querySelectorAll('button, input[type="submit"]').forEach(function (btn) {
      btn.disabled = true;
    });
    var payload = {
      email: email,
      website: honeypot ? honeypot.value : '',
      _wpnonce: nonceInput ? nonceInput.value : ''
    };

    state.fetchImpl(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        if (!res) {
          throw new Error('No response');
        }
        if (res.status === 204) {
          return {};
        }
        return res.json().catch(function () { return {}; }).then(function (body) {
          if (!res.ok) {
            var message = body && body.message ? body.message : 'Subscription failed.';
            throw new Error(message);
          }
          return body;
        });
      })
      .then(function (body) {
        setSubscribeStatus(statusEl, (body && body.message) ? body.message : 'Check your email to confirm.');
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

  function refreshSummary(manual, force) {
    if (!state.fetchImpl || !state.endpoint || state.isRefreshing) {
      return Promise.resolve();
    }
    var startedAt = Date.now();
    var scheduled = false;
    state.isRefreshing = true;
    if (manual) {
      state.backoff = state.pollInterval;
      state.nextRefreshAt = Date.now() + state.pollInterval;
      updateCountdown();
    }
    var url = state.endpoint;
    if (!url) {
      state.isRefreshing = false;
      return Promise.resolve();
    }
    var headers = { Accept: 'application/json' };
    if (manual || force) {
      url = appendQuery(url, '_', Date.now());
    }
    if (manual) {
      url = appendQuery(url, 'refresh', '1');
    }
    if (state.etag && !force) {
      headers['If-None-Match'] = state.etag;
    }
    return state.fetchImpl(url, {
      credentials: 'same-origin',
      headers: headers
    })
      .then(function (res) {
        if (!res) {
          throw new Error('No response');
        }
        if (res.status === 304) {
          state.backoff = state.pollInterval;
          showDegraded(false);
          var elapsed304 = Date.now() - startedAt;
          var delay304 = Math.max(10, state.pollInterval - elapsed304);
          scheduleNext(delay304);
          scheduled = true;
          return null;
        }
        var etag = res.headers ? res.headers.get('ETag') : null;
        if (etag) {
          state.etag = etag;
        }
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (body) {
        if (!scheduled) {
          state.backoff = state.pollInterval;
          showDegraded(false);
          var elapsed = Date.now() - startedAt;
          var delay = Math.max(10, state.pollInterval - elapsed);
          scheduleNext(delay);
          scheduled = true;
        }
        if (!body) {
          return;
        }
        if (Array.isArray(body.providers)) {
          renderProviders(body.providers);
        }
        var fetched = body.fetched_at || (body.meta && body.meta.fetchedAt);
        if (fetched) {
          state.fetchedAt = fetched;
          updateFetched();
        }
      })
      .catch(function () {
        state.backoff = Math.min(state.backoff * 2, MAX_BACKOFF_MS);
        showDegraded(true);
        var elapsedError = Date.now() - startedAt;
        var backoffDelay = Math.max(10, state.backoff - elapsedError);
        scheduleNext(backoffDelay);
        scheduled = true;
      })
      .finally(function () {
        state.isRefreshing = false;
        if (state.pendingManual) {
          state.pendingManual = false;
          if (state.root && typeof state.root.setTimeout === 'function') {
            state.root.setTimeout(function () {
              manualRefresh();
            }, 0);
          } else {
            manualRefresh();
          }
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
      state.pendingManual = true;
      return;
    }
    state.pendingManual = false;
    setLoading(true);
    callRefreshEndpoint()
      .catch(function () {
        return null;
      })
      .then(function () {
        return refreshSummary(true, true);
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
    state.countdownEl = state.container.querySelector('[data-lo-countdown]') || state.container.querySelector('.board-subtitle');
    state.refreshButton = state.container.querySelector('[data-lo-refresh]') || state.container.querySelector('.coin-btn');
    state.degradedBadge = state.container.querySelector('[data-lo-degraded]');
    state.endpoint = config.endpoint || '';
    state.refreshEndpoint = config.refreshEndpoint || '';
    state.refreshNonce = config.refreshNonce || '';
    state.subscribeEndpoint = config.subscribeEndpoint || '';
    state.pollInterval = Number(config.pollInterval) || POLL_MS;
    if (!Number.isFinite(state.pollInterval) || state.pollInterval <= 0) {
      state.pollInterval = POLL_MS;
    }
    state.backoff = state.pollInterval;

    var initial = config.initial || {};
    var initialProviders = [];
    if (Array.isArray(initial.providers)) {
      initialProviders = initial.providers;
    } else if (Array.isArray(config.providers)) {
      initialProviders = config.providers;
    }
    if (initialProviders.length) {
      renderProviders(initialProviders);
    }
    var initialFetched = initial.fetched_at || initial.fetchedAt;
    if (!initialFetched && config.meta && (config.meta.fetched_at || config.meta.fetchedAt)) {
      initialFetched = config.meta.fetched_at || config.meta.fetchedAt;
    }
    state.fetchedAt = initialFetched || null;
    updateFetched();

    if (state.refreshButton) {
      state.refreshButton.addEventListener('click', manualRefresh);
    }

    enhanceSubscribeForms();

    state.nextRefreshAt = Date.now() + state.pollInterval;
    startCountdown();

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
  }

  return {
    init: init,
    stopAutoRefresh: stopAutoRefresh,
    normalizeStatus: normalizeStatus,
    snarkOutage: snarkOutage
  };
});
