(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
    return;
  }

  root.GastownDialog = factory();
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const DEFAULT_FALLBACK_LINE = 'Dialog data unavailable.';
  const DEFAULT_MISSING_DIALOG_LINE = 'This guide does not have dialog copy yet.';

  function sanitizeText(value, fallback) {
    const trimmed = typeof value === 'string' ? value.trim() : '';
    return trimmed || fallback;
  }

  function sanitizeRelativeHref(href) {
    if (typeof href !== 'string') {
      return '';
    }

    const trimmed = href.trim();
    if (!trimmed || /^[a-z][a-z\d+.-]*:/i.test(trimmed) || trimmed.startsWith('//')) {
      return '';
    }

    if (!trimmed.startsWith('/') && !trimmed.startsWith('./') && !trimmed.startsWith('../') && !trimmed.startsWith('#') && !trimmed.startsWith('?')) {
      return '';
    }

    return trimmed;
  }

  function normalizeDialogEntry(entry, options) {
    const settings = options && typeof options === 'object' ? options : {};
    const fallbackTitle = sanitizeText(settings.fallbackTitle, 'Gastown guide');
    const unavailableLine = sanitizeText(settings.unavailableLine, DEFAULT_FALLBACK_LINE);
    const missingLine = sanitizeText(settings.missingLine, DEFAULT_MISSING_DIALOG_LINE);
    const defaultCloseLabel = sanitizeText(settings.defaultCloseLabel, 'Back to walk');
    const hasSourceEntry = entry && typeof entry === 'object' && !Array.isArray(entry);

    const title = hasSourceEntry ? sanitizeText(entry.title, fallbackTitle) : fallbackTitle;
    const sourceLines = hasSourceEntry && Array.isArray(entry.lines) ? entry.lines : [];
    const lines = sourceLines
      .map((line) => sanitizeText(line, ''))
      .filter(Boolean);

    const normalizedLines = lines.length
      ? lines
      : [hasSourceEntry ? missingLine : unavailableLine];

    const sourceActions = hasSourceEntry && Array.isArray(entry.actions) ? entry.actions : [];
    const actions = sourceActions.reduce((list, action) => {
      if (!action || typeof action !== 'object') {
        return list;
      }

      const type = sanitizeText(action.type, '').toLowerCase();
      const label = sanitizeText(action.label, type === 'close' ? defaultCloseLabel : 'Open link');

      if (type === 'close') {
        list.push({ type: 'close', label });
        return list;
      }

      if (type === 'link') {
        const href = sanitizeRelativeHref(action.href);
        if (!href) {
          return list;
        }

        list.push({ type: 'link', label, href });
      }

      return list;
    }, []);

    return {
      title,
      lines: normalizedLines,
      actions,
      hasCustomActions: actions.length > 0,
    };
  }

  return {
    DEFAULT_FALLBACK_LINE,
    DEFAULT_MISSING_DIALOG_LINE,
    normalizeDialogEntry,
    sanitizeRelativeHref,
  };
});
