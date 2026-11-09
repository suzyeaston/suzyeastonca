'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = p => fs.readFileSync(path.join(__dirname, '..', p), 'utf8');

test('WebGL fills the entire responsive camera viewport including fullscreen', () => {
  const css = read('assets/css/gastown-sim.css');
  const sim = read('js/gastown-sim.js');
  assert.match(css, /\.gastown-sim-canvas\s*\{[^}]*height:\s*clamp\(/s);
  assert.match(css, /\.gastown-sim-canvas\s*>\s*canvas\s*\{[^}]*height:\s*100%\s*!important/s);
  assert.match(sim, /canvasWrap\.clientHeight/);
  assert.match(sim, /renderer\.setSize\(width, height, false\)/);
  assert.match(sim, /ResizeObserver/);
  assert.doesNotMatch(sim, /Math\.min\(window\.innerHeight\s*\*\s*0\.72/);
});

test('film cameras never insert big generic landmark pillars at the Station East lot', () => {
  const sim = read('js/gastown-sim.js');
  const street = read('js/gastown-street-life.js');
  const start = sim.indexOf('function addLandmarks(world)');
  const end = sim.indexOf('function addDebugRoute(world)', start);
  assert.ok(start >= 0 && end > start);
  assert.match(sim.slice(start,end), /if \(!state\.debugEnabled\) return;/);
  assert.doesNotMatch(street, /Station East lantern/);
  assert.doesNotMatch(street, /lot\.center\.x\s*\+\s*9/);
});

test('The Landing is modeled from actual perimeter edges with Water Street arches and lab placard', () => {
  const src = read('js/gastown-waterfront-places.js');
  assert.match(src, /pts\[edge\],pts\[\(edge\+1\)%pts\.length\]/);
  assert.match(src, /new THREE\.TorusGeometry\(1\.38/);
  assert.match(src, /MULTIMODAL MEDIA LAB/);
  assert.match(src, /The Landing warehouse sash window/);
  assert.match(src, /Limestone window lintel/);
});

test('staged pedestrians are in the accessible lot aisle and two buses remain in circuit', () => {
  const life = require('../js/gastown-street-life.js');
  const world = JSON.parse(read('assets/world/gastown-water-street.json'));
  const station = { sideEntranceFor() { return { approach: {x:22,z:59} }; } };
  const people = life.peopleFor(world,station);
  assert.equal(people.length,6);
  assert.equal(new Set(people.map(p=>p.id)).size,6);
  assert.ok(people.every(p => p.patrol.length >= 2));
  const src = read('js/gastown-street-life.js');
  assert.match(src, /buses=\[makeBus\(THREE\),makeBus\(THREE\)\]/);
  assert.match(src, /const d=\(seconds\*4\.1\+20\+\(index\*total\/2\)\)%total/);
  const sim = read('js/gastown-sim.js');
  assert.match(sim, /\['station pedestrians', addNpcs\]/);
  assert.match(sim, /streetLifeScene\.update\(time \/ 1000\)/);
});
