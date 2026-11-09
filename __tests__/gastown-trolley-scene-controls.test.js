'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8');

test('Vancouver electric trolley art has paired roof collectors and a local route label', () => {
  const src = read('js/gastown-street-life.js');
  assert.match(src, /Route 3 Waterfront electric trolley/);
  assert.match(src, /3 WATERFRONT/);
  assert.match(src, /for \(const x of \[-0\.53,0\.53\]\)/);
  assert.match(src, /CylinderGeometry\(radius,radius,length,6\)/);
  assert.match(src, /bike rack distinguish/);
  assert.doesNotMatch(src, /50 WATERFRONT/);
});

test('scene settings are inside the fullscreen minimap with unique select bindings', () => {
  const html = read('page-gastown-sim.php');
  const controls = html.slice(html.indexOf('<aside class="gastown-minimap"'), html.indexOf('</aside>'));
  for (const key of ['time-of-day', 'weather', 'mood']) {
    assert.equal((html.match(new RegExp('name="'+key+'"', 'g')) || []).length, 1);
    assert.match(controls, new RegExp('name="'+key+'"'));
  }
  assert.match(controls, /data-scene-options/);
  assert.match(read('js/gastown-sim.js'), /\[name="weather"\]/);
});
