'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
function three(){
  class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this}}
  class Geo{constructor(...args){this.args=args}}
  class Shape{moveTo(){}lineTo(){}absarc(){}}
  class Mat{constructor(o){this.options=o}}
  class N{constructor(g,m){this.geometry=g;this.material=m;this.children=[];this.name='';this.rotation={};this.position=new Vec();this.scale=new Vec();this.userData={}}add(n){this.children.push(n)}}
  class Texture{constructor(){this.repeat={set(){}}}}
  return {Group:N,Mesh:N,BoxGeometry:Geo,CylinderGeometry:Geo,
    PlaneGeometry:Geo,TorusGeometry:Geo,SphereGeometry:Geo,ShapeGeometry:Geo,
    Shape,MeshBasicMaterial:Mat,MeshStandardMaterial:Mat,CanvasTexture:Texture,
    RepeatWrapping:1,SRGBColorSpace:'srgb',DoubleSide:2};
}
function stationScene(){
  const fakeWindow={document:{createElement:()=>({getContext:()=>({
    fillRect(){},clearRect(){},fillText(){}
  })})}};
  new Function('window',read('js/gastown-waterfront-station.js'))(fakeWindow);
  return {model:fakeWindow.GastownWaterfrontStation,
    root:fakeWindow.GastownWaterfrontStation.create(three(),world)};
}
function wceScene(){
  const fakeWindow={document:{createElement:()=>({getContext:()=>({
    fillRect(){},fillText(){}
  })})}};
  new Function('window',read('js/gastown-west-coast-express.js'))(fakeWindow);
  return fakeWindow.GastownWestCoastExpress.create(three(),world);
}
test('photo-led hall has bright cream plaster walls, rose upper panels and natural-light sashes',()=>{
  const {root}=stationScene();
  const named=n=>root.children.filter(child=>child.name===n);
  assert.ok(named('Waterfront ivory interior wall bay').length>=8);
  assert.ok(named('Waterfront rose upper heritage frieze bay').length>=8);
  assert.ok(named('Heritage landscape mural inset').length>=8);
  assert.ok(named('Waterfront tall station window daylight').length>=5);
  assert.ok(named('Waterfront sash window white mullion').length>=10);
  assert.ok(named('Waterfront shopfront glass doors').length>=3);
  assert.ok(named('Waterfront gold-rimmed heritage clock face').length===1);
  assert.ok(named('Waterfront Rogue amber restaurant interior').length===2);
  const walls=named('Waterfront ivory interior wall bay');
  assert.ok(walls.every(n=>n.material.options.color===0xe8decf),
    'masonry remains exterior while inner finish is ivory, not bare red');
  assert.equal(root.userData.heritageInteriorPhotoPass2026,true);
});
test('photographed retail side is opposite tall windows and parking stays east of station',()=>{
  const {root,model}=stationScene();
  const pane=root.children.find(n=>n.name==='Waterfront tall station window daylight');
  const retail=root.children.find(n=>n.name==='Waterfront shopfront glass doors');
  assert.ok(pane.position.x<0,'tall windows on west side');
  assert.ok(retail.position.x>0,'retail/shopfront on east side');
  const interiorSign=root.children.find(n=>n.name==='CORDOVA EXIT / STATION EAST LOT');
  assert.ok(interiorSign && interiorSign.position.x>0,
    'east is to the left when standing inside facing Cordova');
  assert.ok(Math.abs(interiorSign.rotation.y-Math.PI)<1e-8,
    'lot exit sign faces into the hall when walking toward Cordova');
  const p=model.sideEntranceFor(world);
  assert.ok(p.approach.x>world.station.x,
    'actual Station East lot remains east of station: no geographic mirror');
  assert.ok(model.isWalkableInterior(world,p.interior));
});
test('WCE canopy and blue banner no longer slice through heritage hall',()=>{
  const root=wceScene();
  const roofs=root.children.filter(n=>n.name==='West Coast Express platform roof canopy'
    || n.name==='WCE covered west boarding canopy');
  assert.equal(roofs.length,2,'two disjoint canopies span behind the station');
  const east=roofs.find(n=>n.name==='West Coast Express platform roof canopy');
  const west=roofs.find(n=>n.name==='WCE covered west boarding canopy');
  assert.ok(east.position.x-east.geometry.args[0]/2>=3);
  assert.ok(west.position.x+west.geometry.args[0]/2<=-35);
  const sign=root.children.find(n=>n.name==='WEST COAST EXPRESS · WATERFRONT');
  assert.ok(sign && sign.position.x>=20,
    'the giant blue sign moved behind the station, outside the concourse');
  const platform=root.children.find(n=>n.name==='West Coast Express Waterfront boarding platform');
  assert.ok(platform.geometry.args[0]>=110,
    'the below-grade stopped train retains its boarding apron');
  assert.ok(root.userData.canopyDoesNotIntersectHeritageHall);
});
