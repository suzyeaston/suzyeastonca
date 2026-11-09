'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = f => fs.readFileSync(path.join(__dirname,'..',f),'utf8');

test('Water Street cars follow westbound only, trolley corridor stays separate',()=>{
 const src=read('js/gastown-street-life.js');
 assert.match(src,/along\(total-distance,-6\.4,true,car\)/);
 assert.doesNotMatch(src,/const reverse=index%2===1/);
 assert.match(src,/Route #3 trolleys run on the illustrated Cordova-side circuit/);
});

test('storm advances visible lightning each frame and thunder after flash',()=>{
 const src=read('js/gastown-sim.js');
 assert.match(src,/maybeTriggerLightning\(currentWeather\);\s*updateLightning\(currentWeather\)/);
 assert.match(src,/state\.activeWeather !== 'thunderstorm'/);
 assert.match(src,/nextLightningAt = now \+ 6500/);
 assert.match(src,/now >= flash\.thunderAt/);
 assert.match(src,/lightningLight\.intensity = intensity/);
});

test('side controls hand off pointer lock, ambient voices use nearby consented speech',()=>{
 const js=read('js/gastown-sim.js');
 assert.match(js,/sidebar\.addEventListener\('pointerdown'/);
 assert.match(js,/document\.exitPointerLock\(\)/);
 assert.match(js,/function maybeSpeakNearNpc\(seconds\)/);
 assert.match(js,/!state\.hasInteracted \|\| !localAmbienceEnabled/);
 assert.match(js,/npcAmbientCooldown\.set/);
 const page=read('page-gastown-sim.php');
 assert.match(page,/Press M to open\/close this menu/);
});

test('clear weather turns off the synthesized rain bed, while rain raises it',()=>{
 const street=read('js/gastown-street-life.js');
 const sim=read('js/gastown-sim.js');
 assert.match(street,/currentWeather='clear'/);
 assert.match(street,/currentWeather==='rain'\?0\.10/);
 assert.match(street,/currentWeather==='drizzle'\?0\.035:0/);
 assert.match(street,/rainNoiseGain\.gain\.setTargetAtTime\(rainLevel/);
 assert.match(sim,/streetLifeScene\.setWeather\(weatherId\)/);
 assert.match(sim,/streetLifeScene\.setWeather\(state\.activeWeather\)/);
});
test('rain surrounds the moving player and umbrellas are upright',()=>{
 const src=read('js/gastown-sim.js');
 assert.match(src,/rainGroup\.position\.set\(player\.position\.x, 0, player\.position\.z\)/);
 assert.match(src,/const dropCount = Math\.floor\(\(750 \+ \(intensity \* 1550\)\)/);
 assert.match(src,/canopy\.position\.set\(0\.28, style\.height \+ 0\.21, 0\)/);
 const canopy=src.slice(src.indexOf("const canopy = new THREE.Mesh(new THREE.ConeGeometry(0.55"));
 assert.doesNotMatch(canopy.slice(0,180),/canopy\.rotation\.x = Math\.PI/);
});
