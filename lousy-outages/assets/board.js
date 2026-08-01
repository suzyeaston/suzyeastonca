/*
 * Lousy Outages public board.
 *
 * The server renders the same markup this file produces, so hydration is a
 * straight re-render of the dynamic sections rather than a diff against
 * hand-written placeholders.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-lox]');
  if (!root) {
    return;
  }

  var CONFIG = window.LousyOutagesBoard || {};
  var META = CONFIG.providers || {};

  var endpoints = {
    summary: root.getAttribute('data-lox-summary') || '',
    history: root.getAttribute('data-lox-history') || '',
    refresh: root.getAttribute('data-lox-refresh') || '',
    nonce: root.getAttribute('data-lox-nonce') || ''
  };

  var pollInterval = Math.max(30000, parseInt(root.getAttribute('data-lox-poll'), 10) || 60000);
  var nextPollAt = Date.now() + pollInterval;
  var latestProviders = [];
  var historyState = { days: 30, majorOnly: false, page: 1, rows: [] };

  var STATE_LABELS = {
    outage: 'DOWN',
    degraded: 'DEGRADED',
    maintenance: 'MAINTENANCE',
    advisory: 'ADVISORY',
    operational: 'UP',
    unknown: 'NO ANSWER'
  };

  var STATE_TONES = {
    outage: 'down',
    degraded: 'warn',
    maintenance: 'warn',
    advisory: 'advisory',
    operational: 'ok',
    unknown: 'unknown'
  };

  var STATE_ALIASES = {
    major: 'outage', major_outage: 'outage', critical: 'outage', outage: 'outage',
    partial: 'degraded', partial_outage: 'degraded', degraded: 'degraded',
    degraded_performance: 'degraded', minor: 'degraded',
    maintenance: 'maintenance', scheduled: 'maintenance',
    operational: 'operational', ok: 'operational', none: 'operational',
    advisory: 'advisory'
  };

  var SEVERITY_ALIASES = {
    critical: 'outage', major: 'outage', major_outage: 'outage', outage: 'outage',
    partial: 'degraded', partial_outage: 'degraded', degraded: 'degraded',
    degraded_performance: 'degraded', minor: 'degraded', incident: 'degraded',
    investigating: 'degraded', identified: 'degraded', monitoring: 'degraded',
    maintenance: 'maintenance', scheduled: 'maintenance'
  };

  /* ---------- small helpers ---------- */

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) { node.className = className; }
    if (text !== undefined && text !== null) { node.textContent = String(text); }
    return node;
  }

  function firstString() {
    for (var i = 0; i < arguments.length; i++) {
      var value = arguments[i];
      if (typeof value === 'string' && value.trim() !== '') { return value.trim(); }
    }
    return '';
  }

  function toTimestamp(value) {
    if (!value) { return 0; }
    if (typeof value === 'number') { return value > 1e11 ? Math.floor(value / 1000) : value; }
    var parsed = Date.parse(value);
    return isNaN(parsed) ? 0 : Math.floor(parsed / 1000);
  }

  function firstTimestamp(source, keys) {
    for (var i = 0; i < keys.length; i++) {
      var ts = toTimestamp(source[keys[i]]);
      if (ts > 0) { return ts; }
    }
    return 0;
  }

  function relativeTime(seconds) {
    if (!seconds) { return 'never'; }
    var delta = Math.floor(Date.now() / 1000) - seconds;
    if (delta < 0) { return 'just now'; }
    if (delta < 60) { return delta + 's ago'; }
    if (delta < 3600) { return Math.floor(delta / 60) + 'm ago'; }
    if (delta < 86400) { return Math.floor(delta / 3600) + 'h ago'; }
    var days = Math.floor(delta / 86400);
    if (days < 30) { return days + 'd ago'; }
    var months = Math.floor(days / 30);
    return months < 12 ? months + 'mo ago' : Math.floor(months / 12) + 'y ago';
  }

  function absoluteTime(seconds) {
    if (!seconds) { return 'no timestamp'; }
    try {
      return new Date(seconds * 1000).toLocaleString();
    } catch (err) {
      return new Date(seconds * 1000).toISOString();
    }
  }

  function timeNode(seconds) {
    var node = el('time', 'lox-time', relativeTime(seconds));
    node.setAttribute('datetime', seconds ? new Date(seconds * 1000).toISOString() : '');
    node.setAttribute('title', absoluteTime(seconds));
    node.setAttribute('data-lox-rel', '');
    return node;
  }

  function tidy(text, limit) {
    var value = String(text || '')
      .replace(/<[^>]*>/g, ' ')
      .replace(/\*\*\s*(Summary|Description|Symptoms?|Workaround|Impact|Status|Updates?)\s*\*\*\s*:?/gi, ' ')
      .replace(/\s+/g, ' ')
      .replace(/^[\s*:-]+|[\s*:-]+$/g, '');
    if (!limit || value.length <= limit) { return value; }
    var slice = value.slice(0, limit - 1);
    var space = slice.lastIndexOf(' ');
    if (space > limit * 0.6) { slice = slice.slice(0, space); }
    return slice.replace(/[\s.,;:-]+$/, '') + '…';
  }

  function stateKey(provider) {
    var lane = String(provider.lane || '').toLowerCase();
    if (lane === 'unverified') { return 'unknown'; }
    if (lane === 'long_running') { return 'advisory'; }
    var raw = String(provider.stateCode || provider.status || 'unknown').toLowerCase();
    var key = STATE_ALIASES[raw] || 'unknown';
    if (key === 'operational' && provider.incidents && provider.incidents.length) { return 'degraded'; }
    return key;
  }

  function severityKey(raw) {
    return SEVERITY_ALIASES[String(raw || '').toLowerCase()] || 'degraded';
  }

  function incidentView(incident) {
    var title = tidy(firstString(incident.display_title, incident.displayTitle, incident.title, incident.name), 130);
    var summary = tidy(firstString(incident.summary, incident.body, incident.message), 320);
    if (!title) { title = summary || 'Incident reported'; }
    if (summary && summary.toLowerCase() === title.toLowerCase()) { summary = ''; }

    var scope = [];
    (incident.affected_services || []).forEach(function (service) {
      if (typeof service === 'string' && service.trim()) { scope.push(service.trim()); }
    });
    ['region_name', 'region_code', 'scope'].forEach(function (field) {
      var value = firstString(incident[field]);
      if (value) { scope.push(value); }
    });
    scope = scope.filter(function (value, index, list) { return list.indexOf(value) === index; }).slice(0, 4);

    return {
      id: String(incident.id || ''),
      providerId: String(incident.provider_id || ''),
      provider: firstString(incident.provider, incident.provider_name, incident.provider_id),
      title: title,
      summary: summary,
      severity: severityKey(incident.impact || incident.status),
      lifecycle: String(incident.status || incident.impact || 'incident').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }),
      scope: scope.join(' · '),
      updatedTs: firstTimestamp(incident, ['last_official_update', 'updated_at', 'updatedAt', 'started_at', 'startedAt']),
      checkedTs: firstTimestamp(incident, ['checked_at', 'checkedAt']),
      url: firstString(incident.url, incident.provider_url)
    };
  }

  function factsNode(pairs) {
    var dl = el('dl', 'lox-facts');
    pairs.forEach(function (pair) {
      if (!pair || !pair[1]) { return; }
      var row = el('div');
      row.appendChild(el('dt', null, pair[0]));
      var dd = el('dd');
      if (typeof pair[1] === 'string') { dd.textContent = pair[1]; } else { dd.appendChild(pair[1]); }
      row.appendChild(dd);
      dl.appendChild(row);
    });
    return dl;
  }

  function outLink(url, label) {
    var link = el('a', 'lox-out', label);
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    return link;
  }

  /* ---------- card renderers ---------- */

  function incidentCard(incident, variant) {
    var view = incidentView(incident);
    var tone = variant === 'advisory' ? 'advisory' : (view.severity === 'outage' ? 'down' : 'warn');
    var card = el('article', 'lox-card lox-card--' + tone);

    var head = el('header', 'lox-card__head');
    head.appendChild(el('h3', 'lox-card__provider', view.provider || view.providerId));
    head.appendChild(el('span', 'lox-chip lox-chip--' + tone, variant === 'advisory' ? 'NO RECENT UPDATE' : view.severity.toUpperCase()));
    card.appendChild(head);

    card.appendChild(el('p', 'lox-card__title', view.title));
    if (view.summary) { card.appendChild(el('p', 'lox-card__body', view.summary)); }

    card.appendChild(factsNode([
      ['Scope', view.scope],
      ['Lifecycle', view.lifecycle],
      ['Provider last spoke', timeNode(view.updatedTs)],
      ['We last checked', timeNode(view.checkedTs)]
    ]));

    if (view.url) { card.appendChild(outLink(view.url, 'Read the provider notice ↗')); }
    return card;
  }

  function signalCard(provider) {
    var card = el('article', 'lox-card lox-card--warn');
    var head = el('header', 'lox-card__head');
    head.appendChild(el('h3', 'lox-card__provider', firstString(provider.name, provider.provider, provider.id)));
    head.appendChild(el('span', 'lox-chip lox-chip--warn', 'DEGRADED'));
    card.appendChild(head);
    card.appendChild(el('p', 'lox-card__title', 'Status page says something is off. No incident filed.'));

    var summary = tidy(firstString(provider.summary, provider.message), 320);
    if (summary) { card.appendChild(el('p', 'lox-card__body', summary)); }

    card.appendChild(factsNode([
      ['Provider page updated', timeNode(firstTimestamp(provider, ['data_observed_at', 'updatedAt', 'updated_at']))],
      ['We last checked', timeNode(firstTimestamp(provider, ['checked_at', 'checkedAt', 'last_successful_at']))]
    ]));

    var url = firstString(provider.url);
    if (url) { card.appendChild(outLink(url, 'Open status page ↗')); }
    return card;
  }

  function unverifiedCard(provider) {
    var card = el('article', 'lox-card lox-card--unknown');
    var head = el('header', 'lox-card__head');
    head.appendChild(el('h3', 'lox-card__provider', firstString(provider.name, provider.provider, provider.id)));
    head.appendChild(el('span', 'lox-chip lox-chip--unknown', 'NO ANSWER'));
    card.appendChild(head);
    card.appendChild(el('p', 'lox-card__title', 'We could not read this provider’s status. Not a claim that it is down.'));

    var reason = tidy(firstString(provider.fetch_error, provider.stale_label), 200);
    if (reason) { card.appendChild(el('p', 'lox-card__body', reason)); }

    card.appendChild(factsNode([
      ['Last attempt', timeNode(firstTimestamp(provider, ['last_attempted_at', 'checked_at', 'checkedAt']))]
    ]));
    return card;
  }

  /* ---------- section rendering ---------- */

  function fillGrid(name, items, builder, emptyCopy) {
    var grid = root.querySelector('[data-lox-grid="' + name + '"]');
    if (!grid) { return; }
    grid.textContent = '';
    if (!items.length) {
      grid.appendChild(el('p', 'lox-empty', emptyCopy));
      return;
    }
    items.forEach(function (item) { grid.appendChild(builder(item)); });
  }

  function setSectionMeta(name, count, tone) {
    var section = root.querySelector('[data-lox-section="' + name + '"]');
    if (!section) { return; }
    section.className = 'lox-section lox-section--' + tone;
    var counter = section.querySelector('.lox-section__count');
    if (counter) { counter.textContent = String(count); }
  }

  function providerRows(state) {
    var rows = [];
    (state.providers || []).forEach(function (provider) {
      var id = String(provider.provider_id || provider.id || '');
      if (!id) { return; }
      var registry = META[id] || {};
      rows.push({
        id: id,
        name: firstString(provider.name, provider.provider, registry.name, id),
        state: stateKey(provider),
        summary: tidy(firstString(provider.summary, provider.message), 220),
        category: String(provider.category || registry.category || 'other').toLowerCase(),
        source: String(provider.sourceType || provider.source_type || registry.source || 'unknown').toLowerCase(),
        checkedTs: firstTimestamp(provider, ['checked_at', 'checkedAt', 'last_successful_at', 'fetched_at']),
        url: firstString(provider.url, registry.url)
      });
    });

    var order = { outage: 0, degraded: 1, advisory: 2, maintenance: 3, unknown: 4, operational: 5 };
    rows.sort(function (left, right) {
      var delta = (order[left.state] || 9) - (order[right.state] || 9);
      return delta !== 0 ? delta : left.name.toLowerCase().localeCompare(right.name.toLowerCase());
    });
    return rows;
  }

  function renderMatrix(rows) {
    var body = root.querySelector('[data-lox-matrix]');
    if (!body) { return; }
    body.textContent = '';

    rows.forEach(function (row) {
      var tone = STATE_TONES[row.state] || 'unknown';
      var tr = el('tr');
      tr.setAttribute('data-lox-row', '');
      tr.setAttribute('data-name', (row.name + ' ' + row.id).toLowerCase());
      tr.setAttribute('data-category', row.category);
      tr.setAttribute('data-state', row.state);

      var stateCell = el('td');
      stateCell.setAttribute('data-label', 'state');
      var led = el('span', 'lox-led lox-led--' + tone);
      led.setAttribute('aria-hidden', 'true');
      stateCell.appendChild(led);
      stateCell.appendChild(el('span', 'lox-state lox-state--' + tone, STATE_LABELS[row.state] || 'UNKNOWN'));
      tr.appendChild(stateCell);

      var nameCell = el('th', null, row.name);
      nameCell.setAttribute('scope', 'row');
      nameCell.setAttribute('data-label', 'provider');
      tr.appendChild(nameCell);

      var detail = el('td', 'lox-table__detail', row.summary || '—');
      detail.setAttribute('data-label', 'detail');
      tr.appendChild(detail);

      var category = el('td', null, row.category);
      category.setAttribute('data-label', 'category');
      tr.appendChild(category);

      var source = el('td', null, row.source.replace(/_/g, ' '));
      source.setAttribute('data-label', 'source');
      tr.appendChild(source);

      var checked = el('td');
      checked.setAttribute('data-label', 'checked');
      checked.appendChild(timeNode(row.checkedTs));
      tr.appendChild(checked);

      var page = el('td');
      page.setAttribute('data-label', 'page');
      if (row.url) { page.appendChild(outLink(row.url, 'open ↗')); } else { page.textContent = '—'; }
      tr.appendChild(page);

      body.appendChild(tr);
    });

    applyMatrixFilters();
  }

  function renderReadout(rows, state) {
    var counts = {
      down: 0, degraded: 0, advisory: 0, unknown: 0, operational: 0, tracked: rows.length
    };
    rows.forEach(function (row) {
      if (row.state === 'outage') { counts.down++; }
      else if (row.state === 'degraded' || row.state === 'maintenance') { counts.degraded++; }
      else if (row.state === 'advisory') { counts.advisory++; }
      else if (row.state === 'unknown') { counts.unknown++; }
      else { counts.operational++; }
    });

    Object.keys(counts).forEach(function (key) {
      var cell = root.querySelector('[data-lox-count="' + key + '"] .lox-readout__value');
      if (cell) { cell.textContent = String(counts[key]).padStart(2, '0'); }
    });

    var meta = state.meta || {};
    var verdict = { tone: 'ok', line: 'ALL CLEAR', sub: 'All ' + rows.length + ' providers up. Nothing on fire.' };
    var activeCount = (state.outages || []).length;
    var advisoryCount = (state.long_running || []).length;

    if (activeCount > 0) {
      var providers = (meta.current_official_provider_ids || []).length || counts.down || 1;
      verdict = {
        tone: 'down',
        line: providers === 1 ? '1 PROVIDER IS DOWN' : providers + ' PROVIDERS ARE DOWN',
        sub: activeCount + ' open ' + (activeCount === 1 ? 'incident' : 'incidents') + ' the provider is still updating.'
      };
    } else if ((state.signals || []).length > 0) {
      var signalCount = state.signals.length;
      verdict = {
        tone: 'warn',
        line: 'DEGRADED',
        sub: signalCount + ' ' + (signalCount === 1 ? 'provider' : 'providers') + ' flagged trouble without filing an incident.'
      };
    } else if ((state.unverified || []).length > 0) {
      var unverifiedCount = state.unverified.length;
      verdict = {
        tone: 'unknown',
        line: 'PARTIAL READ',
        sub: unverifiedCount + ' ' + (unverifiedCount === 1 ? 'provider' : 'providers') + ' did not answer. Everything else is up.'
      };
    } else if (advisoryCount > 0) {
      verdict.sub = (rows.length - counts.advisory) + ' of ' + rows.length + ' up. ' + advisoryCount + ' old ' + (advisoryCount === 1 ? 'advisory' : 'advisories') + ' still open.';
    }

    var verdictNode = root.querySelector('[data-lox-verdict]');
    if (verdictNode) {
      verdictNode.className = 'lox-verdict lox-verdict--' + verdict.tone;
      verdictNode.textContent = verdict.line;
    }
    var subNode = root.querySelector('[data-lox-verdict-sub]');
    if (subNode) { subNode.textContent = verdict.sub; }
  }

  function renderState(payload) {
    var state = payload.current_state || payload;
    if (!state || !state.providers) { return; }

    var rows = providerRows(state);
    latestProviders = rows;

    renderReadout(rows, state);
    renderMatrix(rows);

    var active = state.outages || [];
    var advisories = state.long_running || [];
    var signals = state.signals || [];
    var unverified = state.unverified || [];

    fillGrid('active', active, function (item) { return incidentCard(item, 'active'); }, 'Nothing on fire. Enjoy it.');
    fillGrid('degraded', signals, signalCard, 'No unexplained yellow lights.');
    fillGrid('advisories', advisories, function (item) { return incidentCard(item, 'advisory'); }, 'No stale notices hanging around.');
    fillGrid('unverified', unverified, unverifiedCard, 'Every provider answered.');

    setSectionMeta('active', active.length, active.length ? 'down' : 'dim');
    setSectionMeta('degraded', signals.length, signals.length ? 'warn' : 'dim');
    setSectionMeta('advisories', advisories.length, advisories.length ? 'advisory' : 'dim');
    setSectionMeta('unverified', unverified.length, 'unknown');

    var unverifiedSection = root.querySelector('[data-lox-section="unverified"]');
    if (unverifiedSection) { unverifiedSection.hidden = unverified.length === 0; }

    var syncNode = root.querySelector('.lox-shell__meta [data-lox-rel]');
    if (syncNode) {
      var ts = toTimestamp(payload.fetched_at || state.fetched_at);
      syncNode.setAttribute('datetime', ts ? new Date(ts * 1000).toISOString() : '');
      syncNode.setAttribute('title', absoluteTime(ts));
      syncNode.textContent = relativeTime(ts);
    }
  }

  /* ---------- matrix filters ---------- */

  function applyMatrixFilters() {
    var query = (root.querySelector('[data-lox-filter="query"]') || {}).value || '';
    var category = (root.querySelector('[data-lox-filter="category"]') || {}).value || '';
    var state = (root.querySelector('[data-lox-filter="state"]') || {}).value || '';
    query = query.trim().toLowerCase();

    var rows = root.querySelectorAll('[data-lox-row]');
    var shown = 0;
    Array.prototype.forEach.call(rows, function (row) {
      var matches = (!query || row.getAttribute('data-name').indexOf(query) !== -1)
        && (!category || row.getAttribute('data-category') === category)
        && (!state || row.getAttribute('data-state') === state);
      row.hidden = !matches;
      if (matches) { shown++; }
    });

    var counter = root.querySelector('[data-lox-matrix-count]');
    if (counter) { counter.textContent = shown + ' shown'; }
    var empty = root.querySelector('[data-lox-matrix-empty]');
    if (empty) { empty.hidden = shown !== 0; }
  }

  /* ---------- history ---------- */

  function historyRow(entry) {
    var severity = severityKey(entry.severity || entry.status);
    var row = el('li', 'lox-log__row lox-log__row--' + severity);

    var when = el('span', 'lox-log__when');
    when.appendChild(timeNode(toTimestamp(entry.first_seen || entry.last_seen)));
    row.appendChild(when);

    row.appendChild(el('span', 'lox-log__provider', entry.provider_label || entry.provider || ''));
    row.appendChild(el('span', 'lox-log__sev', severity.toUpperCase()));

    var title = el('span', 'lox-log__title');
    var text = tidy(entry.summary || entry.title || '', 150) || 'Incident';
    if (entry.url) {
      var link = el('a', null, text);
      link.href = entry.url;
      link.target = '_blank';
      link.rel = 'noopener';
      title.appendChild(link);
    } else {
      title.textContent = text;
    }
    row.appendChild(title);
    return row;
  }

  function renderHistory(append) {
    var list = root.querySelector('[data-lox-log]');
    if (!list) { return; }
    if (!append) { list.textContent = ''; }

    if (!historyState.rows.length && !append) {
      list.appendChild(el('li', 'lox-empty', 'No incidents recorded in this window.'));
      return;
    }
    historyState.rows.forEach(function (entry) { list.appendChild(historyRow(entry)); });
  }

  function loadHistory(append) {
    if (!endpoints.history) { return Promise.resolve(); }
    var url = endpoints.history
      + (endpoints.history.indexOf('?') === -1 ? '?' : '&')
      + 'days=' + encodeURIComponent(historyState.days)
      + '&per_page=' + (historyState.majorOnly ? 20 : 20)
      + '&page=' + historyState.page
      + '&severity=' + (historyState.majorOnly ? 'important' : 'all')
      + (historyState.majorOnly ? '&min_severity=outage' : '');

    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : Promise.reject(new Error('history ' + response.status)); })
      .then(function (payload) {
        var rows = [];
        (payload.providers || []).forEach(function (provider) {
          (provider.incidents || []).forEach(function (incident) {
            rows.push({
              provider: provider.label || provider.id,
              provider_label: provider.label || provider.id,
              summary: incident.summary,
              severity: incident.severity || incident.status,
              first_seen: incident.first_seen,
              last_seen: incident.last_seen,
              url: incident.url
            });
          });
        });
        rows.sort(function (left, right) { return toTimestamp(right.first_seen) - toTimestamp(left.first_seen); });

        historyState.rows = rows;
        renderHistory(append);

        var meta = payload.meta || {};
        var counter = root.querySelector('[data-lox-history-count]');
        if (counter) { counter.textContent = (meta.total_matching || rows.length) + ' entries'; }
        var more = root.querySelector('[data-lox-history-more]');
        if (more) { more.hidden = !meta.has_more; }
      })
      .catch(function () {
        var counter = root.querySelector('[data-lox-history-count]');
        if (counter) { counter.textContent = 'log unavailable'; }
      });
  }

  /* ---------- polling ---------- */

  function setStatus(message, isError) {
    var node = root.querySelector('[data-lox-status]');
    if (!node) { return; }
    node.textContent = message || '';
    node.style.color = isError ? 'var(--lox-down)' : '';
  }

  function loadSummary() {
    if (!endpoints.summary) { return Promise.resolve(); }
    return fetch(endpoints.summary, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : Promise.reject(new Error('summary ' + response.status)); })
      .then(function (payload) {
        renderState(payload);
        nextPollAt = Date.now() + pollInterval;
        setStatus('');
      })
      .catch(function () {
        setStatus('Poll failed. Showing the last good read.', true);
      });
  }

  function tick() {
    Array.prototype.forEach.call(root.querySelectorAll('[data-lox-rel]'), function (node) {
      var iso = node.getAttribute('datetime');
      if (!iso) { return; }
      node.textContent = relativeTime(toTimestamp(iso));
    });

    var countdown = root.querySelector('[data-lox-countdown]');
    if (countdown) {
      var seconds = Math.max(0, Math.round((nextPollAt - Date.now()) / 1000));
      countdown.textContent = 'Next poll in ' + seconds + 's.';
    }

    if (Date.now() >= nextPollAt) {
      nextPollAt = Date.now() + pollInterval;
      loadSummary();
    }
  }

  /* ---------- CSV ---------- */

  function exportCsv() {
    var header = ['provider', 'state', 'detail', 'category', 'source', 'checked_at', 'status_page'];
    var lines = [header.join(',')];
    latestProviders.forEach(function (row) {
      lines.push([
        row.name,
        STATE_LABELS[row.state] || row.state,
        row.summary,
        row.category,
        row.source,
        row.checkedTs ? new Date(row.checkedTs * 1000).toISOString() : '',
        row.url
      ].map(function (value) {
        return '"' + String(value === undefined || value === null ? '' : value).replace(/"/g, '""') + '"';
      }).join(','));
    });

    var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'lousy-outages-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  }

  /* ---------- forms ---------- */

  function serialize(form) {
    var data = {};
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name || field.disabled) { return; }
      if (field.type === 'checkbox') {
        if (!field.checked) { return; }
        if (field.name.slice(-2) === '[]') {
          var key = field.name.slice(0, -2);
          data[key] = data[key] || [];
          data[key].push(field.value);
          return;
        }
        data[field.name] = field.value || '1';
        return;
      }
      if (field.type === 'radio' && !field.checked) { return; }
      data[field.name] = field.value;
    });
    return data;
  }

  function wireForm(form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var status = form.querySelector('[data-lox-form-status]');
      var button = form.querySelector('button[type="submit"]');
      if (button) { button.disabled = true; }
      if (status) {
        status.className = 'lox-form__status';
        status.textContent = 'Sending…';
      }

      fetch(form.getAttribute('action'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(serialize(form))
      })
        .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (payload) {
            return { ok: response.ok, payload: payload };
          });
        })
        .then(function (result) {
          var payload = result.payload || {};
          var succeeded = result.ok && payload.success !== false && payload.ok !== false;
          if (status) {
            status.className = 'lox-form__status' + (succeeded ? '' : ' lox-form__status--error');
            status.textContent = payload.message || (succeeded ? 'Logged. Thanks.' : 'That did not go through. Try again.');
          }
          if (succeeded) { form.reset(); }
        })
        .catch(function () {
          if (status) {
            status.className = 'lox-form__status lox-form__status--error';
            status.textContent = 'Network said no. Try again.';
          }
        })
        .then(function () {
          if (button) { button.disabled = false; }
        });
    });
  }

  /* ---------- wiring ---------- */

  Array.prototype.forEach.call(root.querySelectorAll('[data-lox-filter]'), function (control) {
    control.addEventListener('input', applyMatrixFilters);
    control.addEventListener('change', applyMatrixFilters);
  });

  var reload = root.querySelector('[data-lox-reload]');
  if (reload) {
    reload.addEventListener('click', function () {
      reload.disabled = true;
      setStatus('Polling…');
      var work = (endpoints.refresh && endpoints.nonce)
        ? fetch(endpoints.refresh, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': endpoints.nonce, Accept: 'application/json' }
          }).catch(function () { return null; })
        : Promise.resolve(null);

      work.then(loadSummary).then(function () {
        reload.disabled = false;
      });
    });
  }

  var csv = root.querySelector('[data-lox-csv]');
  if (csv) { csv.addEventListener('click', exportCsv); }

  var windowSelect = root.querySelector('[data-lox-history-window]');
  if (windowSelect) {
    windowSelect.addEventListener('change', function () {
      historyState.days = parseInt(windowSelect.value, 10) || 0;
      historyState.page = 1;
      loadHistory(false);
    });
  }

  var majorToggle = root.querySelector('[data-lox-history-major]');
  if (majorToggle) {
    majorToggle.addEventListener('change', function () {
      historyState.majorOnly = majorToggle.checked;
      historyState.page = 1;
      loadHistory(false);
    });
  }

  var historyMore = root.querySelector('[data-lox-history-more]');
  if (historyMore) {
    historyMore.addEventListener('click', function () {
      historyState.page += 1;
      loadHistory(true);
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-lox-form]'), wireForm);

  latestProviders = providerRows({ providers: (CONFIG.initial && CONFIG.initial.providers) || [] });
  setInterval(tick, 1000);
  tick();
  loadHistory(false);
})();
