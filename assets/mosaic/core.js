/* Pure utilities, shared with the Node tests. No dependencies. */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.MosaicCore = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';
  const DURATIONS = Object.freeze([0, 15, 30, 60, 120]);
  function formatTime(seconds) {
    const safe = Math.max(0, Math.floor(Number.isFinite(seconds) ? seconds : 0));
    return `${String(Math.floor(safe / 60)).padStart(2, '0')}:${String(safe % 60).padStart(2, '0')}`;
  }
  function clamp(value, min, max) {
    const numeric = Number(value);
    return Math.min(max, Math.max(min, Number.isFinite(numeric) ? numeric : min));
  }
  function columnsFor(count) { return count === 9 ? 3 : 2; }
  function expectedPosition(elapsed, seconds) {
    if (!Number.isFinite(elapsed) || !Number.isFinite(seconds) || seconds <= 0) return 0;
    return ((elapsed % seconds) + seconds) % seconds;
  }
  function canChangeGrid(count, filledIndices) {
    return (count === 4 || count === 9) && filledIndices.every(index => Number.isInteger(index) && index >= 0 && index < count);
  }
  // The session's timeline is derived from recorded media, not a planning target.
  function sessionSeconds(clips) {
    return Math.max(0, ...Array.from(clips || [], clip =>
      Number.isFinite(clip?.seconds) && clip.seconds > 0 ? clip.seconds : 0));
  }
  // A guide may fill up, but it never decides whether a take should stop.
  function targetProgress(elapsed, target) {
    if (!Number.isFinite(target) || target <= 0) return 0;
    return clamp((elapsed / target) * 100, 0, 100);
  }
  function chooseMime(preferred, supported) {
    return preferred.find(type => supported(type)) || '';
  }
  function safeFileName(name) {
    return String(name || 'mosaic').trim().replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'mosaic';
  }
  return { DURATIONS, formatTime, clamp, columnsFor, expectedPosition, canChangeGrid, sessionSeconds, targetProgress, chooseMime, safeFileName };
});
