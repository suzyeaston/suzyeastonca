(function () {
  'use strict';

  var DEFAULT_INTERVAL = 300000;

  function parseConfig(container) {
    var globalConfig = window.lousyOutagesTeaser || {};
    return {
      endpoint: container.dataset.loEndpoint || globalConfig.endpoint || '',
      interval: Math.max(60000, parseInt(container.dataset.loRefreshInterval || globalConfig.refreshInterval || DEFAULT_INTERVAL, 10) || DEFAULT_INTERVAL),
      dashboardUrl: container.dataset.loDashboardUrl || globalConfig.dashboardUrl || '/lousy-outages/'
    };
  }

  function setText(root, selector, value) {
    var el = root.querySelector(selector);
    if (!el) return;
    if (value === undefined || value === null || value === '') {
      el.textContent = '';
      el.setAttribute('hidden', 'hidden');
      return;
    }
    el.removeAttribute('hidden');
    el.textContent = String(value);
  }

  function updateLink(root, selector, href) {
    var el = root.querySelector(selector);
    if (el && href) el.setAttribute('href', href);
  }

  function padCount(value) {
    var n = parseInt(value, 10) || 0;
    return String(n).padStart(2, '0');
  }

  function render(container, payload, config) {
    var teaser = payload && payload.teaser ? payload.teaser : null;
    if (!teaser) return;

    var counts = teaser.counts || {};
    var lead = teaser.lead || {};
    var tone = teaser.tone || 'ok';
    var urls = teaser.urls || {};

    container.className = container.className.replace(/lo-home-teaser--\w+/g, '').trim();
    container.classList.add('lo-home-teaser');
    container.classList.add('lo-home-teaser--' + tone);

    var downCount = parseInt(counts.down, 10) || 0;
    var degradedCount = parseInt(counts.degraded, 10) || 0;
    var isHot = downCount > 0 || degradedCount > 0 || tone === 'down' || tone === 'warn' || tone === 'advisory' || tone === 'degraded' || tone === 'bad';
    if (isHot) {
      container.classList.add('lo-home-teaser--hot');
    } else {
      container.classList.remove('lo-home-teaser--hot');
    }

    var details = container.querySelector('.lo-home-teaser__details');
    if (details) {
      if (isHot) {
        details.setAttribute('open', 'open');
      } else {
        details.removeAttribute('open');
      }
    }

    var summaryAction = container.querySelector('.lo-home-teaser__summary-action');
    if (summaryAction) {
      summaryAction.textContent = isHot ? 'live board open' : 'expand live board';
    }

    setText(container, '[data-lo-verdict-line]', teaser.verdict_line || '');
    setText(container, '[data-lo-verdict-sub]', teaser.verdict_sub || '');
    setText(container, '[data-lo-summary-verdict]', teaser.verdict_line || '');

    if (typeof document !== 'undefined' && typeof document.querySelector === 'function') {
      var signalVerdict = document.querySelector('[data-signal-lo-verdict]');
      if (signalVerdict && teaser.verdict_line) {
        signalVerdict.textContent = teaser.verdict_line;
      }
    }

    setText(container, '[data-lo-stat="down"] strong', padCount(counts.down));
    setText(container, '[data-lo-stat="degraded"] strong', padCount(counts.degraded));
    setText(container, '[data-lo-stat="advisory"] strong', padCount(counts.advisory));
    setText(container, '[data-lo-stat="up"] strong', padCount(counts.up));
    updateLink(container, '[data-lo-stat="down"]', urls.active || config.dashboardUrl + '#active');
    updateLink(container, '[data-lo-stat="degraded"]', urls.degraded || config.dashboardUrl + '#degraded');
    updateLink(container, '[data-lo-stat="advisory"]', urls.advisories || config.dashboardUrl + '#advisories');
    updateLink(container, '[data-lo-stat="up"]', urls.matrix || config.dashboardUrl + '#providers');

    var chip = container.querySelector('[data-lo-lead-label]');
    if (chip) {
      chip.textContent = lead.label || '';
      chip.className = 'lo-home-chip lo-home-chip--' + (lead.kind || tone);
    }

    setText(container, '[data-lo-lead-title]', lead.title || '');
    setText(container, '[data-lo-lead-summary]', lead.summary || '');
    updateLink(container, '[data-lo-lead-link]', lead.url || urls.full || config.dashboardUrl);

    var providerEl = container.querySelector('[data-lo-lead-provider]');
    var providerLink = container.querySelector('[data-lo-provider-link]');
    if (lead.provider) {
      if (providerEl) {
        providerEl.textContent = lead.provider;
        providerEl.removeAttribute('hidden');
      }
      if (providerLink) {
        providerLink.removeAttribute('hidden');
        providerLink.setAttribute('href', lead.section_url || urls.full || config.dashboardUrl);
      }
    } else {
      if (providerEl) providerEl.setAttribute('hidden', 'hidden');
      if (providerLink) providerLink.setAttribute('hidden', 'hidden');
    }

    var alsoList = container.querySelector('[data-lo-also]');
    if (alsoList) {
      alsoList.innerHTML = '';
      var also = Array.isArray(teaser.also) ? teaser.also : [];
      if (!also.length) {
        alsoList.setAttribute('hidden', 'hidden');
      } else {
        alsoList.removeAttribute('hidden');
        also.forEach(function (item) {
          var li = document.createElement('li');
          var link = document.createElement('a');
          link.className = 'lo-home-also__link lo-home-also__link--' + (item.tone || 'dim');
          link.href = item.url || config.dashboardUrl;
          var label = document.createElement('span');
          label.className = 'lo-home-also__label';
          label.textContent = item.label || '';
          var title = document.createElement('span');
          title.className = 'lo-home-also__title';
          title.textContent = item.title || '';
          link.append(label, title);
          li.append(link);
          alsoList.append(li);
        });
      }
    }

    var syncParts = [];
    if (teaser.fetched_label) syncParts.push('Last sync ' + teaser.fetched_label);
    if (counts.tracked) syncParts.push(padCount(counts.tracked) + ' tracked');
    setText(container, '[data-lo-sync]', syncParts.join(' · '));

    var lastAlert = teaser.last_alert || null;
    var alertEl = container.querySelector('[data-lo-last-alert]');
    if (alertEl) {
      if (lastAlert && (lastAlert.title || lastAlert.provider)) {
        alertEl.removeAttribute('hidden');
        alertEl.className = 'lo-home-alert lo-home-alert--' + (lastAlert.tone || 'signal');
        setText(alertEl, '[data-lo-last-alert-label]', lastAlert.label || 'LAST EMAIL');
        setText(alertEl, '[data-lo-last-alert-title]', lastAlert.title || lastAlert.provider || '');
        setText(alertEl, '[data-lo-last-alert-provider]', lastAlert.provider || '');
        setText(alertEl, '[data-lo-last-alert-time]', lastAlert.time_label || '');
        updateLink(alertEl, '[data-lo-last-alert-link]', lastAlert.url || config.dashboardUrl + '#active');
      } else {
        alertEl.setAttribute('hidden', 'hidden');
      }
    }

    if (teaser.delayed) {
      markDelayed(container);
    } else {
      var delayed = container.querySelector('.lo-home-delayed');
      if (delayed) delayed.remove();
      container.classList.remove('lo-home-teaser--delayed');
    }
  }

  function markDelayed(container) {
    container.classList.add('lo-home-teaser--delayed');
    var screen = container.querySelector('.lo-home-teaser__screen');
    if (screen && !screen.querySelector('.lo-home-delayed')) {
      var p = document.createElement('p');
      p.className = 'lo-home-delayed';
      p.setAttribute('role', 'status');
      p.textContent = 'Live verification delayed; showing the last saved snapshot.';
      screen.append(p);
    }
  }

  function init(container) {
    var config = parseConfig(container);
    if (!config.endpoint || container.dataset.loTeaserReady === '1') return;
    container.dataset.loTeaserReady = '1';

    var inFlight = false;
    var refresh = function () {
      if (inFlight || document.hidden) return;
      inFlight = true;
      var url = new URL(config.endpoint, window.location.href);
      url.searchParams.set('_lo_cache_bust', Date.now().toString());
      fetch(url.toString(), { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('summary fetch failed'); return r.json(); })
        .then(function (payload) { render(container, payload, config); })
        .catch(function () { markDelayed(container); })
        .finally(function () { inFlight = false; });
    };

    refresh();
    window.setInterval(refresh, config.interval);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  }

  window.LousyOutagesTeaser = { render, markDelayed, init };
  document.addEventListener('DOMContentLoaded', function () {
    var c = document.getElementById('lousy-outages-teaser');
    if (c) init(c);
  });
}());
