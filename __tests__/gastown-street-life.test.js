const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const streetLife = require('../js/gastown-street-life.js');
const read = (file) => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');

test('Station East has reproducible walking characters and two interaction types', () => {
  const world = JSON.parse(read('assets/world/gastown-water-street.json'));
  const station = { sideEntranceFor: () => ({ approach: { x: 24, z: 53 } }) };
  const people = streetLife.peopleFor(world, station);
  assert.equal(people.length, 6);
  assert.equal(people.filter(p => p.voiceCue === 'parking-smalltalk').length, 2);
  assert.equal(people.filter(p => p.voiceCue === 'parking-highfive').length, 2);
  for (const person of people) {
    assert.ok(Array.isArray(person.patrol) && person.patrol.length >= 2);
    assert.equal(person.role, 'commuter');
    assert.ok(person.patrol.every(point => Number.isFinite(point.x) && Number.isFinite(point.z)));
  }
  assert.deepEqual(streetLife.peopleFor(world, station), people);
  assert.deepEqual(streetLife.peopleFor(null, station), []);
});

test('the simulator includes the authored street life and never draws generic parking/heritage landmark towers', () => {
  const src = read('js/gastown-sim.js');
  const php = read('functions.php');
  assert.match(src, /streetLife\.create\(THREE, world\)/);
  assert.match(src, /streetLifeScene\.update\(time \/ 1000\)/);
  assert.match(src, /interiorCommuters\.concat\(platformCommuters, stationCommuters, lotWalkers, world\.npcs \|\| \[\]\)/);
  assert.match(src, /'view_axis', 'parking', 'heritage_frontage'/);
  assert.match(php, /'se-gastown-street-life'/);
  assert.match(src, /'parking-highfive'/);
  assert.match(src, /'parking-smalltalk'/);
});

