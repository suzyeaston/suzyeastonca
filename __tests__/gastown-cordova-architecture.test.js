'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ROOT=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(ROOT,file),'utf8');

function threeStub(){
  class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this;}copy(v){return this.set(v.x,v.y,v.z);}}
  class Geometry{constructor(...args){this.args=args;}}
  class Material{constructor(params){this.params=params||{};this.color={};}}
  class Mesh{
    constructor(geometry,material){this.geometry=geometry;this.material=material;this.name='';this.position=new Vec();this.rotation={};this.scale=new Vec();this.children=[];}
  }
  class Group{
    constructor(){this.position=new Vec();this.rotation={};this.children=[];this.userData={};this.name='';}
    add(child){this.children.push(child);}
  }
  return {Group,Mesh,BoxGeometry:Geometry,MeshBasicMaterial:Material,
    MeshStandardMaterial:Material,DoubleSide:2};
}
function model(){
  const win={};
  new Function('window',read('js/gastown-cordova-architecture.js'))(win);
  return win.GastownCordovaArchitecture;
}
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
test('replaced GIS near-station masses are all mapped to local building footprints',()=>{
  const m=model(), group=m.create(threeStub(),world);
  assert.deepEqual(Array.from(m.replacementIds()),['feature-92','feature-15','feature-16']);
  assert.equal(group.children.length,3);
  assert.deepEqual(Array.from(group.userData.replacedIds()),Array.from(m.replacementIds()));
  for(const child of group.children){
    assert.ok(child.userData.sourceFootprintId);
    const source=world.buildings.find(b=>b.id===child.userData.sourceFootprintId);
    assert.ok(source,'must refer to a real civic footprint');
    assert.equal(child.position.x,source.x);
    assert.equal(child.position.z,source.z);
    assert.equal(child.rotation.y,-source.yaw);
  }
});
test('no oversize triangulated shadow caster or black roof wedge in new silhouettes',()=>{
  const group=model().create(threeStub(),world);
  for(const b of group.children){
    const shells=b.children.filter(x=>/clean orthogonal body/.test(x.name));
    assert.ok(shells.length>=1);
    for(const part of b.children){
      assert.equal(part.castShadow,false,part.name);
      assert.equal(part.receiveShadow,false,part.name);
      assert.ok(part.geometry.args.every(Number.isFinite),part.name);
    }
  }
  const sim=read('js/gastown-sim.js');
  assert.match(sim,/replacedBuildings\.has\(b\.id\)/);
  assert.match(sim,/roof\.rotation\.y = mesh\.rotation\.y/);
  assert.match(sim,/roof\.scale\.set\(massingInset, 1, massingInset\)/);
  assert.match(sim,/if \(state\.debugEnabled\) \{\s*const edge = new THREE\.LineSegments/);
});
test('reference-derived skylines distinguish gridded glass and curved modern tower',()=>{
  const group=model().create(threeStub(),world);
  const all=group.children.flatMap(b=>b.children.map(x=>x.name));
  assert.ok(all.includes('Stone square-bay Cordova office block clean orthogonal body'));
  assert.ok(all.includes('Seymour gridded glass tower clean orthogonal body'));
  assert.ok(all.includes('Cordova glass upper office shaft clean orthogonal body'));
  assert.equal(all.filter(n=>n.startsWith('Cordova curved glass strip')).length,7);
  assert.ok(all.some(n=>n==='Cordova glazed rooftop crown'));
});
test('WordPress and theme deploy load this model before Gastown sim',()=>{
  const functions=read('functions.php');
  const manifest=read('scripts/theme_deploy_manifest.py');
  const sim=read('js/gastown-sim.js');
  assert.match(functions,/'se-gastown-cordova-architecture'/);
  assert.match(functions,/'se-gastown-harbour-vista', 'se-gastown-cordova-architecture', 'se-gastown-suzy-avatar'/);
  assert.match(manifest,/"js\/gastown-cordova-architecture.js"/);
  assert.match(sim,/cordovaArchitecture\.create\(THREE, world\)/);
  assert.match(sim,/worldGroup\.add\(southSkyline\)/);
});
