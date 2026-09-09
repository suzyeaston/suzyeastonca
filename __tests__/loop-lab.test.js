const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {
  formatDuration,
  createLayerName,
  hasAudibleLayers,
  resolveControlLocks,
  createMixFilename,
  encodeWav,
  audioBufferToWav,
} = require('../js/loop-lab.js');

const ROOT = path.resolve(__dirname, '..');
const TEMPLATE = fs.readFileSync(path.join(ROOT, 'page-loop-lab.php'), 'utf8');
const APP_SOURCE = fs.readFileSync(path.join(ROOT, 'js', 'loop-lab.js'), 'utf8');
const STYLES = fs.readFileSync(path.join(ROOT, 'assets', 'css', 'loop-lab.css'), 'utf8');

function readAscii(view, offset, length) {
  let text = '';
  for (let index = 0; index < length; index += 1) text += String.fromCharCode(view.getUint8(offset + index));
  return text;
}

test('formatDuration returns a one-decimal seconds label', () => {
  assert.equal(formatDuration(0), '00.0s');
  assert.equal(formatDuration(1.24), '01.2s');
  assert.equal(formatDuration(12.99), '13.0s');
});

test('createLayerName uses human track numbering', () => {
  assert.equal(createLayerName(0), 'Layer 01');
  assert.equal(createLayerName(9), 'Layer 10');
});

test('hasAudibleLayers only counts unmuted layers', () => {
  assert.equal(hasAudibleLayers([]), false);
  assert.equal(hasAudibleLayers([{ muted: true }]), false);
  assert.equal(hasAudibleLayers([{ muted: true }, { muted: false }]), true);
  assert.equal(hasAudibleLayers(null), false);
});

test('an empty tape only offers the record button', () => {
  const locks = resolveControlLocks({ mode: 'ready', layerCount: 0, loopDuration: 0, audible: false });
  assert.deepEqual(locks, { record: false, stop: true, restart: true, exportMix: true, clear: true });
});

test('a captured loop unlocks restart, export and clear', () => {
  const locks = resolveControlLocks({ mode: 'playing', layerCount: 2, loopDuration: 4.5, audible: true });
  assert.deepEqual(locks, { record: false, stop: true, restart: false, exportMix: false, clear: false });
});

test('recording locks the destructive and playback controls until stop', () => {
  const locks = resolveControlLocks({ mode: 'recording', layerCount: 1, loopDuration: 4.5, audible: true });
  assert.equal(locks.stop, false);
  assert.equal(locks.record, true);
  assert.equal(locks.restart, true);
  assert.equal(locks.clear, true);
  assert.equal(locks.exportMix, true);
});

test('exporting locks the controls but leaves the arrangement alone', () => {
  const locks = resolveControlLocks({ mode: 'build', exporting: true, layerCount: 3, loopDuration: 6, audible: true });
  assert.equal(locks.exportMix, true);
  assert.equal(locks.restart, true);
  assert.equal(locks.clear, true);
  assert.equal(locks.record, true);
});

test('an all-muted tape blocks export but still allows restart and clear', () => {
  const locks = resolveControlLocks({ mode: 'playing', layerCount: 2, loopDuration: 4.5, audible: false });
  assert.equal(locks.exportMix, true);
  assert.equal(locks.restart, false);
  assert.equal(locks.clear, false);
});

test('createMixFilename stamps a dated wav name', () => {
  assert.equal(
    createMixFilename(new Date(2026, 8, 8, 18, 24)),
    'loop-lab-mix-2026-09-08-1824.wav'
  );
  assert.match(createMixFilename(new Date('nope')), /^loop-lab-mix-\d{4}-\d{2}-\d{2}-\d{4}\.wav$/);
});

test('encodeWav writes a 16-bit PCM header that matches the payload', () => {
  const buffer = encodeWav([new Float32Array([0, 1, -1, 0.5])], 44100);
  const view = new DataView(buffer);
  assert.equal(buffer.byteLength, 44 + 4 * 2);
  assert.equal(readAscii(view, 0, 4), 'RIFF');
  assert.equal(view.getUint32(4, true), 36 + 8);
  assert.equal(readAscii(view, 8, 4), 'WAVE');
  assert.equal(readAscii(view, 12, 4), 'fmt ');
  assert.equal(view.getUint32(16, true), 16);
  assert.equal(view.getUint16(20, true), 1);
  assert.equal(view.getUint16(22, true), 1);
  assert.equal(view.getUint32(24, true), 44100);
  assert.equal(view.getUint32(28, true), 44100 * 2);
  assert.equal(view.getUint16(32, true), 2);
  assert.equal(view.getUint16(34, true), 16);
  assert.equal(readAscii(view, 36, 4), 'data');
  assert.equal(view.getUint32(40, true), 8);
});

test('encodeWav clamps samples and keeps full-scale peaks intact', () => {
  const view = new DataView(encodeWav([new Float32Array([0, 1, -1, 4, -4])], 8000));
  assert.equal(view.getInt16(44, true), 0);
  assert.equal(view.getInt16(46, true), 32767);
  assert.equal(view.getInt16(48, true), -32768);
  assert.equal(view.getInt16(50, true), 32767);
  assert.equal(view.getInt16(52, true), -32768);
});