test('scroll-to-zoom affects the playable camera and minimap, with Alt+wheel reserved for pitch', () => {
  const src = read('js/gastown-sim.js');
  const wheel = src.slice(src.indexOf('function onWheelLook(event) {'), src.indexOf('function setMoveKey(',src.indexOf('function onWheelLook(event) {')));
  assert.match(wheel, /setMinimapZoom\(minimapState\.zoom/);
  assert.match(wheel, /event\.altKey/);
  assert.match(src, /minimapCanvas\.addEventListener\('wheel', onWheelLook, \{ passive: false \}\)/);
  assert.match(src, /updateThirdPersonCameraPose\(true\)/);
});

test('the authored characters have locally available dialog even without online voice or conversation endpoints', () => {
  const dialogs = JSON.parse(read('assets/dialog/gastown.json'));
  for (const key of ['parking_small_talk', 'parking_high_five']) {
    assert.ok(dialogs[key]);
    assert.ok(dialogs[key].lines.length >= 2);
  }
  const src = read('js/gastown-sim.js');
  assert.match(src, /enableNpcVoiceSnippets: true/);
  assert.match(src, /Another ticket\?/);
  assert.match(src, /Impark Lot 531 complaint/i);
});

test('Impark conversations remain usable without floating comic text boxes',()=>{
 const src=read('js/gastown-sim.js');
 const dialogs=JSON.parse(read('assets/dialog/gastown.json'));
 assert.doesNotMatch(src,/streetLife\.bubble\(THREE/);
 assert.match(src,/npcState.voiceCue \|\| ''/);
 assert.match(dialogs.parking_small_talk.lines.join(' '),/ticketed|ticket/i);
 assert.match(dialogs.parking_high_five.lines.join(' '),/ticket/i);
 assert.doesNotMatch(dialogs.parking_small_talk.lines.join(' '),/Steam Clock/);
});


function makeStreetLifeThreeStub() {
  class Node {
    constructor() {
      this.children=[];this.userData={};this.visible=true;this.rotation={x:0,y:0,z:0};
      this.position={x:0,y:0,z:0,set(x,y,z){this.x=x;this.y=y;this.z=z;}};
    }
    add(child){this.children.push(child);}
  }
  class Group extends Node {}
  class Mesh extends Node {constructor(geometry,material){super();this.geometry=geometry;this.material=material;}}
  class Shape {}
  return {
    Group,Mesh,Shape,
    BoxGeometry:class{},CylinderGeometry:class{},SphereGeometry:class{},
    MeshStandardMaterial:class{},MeshBasicMaterial:class{}
  };
}

function offsetFromMappedRoad(world,position){
  const line=world.route.centerline.slice(0,9);
  let closest={distance:Infinity,side:0};
  for(let i=1;i<line.length;i++){
    const a=line[i-1],b=line[i],dx=b.x-a.x,dz=b.z-a.z;
    const t=Math.max(0,Math.min(1,((position.x-a.x)*dx+(position.z-a.z)*dz)/(dx*dx+dz*dz)));
    const x=a.x+t*dx,z=a.z+t*dz;
    const distance=Math.hypot(position.x-x,position.z-z);
    // Positive is the RIGHT of the SE/eastbound mapped centreline.
    const side=(dx*(position.z-z)-dz*(position.x-x))/Math.hypot(dx,dz);
    if(distance<closest.distance)closest={distance,side};
  }
  return closest.side;
}

test('mapped Cordova traffic keeps right on both sides of the road',()=>{
  const world=JSON.parse(read('assets/world/gastown-water-street.json'));
  const scene=streetLife.create(makeStreetLifeThreeStub(),world);
  const bus=scene.root.children.find(node=>node.name.includes('Route 3 Waterfront electric trolley'));
  const car=scene.root.children.find(node=>node.name==='Illustrated moving street car 1');
  assert.ok(bus&&car);
  scene.update(0);
  assert.ok(offsetFromMappedRoad(world,bus.position)>1.5,'eastbound bus stays SOUTH/right of centreline');
  assert.ok(offsetFromMappedRoad(world,car.position)<-1.5,'westbound car stays NORTH/right of its direction');
  assert.equal(bus.userData.doorPanels.length,4,'two paired doors are on the curb side');
  assert.ok(bus.userData.doorPanels.every(panel=>panel.mesh.position.x>0));
});

test('Harbour Centre bus stop cycles bus dwell, alighting, boarding and next waiting queue',()=>{
  const world=JSON.parse(read('assets/world/gastown-water-street.json'));
  const scene=streetLife.create(makeStreetLifeThreeStub(),world);
  const root=scene.root,stop=root.userData.busStop;
  assert.equal(stop.name,'Harbour Centre eastbound bus stop');
  assert.equal(stop.curbSide,'right');
  assert.ok(offsetFromMappedRoad(world,stop.position)>5,'stop is on Harbour Centre curb');
  const furniture=root.children.find(node=>node.name==='Harbour Centre eastbound bus stop');
  assert.ok(furniture);
  assert.ok(furniture.children.some(child=>child.name.includes('Route 3 bus stop sign')));
  const board=furniture.children.find(child=>child.name==='Harbour Centre boarding commuter 1');
  const alight=furniture.children.find(child=>child.name==='Harbour Centre alighting commuter 1');
  const bus=root.children.find(node=>node.name.includes('Route 3 Waterfront electric trolley'));
  const arrival=(stop.distance-20)/4.1;
  scene.update(arrival+1.0);
  assert.equal(bus.userData.atStop,true);
  assert.ok(bus.userData.doorOpen>0);
  assert.equal(alight.visible,true,'first passenger visibly steps off');
  const stoppedAt={x:bus.position.x,z:bus.position.z};
  scene.update(arrival+5.0);
  assert.equal(bus.position.x,stoppedAt.x);
  assert.equal(bus.position.z,stoppedAt.z);
  assert.equal(board.visible,false,'waiting commuter boards and is no longer on curb');
  scene.update(arrival+8.3);
  assert.equal(bus.userData.atStop,false);
  assert.equal(bus.userData.doorOpen,0);
  assert.equal(board.visible,true,'a new waiting queue appears for the next bus');
  assert.equal(alight.visible,false,'alighting guests have left the stop cycle');
});
