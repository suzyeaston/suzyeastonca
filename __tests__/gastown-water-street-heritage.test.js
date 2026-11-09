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
 PlaneGeometry:Geo,SphereGeometry:Geo,MeshStandardMaterial:Mat,CanvasTexture,
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
 assert.equal(s.userData.routeStart,9);
 assert.equal(s.userData.routeEnd,world.route.centerline.length-1);
 const road=all.filter(n=>/Water Street brick roadway/.test(n.name));
 assert.equal(road.length,world.route.centerline.length-10);
 assert.ok(road.every(p=>p.rotation.order==='YXZ'));
 assert.ok(road.every(p=>p.geometry.args[0]<=world.route.streetWidth));
});
test('historic identity includes storefronts, globe lights, chains, benches and courier van',()=>{
 const all=nodes(scene());
 for(const name of ['Heritage brick pier','Tall heritage sash','heritage street light',
 'chain bollard','Sagging heritage curb chain','sidewalk bench','street tree','delivery van']){
  assert.ok(all.some(n=>n.name.toLowerCase().includes(name.toLowerCase())),name);
 }
 assert.ok(all.filter(n=>n.name.includes('Tall heritage sash window')).length>=30);
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
