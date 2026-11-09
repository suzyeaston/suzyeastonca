'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const source=read('js/gastown-waterfront-places.js');
const sim=read('js/gastown-sim.js');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));

test('The Landing corner portal is anchored to the real Water Street frontage',()=>{
 assert.match(source,/const frontA=pts\[6\], frontB=pts\[7\]/);
 assert.match(source,/function landingEntranceFor\(world\)/);
 assert.match(source,/const a=pts\[6\],b=pts\[7\]/);
 assert.match(source,/The Landing sidewalk barrel canopy curved iron rib/);
 assert.match(source,/The Landing paired glass door mullion/);
 assert.match(source,/The Landing translucent overhead canopy glazing/);
 assert.match(source,/entrancePane.renderOrder=4/);
 const a=world.landing.footprint[6],b=world.landing.footprint[7];
 const d=Math.hypot(b.x-a.x,b.z-a.z);
 const threshold={x:(a.x+b.x)/2+(b.z-a.z)/d*3.22,z:(a.z+b.z)/2-(b.x-a.x)/d*3.22};
 assert.ok(Number.isFinite(threshold.x)&&Number.isFinite(threshold.z));
 assert.ok(threshold.x>55&&threshold.x<100);
 assert.ok(threshold.z>115&&threshold.z<135);
});

test('sixth floor is a bounded fictional playable interior, with visible exit and KK dialogue',()=>{
 assert.match(source,/function createLabInterior\(THREE\)/);
 for(const marker of ['fictionalInterior=true','Sixth floor','North Shore mountain ridge silhouette','Black grand piano body','KK stylized portrait head','KK denim jacket','Exit to Water Street door']) {
  assert.ok(source.includes(marker),marker);
 }
 assert.match(sim,/function interactWithLanding\(\)/);
 assert.match(sim,/function updateLandingHint\(\)/);
 assert.match(sim,/state\.landingLab\.inside/);
 assert.match(sim,/if \(state\.landingLab\.inside && interactWithLanding\(\)\)/);
 assert.match(sim,/player\.position\.x=Math\.max\(792\.75/);
 const dialog=JSON.parse(read('assets/dialog/gastown.json')).kk_lab_greeter;
 assert.ok(dialog&&dialog.lines.length>=2);
 assert.match(dialog.lines.join(' '),/imaginary Multimodal Lab/);
});

test('steam clock uses visible animated mesh plumes and playable synthesized Westminster motif',()=>{
 assert.match(sim,/const STEAM_CLOCK_CHIME_MOTIF/);
 assert.match(sim,/function triggerSteamClockChime\(reason\)/);
 assert.match(sim,/new Tone\.PolySynth/);
 assert.match(sim,/Steam Clock animated vent plume/);
 assert.match(sim,/steamBurstUntil/);
 assert.match(sim,/const burst=nowSeconds</);
 assert.match(sim,/unlockSteamClockAudio\(\)\.then\(\(\)=>triggerSteamClockChime\('manual'\)\)/);
 assert.doesNotMatch(sim,/new Audio\([^)]*big.ben/i);
});
