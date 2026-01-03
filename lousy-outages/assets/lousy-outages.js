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
  var THEME_STORAGE_KEY = 'lo-theme-preference';
  var VISIBILITY_STORAGE_KEY = 'lo-visible-providers';
  var HISTORY_LIMIT = 80;
  var HISTORY_RENDER_LIMIT = 12;
  var HISTORY_DEFAULT_DAYS = 30;

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
    themeToggle: null,
    exportCSVButton: null,
    exportPDFButton: null,
    providerToggles: [],
    mediaQuery: null,
    loadingEl: null,
    fetchedAt: null,
    isRefreshing: false,
    pendingManual: false,
    lastFetchStartedAt: null,
    manualQueued: false,
    staleRefreshQueued: false,
    staleRefreshInFlight: false,
    lastStaleRefreshAttempt: null,
    degradedDueToStale: false,
    historyEndpoint: '',
    historyList: null,
    historyEmpty: null,
    historyError: null,
    historyCharts: null,
    historyImportantOnly: true,
    historyWindowDays: HISTORY_DEFAULT_DAYS,
    historyProviders: [],
    historyMeta: {},
    historyIncidents: [],
    historyExpanded: false,
    historyToggleButton: null,
    visibleProviders: {},
    reportForm: null,
    reportProvider: null,
    reportProviderNameWrap: null,
    reportProviderName: null,
    reportSummary: null,
    reportContact: null,
    reportStatus: null,
    reportSubmit: null,
    reportCaptchaPhrase: null,
    reportCaptchaInput: null,
    reportCaptchaToken: null,
    reportCaptchaRefresh: null,
    reportPhraseEndpoint: '',
    reportCaptchaTokenValue: '',
    reportProvidersInitialized: false,
    debug: false,
    postPaintStarted: false
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

  function getStoredTheme() {
    if (!state.root || !state.root.localStorage) {
      return null;
    }
    try {
      return state.root.localStorage.getItem(THEME_STORAGE_KEY);
    } catch (err) {
      return null;
    }
  }

  function storeTheme(theme) {
    if (!state.root || !state.root.localStorage) {
      return;
    }
    try {
      state.root.localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch (err) {
      // Ignore storage errors (private mode, etc.).
    }
  }

  function clearStoredTheme() {
    if (!state.root || !state.root.localStorage) {
      return;
    }
    try {
      state.root.localStorage.removeItem(THEME_STORAGE_KEY);
    } catch (err) {
      // Ignore storage errors.
    }
  }

  function updateThemeToggleLabel(theme) {
    if (!state.themeToggle) {
      return;
    }
    var isLight = theme === 'light';
    var next = isLight ? 'dark' : 'light';
    state.themeToggle.textContent = isLight ? 'Switch to dark mode' : 'Switch to light mode';
    state.themeToggle.setAttribute('aria-pressed', isLight ? 'true' : 'false');
    state.themeToggle.setAttribute('aria-label', 'Switch to ' + next + ' mode');
  }

  function applyTheme(theme, persist) {
    if (!state.container || !state.container.classList) {
      return;
    }
    var desired = theme === 'light' ? 'light' : 'dark';
    state.container.classList.remove('lo-theme-light', 'lo-theme-dark');
    state.container.classList.add(desired === 'light' ? 'lo-theme-light' : 'lo-theme-dark');
    updateThemeToggleLabel(desired);
    if (persist) {
      storeTheme(desired);
    }
  }

  function loadVisibleProviders() {
    if (!state.root || !state.root.localStorage) {
      return {};
    }
    try {
      var raw = state.root.localStorage.getItem(VISIBILITY_STORAGE_KEY);
      if (!raw) {
        return {};
      }
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (err) {
      return {};
    }
  }

  function persistVisibleProviders(prefs) {
    if (!state.root || !state.root.localStorage) {
      return;
    }
    try {
      state.root.localStorage.setItem(VISIBILITY_STORAGE_KEY, JSON.stringify(prefs || {}));
    } catch (err) {
      // Ignore storage errors.
    }
  }

  function applyProviderVisibility() {
    if (!state.container) {
      return;
    }
    var cards = state.container.querySelectorAll('.lo-card');
    if (!cards || !cards.length) {
      return;
    }
    var prefs = state.visibleProviders || {};
    var hasPrefs = Object.keys(prefs).length > 0;
    cards.forEach(function (card) {
      var slug = card.getAttribute('data-provider-id');
      var show = true;
      if (slug) {
        if (hasPrefs) {
          show = prefs[slug] !== false;
        }
      }
      if (show) {
        card.classList.remove('lo-card--hidden');
        card.removeAttribute('data-lo-hidden');
        card.setAttribute('aria-hidden', 'false');
      } else {
        card.classList.add('lo-card--hidden');
        card.setAttribute('data-lo-hidden', 'true');
        card.setAttribute('aria-hidden', 'true');
      }
    });
  }

  function syncProviderToggleUI() {
    if (!state.providerToggles || !state.providerToggles.length) {
      return;
    }
    var prefs = state.visibleProviders || {};
    var hasPrefs = Object.keys(prefs).length > 0;
    state.providerToggles.forEach(function (input) {
      var slug = input.value;
      if (!slug) {
        return;
      }
      var checked = hasPrefs ? prefs[slug] !== false : true;
      input.checked = checked;
    });
  }

  function handleProviderToggle(event) {
    var target = event.target;
    if (!target || !target.value) {
      return;
    }
    var prefs = state.visibleProviders || {};
    prefs[target.value] = !!target.checked;
    state.visibleProviders = prefs;
    persistVisibleProviders(prefs);
    applyProviderVisibility();
  }

  function setAllProvidersVisibility(visible) {
    if (!state.providerToggles || !state.providerToggles.length) {
      return;
    }
    var prefs = {};
    state.providerToggles.forEach(function (input) {
      if (!input || !input.value) {
        return;
      }
      input.checked = visible;
      if (!visible) {
        prefs[input.value] = false;
      }
    });
    state.visibleProviders = prefs;
    persistVisibleProviders(prefs);
    applyProviderVisibility();
  }

  function initProviderVisibility() {
    state.visibleProviders = loadVisibleProviders();
    var toggles = state.container ? state.container.querySelectorAll('[data-lo-provider-toggle]') : [];
    state.providerToggles = toggles ? Array.prototype.slice.call(toggles) : [];
    if (state.providerToggles.length) {
      state.providerToggles.forEach(function (input) {
        input.addEventListener('change', handleProviderToggle);
      });
      syncProviderToggleUI();
    }

    var selectAll = state.container ? state.container.querySelector('[data-lo-provider-select="all"]') : null;
    if (selectAll) {
      selectAll.addEventListener('click', function (event) {
        event.preventDefault();
        setAllProvidersVisibility(true);
        syncProviderToggleUI();
      });
    }

    var selectNone = state.container ? state.container.querySelector('[data-lo-provider-select="none"]') : null;
    if (selectNone) {
      selectNone.addEventListener('click', function (event) {
        event.preventDefault();
        setAllProvidersVisibility(false);
        syncProviderToggleUI();
      });
    }
    applyProviderVisibility();
  }

  function handleSystemThemeChange(event) {
    if (getStoredTheme()) {
      return;
    }
    applyTheme(event.matches ? 'light' : 'dark', false);
  }

  function initThemePreference() {
    var stored = getStoredTheme();
    var preferred = stored;

    if (state.root && state.root.matchMedia) {
      state.mediaQuery = state.root.matchMedia('(prefers-color-scheme: light)');
      if (!preferred) {
        preferred = state.mediaQuery.matches ? 'light' : 'dark';
      }
      if (state.mediaQuery.addEventListener) {
        state.mediaQuery.addEventListener('change', handleSystemThemeChange);
      } else if (state.mediaQuery.addListener) {
        state.mediaQuery.addListener(handleSystemThemeChange);
      }
    }

    applyTheme(preferred || 'dark', Boolean(stored));
  }

  function toggleTheme() {
    var isLight = state.container && state.container.classList.contains('lo-theme-light');
    var next = isLight ? 'dark' : 'light';
    applyTheme(next, true);
  }

  function getText(target, selector) {
    if (!target) {
      return '';
    }
    var node = selector ? target.querySelector(selector) : target;
    if (!node || typeof node.textContent !== 'string') {
      return '';
    }
    return node.textContent.trim();
  }

  function getHistoryChartData() {
    var meta = state.historyMeta || {};
    var providers = state.historyProviders || [];

    var providerCounts = meta && meta.provider_counts && typeof meta.provider_counts === 'object'
      ? meta.provider_counts
      : null;
    var dailyCounts = meta && meta.daily_counts && typeof meta.daily_counts === 'object'
      ? meta.daily_counts
      : null;
    var yoy = meta && meta.year_over_year && typeof meta.year_over_year === 'object'
      ? meta.year_over_year
      : null;

    if (!providerCounts) {
      providerCounts = {};
      (Array.isArray(providers) ? providers : []).forEach(function (provider) {
        if (!provider || !Array.isArray(provider.incidents)) {
          return;
        }
        var label = provider.label || provider.name || provider.provider || 'Provider';
        providerCounts[label] = (providerCounts[label] || 0) + provider.incidents.length;
      });
    }

    if (!dailyCounts) {
      dailyCounts = {};
      (Array.isArray(providers) ? providers : []).forEach(function (provider) {
        if (!provider || !Array.isArray(provider.incidents)) {
          return;
        }
        provider.incidents.forEach(function (incident) {
          var dateStr = incident.first_seen || incident.firstSeen || incident.last_seen || incident.lastSeen || '';
          if (!dateStr) {
            return;
          }
          var parsed = Date.parse(dateStr);
          if (!Number.isNaN(parsed)) {
            var formatted = new Date(parsed).toISOString().slice(0, 10);
            dailyCounts[formatted] = (dailyCounts[formatted] || 0) + 1;
          }
        });
      });
    }

    var windowDays = meta && meta.window_days ? parseInt(meta.window_days, 10) : state.historyWindowDays;

    return {
      providerCounts: providerCounts,
      dailyCounts: dailyCounts,
      windowDays: Number.isFinite(windowDays) && windowDays > 0 ? windowDays : HISTORY_DEFAULT_DAYS,
      yearOverYear: yoy
    };
  }

  function downloadCSV() {
    var incidents = Array.isArray(state.historyIncidents) ? state.historyIncidents : [];
    if (!incidents.length) {
      if (state.root && state.root.alert) {
        state.root.alert('No incidents to export yet. Try refreshing history.');
      }
      return;
    }

    var rows = [
      ['first_seen', 'last_seen', 'provider', 'severity', 'status', 'summary', 'url']
    ];

    incidents.forEach(function (entry) {
      rows.push([
        entry.first_seen || '',
        entry.last_seen || '',
        entry.provider || '',
        String(entry.severity || '').toUpperCase(),
        entry.status || '',
        entry.summary || '',
        entry.url || ''
      ]);
    });

    var csvContent = rows.map(function (row) {
      return row.map(function (field) {
        var safe = (field || '').toString().replace(/"/g, '""');
        return '"' + safe + '"';
      }).join(',');
    }).join('\n');

    var blob = new Blob([csvContent], { type: 'text/csv' });
    var urlCreator = (state.root && state.root.URL) ? state.root.URL : (typeof URL !== 'undefined' ? URL : null);
    if (!urlCreator || !urlCreator.createObjectURL) {
      return;
    }
    var url = urlCreator.createObjectURL(blob);
    var link = state.doc.createElement('a');
    link.href = url;
    link.download = 'lousy-outages-history.csv';
    state.doc.body.appendChild(link);
    link.click();
    state.doc.body.removeChild(link);
    if (urlCreator.revokeObjectURL) {
      urlCreator.revokeObjectURL(url);
    }
  }

  function exportPDF() {
    if (!state.root || !state.historyCharts) {
      return;
    }

    var hasCharts = state.historyCharts.querySelector && state.historyCharts.querySelector('svg');
    if (!hasCharts) {
      return;
    }

    var clone = state.historyCharts.cloneNode(true);
    clone.removeAttribute('hidden');

    var printWindow = state.root.open('', '_blank');
    if (!printWindow || !printWindow.document || typeof printWindow.document.write !== 'function') {
      return;
    }

    var styles = [
      "@import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap');",
      'body { margin: 0; padding: 24px; font-family: \"Press Start 2P\", \"VT323\", \"IBM Plex Mono\", monospace; background: #050510; color: #9af3ff; text-shadow: 0 0 6px rgba(0, 255, 255, 0.35); }',
      '.lo-history__chart { margin-bottom: 24px; padding: 16px; border: 2px solid #1bf0ff; box-shadow: 0 0 0 2px #ff2fb3 inset, 0 0 18px rgba(27, 240, 255, 0.25); background: linear-gradient(135deg, rgba(0, 255, 170, 0.05) 0%, rgba(255, 47, 179, 0.05) 100%), #0b0b1e; }',
      '.lo-history__chart-title { font-size: 12px; font-weight: 700; margin-bottom: 8px; letter-spacing: 1px; text-transform: uppercase; color: #f5f5f5; }',
      '.lo-history__subtitle { color: #72f3ff; font-size: 10px; letter-spacing: 0.5px; margin-bottom: 8px; }',
      '.lo-history__chart svg, .lo-history__chart canvas { width: 100%; height: auto; border: 1px solid #2affd5; background: repeating-linear-gradient(90deg, rgba(42, 255, 213, 0.08) 0, rgba(42, 255, 213, 0.08) 6px, transparent 6px, transparent 12px), #050510; image-rendering: pixelated; }',
      '.lo-history__chart-bar { fill: #26ffd6; stroke: #000; stroke-width: 1; }',
      '.lo-history__chart-bar--thin { fill: #ff2fb3; }',
      '.lo-history__chart-bar--active { filter: drop-shadow(0 0 4px rgba(255, 47, 179, 0.8)); }',
      '.lo-history__chart-text { font-size: 9px; fill: #e4f7ff; text-shadow: 0 0 4px rgba(0, 255, 255, 0.25); }',
      '.lo-history__chart text { font-family: \"Press Start 2P\", \"VT323\", \"IBM Plex Mono\", monospace; }',
      '.lo-history__chart-tooltip { margin-top: 6px; font-size: 10px; color: #f6ff8f; }',
      '.lo-retro-legend { display: flex; gap: 10px; align-items: center; margin-top: 10px; font-size: 9px; color: #fefefe; }',
      '.lo-retro-legend__swatch { width: 14px; height: 14px; border: 2px solid #0b0b1e; box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.2); display: inline-block; margin-right: 6px; }'
    ].join('\n');

    var title = 'Lousy Outages – Incident charts';
    var html = [
      '<!doctype html>',
      '<html>',
      '<head>',
      '<meta charset="utf-8">',
      '<title>' + title + '</title>',
      '<style>' + styles + '</style>',
      '</head>',
      '<body>',
      '<h1 style="font-size:18px; margin:0 0 16px;">' + title + '</h1>',
      clone.innerHTML,
      '</body>',
      '</html>'
    ].join('');

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    var triggerPrint = function () {
      if (printWindow.focus) {
        printWindow.focus();
      }
      if (typeof printWindow.print === 'function') {
        printWindow.print();
      }
    };

    if (printWindow.document.readyState === 'complete') {
      triggerPrint();
    } else {
      printWindow.addEventListener('load', triggerPrint);
    }
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
    applyProviderVisibility();
  }


  function getSummaryText(provider) {
    if (!provider) {
      return '';
    }
    return provider.summary || provider.message || '';
  }

  function condenseIncidentTitle(providerSlug, text) {
    var raw = String(text || '').replace(/\s+/g, ' ').trim();
    if (!raw) {
      return { text: '', title: '' };
    }

    var display = raw;
    var slug = String(providerSlug || '').toLowerCase();
    if (slug === 'zscaler') {
      var parts = display.split(' - ');
      if (parts.length > 1) {
        var rightSide = parts.slice(1).join(' - ').trim();
        if (rightSide && /(\.com|\.net)/i.test(rightSide) && /,/.test(rightSide)) {
          display = parts[0].trim();
        }
      }
    }

    if (display.length > 140) {
      display = display.slice(0, 137).replace(/\s+$/, '') + '…';
    }

    return { text: display, title: raw };
  }

  function getProviderSlug(provider) {
    if (!provider) {
      return '';
    }
    return String(provider.id || provider.slug || provider.provider || provider.name || '').toLowerCase();
  }

  function updateLegacyCard(card, provider, normalized) {
    var providerSlug = getProviderSlug(provider);
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
          var summaryText = '';
          if (incident.summary) {
            var condensedSummary = condenseIncidentTitle(providerSlug, incident.summary);
            summaryText = ' — ' + condensedSummary.text;
            if (condensedSummary.title && condensedSummary.title !== condensedSummary.text) {
              item.setAttribute('title', condensedSummary.title);
            }
          }
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
    var providerSlug = getProviderSlug(provider);
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
          var condensedName = condenseIncidentTitle(providerSlug, incident.name || 'Incident');
          title.textContent = condensedName.text || 'Incident';
          if (condensedName.title && condensedName.title !== condensedName.text) {
            title.setAttribute('title', condensedName.title);
          }
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
            var condensedSummary = condenseIncidentTitle(providerSlug, incident.summary);
            details.textContent = condensedSummary.text;
            if (condensedSummary.title && condensedSummary.title !== condensedSummary.text) {
              details.setAttribute('title', condensedSummary.title);
            }
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

  function mapHistoryStatus(code) {
    var key = String(code || '').toLowerCase();
    if (
      key === 'major_outage' ||
      key === 'critical' ||
      key === 'outage' ||
      key === 'major'
    ) {
      return { label: 'Outage', className: 'outage' };
    }
    if (
      key === 'minor_outage' ||
      key === 'partial_outage' ||
      key === 'degraded_performance' ||
      key === 'partial' ||
      key === 'degraded' ||
      key === 'incident'
    ) {
      return { label: 'Degraded', className: 'degraded' };
    }
    if (key === 'maintenance' || key === 'maintenance_window') {
      return { label: 'Maintenance', className: 'maintenance' };
    }
    if (key === 'operational' || key === 'none' || key === 'ok') {
      return { label: 'Operational', className: 'operational' };
    }
    return { label: 'Unknown', className: 'unknown' };
  }

  function parseIncidentTimestamp(value) {
    if (typeof value === 'number') {
      return value;
    }
    if (value instanceof Date) {
      return value.getTime();
    }
    var parsed = Date.parse(String(value || ''));
    if (Number.isNaN(parsed)) {
      return 0;
    }
    return parsed;
  }

  function getIncidentStartTs(incident) {
    if (!incident || typeof incident !== 'object') {
      return 0;
    }
    return parseIncidentTimestamp(
      incident.started_at ||
      incident.first_seen ||
      incident.detected_at ||
      incident.updated_at ||
      incident.last_seen
    );
  }

  function getIncidentUpdatedTs(incident) {
    if (!incident || typeof incident !== 'object') {
      return 0;
    }
    return parseIncidentTimestamp(incident.updated_at || incident.last_seen);
  }

  function severityRank(sev) {
    var code = String(sev || '').toLowerCase();
    if (code === 'outage' || code === 'major_outage' || code === 'major' || code === 'critical') {
      return 4;
    }
    if (
      code === 'degraded' ||
      code === 'partial' ||
      code === 'partial_outage' ||
      code === 'degraded_performance' ||
      code === 'incident'
    ) {
      return 3;
    }
    if (code === 'maintenance') {
      return 2;
    }
    if (code === 'info') {
      return 1;
    }
    return 0;
  }

  function normalizeIncidentTitle(value) {
    var text = String(value || '').toLowerCase();
    text = text.replace(/\bdetails\b/g, '');
    text = text.replace(/([!?.:,;])\1+/g, '$1');
    text = text.replace(/\s+/g, ' ').trim();
    return text;
  }

  function dedupeIncidents(list) {
    if (!Array.isArray(list)) {
      return [];
    }
    var seen = {};
    list.forEach(function (entry) {
      if (!entry || typeof entry !== 'object') {
        return;
      }
      var providerSlug = String(entry.provider_id || entry.provider || '').toLowerCase();
      var titleKey = normalizeIncidentTitle(entry.summary || entry.title || '');
      var startTs = getIncidentStartTs(entry);
      var key = providerSlug + '|' + titleKey + '|' + Math.floor(startTs / 60000);
      var updatedTs = getIncidentUpdatedTs(entry);
      if (!seen[key] || getIncidentUpdatedTs(seen[key]) < updatedTs) {
        seen[key] = entry;
      }
    });
    return Object.keys(seen).map(function (key) {
      return seen[key];
    });
  }

  function formatHistoryDate(value) {
    if (!value) {
      return '';
    }
    var parsed = Date.parse(value + 'T00:00:00Z');
    if (Number.isNaN(parsed)) {
      return value;
    }
    try {
      return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
        year: 'numeric'
      }).format(new Date(parsed));
    } catch (err) {
      return new Date(parsed).toISOString().slice(0, 10);
    }
  }

  function formatIncidentCount(count) {
    var safe = Number.isFinite(count) ? count : 0;
    if (safe === 1) {
      return '1 incident';
    }
    return safe + ' incidents';
  }

  function formatDateRange(meta) {
    if (!meta) {
      return '';
    }
    var startRaw = meta.window_start || meta.windowStart;
    var endRaw = meta.window_end || meta.windowEnd;
    if (!startRaw || !endRaw) {
      return '';
    }

    var startDate = Date.parse(startRaw + 'T00:00:00Z');
    var endDate = Date.parse(endRaw + 'T00:00:00Z');
    if (Number.isNaN(startDate) || Number.isNaN(endDate)) {
      return '';
    }

    var startObj = new Date(startDate);
    var endObj = new Date(endDate);
    var startYear = startObj.getUTCFullYear();
    var endYear = endObj.getUTCFullYear();

    var formatter = function (date, includeYear) {
      try {
        return new Intl.DateTimeFormat(undefined, {
          month: 'short',
          day: 'numeric',
          year: includeYear ? 'numeric' : undefined
        }).format(date);
      } catch (err) {
        var iso = date.toISOString().slice(0, 10);
        return includeYear ? iso : iso.slice(5);
      }
    };

    var startLabel = formatter(startObj, startYear !== endYear);
    var endLabel = formatter(endObj, true);

    return startLabel + ' – ' + endLabel;
  }

  function renderHistoryCharts(providers, meta) {
    if (!state.historyCharts || !state.doc) {
      return;
    }

    state.historyCharts.innerHTML = '';
    var chartData = getHistoryChartData();
    var providerCounts = chartData ? chartData.providerCounts : null;
    var dailyCounts = chartData ? chartData.dailyCounts : null;
    var yoy = chartData ? chartData.yearOverYear : null;
    var hasProviders = providerCounts && Object.keys(providerCounts).length > 0;
    var hasDaily = dailyCounts && Object.keys(dailyCounts).length > 0;

    if (!hasProviders && !hasDaily) {
      state.historyCharts.setAttribute('hidden', 'hidden');
      return;
    }

    state.historyCharts.removeAttribute('hidden');
    var frag = state.doc.createDocumentFragment();

    var subtitle = formatDateRange(meta || {});

    if (hasProviders) {
      var providerChart = buildBarChart(providerCounts, 'Incidents by provider', subtitle);
      frag.appendChild(providerChart);
    }

    if (hasDaily) {
      var timelineChart = buildTimelineChart(
        dailyCounts,
        (chartData && chartData.windowDays) || HISTORY_DEFAULT_DAYS,
        subtitle
      );
      frag.appendChild(timelineChart);
    }

    if (yoy && yoy.current && yoy.previous) {
      var yoyChart = buildYearOverYearChart(
        yoy,
        subtitle,
        (chartData && chartData.windowDays) || HISTORY_DEFAULT_DAYS
      );
      if (yoyChart) {
        frag.appendChild(yoyChart);
      }
    }

    state.historyCharts.appendChild(frag);
  }

  function buildBarChart(counts, title, subtitle) {
    var chartWrap = state.doc.createElement('div');
    chartWrap.className = 'lo-history__chart';
    if (title) {
      var heading = state.doc.createElement('div');
      heading.className = 'lo-history__chart-title';
      heading.textContent = title;
      chartWrap.appendChild(heading);
      if (subtitle) {
        var sub = state.doc.createElement('div');
        sub.className = 'lo-history__subtitle';
        sub.textContent = subtitle;
        chartWrap.appendChild(sub);
      }
    }

    var entries = Object.keys(counts).map(function (key) {
      return { label: key, value: counts[key] };
    }).sort(function (a, b) { return b.value - a.value; }).slice(0, 8);
    var max = entries.reduce(function (acc, item) { return Math.max(acc, item.value || 0); }, 0) || 1;
    var width = Math.max(220, entries.length * 70);
    var height = 160;
    var topPadding = 30;
    var bottomPadding = 30;
    var barWidth = Math.floor((width - 20) / Math.max(entries.length, 1)) - 10;
    var svg = state.doc.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Incidents by provider');

    var yLabel = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
    yLabel.setAttribute('x', '6');
    yLabel.setAttribute('y', '18');
    yLabel.setAttribute('class', 'lo-history__chart-text');
    yLabel.textContent = 'Incidents';
    svg.appendChild(yLabel);

    entries.forEach(function (entry, index) {
      var x = 10 + index * (barWidth + 10);
      var scaled = Math.max(4, Math.round(((entry.value || 0) / max) * (height - topPadding - bottomPadding)));
      var y = height - bottomPadding - scaled;

      var rect = state.doc.createElementNS('http://www.w3.org/2000/svg', 'rect');
      rect.setAttribute('x', String(x));
      rect.setAttribute('y', String(y));
      rect.setAttribute('width', String(barWidth));
      rect.setAttribute('height', String(scaled));
      rect.setAttribute('class', 'lo-history__chart-bar');
      rect.setAttribute('data-count', String(entry.value));
      rect.setAttribute('data-label', entry.label);
      var rectTitle = state.doc.createElementNS('http://www.w3.org/2000/svg', 'title');
      rectTitle.textContent = entry.label + ' – ' + entry.value + ' incidents';
      rect.appendChild(rectTitle);
      svg.appendChild(rect);

      if (scaled >= 20) {
        var valueLabel = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
        valueLabel.setAttribute('x', String(x + (barWidth / 2)));
        valueLabel.setAttribute('y', String(Math.max(topPadding, y - 6)));
        valueLabel.setAttribute('text-anchor', 'middle');
        valueLabel.setAttribute('class', 'lo-history__chart-text');
        valueLabel.textContent = String(entry.value);
        svg.appendChild(valueLabel);
      }

      var label = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
      label.setAttribute('x', String(x + (barWidth / 2)));
      label.setAttribute('y', String(height - 12));
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('class', 'lo-history__chart-text');
      label.textContent = entry.label.length > 8 ? entry.label.slice(0, 8) + '…' : entry.label;
      svg.appendChild(label);
    });

    chartWrap.appendChild(svg);
    return chartWrap;
  }

  function buildTimelineChart(counts, days, subtitle) {
    var chartWrap = state.doc.createElement('div');
    chartWrap.className = 'lo-history__chart';
    var heading = state.doc.createElement('div');
    heading.className = 'lo-history__chart-title';
    heading.textContent = 'Incidents over time';
    chartWrap.appendChild(heading);
    if (subtitle) {
      var sub = state.doc.createElement('div');
      sub.className = 'lo-history__subtitle';
      sub.textContent = subtitle;
      chartWrap.appendChild(sub);
    }

    var today = new Date();
    var labels = [];
    for (var i = days - 1; i >= 0; i -= 1) {
      var dt = new Date(today.getTime() - i * 24 * 60 * 60 * 1000);
      labels.push(dt.toISOString().slice(0, 10));
    }

    var values = labels.map(function (label) { return counts[label] || 0; });
    var totalIncidents = 0;
    var activeDays = 0;
    var maxCount = 0;
    var peakDate = '';

    values.forEach(function (value, idx) {
      totalIncidents += value;
      if (value > 0) {
        activeDays += 1;
      }
      if (value > maxCount) {
        maxCount = value;
        peakDate = labels[idx];
      }
    });

    var friendlyFormatter = function (dateStr) {
      if (!dateStr) {
        return '';
      }
      var parsed = Date.parse(dateStr + 'T00:00:00Z');
      if (Number.isNaN(parsed)) {
        return dateStr;
      }
      try {
        return new Intl.DateTimeFormat(undefined, {
          month: 'short',
          day: 'numeric'
        }).format(new Date(parsed));
      } catch (err) {
        return new Date(parsed).toISOString().slice(0, 10);
      }
    };

    var meta = state.doc.createElement('div');
    meta.className = 'lo-history__meta';
    if (totalIncidents <= 0) {
      meta.textContent = 'No incidents recorded during this window.';
    } else {
      var dayLabel = labels.length === 1 ? 'day' : 'days';
      var summary = formatIncidentCount(totalIncidents) + ' across ' + activeDays + ' of ' + labels.length + ' ' + dayLabel;
      if (maxCount > 0 && peakDate) {
        summary += ' (busiest: ' + friendlyFormatter(peakDate) + ' with ' + formatIncidentCount(maxCount) + ').';
      } else {
        summary += '.';
      }
      meta.textContent = summary;
    }
    chartWrap.appendChild(meta);

    var tooltip = null;
    try {
      tooltip = state.doc.createElement('div');
      tooltip.className = 'lo-history__chart-tooltip';
      tooltip.setAttribute('role', 'status');
      tooltip.setAttribute('aria-live', 'polite');
      tooltip.textContent = 'Hover or focus a bar to see details.';
      chartWrap.appendChild(tooltip);
    } catch (err) {
      tooltip = null;
    }

    var max = values.reduce(function (acc, val) { return Math.max(acc, val); }, 0) || 1;
    var width = Math.max(240, labels.length * 12);
    var height = 170;
    var topPadding = 28;
    var bottomPadding = 46;
    var svg = state.doc.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Incidents over time');

    var yAxisLabel = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
    yAxisLabel.setAttribute('x', '6');
    yAxisLabel.setAttribute('y', '18');
    yAxisLabel.setAttribute('class', 'lo-history__chart-text');
    yAxisLabel.textContent = 'Incidents per day';
    svg.appendChild(yAxisLabel);

    var xAxisLabel = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
    xAxisLabel.setAttribute('x', String(width / 2));
    xAxisLabel.setAttribute('y', String(height - 8));
    xAxisLabel.setAttribute('text-anchor', 'middle');
    xAxisLabel.setAttribute('class', 'lo-history__chart-text');
    xAxisLabel.textContent = 'Day';
    svg.appendChild(xAxisLabel);

    var step = Math.max(1, Math.ceil(labels.length / 8));

    labels.forEach(function (label, idx) {
      var x = 8 + idx * 8;
      var scaled = Math.max(2, Math.round((values[idx] / max) * (height - topPadding - bottomPadding)));
      var y = height - bottomPadding - scaled;
      var rect = state.doc.createElementNS('http://www.w3.org/2000/svg', 'rect');
      rect.setAttribute('x', String(x));
      rect.setAttribute('y', String(y));
      rect.setAttribute('width', '6');
      rect.setAttribute('height', String(scaled));
      rect.setAttribute('class', 'lo-history__chart-bar lo-history__chart-bar--thin');
      rect.setAttribute('tabindex', '0');
      var friendlyLabel = friendlyFormatter(label);
      rect.setAttribute('aria-label', friendlyLabel + ': ' + formatIncidentCount(values[idx]));
      var barTitle = state.doc.createElementNS('http://www.w3.org/2000/svg', 'title');
      barTitle.textContent = label + ' – ' + values[idx] + ' incidents';
      rect.appendChild(barTitle);

      var activateBar = function () {
        if (tooltip) {
          tooltip.textContent = friendlyLabel + ': ' + formatIncidentCount(values[idx]);
        }
        rect.classList.add('lo-history__chart-bar--active');
      };

      var deactivateBar = function () {
        if (tooltip) {
          tooltip.textContent = 'Hover or focus a bar to see details.';
        }
        rect.classList.remove('lo-history__chart-bar--active');
      };

      rect.addEventListener('mouseenter', activateBar);
      rect.addEventListener('focus', activateBar);
      rect.addEventListener('mouseleave', deactivateBar);
      rect.addEventListener('blur', deactivateBar);

      svg.appendChild(rect);

      if (scaled > 14 && values[idx] > 0) {
        var countLabel = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
        countLabel.setAttribute('x', String(x + 3));
        countLabel.setAttribute('y', String(Math.max(topPadding, y - 3)));
        countLabel.setAttribute('text-anchor', 'middle');
        countLabel.setAttribute('class', 'lo-history__chart-text');
        countLabel.textContent = String(values[idx]);
        svg.appendChild(countLabel);
      }

      if (idx % step === 0 || idx === labels.length - 1) {
        var tick = state.doc.createElementNS('http://www.w3.org/2000/svg', 'text');
        tick.setAttribute('x', String(x + 3));
        tick.setAttribute('y', String(height - 16));
        tick.setAttribute('text-anchor', 'middle');
        tick.setAttribute('class', 'lo-history__chart-text');
        tick.setAttribute('transform', 'rotate(-45 ' + (x + 3) + ' ' + (height - 16) + ')');
        tick.textContent = friendlyLabel || label.slice(5);
        svg.appendChild(tick);
      }
    });

    chartWrap.appendChild(svg);
    return chartWrap;
  }

  function buildYearOverYearChart(comparison, subtitle, days) {
    if (!comparison || !comparison.current || !comparison.previous || !state.doc) {
      return null;
    }

    var windowDays = Number.isFinite(days) && days > 0 ? days : HISTORY_DEFAULT_DAYS;
    var chartWrap = state.doc.createElement('div');
    chartWrap.className = 'lo-history__chart';

    var heading = state.doc.createElement('div');
    heading.className = 'lo-history__chart-title';
    heading.textContent = 'Year-over-year incident pulse';
    chartWrap.appendChild(heading);

    if (subtitle) {
      var sub = state.doc.createElement('div');
      sub.className = 'lo-history__subtitle';
      sub.textContent = subtitle;
      chartWrap.appendChild(sub);
    }

    var canvas = state.doc.createElement('canvas');
    var width = 560;
    var height = 240;
    canvas.width = width;
    canvas.height = height;
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'Year-over-year incident comparison');

    var ctx = canvas.getContext && canvas.getContext('2d');
    if (!ctx) {
      chartWrap.appendChild(canvas);
      return chartWrap;
    }

    ctx.imageSmoothingEnabled = false;
    ctx.fillStyle = '#050510';
    ctx.fillRect(0, 0, width, height);

    var plotPadding = 36;
    var plotWidth = width - (plotPadding * 2);
    var plotHeight = height - (plotPadding * 2);
    var baseX = plotPadding;
    var baseY = height - plotPadding;

    ctx.strokeStyle = 'rgba(42, 255, 213, 0.15)';
    for (var gx = 0; gx <= plotWidth; gx += 20) {
      ctx.beginPath();
      ctx.moveTo(baseX + gx, baseY - plotHeight);
      ctx.lineTo(baseX + gx, baseY);
      ctx.stroke();
    }
    for (var gy = 0; gy <= plotHeight; gy += 20) {
      ctx.beginPath();
      ctx.moveTo(baseX, baseY - gy);
      ctx.lineTo(baseX + plotWidth, baseY - gy);
      ctx.stroke();
    }

    var seriesCurrent = buildSeries(windowDays, comparison.current);
    var seriesPrevious = buildSeries(windowDays, comparison.previous);
    var maxCurrent = seriesCurrent.reduce(function (acc, val) { return Math.max(acc, val); }, 0);
    var maxPrevious = seriesPrevious.reduce(function (acc, val) { return Math.max(acc, val); }, 0);
    var maxValue = Math.max(1, maxCurrent, maxPrevious);
    var stepX = plotWidth / Math.max(windowDays - 1, 1);

    var drawSeries = function (series, color, glowColor) {
      ctx.strokeStyle = glowColor;
      ctx.lineWidth = 4;
      ctx.beginPath();
      series.forEach(function (value, idx) {
        var x = baseX + (idx * stepX);
        var y = baseY - ((value / maxValue) * plotHeight);
        if (idx === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();

      ctx.strokeStyle = color;
      ctx.lineWidth = 2;
      ctx.beginPath();
      series.forEach(function (value, idx) {
        var x = baseX + (idx * stepX);
        var y = baseY - ((value / maxValue) * plotHeight);
        if (idx === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
        ctx.fillStyle = color;
        ctx.fillRect(x - 3, y - 3, 6, 6);
      });
      ctx.stroke();
    };

    drawSeries(seriesPrevious, '#00c2ff', 'rgba(0, 194, 255, 0.35)');
    drawSeries(seriesCurrent, '#ff2fb3', 'rgba(255, 47, 179, 0.35)');

    var legend = state.doc.createElement('div');
    legend.className = 'lo-retro-legend';
    legend.innerHTML = [
      '<span class="lo-retro-legend__swatch" style="background:#ff2fb3;"></span>Current window',
      '<span class="lo-retro-legend__swatch" style="background:#00c2ff;"></span>Previous year'
    ].join(' ');

    var delta = (comparison.current.total || 0) - (comparison.previous.total || 0);
    var deltaText = state.doc.createElement('div');
    deltaText.className = 'lo-history__subtitle';
    var deltaLabel = delta === 0 ? 'matches last year' : (delta > 0 ? '+' + delta + ' vs last year' : delta + ' vs last year');
    deltaText.textContent = 'Total incidents: ' + (comparison.current.total || 0) + ' (' + deltaLabel + ').';

    chartWrap.appendChild(canvas);
    chartWrap.appendChild(legend);
    chartWrap.appendChild(deltaText);

    return chartWrap;
  }

  function buildSeries(days, windowData) {
    var series = [];
    var counts = windowData && windowData.daily_counts && typeof windowData.daily_counts === 'object'
      ? windowData.daily_counts
      : {};
    var start = windowData && windowData.start ? Date.parse(windowData.start + 'T00:00:00Z') : NaN;

    for (var i = 0; i < days; i++) {
      var dateKey;
      if (!Number.isNaN(start)) {
        var dateObj = new Date(start + (i * 24 * 60 * 60 * 1000));
        dateKey = dateObj.toISOString().slice(0, 10);
      }
      series.push(counts[dateKey] || 0);
    }

    return series;
  }

  function setHistoryLoading() {
    if (!state.historyList || !state.doc) {
      return;
    }
    state.historyList.innerHTML = '';
    var placeholder = state.doc.createElement('li');
    placeholder.className = 'lo-history__item lo-history__item--placeholder';
    placeholder.textContent = 'Loading incidents…';
    state.historyList.appendChild(placeholder);
    if (state.historyEmpty) {
      state.historyEmpty.setAttribute('hidden', 'hidden');
    }
    if (state.historyError) {
      state.historyError.setAttribute('hidden', 'hidden');
    }
    if (state.historyCharts) {
      state.historyCharts.innerHTML = '';
      state.historyCharts.setAttribute('hidden', 'hidden');
    }
  }

  function renderHistoryList(providers, meta) {
    if (!state.historyList || !state.doc) {
      return;
    }
    state.historyList.innerHTML = '';
    state.historyIncidents = [];
    if (state.historyToggleButton && state.historyToggleButton.parentNode) {
      state.historyToggleButton.parentNode.removeChild(state.historyToggleButton);
    }
    state.historyToggleButton = null;
    if (state.historyError) {
      state.historyError.setAttribute('hidden', 'hidden');
    }

    renderHistoryCharts(providers, meta || {});

    var providerList = Array.isArray(providers) ? providers : [];
    var prefs = state.visibleProviders || {};
    var hasPrefs = Object.keys(prefs).length > 0;

    var incidentEntries = [];

    providerList.forEach(function (provider) {
      if (!provider || typeof provider !== 'object') {
        return;
      }
      var slug = String(provider.id || '').toLowerCase();
      if (slug && hasPrefs && prefs[slug] === false) {
        return;
      }
      var label = provider.label || provider.name || provider.provider || 'Provider';
      if (Array.isArray(provider.incidents)) {
        provider.incidents.forEach(function (entry) {
          if (!entry || typeof entry !== 'object') {
            return;
          }
          var status = entry && entry.status ? String(entry.status).toLowerCase() : '';
          if (status === 'operational' || status === 'ok' || status === 'none') {
            return;
          }
          incidentEntries.push({
            provider: entry.provider || label,
            provider_id: slug,
            summary: entry.summary || entry.title || '',
            status: status || 'unknown',
            severity: (entry.severity || '').toString(),
            started_at: entry.started_at || entry.startedAt || null,
            first_seen: entry.first_seen || entry.firstSeen || null,
            detected_at: entry.detected_at || entry.detectedAt || null,
            last_seen: entry.last_seen || entry.lastSeen || null,
            updated_at: entry.updated_at || entry.updatedAt || null,
            url: entry.url || ''
          });
        });
      }
    });

    incidentEntries = dedupeIncidents(incidentEntries);

    incidentEntries.sort(function (a, b) {
      var aStart = getIncidentStartTs(a);
      var bStart = getIncidentStartTs(b);
      if (aStart !== bStart) {
        return bStart - aStart;
      }
      var aUpdated = getIncidentUpdatedTs(a);
      var bUpdated = getIncidentUpdatedTs(b);
      if (aUpdated !== bUpdated) {
        return bUpdated - aUpdated;
      }
      return severityRank(b.severity || b.status) - severityRank(a.severity || a.status);
    });

    state.historyIncidents = incidentEntries.slice();

    if (incidentEntries.length) {
      if (state.historyEmpty) {
        state.historyEmpty.setAttribute('hidden', 'hidden');
      }

      var displayLimit = state.historyExpanded ? HISTORY_LIMIT : HISTORY_RENDER_LIMIT;
      var displayEntries = incidentEntries.slice(0, displayLimit);

      displayEntries.forEach(function (entry) {
        var li = state.doc.createElement('li');
        li.className = 'lo-history__item';

        var timeWrap = state.doc.createElement('div');
        timeWrap.className = 'lo-history__time';
        var started = entry.started_at || entry.first_seen || entry.detected_at || entry.updated_at || entry.last_seen;
        var timeEl = state.doc.createElement('time');
        timeEl.setAttribute('datetime', started || '');
        timeEl.textContent = formatTimestamp(started) || formatHistoryDate(started);
        timeWrap.appendChild(timeEl);

        if (entry.last_seen && entry.last_seen !== entry.first_seen) {
          var range = state.doc.createElement('span');
          range.className = 'lo-history__time-range';
          range.textContent = 'Updated ' + formatTimestamp(entry.last_seen);
          timeWrap.appendChild(range);
        }

        var details = state.doc.createElement('div');
        details.className = 'lo-history__details';
        var providerName = entry.provider || 'Provider';
        var providerLabelEl = state.doc.createElement('strong');
        providerLabelEl.textContent = providerName;
        details.appendChild(providerLabelEl);
        var summaryText = entry.summary || 'Incident reported';
        var condensedSummary = condenseIncidentTitle(entry.provider_id, summaryText);
        details.appendChild(state.doc.createTextNode(' — '));
        var summaryEl = state.doc.createElement('span');
        summaryEl.className = 'lo-history__summary';
        summaryEl.textContent = condensedSummary.text || summaryText;
        if (condensedSummary.title && condensedSummary.title !== condensedSummary.text) {
          summaryEl.setAttribute('title', condensedSummary.title);
        }
        details.appendChild(summaryEl);
        if (entry.url) {
          var link = state.doc.createElement('a');
          link.href = entry.url;
          link.target = '_blank';
          link.rel = 'noopener';
          link.className = 'lo-link';
          link.textContent = 'Details';
          details.appendChild(state.doc.createTextNode(' '));
          details.appendChild(link);
        }

        var badge = state.doc.createElement('span');
        var mapped = mapHistoryStatus(entry.status);
        badge.className = 'lo-pill ' + mapped.className + ' lo-history__badge';
        badge.textContent = mapped.label;

        li.appendChild(timeWrap);
        li.appendChild(details);
        li.appendChild(badge);

        state.historyList.appendChild(li);
      });

      if (incidentEntries.length > HISTORY_RENDER_LIMIT && state.historyList.parentNode) {
        var toggle = state.doc.createElement('button');
        toggle.type = 'button';
        toggle.className = 'lo-button lo-history__toggle';
        toggle.textContent = state.historyExpanded ? 'Show fewer' : 'Show more';
        toggle.addEventListener('click', function () {
          state.historyExpanded = !state.historyExpanded;
          renderHistoryList(state.historyProviders, state.historyMeta);
        });
        state.historyToggleButton = toggle;
        state.historyList.parentNode.appendChild(toggle);
      }

      return;
    }

    if (state.historyEmpty) {
      state.historyEmpty.textContent = state.historyImportantOnly
        ? 'No significant incidents in the selected window.'
        : 'No incidents in the selected window.';
      state.historyEmpty.removeAttribute('hidden');
    }
  }

  function renderHistoryError(message) {
    if (state.historyList) {
      state.historyList.innerHTML = '';
    }
    if (state.historyEmpty) {
      state.historyEmpty.setAttribute('hidden', 'hidden');
    }
    if (state.historyError) {
      state.historyError.textContent = message || 'Unable to load incidents right now.';
      state.historyError.removeAttribute('hidden');
    }
  }

  function fetchHistoryData() {
    if (!state.historyEndpoint || !state.fetchImpl) {
      return Promise.resolve();
    }
    setHistoryLoading();

    var providerIds = [];
    var cards = state.container ? state.container.querySelectorAll('[data-provider-id], .provider-card') : [];
    if (cards && cards.length) {
      cards.forEach(function (card) {
        var slug = card.getAttribute ? card.getAttribute('data-provider-id') || card.getAttribute('data-id') : null;
        if (slug) {
          providerIds.push(String(slug));
        }
      });
    }
    var prefs = state.visibleProviders || {};
    var hasPrefs = Object.keys(prefs).length > 0;
    if (hasPrefs && providerIds.length) {
      providerIds = providerIds.filter(function (id) {
        return prefs[String(id).toLowerCase()] !== false;
      });
    }

    var windowDays = Number.isFinite(state.historyWindowDays) && state.historyWindowDays > 0
      ? state.historyWindowDays
      : HISTORY_DEFAULT_DAYS;
    var url = appendQuery(state.historyEndpoint, 'days', String(windowDays));
    url = appendQuery(url, 'limit', String(HISTORY_LIMIT));
    url = appendQuery(url, 'severity', state.historyImportantOnly ? 'important' : 'all');
    if (providerIds.length) {
      url = appendQuery(url, 'provider', providerIds.join(','));
    }

    return state.fetchImpl(url, { headers: { Accept: 'application/json' } })
      .then(function (res) {
        if (!res || !res.ok) {
          throw new Error('history unavailable');
        }
        return res.json();
      })
      .then(function (body) {
        if (!body || !Array.isArray(body.providers)) {
          throw new Error('invalid history response');
        }

        state.historyProviders = body.providers;
        state.historyMeta = body.meta || {};
        state.historyExpanded = false;
        renderHistoryList(body.providers, body.meta || {});
      })
      .catch(function (err) {
        if (state.debug && state.root && state.root.console && typeof state.root.console.error === 'function') {
          state.root.console.error(err);
        }
        state.historyIncidents = [];
        renderHistoryError('Unable to load incident history right now.');
      });
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

  function setReportStatus(message, tone) {
    if (!state.reportStatus) {
      return;
    }
    state.reportStatus.textContent = message || '';
    state.reportStatus.classList.remove('lo-report__status--success', 'lo-report__status--error');
    if ('success' === tone) {
      state.reportStatus.classList.add('lo-report__status--success');
    } else if ('error' === tone) {
      state.reportStatus.classList.add('lo-report__status--error');
    }
  }

  function setReportProviderOtherVisibility(providerId) {
    if (!state.reportProviderNameWrap || !state.reportProviderName) {
      return;
    }
    var isOther = providerId === 'other';
    if (isOther) {
      state.reportProviderNameWrap.removeAttribute('hidden');
      state.reportProviderName.setAttribute('required', 'required');
    } else {
      state.reportProviderNameWrap.setAttribute('hidden', 'hidden');
      state.reportProviderName.removeAttribute('required');
      state.reportProviderName.value = '';
    }
  }

  function normalizeReportCaptcha(value) {
    var text = String(value || '').toLowerCase();
    text = text.replace(/[^a-z0-9\s]+/gi, '');
    text = text.replace(/\s+/g, ' ').trim();
    return text;
  }

  function requestReportPhrase() {
    if (!state.fetchImpl || !state.reportPhraseEndpoint || !state.reportCaptchaPhrase || !state.reportCaptchaToken) {
      return;
    }
    state.reportCaptchaPhrase.textContent = 'Loading phrase…';
    state.reportCaptchaTokenValue = '';

    var url = state.reportPhraseEndpoint;
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    url += separator + 'action=lo_get_report_phrase';

    state.fetchImpl(url, { method: 'GET', credentials: 'same-origin' })
      .then(function (res) {
        if (!res) {
          throw new Error('No response');
        }
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (data) {
        if (!data || !data.phrase || !data.token) {
          throw new Error('Invalid phrase payload');
        }
        state.reportCaptchaPhrase.textContent = data.phrase;
        state.reportCaptchaTokenValue = data.token;
        state.reportCaptchaToken.value = data.token;
      })
      .catch(function () {
        state.reportCaptchaPhrase.textContent = 'Unable to load phrase.';
        state.reportCaptchaToken.value = '';
      });
  }

  function populateReportProviders(providers) {
    var select = state.reportProvider;
    if (!select || !state.doc) {
      return;
    }

    var current = select.value;
    select.innerHTML = '';

    if (!Array.isArray(providers) || !providers.length) {
      var unavailable = state.doc.createElement('option');
      unavailable.value = '';
      unavailable.textContent = 'Provider list unavailable';
      unavailable.disabled = true;
      unavailable.selected = true;
      select.appendChild(unavailable);
      state.reportProvidersInitialized = true;
      return;
    }

    var placeholder = state.doc.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select a provider…';
    select.appendChild(placeholder);

    var otherOption = state.doc.createElement('option');
    otherOption.value = 'other';
    otherOption.textContent = 'Other (not listed)';
    select.appendChild(otherOption);

    providers.forEach(function (provider) {
      if (!provider) {
        return;
      }
      var slug = String(provider.provider || provider.id || '').trim().toLowerCase();
      var label = provider.name || provider.label || (slug ? slug.charAt(0).toUpperCase() + slug.slice(1) : 'Provider');
      if (!slug) {
        return;
      }
      var option = state.doc.createElement('option');
      option.value = slug;
      option.textContent = label;
      select.appendChild(option);
    });

    if (current && select.querySelector('option[value="' + escapeSelector(current) + '"]')) {
      select.value = current;
    }

    setReportProviderOtherVisibility(select.value);
    state.reportProvidersInitialized = true;
  }

  function handleReportSubmit(event) {
    if (event && typeof event.preventDefault === 'function') {
      event.preventDefault();
    }

    if (!state.fetchImpl || !state.reportProvider || !state.reportSummary || !state.reportSubmit) {
      return;
    }

    var providerId = String(state.reportProvider.value || '').trim();
    var summary = String(state.reportSummary.value || '').trim();
    var providerName = state.reportProviderName ? String(state.reportProviderName.value || '').trim() : '';
    var contact = state.reportContact ? String(state.reportContact.value || '').trim() : '';
    var captchaAnswer = state.reportCaptchaInput ? String(state.reportCaptchaInput.value || '').trim() : '';
    var captchaToken = state.reportCaptchaToken ? String(state.reportCaptchaToken.value || '').trim() : '';

    if (!providerId) {
      setReportStatus('Select a provider to report.', 'error');
      return;
    }

    if (providerId === 'other') {
      if (providerName.length < 2) {
        setReportStatus('Please enter the provider name (2+ characters).', 'error');
        return;
      }
      if (providerName.length > 80) {
        setReportStatus('Provider name must be 80 characters or fewer.', 'error');
        return;
      }
    }

    if (summary.length < 10) {
      setReportStatus('Please add a short description (10+ characters).', 'error');
      return;
    }

    if (!normalizeReportCaptcha(captchaAnswer) || !captchaToken) {
      setReportStatus('Please complete the phrase check.', 'error');
      return;
    }

    var originalLabel = state.reportSubmit.textContent;
    state.reportSubmit.disabled = true;
    state.reportSubmit.textContent = 'Sending…';
    setReportStatus('Sending report…');

    var payload = {
      provider_id: providerId,
      provider_name: providerId === 'other' ? providerName : '',
      summary: summary,
      contact: contact,
      captcha_answer: captchaAnswer,
      captcha_token: captchaToken
    };

    state.fetchImpl('/wp-json/lousy/v1/report', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        if (!res) {
          throw new Error('No response');
        }
        var ok = !!res.ok;
        return res.json().catch(function () { return {}; }).then(function (data) { return { ok: ok, data: data }; });
      })
      .then(function (result) {
        var ok = result && result.ok && result.data && result.data.ok !== false;
        var message = null;
        if (result && result.data) {
          message = result.data.message || result.data.error || null;
        }
        if (ok) {
          if (state.reportSummary) {
            state.reportSummary.value = '';
          }
          if (state.reportContact) {
            state.reportContact.value = '';
          }
          if (state.reportProviderName) {
            state.reportProviderName.value = '';
          }
          if (state.reportCaptchaInput) {
            state.reportCaptchaInput.value = '';
          }
          requestReportPhrase();
          setReportStatus(message || 'Thanks for the report. We will review it shortly.', 'success');
        } else {
          setReportStatus(message || 'Could not submit report right now, please try again later.', 'error');
        }
      })
      .catch(function () {
        setReportStatus('Could not submit report right now, please try again later.', 'error');
      })
      .finally(function () {
        state.reportSubmit.disabled = false;
        state.reportSubmit.textContent = originalLabel;
      });
  }

  function initReportForm() {
    if (!state.doc) {
      return;
    }
    state.reportForm = state.doc.querySelector('[data-lo-report-form]');
    state.reportProvider = state.reportForm ? state.reportForm.querySelector('[data-lo-report-provider]') : null;
    state.reportProviderNameWrap = state.reportForm ? state.reportForm.querySelector('[data-lo-report-other]') : null;
    state.reportProviderName = state.reportForm ? state.reportForm.querySelector('[data-lo-report-provider-name]') : null;
    state.reportSummary = state.reportForm ? state.reportForm.querySelector('[data-lo-report-summary]') : null;
    state.reportContact = state.reportForm ? state.reportForm.querySelector('[data-lo-report-contact]') : null;
    state.reportStatus = state.reportForm ? state.reportForm.querySelector('[data-lo-report-status]') : null;
    state.reportSubmit = state.reportForm ? state.reportForm.querySelector('[data-lo-report-submit]') : null;
    state.reportCaptchaPhrase = state.reportForm ? state.reportForm.querySelector('[data-lo-report-captcha-phrase]') : null;
    state.reportCaptchaInput = state.reportForm ? state.reportForm.querySelector('[data-lo-report-captcha-input]') : null;
    state.reportCaptchaToken = state.reportForm ? state.reportForm.querySelector('[data-lo-report-captcha-token]') : null;
    state.reportCaptchaRefresh = state.reportForm ? state.reportForm.querySelector('[data-lo-report-captcha-refresh]') : null;
    state.reportPhraseEndpoint = state.reportForm ? String(state.reportForm.dataset.loReportPhraseEndpoint || '') : '';

    if (state.reportForm && !state.reportForm.dataset.loReportEnhanced) {
      state.reportForm.dataset.loReportEnhanced = '1';
      state.reportForm.addEventListener('submit', handleReportSubmit);
    }

    if (state.reportProvider) {
      state.reportProvider.addEventListener('change', function (event) {
        var value = event && event.currentTarget ? String(event.currentTarget.value || '').trim() : '';
        setReportProviderOtherVisibility(value);
      });
    }

    if (state.reportCaptchaRefresh) {
      state.reportCaptchaRefresh.addEventListener('click', function () {
        requestReportPhrase();
      });
    }

    requestReportPhrase();
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
          populateReportProviders(body.providers);
        } else {
          resetAutoRefresh();
          if (!state.reportProvidersInitialized) {
            populateReportProviders(null);
          }
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
        if (!state.reportProvidersInitialized) {
          populateReportProviders(null);
        }
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
      var statusOk = !!res.ok;
      return res.json().catch(function () { return null; }).then(function (data) {
        if (!statusOk && !(data && data.skipped)) {
          throw new Error('HTTP ' + res.status);
        }
        return data;
      });
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
    state.debug = !!config.debug;
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
    state.themeToggle = state.container.querySelector('[data-lo-theme-toggle]');
    state.exportCSVButton = state.container.querySelector('[data-lo-export-csv]');
    state.exportPDFButton = state.container.querySelector('[data-lo-export-pdf]');
    state.loadingEl = state.container.querySelector('[data-lo-loading]');
    state.historyList = state.container.querySelector('[data-lo-history-list]');
    state.historyEmpty = state.container.querySelector('[data-lo-history-empty]');
    state.historyError = state.container.querySelector('[data-lo-history-error]');
    state.historyCharts = state.container.querySelector('[data-lo-history-charts]');
    initReportForm();
    var historyToggle = state.container.querySelector('[data-lo-history-important]');
    if (historyToggle) {
      state.historyImportantOnly = historyToggle.checked !== false;
      historyToggle.addEventListener('change', function (event) {
        state.historyImportantOnly = event.currentTarget.checked !== false;
        fetchHistoryData();
      });
    }
    var historyWindow = state.container.querySelector('[data-lo-history-window]');
    if (historyWindow) {
      state.historyWindowDays = parseInt(historyWindow.value, 10) || HISTORY_DEFAULT_DAYS;
      historyWindow.addEventListener('change', function (event) {
        var next = parseInt(event.currentTarget.value, 10);
        state.historyWindowDays = Number.isFinite(next) && next > 0 ? next : HISTORY_DEFAULT_DAYS;
        fetchHistoryData();
      });
    }
    state.historyEndpoint = config.historyEndpoint || '';
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
    if (initialProviders.length) {
      populateReportProviders(initialProviders);
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

    initThemePreference();
    initProviderVisibility();

    if (state.refreshButton) {
      state.refreshButton.addEventListener('click', manualRefresh);
    }

    if (state.themeToggle) {
      state.themeToggle.addEventListener('click', toggleTheme);
    }

    if (state.exportCSVButton) {
      state.exportCSVButton.addEventListener('click', downloadCSV);
    }

    if (state.exportPDFButton) {
      state.exportPDFButton.addEventListener('click', exportPDF);
    }

    enhanceSubscribeForms();

    if (state.doc && typeof state.doc.addEventListener === 'function') {
      state.doc.addEventListener('visibilitychange', handleVisibilityChange);
    }

    queuePostPaintWork();
  }

  function queuePostPaintWork() {
    if (state.postPaintStarted) {
      return;
    }
    state.postPaintStarted = true;
    var kickOff = function () {
      scheduleNext(state.baseDelay);

      if (state.root && typeof state.root.setTimeout === 'function') {
        state.root.setTimeout(fetchHistoryData, 350);
        state.root.setTimeout(function () {
          refreshSummary(false, true).catch(function () {
            // Ignore initial failure; countdown/backoff already handled inside refreshSummary.
          });
        }, 0);
      } else {
        fetchHistoryData().catch(function () {
          // Chart is optional; ignore failures.
        });
        refreshSummary(false, true).catch(function () {
          // Ignore initial failure; countdown/backoff already handled inside refreshSummary.
        });
      }
    };

    if (state.root && typeof state.root.requestAnimationFrame === 'function') {
      state.root.requestAnimationFrame(function () {
        // Wait a tick to let the initial HTML paint before network calls kick in (helps LCP).
        if (state.root && typeof state.root.setTimeout === 'function') {
          state.root.setTimeout(kickOff, 0);
        } else {
          kickOff();
        }
      });
    } else {
      kickOff();
    }
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
