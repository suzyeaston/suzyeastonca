'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=file=>fs.readFileSync(path.join(__dirname,'..',file),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
function stub(){
 class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this}}
 class Geo{constructor(...args){this.args=args}}
 class Mat{constructor(args){this.args=args||{}}}
 class Node{
  constructor(){this.name='';this.children=[];this.position=new Vec();this.rotation={set(x,y,z,order){this.x=x;this.y=y;this.z=z;this.order=order}};this.userData={};this.scale=new Vec();this.quaternion={setFromUnitVectors(){}}}
  add(c){this.children.push(c);c.parent=this}
 }
 class Group extends Node{}
 class Mesh extends Node{constructor(geo,mat){super();this.geometry=geo;this.material=mat}}
 class Vector3 extends Vec{constructor(x,y,z){super();this.set(x,y,z)}normalize(){const n=Math.hypot(this.x,this.y,this.z)||1;this.x/=n;this.y/=n;this.z/=n;return this}}
 const CanvasTexture=class{constructor(){this.repeat={set(){}}}};
 return{Group,Mesh,Vector3,BoxGeometry:Geo,CylinderGeometry:Geo,
 PlaneGeometry:Geo,SphereGeometry:Geo,MeshStandardMaterial:Mat,MeshBasicMaterial:Mat,CanvasTexture,
 RepeatWrapping:1000,SRGBColorSpace:'srgb'};
}
function scene(){
 const sandbox={};
 new Function('globalThis','module','exports',read('js/gastown-street-life.js'))(sandbox,undefined,undefined);
 return sandbox.GastownStreetLife.createWaterStreetDetails(stub(),world);
}
function nodes(root){return [root,...root.children.flatMap(nodes)]}
test('heritage kit only occupies Water Street route and references civic centerline',()=>{
 const s=scene(),all=nodes(s);
 assert.equal(s.userData.referenceApproximation,true);
 assert.equal(s.userData.routeStart,7);
 assert.equal(s.userData.routeEnd,world.route.centerline.length-1);
 const road=all.filter(n=>/Water Street brick roadway/.test(n.name));
 assert.equal(road.length,world.route.centerline.length-8);
 assert.ok(road.every(p=>p.rotation.order==='YXZ'));
 assert.ok(road.every(p=>p.geometry.args[0]<=world.route.streetWidth));
});
test('street kit adds furniture without freestanding facade walls blocking walking route',()=>{
 const all=nodes(scene());
 for(const name of ['heritage street light',
 'chain bollard','Sagging heritage curb chain','sidewalk bench','street tree','delivery van']){
  assert.ok(all.some(n=>n.name.toLowerCase().includes(name.toLowerCase())),name);
 }
 assert.equal(all.filter(n=>n.name.includes('Heritage brick pier and facade')).length,0);
 assert.equal(all.filter(n=>n.name.includes('Tall heritage sash window')).length,0);
 assert.ok(all.filter(n=>n.name.includes('Five globe heritage street light')).length>=12);
});
test('new Water Street layer uses sensible lightweight physical dimensions',()=>{
 const all=nodes(scene());
 const meshes=all.filter(n=>n.geometry);
 assert.ok(meshes.length<1100,'draw call budget');
 assert.ok(meshes.every(n=>n.geometry.args.every(Number.isFinite)));
 assert.ok(meshes.every(n=>n.position.x===undefined||Number.isFinite(n.position.x)));
 const sim=read('js/gastown-sim.js');
 assert.match(sim,/streetLife.createWaterStreetDetails\(THREE, world\)/);
 assert.match(sim,/Deliberately not a collider/);
});

test('The Landing retains its civic building envelope and adds curved glass canopy',()=>{
 const source=read('js/gastown-waterfront-places.js');
 assert.match(source,/The Landing sidewalk barrel canopy curved iron rib/);
 assert.match(source,/The Landing translucent overhead canopy glazing/);
 assert.match(source,/The Landing Victorian canopy sidewalk support/);
 assert.match(source,/landing\.footprint/);
});

test('heritage frontages attach to mapped civic building footprints, not fake street walls',()=>{
 const sceneRoot=scene(),all=nodes(sceneRoot);
 const frontages=all.filter(n=>/^Mapped Water Street heritage frontage /.test(n.name));
 assert.ok(frontages.length>=5,'street needs meaningful historic frontage coverage');
 assert.ok(frontages.length<=14,'constrain draw calls');
 assert.equal(sceneRoot.userData.mappedHeritageFrontages,frontages.length);
 for(const face of frontages){
  const id=face.name.replace('Mapped Water Street heritage frontage ','');
  const building=world.buildings.find(b=>b.id===id);
  assert.ok(building,'each facade must reference a real footprint');
  assert.equal(face.position.x,building.x);
  assert.equal(face.position.z,building.z);
  assert.equal(face.rotation.y,-building.yaw);
 }
 assert.ok(all.some(n=>n.name==='Heritage upper sash window'));
 assert.ok(all.some(n=>n.name==='Ground floor glazing band'));
 assert.ok(all.some(n=>n.name==='Gastown mature tree fine canopy'));
});


