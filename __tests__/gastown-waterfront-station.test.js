const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const ROOT = path.resolve(__dirname, '..');

function loadModel() {
  const paint = { fillRect() {}, clearRect() {}, fillText() {} };
  const canvas = { width: 0, height: 0, getContext: () => paint };
  const window = { document: { createElement: () => canvas } };
  const source = fs.readFileSync(path.join(ROOT, 'js/gastown-waterfront-station.js'), 'utf8');
  vm.runInNewContext(source, { window, Math, Number }, { filename: 'gastown-waterfront-station.js' });
  return { station: window.GastownWaterfrontStation, canvas };
}

function threeStub() {
  class Vector {
    set(x, y, z) { this.x = x; this.y = y; this.z = z; return this; }
  }
  class Shape {
    moveTo() {}
    lineTo() {}
    absarc() {}
  }
  class Material {
    constructor(values) { this.values = values; }
  }
  class Geometry {
    constructor(...args) { this.args = args; }
  }
  class Mesh {
    constructor(geometry, material) {
      this.geometry = geometry; this.material = material;
      this.position = new Vector(); this.rotation = {}; this.scale = new Vector();
      this.name = ''; this.userData = {};
    }
  }
  class Group {
    constructor() {
      this.children = [];
      this.position = new Vector();
      this.rotation = {};
      this.userData = {};
    }
    add(child) { this.children.push(child); }
  }
  class CanvasTexture {
    constructor(canvas) { this.canvas = canvas; this.repeat = { set() {} }; }
  }
  return {
    Group, Mesh, Shape, CanvasTexture,
    BoxGeometry: Geometry, CylinderGeometry: Geometry,
    SphereGeometry: Geometry, TorusGeometry: Geometry,
    PlaneGeometry: Geometry, ShapeGeometry: Geometry,
    MeshStandardMaterial: Material, MeshBasicMaterial: Material,
    RepeatWrapping: 1000, SRGBColorSpace: 3001, DoubleSide: 2,
  };
}

const world = {
  spawn: { x: -16.48, y: 1.7, z: -57.65, yaw: -0.8704 },
  route: { centerline: [{ x: -16.48, z: -57.65 }, { x: 2.49, z: -77.34 }] },
};

test('station is staged in front of the player, not over the starting point', () => {
  const { station } = loadModel();
  const anchor = station.anchorFor(world);
  assert.ok(Math.abs(Math.hypot(anchor.x - world.spawn.x, anchor.z - world.spawn.z) - 13) < 0.001);
  const arrivalYaw = station.arrivalYaw(world);
  const forward = { x: -Math.sin(arrivalYaw), z: -Math.cos(arrivalYaw) };
  const direction = { x: (anchor.x - world.spawn.x) / 13, z: (anchor.z - world.spawn.z) / 13 };
  assert.ok((forward.x * direction.x + forward.z * direction.z) > 0.999, 'player faces station frontage');
});

test('reference-led station exterior contains its distinctive architectural elements', () => {
  const { station } = loadModel();
  const group = station.create(threeStub(), world);
  assert.equal(group.userData.reference.id, 'waterfront-station-exterior-v1');
  const names = group.children.map(child => child.name);
  assert.ok(names.includes('Red-brick CPR station body'));
  assert.equal(names.filter(n => /^Ionic column /.test(n)).length, 8);
  assert.equal(names.filter(n => /^Black dome entrance canopy /.test(n)).length, 3);
  assert.equal(names.filter(n => n === 'Entry gate glass panel').length, 6);
  assert.ok(names.includes('CANADIAN PACIFIC RAILWAY'));
  assert.equal(names.filter(n => n === 'THE STATION').length, 3);
  assert.ok(names.includes('Arched ground-floor glazing'));
  assert.ok(names.includes('Stone entrance landing'));
  assert.ok(names.includes('Transit wayfinding sign'));
});

test('eight local commuters patrol the station exterior with unique walking routes', () => {
  const { station } = loadModel();
  const commuters = station.commutersFor(world);
  assert.equal(commuters.length, 8);
  assert.equal(new Set(commuters.map(npc => npc.id)).size, 8);
  for (const npc of commuters) {
    assert.equal(npc.role, 'commuter');
    assert.equal(npc.behavior, 'route_walk');
    assert.ok(npc.patrol.length >= 3);
    for (const waypoint of npc.patrol) {
      assert.ok(Number.isFinite(waypoint.x) && Number.isFinite(waypoint.z));
      assert.ok(Math.hypot(waypoint.x - world.spawn.x, waypoint.z - world.spawn.z) < 42, 'commuter visible near spawn');
    }
  }
});

test('WordPress loads the station builder before the simulator and deploys the model', () => {
  const functions = fs.readFileSync(path.join(ROOT, 'functions.php'), 'utf8');
  const manifest = fs.readFileSync(path.join(ROOT, 'scripts/theme_deploy_manifest.py'), 'utf8');
  const simulator = fs.readFileSync(path.join(ROOT, 'js/gastown-sim.js'), 'utf8');
  assert.match(functions, /'se-gastown-waterfront-station'/);
  assert.match(functions, /array\( 'three-js', 'howler-js', 'se-gastown-world-loader', 'se-gastown-building-normalizer', 'se-gastown-dialog', 'se-gastown-waterfront-station' \)/);
  assert.ok(manifest.includes('"js/gastown-waterfront-station.js"'));
  assert.match(simulator, /stationModel\.create\(THREE, world\)/);
  assert.match(simulator, /stationModel\.commutersFor\(world\)/);
  assert.match(simulator, /stationModel\.arrivalYaw\(state\.world, spawn\)/);
  assert.match(simulator, /npc\.role === 'commuter'/);
});
