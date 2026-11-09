'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const ROOT = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

function loadStation() {
  const win = { document: { createElement: () => ({ getContext: () => ({
    fillRect() {}, clearRect() {}, fillText() {}
  }) }) } };
  vm.runInNewContext(read('js/gastown-waterfront-station.js'), { window: win, Math, Number });
  return win.GastownWaterfrontStation;
}

test('Cordova central doorway leads into a bounded public hall and back outside', () => {
  const station = loadStation();
  for (const z of [5.0, 2.0, 0, -4, -10, -13.2]) {
    const point = station.toWorld(world, 0, z);
    assert.equal(station.isWalkableInterior(world, point), true, 'walkable at local z=' + z);
  }
  assert.equal(station.isWalkableInterior(world, station.toWorld(world, 0, -15)), false);
  assert.equal(station.isWalkableInterior(world, station.toWorld(world, 8, -6)), false);
  assert.equal(station.isWalkableInterior(world, station.toWorld(world, 0, 8)), false);
  assert.equal(station.isWalkableForecourt(world, station.toWorld(world, 0, 6)), true);
});

test('station hall geometry replaces solid central mass while preserving exterior', () => {
  const s = read('js/gastown-waterfront-station.js');
  assert.match(s, /CPR station hall upper masonry/);
  assert.match(s, /CPR station hall rear masonry/);
  assert.match(s, /Waterfront public concourse tiled floor/);
  assert.match(s, /Concourse stone floor joint/);
  assert.match(s, /Historic concourse pendant glow/);
  assert.match(s, /root\.userData\.interiorApproximate = true/);
  assert.match(s, /root\.userData\.publicHallOnly = true/);
  assert.match(s, /if \(i !== 1\)/);
  assert.doesNotMatch(s, /box\('Red-brick CPR station body', 0, 8\.1, -8\.5, 106/);
});

test('world bounds admit the interior and keep wall collision local', () => {
  const sim = read('js/gastown-sim.js');
  assert.match(sim, /stationModel\.isWalkableInterior\(state\.world, point\)/);
  assert.match(sim, /stationModel\.isWalkableInterior\(state\.world, state\.lastSafePosition\)/);
  assert.match(sim, /Waterfront Station public concourse: passage ends here/);
  assert.match(sim, /player\.position\.copy\(state\.lastSafePosition\)/);
});

test('heritage concourse includes photo-reference architectural rhythm', () => {
  const src = read('js/gastown-waterfront-station.js');
  for (const feature of [
    'Waterfront ceiling longitudinal coffer rib',
    'Waterfront ceiling transverse coffer rib',
    'Waterfront recessed blue-grey coffer panel',
    'Concourse rose frieze',
    'Waterfront hall Ionic column',
    'Concourse terrazzo longitudinal border',
    'Concourse terrazzo cross border',
    'Historic concourse arcade arch',
    'Waterfront historic arcade clock surround'
  ]) {
    assert.ok(src.includes(feature), feature + ' must be authored');
  }
  assert.match(src, /root\.userData\.ornamentProcedural = true/);
  assert.doesNotMatch(src, /box\('Public concourse rear wayfinding wall'/);
});

test('central entrance has no opaque stiles in the middle of walking route', () => {
  const src = read('js/gastown-waterfront-station.js');
  assert.match(src, /i === 1 \? Math\.sign\(dx\) \* 1\.02/);
  assert.match(src, /i === 1 \? Math\.sign\(dx\) \* 1\.1/);
  assert.doesNotMatch(src, /Entry gate stile', x \+ dx \* 0\.1/);
});

test('Angel of Victory is a distinct named historical memorial east of station entrance', () => {
  const src = read('js/gastown-waterfront-station.js');
  assert.match(src, /Angel of Victory \/ Coeur de Lion MacCarthy/);
  assert.match(src, /memorial\.position\.set\(40\.5, 0, 11\.0\)/);
  assert.match(src, /Angel of Victory raised bronze wing/);
  assert.match(src, /Fallen soldier supported torso/);
  assert.match(src, /Angel of Victory memorial plaque/);
  assert.match(src, /root\.userData\.memorialIncluded = true/);
  assert.match(src, /ArtworkId=24/);
  assert.match(src, /fidelity:'original, approximate procedural interpretation'/);
});
