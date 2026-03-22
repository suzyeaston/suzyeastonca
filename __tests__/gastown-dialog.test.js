const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const { normalizeDialogEntry } = require('../js/gastown-dialog.js');

test('dialog entries without actions still normalize to readable fallback content', () => {
  const entry = normalizeDialogEntry({
    title: 'Street busker',
    lines: ['Local looped audio lives here.'],
  }, {
    fallbackTitle: 'Gastown guide',
    defaultCloseLabel: 'Back to walk',
  });

  assert.equal(entry.title, 'Street busker');
  assert.deepEqual(entry.lines, ['Local looped audio lives here.']);
  assert.equal(entry.actions.length, 0);
  assert.equal(entry.hasCustomActions, false);
});

test('dialog actions are normalized and only safe same-origin relative links survive', () => {
  const entry = normalizeDialogEntry({
    title: 'Gastown guide',
    lines: ['Choose your next stop.'],
    actions: [
      { type: 'close', label: 'Back to walk' },
      { type: 'link', label: 'Visit Waterfront', href: '/gastown-simulator/' },
      { type: 'link', label: 'External', href: 'https://example.com/outside' },
      { type: 'link', label: 'Proto-relative', href: '//evil.example.com' },
    ],
  }, {});

  assert.deepEqual(entry.actions, [
    { type: 'close', label: 'Back to walk' },
    { type: 'link', label: 'Visit Waterfront', href: '/gastown-simulator/' },
  ]);
  assert.equal(entry.hasCustomActions, true);
});

test('invalid external hrefs are rejected and missing entries get a visible fallback line', () => {
  const entry = normalizeDialogEntry(null, {
    fallbackTitle: 'Pedestrian',
    unavailableLine: 'Dialog data unavailable.',
  });

  assert.equal(entry.title, 'Pedestrian');
  assert.deepEqual(entry.lines, ['Dialog data unavailable.']);
  assert.deepEqual(entry.actions, []);
});

test('openDialogForNpc source exits pointer lock before scheduling the modal UI', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');
  const branchNeedle = "if (document.pointerLockElement === renderer.domElement) {";
  const branchStart = src.indexOf(branchNeedle);
  const branchEnd = src.indexOf('return;', branchStart);
  const branch = src.slice(branchStart, branchEnd);

  assert.notEqual(branchStart, -1, 'expected pointer lock branch');
  assert.ok(branch.includes('document.exitPointerLock();'));
  assert.ok(branch.includes('window.setTimeout(showDialog, 0);'));
  assert.ok(branch.indexOf('document.exitPointerLock();') < branch.indexOf('window.setTimeout(showDialog, 0);'));
});


test('Gastown simulator markup starts with dialog and interact prompt hidden', () => {
  const pagePath = path.join(__dirname, '..', 'page-gastown-sim.php');
  const markup = fs.readFileSync(pagePath, 'utf8');

  assert.match(markup, /class="gastown-interact-prompt"[^>]*hidden/);
  assert.match(markup, /class="gastown-dialog-modal"[^>]*aria-hidden="true"[^>]*hidden/);
});

test('Gastown simulator CSS preserves hidden attribute semantics for modal and prompt', () => {
  const cssPath = path.join(__dirname, '..', 'assets', 'css', 'gastown-sim.css');
  const css = fs.readFileSync(cssPath, 'utf8');

  assert.match(css, /\.gastown-interact-prompt\[hidden\]\s*\{[^}]*display:\s*none\s*!important;/s);
  assert.match(css, /\.gastown-dialog-modal\[hidden\]\s*\{[^}]*display:\s*none\s*!important;/s);
});

test('closeDialog source restores hidden state and resumable scene focus cues', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');
  const start = src.indexOf('function closeDialog() {');
  const end = src.indexOf('function openDialogForNpc', start);
  const block = src.slice(start, end);

  assert.notEqual(start, -1, 'expected closeDialog function');
  assert.match(block, /dialogModalEl\.setAttribute\('hidden', 'hidden'\);/);
  assert.match(block, /dialogModalEl\.setAttribute\('aria-hidden', 'true'\);/);
  assert.match(block, /setInteractPrompt\(''\);/);
  assert.match(block, /setStatus\('Dialog closed\. Click scene to resume\.'\);/);
  assert.match(block, /renderer\.domElement \|\| canvasWrap/);
});

test('init path does not auto-open a guide dialog during simulator boot', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');
  const start = src.indexOf('async function init() {');
  const end = src.indexOf('init();', start);
  const block = src.slice(start, end);

  assert.notEqual(start, -1, 'expected init function');
  assert.equal(block.includes('openDialogForNpc('), false);
});

test('Gastown audio setup fails softly when assets are unavailable', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function createSafeHowl\(options\)/);
  assert.match(src, /warnAudioUnavailable\('Gastown audio assets missing or failed to load; simulator continuing without some audio\.'/);
  assert.match(src, /warnAudioUnavailable\('Howler audio unavailable; simulator continuing without ambient audio\.'/);
});
