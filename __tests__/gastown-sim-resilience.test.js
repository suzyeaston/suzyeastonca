const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
const src = fs.readFileSync(simPath, 'utf8');

test('landmark visual state updates guard optional marker, halo, and reflection materials', () => {
  assert.match(src, /function applyLandmarkVisualState\(landmarkVisuals, lightingState\) \{/);
  assert.match(src, /if \(landmarkVisual\.markerMaterial\) \{/);
  assert.match(src, /landmarkVisual\.markerMaterial\.emissiveIntensity = markerGlow;/);
  assert.match(src, /if \(landmarkVisual\.haloMaterial\) \{/);
  assert.match(src, /landmarkVisual\.haloMaterial\.opacity = haloOpacity;/);
  assert.match(src, /if \(landmarkVisual\.reflectionMaterial\) \{/);
  assert.match(src, /landmarkVisual\.reflectionMaterial\.opacity = reflectionOpacity;/);
  assert.match(src, /applyLandmarkVisualState\(visualState\.landmarkVisuals, \{ mood, weather, timeOfDay \}\);/);
});

test('suppressed landmark markers only push marker visuals when generic markers are actually rendered', () => {
  const addLandmarksStart = src.indexOf('function addLandmarks(world) {');
  const addLandmarksEnd = src.indexOf('function addDebugRoute(world) {', addLandmarksStart);
  const block = src.slice(addLandmarksStart, addLandmarksEnd);

  assert.notEqual(addLandmarksStart, -1, 'expected addLandmarks function');
  assert.match(block, /if \(state\.debugEnabled \|\| !suppressGenericMarker\) \{/);
  assert.match(block, /visualState\.landmarkVisuals\.push\(\{ markerMaterial, haloMaterial \}\);/);
  assert.match(block, /visualState\.landmarkVisuals\.push\(\{ reflectionMaterial \}\);/);
});

test('audio setup wraps howl creation in a soft-fail guard so missing files do not abort init', () => {
  assert.match(src, /function setupAudio\(world\) \{/);
  assert.match(src, /try \{/);
  assert.match(src, /state\.sounds\.thunder = createSafeHowl\(\{ src: src\('weather\/thunder_roll'\), volume: 0\.22 \}\);/);
  assert.match(src, /state\.sounds\.beds\[id\] = createSafeHowl\(\{ src: src\('beds\/' \+ id\), loop: true, volume: 0, html5: false \}\);/);
  assert.match(src, /warnAudioUnavailable\('Gastown audio setup failed; simulator continuing without ambient audio\.', error\);/);
});

test('init still sequences NPC, minimap, landmark, and audio setup inside one startup try/catch', () => {
  const initStart = src.indexOf('async function init() {');
  const initEnd = src.indexOf('init();', initStart);
  const block = src.slice(initStart, initEnd);

  assert.notEqual(initStart, -1, 'expected init function');
  assert.match(block, /addNpcs\(state\.world\);/);
  assert.match(block, /addLandmarks\(state\.world\);/);
  assert.match(block, /setupAudio\(state\.world\);/);
  assert.match(block, /minimapState\.worldMetrics = getWorldMetrics\(\);/);
  assert.match(block, /setStatus\('Unable to start simulator: ' \+ error\.message\);/);
});
