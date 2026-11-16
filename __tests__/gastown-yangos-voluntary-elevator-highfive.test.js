'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const sim=read('js/gastown-sim.js');
const places=read('js/gastown-waterfront-places.js');

function three(){
 class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this;}}
 class Geo{constructor(...args){this.args=args;}rotateX(){return this;}}
 class Mat{constructor(p){this.params=p;}}
 class Node{
  constructor(geometry,material){
   this.geometry=geometry;this.material=material;this.name='';
   this.children=[];this.userData={};this.visible=true;
   this.position=new Vec();this.rotation={};this.scale=new Vec();
  }
  add(mesh){this.children.push(mesh);}
 }
 class Tex{constructor(){this.repeat={set(){}};}}
 return {Group:Node,Mesh:Node,BoxGeometry:Geo,PlaneGeometry:Geo,
  SphereGeometry:Geo,CylinderGeometry:Geo,TorusGeometry:Geo,
  MeshBasicMaterial:Mat,MeshStandardMaterial:Mat,CanvasTexture:Tex,
  DoubleSide:2,SRGBColorSpace:'srgb'};
}
function loadPlaces(){
 const ctx={clearRect(){},fillRect(){},fillText(){}};
 const w={document:{createElement:()=>({getContext:()=>ctx})}};
 new Function('window',places)(w);
 return w.GastownWaterfrontPlaces;
}
function descendants(g){
 const list=[g];for(let i=0;i<list.length;i++)list.push(...(list[i].children||[]));
 return list;
}
function controllerHarness(stage='lab',start={x:800,z:804.9}){
 const startCode=sim.indexOf('  function isLandingLobbyStage()');
 const endCode=sim.indexOf('  function updateLandingHint() {',startCode);
 assert.ok(startCode>0&&endCode>startCode);
 const code=sim.slice(startCode,endCode);
 const THREE=three(),p=loadPlaces();
 const labRoom=p.createLabInterior(THREE),lobby=p.createLandingJourney(THREE);
 const player={position:{...start}};
 const states=[];
 const lab={stage,inside:true,stageSince:0,fade:0,farewellAt:0,
  farewellSpoken:false,yangosGreeted:false,yangosTalked:false,lastNearLabLift:false,
  lastGuardNear:false,nearLobbyExit:false,
  guardHighFiveAt:0,roomRoot:labRoom,journeyRoot:lobby,
  hasLeftEntry:true,calledLift:false,escortPaused:false,
  outdoorGreeting:true,greetPlayed:true,
  outsideEscort:{position:{set(){}},visible:false}};
 const state={world:{},landingLab:lab,isRunning:true};
 const portal={roomExit:{x:800,z:809.2},elevatorCar:{x:880,z:850},
  threshold:{x:70,z:120},outward:{x:0,z:1},lobbySpawn:{x:850,z:858}};
 const status=[];
 let now=11;
 const performance={now:()=>now*1000};
 const landingTravel=(next,point,description)=>{
  lab.stage=next;lab.inside=next!=='street';lab.stageSince=now;
  player.position={...point};states.push({stage:next,status:description});
  return true;
 };
 const landingSpeak=text=>status.push({who:'KK',text});
 const landingNpcSpeak=(who,text)=>status.push({who,text});
 const setStatus=text=>status.push({who:'status',text});
 const waterfrontPlaces={landingEntranceFor:()=>portal};
 const startLandingJourney=()=>false;
 const fn=new Function('state','player','landingTravel','performance',
   'waterfrontPlaces','startLandingJourney','landingSpeak',
   'landingNpcSpeak','setStatus',code+
   '\nreturn {interactWithLanding,updateLandingEscort,returnLandingLabToLift,'+
   'highFiveLandingGuard,talkToYangos};');
 const controls=fn(state,player,landingTravel,performance,waterfrontPlaces,
   startLandingJourney,landingSpeak,landingNpcSpeak,setStatus);
 return {state,player,lab,room:labRoom,lobby,portal,status,states,
   setNow:v=>{now=v;},...controls};
}

