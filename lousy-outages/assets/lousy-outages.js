(function (global, factory) {
  if (typeof module === 'object' && typeof module.exports === 'object') {
    module.exports = factory(global);
  } else {
    global.LousyOutagesApp = factory(global);
  }
})(typeof window !== 'undefined' ? window : globalThis, function (global) {
  'use strict';

  var defaultStrings = {
    updatedLabel: 'Updated:',
    providerHeader: 'Provider',
    statusHeader: 'Status',
    messageHeader: 'Message',
    buttonLabel: 'Insert coin to refresh',
    buttonShortLabel: 'Insert coin',
    buttonLoading: 'Loading…',
    teaserCaption: 'Check if your favourite services are up. Insert coin to refresh.',
    microcopy: 'Vancouver weather: cloudy with a chance of outages.',
    offlineMessage: 'Unable to reach the arcade right now. Try again soon.',
    tickerFallback: 'All quiet on the outage front.',
    unknownStatus: 'Unknown',
    noPublicStatus: 'No public status API'
  };

  var state = {
    container: null,
    tableBody: null,
    tickerEl: null,
    tickerMessages: [],
    tickerIndex: 0,
    tickerTimer: null,
    refreshButton: null,
    lastUpdatedSpan: null,
    pollTimer: null,
    inFlight: null,
    lastFetchedAt: null,
    strings: defaultStrings,
    config: {
      endpoint: '',
      pollInterval: 300000,
      fetchTimeout: 8000,
      locale: 'en-CA',
      providers: [],
      debug: false
    }
  };

  function readConfig(customConfig) {
    var baseConfig = global.LousyOutagesConfig || global.LousyOutages || {};
    if (customConfig && typeof customConfig === 'object') {
      baseConfig = Object.assign({}, baseConfig, customConfig);
    }
    state.config.endpoint = baseConfig.endpoint || state.config.endpoint;
    var interval = Number(baseConfig.pollInterval);
    var timeout = Number(baseConfig.fetchTimeout);
    state.config.pollInterval = interval && interval > 0 ? interval : state.config.pollInterval;
    state.config.fetchTimeout = timeout && timeout > 0 ? timeout : state.config.fetchTimeout;
    state.config.locale = baseConfig.locale || state.config.locale;
    state.config.providers = Array.isArray(baseConfig.providers) ? baseConfig.providers : state.config.providers;
    state.config.debug = Boolean(baseConfig.debug);
    if (state.config.debug) {
      state.config.pollInterval = Math.max(10, state.config.pollInterval);
      state.config.fetchTimeout = Math.max(500, state.config.fetchTimeout);
    } else {
      state.config.pollInterval = Math.max(60000, state.config.pollInterval);
      state.config.fetchTimeout = Math.max(2000, state.config.fetchTimeout);
    }
    var mergedStrings = Object.assign({}, defaultStrings);
    if (baseConfig.fallbackStrings && typeof baseConfig.fallbackStrings === 'object') {
      mergedStrings = Object.assign(mergedStrings, baseConfig.fallbackStrings);
    }
    if (baseConfig.strings && typeof baseConfig.strings === 'object') {
      mergedStrings = Object.assign(mergedStrings, baseConfig.strings);
    }
    state.strings = mergedStrings;
  }

  function normalizeStatus(statusCode, label) {
    var code = (statusCode || '').toLowerCase();
    var known = {
      operational: label || 'Operational',
      degraded: label || 'Degraded',
      outage: label || 'Outage',
      unknown: label || state.strings.unknownStatus || 'Unknown'
    };
    if (!known[code]) {
      code = 'unknown';
    }
    var resolved = known[code];
    var className = 'status--' + code;
    return {
      code: code,
      label: label || resolved,
      className: className
    };
  }

  function formatTimestamp(isoString) {
    if (!isoString) {
      return '';
    }
    var date = new Date(isoString);
    if (isNaN(date.getTime())) {
      return '';
    }
    var localFormatter = new Intl.DateTimeFormat(undefined, {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
      timeZoneName: 'short'
    });
    var gmtFormatter = new Intl.DateTimeFormat('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
      timeZone: 'UTC'
    });
    return localFormatter.format(date) + ' (' + gmtFormatter.format(date) + ' GMT)';
  }

  function tickTicker() {
    if (!state.tickerEl) {
      return;
    }
    if (!state.tickerMessages.length) {
      state.tickerEl.textContent = '';
      return;
    }
    state.tickerEl.textContent = state.tickerMessages[state.tickerIndex];
    state.tickerIndex = (state.tickerIndex + 1) % state.tickerMessages.length;
  }

  function updateTicker(messages) {
    if (!state.tickerEl) {
      return;
    }
    if (state.tickerTimer) {
      global.clearInterval(state.tickerTimer);
      state.tickerTimer = null;
    }
    state.tickerMessages = messages && messages.length ? messages : [];
    state.tickerIndex = 0;
    tickTicker();
    if (state.tickerMessages.length > 1 && !global.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      state.tickerTimer = global.setInterval(tickTicker, 4000);
    }
  }

  function setLoading(isLoading) {
    if (!state.container) {
      return;
    }
    state.container.classList.toggle('is-refreshing', Boolean(isLoading));
    state.container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    if (state.refreshButton) {
      state.refreshButton.classList.toggle('is-loading', Boolean(isLoading));
      var loadingLabel = state.refreshButton.getAttribute('data-loading-label') || state.strings.buttonLoading;
      if (isLoading) {
        state.refreshButton.setAttribute('aria-label', loadingLabel);
      } else {
        state.refreshButton.removeAttribute('aria-label');
      }
    }
  }

  function logError(error) {
    if (!error) {
      return;
    }
    if (state.config.debug) {
      console.error('Lousy Outages fetch failed', error);
    } else if (error && error.message) {
      console.error('Lousy Outages fetch failed:', error.message);
    }
  }

  function ensureAllProviders(providers) {
    var existing = Object.create(null);
    if (Array.isArray(providers)) {
      providers.forEach(function (provider) {
        existing[provider.provider] = provider;
      });
    }
    state.config.providers.forEach(function (meta) {
      if (!existing[meta.id]) {
        providers.push({
          provider: meta.id,
          name: meta.name,
          statusCode: 'unknown',
          status: state.strings.unknownStatus,
          message: state.strings.noPublicStatus,
          updatedAt: state.lastFetchedAt || new Date().toISOString(),
          url: ''
        });
      }
    });
    return providers;
  }

  function renderProviders(providers) {
    if (!state.tableBody || !Array.isArray(providers)) {
      return Promise.resolve([]);
    }
    var updatePromises = providers.map(function (provider) {
      return Promise.resolve().then(function () {
        var row = state.tableBody.querySelector('tr[data-id="' + provider.provider + '"]');
        if (!row) {
          return provider;
        }
        var statusCell = row.querySelector('.status');
        var messageCell = row.querySelector('.msg');
        var normalized = normalizeStatus(provider.statusCode, provider.status);
        statusCell.textContent = normalized.label;
        statusCell.dataset.status = normalized.code;
        statusCell.className = 'status ' + normalized.className;
        var message = provider.message || '';
        messageCell.textContent = message;
        if (normalized.code === 'unknown' && provider.error) {
          messageCell.title = provider.error;
        } else if (messageCell.title) {
          messageCell.removeAttribute('title');
        }
        return provider;
      });
    });
    return Promise.allSettled(updatePromises).then(function (results) {
      return results
        .filter(function (result) { return result.status === 'fulfilled'; })
        .map(function (result) { return result.value; });
    });
  }

  function updateTimestamp(iso) {
    if (!state.lastUpdatedSpan) {
      return;
    }
    var formatted = formatTimestamp(iso);
    state.lastUpdatedSpan.textContent = formatted || '--';
    state.lastFetchedAt = iso;
  }

  function handleSuccess(payload) {
    var providers = Array.isArray(payload.providers) ? payload.providers.slice() : [];
    providers = ensureAllProviders(providers);
    return renderProviders(providers).then(function (updatedProviders) {
      var tickerMessages = updatedProviders
        .map(function (provider) { return provider.message ? provider.message.trim() : ''; })
        .filter(function (msg, index, arr) { return msg && arr.indexOf(msg) === index; });
      updateTicker(tickerMessages.length ? tickerMessages : [state.strings.tickerFallback]);
      var fetchedAt = payload.meta && payload.meta.fetchedAt ? payload.meta.fetchedAt : new Date().toISOString();
      updateTimestamp(fetchedAt);
      return updatedProviders;
    });
  }

  function handleFailure(error) {
    logError(error);
    if (state.tickerEl) {
      state.tickerEl.textContent = state.strings.offlineMessage || '';
    }
    return [];
  }

  function fetchStatuses(options) {
    options = options || {};
    if (state.inFlight && !options.force) {
      return state.inFlight;
    }
    if (!state.config.endpoint) {
      return Promise.resolve([]);
    }
    setLoading(true);
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timeoutId = null;
    if (controller) {
      timeoutId = global.setTimeout(function () {
        controller.abort();
      }, state.config.fetchTimeout);
    }
    var fetchOptions = {
      method: 'GET',
      headers: { 'Cache-Control': 'no-cache' },
      cache: 'no-store'
    };
    if (controller) {
      fetchOptions.signal = controller.signal;
    }
    state.inFlight = global.fetch(state.config.endpoint, fetchOptions)
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Network response was not ok (' + response.status + ')');
        }
        return response.json();
      })
      .then(handleSuccess)
      .catch(handleFailure)
      .finally(function () {
        setLoading(false);
        if (timeoutId) {
          global.clearTimeout(timeoutId);
        }
        state.inFlight = null;
      });

    return state.inFlight;
  }

  function startAutoRefresh() {
    if (state.pollTimer) {
      global.clearInterval(state.pollTimer);
    }
    state.pollTimer = global.setInterval(function () {
      fetchStatuses();
    }, state.config.pollInterval);
    return state.pollTimer;
  }

  function stopAutoRefresh() {
    if (state.pollTimer) {
      global.clearInterval(state.pollTimer);
      state.pollTimer = null;
    }
  }

  function attachButton() {
    if (!state.refreshButton) {
      return;
    }
    state.refreshButton.addEventListener('click', function () {
      fetchStatuses({ force: true });
    });
  }

  function init(customConfig) {
    readConfig(customConfig);
    if (!global.document) {
      return state;
    }
    state.container = global.document.getElementById('lousy-outages');
    if (!state.container) {
      return state;
    }
    state.tableBody = state.container.querySelector('tbody');
    state.tickerEl = state.container.querySelector('.ticker');
    state.refreshButton = state.container.querySelector('.coin-btn');
    state.lastUpdatedSpan = state.container.querySelector('.last-updated span');

    if (state.lastUpdatedSpan) {
      var initial = state.lastUpdatedSpan.getAttribute('data-initial');
      if (initial) {
        updateTimestamp(initial);
      } else {
        state.lastUpdatedSpan.textContent = '--';
      }
    }

    attachButton();
    fetchStatuses({ force: true });
    startAutoRefresh();
    return state;
  }

  if (global.document) {
    if (global.document.readyState !== 'loading') {
      init();
    } else {
      global.document.addEventListener('DOMContentLoaded', function () {
        init();
      });
    }
  }

  return {
    init: init,
    refresh: fetchStatuses,
    startAutoRefresh: startAutoRefresh,
    stopAutoRefresh: stopAutoRefresh,
    normalizeStatus: normalizeStatus,
    formatTimestamp: formatTimestamp,
    _state: state
  };
});
