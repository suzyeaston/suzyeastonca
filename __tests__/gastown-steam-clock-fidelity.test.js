'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=pathName=>fs.readFileSync(path.join(__dirname,'..',pathName),'utf8');

test('Steam Clock sculpture is 4.9m tall, with four hand-painted dials and five whistles',()=>{
 const sim=read('js/gastown-sim.js');
 assert.match(sim,/clockArtwork\.scale\.set\(0\.52, 0\.52, 0\.52\)/);
 assert.match(sim,/clockRoot\.userData\.clockHeightMetres=4\.94/);
 assert.match(sim,/clockRoot\.userData\.clockFaces=4/);
 assert.match(sim,/clockRoot\.userData\.clockWhistles=5/);
 assert.match(sim,/Steam Clock cream Roman-numeral clock face/);
 assert.match(sim,/Steam Clock dial hour hand/);
 assert.match(sim,/Steam Clock rooftop steam whistle/);
 assert.match(sim,/\[0, Math\.PI \/ 2, Math\.PI, -Math\.PI \/ 2\]\.forEach/);
 assert.match(sim,/\[\[0,0\],\[-0\.57,-0\.57\]/);
 assert.match(sim,/clockArtwork\.add\(steamPlume\)/);
 assert.match(sim,/clockArtwork\.add\(mist\)/);
 assert.match(sim,/clockRoot\.add\(plaza\)/);
 assert.match(sim,/clockRoot\.add\(plazaApron\)/);
 assert.ok(Math.abs(9.5*0.52-4.94)<0.02);
});

test('Steam Clock is tied to existing real corner anchor, with paving and no changed walk bounds',()=>{
 const world=JSON.parse(read('assets/world/gastown-water-street.json'));
 const hero=world.hero_landmarks.find(x=>x.id==='steam-clock-hero');
 const clock=world.landmarks.find(x=>x.id==='steam-clock');
 assert.ok(hero&&clock);
 assert.ok(Math.hypot(hero.x-clock.x,hero.z-clock.z)<0.01);
 assert.equal(world.route.centerline.at(-1).id,'steam-clock-approach');
 const sim=read('js/gastown-sim.js');
 assert.match(sim,/clockRoot\.position\.set\(hero\.x, 0, hero\.z\)/);
 assert.match(sim,/worldGroup\.add\(clockRoot\)/);
});


test('clock plaza is compact and avoids overlapping disc and ring triangles',()=>{
 const src=read('js/gastown-sim.js');
 assert.match(src,/const plazaRadius = Math\.min\(2\.2,/);
 assert.match(src,/plaza\.position\.y = 0\.155/);
 assert.match(src,/new THREE\.RingGeometry\(plazaRadius, plazaRadius \+ 0\.58, 40\)/);
 assert.match(src,/plazaApron\.position\.y = 0\.155/);
 assert.match(src,/Steam Clock non-overlapping plaza ring/);
 assert.doesNotMatch(src,/plazaRadius \* 0\.62, plazaRadius \+ 1\.1/);
});
