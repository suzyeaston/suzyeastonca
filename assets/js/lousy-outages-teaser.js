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

  function updateToneClasses(container, tone) {
    container.className = container.className
      .replace(/lo-home-teaser--\w+/g, '')
      .replace(/home-feature-preview--(ok|warn|down|advisory|degraded|bad|unknown|clear|dim)\b/g, '')
      .trim();
    if (container.classList.contains('lo-home-teaser')) {
      container.classList.add('lo-home-teaser--' + tone);
    }
    if (container.classList.contains('home-feature-preview--live')) {
      container.classList.add('home-feature-preview--' + tone);
    }
  }

  function flyoverCopy(teaser) {
    var counts = teaser.counts || {};
    var down = parseInt(counts.down, 10) || 0;
    var degraded = parseInt(counts.degraded, 10) || 0;
    var advisory = parseInt(counts.advisory, 10) || 0;
    var lead = teaser.lead || {};
    var kind = lead.kind || 'clear';
    var provider = String(lead.provider || '').trim();
    var tone = teaser.tone || 'ok';
    var highlight = down > 0 || degraded > 0 || advisory > 0
      || ['down', 'warn', 'advisory', 'degraded', 'bad', 'unknown'].indexOf(tone) !== -1
      || ['down', 'warn', 'advisory', 'unknown'].indexOf(kind) !== -1;
    var bannerParts = [];

    if (down > 0) bannerParts.push(down + ' provider' + (down === 1 ? '' : 's') + ' down');
    if (degraded > 0) bannerParts.push(degraded + ' degraded');
    if (advisory > 0) bannerParts.push(advisory + ' advisory' + (advisory === 1 ? '' : 'ies'));

    var banner = bannerParts.length
      ? 'LOUSY OUTAGES → ' + bannerParts.join(' · ') + ' · open status'
      : 'LOUSY OUTAGES → monitoring provider signals';

    var report = 'latest report: no major active incident summary available right now';
    if (highlight && kind !== 'clear') {
      if (kind === 'down') {
        report = provider
          ? 'latest report: ' + provider + ' status page indicates service disruption'
          : 'latest report: service disruption is being reported';
      } else if (kind === 'warn') {
        report = provider
          ? 'latest report: ' + provider + ' status page shows degraded service'
          : 'latest report: provider status page shows degraded service';
      } else if (kind === 'advisory') {
        report = provider
          ? 'latest report: ' + provider + ' open advisory still active'
          : 'latest report: open advisory still active';
      } else if (kind === 'unknown') {
        report = provider
          ? 'latest report: ' + provider + ' status could not be verified'
          : 'latest report: status verification incomplete';
      } else {
        report = 'latest report: provider signals under watch';
      }
    }

    return { banner: banner, report: report, tone: tone, highlight: highlight };
  }

  function updateFlyoverTone(flyover, tone, highlight) {
    flyover.className = flyover.className
      .replace(/home-lo-flyover--\w+/g, '')
      .replace(/\bhome-lo-flyover--hot\b/g, '')
      .trim();
    flyover.classList.add('home-lo-flyover');
    flyover.classList.add('home-lo-flyover--' + tone);
    if (highlight) flyover.classList.add('home-lo-flyover--hot');
  }

  function renderFlyover(flyover, teaser) {
    if (!flyover || !teaser) return;
    var copy = flyoverCopy(teaser);
    updateFlyoverTone(flyover, copy.tone, copy.highlight);
    flyover.querySelectorAll('[data-lo-flyover-banner]').forEach(function (el) {
      el.textContent = copy.banner;
    });
    var reportEl = flyover.querySelector('[data-lo-flyover-report]');
    if (reportEl) reportEl.textContent = copy.report;
    var cta = flyover.querySelector('.home-lo-flyover__cta');
    if (cta) cta.setAttribute('aria-label', copy.banner + ' — view Lousy Outages status');
    var craft = flyover.querySelector('.home-lo-flyover__craft');
    if (craft && teaser.dashboard_url) craft.setAttribute('href', teaser.dashboard_url);
  }

  function render(container, payload, config) {
    var teaser = payload && payload.teaser ? payload.teaser : null;
    if (!teaser) return;

    var counts = teaser.counts || {};
    var lead = teaser.lead || {};
    var tone = teaser.tone || 'ok';
    var urls = teaser.urls || {};

    if (container.classList.contains('lo-home-teaser')) {
      container.classList.add('lo-home-teaser');
    }
    updateToneClasses(container, tone);

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
    setText(container, '[data-lo-preview-verdict]', teaser.verdict_line || '');
    setText(container, '[data-lo-preview-verdict-sub]', teaser.verdict_sub || '');

    var previewVerdict = container.querySelector('[data-lo-preview-verdict]');
    if (previewVerdict) {
      previewVerdict.className = 'home-feature-preview__verdict home-feature-preview__verdict--' + tone;
    }

    var previewStats = container.querySelectorAll('[data-lo-preview-stat]');
    if (previewStats.length) {
      previewStats.forEach(function (cell) {
        var key = cell.getAttribute('data-lo-preview-stat');
        var valueEl = cell.querySelector('.home-feature-preview__value');
        if (valueEl && counts[key] !== undefined) {
          valueEl.textContent = padCount(counts[key]);
        }
      });
    }

    var syncBits = [];
    if (teaser.fetched_label) {
      syncBits.push('Last sync ' + teaser.fetched_label);
    }
    if (counts.tracked) {
      syncBits.push(padCount(counts.tracked) + ' tracked');
    }
    setText(container, '[data-lo-preview-sync]', syncBits.join(' · '));

    if (typeof document !== 'undefined' && typeof document.querySelector === 'function') {
      document.querySelectorAll('[data-lo-flyover]').forEach(function (flyover) {
        renderFlyover(flyover, teaser);
      });
    }

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
      container.classList.remove('home-feature-preview--delayed');
    }
  }

  function markDelayed(container) {
    container.classList.add('lo-home-teaser--delayed');
    if (container.classList.contains('home-feature-preview--live')) {
      container.classList.add('home-feature-preview--delayed');
    }
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
        .then(function (payload) {
          render(container, payload, config);
          if (container.hasAttribute('data-lo-flyover') && payload && payload.teaser) {
            renderFlyover(container, payload.teaser);
          }
        })
        .catch(function () { markDelayed(container); })
        .finally(function () { inFlight = false; });
    };

    refresh();
    window.setInterval(refresh, config.interval);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  }

  window.LousyOutagesTeaser = { render, renderFlyover, flyoverCopy, markDelayed, init };
  document.addEventListener('DOMContentLoaded', function () {
    var containers = document.querySelectorAll('[data-lo-endpoint]');
    containers.forEach(init);
    document.querySelectorAll('[data-lo-flyover][data-lo-endpoint]').forEach(init);
  });
}());
