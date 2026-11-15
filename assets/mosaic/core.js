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
  // Musical timing: the transport and loop recorder use the same bar grid.
  function barSeconds(bpm) {
    const tempo = Number(bpm);
    return Number.isFinite(tempo) && tempo >= 40 && tempo <= 300 ? 240 / tempo : 2;
  }
  function nextBarBoundary(elapsed, bpm) {
    const bar = barSeconds(bpm);
    if (!Number.isFinite(elapsed) || elapsed < 0) return bar;
    // Always allow a meaningful positive bar when the musician presses Stop.
    return Math.max(bar, Math.ceil((elapsed + .005) / bar) * bar);
  }
  function stopAtNextBar(elapsed, bpm) {
    return Math.max(0, nextBarBoundary(elapsed,bpm)-Math.max(0,elapsed));
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
  // Put widely accepted MP4/H.264/AAC combinations ahead of browser-native WebM.
  // A generic MP4 request is less certain about codecs, so comes after explicit
  // H.264 + AAC requests. Never relabel a WebM with an .mp4 extension.
  const SOCIAL_VIDEO_MIMES = Object.freeze([
    'video/mp4;codecs=avc1.424028,mp4a.40.2',
    'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
    'video/mp4;codecs=avc1.64001F,mp4a.40.2',
    'video/mp4',
    'video/webm;codecs=vp8,opus',
    'video/webm;codecs=vp9,opus',
    'video/webm',
  ]);
  function chooseSocialVideoMime(supported) {
    return chooseMime(SOCIAL_VIDEO_MIMES, supported);
  }
  function videoExtension(mime) {
    if (/^video\/mp4(?:;|$)/i.test(mime || '')) return 'mp4';
    if (/^video\/webm(?:;|$)/i.test(mime || '')) return 'webm';
    return '';
  }
  function safeFileName(name) {
    return String(name || 'mosaic').trim().replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'mosaic';
  }
  return { DURATIONS, formatTime, clamp, columnsFor, expectedPosition, barSeconds, nextBarBoundary, stopAtNextBar, canChangeGrid, sessionSeconds, targetProgress, chooseMime, SOCIAL_VIDEO_MIMES, chooseSocialVideoMime, videoExtension, safeFileName };
});
