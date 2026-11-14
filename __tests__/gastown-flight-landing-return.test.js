'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const sim=read('js/gastown-sim.js');
const places=read('js/gastown-waterfront-places.js');
const life=read('js/gastown-street-life.js');

test('second SUZYFLY is not eaten by LETSGO and safely descends into lot',()=>{
  assert.match(sim,/if\(letter==='l'&&state\.flight\.secretBuffer\.endsWith\('suzyf'\)\)/);
  assert.match(sim,/if\(fly\.enabled\)\{\s*if\(!fly\.landing\)beginFlightLanding\(\)/);
  assert.match(sim,/function findParkingLandingTarget\(\)/);
  assert.match(sim,/waterfrontPlaces\.isWalkableLot\(state\.world,p\)/);
  assert.match(sim,/function updateFlightLanding\(delta\)/);
  assert.match(sim,/const step=Math\.min\(distance,delta\*18\)/);
  assert.match(sim,/player\.position\.y=Math\.max\(ground\+safeHeight,player\.position\.y-delta\*9\.5\)/);
  assert.match(sim,/if\(remaining<0\.04&&fly\.altitude<0\.04\)/);
  assert.match(sim,/updateFlightLanding\(delta\);\s*const currentWeather/);
  assert.match(sim,/fly\.enabled=false;fly\.altitude=0;fly\.vertical=0;fly\.landing=null/);
  assert.doesNotMatch(sim,/fly\.altitude=0;fly\.vertical=0;fly\.up=false;fly\.down=false;\s*player\.position\.y=getGroundY\(\)/);
});

test('parking aisle candidate stays walkable among parked cars',()=>{
  const world=JSON.parse(read('assets/world/gastown-water-street.json'));
  const win={document:{createElement:()=>({getContext:()=>({fillRect(){},fillText(){}})})}};
  new Function('window',places)(win);
  const p=win.GastownWaterfrontPlaces,entry=p.entranceFor(world.parkingLot);
  const fence=p.lookoutFor(world,null);
  const targets=[0.44,0.38,0.5,0.32,0.56,0.25].map(t=>({
    x:entry.x+(fence.x-entry.x)*t,z:entry.z+(fence.z-entry.z)*t
  }));
  assert.ok(targets.some(pos=>p.isWalkableLot(world,pos)));
});

test('Landing returns via sixth-floor elevator and lobby before Water Street',()=>{
  for(const stage of ['return-lift','riding-down','lobby-return'])
    assert.ok(sim.includes("lab.stage==='"+stage+"'"),stage);
  assert.match(sim,/landingTravel\('return-lift',portal\.elevatorCar/);
  assert.match(sim,/landingTravel\('lobby-return',\{x:850,z:851\.6\}/);
  assert.match(sim,/landingTravel\('street',outside,'Back on Water Street/);
  assert.match(sim,/lab\.reentryBlockedUntil=nowSeconds\+8/);
  assert.match(sim,/lab\.outsideEscort\.visible=true/);
  assert.match(sim,/\['lift','riding','return-lift','riding-down'\]\.includes\(stage\)/);
  assert.match(places,/Sixth floor return elevator left open glass leaf/);
  assert.match(places,/Sixth floor return elevator right open glass leaf/);
  assert.match(places,/ELEVATOR DOWN TO WATER STREET/);
  assert.doesNotMatch(sim,/if\(elapsed>2\.7&&near\(portal\.roomExit,1\.0\)\)\{\s*landingTravel\('street'/);
});

test('detailed Water Street facade treatment retains actual GIS footprint IDs',()=>{
  assert.match(life,/wall\.userData\.mappedFootprintId=building\.id/);
  assert.match(life,/wall\.userData\.architecturalTypology=typology/);
  assert.match(life,/heritageReferences\[typology\]/);
  for(const part of ['Historic cast iron storefront column','Gastown corbelled parapet cap',
    'Historic cornice bracket','Warehouse sandstone belt course',
    'Two-over-two wood sash meeting rail','Historic segmental stone window lintel'])
    assert.ok(life.includes(part),part);
  assert.match(life,/not an exact address assignment/);
});
