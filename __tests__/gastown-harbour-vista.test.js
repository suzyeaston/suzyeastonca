const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ROOT = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

function threeStub() {
  class Vector {
    set(x, y, z) { this.x = x; this.y = y; this.z = z; }
  }
  class Geometry {
    constructor(...args) { this.args = args; }
    setAttribute(name, attribute) { this[name] = attribute; return this; }
  }
  class Shape {
    moveTo() {} lineTo() {} quadraticCurveTo() {} closePath() {}
  }
  class Material {
    constructor(options) { this.options = options; }
  }
  class Mesh {
    constructor(geometry, material) {
      this.geometry = geometry; this.material = material;
      this.name = ''; this.position = new Vector();
      this.rotation = {}; this.userData = {};
    }
  }
  class Group {
    constructor() {
      this.children = []; this.name = '';
      this.position = new Vector(); this.rotation = {}; this.userData = {};
    }
    add(mesh) { this.children.push(mesh); }
  }
  class CanvasTexture {
    constructor(canvas) { this.canvas = canvas; this.colorSpace = ''; }
  }
  return {
    Group, Mesh, Shape, CanvasTexture,
    MeshStandardMaterial: Material, MeshBasicMaterial: Material, LineBasicMaterial: Material,
    BoxGeometry: Geometry, CylinderGeometry: Geometry, ConeGeometry: Geometry,
    PlaneGeometry: Geometry, ShapeGeometry: Geometry, BufferGeometry: Geometry,
    Float32BufferAttribute: Geometry, LineSegments: Mesh,
    SRGBColorSpace: 'srgb', DoubleSide: 2,
  };
}
function getVista() {
  const canvas = { width: 0, height: 0, getContext: () => ({
    fillRect() {}, clearRect() {}, fillText() {},
  }) };
  const window = { document: { createElement: () => canvas } };
  new Function('window', read('js/gastown-harbour-vista.js'))(window);
  return window.GastownHarbourVista;
}
function flatten(root) {
  const children = [root];
  for(let i=0; i<children.length; i++) {
    children.push(...(children[i].children || []));
  }
  return children;
}
function polygonContains(point, poly) {
  let inside = false;
  for(let i=0,j=poly.length-1;i<poly.length;j=i++){
    const a=poly[i], b=poly[j];
    if((a.z>point.z)!==(b.z>point.z)
      && point.x<((b.x-a.x)*(point.z-a.z))/((b.z-a.z)||1e-10)+a.x) inside=!inside;
  }
  return inside;
}
test('Canada Place is georeferenced north of Waterfront Station, not placed by eye', () => {
  const vista = getVista();
  const pier = vista.positionFor(world);
  assert.ok(Math.abs(pier.x+35.92)<2, 'Canada Place longitude gives expected Vancouver local east-west');
  assert.ok(Math.abs(pier.z+265.33)<2, 'Canada Place latitude gives expected northward distance');
  assert.ok(pier.z<world.station.z-220, 'Canada Place is north of the station');
  assert.ok(pier.x<world.station.x, 'Canada Place is slightly west of the station');
  const reverseNorth = vista.projectScene(-123.1116666667,49.2868333333,world.meta.gis.origin);
  assert.ok(reverseNorth.z<0, 'north is negative scene Z');
});

test('Harbour view pose stays on a civic sidewalk and faces the cruise terminal', () => {
  const vista = getVista();
  const target=vista.positionFor(world), pose=vista.lookoutFor(world);
  assert.ok(pose && Number.isFinite(pose.yaw));
  assert.ok(polygonContains(pose,world.route.walkBounds));
  assert.ok(world.zones.sidewalk.some(zone=>polygonContains(pose,zone.polygon)));
  assert.equal(world.buildings.some(b=>polygonContains(pose,b.footprint)), false);
  assert.equal(polygonContains(pose,world.parkingLot.polygon), false);
  const dx=target.x-pose.x, dz=target.z-pose.z, length=Math.hypot(dx,dz);
  const alignment=((-Math.sin(pose.yaw))*dx+(-Math.cos(pose.yaw))*dz)/length;
  assert.ok(alignment>0.999, 'preset looks straight toward Canada Place');
  assert.ok(pose.x>world.spawn.x && pose.z>world.spawn.z, 'harbour sidewalk viewpoint is east along Cordova');
});

test('render scene includes water, layered mountain skyline, five sail groups and representative docked cruise ship', () => {
  const root=getVista().create(threeStub(), world);
  const children=flatten(root);
  const names=children.map(node=>node.name);
  assert.ok(root);
  assert.equal(root.userData.northAxis,'-Z');
  assert.equal(root.userData.representativeShip,true);
  assert.equal(root.userData.mountainElevationSurveyed,false);
  assert.ok(names.includes('Burrard Inlet water'));
  assert.ok(names.includes('Waterfront rail-yard apron'));
  assert.ok(names.includes('North Shore distant blue peaks'));
  assert.ok(names.includes('North Vancouver forested lower slopes'));
  assert.equal(names.filter(name=>/^Canada Place white sail [1-5]$/.test(name)).length,10);
  assert.ok(names.includes('CANADA PLACE'));
  assert.equal(names.filter(name=>/Cruise ship white passenger deck/.test(name)).length,7);
  assert.equal(names.filter(name=>/Orange cruise ship lifeboat/.test(name)).length,10);
  assert.ok(names.includes('Cruise ship pointed bow'));
  assert.ok(names.includes('North Vancouver SeaBus silhouette'));
  assert.ok(names.includes('Working harbour tug silhouette'));
  const cruise=children.find(n=>n.name.includes('Representative cruise ship at Canada Place'));
  assert.ok(cruise.userData.representative);
  assert.equal(cruise.userData.liveBerthAssignment,false);
  assert.ok(children.length<400,'keep the vista render budget suitable for an 8GB laptop');
  const pier=children.find(n=>n.name==='Canada Place pier / white sails');
  assert.ok(pier.position.z<-100 && pier.position.x<0,'pier starts on waterfront, extending into the inlet');
});

test('scene loads the vista, extends draw distance and exposes real Harbour view control', () => {
  const php=read('functions.php');
  const js=read('js/gastown-sim.js');
  const markup=read('page-gastown-sim.php');
  const manifest=read('scripts/theme_deploy_manifest.py');
  const workflow=read('.github/workflows/gastown-gis-qa.yml');
  assert.match(php, /'se-gastown-harbour-vista'/);
  assert.match(php, /'se-gastown-waterfront-places', 'se-gastown-harbour-vista'/);
  assert.match(js, /harbourVista\.create\(THREE, world\)/);
  assert.match(js, /const STREET_CAMERA_FAR = 1000/);
  assert.match(js, /harbourVista\.lookoutFor\(state\.world\)/);
  assert.match(js, /isSpawnSafe\(pose\)/);
  assert.match(markup, /data-action="harbour-lookout"/);
  assert.match(markup, /not live berth status/);
  assert.ok(manifest.includes('"js/gastown-harbour-vista.js"'));
  assert.match(workflow, /node --test __tests__\/gastown-harbour-vista\.test\.js/);
});
