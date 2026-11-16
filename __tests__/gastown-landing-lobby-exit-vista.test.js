'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
const placesSource=read('js/gastown-waterfront-places.js');
const sim=read('js/gastown-sim.js');

function mockThree(){
 class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this;}}
 class Geo{constructor(...args){this.args=args;}rotateX(){return this;}}
 class Shape{moveTo(){}lineTo(){}closePath(){}}
 class Material{constructor(options){this.options=options;}}
 class Node{
  constructor(geometry,material){
   this.geometry=geometry;this.material=material;this.name='';this.visible=true;
   this.children=[];this.userData={};this.position=new Vec();this.rotation={};this.scale=new Vec();
  }
  add(n){this.children.push(n);}
 }
 class Tex{constructor(){this.repeat={set(){}};}}
 return {Group:Node,Mesh:Node,BoxGeometry:Geo,PlaneGeometry:Geo,SphereGeometry:Geo,
  CylinderGeometry:Geo,TorusGeometry:Geo,ExtrudeGeometry:Geo,Shape,
  MeshStandardMaterial:Material,MeshBasicMaterial:Material,CanvasTexture:Tex,
  SRGBColorSpace:'srgb',DoubleSide:2};
}
function loadPlaces(){
 const ctx={fillRect(){},clearRect(){},fillText(){}};
 const win={document:{createElement:()=>({getContext:()=>ctx})}};
 new Function('window',placesSource)(win);
 return win.GastownWaterfrontPlaces;
}
function objects(group){
 const all=[group];for(let i=0;i<all.length;i++)all.push(...(all[i].children||[]));
 return all;
}
const named=(group,name)=>objects(group).filter(n=>n.name===name);
function exitHarness(stage='lobby',position={x:850,z:858.2}){
 const start=sim.indexOf('  function isLandingLobbyStage()');
 const end=sim.indexOf('  function updateLandingEscort(delta,nowSeconds) {',start);
 assert.ok(start>0&&end>start);
 const section=sim.slice(start,end);
 const state={world:{landing:{}},landingLab:{
  stage,inside:true,hasLeftEntry:false,outdoorGreeting:true,greetPlayed:true,
  nearLobbyExit:true,lastNear:true,
  outsideEscort:{position:{set(x,y,z){this.x=x;this.y=y;this.z=z;}},visible:false}
 }};
 const player={position:{...position}};
 const portal={threshold:{x:75,z:120},outward:{x:-0.5,z:0.5},
  elevatorCar:{x:880,z:850},roomExit:{x:800,z:809.2}};
 const status=[];
 const landingTravel=(next,point,message)=>{
  state.landingLab.stage=next;state.landingLab.inside=next!=='street';
  player.position={...point};status.push(message);return true;
 };
 const places={landingEntranceFor:()=>portal};
 const startLandingJourney=()=>true;
 const api=new Function('state','player','landingTravel','performance',
  'waterfrontPlaces','startLandingJourney',section+
  '\nreturn {interactWithLanding,exitLandingToWaterStreet,returnLandingLabToLift,isLandingLobbyStage};')(
  state,player,landingTravel,{now:()=>11000},places,startLandingJourney);
 return {state,player,portal,status,...api};
}

test('ground-floor lobby, escort and elevator-return all allow E exit to Water Street',()=>{
 for(const stage of ['lobby','escort','lobby-return']){
  const h=exitHarness(stage);
  assert.equal(h.interactWithLanding(),true,stage);
  assert.equal(h.state.landingLab.stage,'street');
  assert.equal(h.state.landingLab.inside,false);
  assert.ok(h.status[0].includes('glass doors'));
  assert.equal(h.state.landingLab.outsideEscort.visible,true,
   'KK should be outside after exit from '+stage);
  assert.ok(h.state.landingLab.reentryBlockedUntil>11);
 }
});

