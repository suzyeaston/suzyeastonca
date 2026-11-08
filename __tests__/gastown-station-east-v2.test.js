'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = name => fs.readFileSync(path.join(root,name),'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

function threeStub() {
  class Vec {
    set(x,y,z) { this.x=x;this.y=y;this.z=z;return this; }
  }
  class Rot {
    set(x,y,z) { this.x=x;this.y=y;this.z=z;return this; }
  }
  class Geo {
    constructor(...args) { this.args=args; }
    rotateX() { return this; }
  }
  class Shape { moveTo(){} lineTo(){} closePath(){} quadraticCurveTo(){} }
  class Material { constructor(options){this.options=options;} }
  class Mesh {
    constructor(geometry,material) {
      this.geometry=geometry;this.material=material;
      this.name='';this.children=[];this.position=new Vec();
      this.rotation=new Rot();this.scale=new Vec();this.userData={};
    }
  }
  class Group {
    constructor() {
      this.children=[];this.name='';this.position=new Vec();
      this.rotation=new Rot();this.scale=new Vec();this.userData={};
    }
    add(v) { this.children.push(v); }
  }
  class CanvasTexture {constructor(){this.repeat={set(){}};}}
  return {
    Group,Mesh,Shape,CanvasTexture,
    MeshStandardMaterial:Material,MeshBasicMaterial:Material,
    BoxGeometry:Geo,CylinderGeometry:Geo,SphereGeometry:Geo,
    TorusGeometry:Geo,CircleGeometry:Geo,PlaneGeometry:Geo,
    ShapeGeometry:Geo,ExtrudeGeometry:Geo,
    DoubleSide:2,RepeatWrapping:1000,SRGBColorSpace:'srgb'
  };
}
function boot() {
  const ctx={fillRect(){},fillText(){},clearRect(){}};
  const win={document:{createElement:()=>({width:1024,height:256,getContext:()=>ctx})}};
  new Function('window',read('js/gastown-waterfront-places.js'))(win);
  new Function('window',read('js/gastown-suzy-avatar.js'))(win);
  return win;
}
function all(root) {
  const results=[root];
  for(let i=0;i<results.length;i++)results.push(...(results[i].children||[]));
  return results;
}

test('Lot 531 has visible landmark props, not just an asphalt polygon',()=>{
  const win=boot(),place=win.GastownWaterfrontPlaces.create(threeStub(),world);
  const lot=place.children[0],names=all(lot).map(x=>x.name);
  for(const feature of [
    'Station East overhead 2m clearance gantry',
    'MAX HEIGHT 2.00m',
    '8 km/h',
    'WARNING',
    'Yellow waterfront concrete barricade',
    'Station East entry yellow hatching',
    'Waterfront fence vertical black picket',
    'Lot inbound painted arrow head'
  ]) assert.ok(names.includes(feature),'missing station landmark: '+feature);
  assert.ok(lot.userData.yellowBarricades>=2,'need yellow barricades on perimeter');
  assert.ok(names.filter(x=>x==='Waterfront fence vertical black picket').length>=40);
  assert.ok(!names.includes('Parking gate arm'),'entry must remain walkable');
  assert.ok(win.GastownWaterfrontPlaces.isWalkableLot(world,win.GastownWaterfrontPlaces.entranceFor(world.parkingLot)));
  assert.ok(names.filter(x=>x==='Yellow waterfront concrete barricade').length<25,'respect low-power 3D mesh budget');
});

test('Steamworks reference awning belongs to the pale warehouse',()=>{
  const place=boot().GastownWaterfrontPlaces.create(threeStub(),world);
  const names=all(place.children[1]).map(x=>x.name);
  assert.ok(names.includes('Steamworks patio copper awning'));
  assert.equal(names.filter(x=>x==='Steamworks outdoor patio frame').length,2);
});

test('Suzy is a real stylized third-person model with film-ready cues',()=>{
  const avatar=boot().GastownSuzyAvatar.create(threeStub());
  const names=all(avatar).map(x=>x.name);
  for(const feature of [
    'Black overshirt torso','Light denim waist','Silver lavender crown',
    'Messy lavender bun','Translucent spectacle rim','Handheld dynamic microphone'
  ]) assert.ok(names.includes(feature),'missing Suzy avatar element: '+feature);
  assert.equal(avatar.userData.identity,'Suzy Easton');
  assert.equal(avatar.visible,false,'default first-person must not render an obstructing character');
  const api=boot().GastownSuzyAvatar;
  api.animate(avatar,0,0);
  const leg=avatar.userData.limbs.legs[0],before=leg.rotation.x;
  api.animate(avatar,0.2,1);
  assert.notEqual(leg.rotation.x,before,'walking animates rig rather than static paper doll');
});

test('Suzy identity and view controls are wired into live runtime and deployment manifest',()=>{
  const page=read('page-gastown-sim.php');
  const runtime=read('js/gastown-sim.js');
  const php=read('functions.php');
  const manifest=read('scripts/theme_deploy_manifest.py');
  assert.match(page,/SUZY · 0% mapped/);
  assert.doesNotMatch(page,/Name your walker/);
  assert.doesNotMatch(page,/data-action="rename-walker"/);
  for(const action of ['harbour-lookout','lot-entrance-view','lot-diagonal-view','suzy-view'])
    assert.ok(page.includes('data-action="'+action+'"'),action);
  assert.match(runtime,/walkerName: 'Suzy'/);
  assert.match(runtime,/suzyAvatarModel\.create\(THREE\)/);
  assert.match(runtime,/function toggleSuzyView\(\)/);
  assert.match(runtime,/isSpawnSafe\(pose\)/);
  assert.match(runtime,/function goToLotView\(shot\)/);
  assert.match(php,/'se-gastown-suzy-avatar'/);
  assert.ok(manifest.includes('"js/gastown-suzy-avatar.js"'));
});
