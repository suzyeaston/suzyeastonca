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
