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
    // Street-facing facade meshes can be grouped by mapped civic edge.
    // Check the actual meshes recursively, not their non-rendered Group parents.
    const queue=[...b.children];
    for(let i=0;i<queue.length;i++){
      const part=queue[i];
      if(!part.geometry){queue.push(...(part.children||[]));continue;}
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

test('Cordova-facing Harbour Centre streetwall follows the real civic edges',()=>{
  const group=model().create(threeStub(),world);
  const harbour=group.children.find(child=>child.userData.landmark==='Harbour Centre / Spencer Building');
  assert.ok(harbour);
  const facades=harbour.children.filter(node=>node.name.startsWith('Harbour Centre Cordova civic parcel frontage'));
  assert.equal(facades.length,2);
  const parcel=world.buildings.find(b=>b.id==='feature-16');
  for(const facade of facades){
    const [a,b]=facade.userData.civicEdge,pa=parcel.footprint[a],pb=parcel.footprint[b];
    const center={x:(pa.x+pb.x)/2,z:(pa.z+pb.z)/2};
    const yaw=-harbour.rotation.y;
    const local=facade.position,worldPos={
      x:harbour.position.x+local.x*Math.cos(yaw)-local.z*Math.sin(yaw),
      z:harbour.position.z+local.x*Math.sin(yaw)+local.z*Math.cos(yaw)
    };
    assert.ok(Math.hypot(center.x-worldPos.x,center.z-worldPos.z)<0.05,
      'facade must sit on the correct 2015 civic parcel edge');
    assert.ok(facade.children.some(node=>node.name==='Harbour Centre individual upper sash window'));
    assert.ok(facade.children.some(node=>node.name==='Harbour Centre Cordova retail display glass'));
    const mass=facade.children.find(node=>node.name==='Harbour Centre articulated stone podium body');
    assert.ok(mass.geometry.args[1] <= 14, 'remove the oversize 17-m blank slab');
    assert.ok(facade.children.every(node=>node.name!=='Harbour Centre Cordova parkade concrete streetwall'));
    const outwardAngle=harbour.rotation.y+facade.rotation.y;
    const normal={x:Math.sin(outwardAngle),z:Math.cos(outwardAngle)};
    const towardLot={
      x:world.parkingLot.center.x-center.x,
      z:world.parkingLot.center.z-center.z
    };
    const signedNormal=towardLot.x*normal.x+towardLot.z*normal.z;
    assert.ok(signedNormal*facade.userData.parkingFace>0,
      'public-side windows face the parking lot, not the back of the facade');
    const sign=facade.children.find(node=>node.name.endsWith('fascia panel'));
    assert.ok(sign && sign.userData.signText, 'retail sign backing has a readable label');
    assert.ok(Math.sign(sign.position.z)===facade.userData.parkingFace,
      'shop sign is visible from the public parking lot');
    assert.ok(facade.children.every(node=>node.castShadow===false),
      'architectural details may not cast black wedges');
  }
  assert.match(harbour.userData.cordovaStreetWallSource,/feature-16/);
});

test('Food Court and Shops precede Dollarama toward the parking lot, by mapped civic frontage',()=>{
  const harbour=model().create(threeStub(),world).children
    .find(child=>child.userData.landmark==='Harbour Centre / Spencer Building');
  const west=harbour.children.find(node=>node.userData?.streetLevelRetail==='Food Court / Shops');
  const east=harbour.children.find(node=>node.userData?.streetLevelRetail==='Dollarama');
  assert.ok(west && east);
  assert.deepEqual(west.userData.civicEdge,[5,6],'542 W Cordova / Food Court is the western frontage');
  assert.deepEqual(east.userData.civicEdge,[13,14],'540 W Cordova / Dollarama is the eastern frontage');
  const parcel=world.buildings.find(b=>b.id==='feature-16');
  const midpoint=(edge)=>{const [a,b]=edge;return {
    x:(parcel.footprint[a].x+parcel.footprint[b].x)/2,
    z:(parcel.footprint[a].z+parcel.footprint[b].z)/2
  };};
  assert.ok(midpoint(west.userData.civicEdge).x < midpoint(east.userData.civicEdge).x,
    'food court comes first when walking east along Cordova, Dollarama is farther toward parking');
  for(const name of ['Food Court','Shops']){
    assert.ok(west.children.some(node=>node.name===name+' recessed glass entrance pair'));
    assert.ok(west.children.some(node=>node.name===name+' projecting entrance awning'));
    const sign=west.children.find(node=>node.name===name+' Cordova entry fascia panel');
    assert.ok(sign && sign.userData.signText===name.toUpperCase());
  }
  assert.ok(east.children.some(node=>node.name==='Dollarama recessed glass door pair'));
  assert.equal(east.children.find(node=>node.name==='Dollarama Cordova storefront fascia panel')
    .userData.signText,'DOLLARAMA');
  const entry=harbour.children.find(node=>node.name==='Harbour Centre Cordova recessed connecting lobby');
  assert.ok(entry);
  assert.deepEqual(entry.userData.recessedBetweenCivicEdges,[6,13]);
  assert.ok(entry.children.some(node=>node.name==='Harbour Centre connecting lobby full-height doors'));
  assert.equal(entry.children.find(node=>node.name==='Harbour Centre Cordova main entrance fascia panel')
    .userData.signText,'HARBOUR CENTRE');
  assert.deepEqual(Array.from(harbour.userData.cordovaRetailOrder),
    ['Food Court','Shops','Harbour Centre','Dollarama']);
  assert.equal(harbour.userData.cordovaFrontFacesComputedFromLot,true);
});

test('Harbour Centre has one physically connected three-dimensional podium, not free-standing facade slabs',()=>{
  const harbour=model().create(threeStub(),world).children
    .find(child=>child.userData.landmark==='Harbour Centre / Spencer Building');
  const linked=harbour.userData.connectedCivicPodium;
  assert.ok(linked && linked.overlapsSpencerTower,'podium must overlap the tower at civic parcel');
  assert.ok(linked.backX-linked.frontX>65,'mapped podium must have actual depth');
  assert.ok(linked.roofY>12 && linked.roofY<15);
  const names=[
    'Harbour Centre connected east retail podium',
    'Harbour Centre connected west civic podium',
    'Harbour Centre connected central lobby mass',
    'Harbour Centre connected north retail wing'
  ];
  const masses=names.map(name=>harbour.children.find(node=>node.name===name));
  assert.ok(masses.every(Boolean),'two sides, middle and north wing must all have physical masses');
  assert.ok(masses.every(node=>node.userData.connectedBuildingMass));
  assert.ok(masses.every(node=>node.geometry.args[0]>20 && node.geometry.args[2]>6),
    'connected podium volumes must not be 34cm paper-thin');
  const east=masses[0],west=masses[1],connector=masses[2];
  const bounds=(m)=>({minX:m.position.x-m.geometry.args[0]/2,
    maxX:m.position.x+m.geometry.args[0]/2,
    minZ:m.position.z-m.geometry.args[2]/2,
    maxZ:m.position.z+m.geometry.args[2]/2});
  const e=bounds(east),w=bounds(west),c=bounds(connector);
  const overlap=(a,b)=>a.minX<b.maxX && b.minX<a.maxX &&
    a.minZ<b.maxZ && b.minZ<a.maxZ;
  assert.ok(overlap(e,c) && overlap(w,c),
    'podium segments must overlap through the connector, not hover separately');
  const tower=harbour.children.find(node=>node.name==='Harbour Centre concrete gridded 28 storey shaft');
  assert.ok(tower && e.minX<tower.position.x && e.maxX>tower.position.x);
  assert.ok(w.minX<tower.position.x && w.maxX>tower.position.x);
  const local=world.buildings.find(b=>b.id==='feature-16').footprint_local;
  const averageX=(a,b)=>(local[a].x+local[b].x)/2;
  assert.ok(Math.abs(linked.westFrontX-averageX(5,6))<0.2);
  assert.ok(Math.abs(linked.eastFrontX-averageX(13,14))<0.2);
  for(const name of [
    'Harbour Centre connected east podium roof',
    'Harbour Centre connected west podium roof',
    'Harbour Centre connected central roof',
    'Harbour Centre connected north wing roof'
  ]){
    const roof=harbour.children.find(node=>node.name===name);
    assert.ok(roof && roof.geometry.args[1]===0.24,'each new solid volume needs a flat roof');
  }
  assert.equal(world.parkingLot.center.x,46,'raised parking lot and all movement geometry remain unchanged');
  assert.equal(world.station.x,-22.92);
});