test('encodeWav interleaves stereo frames', () => {
  const left = new Float32Array([1, 0]);
  const right = new Float32Array([-1, 0]);
  const buffer = encodeWav([left, right], 48000);
  const view = new DataView(buffer);
  assert.equal(view.getUint16(22, true), 2);
  assert.equal(view.getUint16(32, true), 4);
  assert.equal(view.getUint32(28, true), 48000 * 4);
  assert.equal(view.getUint32(40, true), 2 * 4);
  assert.equal(view.getInt16(44, true), 32767);
  assert.equal(view.getInt16(46, true), -32768);
});

test('audioBufferToWav reads every channel off an AudioBuffer-shaped object', () => {
  const channels = [new Float32Array([1, 0]), new Float32Array([-1, 0])];
  const fakeBuffer = {
    numberOfChannels: 2,
    length: 2,
    sampleRate: 22050,
    getChannelData: (index) => channels[index],
  };
  const view = new DataView(audioBufferToWav(fakeBuffer));
  assert.equal(view.getUint16(22, true), 2);
  assert.equal(view.getUint32(24, true), 22050);
  assert.equal(view.getInt16(44, true), 32767);
  assert.equal(view.getInt16(46, true), -32768);
});

test('template exposes the export and clear tape controls', () => {
  assert.match(TEMPLATE, /data-loop-export/);
  assert.match(TEMPLATE, /data-loop-clear\b/);
  assert.match(TEMPLATE, /data-loop-restart/);
  assert.match(TEMPLATE, />export mix</);
  assert.match(TEMPLATE, />clear tape</);
  assert.match(TEMPLATE, />restart loop</);
});

test('template no longer ships the destructive data-loop-reset control', () => {
  assert.doesNotMatch(TEMPLATE, /data-loop-reset/);
  assert.doesNotMatch(APP_SOURCE, /data-loop-reset/);
});

test('template ships an accessible clear tape confirmation dialog', () => {
  assert.match(TEMPLATE, /<dialog[^>]*data-loop-clear-dialog/);
  assert.match(TEMPLATE, /aria-labelledby="loop-clear-title"/);
  assert.match(TEMPLATE, /aria-describedby="loop-clear-copy"/);
  assert.match(TEMPLATE, /id="loop-clear-title"[^>]*>clear the tape\?</);
  assert.match(TEMPLATE, /data-loop-clear-cancel/);
  assert.match(TEMPLATE, /data-loop-clear-confirm/);
});

test('restart reschedules playback instead of wiping the arrangement', () => {
  const restart = APP_SOURCE.match(/function restartLoop\(\)[\s\S]*?\n    }/);
  assert.ok(restart, 'restartLoop should exist');
  assert.match(restart[0], /playLoop\(\)/);
  assert.doesNotMatch(restart[0], /layers = \[\]/);
  assert.doesNotMatch(restart[0], /loopDuration = 0/);
  assert.match(restart[0], /mode === 'recording'/);
});

test('clearArrangement owns the destructive reset and guards stale takes', () => {
  const clear = APP_SOURCE.match(/function clearArrangement\(\)[\s\S]*?\n    }/);
  assert.ok(clear, 'clearArrangement should exist');
  assert.match(clear[0], /layers = \[\]/);
  assert.match(clear[0], /loopDuration = 0/);
  assert.match(clear[0], /tapeEpoch \+= 1/);
  assert.doesNotMatch(APP_SOURCE, /function reset\(\)/);
  assert.match(APP_SOURCE, /if \(epoch !== tapeEpoch\) return;/);
});

test('export renders offline and never uploads the mix', () => {
  assert.match(APP_SOURCE, /OfflineAudioContext \|\| root\.webkitOfflineAudioContext/);
  assert.match(APP_SOURCE, /layers\.filter\(\(layer\) => !layer\.muted\)/);
  assert.match(APP_SOURCE, /type: 'audio\/wav'/);
  assert.match(APP_SOURCE, /revokeObjectURL/);
  assert.match(APP_SOURCE, /printing the tape\.\.\./);
  assert.match(APP_SOURCE, /mix exported\. tape escaped\./);
  assert.doesNotMatch(APP_SOURCE, /fetch\(|XMLHttpRequest/);
});

test('loop lab styles cover the dialog and the wider control row', () => {
  assert.match(STYLES, /\.loop-lab-dialog\{/);
  assert.match(STYLES, /\.loop-lab-dialog::backdrop/);
  assert.match(STYLES, /\.loop-lab-dialog\.is-fallback/);
  assert.match(STYLES, /\.loop-lab-controls\{display:grid;grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/);
  assert.match(STYLES, /@media \(min-width:900px\)\{\.loop-lab-controls\{grid-template-columns:2fr repeat\(4,minmax\(0,1fr\)\)\}/);
});

test('loop lab runtime files are listed in the theme deploy manifest', () => {
  const manifest = fs.readFileSync(path.join(ROOT, 'scripts', 'theme_deploy_manifest.py'), 'utf8');
  assert.match(manifest, /"page-loop-lab\.php"/);
  assert.match(manifest, /"assets\/css\/loop-lab\.css"/);
  assert.match(manifest, /"js\/loop-lab\.js"/);
});
