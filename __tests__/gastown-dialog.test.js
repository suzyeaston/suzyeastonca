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
