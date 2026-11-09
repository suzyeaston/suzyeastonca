'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');

test('third-person camera applies state pitch rather than a fixed portrait-only pose', () => {
  const src=read('js/gastown-sim.js');
  const method=src.slice(src.indexOf('function updateThirdPersonCameraPose(force) {'),
    src.indexOf('function updateSuzyCameraButtons()'));
  assert.match(method,/portraitPitch \+ state\.pitch \* 0\.75/);
  assert.match(method,/camera\.rotation\.set\(pitch/);
  assert.match(src,/const dy = locked \? event\.movementY : event\.clientY - lookDrag\.y/);
  assert.match(src,/adjustPitch\(-\(dy \* sensitivity\)\)/);
});

test('photo button and P keyboard save scene PNG without screen overlays', () => {
  const js=read('js/gastown-sim.js');
  const html=read('page-gastown-sim.php');
  assert.equal((html.match(/data-action="scene-photo"/g)||[]).length,2);
  assert.match(js,/function takeScenePhoto\(\)/);
  assert.match(js,/renderer\.render\(scene, camera\)/);
  assert.match(js,/renderer\.domElement\.toDataURL\('image\/png'\)/);
  assert.match(js,/link\.download = filename/);
  assert.match(js,/if \(event\.code === 'KeyP' && !event\.repeat/);
  assert.match(js,/photoBtns\.forEach\(button => button\.addEventListener\('click', takeScenePhoto\)\)/);
});

test('review-inspired dialogue is original, location-specific and keeps conditional variants', () => {
  const d=JSON.parse(read('assets/dialog/gastown.json'));
  assert.match(d.tourist_clock_stop.lines.join(' '),/toot/);
  assert.match(d.tourist_clock_photo.lines.join(' '),/jackets/);
  assert.match(d.parking_high_five.lines.join(' '),/ticket/i);
  assert.ok(d.parking_small_talk.variants.some(v=>v.when.weather==='rain'));
  assert.match(d.pedestrian_clock_hint.lines.join(' '),/1977/);
});
