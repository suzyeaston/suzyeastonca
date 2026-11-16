'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = p => fs.readFileSync(path.join(__dirname, '..', p), 'utf8');
const streetLife = require('../js/gastown-street-life.js');

class Group {
  constructor() {
    this.children = [];
    this.position = { x: 0, y: 0, z: 0, set(x, y, z) { this.x = x; this.y = y; this.z = z; } };
    this.rotation = { x: 0, y: 0, z: 0 };
    this.userData = {};
    this.visible = true;
  }
  add(mesh) { this.children.push(mesh); }
}
class Mesh extends Group { constructor(geometry, material) { super(); this.geometry = geometry; this.material = material; } }
class Geometry { constructor(...args) { this.args = args; } }
class Material { constructor(options) { Object.assign(this, options); } }
const THREE = {
  Group, Mesh, BoxGeometry: Geometry, CylinderGeometry: Geometry, SphereGeometry: Geometry,
  MeshBasicMaterial: Material, MeshStandardMaterial: Material,
};

test('Station East creates moving road and parking traffic without placeholder pillars', () => {
  const world = JSON.parse(read('assets/world/gastown-water-street.json'));
  const scene = streetLife.create(THREE, world);
  const buses = scene.root.children.filter(node => /Illustrated Route 3 Waterfront electric trolley/.test(node.name));
  const cars = scene.root.children.filter(node => /moving street car/.test(node.name));
  const lotCars = scene.root.children.filter(node => /Station East arrival/.test(node.name));
  assert.equal(buses.length, 2);
  assert.equal(cars.length, 4);
  assert.equal(lotCars.length, 1);
  assert.equal(scene.root.children.length, 11,
    'Cordova traffic, Harbour Centre stop, and three separate Water Street cars');
  assert.equal(scene.root.children.filter(node=>/Water Street one-way westbound car/.test(node.name)).length,3);
  assert.ok(scene.root.children.some(node => node.name === 'Harbour Centre eastbound bus stop'));
  const before = cars.map(car => [car.position.x,car.position.z]);
  const lotBefore = [lotCars[0].position.x,lotCars[0].position.z];
  scene.update(5.0);
  assert.ok(cars.some((car,i) => Math.hypot(car.position.x-before[i][0],car.position.z-before[i][1]) > 0.01));
  assert.ok(Math.hypot(lotCars[0].position.x-lotBefore[0],lotCars[0].position.z-lotBefore[1]) > 0.01);
  assert.equal(scene.startAudio(), false, 'browser audio cannot auto-start in Node');
  assert.doesNotThrow(() => scene.stopAudio());
  assert.ok(scene.root.children.every(node => !/lantern|pillar|obelisk/i.test(node.name)));
});

test('sound is optional, user-activated and synthetic, never a foreign voice asset', () => {
  const street = read('js/gastown-street-life.js');
  const sim = read('js/gastown-sim.js');
  const php = read('functions.php');
  const page = read('page-gastown-sim.php');
  const css = read('assets/css/gastown-sim.css');
  assert.match(street, /function makeLocalAmbience\(/);
  assert.match(street, /window\.AudioContext \|\| window\.webkitAudioContext/);
  assert.match(street, /return \{root,update,startAudio:ambience\.start,stopAudio:ambience\.stop,setWeather:ambience\.setWeather\}/);
  assert.match(sim, /streetLifeScene\.startAudio\(\)/);
  assert.match(sim, /utterance\.voice = voice/);
  assert.match(sim, /\^en\[-_\]CA\$/);
  assert.match(php, /\$gastown_audio_fallbacks = array\(/);
  assert.doesNotMatch(php, /'beds\/commuter_mix'\s*=>\s*'\/assets\/audio\/yvr\/skytrain-rumble/);
  assert.match(page, /data-action="local-ambience-toggle"/);
  assert.match(css, /\.gastown-local-ambience:focus-visible/);
});


test('Suzyfly toggle is discoverable in HUD, tutorial, and controls without capturing mouse',()=>{
 const page=read('page-gastown-sim.php');
 const sim=read('js/gastown-sim.js');
 const css=read('assets/css/gastown-sim.css');
 assert.match(page,/data-sim-flight-tip/);
 assert.match(page,/Type <kbd>SUZYFLY<\/kbd>/);
 assert.match(page,/type it again to land vertically/i);
 assert.match(page,/<strong>suzyfly<\/strong>/);
 assert.match(sim,/const flightTipEl = app\.querySelector\('\[data-sim-flight-tip\]'\)/);
 assert.match(sim,/function updateFlightTip\(\)/);
 assert.match(sim,/fly\.enabled=true;fly\.altitude/);
 assert.match(sim,/if\(fly\.enabled\)/);
 assert.match(sim,/beginFlightLanding\(\)/);
 assert.match(sim,/updateFlightTip\(\)/);
 assert.match(css,/\.gastown-flight-tip\s*\{[^}]*pointer-events:\s*none/);
 assert.match(css,/\.gastown-flight-tip\[data-flying="true"\]/);
});


test('instructions distinguish arrow-key turning from WASD sidestepping',()=>{
  const page=read('page-gastown-sim.php');
  const sim=read('js/gastown-sim.js');
  assert.match(page,/Arrow keys:<\/strong> ↑ forward, ↓ backward, ← turn left, → turn right/);
  assert.match(page,/WASD:<\/strong> W forward, S backward, A sidestep left, D sidestep right/);
  assert.match(page,/A\/D do not turn Suzy/);
  assert.match(page,/Both control schemes work while flying/);
  assert.match(page,/data-sim-pointer-hint>Click to look · ↑\/↓ move · ←\/→ turn · W\/S move · A\/D sidestep/);
  assert.match(sim,/Mouse captured · ↑\/↓ move · ←\/→ turn · W\/S move · A\/D sidestep/);
  assert.match(sim,/if \(code === 'KeyW' \|\| code === 'ArrowUp'\) state\.move\.forward = pressed/);
  assert.match(sim,/if \(code === 'KeyS' \|\| code === 'ArrowDown'\) state\.move\.backward = pressed/);
  assert.match(sim,/if \(code === 'KeyA'\) state\.move\.left = pressed/);
  assert.match(sim,/if \(code === 'KeyD'\) state\.move\.right = pressed/);
  assert.match(sim,/if \(code === 'ArrowLeft'\) state\.turn\.left = pressed/);
  assert.match(sim,/if \(code === 'ArrowRight'\) state\.turn\.right = pressed/);
  assert.match(page,/<strong>Space bar<\/strong> to ascend/);
});
