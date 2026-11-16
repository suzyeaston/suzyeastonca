'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const source=read('js/gastown-waterfront-places.js');
const simulator=read('js/gastown-sim.js');

function fakeThreeAndDom(){
 const canvases=[];
 class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this;}}
 class Geo{constructor(...args){this.args=args;}rotateX(){return this;}}
 class Mat{constructor(options){this.options=options;}}
 class Shape{moveTo(){}lineTo(){}closePath(){}}
 class Node{
  constructor(geo,mat){
   this.geometry=geo;this.material=mat;this.userData={};
   this.name='';this.visible=true;this.children=[];
   this.position=new Vec();this.rotation={};this.scale=new Vec();
  }
  add(child){this.children.push(child);}
  remove(child){this.children.splice(this.children.indexOf(child),1);}
 }
 class Tex{constructor(canvas){this.canvas=canvas;this.repeat={set(){}};this.needsUpdate=false;}}
 const THREE={
  Group:Node,Mesh:Node,BoxGeometry:Geo,PlaneGeometry:Geo,SphereGeometry:Geo,
  CylinderGeometry:Geo,TorusGeometry:Geo,ShapeGeometry:Geo,ExtrudeGeometry:Geo,
  Shape,CanvasTexture:Tex,MeshStandardMaterial:Mat,MeshBasicMaterial:Mat,
  DoubleSide:2,SRGBColorSpace:'srgb'
 };
 const win={document:{createElement(name){
  assert.equal(name,'canvas');
  const stats={rects:0,paths:0,texts:0,gradientStops:0};
  const ctx={
   fillRect(){stats.rects++;},fillText(){stats.texts++;},
   beginPath(){stats.paths++;},moveTo(){},bezierCurveTo(){},
   lineTo(){},stroke(){},ellipse(){},
   createRadialGradient(){return {addColorStop(){stats.gradientStops++;}}}
  };
  const canvas={width:0,height:0,getContext:()=>ctx,stats};
  canvases.push(canvas);
  return canvas;
 }}};
 new Function('window',source)(win);
 return {THREE,win,api:win.GastownWaterfrontPlaces,canvases};
}
function descendants(root){
 const all=[root];for(let i=0;i<all.length;i++)all.push(...(all[i].children||[]));
 return all;
}

test('June screening layout: giant LED wall, demo desk, microphone and 12 fictional attendees',()=>{
 const {THREE,api}=fakeThreeAndDom(),room=api.createLabInterior(THREE);
 const nodes=descendants(room),names=nodes.map(n=>n.name);
 assert.ok(names.includes('BC + AI Film Club live generative animation on Clear LED style wall'));
 assert.ok(names.includes('Film Club black Clear LED style screen enclosure'));
 assert.ok(names.includes('Film Club presenter demo station'));
 assert.ok(names.includes('Film Club presenter live workflow laptop display'));
 assert.ok(names.includes('Film Club floor microphone'));
 assert.ok(names.includes('BC + AI  /  FILM CLUB'));
 assert.equal(names.filter(n=>n==='BC + AI Film Club audience chair').length,9);
 assert.equal(names.filter(n=>n==='BC + AI Film Club seated attendee').length,9);
 assert.equal(names.filter(n=>n==='BC + AI Film Club standing attendee').length,2);
 assert.equal(names.filter(n=>n==='BC + AI Film Club fictional presenter').length,1);
 assert.equal(room.userData.filmClubAudience.length,9);
 assert.equal(room.userData.filmClubStanding.length,3);
 assert.ok(room.userData.filmClubSource.includes('June'));
 assert.ok(nodes.length<350,'keep 8GB laptop geometry bounded');
});

test('LED wall has an original moving texture, faces seats and throttles when inactive',()=>{
 const {THREE,api}=fakeThreeAndDom(),room=api.createLabInterior(THREE);
 const art=room.userData.filmClubScreen;
 assert.ok(art,'the LED animation should instantiate with the room');
 assert.equal(art.canvas.width,512);
 assert.equal(art.canvas.height,288);
 assert.ok(art.display.position.x<793,'screen is on the west wall facing east');
 assert.equal(art.display.rotation.y,Math.PI/2);
 assert.equal(art.frames,1,'first frame renders before player arrives');
 assert.equal(art.texture.needsUpdate,true);
 assert.ok(art.canvas.stats.paths>0,'show organic animated motion graphics');
 assert.ok(art.canvas.stats.gradientStops>=3,'screen has layered spectral colour');
 assert.equal(api.animateFilmClub(room,1),true);
 assert.equal(art.frames,2);
 assert.equal(api.animateFilmClub(room,1.02),false,'no 60fps canvas redraw');
 assert.equal(api.animateFilmClub(room,1.2),true);
 assert.equal(art.frames,3);
 room.visible=false;
 assert.equal(api.animateFilmClub(room,2),false,'nothing updates outside the lab');
 assert.equal(art.frames,3);
 assert.equal(art.display.material.options.map,art.texture);
});

test('scene preserves Yangos, KK, volunteer, elevator and harbour windows',()=>{
 const {THREE,api}=fakeThreeAndDom(),room=api.createLabInterior(THREE);
 const n=descendants(room).map(node=>node.name);
 assert.ok(room.userData.yangos,'Yangos keeps independent position');
 assert.ok(room.userData.coverHost,'KK can still tap the volunteer');
 assert.ok(room.userData.greeter,'KK remains upstairs');
 assert.ok(n.includes('ELEVATOR DOWN TO WATER STREET'));
 assert.ok(n.includes('North-facing sixth-floor window glazing'));
 assert.ok(n.includes('Multimodal Lab east picture-window glazing'));
 assert.equal(room.userData.windowVistas.length,5);
 for(const group of room.userData.filmClubAudience){
  assert.ok(group.position.z<806,'keep return-elevator walkway clear');
  assert.ok(group.position.x<802,'audience faces screen, not Yangos');
 }
});

test('screen animation uses existing render loop and has no autoplay audio/network media',()=>{
 assert.match(simulator,/waterfrontPlaces\.animateFilmClub\(state\.landingLab\.roomRoot,time\/1000\)/);
 assert.match(simulator,/state\.landingLab\.stage==='lab'/);
 assert.match(source,/seconds-playhead\.lastFrame<1\/12/);
 assert.doesNotMatch(source,/document\.createElement\('video'\)/);
 assert.doesNotMatch(source,/new Audio\(/);
 assert.doesNotMatch(source,/requestVideoFrameCallback/);
});
