const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const sim = fs.readFileSync(path.join(__dirname,'../js/gastown-sim.js'),'utf8');
const harbour = fs.readFileSync(path.join(__dirname,'../js/gastown-harbour-vista.js'),'utf8');

test('secret letsgo only transforms when Suzy is flying and does not retrigger mid-spin',()=>{
  assert.match(sim,/function consumeFlightSpellKey\(event\)/);
  assert.match(sim,/const word = 'letsgo'/);
  assert.match(sim,/if \(consumeFlightSpellKey\(event\)\) return;/);
  assert.doesNotMatch(sim,/secretBuffer\.endsWith\('letsgo'\)/);
  assert.match(sim,/clearMovementInput\(\);\s*state\.velocity\.set\(0,0,0\);\s*startMagicTransformation\(\)/);
  assert.match(sim,/if \(!state\.flight\.enabled \|\| state\.magic\.active \|\| !suzyAvatar\) return false/);
  assert.match(sim,/function updateMagicTransformation\(delta\)/);
  assert.match(sim,/spell\.active = false/);
  assert.match(sim,/spell\.group = null/);
  assert.match(sim,/spell\.particles = null/);
});
test('transformation preserves back-follow camera and freezes movement while playing',()=>{
  assert.match(sim,/state\.avatarCameraFacing = 'back'/);
  assert.match(sim,/if \(state\.magic\.active\) \{\s*state\.velocity\.set\(0,0,0\)/);
  assert.match(sim,/updateMagicTransformation\(delta\)/);
  assert.match(sim,/suzyAvatar\.rotation\.y = 0/);
});
test('skyline and freight detail are constructed inside the existing harbour context',()=>{
  assert.match(harbour,/function downtownSkylineDepth\(THREE,root\)/);
  assert.match(harbour,/approximateSkylineInfillCount=blocks\.length/);
  assert.match(harbour,/function additionalWaterfrontFreight\(THREE,root\)/);
  assert.match(harbour,/additionalFreightWagons=6/);
  assert.match(harbour,/waterfrontRailYard\(THREE,root,world\);\s*additionalWaterfrontFreight\(THREE,root\);\s*downtownSkylineDepth\(THREE,root\)/);
});

test('eastbound rail corridor sits in the northern apron and freight aligns to it',()=>{
  assert.match(harbour,/const begin = \{ x: 68, z: -86 - row \* 5\.0 \}/);
  assert.match(harbour,/const end = \{ x: 490, z: -145 - row \* 5\.0 \}/);
  assert.match(harbour,/const z=\(-86-row\*5\.0\)/);
  assert.match(harbour,/Stationary intermodal freight container/);
});
