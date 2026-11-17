'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root,file),'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

function loadStation() {
  const win={};
  vm.runInNewContext(read('js/gastown-waterfront-station.js'),{window:win,Math,Number});
  return win.GastownWaterfrontStation;
}

test('heritage arcade route bypasses Rogue via the left passage to the WCE apron',()=>{
  const station=loadStation();
  for(let z=5;z>=-35.1;z-=0.25){
    const pt=station.toWorld(world,-12,z);
    assert.equal(station.isWalkableInterior(world,pt),true,'left arcade at '+z);
  }
  for(let x=-12;x<=0;x+=0.25){
    assert.equal(station.isWalkableInterior(world,station.toWorld(world,x,-35.1)),
      true,'behind-Rogue transfer at local x='+x);
  }
  for(let z=-35.1;z>=-41;z-=0.25){
    assert.equal(station.isWalkableInterior(world,station.toWorld(world,0,z)),
      true,'WCE ramp at local z='+z);
  }
  // Do not walk through either of Rogue's front doors to reach the train.
  for (const x of [0,12]) {
    assert.equal(station.isWalkableInterior(world,station.toWorld(world,x,-33)),
      false,'Rogue facade is not transit access at local x='+x);
  }
  // The portal ends directly on the covered eastern WCE boarding apron.
  const exit=station.toWorld(world,0,-41);
  assert.ok(station.isWalkableWestCoastExpress(world,exit));
  for(let x=exit.x;x<=30;x+=0.25){
    assert.ok(station.isWalkableWestCoastExpress(world,{x,z:exit.z}),
      'platform approach at world x='+x);
  }
  assert.equal(station.isWalkableInterior(world,station.toWorld(world,8,-41)),false,
    'not allowed to punch through a closed rear wall');
});

test('walkability bounds protect track bed, rail yard and heritage building',()=>{
  const station=loadStation();
  assert.equal(station.isWalkableWestCoastExpress(world,{x:12,z:-11}),true);
  assert.equal(station.isWalkableWestCoastExpress(world,{x:12,z:-17}),false);
  assert.equal(station.isWalkableWestCoastExpress(world,{x:12,z:-5}),false);
  assert.equal(station.isWalkableWestCoastExpress(world,{x:-27,z:-9}),false,
    'the western illustrated platform overlaps the heritage shell');
  assert.equal(station.isWalkableInterior(world,station.toWorld(world,0,-42)),false);
  assert.equal(station.isWalkableInterior(world,station.toWorld(world,0,10)),false);
});

test('WCE transition follows continuous floor surface rather than teleporting',()=>{
  const station=loadStation();
  const sample=z=>station.surfaceYFor(world,station.toWorld(world,0,z));
  assert.equal(sample(-33),0);
  assert.ok(sample(-38)<-0.4 && sample(-38)>-0.6);
  assert.ok(Math.abs(sample(-41)+(6/6.1))<1e-8);
  assert.equal(station.surfaceYFor(world,{x:12,z:-10}),-1);
  assert.equal(station.surfaceYFor(world,station.toWorld(world,0,-10)),0);
});

test('interior and platform crowds occupy authored walking regions',()=>{
  const station=loadStation();
  const hall=station.interiorCommutersFor(world);
  const platform=station.platformCommutersFor(world);
  assert.ok(hall.length>=9,'heritage hall is populated');
  assert.ok(platform.length>=3,'covered boarding area is populated');
  assert.equal(new Set([...hall,...platform].map(n=>n.id)).size,hall.length+platform.length);
  assert.ok(hall.every(n=>n.role==='commuter'&&
    n.patrol.every(p=>station.isWalkableInterior(world,p))),
    'all indoor NPC waypoints are public concourse positions');
  assert.ok(platform.every(n=>n.patrol.every(p=>station.isWalkableWestCoastExpress(world,p))),
    'all platform NPCs are on the safe apron, not inside the heritage building');
});

test('station geometry and simulator implement the actual platform access',()=>{
  const station=read('js/gastown-waterfront-station.js');
  const wce=read('js/gastown-west-coast-express.js');
  const sim=read('js/gastown-sim.js');
  const names=[
    'Waterfront blue directional fascia',
    'Waterfront ticket machine enclosure',
    'Waterfront fare gate narrow housing',
    'Waterfront luminous upper concourse glazing',
    'West Coast Express concourse-to-platform continuous ramp',
    'WCE access glazed corridor wall',
    'West Coast Express fare-paid threshold canopy'
  ];
  for(const name of names)assert.ok(station.includes(name),name);
  assert.match(station,/if \(x !== -12\) box\('Concourse arch recessed opening'/);
  assert.match(sim,/stationModel\.interiorCommutersFor\(world\)/);
  assert.match(sim,/stationModel\.platformCommutersFor\(world\)/);
  assert.match(sim,/stationModel\.surfaceYFor\(state\.world,here\)/);
  assert.match(sim,/stationModel\.isWalkableWestCoastExpress\(state\.world, point\)/);
  assert.match(wce,/root\.userData\.noPublicRailAccess=true/);
  assert.match(wce,/platformWalkableEasternApron=true/);
});
