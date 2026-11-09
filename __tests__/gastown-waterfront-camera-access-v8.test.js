'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

test('Station East side door points toward the real parking lot and connects to walkable frontage', () => {
  const win = {};
  new Function('window', read('js/gastown-waterfront-station.js'))(win);
  const station = win.GastownWaterfrontStation;
  const access = station.sideEntranceFor(world);
  assert.ok(access && access.threshold && access.approach && access.forecourt);
  for (const p of [access.threshold, access.approach, access.forecourt]) {
    assert.ok(Number.isFinite(p.x) && Number.isFinite(p.z));
    assert.equal(station.isWalkableForecourt(world, p), true, 'access point must be on walkable exterior');
  }
  assert.ok(Math.hypot(access.approach.x - world.parkingLot.center.x,
    access.approach.z - world.parkingLot.center.z) <
    Math.hypot(access.forecourt.x - world.parkingLot.center.x,
    access.forecourt.z - world.parkingLot.center.z),
    'the entrance approach must be closer to the parking lot than the Cordova forecourt');
  assert.equal(station.isWalkableForecourt(world, station.toWorld(world, 0, -5)), false,
    'the solid station interior must remain inaccessible');
});

test('parking-side architecture actually draws a doorway, not only a map marker', () => {
  const src = read('js/gastown-waterfront-station.js');
  for (const part of [
    'Station East side entrance glass double doors',
    'Station East entrance stone header',
    'Station East entrance small awning',
    'Station East to Cordova pedestrian connection',
    'Station east entry tactile threshold',
    'root.userData.sideEntrance = sideEntranceFor(world)'
  ]) assert.ok(src.includes(part), part);
});

test('back-follow and face-front camera keep a stable shoulder and bounded eye-level pitch', () => {
  const src = read('js/gastown-sim.js');
  assert.match(src, /const previousShoulder = state.avatarCameraShoulder \|\| 0/);
  assert.match(src, /const oldShoulder = Number\.isFinite\(state\.followCameraShoulder\)/);
  assert.match(src, /Math\.max\(1\.75, Math\.hypot\(offset, boom\)\)/);
  assert.match(src, /Math\.min\(0\.36, Math\.atan2\(/);
  assert.match(src, /intersectObjects\(thirdPersonCameraBlockers, true\)/);
  assert.match(src, /hit\.distance > 0\.06/);
  assert.doesNotMatch(src, /hit\.distance > 0\.72/);
  assert.match(src, /state\.followCameraShoulder=undefined/);
  assert.match(src, /updateThirdPersonCameraPose\(false\)/);
});

test('E interaction connects lot-side doorway with station forecourt and movement hint', () => {
  const src = read('js/gastown-sim.js');
  assert.match(src, /function interactWithStationSideEntrance\(\)/);
  assert.match(src, /if \(interactWithStationSideEntrance\(\)\) \{ event\.preventDefault\(\); return; \}/);
  assert.match(src, /state\.lastSafePosition\.copy\(player\.position\)/);
  assert.match(src, /updateStationSideEntranceHint\(\)/);
  assert.match(src, /Station East side entrance: press E/);
  assert.match(src, /Station side access: arrived at the Cordova entrance/);
});

test('both updated JS scripts parse as valid ECMAScript', () => {
  assert.doesNotThrow(() => new Function('window', read('js/gastown-waterfront-station.js')));
  assert.doesNotThrow(() => new Function(read('js/gastown-sim.js')));
});
