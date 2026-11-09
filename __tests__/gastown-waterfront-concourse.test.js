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
  assert.equal(station.isWalkableInterior(world, station.toWorld(world, 0, -30)), true);
  assert.equal(station.isWalkableInterior(world, station.toWorld(world, 0, -32)), false);
  assert.equal(station.isWalkableInterior(world, station.toWorld(world, 8, -6)), true);
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

test('expanded hall and Station East public corridor form one traversable route', () => {
  const station = loadStation();
  const side = station.sideEntranceFor(world);
  assert.ok(side.interior, 'side entrance has a public hall destination');
  assert.equal(station.isWalkableInterior(world, side.interior), true);
  // Sample an actual continuous pedestrian centreline across the side doorway,
  // east connector and the central concourse. A teleport alone is insufficient.
  for (let x = 55.5; x >= 0; x -= 0.25) {
    assert.equal(
      station.isWalkableInterior(world, station.toWorld(world, x, -4)),
      true, 'east access blocked at local x=' + x
    );
  }
  for (let z = -4; z >= -30; z -= 0.25) {
    assert.equal(
      station.isWalkableInterior(world, station.toWorld(world, 0, z)),
      true, 'hall blocked at local z=' + z
    );
  }
  for (const point of [[60, -4], [38, -10], [22, -12], [0, -33], [-22, -4]]) {
    assert.equal(station.isWalkableInterior(world, station.toWorld(world, ...point)), false);
  }
});

test('a generously proportioned hall replaces the formerly shallow narrow stage', () => {
  const src = read('js/gastown-waterfront-station.js');
  assert.match(src, /interiorHallDimensions = \{ width: 44\.4, depth: 35\.7, ceiling: 14\.45 \}/);
  assert.match(src, /Waterfront east entrance passage floor/);
  assert.match(src, /East wing corridor header/);
  assert.match(src, /Waterfront east connector plaster ceiling/);
  assert.match(src, /Station East open leaf glass door/);
  assert.doesNotMatch(src, /Station East side entrance glass double doors/);
  assert.match(src, /eastEntryConnectsToHall = true/);
  const runtime = read('js/gastown-sim.js');
  assert.match(runtime, /const destination = doorway\.interior/);
  assert.match(runtime, /Entered the Waterfront Station public concourse from Station East/);
  assert.doesNotMatch(runtime, /exterior navigation shortcut to Cordova/);
});

test('trolley side advert is an original MAKE ART NOT WAR local canvas, livery unchanged', () => {
  const src = read('js/gastown-street-life.js');
  assert.match(src, /MAKE ART NOT WAR trolley side advertisement/);
  assert.match(src, /ctx\.fillText\('MAKE ART NOT WAR'/);
  assert.match(src, /root\.userData\.advertiser = 'MAKE ART NOT WAR'/);
  assert.match(src, /color:0x0866ae/);
  assert.match(src, /color:0xc7d1d4/);
});

test('covered station concourse suppresses rain while outdoor drizzle equips Suzy', () => {
  const runtime=read('js/gastown-sim.js');
  const avatar=read('js/gastown-suzy-avatar.js');
  assert.match(runtime,/function isPlayerInsideWaterfront\(\)/);
  assert.match(runtime,/rainGroup\.visible = !state\.landingLab\.inside && !isPlayerInsideWaterfront\(\)/);
  assert.match(runtime,/if \(!rainGroup\.visible\) return;/);
  assert.match(runtime,/wet && !isPlayerInsideWaterfront\(\)/);
  assert.match(runtime,/state\.activeWeather === 'drizzle'/);
  assert.match(avatar,/Suzy upright Vancouver rain umbrella/);
  assert.match(avatar,/Umbrella upright rain canopy/);
  assert.match(avatar,/umbrella\.visible=false/);
  assert.match(avatar,/setUmbrellaWeather/);
});

test('Station East arcade uses light plaster arches and a proper glazed transom', () => {
  const station = read('js/gastown-waterfront-station.js');
  for (const name of [
    'Station East arched arcade cream inner wall',
    'Waterfront Station East cream passage arch',
    'Station East arcade jamb',
    'Station East green transom mullion',
    'Station East entrance glazed transom',
    'Station East deep green door stile'
  ]) assert.ok(station.includes(name), name);
  assert.match(station,/sideEntranceStyle='cream arched arcade, dark green framed glazing'/);
});
