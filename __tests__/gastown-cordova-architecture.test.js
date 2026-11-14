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
  return {Group,Mesh,BoxGeometry:Geometry,CylinderGeometry:Geometry,MeshBasicMaterial:Material,
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
  assert.deepEqual(Array.from(group.userData.replacedIds),Array.from(m.replacementIds()));
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
test('mapped waterfront parcels correspond to the real named architecture',()=>{
  const group=model().create(threeStub(),world);
  const all=group.children.flatMap(b=>b.children.map(x=>x.name));
  assert.ok(all.includes('333 Seymour stone and glass office podium clean orthogonal body'));
  assert.ok(all.includes('320 Granville glazed public lobby clean orthogonal body'));
  assert.ok(all.includes('Harbour Centre heritage Spencer podium clean orthogonal body'));
  assert.equal(all.filter(n=>n==='320 Granville offset silver floor ribbon').length,9);
  assert.equal(all.filter(n=>n==='320 Granville curved front glass fin').length,14);
  assert.equal(all.filter(n=>n==='320 Granville curved side glass fin').length,14);
  assert.equal(group.children.find(b=>b.userData.landmark==='320 Granville').userData.sourceFootprintId,'feature-15');
  assert.equal(group.children.find(b=>b.userData.landmark==='333 Seymour').userData.sourceFootprintId,'feature-92');
});
test('WordPress and theme deploy load this model before Gastown sim',()=>{
  const functions=read('functions.php');
  const manifest=read('scripts/theme_deploy_manifest.py');
  const sim=read('js/gastown-sim.js');
  assert.match(functions,/'se-gastown-cordova-architecture'/);
  assert.match(functions,/'se-gastown-harbour-vista', 'se-gastown-cordova-architecture', 'se-gastown-waterfront-skyline', 'se-gastown-suzy-avatar'/);
  assert.match(manifest,/"js\/gastown-cordova-architecture.js"/);
  assert.match(sim,/cordovaArchitecture\.create\(THREE, world\)/);
  assert.match(sim,/worldGroup\.add\(southSkyline\)/);
});

test('Harbour Centre has recognizable 28-floor shaft and Vancouver Lookout disc',()=>{
  const group=model().create(threeStub(),world);
  const landmark=group.children.find(child=>child.userData.landmark==='Harbour Centre / Spencer Building');
  assert.ok(landmark,'landmark must use an existing civic footprint');
  assert.equal(landmark.userData.sourceFootprintId,'feature-16');
  const names=landmark.children.map(child=>child.name);
  assert.ok(names.includes('Harbour Centre concrete gridded 28 storey shaft'));
  assert.equal(names.filter(n=>n==='Harbour Centre front window ribbon').length,56);
  assert.ok(names.includes('Vancouver Lookout panoramic glass ring'));
  assert.ok(names.includes('Vancouver Lookout overhanging circular roof'));
  const crown=landmark.children.find(child=>child.name==='Vancouver Lookout overhanging circular roof');
  assert.equal(crown.geometry.args[0],14.7);
  assert.equal(crown.geometry.args[1],14.7);
  assert.equal(crown.castShadow,false);
});

test('Vancouver Lookout is within a few metres of the real 555 W Hastings coordinate',()=>{
  const landmark=model().create(threeStub(),world).children
    .find(g=>g.userData.landmark==='Harbour Centre / Spencer Building');
  const tower=landmark.children.find(g=>g.name==='Harbour Centre concrete gridded 28 storey shaft');
  assert.ok(tower);
  const yaw=-landmark.rotation.y,c=Math.cos(yaw),s=Math.sin(yaw);
  const dx=tower.position.x*c-tower.position.z*s;
  const dz=tower.position.x*s+tower.position.z*c;
  const global={x:landmark.position.x+dx,z:landmark.position.z+dz};
  const origin=world.meta.gis.origin,rad=Math.PI/180,R=6371008.8;
  const expected={x:(-123.111935-origin.lon)*R*rad*Math.cos(origin.lat*rad),
    z:-(49.284697-origin.lat)*R*rad};
  assert.ok(Math.hypot(global.x-expected.x,global.z-expected.z)<1,
    'Lookout must be at 555 W Hastings, not at the 333 Seymour parcel');
  assert.ok(Math.hypot(global.x-world.parkingLot.center.x,global.z-world.parkingLot.center.z)>45);
});