test('KK stays upstairs, taps Yangos to cover and greets Suzy without triggering elevator',()=>{
 const portal=loadPlaces().landingEntranceFor(
  JSON.parse(read('assets/world/gastown-water-street.json')));
 assert.ok(Math.hypot(portal.roomSpawn.x-portal.roomExit.x,
  portal.roomSpawn.z-portal.roomExit.z)>4,'room spawn clear of return lift');
 const h=controllerHarness();
 h.room.userData.greeter.visible=true;
 h.room.userData.greeter.position.set(800.9,0,807);
 h.setNow(1.1);h.updateLandingEscort(0.016,1.1);
 assert.equal(h.lab.stage,'lab');
 assert.equal(h.status.filter(x=>x.who==='KK').length,1);
 h.setNow(4.4);h.updateLandingEscort(0.016,4.4);
 assert.equal(h.lab.kkCoveredByYangos,true,'KK asks Yangos to cover him');
 assert.ok(h.room.userData.greeter.userData.tapArm.rotation.z>0.5,
  'KK visibly taps Yangos on the shoulder');
 assert.ok(h.status.some(x=>/taps Yangos on the shoulder/.test(x.text)));
 h.setNow(5.9);h.updateLandingEscort(0.016,5.9);
 assert.equal(h.room.userData.greeter.visible,true,'KK remains upstairs');
 assert.equal(h.room.userData.greeter.position.x,803.2,
  'KK takes up a position beside Yangos rather than leaving');
 assert.equal(h.lab.stage,'lab','handoff must not trigger elevator');
 assert.equal(h.lab.yangosGreeted,true);
 assert.equal(h.status.filter(x=>x.who==='Yangos').length,1);
 assert.match(h.status.find(x=>x.who==='Yangos').text,/Welcome to the Multi-Modal Lab/);
 assert.equal(h.player.position.z,804.9,'Suzy stays in room');
 h.setNow(6.2);h.updateLandingEscort(0.016,6.2);
 assert.equal(h.status.filter(x=>x.who==='Yangos').length,1,'no repeated greeting');
 assert.equal(h.room.userData.greeter.visible,true,'KK stays visible after Yangos speaks');
 assert.equal(h.room.userData.greeter.userData.tapArm.rotation.z,0,
  'KK's tapping hand settles back down');
 assert.equal(h.states.length,0,'Suzy never leaves the sixth floor on handoff');
});

test('Even standing at return lift never triggers automatic descent; E does',()=>{
 const h=controllerHarness('lab',{x:800,z:809.1});
 h.lab.farewellSpoken=true;h.lab.yangosGreeted=true;
 for(const t of [3.0,6.0,9.0,20]){
  h.setNow(t);h.updateLandingEscort(0.016,t);
  assert.equal(h.lab.stage,'lab','walking near a door must never teleport');
 }
 assert.equal(h.states.length,0);
 assert.ok(h.status.some(x=>x.text?.includes('press E')));
 h.setNow(21);
 assert.equal(h.interactWithLanding(),true);
 assert.equal(h.lab.stage,'return-lift');
 assert.equal(h.player.position.x,880);
 h.setNow(23);h.updateLandingEscort(0.016,23);
 assert.equal(h.lab.stage,'riding-down');
 h.setNow(27);h.updateLandingEscort(0.016,27);
 assert.equal(h.lab.stage,'lobby-return');
});

test('Yangos can be greeted, lab remains explorable away from elevator',()=>{
 const h=controllerHarness('lab',{x:804.1,z:804.7});
 assert.equal(h.interactWithLanding(),false,'greeting waits for KK to leave');
 h.lab.yangosGreeted=true;
 assert.equal(h.interactWithLanding(),true);
 assert.equal(h.lab.stage,'lab');
 assert.equal(h.lab.yangosTalked,true);
 assert.equal(h.status.at(-1).who,'Yangos');
 const far=controllerHarness('lab',{x:798,z:797});
 far.lab.yangosGreeted=true;
 assert.equal(far.interactWithLanding(),false);
 assert.equal(far.lab.stage,'lab');
});

test('After taking the elevator down, E gives the guard a high five',()=>{
 const h=controllerHarness('lobby-return',{x:853.1,z:855.0});
 h.setNow(14);assert.equal(h.interactWithLanding(),true);
 assert.equal(h.lab.stage,'lobby-return','high five must not exit the building');
 assert.ok(h.status.at(-1).text.includes('HIGH FIVE'));
 h.setNow(14.18);h.updateLandingEscort(0.016,14.18);
 assert.ok(h.lobby.userData.securityHighFiveArm.rotation.z < -0.5,
  'guard hand should visibly rise');
 h.setNow(18);h.updateLandingEscort(0.016,18);
 assert.equal(h.lobby.userData.securityHighFiveArm.rotation.z,0,
  'high five animation settles');
 h.player.position={x:850,z:858.2};
 assert.equal(h.interactWithLanding(),true,'glass exit remains available');
 assert.equal(h.lab.stage,'street');
});

test('Yangos and security guard are actual distinct lightweight NPCs',()=>{
 const p=loadPlaces(),T=three();
 const lab=p.createLabInterior(T),lobby=p.createLandingJourney(T);
 const labMeshes=descendants(lab).map(n=>n.name);
 const lobbyMeshes=descendants(lobby).map(n=>n.name);
 assert.ok(lab.userData.yangos);
 assert.ok(lab.userData.yangosWave);
 assert.ok(lab.userData.greeter.userData.tapArm,'KK has an actual shoulder-tap joint');
 const armPieces=descendants(lab.userData.greeter.userData.tapArm).map(n=>n.name);
 assert.ok(armPieces.includes('KK tattooed forearm'));
 assert.ok(armPieces.includes('KK hand'));
 assert.ok(labMeshes.includes('Yangos | fictional Multimodal Lab welcome host'));
 assert.ok(labMeshes.includes('Yangos welcoming hand'));
 assert.ok(lobby.userData.securityGuard);
 assert.ok(lobby.userData.securityHighFiveArm);
 assert.ok(lobbyMeshes.includes('Security guard raised high-five hand'));
 assert.ok(descendants(lab).length<270);
 assert.ok(descendants(lobby).length<240);
 assert.match(sim,/if\(lab\.stage==='lobby-return'&&highFiveLandingGuard\(\)\)/);
 assert.doesNotMatch(sim,/if\(elapsed>2\.7&&near\(portal\.roomExit,1\.55\)\)/);
});
