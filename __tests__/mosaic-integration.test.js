'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
test('MOSAIC WordPress virtual route is self-hosted without GitHub Pages', () => {
  const php = read('functions.php');
  const page = read('page-mosaic.php');
  assert.match(php, /function se_mosaic_is_request\(/);
  assert.match(php, /'mosaic' === \$relative/);
  assert.match(php, /'\/page-mosaic\.php'/);
  assert.match(page, /get_template_directory_uri\(\)\s*\.\s*\$studio_relative/);
  assert.match(page, /allow="camera; microphone; autoplay"/);
  assert.doesNotMatch(page, /github\.io/);
});
test('MOSAIC runtime shipped locally alongside WordPress template', () => {
  for (const asset of ['index.html','styles.css','core.js','effects.js','patterns.js','soundbank.js','instruments.js','timeline.js','scenes.js','audio-loops.js','app.js']) {
    assert.ok(fs.existsSync(path.join(__dirname,'../assets/mosaic',asset)),asset);
  }
  const html = read('assets/mosaic/index.html');
  assert.match(html, /src="core\.js(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /src="effects\.js(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /src="app\.js(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /href="styles\.css(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /planning target/i);
});
test('Theme manifest and workflow deploy all MOSAIC assets', () => {
  const manifest = read('scripts/theme_deploy_manifest.py');
  const workflow = read('.github/workflows/deploy-production.yml');
  for (const asset of ['page-mosaic.php', 'assets/mosaic/index.html','assets/mosaic/styles.css','assets/mosaic/core.js','assets/mosaic/effects.js','assets/mosaic/patterns.js','assets/mosaic/soundbank.js','assets/mosaic/instruments.js','assets/mosaic/timeline.js','assets/mosaic/scenes.js','assets/mosaic/audio-loops.js','assets/mosaic/app.js']) {
    assert.ok(manifest.includes('"' + asset + '"'),asset);
  }
  assert.ok(manifest.includes('"assets/mosaic/**"'));
  assert.ok(workflow.includes("'assets/mosaic/**'"));
  assert.ok(workflow.includes('mosaic-integration.test.js'));
});
test('Recording has no automatic time limit and supports musician-controlled stop', () => {
  const app = read('assets/mosaic/app.js');
  assert.match(app, /function stopTake\(keep\)/);
  assert.match(app, /stopTake\(true\)/);
  assert.doesNotMatch(app, /activeTimer\s*=\s*setTimeout\s*\(/);
});

test('MOSAIC live global FX preview shares renderer with export and falls back for social', () => {
  const app = read('assets/mosaic/app.js');
  const fx = require('../assets/mosaic/effects.js');
  const html = read('assets/mosaic/index.html');
  assert.equal(fx.PRESETS.length, 5);
  assert.match(app, /renderFinalFrame\(previewContext,/);
  assert.match(app, /renderFinalFrame\(ctx, 720, 720,/);
  assert.match(app, /navigator\.share\(\{ files: \[file\]/);
  assert.match(app, /navigator\.canShare/);
  assert.match(app, /downloadBlob\(blob, name\)/);
  assert.match(html, /id="fx-preset"/);
  assert.match(html, /id="share-btn"/);
  assert.match(html, /id="share-caption"/);
});

test('One-tap tile grid allows recording, active-take stop and independent removal', () => {
  const app = read('assets/mosaic/app.js');
  const html = read('assets/mosaic/index.html');
  const css = read('assets/mosaic/styles.css');
  assert.match(app, /const tile = document\.createElement\('div'\)/);
  assert.match(app, /const action = document\.createElement\('button'\)/);
  assert.match(app, /const remove = document\.createElement\('button'\)/);
  assert.match(app, /action\.addEventListener\('click', \(\) => activateTile\(i\)\)/);
  assert.match(app, /if \(!clips\.has\(i\)\) startTake\(\)/);
  assert.match(app, /selected === i\) stopTake\(true\)/);
  assert.match(app, /event\.stopPropagation\(\);\s*removeTile\(i\);/);
  assert.match(app, /async function removeTile\(index = selected\)/);
  assert.match(app, /await dbWrite\('delete', index\)/);
  assert.match(css, /\.tile \.tile-remove/);
  assert.match(html, /Tap <strong>\+<\/strong> in an empty tile/);
});

test('MOSAIC now prefers authentic MP4/H.264/AAC for final export', () => {
  const app = read('assets/mosaic/app.js');
  const core = require('../assets/mosaic/core.js');
  const html = read('assets/mosaic/index.html');
  assert.match(core.SOCIAL_VIDEO_MIMES[0], /^video\/mp4;codecs=avc1\./);
  assert.match(core.SOCIAL_VIDEO_MIMES[0], /mp4a\.40\.2/);
  assert.equal(core.chooseSocialVideoMime(() => true), core.SOCIAL_VIDEO_MIMES[0]);
  assert.equal(core.chooseSocialVideoMime(x => x.startsWith('video/webm')), 'video/webm;codecs=vp8,opus');
  assert.equal(core.videoExtension('video/mp4;codecs=avc1.424028,mp4a.40.2'), 'mp4');
  assert.equal(core.videoExtension('video/webm'), 'webm');
  assert.match(app, /rec = makeSocialRecorder\(outputStream\)/);
  assert.match(app, /const type = rec\.mimeType \|\| ''/);
  assert.match(app, /core\.videoExtension\(type\)/);
  assert.match(html, /id="export-format-note"/);
  assert.match(html, /MP4 PREFERRED/);
  assert.match(html, /src="app\.js\?v=[a-z0-9_-]+"/);
});


test('SUZY//AI browser instruments are playable, locally layered, and mixed into export', () => {
  const app = read('assets/mosaic/app.js');
  const html = read('assets/mosaic/index.html');
  const instrument = require('../assets/mosaic/instruments.js');
  assert.deepEqual(instrument.KINDS, ['keys','bass','pad']);
  assert.equal(Math.round(instrument.frequency(69)), 440);
  assert.equal(instrument.pitch('bass',0), 48);
  assert.match(html, /src="instruments\.js\?v=[a-z0-9_-]+"/);
  assert.match(html, /id="instrument-record"/);
  assert.match(html, /id="instrument-pads"/);
  assert.match(app, /instrumentRack\?\.tick\(elapsed\)/);
  assert.match(app, /instrumentRack\?\.startPlayback\(\)/);
  assert.match(app, /instrumentRack\?\.connectExport\(exportDestination\)/);
  assert.match(app, /instrumentRack\?\.disconnectExport\(\)/);
  assert.match(app, /instrumentRack\.finish\(\)/);
  assert.match(app, /hasArrangement\(\)/);
  const layer = {kind:'keys',seconds:2,muted:false,volume:.8,events:[{t:.5,note:60,dur:.4}]};
  assert.deepEqual(instrument.eventsInWindow(layer,0,5).map(event=>event.t), [.5,2.5,4.5]);
});


test('MOSAIC loop library has ready-to-play synth patterns and custom rhythm grid', () => {
  const patterns = require('../assets/mosaic/patterns.js');
  const instruments = require('../assets/mosaic/instruments.js');
  const html = read('assets/mosaic/index.html');
  assert.deepEqual(patterns.PRESETS.map(p => p.id),
    ['heartbeat','broken','half-time','sub-walk','glass-circuit','soft-orbit']);
  assert.match(html, /src="patterns\.js\?v=[a-z0-9_-]+"/);
  assert.match(html, /src="instruments\.js\?v=[a-z0-9_-]+"/);
  assert.match(html, /id="instrument-loop-preset"/);
  assert.match(html, /id="instrument-add-rhythm"/);
  assert.match(html, /id="instrument-step-grid"/);
  const beat=patterns.buildPreset('heartbeat',120,4);
  assert.equal(beat.seconds,8);
  assert.equal(beat.kind,'drums');
  assert.ok(instruments.normalizeLayer(beat));
  assert.equal(instruments.eventsInWindow(beat,8,8.2)[0].t,8);
  const custom=patterns.buildPreset('custom-rhythm',120,2,{kick:[0,8],snare:[4,12],hat:[0,2,4,6,8,10,12,14]});
  assert.equal(custom.seconds,4);
  assert.equal(custom.events.filter(e=>e.note===36).length,4);
});


test('MOSAIC sound bank can audition music without committing and arranges actual video/audio lanes', () => {
  const bank = require('../assets/mosaic/soundbank.js');
  const timeline = require('../assets/mosaic/timeline.js');
  const instruments = require('../assets/mosaic/instruments.js');
  const html = read('assets/mosaic/index.html');
  const app = read('assets/mosaic/app.js');
  assert.deepEqual(Object.keys(bank.SOUNDS),['keys','bass','pad','drums']);
  assert.equal(bank.defaultVoice('pad'),'pad-silk');
  assert.equal(bank.soundFor('pad','pad-air').label,'Air pad · light');
  assert.equal(instruments.normalizeLayer({kind:'pad',seconds:2,events:[{t:0,note:64,dur:.5}]}).voice,'pad-silk');
  assert.match(html, /src="soundbank\.js\?v=[a-z0-9_-]+"/);
  assert.match(html, /src="timeline\.js\?v=[a-z0-9_-]+"/);
  assert.match(html, /id="instrument-preview-loop"/);
  assert.match(html, /id="instrument-preview-rhythm"/);
  assert.match(html, /id="instrument-loop-voice"/);
  assert.match(read('assets/mosaic/instruments.js'), /className='instrument-layer-voice'/);
  assert.match(html, /id="mosaic-arrangement-tracks"/);
  assert.match(app, /updateTimelinePlayhead\(position\)/);
  const layers=timeline.makeLanes(
    [{index:0,seconds:5,muted:false,audioOnly:false}],
    [{id:'p1',kind:'pad',voice:'pad-silk',seconds:2,events:[{t:.5,note:64,dur:.4}]}],6
  );
  assert.equal(layers.length,2);
  assert.equal(layers[1].segments.length,3);
  assert.equal(layers[1].notes[0].left,25);
});


test('MOSAIC v0.7 synchronizes camera takes, live music loops, and the instrument rack', () => {
  const app=read('assets/mosaic/app.js');
  const core=require('../assets/mosaic/core.js');
  const rack=require('../assets/mosaic/instruments.js');
  const timeline=require('../assets/mosaic/timeline.js');
  const html=read('assets/mosaic/index.html');
  assert.equal(core.barSeconds(120),2);
  assert.equal(core.nextBarBoundary(14.8,120),16);
  const capture=app.slice(app.indexOf('async function startTake()'),app.indexOf('function stopTake(keep)'));
  assert.ok(capture.indexOf("await startPlayback('recording')")>=0);
  assert.ok(capture.indexOf("await startPlayback('recording')")<capture.indexOf('activeRecorder.start(250)'));
  assert.match(app,/transportAudioOrigin = audioContext\.currentTime/);
  assert.match(app,/core\.nextBarBoundary\(elapsed,takeSyncBpm\)/);
  assert.match(app,/audioScheduleId=setInterval\(\(\)=>\{/);
  assert.match(app,/instrumentRack\.begin\(\{clock:transportSeconds,bpm:arrangementBpm\(\)\}\)/);
  assert.match(app,/ui\.loopToggle\?\.addEventListener\('click'/);
  assert.match(html,/id="loop-toggle"/);
  assert.match(html,/id="loop-status"/);
  assert.match(html,/src="app\.js\?v=[a-z0-9_-]+"/);
  assert.match(html,/src="core\.js\?v=[a-z0-9_-]+"/);
  assert.match(html,/class="instrument-mixer-details"/);
  const loop=rack.normalizeLayer({id:'x',kind:'bass',seconds:8,startAt:2,events:[{t:0,note:40,dur:.4}]});
  assert.equal(loop.startAt,2);
  assert.equal(rack.eventsInWindow(loop,1.9,2.1)[0].t,2);
  assert.equal(timeline.makeLanes([], [loop],10)[0].segments[0].start,2);
});

test('Live rhythm audition repeats without adding to the saved arrangement',()=>{
  const source=read('assets/mosaic/instruments.js');
  const html=read('assets/mosaic/index.html');
  assert.match(source,/this\.previewTimer=this\.host\.setInterval\(schedule,30\)/);
  assert.match(source,/this\.host\.clearInterval\(this\.previewTimer\)/);
  assert.match(source,/this\.onChange\('added'\)/);
  assert.match(html,/id="instrument-preview-loop"/);
  assert.match(html,/id="instrument-preview-rhythm"/);
});


test('MOSAIC song scene deck saves non-destructive mix snapshots for Verse, Chorus and Bridge',()=>{
  const html=read('assets/mosaic/index.html');
  const app=read('assets/mosaic/app.js');
  const scenes=require('../assets/mosaic/scenes.js');
  const host={localStorage:{getItem:()=>null,setItem:()=>{},removeItem:()=>{}}};
  const deck=new scenes.Deck(host);
  deck.seed({'video:0':1,'instrument:bass-1':.8});
  assert.deepEqual(scenes.IDS,['verse','chorus','bridge']);
  deck.rememberAdded('audio:mic-1',.7);
  assert.equal(deck.scenes.verse['audio:mic-1'],.7);
  assert.equal(deck.scenes.chorus['audio:mic-1'],0);
  const q=deck.queue('chorus',3.4,120);
  assert.equal(q.at,4);
  assert.match(html,/data-scene="verse"/);
  assert.match(html,/data-scene="chorus"/);
  assert.match(html,/data-scene="bridge"/);
  assert.match(html,/id="scene-save"/);
  assert.match(html,/src="scenes\.js\?v=scenes9"/);
  assert.match(app,/sceneDeck\.capture\(currentMixSnapshot\(\)\)/);
  assert.match(app,/sceneDeck\?\.tick\(elapsed,applySceneMix\)/);
  assert.doesNotMatch(app,/await saveClip\(clip\);\s*renderGrid\(\);\s*\}\s*function changeGrid/);
});

test('Mic-only musical overdubs have their own store, track lanes and export bus',()=>{
  const html=read('assets/mosaic/index.html'),app=read('assets/mosaic/app.js');
  const audio=require('../assets/mosaic/audio-loops.js');
  const timeline=require('../assets/mosaic/timeline.js');
  assert.match(html,/src="audio-loops\.js\?v=scenes9"/);
  assert.match(html,/id="audio-loop-record"/);
  assert.match(html,/id="audio-loop-bars"/);
  assert.match(html,/id="audio-loop-lanes"/);
  assert.match(app,/indexedDB\.open\('mosaic-studio-v1', 2\)/);
  assert.match(app,/createObjectStore\('audioLoops'/);
  assert.match(app,/audioRack\?\.connectExport\(exportDestination\)/);
  assert.match(app,/audioRack\?\.disconnectExport\(\)/);
  assert.match(app,/await audioRack\.restore\(await dbAudioRead\(\)\)/);
  assert.deepEqual(audio.cycleTimes(4,0,13),[0,4,8,12]);
  const lane=timeline.makeLanes([],[],8,[{id:'mic1',name:'Mic',seconds:4,muted:false}]);
  assert.equal(lane[0].type,'mic');
  assert.equal(lane[0].segments.length,2);
});
