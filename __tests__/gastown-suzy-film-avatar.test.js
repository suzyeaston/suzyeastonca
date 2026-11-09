'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = p => fs.readFileSync(path.join(__dirname,'..',p),'utf8');
test('Suzy is grounded in reference photos rather than the purple placeholder', () => {
  const avatar = read('js/gastown-suzy-avatar.js');
  for(const feature of [
    'Silver blonde full hair cap',
    'Wispy layered bangs',
    'Warm brown visible eye',
    'Natural eyebrow',
    'Sculpted nose',
    'Mouth with soft smile',
    'Suzy orange checkerboard Vans backpack',
    'VANS woven patch lettering',
    "avatar.userData.backpack='Orange/ivory checkerboard Vans backpack and straps'"
  ]) assert.ok(avatar.includes(feature),feature);
  assert.doesNotMatch(avatar,/Bun stray tuft/);
  assert.doesNotMatch(avatar,/Messy lavender bun/);
});
test('Follow camera stays at eye level and uses a shoulder path instead of top-down clipping',()=>{
  const js=read('js/gastown-sim.js');
  assert.match(js,/const shoulders = \[0, -0\.85, 0\.85, -1\.55, 1\.55\]/);
  assert.match(js,/thirdPersonCameraRay\.intersectObjects/);
  assert.match(js,/const headroom = desiredHeight/);
  assert.match(js,/camera\.position\.set\(offset, y, side \* boom\)/);
  assert.match(js,/Math\.atan2\(offset, side \* boom\)/);
  assert.doesNotMatch(js,/headroom=boom<1\.05\?3\.18/);
  assert.doesNotMatch(js,/!isSpawnSafe\(candidate\)/);
  assert.match(js,/state\.followCameraDistance=undefined/);
});
test('Map +/- zoom the map, third-person portrait boom and first-person lens',()=>{
  const js=read('js/gastown-sim.js');
  assert.match(js,/1 \/ Math\.sqrt\(minimapState\.zoom \|\| 1\)/);
  assert.match(js,/camera\.zoom = state\.thirdPerson \? 1/);
  assert.match(js,/function setMinimapZoom\(nextZoom\)/);
  assert.match(js,/updateThirdPersonCameraPose\(true\)/);
  assert.match(js,/camera\.updateProjectionMatrix\(\)/);
  assert.match(js,/Zoom map and camera closer to Suzy/);
});
test('Working harbour is visibly populated behind fence',()=>{
  const harbour=read('js/gastown-harbour-vista.js');
  assert.match(harbour,/Station East visible railcar spine/);
  assert.match(harbour,/Visible Station East freight container/);
  assert.match(harbour,/root\.userData\.tracksAlignedToLotFence=true/);
});

test('Front and rear are independent film angles with a visible checkerboard silhouette',()=>{
  const js=read('js/gastown-sim.js');
  const html=read('page-gastown-sim.php');
  const avatar=read('js/gastown-suzy-avatar.js');
  assert.match(js,/avatarCameraFacing: 'back'/);
  assert.match(js,/facingFront\?Math\.PI:0/);
  assert.match(js,/function setSuzyCameraFacing\(direction\)/);
  assert.match(js,/state\.avatarCameraFacing=direction/);
  assert.match(js,/suzyFrontBtn\.addEventListener\('click'/);
  assert.match(js,/suzyBackBtn\.addEventListener\('click'/);
  assert.match(html,/data-action="suzy-front-view"/);
  assert.match(html,/data-action="suzy-back-view"/);
  assert.match(avatar,/Orange checker shoulder strap/);
  assert.match(avatar,/Rounded orange checkerboard Vans daypack shell/);
  assert.match(avatar,/const tiles=8,tile=256\/tiles/);
});
test('Gastown keeps AI scoring in its separate project',()=>{
  const js=read('js/gastown-sim.js');
  const html=read('page-gastown-sim.php');
  const php=read('functions.php');
  const fs=require('node:fs');
  assert.doesNotMatch(html,/film-panel-toggle|data-film-player|FILM \/\/ AI/);
  assert.doesNotMatch(js,/GastownFilmConductor|filmConductor/);
  assert.doesNotMatch(php,/se-gastown-film-conductor|se-gastown-film-cues/);
  assert.equal(fs.existsSync(require('node:path').join(__dirname,'..','js/gastown-film-conductor.js')),false);
});
test('Cordova has GIS-edge glazing and real-scale road fixtures',()=>{
  const js=read('js/gastown-sim.js');
  assert.match(js,/Cordova GIS footprint-edge upper sash glazing/);
  assert.match(js,/THREE\.InstancedMesh/);
  assert.match(js,/West Cordova suspended trolley catenary wires/);
  assert.match(js,/West Cordova dashed roadway lane stripe/);
});
