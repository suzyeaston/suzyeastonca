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
    'Suzy black Vans backpack',
    'VANS woven patch lettering',
    "avatar.userData.backpack='Black Vans daypack with woven label'"
  ]) assert.ok(avatar.includes(feature),feature);
  assert.doesNotMatch(avatar,/Bun stray tuft/);
  assert.doesNotMatch(avatar,/Messy lavender bun/);
});
test('Follow camera remains locked behind Suzy and moves above her when obstructed',()=>{
  const js=read('js/gastown-sim.js');
  assert.match(js,/const headroom=boom<1\.10\?3\.35:2\.55/);
  assert.match(js,/camera\.position\.set\(0,y,boom\)/);
  assert.match(js,/camera\.rotation\.set\(-Math\.atan2\(headroom-1\.55/);
  assert.match(js,/thirdPersonCameraRay\.intersectObjects/);
  assert.match(js,/state\.followCameraDistance=undefined/);
});
test('Working harbour is visibly populated behind fence',()=>{
  const harbour=read('js/gastown-harbour-vista.js');
  assert.match(harbour,/Station East visible railcar spine/);
  assert.match(harbour,/Visible Station East freight container/);
  assert.match(harbour,/nearbyContainers=9/);
});
