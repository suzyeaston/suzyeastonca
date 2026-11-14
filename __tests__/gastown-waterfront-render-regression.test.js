'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ROOT=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(ROOT,file),'utf8');

function threeStub(){
  class Vec { set(x,y,z){this.x=x;this.y=y;this.z=z;return this;} }
  class Geometry { constructor(...args){this.args=args;} rotateX(){return this;} }
  class Material { constructor(options){this.options=options;} }
  class Node {
    constructor(geometry,material){
      this.geometry=geometry;this.material=material;this.children=[];
      this.position=new Vec();this.scale=new Vec();this.rotation={};
      this.name='';this.userData={};
    }
    add(child){this.children.push(child);}
  }
  class Texture { constructor(){this.repeat={set(){}};} }
  return {
    Group:Node,Mesh:Node,BoxGeometry:Geometry,PlaneGeometry:Geometry,
    CylinderGeometry:Geometry,SphereGeometry:Geometry,
    MeshStandardMaterial:Material,MeshBasicMaterial:Material,
    CanvasTexture:Texture,DoubleSide:2,SRGBColorSpace:'srgb'
  };
}
function nodes(root){
  const all=[root];
  for(let i=0;i<all.length;i++)all.push(...(all[i].children||[]));
  return all;
}

test('The Landing lobby really builds: missing vestibule material must not erase the waterfront',()=>{
  const ctx={fillRect(){},fillText(){}};
  const fakeWindow={document:{createElement:()=>({getContext:()=>ctx})}};
  new Function('window',read('js/gastown-waterfront-places.js'))(fakeWindow);
  const journey=fakeWindow.GastownWaterfrontPlaces.createLandingJourney(threeStub());
  const all=nodes(journey);
  assert.ok(all.some(node=>node.name==='Elevator vestibule black stone surround'));
  assert.ok(all.some(node=>node.name==='Elevator floor indicator'));
  assert.ok(all.some(node=>node.name==='KK fictional lobby escort'));
  assert.ok(journey.userData.elevator);
});

test('Waterfront Station and Station East lot render before optional lobby extras',()=>{
  const main=read('js/gastown-sim.js');
  const hero=main.slice(main.indexOf('  function addHeroLandmarks(world) {'),main.indexOf('    (world.hero_landmarks || []).forEach(',main.indexOf('  function addHeroLandmarks(world) {')));
  const station=hero.indexOf("safelyCreate('Waterfront Station'");
  const lot=hero.indexOf("safelyCreate('Station East parking'");
  const lobby=hero.indexOf("safelyCreate('The Landing lobby'");
  assert.ok(station>=0&&lot>station&&lobby>lot,'critical station and lot must render first');
  assert.match(hero,/safelyCreate\('The Landing lab'/);
  assert.match(hero,/safelyCreate\('The Landing exterior'/);
  assert.match(hero,/safelyCreate\('harbour'/);
  assert.equal((hero.match(/stationModel\.create\(THREE, world\)/g)||[]).length,1);
});

test('waterfront keeps mapped architecture but not invented eastern high-rises',()=>{
  const harbour=read('js/gastown-harbour-vista.js');
  const scene=read('js/gastown-sim.js');
  assert.doesNotMatch(harbour,/downtownSkylineDepth|Illustrative Vancouver downtown skyline infill/);
  assert.match(harbour,/waterfrontRailYard\(THREE,root,world\)/);
  assert.match(harbour,/additionalWaterfrontFreight\(THREE,root\)/);
  assert.match(scene,/cordovaArchitecture\.create\(THREE, world\)/);
  assert.match(scene,/world\.buildings\.forEach\(\(b\) =>/);
});
