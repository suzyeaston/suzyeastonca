const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const streetLife = require('../js/gastown-street-life.js');
const read = (file) => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');

test('Station East has reproducible walking characters and two interaction types', () => {
  const world = {
    parkingLot: { center: { x: 46, z: 56.57 } },
    route: { centerline: [{x:-38,z:36},{x:-25,z:49},{x:-12,z:62}] }
  };
  const station = { sideEntranceFor: () => ({ approach: { x: 24, z: 53 } }) };
  const people = streetLife.peopleFor(world, station);
  assert.equal(people.length, 6);
  assert.equal(people.filter(p => p.voiceCue === 'parking-smalltalk').length, 2);
  assert.equal(people.filter(p => p.voiceCue === 'parking-highfive').length, 2);
  for (const person of people) {
    assert.ok(Array.isArray(person.patrol) && person.patrol.length >= 2);
    assert.equal(person.role, 'commuter');
    assert.ok(person.patrol.every(point => Number.isFinite(point.x) && Number.isFinite(point.z)));
  }
  assert.deepEqual(streetLife.peopleFor(world, station), people);
  assert.deepEqual(streetLife.peopleFor(null, station), []);
});

test('the simulator includes the authored street life and never draws generic parking/heritage landmark towers', () => {
  const src = read('js/gastown-sim.js');
  const php = read('functions.php');
  assert.match(src, /streetLife\.create\(THREE, world\)/);
  assert.match(src, /streetLifeScene\.update\(time \/ 1000\)/);
  assert.match(src, /lotWalkers\.concat\(stationCommuters, world\.npcs \|\| \[\]\)/);
  assert.match(src, /'view_axis', 'parking', 'heritage_frontage'/);
  assert.match(php, /'se-gastown-street-life'/);
  assert.match(src, /'parking-highfive'/);
  assert.match(src, /'parking-smalltalk'/);
});

test('scroll-to-zoom affects the playable camera and minimap, with Alt+wheel reserved for pitch', () => {
  const src = read('js/gastown-sim.js');
  const wheel = src.slice(src.indexOf('function onWheelLook(event) {'), src.indexOf('function setMoveKey(',src.indexOf('function onWheelLook(event) {')));
  assert.match(wheel, /setMinimapZoom\(minimapState\.zoom/);
  assert.match(wheel, /event\.altKey/);
  assert.match(src, /minimapCanvas\.addEventListener\('wheel', onWheelLook, \{ passive: false \}\)/);
  assert.match(src, /updateThirdPersonCameraPose\(true\)/);
});

test('the authored characters have locally available dialog even without online voice or conversation endpoints', () => {
  const dialogs = JSON.parse(read('assets/dialog/gastown.json'));
  for (const key of ['parking_small_talk', 'parking_high_five']) {
    assert.ok(dialogs[key]);
    assert.ok(dialogs[key].lines.length >= 2);
  }
  const src = read('js/gastown-sim.js');
  assert.match(src, /enableNpcVoiceSnippets: true/);
  assert.match(src, /High five!/);
  assert.match(src, /high-five successfully/i);
});
