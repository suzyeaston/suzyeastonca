const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
const src = fs.readFileSync(simPath, 'utf8');

test('audio setup fails softly and uses a conservative mix budget', () => {
  assert.match(src, /function setupAudio\(world\) \{/);
  assert.match(src, /const AUDIO_MIX = \{/);
  assert.match(src, /masterBudget:\s*0\.62/);
  assert.match(src, /ambientBedMax:\s*0\.24/);
  assert.match(src, /zoneBedMax:\s*0\.18/);
  assert.match(src, /function clampVolume\(value, max\)/);
  assert.match(src, /warnAudioUnavailable\('Gastown audio setup failed; simulator continuing without ambient audio\.', error\);/);
  assert.match(src, /state\.sounds\.beds\[id\] = createSafeHowl\(/);
  assert.match(src, /\['quiet_night', 'commuter_mix'\]/);
});

test('fallback-world disclosure stays explicit', () => {
  const worldPath = path.join(__dirname, '..', 'assets', 'world', 'gastown-water-street.json');
  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));

  assert.equal(world.meta.fallbackMode, 'working-gastown-corridor');
  assert.equal(world.meta.isRealCivicBuild, false);
  assert.equal(world.meta.buildClassification, 'approximate-fallback');
  assert.match(world.meta.provenanceSummary, /Approximate fallback corridor retained/);
});

test('runtime init renders core world first and defers optional systems', () => {
  const initStart = src.indexOf('async function init() {');
  const initEnd = src.indexOf('init();', initStart);
  const block = src.slice(initStart, initEnd);

  assert.notEqual(initStart, -1, 'expected init function');
  assert.match(block, /const coreSystems = \[/);
  assert.match(block, /\['ground', addGround\]/);
  assert.match(block, /\['buildings', addBuildings\]/);
  assert.match(block, /\['hero landmarks', addHeroLandmarks\]/);
  assert.match(block, /window\.setTimeout\(\(\) => \{/);
  assert.match(block, /loadDialogData\(\)\.then/);
  assert.match(block, /try \{ addNpcs\(state\.world\); \}/);
  assert.match(block, /try \{ if \(RUNTIME_PROFILE\.deferAudioSetup\) setupAudio\(state\.world\); \}/);
  assert.match(block, /setStatus\('Gastown working corridor ready\. Click in to explore\.'/);
});

test('movement model supports hold-to-run with smooth activation and reset', () => {
  assert.match(src, /const HOLD_TO_RUN_THRESHOLD_SECONDS = 0\.34/);
  assert.match(src, /const HOLD_TO_RUN_FORWARD_MULTIPLIER = 1\.58/);
  assert.match(src, /function updateHoldToRunState\(delta, forwardInput\)/);
  assert.match(src, /state\.motion\.runHoldSeconds \+= delta/);
  assert.match(src, /state\.motion\.runActive = !state\.gait\.precise && state\.cameraMode === 'street' && \(holdRunActive \|\| shiftRunActive\)/);
  assert.match(src, /function getDynamicMovementProfile\(baseProfile, runState\)/);
  assert.match(src, /runForwardFactor = forward > 0 \? \(1 \+ \(\(HOLD_TO_RUN_FORWARD_MULTIPLIER - 1\) \* movementProfile\.runBlend\)\) : 1/);
  assert.match(src, /runStrafeFactor = 1 - \(movementProfile\.runBlend \* 0\.17\)/);
  assert.match(src, /state\.motion\.runBlend = 0;/);
  assert.match(src, /state\.motion\.runHoldSeconds = 0;/);
  assert.match(src, /state\.motion\.runActive = false;/);
});
