'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.resolve(__dirname,'..',p),'utf8');

function score(){
 const win={};
 new Function('window',read('js/gastown-film-cues.js'))(win);
 new Function('window',read('js/gastown-film-conductor.js'))(win);
 return win;
}
test('60-second original scene score has five contiguous acts',()=>{
 const w=score(),c=w.GastownFilmCues;
 assert.equal(c.DURATION,60);
 assert.equal(c.CUES.length,5);
 assert.equal(c.CUES[0].from,0);
 assert.equal(c.CUES.at(-1).to,60);
 for(let i=1;i<c.CUES.length;i++)assert.equal(c.CUES[i-1].to,c.CUES[i].from);
 assert.equal(c.sample(39).cue,'interruption');
 assert.ok(c.sample(58,{highs:1}).bioluminescence>0.5);
});
test('Magenta prompts are original and separate from previous Pyramid Song material',()=>{
 const p=score().GastownFilmConductor.PRESETS;
 assert.equal(Object.keys(p).length,6);
 for(const [key,preset] of Object.entries(p)){
   assert.ok(preset.prompt.length>45,key);
   assert.doesNotMatch(preset.prompt,/pyramid song|grimes|radiohead/i);
 }
 assert.equal(score().GastownFilmConductor.formatTime(39.6),'00:39');
});
test('UI and scripts wire conductor into scene without falsely claiming AI model executes in browser',()=>{
 const page=read('page-gastown-sim.php');
 const php=read('functions.php');
 const runtime=read('js/gastown-sim.js');
 const manifest=read('scripts/theme_deploy_manifest.py');
 const bridge=read('js/gastown-film-conductor.js');
 assert.match(page,/data-action="film-panel-toggle"/);
 assert.match(page,/data-film-audio/);
 assert.match(page,/data-film-morph/);
 assert.match(page,/No AI model runs in this browser/);
 assert.match(php,/'se-gastown-film-conductor'/);
 assert.match(runtime,/filmConductorModel\.mount\(app, THREE, worldGroup, state\.world\)/);
 assert.match(runtime,/state\.filmConductor\.tick\(\)/);
 assert.ok(manifest.includes('"js/gastown-film-cues.js"'));
 assert.ok(manifest.includes('"js/gastown-film-conductor.js"'));
 assert.match(bridge,/createMediaElementSource\(audio\)/);
 assert.match(bridge,/URL\.createObjectURL\(file\)/);
 assert.match(bridge,/URL\.revokeObjectURL\(objectUrl\)/);
});
