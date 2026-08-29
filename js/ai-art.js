/**
 * MACHINE VISIONS gallery interactions: filters, URL state, detail dialog.
 */
(function () {
  'use strict';

  var FILTER_KEYS_EXPORT = ['all', 'film', 'stills', 'loops', 'process'];

  function normalizeFilter(value) {
    var next = String(value || 'all').toLowerCase();
    return FILTER_KEYS_EXPORT.indexOf(next) >= 0 ? next : 'all';
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
      FILTER_KEYS: FILTER_KEYS_EXPORT,
      normalizeFilter: normalizeFilter
    };
  }

  if (typeof document === 'undefined') {
    return;
  }

  var root = document.querySelector('[data-ai-art-root]');
  if (!root) return;

  var FILTER_KEYS = ['all', 'film', 'stills', 'loops', 'process'];
  var works = [];
  try {
    var raw = document.getElementById('ai-art-works-data');
    works = raw ? JSON.parse(raw.textContent || '[]') : [];
  } catch (err) {
    works = [];
  }

  var worksBySlug = {};
  works.forEach(function (work) {
    if (work && work.slug) worksBySlug[work.slug] = work;
  });

  var filterButtons = Array.prototype.slice.call(
    root.querySelectorAll('[data-ai-art-filter]')
  );
  var cards = Array.prototype.slice.call(root.querySelectorAll('.ai-art-card'));
  var emptyFilter = root.querySelector('[data-ai-art-filter-empty]');
  var detail = root.querySelector('[data-ai-art-detail]');
  var detailBody = root.querySelector('[data-ai-art-detail-body]');
  var closeBtn = root.querySelector('[data-ai-art-close]');

  function currentFilterFromUrl() {
    try {
      var params = new URLSearchParams(window.location.search);
      var value = (params.get('filter') || 'all').toLowerCase();
      return FILTER_KEYS.indexOf(value) >= 0 ? value : 'all';
    } catch (e) {
      return 'all';
    }
  }

  function setFilter(filter, push) {
    var next = FILTER_KEYS.indexOf(filter) >= 0 ? filter : 'all';
    filterButtons.forEach(function (btn) {
      var active = btn.getAttribute('data-ai-art-filter') === next;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    var visible = 0;
    cards.forEach(function (card) {
      var kind = card.getAttribute('data-kind') || '';
      var show = next === 'all' || kind === next;
      card.hidden = !show;
      if (show) visible += 1;
    });

    if (emptyFilter) {
      emptyFilter.hidden = visible > 0 || cards.length === 0;
    }

    try {
      var url = new URL(window.location.href);
      if (next === 'all') {
        url.searchParams.delete('filter');
      } else {
        url.searchParams.set('filter', next);
      }
      if (push) {
        window.history.pushState({ aiArtFilter: next }, '', url.toString());
      } else {
        window.history.replaceState({ aiArtFilter: next }, '', url.toString());
      }
    } catch (e) {
      /* ignore URL sync failures */
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function listHtml(items, mapper) {
    if (!items || !items.length) return '';
    return (
      '<ul class="ai-art-detail__list">' +
      items
        .map(function (item) {
          return '<li>' + mapper(item) + '</li>';
        })
        .join('') +
      '</ul>'
    );
  }

  function renderDetail(work) {
    if (!detailBody || !work) return;
    var kind = work.kind || 'still';
    var w = Math.max(1, Number(work.width) || 16);
    var h = Math.max(1, Number(work.height) || 9);
    var media;

    if (kind === 'film' || kind === 'loop') {
      media =
        '<div class="ai-art-detail__media" style="--ai-art-aspect:' +
        w +
        ' / ' +
        h +
        '"><video controls playsinline preload="metadata" poster="' +
        escapeHtml(work.posterUrl || work.thumbnailUrl || '') +
        '"' +
        (kind === 'loop' ? ' loop muted' : '') +
        '><source src="' +
        escapeHtml(work.srcUrl || '') +
        '">' +
        (work.captionsUrl
          ? '<track kind="captions" src="' +
            escapeHtml(work.captionsUrl) +
            '" srclang="en" label="English">'
          : '') +
        '</video></div>';
    } else {
      media =
        '<div class="ai-art-detail__media" style="--ai-art-aspect:' +
        w +
        ' / ' +
        h +
        '"><img src="' +
        escapeHtml(work.srcUrl || work.thumbnailUrl || '') +
        '" alt="' +
        escapeHtml(work.alt || '') +
        '" width="' +
        w +
        '" height="' +
        h +
        '"></div>';
    }

    var tools = listHtml(work.tools, function (tool) {
      var bits = [tool.name];
      if (tool.model) bits.push(tool.model);
      if (tool.role) bits.push('(' + tool.role + ')');
      return escapeHtml(bits.join(' — '));
    });

    var humans = listHtml(work.humanContribution, function (item) {
      return escapeHtml(item);
    });

    var credits = listHtml(work.credits, function (item) {
      var label = escapeHtml(item.label || '');
      var value = escapeHtml(item.value || '');
      if (item.url) {
        return (
          label +
          ': <a href="' +
          escapeHtml(item.url) +
          '" rel="noopener noreferrer">' +
          value +
          '</a>'
        );
      }
      return label + ': ' + value;
    });

    detailBody.innerHTML =
      media +
      '<h2 id="ai-art-detail-title" class="ai-art-detail__title pixel-font">' +
      escapeHtml(work.title || '') +
      '</h2>' +
      '<div class="ai-art-detail__body">' +
      '<p>' +
      escapeHtml(work.description || '') +
      '</p>' +
      (work.artistNote
        ? '<h3 class="pixel-font">Artist note</h3><p>' +
          escapeHtml(work.artistNote) +
          '</p>'
        : '') +
      '<h3 class="pixel-font">Tools / models</h3>' +
      (tools || '<p>Not disclosed for this work.</p>') +
      '<h3 class="pixel-font">Human contribution</h3>' +
      (humans || '<p>Documented on publish.</p>') +
      (work.promptExcerpt
        ? '<h3 class="pixel-font">Prompt excerpt</h3><p>' +
          escapeHtml(work.promptExcerpt) +
          '</p>'
        : '') +
      (work.audioDescription
        ? '<h3 class="pixel-font">Audio / visual description</h3><p>' +
          escapeHtml(work.audioDescription) +
          '</p>'
        : '') +
      (work.transcript
        ? '<h3 class="pixel-font">Transcript</h3><p>' +
          escapeHtml(work.transcript) +
          '</p>'
        : '') +
      (credits ? '<h3 class="pixel-font">Credits</h3>' + credits : '') +
      (work.contentCredentialsUrl
        ? '<p><a class="ai-art-c2pa" href="' +
          escapeHtml(work.contentCredentialsUrl) +
          '" rel="noopener noreferrer">Content Credentials</a></p>'
        : '') +
      '</div>';
  }

  function openWork(slug, push) {
    var work = worksBySlug[slug];
    if (!work || !detail) return;
    renderDetail(work);
    if (typeof detail.showModal === 'function') {
      detail.showModal();
    } else {
      detail.setAttribute('open', '');
    }
    try {
      var url = new URL(window.location.href);
      url.hash = slug;
      if (push) {
        window.history.pushState({ aiArtSlug: slug }, '', url.toString());
      }
    } catch (e) {
      /* ignore */
    }
    if (closeBtn) closeBtn.focus();
  }

  function closeDetail() {
    if (!detail) return;
    var video = detail.querySelector('video');
    if (video) {
      video.pause();
    }
    if (typeof detail.close === 'function') {
      detail.close();
    } else {
      detail.removeAttribute('open');
    }
    try {
      var url = new URL(window.location.href);
      url.hash = '';
      window.history.replaceState({}, '', url.toString());
    } catch (e) {
      /* ignore */
    }
  }

  filterButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setFilter(btn.getAttribute('data-ai-art-filter'), true);
    });
  });

  root.addEventListener('click', function (event) {
    var openBtn = event.target.closest('[data-ai-art-open]');
    if (openBtn) {
      openWork(openBtn.getAttribute('data-ai-art-open'), true);
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeDetail);
  }

  if (detail) {
    detail.addEventListener('cancel', function (event) {
      event.preventDefault();
      closeDetail();
    });
    detail.addEventListener('click', function (event) {
      if (event.target === detail) closeDetail();
    });
  }

  window.addEventListener('popstate', function () {
    setFilter(currentFilterFromUrl(), false);
    var hash = (window.location.hash || '').replace(/^#/, '');
    if (hash && worksBySlug[hash]) {
      openWork(hash, false);
    } else if (detail && detail.open) {
      closeDetail();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && detail && detail.open) {
      closeDetail();
    }
  });

  setFilter(currentFilterFromUrl(), false);
  var initialHash = (window.location.hash || '').replace(/^#/, '');
  if (initialHash && worksBySlug[initialHash]) {
    openWork(initialHash, false);
  }
})();