test('inside exit cannot trigger from elevator, far from entrance, or from lab window',()=>{
 const far=exitHarness('escort',{x:847,z:852});
 assert.equal(far.interactWithLanding(),false);
 assert.equal(far.state.landingLab.inside,true);
 const lift=exitHarness('lift');
 assert.equal(lift.interactWithLanding(),false);
 assert.equal(lift.state.landingLab.stage,'lift');
 const lab=exitHarness('lab',{x:802,z:798});
 assert.equal(lab.interactWithLanding(),false);
 assert.equal(lab.state.landingLab.stage,'lab');
 const nearLabDoor=exitHarness('lab',{x:800,z:809.15});
 assert.equal(nearLabDoor.interactWithLanding(),true);
 assert.equal(nearLabDoor.state.landingLab.stage,'return-lift');
 assert.equal(nearLabDoor.player.position.x,880);
});

test('walking toward the entrance handles the exit during KK escort, with an E fallback',()=>{
 assert.match(sim,/if\(isLandingLobbyStage\(\)\)\{/);
 assert.match(sim,/if\(player\.position\.z<855\.8\)lab\.hasLeftEntry=true/);
 assert.match(sim,/if\(atExit&&lab\.hasLeftEntry&&elapsed>0\.65\)/);
 assert.match(sim,/exitLandingToWaterStreet\(portal\)/);
 assert.match(sim,/if \(state\.landingLab\.inside\) \{/);
 assert.match(sim,/if\(!interactWithLanding\(\)\)/);
 assert.match(sim,/To leave, walk back to the green carpet and glass entrance/);
 assert.match(sim,/roomExit,1\.55/);
});

test('Landing lobby has green carpet, 375 brass lettering, open glass and harbour end window',()=>{
 const lobby=loadPlaces().createLandingJourney(mockThree());
 assert.equal(lobby.userData.exitDoor.x,850);
 assert.equal(lobby.userData.exitDoor.z,858.25);
 for(const n of [
  'The Landing lobby stone floor','The Landing green carpet entrance runner',
  'The Landing entrance green doormat','Glass main entrance left hinged door',
  'Glass main entrance right hinged door','The Landing interior 375 etched brass entrance lintel',
  'The Landing north harbour-facing lobby window','The Landing lobby green reception plant',
  'EXIT TO WATER STREET  ↓']){
  assert.ok(named(lobby,n).length,n);
 }
 assert.ok(objects(lobby).length<240,'lobby and its pre-existing detailed KK avatar should stay lightweight');
});

test('sixth floor builds without ReferenceError and has both harbour and Woodwards-facing windows',()=>{
 const lab=loadPlaces().createLabInterior(mockThree());
 assert.equal(lab.userData.fictionalInterior,true);
 for(const n of [
  'Sixth floor elevator bronze header',
  'Multimodal Lab timber ceiling',
  'MML north sky over parking and Burrard Inlet',
  'Station East parking lot upper roofline',
  'Waterfront container crane angled boom',
  'Burrard Inlet blue horizon',
  "Woodward's district distant red-brick silhouette",
  'North-facing sixth-floor window glazing',
  'Multimodal Lab east picture-window glazing',
  'MML 6F green carpet elevator threshold']){
  assert.ok(named(lab,n).length,n);
 }
 assert.equal(lab.userData.windowVistas.length,5);
 assert.ok(objects(lab).length<270,'including existing coder and KK avatars, preserve the 8 GB low-poly budget');
});

test('exterior Landing contains real-footprint sixth-floor harbour windows above the parking',()=>{
 const b=loadPlaces().createLanding(mockThree(),world);
 const windows=named(b,'The Landing sixth-floor harbour-facing MML window');
 assert.equal(windows.length,7);
 assert.ok(windows.every(w=>w.position.y===18.85));
 assert.equal(b.userData.sixthFloorWindowCount,7);
 assert.match(b.userData.sixthFloorWindowsFace,/Station East parking/);
 // Preserve the physical heritage building height/footprint, not a new tower.
 assert.equal(named(b,'The Landing seven-storey civic footprint').length,1);
});
