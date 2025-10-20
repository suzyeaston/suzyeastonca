if (typeof window === 'undefined') {
  const root = typeof globalThis !== 'undefined' ? globalThis : global;
  const state = {
    root,
    pollMs: 300000,
    countdownTimer: null,
    refreshTimer: null,
    lastFetched: null,
    providers: [],
    config: {},
    fetch: null,
    doc: null,
    container: null,
    grid: null,
    metaFetched: null,
    metaCountdown: null,
    refreshButton: null
  };

  const STATUS_MAP = {
    operational: { code: 'operational', label: 'Operational', className: 'status--operational' },
    degraded: { code: 'degraded', label: 'Degraded', className: 'status--degraded' },
    outage: { code: 'outage', label: 'Outage', className: 'status--outage' },
    maintenance: { code: 'maintenance', label: 'Maintenance', className: 'status--maintenance' },
    unknown: { code: 'unknown', label: 'Unknown', className: 'status--unknown' }
  };

  const SNARKS = {
    aws: [
      'us-east-1 is a lifestyle choice.',
      'Somewhere a Lambda forgot to set its alarm.'
    ],
    github: ['Push it real good, but maybe later.'],
    slack: ['Time to revive the email thread.'],
    default: ['Hold tight—someone’s jiggling the ethernet cable.']
  };

  function normalizeStatus(status) {
    const key = String(status || '').toLowerCase();
    return STATUS_MAP[key] || STATUS_MAP.unknown;
  }

  function snarkOutage(provider, status, summary) {
    const statusKey = String(status || '').toLowerCase();
    if ('operational' === statusKey || 'maintenance' === statusKey) {
      return summary || 'All systems operational.';
    }
    const providerKey = String(provider || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    const lines = SNARKS[providerKey] || SNARKS.default;
    const index = Math.min(lines.length - 1, Math.floor(Math.random() * lines.length));
    const chosen = lines[index] || '';
    return chosen ? chosen : summary || 'Something feels off.';
  }

  function formatTimestamp(iso) {
    if (!iso) {
      return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
      return '';
    }
    return new Intl.DateTimeFormat(undefined, {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    }).format(date);
  }

  function formatRelative(ms) {
    if (!Number.isFinite(ms)) {
      return '';
    }
    if (ms <= 0) {
      return 'Ready to refresh';
    }
    const seconds = Math.round(ms / 1000);
    if (seconds < 60) {
      return seconds + 's until refresh';
    }
    const minutes = Math.floor(seconds / 60);
    const rem = seconds % 60;
    return minutes + 'm ' + rem + 's until refresh';
  }

  function setLoading(isLoading) {
    if (state.refreshButton) {
      state.refreshButton.disabled = !!isLoading;
    }
  }

  function updateMeta(meta) {
    if (!meta) {
      return;
    }
    if (meta.fetchedAt) {
      state.lastFetched = meta.fetchedAt;
    }
    if (state.metaFetched) {
      state.metaFetched.textContent = state.lastFetched ? formatTimestamp(state.lastFetched) : '—';
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
    const diff = state.pollMs - (Date.now() - new Date(state.lastFetched).getTime());
    state.metaCountdown.textContent = formatRelative(diff);
  }

  function startCountdown() {
    if (state.countdownTimer) {
      state.root.clearInterval(state.countdownTimer);
    }
    tickCountdown();
    state.countdownTimer = state.root.setInterval(tickCountdown, 1000);
  }

  function scheduleNextRefresh() {
    if (state.refreshTimer) {
      state.root.clearTimeout(state.refreshTimer);
    }
    const delay = Math.max(60, state.pollMs);
    state.refreshTimer = state.root.setTimeout(function () {
      refreshNow(false);
    }, delay);
  }

  function renderProviders(list) {
    state.providers = Array.isArray(list) ? list : [];
    if (!state.doc) {
      return;
    }
    state.providers.forEach(provider => {
      if (!provider || !provider.id) {
        return;
      }
      const selector = '.provider-card[data-id="' + provider.id + '"]';
      const card = state.doc.querySelector(selector);
      if (!card) {
        return;
      }
      const normalized = normalizeStatus(provider.stateCode || provider.status || provider.statusCode);
      const badge = card.querySelector('.status-badge');
      if (badge) {
        badge.textContent = normalized.label;
        badge.className = 'status-badge ' + normalized.className;
        if (badge.dataset) {
          badge.dataset.status = normalized.code;
        }
      }
      const summary = card.querySelector('.provider-card__summary');
      if (summary) {
        summary.textContent = provider.summary || provider.message || '';
      }
      const snark = card.querySelector('.provider-card__snark');
      if (snark) {
        snark.textContent = snarkOutage(provider.name || provider.provider || provider.id, normalized.label, provider.summary || provider.message || '');
      }
      const link = card.querySelector('.provider-details .provider-link');
      if (link && provider.url) {
        link.setAttribute('href', provider.url);
      }
      const incidentsWrap = card.querySelector('.provider-details .incidents');
      if (incidentsWrap) {
        incidentsWrap.children.length = 0;
        const incidents = Array.isArray(provider.incidents) ? provider.incidents : [];
        if (!incidents.length) {
          const empty = state.doc.createElement('p');
          empty.className = 'incident-empty';
          empty.textContent = 'No active incidents. Go write a chorus.';
          incidentsWrap.appendChild(empty);
        } else {
          incidents.forEach(item => {
            const entry = state.doc.createElement('article');
            entry.className = 'incident';
            const heading = state.doc.createElement('h4');
            heading.className = 'incident__title';
            heading.textContent = item.title || 'Incident';
            entry.appendChild(heading);
            const meta = state.doc.createElement('p');
            meta.className = 'incident__meta';
            const impact = item.impact ? String(item.impact).replace(/^[a-z]/, c => c.toUpperCase()) : 'Unknown';
            const updated = item.updatedAt || item.updated_at;
            meta.textContent = impact + (updated ? ' • ' + formatTimestamp(updated) : '');
            entry.appendChild(meta);
            const body = state.doc.createElement('p');
            body.className = 'incident__summary';
            body.textContent = item.summary || '';
            entry.appendChild(body);
            incidentsWrap.appendChild(entry);
          });
        }
      }
    });
  }

  function refreshNow(force) {
    if (!state.fetch || !state.config || !state.config.endpoint) {
      return Promise.resolve();
    }
    let url = state.config.endpoint;
    const separator = url.indexOf('?') === -1 ? '?' : '&';
    url += separator + '_=' + Date.now();
    if (force) {
      url += '&refresh=1';
    }
    setLoading(true);
    return state.fetch(url, { credentials: 'same-origin' })
      .then(res => {
        if (!res || !res.ok) {
          const status = res ? res.status : 0;
          throw new Error('HTTP ' + status);
        }
        return res.json();
      })
      .then(payload => {
        renderProviders(payload.providers || []);
        updateMeta(payload.meta || {});
        startCountdown();
        scheduleNextRefresh();
      })
      .catch(error => {
        if (state.metaCountdown) {
          state.metaCountdown.textContent = 'Refresh failed: ' + error.message;
        }
        scheduleNextRefresh();
      })
      .finally(() => {
        setLoading(false);
      });
  }

  function stopAutoRefresh() {
    if (state.countdownTimer) {
      state.root.clearInterval(state.countdownTimer);
      state.countdownTimer = null;
    }
    if (state.refreshTimer) {
      state.root.clearTimeout(state.refreshTimer);
      state.refreshTimer = null;
    }
  }

  function init(config) {
    stopAutoRefresh();
    state.config = config || {};
    state.pollMs = Number(state.config.pollInterval) || 300000;
    if (!Number.isFinite(state.pollMs) || state.pollMs <= 0) {
      state.pollMs = 300000;
    }
    state.doc = state.config.document || state.root.document || (typeof document !== 'undefined' ? document : null);
    if (!state.doc || typeof state.doc.querySelector !== 'function') {
      return;
    }
    state.container = state.config.container || state.doc.getElementById('lousy-outages') || state.doc.querySelector('.lousy-outages-board');
    if (!state.container) {
      return;
    }
    state.grid = state.container.querySelector('.providers-grid') || state.container.querySelector('[data-lo-grid]');
    state.metaFetched = state.container.querySelector('[data-lo-fetched]') || state.container.querySelector('.last-updated span');
    state.metaCountdown = state.container.querySelector('[data-lo-countdown]') || state.container.querySelector('.board-subtitle');
    state.refreshButton = state.container.querySelector('[data-lo-refresh]') || state.container.querySelector('.coin-btn');
    state.fetch = state.config.fetch || state.root.fetch;
    state.lastFetched = state.config.initial && state.config.initial.meta ? state.config.initial.meta.fetchedAt : null;

    renderProviders((state.config.initial && state.config.initial.providers) || state.providers);
    updateMeta(state.config.initial ? state.config.initial.meta : null);

    if (state.refreshButton && typeof state.refreshButton.addEventListener === 'function') {
      state.refreshButton.addEventListener('click', function () {
        refreshNow(true);
      });
    }

    refreshNow(false);
  }

  module.exports = {
    init,
    stopAutoRefresh,
    normalizeStatus,
    snarkOutage
  };
  return;
}

(function (window) {
  'use strict';

  var config = window.LousyOutagesConfig || {};
  var container = document.querySelector('.lousy-outages');
  if (!container) {
    return;
  }

  var metaFetched = container.querySelector('[data-lo-fetched]');
  var metaCountdown = container.querySelector('[data-lo-countdown]');
  var refreshButton = container.querySelector('[data-lo-refresh]');
  var grid = container.querySelector('[data-lo-grid]');

  var pollMs = Number(config.pollInterval) || 300000;
  var countdownTimer = null;
  var lastFetched = config.initial && config.initial.meta ? config.initial.meta.fetchedAt : null;
  var providers = (config.initial && config.initial.providers) || [];

  function formatTimestamp(iso) {
    if (!iso) {
      return '';
    }
    var date = new Date(iso);
    if (isNaN(date.getTime())) {
      return '';
    }
    return new Intl.DateTimeFormat(undefined, {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    }).format(date);
  }

  function formatRelative(ms) {
    if (!isFinite(ms)) {
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
    return minutes + 'm ' + rem + 's until refresh';
  }

  function formatImpact(impact) {
    if (!impact) {
      return 'Unknown impact';
    }
    impact = String(impact);
    return impact.charAt(0).toUpperCase() + impact.slice(1);
  }

  function buildIncidents(incidentList) {
    var wrapper = document.createElement('div');
    wrapper.className = 'lo-inc';

    var heading = document.createElement('strong');
    heading.textContent = 'Incidents';
    wrapper.appendChild(heading);

    if (!incidentList || !incidentList.length) {
      var empty = document.createElement('p');
      empty.className = 'lo-empty';
      empty.textContent = 'No active incidents';
      wrapper.appendChild(empty);
      return wrapper;
    }

    var list = document.createElement('ul');
    list.className = 'lo-inc-list';

    incidentList.forEach(function (incident) {
      var item = document.createElement('li');
      item.className = 'lo-inc-item';

      var title = document.createElement('p');
      title.className = 'lo-inc-title';
      title.textContent = incident.title || 'Incident';
      item.appendChild(title);

      var meta = document.createElement('p');
      meta.className = 'lo-inc-meta';
      var bits = [];
      if (incident.impact) {
        bits.push(formatImpact(incident.impact));
      }
      if (incident.updatedAt) {
        bits.push(formatTimestamp(incident.updatedAt));
      }
      meta.textContent = bits.join(' • ');
      item.appendChild(meta);

      if (incident.url) {
        var link = document.createElement('a');
        link.href = incident.url;
        link.textContent = 'Open incident';
        link.className = 'lo-status-link';
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        item.appendChild(link);
      }

      list.appendChild(item);
    });

    wrapper.appendChild(list);
    return wrapper;
  }

  function buildCard(provider) {
    var card = document.createElement('article');
    card.className = 'lo-card';
    card.dataset.providerId = provider.id;

    var prealert = provider && typeof provider.prealert === 'object' ? provider.prealert : {};
    var risk = Number(prealert && prealert.risk != null ? prealert.risk : provider.risk || 0);
    if (!isFinite(risk)) {
      risk = 0;
    }

    var head = document.createElement('div');
    head.className = 'lo-head';

    var title = document.createElement('h3');
    title.className = 'lo-title';
    title.textContent = provider.name || provider.provider || provider.id;
    head.appendChild(title);

    var pill = document.createElement('span');
    var stateCode = provider.stateCode || 'unknown';
    pill.className = 'lo-pill ' + stateCode;
    pill.textContent = provider.state || formatImpact(stateCode);
    head.appendChild(pill);

    if (risk >= 20) {
      var riskPill = document.createElement('span');
      riskPill.className = 'lo-pill risk';
      riskPill.textContent = 'RISK: ' + Math.round(risk) + '/100';
      head.appendChild(riskPill);
    }

    card.appendChild(head);

    if (provider.error) {
      var error = document.createElement('span');
      error.className = 'lo-error';
      error.textContent = 'Error: ' + provider.error;
      card.appendChild(error);
    }

    var summary = document.createElement('p');
    summary.className = 'lo-summary';
    summary.textContent = provider.summary || 'No status summary available.';
    card.appendChild(summary);

    if (risk > 0) {
      var measures = prealert && typeof prealert.measures === 'object' ? prealert.measures : {};
      var measureBits = [];
      if (measures && measures.latency_ms !== undefined && measures.latency_ms !== null && measures.latency_ms !== '') {
        measureBits.push('Latency ' + Number(measures.latency_ms) + ' ms');
      }
      if (measures && measures.baseline_ms !== undefined && measures.baseline_ms !== null && measures.baseline_ms !== '') {
        measureBits.push('Baseline ' + Number(measures.baseline_ms) + ' ms');
      }
      if (
        measures &&
        measures.downdetector_reports !== undefined &&
        measures.downdetector_reports !== null &&
        measures.downdetector_reports !== ''
      ) {
        var reportCount = Number(measures.downdetector_reports);
        if (isFinite(reportCount) && reportCount > 0) {
          var reportLabel = reportCount === 1 ? 'report' : 'reports';
          var reportText = 'Downdetector ' + reportCount + ' ' + reportLabel;
          var age = measures.downdetector_age_minutes;
          var ageNumber = Number(age);
          if (isFinite(ageNumber) && ageNumber >= 0) {
            reportText += ' (latest ' + Math.round(ageNumber) + 'm ago)';
          }
          measureBits.push(reportText);
        }
      }

      var prealertBlock = document.createElement('div');
      prealertBlock.className = 'lo-prealert';

      var preTitle = document.createElement('strong');
      preTitle.textContent = 'Pre-alerts';
      prealertBlock.appendChild(preTitle);

      var preSummary = document.createElement('p');
      preSummary.className = 'lo-prealert-summary';
      var summaryText = prealert && prealert.summary ? String(prealert.summary) : 'Early warning signals detected.';
      preSummary.textContent = summaryText;
      prealertBlock.appendChild(preSummary);

      if (measureBits.length) {
        var preMeasures = document.createElement('p');
        preMeasures.className = 'lo-prealert-metrics';
        preMeasures.textContent = measureBits.join(' • ');
        prealertBlock.appendChild(preMeasures);
      }

      card.appendChild(prealertBlock);
    }

    if (provider.snark) {
      var snark = document.createElement('p');
      snark.className = 'lo-snark';
      snark.textContent = provider.snark;
      card.appendChild(snark);
    }

    card.appendChild(buildIncidents(provider.incidents || []));

    if (provider.url) {
      var view = document.createElement('a');
      view.href = provider.url;
      view.target = '_blank';
      view.rel = 'noopener noreferrer';
      view.className = 'lo-status-link';
      view.textContent = 'View status →';
      card.appendChild(view);
    }

    return card;
  }

  function renderProviders(list) {
    providers = Array.isArray(list) ? list : [];
    if (!grid) {
      return;
    }
    grid.innerHTML = '';

    if (!providers.length) {
      var emptyCard = document.createElement('article');
      emptyCard.className = 'lo-card';
      var message = document.createElement('p');
      message.className = 'lo-summary';
      message.textContent = 'No providers selected yet.';
      emptyCard.appendChild(message);
      grid.appendChild(emptyCard);
      return;
    }

    providers.forEach(function (provider) {
      grid.appendChild(buildCard(provider));
    });
  }

  function updateMeta(meta) {
    if (!meta) {
      return;
    }
    lastFetched = meta.fetchedAt || lastFetched;
    if (metaFetched) {
      metaFetched.textContent = lastFetched ? formatTimestamp(lastFetched) : '—';
    }
  }

  function tickCountdown() {
    if (!metaCountdown) {
      return;
    }
    if (!lastFetched) {
      metaCountdown.textContent = 'Waiting for first refresh…';
      return;
    }
    var diff = pollMs - (Date.now() - new Date(lastFetched).getTime());
    metaCountdown.textContent = formatRelative(diff);
  }

  function startCountdown() {
    if (countdownTimer) {
      window.clearInterval(countdownTimer);
    }
    tickCountdown();
    countdownTimer = window.setInterval(tickCountdown, 1000);
  }

  function setLoading(isLoading) {
    if (!refreshButton) {
      return;
    }
    refreshButton.disabled = isLoading;
  }

  function refreshNow(force) {
    if (!config.endpoint) {
      return;
    }
    setLoading(true);
    var url = config.endpoint;
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    url += separator + '_=' + Date.now();
    if (force) {
      url += '&refresh=1';
    }

    fetch(url, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (payload) {
        if (!payload) {
          throw new Error('Empty payload');
        }
        renderProviders(payload.providers || []);
        updateMeta(payload.meta || {});
        startCountdown();
      })
      .catch(function (error) {
        if (metaCountdown) {
          metaCountdown.textContent = 'Refresh failed: ' + error.message;
        }
      })
      .finally(function () {
        setLoading(false);
      });
  }

  if (refreshButton) {
    refreshButton.addEventListener('click', function () {
      refreshNow(true);
    });
  }

  renderProviders(providers);
  updateMeta(config.initial ? config.initial.meta : null);
  startCountdown();
})(window);
