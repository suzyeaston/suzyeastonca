
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
    buttonLoading: 'Refreshing…',
    teaserCaption: 'Check if your favourite services are up. Insert coin to refresh.',
    microcopy: 'Vancouver weather: cloudy with a chance of outages.',
    offlineMessage: 'Arcade link is jittery. Data might be stale—try again soon.',
    tickerFallback: 'All quiet on the outage front.',
    unknownStatus: 'Unknown',
    noPublicStatus: 'No public status API',
    detailsLabel: 'Details',
    detailsHide: 'Hide details',
    noIncidents: 'No active incidents. Go write a chorus.',
    degradedNoIncidents: 'Status page shows degraded performance. Pop over there for the detailed log.',
    etaInvestigating: 'ETA: investigating — translation: nobody knows yet.',
    viewProvider: 'View provider status →'
  };

  var snarkPre = ['Look.', 'Honestly?', 'Sure, fine.', 'Stop overthinking it.', ''];
  var snarkLines = {
    Operational: [
      "It works. Don’t write a post-mortem for uptime.",
      "Green light means ‘go’. Resist the urge to refactor prod."
    ],
    Degraded: [
      "It’s limping, not dying. Tape it up and keep playing.",
      "Minor outage. Major panic in Slack. Breathe."
    ],
    Outage: [
      "It’s down. Stop refreshing and start reading logs.",
      "Put the runbook where your anxiety is."
    ],
    Maintenance: [
      "Scheduled pain now so you don’t suffer later."
    ],
    Unknown: [
      "No signal. Either it’s fine or they’re hiding."
    ],
    byProvider: {
      Cloudflare: [
        "If the edge sneezes, your site catches a cold."
      ],
      OpenAI: [
        "The model’s fine. Your prompt isn’t. Kidding. It’s probably rate limits."
      ],
      AWS: [
        "us-east-1 is a lifestyle choice. You chose chaos."
      ],
      Azure: [
        "Azure’s blue because you are. Keep refreshing the portal."
      ],
      'Google Cloud': [
        "If it ain’t in Stackdriver, did it even happen?"
      ],
      GitHub: [
        "If pushes fail, touch grass. CI will still betray you later."
      ],
      Slack: [
        "When Slack dies, meetings rise. Choose wisely."
      ],
      Zscaler: [
        "Secure edge hiccup? Double-check the tunnels before blaming the Wi-Fi."
      ]
    }
  };

  var state = {
    container: null,
    grid: null,
    tickerEl: null,
    refreshButton: null,
    lastUpdatedSpan: null,
    summarySection: null,
    summaryPrimary: null,
    summarySecondary: null,
    summaryList: null,
    feedLink: null,
    emailLink: null,
    pollTimer: null,
    inFlight: null,
    lastFetchedAt: null,
    cards: Object.create(null),
    strings: defaultStrings,
    config: {
      endpoint: '',
      pollInterval: 300000,
      fetchTimeout: 10000,
      locale: 'en-CA',
      providers: [],
      voiceEnabled: false,
      debug: false,
      feedUrl: '',
      alertEmail: '',
      initialSummary: null
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
    state.config.voiceEnabled = Boolean(baseConfig.voiceEnabled);
    state.config.debug = Boolean(baseConfig.debug);
    state.config.feedUrl = baseConfig.feedUrl || state.config.feedUrl;
    state.config.alertEmail = baseConfig.alertEmail || state.config.alertEmail;
    state.config.initialSummary = baseConfig.initialSummary || state.config.initialSummary;

    if (state.config.debug) {
      state.config.pollInterval = Math.max(10, state.config.pollInterval);
      state.config.fetchTimeout = Math.max(500, state.config.fetchTimeout);
    } else {
      state.config.pollInterval = Math.max(60000, state.config.pollInterval);
      state.config.fetchTimeout = Math.max(3000, state.config.fetchTimeout);
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

  function sanitizeText(text) {
    if (typeof text !== 'string') {
      return '';
    }
    return text.replace(/\s+/g, ' ').trim();
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
      hour: 'numeric',
      minute: '2-digit',
      second: '2-digit',
      hour12: true,
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

  function formatRelativeShort(diffMs) {
    if (!isFinite(diffMs)) {
      return '';
    }
    var abs = Math.abs(diffMs);
    var minute = 60 * 1000;
    var hour = 60 * minute;
    var day = 24 * hour;
    if (abs < minute) {
      return 'just now';
    }
    if (abs < hour) {
      return Math.round(abs / minute) + 'm ago';
    }
    if (abs < day) {
      return Math.round(abs / hour) + 'h ago';
    }
    return Math.round(abs / day) + 'd ago';
  }

  function buildStartLine(isoString) {
    if (!isoString) {
      return '';
    }
    var date = new Date(isoString);
    if (isNaN(date.getTime())) {
      return '';
    }
    var startFormatter = new Intl.DateTimeFormat(undefined, {
      month: 'short',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit'
    });
    var relative = formatRelativeShort(Date.now() - date.getTime());
    var label = 'Started: ' + startFormatter.format(date);
    if (relative) {
      label += ' (' + relative + ')';
    }
    return label;
  }

  function buildUpdatedLine(isoString) {
    if (!isoString) {
      return '';
    }
    var date = new Date(isoString);
    if (isNaN(date.getTime())) {
      return '';
    }
    var formatter = new Intl.DateTimeFormat(undefined, {
      month: 'short',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit'
    });
    var relative = formatRelativeShort(Date.now() - date.getTime());
    var label = 'Latest update: ' + formatter.format(date);
    if (relative) {
      label += ' (' + relative + ')';
    }
    return label;
  }

  function normalizeStatus(statusCode, label) {
    var code = (statusCode || '').toLowerCase();
    var known = {
      operational: label || 'Operational',
      degraded: label || 'Degraded',
      outage: label || 'Outage',
      maintenance: label || 'Maintenance',
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

  function computeSummary(providers) {
    var summary = {
      total: 0,
      affected: 0,
      unknown: 0,
      providers: [],
      feedUrl: state.config.feedUrl,
      alertEmail: state.config.alertEmail
    };
    if (!Array.isArray(providers)) {
      return summary;
    }
    summary.total = providers.length;
    providers.forEach(function (provider) {
      if (!provider) {
        return;
      }
      var status = sanitizeText(provider.stateCode || provider.statusCode || provider.status || '').toLowerCase();
      if (status === 'unknown') {
        summary.unknown += 1;
      }
      var incidents = Array.isArray(provider.incidents) ? provider.incidents.length : 0;
      var hasIssue = incidents > 0 || status === 'degraded' || status === 'outage' || status === 'maintenance';
      if (!status && hasIssue) {
        status = 'degraded';
      }
      if (!hasIssue) {
        return;
      }
      summary.affected += 1;
      summary.providers.push({
        id: provider.id || provider.provider || provider.name || '',
        name: sanitizeText(provider.name || provider.provider || provider.id || 'Unknown provider'),
        label: sanitizeText(provider.state || provider.status || ''),
        status: status || 'unknown',
        summary: sanitizeText(provider.summary || provider.message || '')
      });
    });
    return summary;
  }

  function renderSummary(summary) {
    if (!state.summarySection) {
      return;
    }
    state.summarySection.classList.remove('is-offline');

    summary = summary || {};
    var total = Number(summary.total);
    if (!isFinite(total)) {
      total = state.config.providers.length;
    }
    var affected = Number(summary.affected) || 0;
    var unknown = Number(summary.unknown) || 0;
    var providers = Array.isArray(summary.providers) ? summary.providers.slice(0, 4) : [];
    var feedUrl = summary.feedUrl || state.config.feedUrl || '';
    var alertEmail = summary.alertEmail || state.config.alertEmail || '';

    state.config.feedUrl = feedUrl;
    state.config.alertEmail = alertEmail;
    state.config.lastSummary = summary;

    state.summarySection.classList.toggle('has-alerts', affected > 0);
    state.summarySection.classList.toggle('has-unknown', affected === 0 && unknown > 0);

    if (state.feedLink) {
      if (feedUrl) {
        state.feedLink.href = feedUrl;
        state.feedLink.removeAttribute('hidden');
      } else {
        state.feedLink.setAttribute('hidden', 'hidden');
      }
    }

    if (state.emailLink) {
      if (alertEmail) {
        state.emailLink.textContent = alertEmail;
        state.emailLink.href = 'mailto:' + alertEmail + '?subject=' + encodeURIComponent('Lousy Outages alerts');
        state.emailLink.removeAttribute('hidden');
      } else {
        state.emailLink.setAttribute('hidden', 'hidden');
      }
    }

    var headline = '';
    var details = '';

    if (affected > 0) {
      headline = affected === 1
        ? 'Heads up: one provider is having trouble.'
        : 'Heads up: ' + affected + ' providers are having trouble.';
      var names = providers.map(function (prov) {
        var normalized = normalizeStatus(prov.status, prov.label);
        var label = sanitizeText(normalized.label);
        return prov.name + (label ? ' (' + label + ')' : '');
      });
      if (names.length) {
        details = names.join(', ');
        var remainder = affected - names.length;
        if (remainder > 0) {
          details += ' +' + remainder + ' more';
        }
      }
    } else if (unknown > 0) {
      headline = 'Monitoring ' + (total || state.config.providers.length || 0) + ' providers.';
      var plural = unknown === 1 ? 'provider is' : 'providers are';
      details = unknown + ' ' + plural + ' currently unknown.';
    } else {
      var count = total || state.config.providers.length || 0;
      headline = count
        ? 'All systems operational across ' + count + ' providers.'
        : 'All systems operational.';
      details = 'No incidents detected right now.';
    }

    if (state.summaryPrimary) {
      state.summaryPrimary.textContent = headline;
    }
    if (state.summarySecondary) {
      state.summarySecondary.textContent = details;
      state.summarySecondary.hidden = !details;
    }

    if (state.summaryList) {
      while (state.summaryList.firstChild) {
        state.summaryList.removeChild(state.summaryList.firstChild);
      }
      if (affected > 0 && providers.length && global.document) {
        state.summaryList.hidden = false;
        providers.forEach(function (prov) {
          var item = global.document.createElement('li');
          item.className = 'status-summary__item';
          var normalized = normalizeStatus(prov.status, prov.label);
          var chip = global.document.createElement('span');
          chip.className = 'status-chip ' + normalized.className;
          chip.textContent = normalized.label;
          item.appendChild(chip);
          var name = global.document.createElement('span');
          name.className = 'status-summary__provider';
          name.textContent = ' ' + prov.name;
          item.appendChild(name);
          if (prov.summary) {
            var note = global.document.createElement('span');
            note.className = 'status-summary__note';
            note.textContent = ' — ' + prov.summary;
            item.appendChild(note);
          }
          state.summaryList.appendChild(item);
        });
      } else {
        state.summaryList.hidden = true;
      }
    }
  }

  function snarkOutage(providerName, stateLabel, summary) {
    var provider = sanitizeText(providerName) || 'Unknown';
    var status = sanitizeText(stateLabel);
    if (!status) {
      status = 'Unknown';
    }
    var cleanedSummary = sanitizeText(summary);
    var stateLines = snarkLines[status] || snarkLines.Unknown;
    var pre = snarkPre[Math.floor(Math.random() * snarkPre.length)];
    var stateLine = stateLines[Math.floor(Math.random() * stateLines.length)];
    var providerQuips = snarkLines.byProvider[provider] || (function () {
      if (!Array.isArray(state.config.providers)) {
        return [];
      }
      for (var i = 0; i < state.config.providers.length; i += 1) {
        var meta = state.config.providers[i];
        if (meta && meta.name === provider && snarkLines.byProvider[meta.name]) {
          return snarkLines.byProvider[meta.name];
        }
      }
      return [];
    })();
    var providerLine = providerQuips.length
      ? providerQuips[Math.floor(Math.random() * providerQuips.length)]
      : '';
    var parts = [pre, stateLine];
    if (providerLine) {
      parts.push(providerLine);
    } else if (cleanedSummary) {
      parts.push(cleanedSummary);
    }
    var text = sanitizeText(parts.join(' '));
    if (!text && cleanedSummary) {
      text = cleanedSummary;
    }
    return text;
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

  function speakText(text) {
    if (!text || !state.config.voiceEnabled) {
      return;
    }
    var synth = global.speechSynthesis;
    var Utterance = global.SpeechSynthesisUtterance;
    if (!synth || typeof synth.cancel !== 'function' || typeof Utterance !== 'function') {
      return;
    }
    var utterance = new Utterance(text);
    var voices = typeof synth.getVoices === 'function' ? synth.getVoices() : [];
    var preferred = voices.find(function (voice) {
      return /English/i.test(voice.lang) && /Google UK English Male|Microsoft David/i.test(voice.name);
    }) || voices.find(function (voice) {
      return /en/i.test(voice.lang);
    });
    if (preferred) {
      utterance.voice = preferred;
    }
    utterance.pitch = 0.7;
    utterance.rate = 0.7;
    synth.cancel();
    synth.speak(utterance);
  }

  function speakSnark(text) {
    speakText(text);
  }

  function announceProviderStatus(record) {
    if (!record) {
      return;
    }
    var parts = [];
    if (record.nameEl && record.nameEl.textContent) {
      parts.push(sanitizeText(record.nameEl.textContent));
    }
    if (record.statusEl && record.statusEl.textContent) {
      parts.push('Status: ' + sanitizeText(record.statusEl.textContent));
    }
    if (record.summaryEl && record.summaryEl.textContent) {
      parts.push(sanitizeText(record.summaryEl.textContent));
    }
    if (record.details && typeof record.details.querySelector === 'function') {
      var incidentSummary = record.details.querySelector('.incident-item .incident-summary');
      if (!incidentSummary) {
        incidentSummary = record.details.querySelector('.incident-empty');
      }
      if (incidentSummary && incidentSummary.textContent) {
        var incidentText = sanitizeText(incidentSummary.textContent);
        if (incidentText) {
          parts.push(incidentText);
        }
      }
    }
    var announcement = sanitizeText(parts.join('. '));
    if (announcement) {
      speakText(announcement);
    }
  }

  function prefersReducedMotion() {
    if (!global.matchMedia) {
      return false;
    }
    return global.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function typewriter(element, text) {
    if (!element) {
      return;
    }
    if (!text) {
      element.textContent = '';
      return;
    }
    if (prefersReducedMotion()) {
      element.textContent = text;
      return;
    }
    element.textContent = '';
    var i = 0;
    var chars = text.split('');
    var timer = global.setInterval(function () {
      element.textContent += chars[i++];
      if (i >= chars.length) {
        global.clearInterval(timer);
      }
    }, 30);
  }

  function applySnark(record, provider) {
    if (!record || !record.snarkEl) {
      return;
    }
    var text = snarkOutage(provider.name || provider.provider, provider.state || provider.stateCode, provider.summary || '');
    if (!text) {
      record.snarkEl.textContent = '';
      return;
    }
    if (record.lastSnark === text) {
      return;
    }
    record.lastSnark = text;
    typewriter(record.snarkEl, text);
    speakSnark(text);
  }

  function updateTimestamp(iso) {
    if (!state.lastUpdatedSpan) {
      return;
    }
    var formatted = formatTimestamp(iso);
    state.lastUpdatedSpan.textContent = formatted || '--';
    state.lastFetchedAt = iso;
  }

  function ensureCard(meta) {
    var id = meta && (meta.id || meta.provider || meta.name);
    if (!id) {
      return null;
    }
    if (state.cards[id]) {
      return state.cards[id];
    }
    if (!state.grid || !global.document) {
      return null;
    }
    var card = createCard(meta);
    state.grid.appendChild(card.card);
    state.cards[id] = card;
    return card;
  }

  function createCard(meta) {
    var doc = global.document;
    var card = doc.createElement('article');
    card.className = 'provider-card';
    card.setAttribute('role', 'listitem');
    card.setAttribute('data-id', meta.id);
    card.setAttribute('data-name', meta.name || meta.provider || meta.id);

    var inner = doc.createElement('div');
    inner.className = 'provider-card__inner';
    card.appendChild(inner);

    var header = doc.createElement('header');
    header.className = 'provider-card__header';
    inner.appendChild(header);

    var nameEl = doc.createElement('h3');
    nameEl.className = 'provider-card__name';
    nameEl.textContent = meta.name || meta.provider || meta.id;
    header.appendChild(nameEl);

    var status = doc.createElement('span');
    status.className = 'status-badge status--unknown';
    status.setAttribute('data-status', 'unknown');
    status.textContent = state.strings.unknownStatus;
    header.appendChild(status);

    var summary = doc.createElement('p');
    summary.className = 'provider-card__summary';
    summary.textContent = '';
    inner.appendChild(summary);

    var snark = doc.createElement('p');
    snark.className = 'provider-card__snark';
    snark.setAttribute('data-snark', 'true');
    snark.innerHTML = '&nbsp;';
    inner.appendChild(snark);

    var toggle = doc.createElement('button');
    toggle.type = 'button';
    toggle.className = 'details-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    var toggleLabel = doc.createElement('span');
    toggleLabel.className = 'toggle-label';
    toggleLabel.textContent = state.strings.detailsLabel;
    toggle.appendChild(toggleLabel);
    inner.appendChild(toggle);

    var details = doc.createElement('section');
    var detailsId = 'lo-details-' + meta.id;
    details.className = 'provider-details';
    details.id = detailsId;
    details.hidden = true;
    toggle.setAttribute('aria-controls', detailsId);
    inner.appendChild(details);

    var incidents = doc.createElement('div');
    incidents.className = 'incidents';
    incidents.setAttribute('data-empty-text', state.strings.noIncidents);
    var empty = doc.createElement('p');
    empty.className = 'incident-empty';
    empty.textContent = state.strings.noIncidents;
    incidents.appendChild(empty);
    details.appendChild(incidents);

    var link = doc.createElement('a');
    link.className = 'provider-link';
    link.setAttribute('data-default-url', meta.url || '');
    link.href = meta.url || '#';
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = state.strings.viewProvider;
    details.appendChild(link);

    toggle.addEventListener('click', function () {
      toggleDetails(id);
    });

    return {
      card: card,
      header: header,
      nameEl: nameEl,
      statusEl: status,
      summaryEl: summary,
      snarkEl: snark,
      toggle: toggle,
      details: details,
      incidents: incidents,
      link: link,
      lastSnark: ''
    };
  }

  function prepareExistingCards() {
    if (!state.container || !global.document) {
      return;
    }
    state.cards = Object.create(null);
    var cards = state.container.querySelectorAll('.provider-card');
    cards.forEach(function (card) {
      var id = card.getAttribute('data-id');
      if (!id) {
        return;
      }
      var record = {
        card: card,
        header: card.querySelector('.provider-card__header'),
        nameEl: card.querySelector('.provider-card__name'),
        statusEl: card.querySelector('.status-badge'),
        summaryEl: card.querySelector('.provider-card__summary'),
        snarkEl: card.querySelector('.provider-card__snark'),
        toggle: card.querySelector('.details-toggle'),
        details: card.querySelector('.provider-details'),
        incidents: card.querySelector('.provider-details .incidents'),
        link: card.querySelector('.provider-link'),
        lastSnark: ''
      };
      state.cards[id] = record;
      if (record.toggle) {
        record.toggle.addEventListener('click', function () {
          toggleDetails(id);
        });
      }
    });
  }

  function toggleDetails(id) {
    var record = state.cards[id];
    if (!record || !record.details || !record.toggle) {
      return;
    }
    var isExpanded = record.toggle.getAttribute('aria-expanded') === 'true';
    var nextExpanded = !isExpanded;
    record.toggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
    record.details.hidden = !nextExpanded;
    var label = nextExpanded ? state.strings.detailsHide : state.strings.detailsLabel;
    var labelEl = record.toggle.querySelector('.toggle-label');
    if (labelEl) {
      labelEl.textContent = label;
    } else {
      record.toggle.textContent = label;
    }
    if (nextExpanded) {
      announceProviderStatus(record);
    }
  }

  function formatImpact(impact) {
    var value = sanitizeText(impact).toLowerCase();
    if (value === 'critical' || value === 'major' || value === 'minor') {
      return value;
    }
    return 'minor';
  }

  function impactLabel(impact) {
    var value = formatImpact(impact);
    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  function updateIncidents(record, provider) {
    if (!record || !record.incidents) {
      return;
    }
    var stateCode = sanitizeText(provider.stateCode || provider.statusCode || '').toLowerCase();
    var degraded = stateCode && stateCode !== 'operational' && stateCode !== 'unknown';
    while (record.incidents.firstChild) {
      record.incidents.removeChild(record.incidents.firstChild);
    }
    var incidents = Array.isArray(provider.incidents) ? provider.incidents : [];
    if (!incidents.length) {
      var empty = global.document ? global.document.createElement('p') : { textContent: '' };
      empty.className = 'incident-empty';
      var emptyMessage = degraded && state.strings.degradedNoIncidents
        ? state.strings.degradedNoIncidents
        : state.strings.noIncidents;
      empty.textContent = emptyMessage;
      record.incidents.setAttribute('data-empty-text', emptyMessage);
      record.incidents.appendChild(empty);
      return;
    }
    record.incidents.setAttribute('data-empty-text', state.strings.noIncidents);
    var list = global.document ? global.document.createElement('ul') : null;
    if (list) {
      list.className = 'incident-list';
      record.incidents.appendChild(list);
    }
    incidents.forEach(function (incident) {
      var item = global.document ? global.document.createElement('li') : null;
      if (!item) {
        return;
      }
      var impact = formatImpact(incident.impact);
      item.className = 'incident-item impact--' + impact;
      item.setAttribute('data-impact', impact);

      var title = global.document.createElement('h4');
      title.className = 'incident-title';
      title.textContent = incident.title || 'Incident';
      item.appendChild(title);

      var badge = global.document.createElement('span');
      badge.className = 'impact-badge';
      badge.textContent = impactLabel(impact);
      title.appendChild(badge);

      var startedLine = buildStartLine(incident.startedAt || incident.started_at);
      if (startedLine) {
        var startEl = global.document.createElement('p');
        startEl.className = 'incident-start';
        startEl.textContent = startedLine;
        item.appendChild(startEl);
      }

      var summary = sanitizeText(incident.summary || '');
      if (summary) {
        var summaryEl = global.document.createElement('p');
        summaryEl.className = 'incident-summary';
        summaryEl.textContent = summary;
        item.appendChild(summaryEl);
      }

      var eta = sanitizeText(incident.eta || '');
      var etaLabel = eta && eta.toLowerCase() !== 'investigating'
        ? 'ETA: ' + eta
        : state.strings.etaInvestigating;
      var etaEl = global.document.createElement('p');
      etaEl.className = 'incident-eta';
      etaEl.textContent = etaLabel;
      item.appendChild(etaEl);

      var updatedLine = buildUpdatedLine(incident.updatedAt || incident.updated_at);
      if (updatedLine) {
        var updatedEl = global.document.createElement('p');
        updatedEl.className = 'incident-updated';
        updatedEl.textContent = updatedLine;
        item.appendChild(updatedEl);
      }

      if (list) {
        list.appendChild(item);
      } else {
        record.incidents.appendChild(item);
      }
    });
  }

  function updateProviderLink(record, provider) {
    if (!record || !record.link) {
      return;
    }
    var url = sanitizeText(provider.url || (provider.metaUrl || ''));
    if (!url) {
      url = record.link.getAttribute('data-default-url') || '#';
    }
    record.link.href = url || '#';
    if (url === '#') {
      record.link.setAttribute('aria-disabled', 'true');
    } else {
      record.link.removeAttribute('aria-disabled');
    }
  }

  function ensureAllProviders(providers) {
    var existing = Object.create(null);
    if (Array.isArray(providers)) {
      providers.forEach(function (provider) {
        var id = provider.id || provider.provider;
        if (id) {
          existing[id] = true;
        }
      });
    }
    state.config.providers.forEach(function (meta) {
      if (!existing[meta.id]) {
        var record = ensureCard(meta);
        if (!record) {
          providers.push({
            id: meta.id,
            provider: meta.name,
            name: meta.name,
            state: state.strings.unknownStatus,
            stateCode: 'unknown',
            summary: state.strings.noPublicStatus,
            incidents: [],
            url: meta.url || ''
          });
        }
      }
    });
    return providers;
  }

  function updateTicker(messages) {
    if (!state.tickerEl) {
      return;
    }
    if (!messages || !messages.length) {
      state.tickerEl.textContent = state.strings.tickerFallback || '';
      return;
    }
    var unique = [];
    messages.forEach(function (message) {
      if (!message) {
        return;
      }
      if (unique.indexOf(message) === -1) {
        unique.push(message);
      }
    });
    state.tickerEl.textContent = unique.join(' • ');
  }

  function renderProviders(providers) {
    if (!Array.isArray(providers)) {
      return [];
    }
    providers = ensureAllProviders(providers);
    var summaries = [];
    providers.forEach(function (provider) {
      var id = provider.id || provider.provider;
      if (!id) {
        return;
      }
      var record = state.cards[id];
      if (!record) {
        record = ensureCard({ id: id, name: provider.name || provider.provider || id, url: provider.url || '' });
      }
      if (!record) {
        return;
      }
      record.nameEl.textContent = provider.name || provider.provider || id;
      var normalized = normalizeStatus(provider.stateCode || provider.statusCode, provider.state || provider.status);
      record.statusEl.textContent = normalized.label;
      record.statusEl.dataset.status = normalized.code;
      record.statusEl.className = 'status-badge ' + normalized.className;
      var summary = sanitizeText(provider.summary || provider.message || '');
      record.summaryEl.textContent = summary;
      updateIncidents(record, provider);
      updateProviderLink(record, provider);
      applySnark(record, provider);
      summaries.push(summary);
    });
    return summaries;
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

  function handleSuccess(payload) {
    var providers = Array.isArray(payload.providers) ? payload.providers.slice() : [];
    var summaries = renderProviders(providers);
    var tickerMessages = summaries.filter(function (msg) {
      return sanitizeText(msg);
    });
    updateTicker(tickerMessages.length ? tickerMessages : [state.strings.tickerFallback]);
    var fetchedAt = payload.meta && payload.meta.fetchedAt ? payload.meta.fetchedAt : new Date().toISOString();
    updateTimestamp(fetchedAt);
    renderSummary(computeSummary(providers));
    return providers;
  }

  function handleFailure(error) {
    logError(error);
    if (state.tickerEl) {
      state.tickerEl.textContent = state.strings.offlineMessage || '';
    }
    if (state.summarySection) {
      state.summarySection.classList.add('is-offline');
      state.summarySection.classList.remove('has-alerts');
      state.summarySection.classList.remove('has-unknown');
    }
    if (state.summaryPrimary) {
      state.summaryPrimary.textContent = state.strings.offlineMessage || 'Status feed unreachable. Data might be stale.';
    }
    if (state.summarySecondary) {
      var fallback = '';
      if (state.lastFetchedAt) {
        var last = new Date(state.lastFetchedAt);
        if (!isNaN(last.getTime())) {
          fallback = 'Last good update ' + formatRelativeShort(Date.now() - last.getTime()) + '.';
        }
      }
      state.summarySecondary.textContent = fallback;
      state.summarySecondary.hidden = !fallback;
    }
    if (state.summaryList) {
      while (state.summaryList.firstChild) {
        state.summaryList.removeChild(state.summaryList.firstChild);
      }
      state.summaryList.hidden = true;
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
    state.grid = state.container.querySelector('.providers-grid');
    state.tickerEl = state.container.querySelector('.ticker');
    state.refreshButton = state.container.querySelector('.coin-btn');
    state.lastUpdatedSpan = state.container.querySelector('.last-updated span');
    state.summarySection = state.container.querySelector('[data-summary]');
    state.summaryPrimary = state.container.querySelector('[data-summary-primary]');
    state.summarySecondary = state.container.querySelector('[data-summary-secondary]');
    state.summaryList = state.container.querySelector('[data-summary-list]');
    state.feedLink = state.container.querySelector('[data-feed-link]');
    state.emailLink = state.container.querySelector('[data-email-link]');
    state.config.voiceEnabled = state.config.voiceEnabled || state.container.getAttribute('data-voice-enabled') === '1';

    if (state.feedLink && state.config.feedUrl) {
      state.feedLink.href = state.config.feedUrl;
    }
    if (state.emailLink && state.config.alertEmail) {
      state.emailLink.textContent = state.config.alertEmail;
      state.emailLink.href = 'mailto:' + state.config.alertEmail + '?subject=' + encodeURIComponent('Lousy Outages alerts');
    }

    prepareExistingCards();
    attachButton();

    if (state.config.initialSummary) {
      renderSummary(state.config.initialSummary);
    } else {
      renderSummary(computeSummary([]));
    }

    if (state.lastUpdatedSpan) {
      var initial = state.lastUpdatedSpan.getAttribute('data-initial');
      if (initial) {
        updateTimestamp(initial);
      } else {
        state.lastUpdatedSpan.textContent = '--';
      }
    }

    global.setTimeout(function () {
      fetchStatuses({ force: true });
    }, 100);
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
    snarkOutage: snarkOutage,
    _state: state
  };
});
