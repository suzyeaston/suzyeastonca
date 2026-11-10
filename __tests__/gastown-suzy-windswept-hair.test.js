'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');

test('Suzy has layered wind-animated hair with separate back and face locks',()=>{
  const avatar=read('js/gastown-suzy-avatar.js');
  assert.match(avatar,/Windswept silver-blonde hair rig/);
  assert.match(avatar,/Layered back hair pivot/);
  assert.match(avatar,/Face framing hair pivot/);
  assert.match(avatar,/avatar\.userData\.hairMotion=\{hairRig,hairLocks,faceLocks\}/);
  assert.match(avatar,/hairMotion\.hairLocks\.forEach/);
  assert.match(avatar,/hairMotion\.faceLocks\.forEach/);
});

test('Flying adds flowing hair without walking strides or losing the backpack',()=>{
  const avatar=read('js/gastown-suzy-avatar.js');
  const sim=read('js/gastown-sim.js');
  assert.match(avatar,/const wind=flying\?0\.65\+0\.35\*active/);
  assert.match(avatar,/if\(mode==='flying'\)\{/);
  assert.match(avatar,/avatar\.userData\.backpack=/);
  assert.match(sim,/suzyAvatarModel\.animate\(suzyAvatar, state\.avatarElapsed,\s*normalizedSpeed,/);
});
