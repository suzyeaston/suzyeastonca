'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ROOT=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(ROOT,file),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
function boot(){
  const window={};
  new Function('window',read('js/gastown-waterfront-skyline.js'))(window);
  return window.GastownWaterfrontSkyline;
}
function stub(){
  class Vec { set(x,y,z){this.x=x;this.y=y;this.z=z;return this;} }
  class Geo{constructor(...args){this.args=args;}}
  class Mat{constructor(params){this.params=params;}}
  class Node{
    constructor(geo,mat){this.geometry=geo;this.material=mat;this.name='';
      this.position=new Vec();this.rotation={};this.children=[];
      this.userData={};}
    add(child){this.children.push(child);}
  }
  return {Group:Node,Mesh:Node,BoxGeometry:Geo,CylinderGeometry:Geo,
    MeshStandardMaterial:Mat,DoubleSide:2};
}
function inside(point,poly){
  let hit=false;
  for(let i=0,j=poly.length-1;i<poly.length;j=i++){
    const a=poly[i],b=poly[j];
    if((a.z>point.z)!==(b.z>point.z)&&point.x<
      (b.x-a.x)*(point.z-a.z)/((b.z-a.z)||1e-9)+a.x)hit=!hit;
  }
  return hit;
}
test('landmarks are named, sourced, georeferenced and finite',()=>{
  const api=boot(),root=api.create(stub(),world);
  assert.equal(root.userData.landmarkCount,6);
  assert.equal(root.children.length,6);
  const expected={
    '200 Granville Square':'granville-square-200',
    'Royal Bank Tower / Six Seven Five':'royal-bank-675',
    'Sinclair Centre former Post Office':'sinclair-centre',
    '601 West Hastings':'hastings-601',
    "Woodward's W43 / restored W sign":'woodwards-w43',
    'Dominion Building / 207 West Hastings':'dominion-207'
  };
  for(const g of root.children){
    assert.equal(g.userData.landmarkId,expected[g.name],g.name);
    assert.match(g.userData.reference,/^https:\/\//);
    assert.equal(g.userData.scenicOnly,true);
    assert.equal(g.userData.collidable,false);
    const anchor=api.LANDMARKS.find(item=>item.id===g.userData.landmarkId);
    const position=api.project(world,anchor);
    assert.ok(Math.hypot(g.position.x-position.x,g.position.z-position.z)<0.01);
    assert.ok(Number.isFinite(position.x)&&Number.isFinite(position.z));
    assert.ok(!inside(position,world.parkingLot.polygon),'parking lot must stay open: '+g.name);
    assert.ok(!inside(position,world.route.walkBounds),'no scenery should block the street: '+g.name);
    assert.ok(Math.hypot(position.x-world.station.x,position.z-world.station.z)>65,
      'keep Waterfront Station clear: '+g.name);
  }
});
test('recognizable roofs and heritage details, with a bounded mesh budget',()=>{
  const root=boot().create(stub(),world);
  const children=root.children.flatMap(g=>g.children);
  const names=children.map(node=>node.name);
  assert.ok(names.includes('Granville Square harbour air-control room'));
  assert.ok(names.includes('Royal Bank tower upper setback'));
  assert.ok(names.includes('Sinclair Centre post office clock tower shaft'));
  assert.equal(names.filter(name=>name.startsWith('Sinclair Centre clock face /')).length,4);
  assert.ok(names.includes('601 West Hastings sculpted glass office shaft'));
  assert.ok(names.includes("Woodward's W43 glazed residential shaft"));
  assert.equal(names.filter(name=>name==="Woodward's replica red W sign").length,4);
  assert.ok(names.includes("Woodward's heritage brick base"));
  assert.ok(names.includes('Dominion Building green mansard roof'));
  assert.ok(children.length<350,'keep all six skyline models economical on an 8GB laptop');
  for(const part of children){
    assert.equal(part.castShadow,false,part.name);
    assert.equal(part.receiveShadow,false,part.name);
    assert.ok(part.geometry.args.every(Number.isFinite),part.name);
  }
});
test('scene includes new module without changing collisions or walking bounds',()=>{
  const runtime=read('js/gastown-sim.js');
  const php=read('functions.php');
  const deploy=read('scripts/theme_deploy_manifest.py');
  assert.match(runtime,/const waterfrontSkyline = window\.GastownWaterfrontSkyline/);
  assert.match(runtime,/waterfrontSkyline\.create\(THREE, world\)/);
  assert.match(runtime,/worldGroup\.add\(skylineContext\)/);
  assert.doesNotMatch(runtime,/thirdPersonCameraBlockers\.push\(skylineContext\)/);
  assert.match(php,/'se-gastown-waterfront-skyline'/);
  assert.match(deploy,/"js\/gastown-waterfront-skyline.js"/);
});
test('landmarks are not rendered if civic coordinate data is absent',()=>{
  const api=boot();
  assert.equal(api.create(stub(),{meta:{}}),null);
  assert.equal(api.project({},api.LANDMARKS[0]),null);
});

test('Woodward and Dominion are on their real southeast anchors, not beside the parking lot',()=>{
  const api=boot(),root=api.create(stub(),world);
  const w=root.children.find(group=>group.userData.landmarkId==='woodwards-w43');
  const d=root.children.find(group=>group.userData.landmarkId==='dominion-207');
  assert.ok(w && d);
  assert.ok(w.position.x>240 && w.position.z>300,'W43 must be beyond the Steam Clock corridor');
  assert.ok(d.position.x>130 && d.position.z>300,'Dominion must be south of Water Street');
  assert.ok(Math.abs(w.userData.heightApprox-122.3)<0.01);
  assert.ok(w.position.x>d.position.x,'Woodward lies east of Dominion');
});
 

test('Woodward’s curves toward Water Street with balcony bands and a readable reverse W',()=>{
 const landmark=boot().create(stub(),world).children
   .find(g=>g.userData.landmarkId==='woodwards-w43');
 assert.ok(landmark.userData.woodwardsCurvedFacade);
 assert.equal(landmark.userData.woodwardsWVisibleBothSides,true);
 const names=landmark.children.map(n=>n.name);
 assert.equal(names.filter(n=>n==="Woodward's bowed north facade glass bay").length,9);
 assert.equal(names.filter(n=>n==="Woodward's curved brick mullion").length,9);
 assert.ok(names.filter(n=>n==="Woodward's curved pale balcony fascia").length>=10);
 assert.ok(names.filter(n=>n==="Woodward's east elevation pale balcony return").length>=10);
 assert.equal(names.filter(n=>n==="Woodward's W backlit reverse face").length,4);
 assert.ok(names.includes("Woodward's heritage retail canopy"));
 assert.ok(names.includes("Woodward's heritage podium roof pavilion"));
 const all=boot().create(stub(),world).children.flatMap(g=>g.children);
 assert.ok(all.length<350,'W43 improvements must fit original 8GB low-poly budget');
});
