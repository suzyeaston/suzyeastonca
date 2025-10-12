(function (globalScope, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(globalScope);
  } else {
    var api = factory(globalScope);
    globalScope.LousyOutages = api;
    if (globalScope.document) {
      var autoInit = function () {
        if (!globalScope.document) {
          return;
        }
        var config = globalScope.LousyOutagesConfig || {};
        if (config.autoInit === false) {
          return;
        }
        api.init(config);
      };
      if (globalScope.document.readyState === 'complete' || globalScope.document.readyState === 'interactive') {
        autoInit();
      } else {
        globalScope.document.addEventListener('DOMContentLoaded', autoInit);
      }
    }
  }
})(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this, function (root) {
  'use strict';

  var STATUS_LOOKUP = {
    operational: { code: 'operational', label: 'Operational', className: 'status--operational' },
    maintenance: { code: 'maintenance', label: 'Maintenance', className: 'status--maintenance' },
    under_maintenance: { code: 'maintenance', label: 'Maintenance', className: 'status--maintenance' },
    partial_outage: { code: 'partial', label: 'Partial outage', className: 'status--partial' },
    degraded_performance: { code: 'degraded', label: 'Degraded', className: 'status--degraded' },
    degraded: { code: 'degraded', label: 'Degraded', className: 'status--degraded' },
    major_outage: { code: 'outage', label: 'Major outage', className: 'status--outage' },
    outage: { code: 'outage', label: 'Outage', className: 'status--outage' },
    incident: { code: 'incident', label: 'Incident', className: 'status--incident' },
    warning: { code: 'warning', label: 'Warning', className: 'status--warning' },
    investigating: { code: 'investigating', label: 'Investigating', className: 'status--investigating' },
    monitoring: { code: 'monitoring', label: 'Monitoring', className: 'status--monitoring' },
    recovering: { code: 'recovering', label: 'Recovering', className: 'status--recovering' },
    resolved: { code: 'operational', label: 'Operational', className: 'status--operational' },
    unknown: { code: 'unknown', label: 'Unknown', className: 'status--unknown' }
  };

  var PROVIDER_QUIPS = {
    aws: [
      'Remember: us-east-1 is a lifestyle choice.',
      'Elastic beanstalk? More like elastic downtime.'
    ],
    github: ['Guess we are all pushing to /dev/null today.'],
    cloudflare: ['Somewhere, a BGP announcement is feeling mischievous.'],
    openai: ['The AI is currently busy writing its own apology.']
  };

  var DEFAULT_OPTIONS = {
    pollInterval: 5 * 60 * 1000,
    strings: {},
    fallbackStrings: {},
    providers: []
  };

  var timers = {
    countdown: null,
    refresh: null
  };

  var state = {
    container: null,
    grid: null,
    metaFetched: null,
    metaCountdown: null,
    refreshButton: null,
    lastFetched: null,
    providers: [],
    config: DEFAULT_OPTIONS
  };

  function coerceNumber(value, fallback) {
    var num = Number(value);
    return Number.isFinite(num) && num > 0 ? num : fallback;
  }

  function mergeConfig(userConfig) {
    var base = {};
    if (root && root.LousyOutagesConfig && typeof root.LousyOutagesConfig === 'object') {
      for (var key in root.LousyOutagesConfig) {
        if (Object.prototype.hasOwnProperty.call(root.LousyOutagesConfig, key)) {
          base[key] = root.LousyOutagesConfig[key];
        }
      }
    }
    var merged = {};
    for (var defKey in DEFAULT_OPTIONS) {
      if (Object.prototype.hasOwnProperty.call(DEFAULT_OPTIONS, defKey)) {
        merged[defKey] = DEFAULT_OPTIONS[defKey];
      }
    }
    for (var baseKey in base) {
      if (Object.prototype.hasOwnProperty.call(base, baseKey)) {
        merged[baseKey] = base[baseKey];
      }
    }
    if (userConfig && typeof userConfig === 'object') {
      for (var userKey in userConfig) {
        if (Object.prototype.hasOwnProperty.call(userConfig, userKey)) {
          merged[userKey] = userConfig[userKey];
        }
      }
    }
    merged.pollInterval = coerceNumber(merged.pollInterval, DEFAULT_OPTIONS.pollInterval);
    return merged;
  }

  function queryContainer(doc) {
    if (!doc) {
      return null;
    }
    return (
      doc.getElementById && doc.getElementById('lousy-outages') ||
      doc.querySelector && doc.querySelector('.lousy-outages, .lousy-outages-board')
    );
  }

  function resolveElements() {
    var doc = root && root.document ? root.document : null;
    state.container = queryContainer(doc);
    if (!state.container) {
      state.grid = null;
      state.metaFetched = null;
      state.metaCountdown = null;
      state.refreshButton = null;
      return;
    }
    if (state.container.querySelector) {
      state.grid = state.container.querySelector('[data-lo-grid]') || state.container.querySelector('.providers-grid') || state.container.querySelector('.lo-grid');
      state.metaFetched = state.container.querySelector('[data-lo-fetched]') || state.container.querySelector('[data-initial]') || state.container.querySelector('.last-updated [data-time]');
      state.metaCountdown = state.container.querySelector('[data-lo-countdown]') || state.container.querySelector('.board-subtitle') || state.container.querySelector('.lo-countdown');
      state.refreshButton = state.container.querySelector('[data-lo-refresh]') || state.container.querySelector('.coin-btn') || state.container.querySelector('.lo-refresh');
    } else {
      state.grid = null;
      state.metaFetched = null;
      state.metaCountdown = null;
      state.refreshButton = null;
    }
  }

  function removeAllChildren(node) {
    if (!node) {
      return;
    }
    if (typeof node.removeChild === 'function') {
      while (node.firstChild) {
        node.removeChild(node.firstChild);
      }
      return;
    }
    if (Array.isArray(node.children)) {
      node.children.forEach(function (child) {
        if (child) {
          child.parentNode = null;
        }
      });
      node.children = [];
    }
  }

  function ensureElement(parent, selector, factory) {
    if (!parent || !parent.querySelector) {
      return null;
    }
    var existing = parent.querySelector(selector);
    if (existing) {
      return existing;
    }
    if (!factory || !root.document || !root.document.createElement) {
      return null;
    }
    var created = factory();
    if (created && typeof parent.appendChild === 'function') {
      parent.appendChild(created);
    } else if (created && parent.children) {
      parent.children.push(created);
      created.parentNode = parent;
    }
    return created;
  }

  function ensureProviderCard(provider) {
    if (!state.grid) {
      return null;
    }
    var selector = '.provider-card[data-id="' + (provider.id || '') + '"]';
    var card = state.grid.querySelector ? state.grid.querySelector(selector) : null;
    if (card) {
      return card;
    }
    if (!root.document || !root.document.createElement) {
      return null;
    }
    card = root.document.createElement('article');
    card.className = 'provider-card';
    if (card.setAttribute) {
      card.setAttribute('data-id', provider.id || '');
      if (provider.name) {
        card.setAttribute('data-name', provider.name);
      }
    } else {
      card.dataset = card.dataset || {};
      card.dataset.id = provider.id || '';
      card.dataset.name = provider.name || '';
    }

    var inner = root.document.createElement('div');
    inner.className = 'provider-card__inner';
    card.appendChild(inner);

    var header = root.document.createElement('header');
    header.className = 'provider-card__header';
    inner.appendChild(header);

    var title = root.document.createElement('h3');
    title.className = 'provider-card__name';
    title.textContent = provider.name || provider.provider || provider.id || 'Provider';
    header.appendChild(title);

    var status = root.document.createElement('span');
    status.className = 'status-badge status--unknown';
    if (status.dataset) {
      status.dataset.status = 'unknown';
    } else if (status.setAttribute) {
      status.setAttribute('data-status', 'unknown');
    }
    status.textContent = 'Unknown';
    header.appendChild(status);

    var summary = root.document.createElement('p');
    summary.className = 'provider-card__summary';
    summary.textContent = '';
    inner.appendChild(summary);

    var snark = root.document.createElement('p');
    snark.className = 'provider-card__snark';
    snark.textContent = '';
    inner.appendChild(snark);

    var toggle = root.document.createElement('button');
    toggle.className = 'details-toggle';
    toggle.setAttribute && toggle.setAttribute('aria-expanded', 'false');
    var toggleLabel = root.document.createElement('span');
    toggleLabel.className = 'toggle-label';
    toggleLabel.textContent = 'Details';
    toggle.appendChild(toggleLabel);
    inner.appendChild(toggle);

    var details = root.document.createElement('section');
    details.className = 'provider-details';
    if (details.setAttribute) {
      details.setAttribute('hidden', '');
    }
    inner.appendChild(details);

    var incidents = root.document.createElement('div');
    incidents.className = 'incidents';
    details.appendChild(incidents);

    var empty = root.document.createElement('p');
    empty.className = 'incident-empty';
    empty.textContent = 'No active incidents. Go write a chorus.';
    incidents.appendChild(empty);

    var link = root.document.createElement('a');
    link.className = 'provider-link';
    link.textContent = 'View provider status →';
    details.appendChild(link);

    if (typeof state.grid.appendChild === 'function') {
      state.grid.appendChild(card);
    } else if (state.grid.children) {
      state.grid.children.push(card);
      card.parentNode = state.grid;
    }

    return card;
  }

  function normalizeStatus(raw) {
    var key = String(raw || '').trim().toLowerCase();
    if (Object.prototype.hasOwnProperty.call(STATUS_LOOKUP, key)) {
      return STATUS_LOOKUP[key];
    }
    return STATUS_LOOKUP.unknown;
  }

  function snarkOutage(providerName, statusLabel, summary) {
    var key = String(providerName || '').trim().toLowerCase();
    if (key && Object.prototype.hasOwnProperty.call(PROVIDER_QUIPS, key)) {
      var lines = PROVIDER_QUIPS[key];
      if (Array.isArray(lines) && lines.length) {
        var index = Math.floor(Math.random() * lines.length);
        return providerName + ': ' + lines[index];
      }
    }
    if (summary) {
      return summary;
    }
    if (statusLabel && providerName) {
      return providerName + ' — ' + statusLabel;
    }
    return providerName || statusLabel || '';
  }

  function setStatusBadge(card, provider) {
    var statusEl = ensureElement(card, '.status-badge', function () {
      var span = root.document.createElement('span');
      span.className = 'status-badge';
      span.dataset = span.dataset || {};
      span.dataset.status = 'unknown';
      span.textContent = 'Unknown';
      return span;
    });
    if (!statusEl) {
      return;
    }
    var normalized = normalizeStatus(provider.stateCode || provider.statusCode || provider.status || provider.state);
    statusEl.textContent = normalized.label;
    if (statusEl.dataset) {
      statusEl.dataset.status = normalized.code;
    } else if (statusEl.setAttribute) {
      statusEl.setAttribute('data-status', normalized.code);
    }
    statusEl.className = 'status-badge ' + normalized.className;
  }

  function setSummary(card, provider) {
    var summaryEl = ensureElement(card, '.provider-card__summary', function () {
      var p = root.document.createElement('p');
      p.className = 'provider-card__summary';
      return p;
    });
    if (summaryEl) {
      summaryEl.textContent = provider.summary || provider.message || '';
    }
    var snarkEl = ensureElement(card, '.provider-card__snark', function () {
      var sn = root.document.createElement('p');
      sn.className = 'provider-card__snark';
      return sn;
    });
    if (snarkEl) {
      if (provider.snark) {
        snarkEl.textContent = provider.snark;
      } else {
        var normalized = normalizeStatus(provider.stateCode || provider.statusCode || provider.status || provider.state);
        if (normalized.code === 'operational') {
          snarkEl.textContent = '';
        } else {
          snarkEl.textContent = snarkOutage(provider.provider || provider.name || provider.id || '', normalized.label, provider.summary || provider.message || '');
        }
      }
    }
  }

  function setIncidents(card, provider) {
    var incidentsEl = ensureElement(card, '.incidents', function () {
      var div = root.document.createElement('div');
      div.className = 'incidents';
      return div;
    });
    if (!incidentsEl) {
      return;
    }
    removeAllChildren(incidentsEl);
    var list = provider.incidents || [];
    if (!Array.isArray(list) || list.length === 0) {
      var empty = root.document.createElement('p');
      empty.className = 'incident-empty';
      empty.textContent = 'No active incidents. Go write a chorus.';
      incidentsEl.appendChild ? incidentsEl.appendChild(empty) : incidentsEl.children && incidentsEl.children.push(empty);
      if (empty) {
        empty.parentNode = incidentsEl;
      }
      return;
    }
    var ul = root.document.createElement('ul');
    ul.className = 'incident-list';
    list.forEach(function (incident) {
      var li = root.document.createElement('li');
      li.className = 'incident-item';
      var title = root.document.createElement('strong');
      title.className = 'incident-title';
      title.textContent = incident.title || incident.name || 'Incident';
      li.appendChild(title);
      if (incident.updatedAt || incident.impact) {
        var meta = root.document.createElement('span');
        meta.className = 'incident-meta';
        var bits = [];
        if (incident.impact) {
          bits.push(String(incident.impact));
        }
        if (incident.updatedAt) {
          bits.push(formatTimestamp(incident.updatedAt));
        }
        meta.textContent = bits.join(' • ');
        li.appendChild(meta);
      }
      if (incident.url) {
        var link = root.document.createElement('a');
        link.className = 'incident-link';
        link.textContent = 'Open incident';
        link.href = incident.url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        li.appendChild(link);
      }
      ul.appendChild(li);
    });
    if (incidentsEl.appendChild) {
      incidentsEl.appendChild(ul);
    } else if (incidentsEl.children) {
      incidentsEl.children.push(ul);
      ul.parentNode = incidentsEl;
    }
  }

  function setProviderLink(card, provider) {
    var link = ensureElement(card, '.provider-link', function () {
      var a = root.document.createElement('a');
      a.className = 'provider-link';
      a.textContent = 'View provider status →';
      return a;
    });
    if (!link) {
      return;
    }
    if (provider.url) {
      link.href = provider.url;
      link.textContent = provider.linkLabel || 'View provider status →';
      if (link.setAttribute) {
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener noreferrer');
      }
      if (link.setAttribute) {
        link.setAttribute('href', provider.url);
      }
    } else {
      link.href = '#';
      if (link.setAttribute) {
        link.setAttribute('href', '#');
      }
    }
  }

  function renderProviders(providers) {
    state.providers = Array.isArray(providers) ? providers.slice() : [];
    if (!state.grid) {
      return;
    }
    state.providers.forEach(function (provider) {
      var card = ensureProviderCard(provider);
      if (!card) {
        return;
      }
      var nameEl = ensureElement(card, '.provider-card__name', function () {
        var title = root.document.createElement('h3');
        title.className = 'provider-card__name';
        return title;
      });
      if (nameEl) {
        nameEl.textContent = provider.name || provider.provider || provider.id || 'Provider';
      }
      setStatusBadge(card, provider);
      setSummary(card, provider);
      setIncidents(card, provider);
      setProviderLink(card, provider);
    });
  }

  function formatTimestamp(iso) {
    if (!iso) {
      return '';
    }
    try {
      var date = new Date(iso);
      if (!Number.isFinite(date.getTime())) {
        return '';
      }
      if (root.Intl && root.Intl.DateTimeFormat) {
        return new root.Intl.DateTimeFormat(undefined, {
          year: 'numeric',
          month: 'short',
          day: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        }).format(date);
      }
      return date.toISOString();
    } catch (error) {
      return '';
    }
  }

  function formatRelative(ms) {
    if (!Number.isFinite(ms)) {
      return '';
    }
    if (ms <= 0) {
      return 'Ready to refresh';
    }
    var seconds = Math.round(ms / 1000);
    if (seconds < 60) {
      return seconds + 's until refresh';
    }
    var minutes = Math.floor(seconds / 60);
    var rem = seconds % 60;
    if (minutes < 60) {
      return minutes + 'm ' + (rem < 10 ? '0' + rem : rem) + 's until refresh';
    }
    var hours = Math.floor(minutes / 60);
    var hourRem = minutes % 60;
    return hours + 'h ' + (hourRem < 10 ? '0' + hourRem : hourRem) + 'm until refresh';
  }

  function updateMeta(meta) {
    if (!meta) {
      return;
    }
    if (meta.fetchedAt) {
      state.lastFetched = meta.fetchedAt;
      if (state.metaFetched) {
        state.metaFetched.textContent = formatTimestamp(meta.fetchedAt);
      }
    }
    if (meta.countdownText && state.metaCountdown) {
      state.metaCountdown.textContent = meta.countdownText;
    }
  }

  function tickCountdown() {
    if (!state.metaCountdown) {
      return;
    }
    if (!state.lastFetched) {
      state.metaCountdown.textContent = 'Waiting for first refresh…';
      return;
    }
    var elapsed = Date.now() - new Date(state.lastFetched).getTime();
    var remaining = state.config.pollInterval - elapsed;
    state.metaCountdown.textContent = formatRelative(remaining);
  }

  function startCountdown() {
    if (timers.countdown) {
      root.clearInterval(timers.countdown);
      timers.countdown = null;
    }
    tickCountdown();
    timers.countdown = root.setInterval(tickCountdown, 1000);
  }

  function stopAutoRefresh() {
    if (timers.refresh) {
      root.clearTimeout(timers.refresh);
      timers.refresh = null;
    }
    if (timers.countdown) {
      root.clearInterval(timers.countdown);
      timers.countdown = null;
    }
  }

  function scheduleNextRefresh() {
    if (!state.config.pollInterval) {
      return;
    }
    if (timers.refresh) {
      root.clearTimeout(timers.refresh);
    }
    timers.refresh = root.setTimeout(function () {
      refreshNow(false);
    }, state.config.pollInterval);
    startCountdown();
  }

  function setLoading(isLoading) {
    if (!state.refreshButton) {
      return;
    }
    state.refreshButton.disabled = !!isLoading;
    var label = state.refreshButton.querySelector ? state.refreshButton.querySelector('.label') : null;
    var loader = state.refreshButton.querySelector ? state.refreshButton.querySelector('.loader') : null;
    if (label) {
      if (isLoading) {
        if (!state.refreshButton.dataset.originalLabel) {
          state.refreshButton.dataset.originalLabel = label.textContent;
        }
        var loadingLabel = state.refreshButton.getAttribute && state.refreshButton.getAttribute('data-loading-label');
        label.textContent = loadingLabel || 'Refreshing…';
      } else if (state.refreshButton.dataset && state.refreshButton.dataset.originalLabel) {
        label.textContent = state.refreshButton.dataset.originalLabel;
      }
    }
    if (loader && loader.classList && typeof loader.classList.toggle === 'function') {
      loader.classList.toggle('is-active', !!isLoading);
    }
  }

  function showRefreshError(error) {
    if (!state.metaCountdown) {
      return;
    }
    var message = error && error.message ? error.message : 'Refresh failed';
    state.metaCountdown.textContent = 'Refresh failed: ' + message;
  }

  function refreshNow(force) {
    if (!state.config.endpoint || !root.fetch) {
      return Promise.resolve(null);
    }
    var url = state.config.endpoint;
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    url += separator + '_=' + Date.now();
    if (force) {
      url += '&refresh=1';
    }
    setLoading(true);
    var request = root.fetch(url, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response || !response.ok) {
          var status = response ? response.status : 0;
          throw new Error('HTTP ' + status);
        }
        return response.json();
      })
      .then(function (payload) {
        if (!payload) {
          throw new Error('Empty payload');
        }
        renderProviders(payload.providers || []);
        updateMeta(payload.meta || {});
        scheduleNextRefresh();
        return payload;
      })
      .catch(function (error) {
        showRefreshError(error);
        throw error;
      })
      .finally(function () {
        setLoading(false);
      });
    return request;
  }

  function attachButtonHandler() {
    if (!state.refreshButton || state.refreshButton._loHandlerAttached) {
      return;
    }
    if (typeof state.refreshButton.addEventListener === 'function') {
      state.refreshButton.addEventListener('click', function (event) {
        if (event && typeof event.preventDefault === 'function') {
          event.preventDefault();
        }
        refreshNow(true);
      });
    }
    state.refreshButton._loHandlerAttached = true;
  }

  function buildInitialCards() {
    if (!state.grid || !Array.isArray(state.config.providers)) {
      return;
    }
    state.config.providers.forEach(function (provider) {
      ensureProviderCard({ id: provider.id, name: provider.name || provider.provider || provider.id });
    });
  }

  function init(userConfig) {
    stopAutoRefresh();
    state.config = mergeConfig(userConfig);
    resolveElements();
    buildInitialCards();
    attachButtonHandler();
    if (state.config.initial && Array.isArray(state.config.initial.providers)) {
      renderProviders(state.config.initial.providers);
    }
    if (state.config.initial && state.config.initial.meta) {
      updateMeta(state.config.initial.meta);
    }
    if (state.config.endpoint) {
      refreshNow(false);
    } else {
      startCountdown();
    }
  }

  return {
    init: init,
    refreshNow: refreshNow,
    stopAutoRefresh: stopAutoRefresh,
    normalizeStatus: normalizeStatus,
    snarkOutage: snarkOutage
  };
});
