'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const harbourSource = fs.readFileSync(
  path.join(__dirname, '..', 'js', 'gastown-harbour-vista.js'), 'utf8'
);

test('Waterfront rail yard retains its 20-track model and fenced context', () => {
  assert.match(harbourSource, /root\.userData\.yardTrackCount\s*=\s*20/);
  assert.match(harbourSource, /Fenced waterfront rail service yard \(non-walkable\)/);
});

test('Near-field sleepers use instancing instead of sparse 24-metre blocks', () => {
  assert.match(harbourSource, /new THREE\.InstancedMesh\(/);
  assert.match(harbourSource, /const tieSpacingMetres = 0\.72/);
  assert.match(harbourSource, /root\.userData\.nearTrackTieCount = tieIndex/);
  assert.doesNotMatch(harbourSource, /for\s*\(let t=-64;t<=64;t\+=24\)/);
});

test('Rail heads and webs remain distinct from ballast', () => {
  assert.match(harbourSource, /Yard rail pair/);
  assert.match(harbourSource, /CP N-Yard steel rail web/);
  assert.match(harbourSource, /CP N-Yard track ballast/);
});
