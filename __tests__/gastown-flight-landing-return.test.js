'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const sim=read('js/gastown-sim.js');
const places=read('js/gastown-waterfront-places.js');
const life=read('js/gastown-street-life.js');

// Exercise the actual flight controller functions in isolation, not just
// their source text. The main simulator closure needs a WebGL/DOM runtime.
function flightHarness({x=120,z=150,y=24,safe=true}={}){
  const from=sim.indexOf('  function isSafeFlightLanding()');
  const to=sim.indexOf('  function movePlayer(delta) {',from);
  assert.ok(from>=0&&to>from,'flight controller functions are present');
  const code=sim.slice(from,to);
  const safeGround={value:safe};
  const hints={dataset:{},textContent:''};
  const statuses=[];
  const player={position:{x,y,z}};
  const state={
    flight:{enabled:true,altitude:y,vertical:0,secretBuffer:'',landing:null,
      up:false,down:false,unsafeLandingNotice:false},
    isRunning:true,world:{},
    lastSafePosition:{copy(pos){this.x=pos.x;this.y=pos.y;this.z=pos.z;}}
  };
  const maths={
    MathUtils:{
      clamp:(v,min,max)=>Math.max(min,Math.min(max,v)),
      lerp:(a,b,t)=>a+(b-a)*t
    }
  };
  function clearMovementInput(){
    state.flight.up=false;state.flight.down=false;
  }
  const controllers=new Function('state','player','flightTipEl','isSpawnSafe',
    'getGroundY','clearMovementInput','setStatus','THREE',code+
    '\nreturn {isSafeFlightLanding,updateFlightTip,beginFlightLanding,'+
    'cancelFlightLanding,updateFlightLanding,updateFlightVertical};')(
      state,player,hints,()=>safeGround.value,()=>0,
      clearMovementInput,text=>statuses.push(text),maths);
  return {state,player,safeGround,hints,statuses,code,...controllers};
}

test('SUZYFLY landing descends directly below Suzy, never changing XY or view',()=>{
 const h=flightHarness({x:167.5,z:130.25,y:24.0});
 const start={x:h.player.position.x,z:h.player.position.z};
 assert.equal(h.beginFlightLanding(),true);
 assert.deepEqual(h.state.flight.landing,start);
 assert.match(h.hints.textContent,/Descending here/);
 h.updateFlightLanding(0.5);
 assert.equal(h.player.position.x,start.x);
 assert.equal(h.player.position.z,start.z);
 assert.equal(h.player.position.y,19.75);
 for(let i=0;i<20;i++)h.updateFlightLanding(0.25);
 assert.equal(h.state.flight.enabled,false);
 assert.equal(h.state.flight.landing,null);
 assert.equal(h.player.position.y,0);
 assert.equal(h.player.position.x,start.x);
 assert.equal(h.player.position.z,start.z);
 assert.deepEqual({x:h.state.lastSafePosition.x,z:h.state.lastSafePosition.z},start);
 assert.match(h.statuses.at(-1),/Touchdown right here/);
 assert.doesNotMatch(h.code,/findParkingLandingTarget|player\.position\.x\s*[+]=|player\.position\.z\s*[+]=/);
 assert.match(h.code,/!target\|\|!state\.isRunning/,'landing respects pause state');
});

test('unsafe landing stays hovering and does not teleport to the lot',()=>{
 const h=flightHarness({x:620,z:500,y:16,safe:false});
 const start={...h.player.position};
 assert.equal(h.beginFlightLanding(),false);
 assert.equal(h.state.flight.landing,null);
 assert.deepEqual(h.player.position,start);
 assert.match(h.statuses.at(-1),/No teleporting/);
 h.state.flight.down=true;
 for(let i=0;i<300;i++)h.updateFlightVertical(0.02);
 assert.equal(h.state.flight.enabled,true);
 assert.equal(h.state.flight.altitude,2.5);
 assert.equal(h.player.position.x,start.x);
 assert.equal(h.player.position.z,start.z);
 assert.match(h.hints.textContent,/street or sidewalk/);
 h.safeGround.value=true; // Suzy flies over a public street.
 for(let i=0;i<200;i++)h.updateFlightVertical(0.02);
 assert.equal(h.state.flight.enabled,false,'holding Q can finish descent when safe');
 assert.equal(h.player.position.x,start.x);
 assert.equal(h.player.position.z,start.z);
 assert.equal(h.player.position.y,0);
});

test('Space ascends without WASD movement, Q descends, second toggle may cancel',()=>{
 const h=flightHarness({x:33,z:14,y:0,safe:true});
 h.state.flight.up=true;
 for(let i=0;i<40;i++)h.updateFlightVertical(0.025);
 assert.ok(h.state.flight.altitude>5,'can climb without horizontal input');
 h.state.flight.up=false;
 h.state.flight.down=true;
 for(let i=0;i<12;i++)h.updateFlightVertical(0.025);
 assert.ok(h.state.flight.altitude<8,'descent input lowers altitude');
 // Reset to an altitude where we can test a separate landing cancellation.
 h.state.flight.down=false;h.state.flight.altitude=18;h.player.position.y=18;
 assert.equal(h.beginFlightLanding(),true);
 h.updateFlightLanding(0.2);
 const airborne=h.player.position.y;
 assert.equal(h.cancelFlightLanding(),true);
 h.updateFlightLanding(0.5);
 assert.equal(h.state.flight.enabled,true);
 assert.equal(h.state.flight.landing,null);
 assert.equal(h.player.position.y,airborne);
 assert.match(h.statuses.at(-1),/Landing cancelled/);
});

test('case-insensitive command and accessible flight controls are documented',()=>{
 const page=read('page-gastown-sim.php');
 assert.match(sim,/event\.code\.slice\(3\)\.toLowerCase\(\)/);
 assert.match(sim,/state\.isRunning&&state\.cameraMode==='street'/);
 assert.match(sim,/if\(code==='KeyQ'\|\|code==='ControlLeft'\|\|code==='ControlRight'\)/);
 assert.match(sim,/if\(fly\.landing\)cancelFlightLanding\(\)/);
 assert.match(sim,/else beginFlightLanding\(\)/);
 assert.match(page,/any capitalization, no Enter/);
 assert.match(page,/type it again to land/i);
 assert.match(page,/Space bar<\/strong> to ascend even while stationary/);
 assert.match(page,/<strong>Q<\/strong> or <strong>Ctrl<\/strong> descends/);
 assert.doesNotMatch(page,/suzyfly[^<]*again to land at Station East/i);
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


test('in-flight banner spells out holding the Space bar while ascending',()=>{
 const page=read('page-gastown-sim.php');
 assert.match(sim,/Flying! Hold SPACE BAR to ascend · Q\/Ctrl to descend · type SUZYFLY again to land here/);
 assert.match(sim,/Flying! Hold SPACE BAR to rise · Q\/Ctrl to descend · move above a street or sidewalk to land/);
 assert.match(page,/hold the <strong>Space bar<\/strong> to ascend/i);
 assert.match(page,/hold SPACE BAR to ascend · Q\/Ctrl to descend/);
 assert.doesNotMatch(sim,/Flying! Type SUZYFLY again to land at Station East/);
});
