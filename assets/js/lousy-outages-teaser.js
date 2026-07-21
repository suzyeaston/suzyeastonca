(function () {
  'use strict';

  const DEFAULT_INTERVAL = 300000;
  let timer = null;
  let inFlight = false;

  function parseConfig(container) {
    const globalConfig = window.lousyOutagesTeaser || {};
    return {
      endpoint: container.dataset.loEndpoint || globalConfig.endpoint || '',
      interval: Math.max(60000, parseInt(container.dataset.loRefreshInterval || globalConfig.refreshInterval || DEFAULT_INTERVAL, 10) || DEFAULT_INTERVAL),
      dashboardUrl: container.dataset.loDashboardUrl || globalConfig.dashboardUrl || '/lousy-outages/'
    };
  }

  function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric' });
  }

  function formatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function isUnresolvedIncident(incident) {
    const lifecycle = [incident.status, incident.eta, incident.resolved_at, incident.resolvedAt]
      .map((value) => String(value || '').trim().toLowerCase())
      .filter(Boolean);
    if (incident.resolved_at || incident.resolvedAt) return false;
    return !lifecycle.some((status) => ['resolved', 'completed', 'postmortem', 'operational', 'ok', 'none'].includes(status));
  }

  function keyFor(item) {
    return [item.providerId, item.summary.toLowerCase(), item.started || ''].join('|');
  }

  function currentItems(payload) {
    const providers = Array.isArray(payload && payload.providers) ? payload.providers : [];
    const groups = new Map();
    providers.forEach((provider) => {
      if (!provider || typeof provider !== 'object') return;
      const providerId = String(provider.id || provider.provider || provider.name || '');
      const providerName = String(provider.name || provider.provider || providerId || 'Unknown provider');
      const allRawIncidents = Array.isArray(provider.incidents) ? provider.incidents.filter((incident) => incident && typeof incident === 'object') : [];
      const rawIncidents = allRawIncidents.filter((incident) => isUnresolvedIncident(incident));
      const tileKind = String(provider.tile_kind || provider.tileKind || '').toLowerCase();
      if (!rawIncidents.length && tileKind !== 'outage' && tileKind !== 'signal') return;
      if (!rawIncidents.length && allRawIncidents.length > 0) return;
      const group = groups.get(providerId) || { type: 'outage', tone: 'outage', providerId, provider: providerName, count: 0, regions: new Set(), href: String(provider.url || ''), sort: 0, lastOfficialUpdate: '', checkedAt: provider.checked_at || provider.checkedAt || payload.fetched_at || '', summaries: [] };
      rawIncidents.forEach((incident) => {
        const started = incident.startedAt || incident.started_at || incident.created_at || '';
        const updated = incident.updatedAt || incident.updated_at || incident.last_official_update || incident.lastOfficialUpdate || started || provider.updatedAt || provider.updated_at || '';
        const ts = Date.parse(updated) || Date.parse(started) || 0;
        group.count += 1;
        group.sort = Math.max(group.sort, ts);
        group.lastOfficialUpdate = ts >= (Date.parse(group.lastOfficialUpdate) || 0) ? updated : group.lastOfficialUpdate;
        group.href = String(incident.url || group.href || provider.url || '');
        [incident.region_name, incident.region_code].filter(Boolean).forEach((r) => group.regions.add(String(r)));
        group.summaries.push(String(incident.display_title || incident.displayTitle || incident.title || incident.summary || 'Incident reported'));
        if (!group.details) group.details = String(incident.summary || '');
      });
      if (!rawIncidents.length && tileKind === 'outage') {
        group.count = Math.max(group.count, 1);
        group.summaries.push(String(provider.summary || provider.state || provider.status_label || 'Active outage'));
      }
      if (!rawIncidents.length && tileKind === 'signal') {
        group.type = 'signal'; group.tone = 'signal'; group.count = Math.max(group.count, 1);
        group.summaries.push(String(provider.summary || provider.state || provider.status_label || 'Verified degraded signal'));
      }
      groups.set(providerId, group);
    });
    return Array.from(groups.values()).sort((a, b) => b.sort - a.sort || a.provider.localeCompare(b.provider)).map((group) => {
      const regions = Array.from(group.regions).slice(0, 3);
      const summary = group.count > 1 ? group.count + ' ongoing regional disruptions' : (group.summaries[0] || 'Active incident');
      const label = group.type === 'signal' ? 'Degraded signal' : (group.count > 1 ? 'Ongoing regional disruptions' : 'Ongoing regional disruption');
      return Object.assign(group, { summary, details: group.details || '', region: regions.join(' · '), label, checkedAt: group.checkedAt });
    });
  }

  function setText(root, selector, text) { const el = root.querySelector(selector); if (el) el.textContent = text; }

  function render(container, payload, config) {
    const allItems = currentItems(payload);
    const items = allItems.slice(0, 3);
    const meta = payload && payload.meta ? payload.meta : {};
    const refreshed = payload && payload.fetched_at ? payload.fetched_at : '';
    container.classList.toggle('lo-home-teaser--active', items.length > 0);
    container.classList.toggle('lo-home-teaser--clear', items.length === 0);
    container.classList.remove('lo-home-teaser--delayed');
    const light = container.querySelector('.lo-home-status-light');
    if (light) {
      light.classList.toggle('lo-home-status-light--alert', items.length > 0);
      light.classList.toggle('lo-home-status-light--clear', items.length === 0);
      setText(light, '.screen-reader-text', items.length > 0 ? 'Active provider incidents or degraded signals' : 'No active provider incidents');
    }
    const screen = container.querySelector('.lo-home-teaser__screen');
    if (!screen) return;
    while (screen.firstChild) screen.removeChild(screen.firstChild);
    const hasVerificationDelay = (payload && Array.isArray(payload.providers) && payload.providers.some((p) => p && (p.is_stale || p.verification_status === 'failed' || p.verification_status === 'stale' || p.stateCode === 'unknown'))) || (meta && Number(meta.unknown || 0) > 0);
    if (items.length === 0) {
      const empty = document.createElement('div'); empty.className = 'lo-home-empty';
      const p1 = document.createElement('p'); p1.textContent = hasVerificationDelay ? 'verification delayed; latest provider checks are unavailable.' : 'all quiet. suspicious, but fine.';
      const p2 = document.createElement('p'); p2.textContent = 'last checked: ' + (formatTime(refreshed) || 'recently');
      empty.append(p1, p2); screen.append(empty); return;
    }
    const band = document.createElement('div'); band.className = 'lo-home-live-band'; band.setAttribute('role', 'status'); band.setAttribute('aria-live', 'polite');
    const dot = document.createElement('span'); dot.className = 'lo-home-live-band__dot'; dot.setAttribute('aria-hidden', 'true');
    const copy = document.createElement('div');
    const label = document.createElement('p'); label.className = 'lo-home-live-band__label'; label.textContent = items.some((i) => i.type === 'outage') ? 'LIVE OUTAGE SIGNAL' : 'VERIFIED DEGRADED SIGNAL';
    const count = document.createElement('p'); count.className = 'lo-home-live-band__count'; count.textContent = allItems.reduce((sum, item) => sum + (item.count || 1), 0) + ' current ' + (allItems.reduce((sum, item) => sum + (item.count || 1), 0) === 1 ? 'issue' : 'issues');
    const providers = document.createElement('p'); providers.className = 'lo-home-live-band__providers'; providers.textContent = Array.from(new Set(items.map((i) => i.provider))).slice(0, 3).join(' + ');
    copy.append(label, count, providers); band.append(dot, copy); screen.append(band);
    const list = document.createElement('ul'); list.className = 'lo-home-alert-list';
    items.forEach((item) => {
      const li = document.createElement('li'); li.className = 'lo-home-alert lo-home-alert--' + item.tone;
      const metaEl = document.createElement('div'); metaEl.className = 'lo-home-alert__meta';
      const strong = document.createElement('strong'); strong.className = 'lo-home-alert__provider'; strong.textContent = item.provider;
      const status = document.createElement('span'); status.className = 'lo-home-alert__status'; status.textContent = item.type === 'signal' ? 'Degraded signal' : item.label;
      metaEl.append(strong, status);
      if (item.region) { const region = document.createElement('span'); region.className = 'lo-home-alert__region'; region.textContent = item.provider + ' ' + item.region; metaEl.append(region); }
      const body = document.createElement('p'); body.className = 'lo-home-alert__body'; body.textContent = item.summary;
      const detail = item.details && item.details !== item.summary ? document.createElement('p') : null;
      if (detail) { detail.className = 'lo-home-alert__detail'; detail.textContent = item.details; }
      const times = document.createElement('div'); times.className = 'lo-home-alert__times';
      if (item.started) { const t = document.createElement('time'); t.className = 'lo-home-alert__time'; t.dateTime = item.started; t.textContent = 'Started ' + formatTime(item.started); times.append(t); }
      if (item.lastOfficialUpdate) { const t = document.createElement('time'); t.className = 'lo-home-alert__time'; t.dateTime = item.lastOfficialUpdate; t.textContent = 'Last official update ' + formatDate(item.lastOfficialUpdate); times.append(t); } else if (item.updated) { const t = document.createElement('time'); t.className = 'lo-home-alert__time'; t.dateTime = item.updated; t.textContent = 'Updated ' + formatTime(item.updated); times.append(t); }
      if (item.checkedAt) { const t = document.createElement('time'); t.className = 'lo-home-alert__time'; t.dateTime = item.checkedAt; t.textContent = 'Status checked ' + formatDate(item.checkedAt); times.append(t); }
      const a = document.createElement('a'); a.className = 'lo-home-alert__details'; a.href = item.href || config.dashboardUrl + (item.providerId ? '#provider-' + encodeURIComponent(item.providerId) : ''); a.textContent = item.label || 'View incidents';
      if (detail) { li.append(metaEl, body, detail, times, a); } else { li.append(metaEl, body, times, a); } list.append(li);
    });
    if (allItems.length > items.length) { const more = document.createElement('p'); more.className = 'lo-home-more'; more.textContent = '+ ' + (allItems.length - items.length) + ' more providers'; list.append(more); }
    screen.append(list);
  }

  function markDelayed(container) {
    container.classList.add('lo-home-teaser--delayed');
    const screen = container.querySelector('.lo-home-teaser__screen');
    if (screen && !screen.querySelector('.lo-home-delayed')) { const p = document.createElement('p'); p.className = 'lo-home-delayed'; p.setAttribute('role', 'status'); p.textContent = 'Live verification delayed; showing the last saved homepage snapshot.'; screen.append(p); }
  }

  function init(container) {
    const config = parseConfig(container);
    if (!config.endpoint || container.dataset.loTeaserReady === '1') return;
    container.dataset.loTeaserReady = '1';
    const refresh = () => {
      if (inFlight || document.hidden) return;
      inFlight = true;
      const url = new URL(config.endpoint, window.location.href); url.searchParams.set('_lo_cache_bust', Date.now().toString());
      fetch(url.toString(), { cache: 'no-store', credentials: 'same-origin' }).then((r) => { if (!r.ok) throw new Error('summary fetch failed'); return r.json(); }).then((payload) => render(container, payload, config)).catch(() => markDelayed(container)).finally(() => { inFlight = false; });
    };
    refresh();
    timer = window.setInterval(refresh, config.interval);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  }

  window.LousyOutagesTeaser = { currentItems, render, markDelayed, init };
  document.addEventListener('DOMContentLoaded', () => { const c = document.getElementById('lousy-outages-teaser'); if (c) init(c); });
}());
