'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8');

test('side menu contains all visual controls, settings and help while keeping a map', () => {
  const html = read('page-gastown-sim.php');
  const start = html.indexOf('<details class="gastown-side-menu"');
  const end = html.indexOf('<details class="gastown-map-fold"', start);
  assert.ok(start > 0 && end > start);
  const menu = html.slice(start,end);
  for (const key of ['harbour-lookout','rail-yard-view','lot-entrance-view','lot-diagonal-view',
    'suzy-view','suzy-front-view','suzy-back-view','time-of-day','weather','mood',
    'low-graphics','reopen-tutorial','pause','reset','tutorial-open']) {
    assert.ok(menu.includes(key), 'menu includes '+key);
  }
  for (const key of ['time-of-day','weather','mood']) {
    assert.equal((html.match(new RegExp('name="'+key+'"','g'))||[]).length,1);
  }
  assert.match(html,/data-sim-minimap/);
  assert.doesNotMatch(html.slice(0,html.indexOf('data-tutorial-overlay')), /<details class="gastown-controls-drawer"/);
});

test('new dialogue stays grounded in transit and Vancouver weather without canned phrasing', () => {
  const dialog = JSON.parse(read('assets/dialog/gastown.json'));
  assert.match(dialog.parking_small_talk.lines.join(' '), /SeaBus/);
  assert.match(dialog.guide_intro.lines.join(' '), /Musqueam/);
  assert.match(dialog.parking_high_five.lines.join(' '), /High five/);
  assert.ok(dialog.parking_small_talk.variants.some(v=>v.when.weather==='rain'));
  assert.doesNotMatch(dialog.parking_small_talk.lines.join(' '), /Anyway, nice chat/);
});

test('the station east street-facing entry has richer low-poly material detail', () => {
  const src = read('js/gastown-waterfront-station.js');
  assert.match(src,/Station East glazed door warm inner reflection/);
  assert.match(src,/Station East side path expansion joint/);
});