test('Water Street curb posts, chains, five-globe lamps and trees stand OUTSIDE the roadway',()=>{
 const root=scene();
 const furniture=root.children.filter(n=>n.name==='Water Street heritage sidewalk furniture');
 assert.ok(furniture.length>=16,'sidewalk furniture on each side of Water Street');
 const widths=[];
 const names=['Black cast iron chain bollard','Bollard spherical cap',
  'Gastown five-globe iron lamppost','Water Street mature tree trunk',
  'Iron street-tree grate','Gastown sidewalk bench seat',
  'Iron bench leg','Gastown bicycle rack'];
 for(const group of furniture){
  assert.equal(group.userData.roadwayHalfWidth,5.2);
  assert.equal(group.userData.sideOfWaterStreet,group.userData.sidewalkOffset<0?'north':'south');
  for(const node of group.children.filter(n=>names.includes(n.name))){
   assert.ok(Math.abs(node.position.x)>=5.95,
    node.name+' should not occupy the 10.4m carriageway');
   widths.push(Math.abs(node.position.x));
  }
  if(group.children.some(n=>n.name==='Black cast iron chain bollard')){
   assert.equal(group.userData.mappedRouteSegment,world.route.centerline.length-2,
    'heritage chains belong by Steam Clock, not across every block');
   assert.equal(group.userData.sideOfWaterStreet,'north');
  }
 }
 assert.ok(widths.length>20);
 const chainGroup=furniture.filter(n=>n.children.some(c=>c.name==='Black cast iron chain bollard'));
 assert.equal(chainGroup.length,1,'only one short chain section by the clock');
 assert.equal(chainGroup[0].children.filter(n=>n.name==='Sagging heritage curb chain').length,4);
});

test('Water Street has three moving westbound cars and no eastbound motor traffic',()=>{
 const THREE=stub();
 const traffic=require('../js/gastown-street-life.js').create(THREE,world);
 const cars=traffic.root.children.filter(n=>/Water Street one-way westbound car/.test(n.name));
 assert.equal(cars.length,3);
 assert.deepEqual(traffic.root.userData.waterStreetTraffic,{
  direction:'westbound',oneWay:true,start:'Water & Cambie',
  end:'Waterfront / Water Street',laneOffsetMetres:1.75
 });
 const original=cars.map(car=>({x:car.position.x,z:car.position.z}));
 traffic.update(3);
 cars.forEach((car,i)=>{
  assert.equal(car.userData.travelDirection,'westbound');
  assert.ok(car.position.x<original[i].x-2,'car advances west, never east');
  assert.ok(Number.isFinite(car.rotation.y));
 });
 const first=world.route.centerline[7],last=world.route.centerline.at(-1);
 assert.ok(original[0].x>first.x && original[0].x>last.x-3,
   'first westbound car spawns at Steam Clock eastern end');
});


test('Water and Cambie no longer ends in a void, with mapped corner shop frontages',()=>{
 const root=scene();
 const intersection=root.children.find(g=>g.name==='Water and Cambie open brick intersection');
 assert.ok(intersection);
 assert.equal(intersection.userData.openWestboundVehicleLane,true);
 assert.equal(intersection.userData.navmeshUnchanged,true);
 const surfaces=intersection.children.map(n=>n.name);
 assert.equal(surfaces.filter(n=>n==='Water Street east-of-Cambie paved continuation').length,2);
 assert.ok(surfaces.includes('Cambie Street northbound connection to the waterfront'));
 assert.ok(surfaces.includes('Cambie Street southbound connection to W Cordova'));
 assert.ok(surfaces.includes('Water and Cambie brick raised intersection apron'));
 const facades=root.children.find(g=>g.name==='Water and Cambie feature-aligned heritage corner shops');
 assert.equal(facades.userData.gisAlignedStorefronts,true);
 assert.deepEqual(Array.from(facades.userData.mappedIds),['feature-86','feature-57']);
 for(const g of facades.children){
  const mapped=world.buildings.find(b=>b.id===g.userData.sourceFootprintId);
  const [a,b]=g.userData.civicEdge;
  const p=mapped.footprint[a],q=mapped.footprint[b];
  assert.ok(Math.hypot(g.position.x-(p.x+q.x)/2,
   g.position.z-(p.z+q.z)/2)<0.01,'shop front must align to civic parcel');
  assert.ok(g.children.some(n=>n.name.endsWith('heritage sash windows')));
  assert.ok(g.children.some(n=>n.name.endsWith('ground-floor shop window')));
 }
});


test('Clock intersection paving is layered to avoid coplanar flicker; no blank cream wall',()=>{
 const s=scene();
 const intersection=s.children.find(x=>x.name==='Water and Cambie open brick intersection');
 assert.ok(intersection);
 const heights=intersection.children.map(x=>x.position.y);
 assert.ok(heights.every(Number.isFinite));
 assert.ok(heights.every(y=>y>=0.071 && y<=0.134));
 assert.deepEqual(Array.from(intersection.userData.nonCoplanarSurfaceLevels),
  [0.071,0.081,0.108,0.134]);
 const mainRoad=nodes(s).find(x=>x.name==='Water Street brick roadway / historic paver lane');
 assert.ok(mainRoad);
 assert.ok(heights.every(y=>Math.abs(y-mainRoad.position.y)>0.008));
 const corners=s.children.find(x=>x.name==='Water and Cambie feature-aligned heritage corner shops');
 assert.ok(corners);
 for(const facade of corners.children){
  assert.equal(facade.userData.opaqueFullHeightOverlay,false);
  assert.equal(facade.userData.facadeSides,2);
  assert.equal(facade.children.filter(n=>n.name.endsWith('warm heritage pier base')).length,0);
  assert.equal(facade.children.filter(n=>n.name.endsWith('ground-floor shop window')).length>0,true);
 }
});
