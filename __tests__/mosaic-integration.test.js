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
  for (const asset of ['index.html','styles.css','core.js','effects.js','patterns.js','soundbank.js','instruments.js','timeline.js','app.js']) {
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
  for (const asset of ['page-mosaic.php', 'assets/mosaic/index.html','assets/mosaic/styles.css','assets/mosaic/core.js','assets/mosaic/effects.js','assets/mosaic/patterns.js','assets/mosaic/soundbank.js','assets/mosaic/instruments.js','assets/mosaic/timeline.js','assets/mosaic/app.js']) {
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
  assert.match(html, /src="soundbank\.js\?v=sound7"/);
  assert.match(html, /src="timeline\.js\?v=sound7"/);
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
