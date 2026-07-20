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
    const items = [];
    providers.forEach((provider) => {
      if (!provider || typeof provider !== 'object') return;
      const providerId = String(provider.id || provider.provider || provider.name || '');
      const providerName = String(provider.name || provider.provider || providerId || 'Unknown provider');
      const tileKind = String(provider.tile_kind || provider.tileKind || '').toLowerCase();
      const stateCode = String(provider.stateCode || provider.status || 'unknown').toLowerCase();
      const stateLabel = String(provider.state || provider.status_label || stateCode || 'Status');
      const rawIncidents = Array.isArray(provider.incidents) ? provider.incidents.filter((incident) => incident && typeof incident === 'object') : [];
      const incidents = rawIncidents.filter((incident) => isUnresolvedIncident(incident));
      incidents.forEach((incident) => {
        const started = incident.startedAt || incident.started_at || incident.created_at || '';
        const updated = incident.updatedAt || incident.updated_at || started || provider.updatedAt || provider.updated_at || '';
        items.push({
          type: 'outage', tone: 'outage', providerId, provider: providerName, label: incident.scope === 'regional' ? 'Ongoing regional disruption' : 'Active incident',
          summary: String(incident.display_title || incident.displayTitle || incident.title || incident.summary || provider.summary || 'Incident reported'),
          details: String(incident.summary || ''),
          region: [incident.region_name, incident.region_code].filter(Boolean).join(' · '),
          lastOfficialUpdate: incident.last_official_update || incident.lastOfficialUpdate || updated,
          checkedAt: incident.checked_at || incident.checkedAt || provider.checked_at || provider.checkedAt || payload.fetched_at || '',
          href: String(incident.url || provider.url || ''), started, updated,
          sort: Date.parse(updated) || Date.parse(started) || 0
        });
      });
      if (rawIncidents.length === 0 && incidents.length === 0 && tileKind === 'outage') {
        const updated = provider.updatedAt || provider.updated_at || '';
        items.push({ type: 'outage', tone: 'outage', providerId, provider: providerName, label: stateLabel, summary: String(provider.summary || stateLabel || 'Active outage'), href: String(provider.url || ''), started: updated, updated, sort: Date.parse(updated) || 0 });
      } else if (rawIncidents.length === 0 && incidents.length === 0 && tileKind === 'signal') {
        const updated = provider.updatedAt || provider.updated_at || '';
        items.push({ type: 'signal', tone: 'signal', providerId, provider: providerName, label: stateLabel, summary: String(provider.summary || stateLabel || 'Verified degraded signal'), href: String(provider.url || ''), started: '', updated, sort: Date.parse(updated) || 0 });
      }
    });
    const seen = new Map();
    items.sort((a, b) => b.sort - a.sort || a.provider.localeCompare(b.provider));
    items.forEach((item) => { if (!seen.has(keyFor(item))) seen.set(keyFor(item), item); });
    return Array.from(seen.values()).slice(0, 5);
  }

  function setText(root, selector, text) { const el = root.querySelector(selector); if (el) el.textContent = text; }

  function render(container, payload, config) {
    const items = currentItems(payload);
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
    const count = document.createElement('p'); count.className = 'lo-home-live-band__count'; count.textContent = items.length + ' current ' + (items.length === 1 ? 'issue' : 'issues');
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
      const a = document.createElement('a'); a.className = 'lo-home-alert__details'; a.href = item.href || config.dashboardUrl + (item.providerId ? '#provider-' + encodeURIComponent(item.providerId) : ''); a.textContent = 'Details';
      if (detail) { li.append(metaEl, body, detail, times, a); } else { li.append(metaEl, body, times, a); } list.append(li);
    });
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
