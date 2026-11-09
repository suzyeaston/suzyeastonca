'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ROOT=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(ROOT,file),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
function stub(){
  class V{set(x,y,z){this.x=x;this.y=y;this.z=z;return this;}}
  class G{constructor(...args){this.args=args;}rotateX(){return this;}setAttribute(){return this;}}
  class Sh{moveTo(){}lineTo(){}quadraticCurveTo(){}closePath(){}}
  class M{constructor(options){this.options=options;}}
  class N{constructor(geo,mat){this.geometry=geo;this.material=mat;this.position=new V();this.rotation={};this.scale=new V();this.children=[];this.name='';this.userData={};}}
  class Group{constructor(){this.position=new V();this.rotation={};this.children=[];this.name='';this.userData={};}add(child){this.children.push(child);}}
  class Texture{constructor(){this.repeat={set(){}};}}
  return {
    Group,Mesh:N,Shape:Sh,CanvasTexture:Texture,Float32BufferAttribute:G,
    BoxGeometry:G,CylinderGeometry:G,ConeGeometry:G,PlaneGeometry:G,ShapeGeometry:G,BufferGeometry:G,
    MeshStandardMaterial:M,MeshBasicMaterial:M,LineBasicMaterial:M,LineSegments:N,
    SRGBColorSpace:'srgb',DoubleSide:2,RepeatWrapping:1000,
  };
}
function boot(){
 const ctx={fillRect(){},clearRect(){},fillText(){}};
 const win={document:{createElement:()=>({width:0,height:0,getContext:()=>ctx})}};
 new Function('window',read('js/gastown-harbour-vista.js'))(win);
 new Function('window',read('js/gastown-waterfront-places.js'))(win);
 return win;
}
function all(root){
 const out=[root];for(let i=0;i<out.length;i++)out.push(...(out[i].children||[]));
 return out;
}
test('waterfront rail yard is a recognisable east-west working freight yard',()=>{
 const view=boot().GastownHarbourVista.create(stub(),world);
 const nodes=all(view);
 assert.ok(nodes.some(n=>n.name==='Fenced waterfront rail service yard (non-walkable)'));
 const rails=nodes.filter(n=>n.name==='Yard rail pair');
 assert.ok(rails.length>=8);
 for(const rail of rails){
   assert.ok(rail.geometry.args[0]>rail.geometry.args[2]*30,
     'freight rail must run east-west, not north-south');
 }
 assert.ok(nodes.filter(n=>n.name==='CP freight railcar in waterfront yard 1').length===1);
 assert.ok(nodes.filter(n=>/CP freight railcar in waterfront yard/.test(n.name)).length>=3);
 assert.ok(nodes.some(n=>n.name==='Waterfront Station red elevated transfer walkway'));
 assert.ok(nodes.some(n=>n.name==='Waterfront SeaBus south terminal building'));
});
test('Canada Place sightline has sparse, single-height trains and no near-fence container wall',()=>{
  const root=boot().GastownHarbourVista.create(stub(),world);
  const nodes=all(root);
  const containers=nodes.filter(n=>n.name==='Visible Station East freight container');
  assert.ok(containers.length>0,'retain some active-freight visual context');
  assert.ok(containers.length<=5,'leave an open western view toward the cruise ship');
  assert.equal(nodes.filter(n=>n.name==='Double-stack freight upper container').length,0);
  assert.ok(nodes.some(n=>n.name==='Representative cruise ship at Canada Place east berth'));
  const railcars=nodes.filter(n=>/^CP freight railcar in waterfront yard/.test(n.name));
  assert.ok(railcars.length>=3,'keep a recognisable railway');
});

test('inlet is visibly water and includes a readable, moving SeaBus',()=>{
 const vista=boot().GastownHarbourVista;
 const root=vista.create(stub(),world);
 const nodes=all(root);
 const water=nodes.find(n=>n.name==='Burrard Inlet water');
 assert.ok(water);
 assert.equal(water.material.options.color,0x266d91);
 const ferry=nodes.find(n=>n.name==='North Vancouver SeaBus silhouette');
 assert.ok(ferry,'ferry should be near the ferry terminal');
 const ferryParts=all(ferry).map(n=>n.name);
 for(const name of ['SeaBus twin catamaran starboard hull',
    'SeaBus twin catamaran port hull','SeaBus golden hull stripe',
    'SeaBus blue passenger window belt','SeaBus broad front windscreen']){
   assert.ok(ferryParts.includes(name),name+' missing');
 }
 const start=ferry.position.x;
 vista.animate(root,41.5);
 assert.notEqual(ferry.position.x,start,'SeaBus moves rather than being an inert box');
 assert.ok(Math.abs(ferry.position.x)<40,'SeaBus remains near the illustrated terminal');
});
test('fence camera stands back from rails so the harbour is visible',()=>{
 const places=boot().GastownWaterfrontPlaces;
 const lot=world.parkingLot;
 const pose=places.lookoutFor(world,boot().GastownHarbourVista.positionFor(world));
 const mid={x:(lot.polygon[4].x+lot.polygon[5].x)/2,
    z:(lot.polygon[4].z+lot.polygon[5].z)/2};
 assert.ok(Math.hypot(pose.x-mid.x,pose.z-mid.z)>5.5,
   'lookout must not put lens against a 2.2m-high fence');
 assert.ok(places.isWalkableLot(world,pose));
});
test('third-person camera avoids a fixed boom into the station wall',()=>{
 const sim=read('js/gastown-sim.js');
 assert.match(sim,/function updateThirdPersonCameraPose\(force\)/);
 assert.match(sim,/intersectObjects\(thirdPersonCameraBlockers,true\)/);
 assert.match(sim,/getGroundY\(\)-player.position.y/);
 assert.match(sim,/thirdPersonCameraBlockers.push\(stationExterior\)/);
 assert.match(sim,/const shoulders = \[0, -0\.85, 0\.85, -1\.55, 1\.55\]/);
 assert.match(sim,/state\.avatarCameraBoom = best\.boom/);
 assert.doesNotMatch(sim,/camera.position.set\([^\n]*4\.7/);
 assert.match(sim,/harbourVista.animate\(state.harbourVistaRoot,time\/1000\)/);
});
