(function () {
  'use strict';

  const DEFAULT_INTERVAL = 300000;
  let inFlight = false;

  function parseConfig(container) {
    const globalConfig = window.lousyOutagesTeaser || {};
    return {
      endpoint: container.dataset.loEndpoint || globalConfig.endpoint || '',
      interval: Math.max(60000, parseInt(container.dataset.loRefreshInterval || globalConfig.refreshInterval || DEFAULT_INTERVAL, 10) || DEFAULT_INTERVAL),
      dashboardUrl: container.dataset.loDashboardUrl || globalConfig.dashboardUrl || '/lousy-outages/'
    };
  }

  function formatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function unresolved(incident) {
    if (!incident || typeof incident !== 'object') return false;
    if (incident.resolved_at || incident.resolvedAt) return false;
    const status = String(incident.status || incident.lifecycle || '').toLowerCase();
    return !['resolved', 'completed', 'postmortem', 'operational', 'ok', 'none'].includes(status);
  }

  function currentItems(payload) {
    const providers = Array.isArray(payload && payload.providers) ? payload.providers : [];
    return providers.map((provider) => {
      const incidents = (Array.isArray(provider.incidents) ? provider.incidents : []).filter(unresolved);
      if (!incidents.length && !['outage', 'incident', 'signal'].includes(String(provider.tile_kind || provider.tileKind || '').toLowerCase())) return null;
      const first = incidents[0] || {};
      return {
        providerId: String(provider.id || provider.provider || ''),
        provider: String(provider.name || provider.provider || provider.id || 'Provider'),
        count: Math.max(incidents.length, incidents.length ? 1 : 0),
        title: String(first.display_title || first.displayTitle || first.title || first.summary || provider.summary || provider.state || 'Provider status update'),
        summary: String(first.summary || provider.summary || provider.state || provider.status_label || 'Latest official update is available.'),
        status: String(first.status || provider.state || provider.status_label || 'Incident'),
        href: String(first.url || provider.url || ''),
        checkedAt: provider.checked_at || provider.checkedAt || payload.fetched_at || '',
        updatedAt: first.last_official_update || first.updated_at || first.updatedAt || provider.updatedAt || provider.updated_at || ''
      };
    }).filter(Boolean).sort((a, b) => (Date.parse(b.updatedAt || b.checkedAt) || 0) - (Date.parse(a.updatedAt || a.checkedAt) || 0));
  }

  function setText(root, selector, value) {
    const el = root.querySelector(selector);
    if (el && value !== undefined && value !== null) el.textContent = String(value);
  }

  function updateLink(root, selector, href) {
    const el = root.querySelector(selector);
    if (el && href) el.setAttribute('href', href);
  }

  function render(container, payload, config) {
    const items = currentItems(payload);
    const lead = items[0];
    const outageCount = items.reduce((sum, item) => sum + (item.count || 1), 0);
    const providerCount = new Set(items.map((item) => item.providerId || item.provider)).size;
    const noticeCount = outageCount;
    container.classList.toggle('lo-home-teaser--active', outageCount > 0);
    container.classList.toggle('lo-home-teaser--clear', outageCount === 0);
    container.classList.remove('lo-home-teaser--delayed');

    setText(container, '[data-lo-stat="outages"] strong', outageCount);
    setText(container, '[data-lo-stat="outages"] span', 'Outage ' + (outageCount === 1 ? 'event' : 'events'));
    setText(container, '[data-lo-stat="providers"] strong', providerCount);
    setText(container, '[data-lo-stat="providers"] span', 'Affected ' + (providerCount === 1 ? 'provider' : 'providers'));
    setText(container, '[data-lo-stat="notices"] strong', noticeCount);
    setText(container, '[data-lo-stat="notices"] span', 'Official ' + (noticeCount === 1 ? 'notice' : 'notices'));

    const leadBox = container.querySelector('[data-lo-lead]');
    if (leadBox && lead) {
      setText(leadBox, '[data-lo-lead-title]', lead.title);
      setText(leadBox, '[data-lo-lead-summary]', lead.summary);
      setText(leadBox, '[data-lo-lead-provider]', lead.provider + (lead.status ? ' · ' + lead.status : ''));
      updateLink(leadBox, '[data-lo-lead-link]', lead.href || config.dashboardUrl);
      updateLink(leadBox, '[data-lo-provider-link]', config.dashboardUrl + '#provider-' + encodeURIComponent(lead.providerId));
    } else if (leadBox && !lead) {
      setText(leadBox, '[data-lo-lead-title]', 'No active incidents');
      setText(leadBox, '[data-lo-lead-summary]', 'Last checked: ' + (formatTime(payload && payload.fetched_at) || 'recently'));
      setText(leadBox, '[data-lo-lead-provider]', 'Provider checks are quiet.');
    }
  }

  function markDelayed(container) {
    container.classList.add('lo-home-teaser--delayed');
    const screen = container.querySelector('.lo-home-teaser__screen');
    if (screen && !screen.querySelector('.lo-home-delayed')) {
      const p = document.createElement('p');
      p.className = 'lo-home-delayed';
      p.setAttribute('role', 'status');
      p.textContent = 'Live verification delayed; showing the last saved homepage snapshot.';
      screen.append(p);
    }
  }

  function init(container) {
    const config = parseConfig(container);
    if (!config.endpoint || container.dataset.loTeaserReady === '1') return;
    container.dataset.loTeaserReady = '1';
    const refresh = () => {
      if (inFlight || document.hidden) return;
      inFlight = true;
      const url = new URL(config.endpoint, window.location.href);
      url.searchParams.set('_lo_cache_bust', Date.now().toString());
      fetch(url.toString(), { cache: 'no-store', credentials: 'same-origin' })
        .then((r) => { if (!r.ok) throw new Error('summary fetch failed'); return r.json(); })
        .then((payload) => render(container, payload, config))
        .catch(() => markDelayed(container))
        .finally(() => { inFlight = false; });
    };
    refresh();
    window.setInterval(refresh, config.interval);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  }

  window.LousyOutagesTeaser = { currentItems, render, markDelayed, init };
  document.addEventListener('DOMContentLoaded', () => { const c = document.getElementById('lousy-outages-teaser'); if (c) init(c); });
}());
