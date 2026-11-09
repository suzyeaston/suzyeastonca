'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const places=read('js/gastown-waterfront-places.js');
const game=read('js/gastown-sim.js');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));

test('The Landing street door includes historically inspired glazing, stone jambs and handles',()=>{
  for(const marker of ['The Landing arched Water Street entry glazing','The Landing double-door brass pull handle',
    'The Landing carved golden 375 lintel','The Landing limestone door jambs',
    'The Landing sidewalk barrel canopy curved iron rib']){
    assert.ok(places.includes(marker),marker);
  }
  assert.match(places,/const frontA=pts\[6\], frontB=pts\[7\]/);
  assert.equal(world.landing.address,'375 Water Street');
  assert.doesNotMatch(places,/sign\(THREE,root,'MULTIMODAL MEDIA LAB',\s*\{x:signX/);
});

test('lobby is separate, walkable, lift is on left and escort shares journey',()=>{
  for(const marker of ['createLandingJourney(THREE)','The Landing lobby stone floor',
    'Glass main entrance left hinged door','Elevator left sliding leaf',
    'Elevator floor button ','KK fictional lobby escort']){
    assert.ok(places.includes(marker),marker);
  }
  assert.match(places,/elevatorLobby:\{x:845,z:851\}/);
  assert.match(game,/function landingTravel\(stage, point, status\)/);
  assert.match(game,/function updateLandingEscort\(delta,nowSeconds\)/);
  assert.match(game,/lab\.stage==='escort'/);
  assert.match(game,/lab\.stage==='lift'/);
  assert.match(game,/lab\.stage==='lab'/);
  assert.match(game,/state\.landingLab\.journeyRoot=journey/);
  assert.match(game,/!state\.landingLab\.inside && !isPlayerInsideWaterfront/);
  assert.match(game,/if\(state\.landingLab\.inside\)\{/);
  assert.match(game,/see ya snoozy!/i);
});

test('player cannot roam beyond lobby, elevator car or sixth-floor lab boundaries',()=>{
  assert.match(game,/stage==='lab'/);
  assert.match(game,/stage==='lift'/);
  assert.match(game,/minX:843\.0,maxX:857\.0,minZ:841\.0,maxZ:858\.6/);
  assert.match(game,/minX:878\.4,maxX:881\.6,minZ:848\.5,maxZ:851\.8/);
  assert.match(game,/minX:792\.75,maxX:807\.25,minZ:791\.1,maxZ:809\.25/);
  assert.match(game,/function interactWithLanding\(\)/);
  assert.match(game,/if \(state\.landingLab\.inside\) \{ event\.preventDefault\(\); return; \}/);
});

test('KK journey is proximity driven with speech, wait-for-player and animated physical doors',()=>{
  assert.match(game,/function landingSpeak\(phrase\)/);
  assert.match(game,/window\.SpeechSynthesisUtterance/);
  assert.match(game,/window\.speechSynthesis\.speak\(utterance\)/);
  assert.match(game,/if\(distance<4\.0&&lab\.outdoorGreeting&&state\.isRunning\)/);
  assert.match(game,/if\(d>0\.08&&following<4\.2\)/);
  assert.match(game,/if\(near\(\{x:845,z:851\},2\.4\)/);
  assert.match(game,/lab\.escort\.position\.set\(879\.35,0,849\.45\)/);
  assert.match(game,/lab\.farewellSpoken=true/);
  assert.match(game,/landingSpeak\('see ya snoozy!'\)/);
  assert.match(game,/state\.thirdPerson=false/);
  assert.match(game,/lab\.indoorPreviousView/);
  for (const name of ['entranceLeft','entranceRight','liftLeft','liftRight','carLeft','carRight']){
    assert.ok(places.includes(name),name);
  }
  assert.match(places,/Multimodal Lab timber ceiling/);
  assert.match(places,/Multimodal Lab exposed wood ceiling beam/);
  assert.match(places,/MML  ·  Multimodal Media Lab/);
});

test('Landing doors remain clear, KK greets outdoors, and exiting requires walking into lobby first',()=>{
  assert.match(places,/The Landing entrance wall west wing/);
  assert.match(places,/The Landing entrance wall east wing/);
  assert.doesNotMatch(places,/block\('The Landing entrance wall',850,3\.3,860,16/);
  assert.match(game,/outsideEscort=outdoor/);
  assert.match(game,/lab\.outdoorGreeting=true/);
  assert.match(game,/lab\.hasLeftEntry=true/);
  assert.match(game,/if\(lab\.hasLeftEntry&&elapsed>1\.0/);
  assert.match(game,/const maleNames=/);
});

test('Suzy starts in rear third person and secret suzyfly enables cape and flight',()=>{
  assert.match(game,/thirdPerson: true/);
  assert.match(game,/avatarCameraFacing: 'back'/);
  assert.match(game,/secretBuffer\.endsWith\('suzyfly'\)/);
  assert.match(game,/suzyCape\.visible=state\.flight\.enabled/);
  assert.match(game,/fly\.altitude=THREE\.MathUtils\.clamp/);
  assert.match(game,/state\.flight\.enabled \? 0 : getGroundY\(\) - player\.position\.y/);
  assert.match(game,/if\(code==='Space'\)state\.flight\.up=pressed/);
  assert.match(game,/if\(code==='ControlLeft'\|\|code==='ControlRight'\)state\.flight\.down=pressed/);
});
