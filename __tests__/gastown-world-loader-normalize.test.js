const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadNormalizeWorldData() {
  const loaderPath = path.join(__dirname, '..', 'js', 'gastown-world-loader.js');
  const src = fs.readFileSync(loaderPath, 'utf8');
  const sandbox = {
    window: {},
    fetch: async () => ({ ok: true, json: async () => ({}) }),
  };
  vm.runInNewContext(src, sandbox);
  return sandbox.window.GastownWorldLoader.normalizeWorldData;
}

function minimalValidWorld(overrides = {}) {
  return {
    route: {
      centerline: [{ x: 0, z: 0 }, { x: 5, z: 0 }],
      walkBounds: [{ x: 0, z: 0 }, { x: 5, z: 0 }, { x: 5, z: 5 }],
    },
    nodes: [{ id: 'n1', x: 0, z: 0 }],
    buildings: [
      {
        id: 'b1',
        footprint: [{ x: 0, z: 0 }, { x: 3, z: 0 }, { x: 3, z: 3 }],
      },
    ],
    zones: {
      street: [{ polygon: [{ x: 0, z: 0 }, { x: 6, z: 0 }, { x: 6, z: 6 }] }],
      sidewalk: [{ polygon: [{ x: -1, z: -1 }, { x: 7, z: -1 }, { x: 7, z: 7 }] }],
    },
    ...overrides,
  };
}

test('normalizeWorldData adds safe defaults for missing optional sections', () => {
  const normalizeWorldData = loadNormalizeWorldData();
  const normalized = normalizeWorldData(minimalValidWorld());

  assert.equal(Array.isArray(normalized.landmarks), true);
  assert.equal(Array.isArray(normalized.audioZones), true);
  assert.equal(Array.isArray(normalized.hero_landmarks), true);
  assert.equal(typeof normalized.facade_profiles, 'object');
  assert.equal(Array.isArray(normalized.navigator.focusCorridor), true);
  assert.equal(Array.isArray(normalized.streetscape.lamps), true);
  assert.equal(Array.isArray(normalized.streetscape.trees), true);
  assert.equal(Array.isArray(normalized.streetscape.bollards), true);
  assert.equal(Array.isArray(normalized.streetscape.surfaceBands), true);
  assert.equal(Array.isArray(normalized.props), true);
  assert.equal(Array.isArray(normalized.npcs), true);
});

test('starter-like world with no landmarks or audioZones normalizes without crashing', () => {
  const normalizeWorldData = loadNormalizeWorldData();
  const starter = minimalValidWorld({ streetscape: {} });
  delete starter.landmarks;
  delete starter.audioZones;

  const normalized = normalizeWorldData(starter);

  assert.equal(Array.isArray(normalized.landmarks), true);
  assert.equal(Array.isArray(normalized.audioZones), true);
  assert.equal(normalized.landmarks.length, 0);
  assert.equal(normalized.audioZones.length, 0);
});

test('normalizeWorldData guarantees required mood/weather/time presets and fields', () => {
  const normalizeWorldData = loadNormalizeWorldData();
  const normalized = normalizeWorldData(minimalValidWorld());

  const requiredMoodKeys = ['ambientBed', 'audioDensity', 'voiceFreq', 'lightIntensity'];
  const requiredWeatherKeys = ['rainIntensity', 'fogDensity', 'cloudCoverage', 'cloudDarkness', 'cloudAltitude', 'lightningFrequency', 'lightningIntensity', 'thunderEnabled'];
  const requiredTimeKeys = [
    'sky', 'ambientColor', 'ambientIntensity', 'keyColor', 'keyIntensity', 'fillColor',
    'fillIntensity', 'buildingContrast', 'buildingEdgeColor', 'roadColor', 'sidewalkColor',
    'laneColor', 'landmarkGlow', 'rainVisibility', 'pathBrightness', 'fogBoost'
  ];

  ['eerie', 'calm', 'lively'].forEach((id) => {
    const preset = normalized.moodPresets[id];
    assert.ok(preset, `missing mood preset ${id}`);
    requiredMoodKeys.forEach((key) => assert.notEqual(preset[key], undefined));
  });

  ['clear', 'rain', 'fog', 'thunderstorm'].forEach((id) => {
    const preset = normalized.weatherPresets[id];
    assert.ok(preset, `missing weather preset ${id}`);
    requiredWeatherKeys.forEach((key) => assert.notEqual(preset[key], undefined));
  });

  ['morning', 'dusk', 'night'].forEach((id) => {
    const preset = normalized.timeOfDayPresets[id];
    assert.ok(preset, `missing time preset ${id}`);
    requiredTimeKeys.forEach((key) => assert.notEqual(preset[key], undefined));
  });
});

test('classic sim startup paths are guarded against missing optional arrays', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim-classic.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('world.landmarks.forEach('), false);
  assert.equal(src.includes('world.audioZones.forEach('), false);
  assert.equal(src.includes('state.world.landmarks.find('), false);
  assert.equal(src.includes('state.world.audioZones.forEach('), false);
  assert.equal(src.includes('(world.landmarks || []).forEach('), true);
  assert.equal(src.includes('(world.audioZones || []).forEach('), true);
  assert.equal(src.includes('(state.world.landmarks || []).find('), true);
  assert.equal(src.includes('(state.world.audioZones || []).forEach('), true);
});


test('normalizeWorldData safely defaults missing or partial props and npcs', () => {
  const normalizeWorldData = loadNormalizeWorldData();
  const normalized = normalizeWorldData(minimalValidWorld({
    props: [{ id: 'bag-1', kind: 'trash_bag', x: 1, z: 2 }],
    npcs: [{ id: 'guide-1', role: 'guide', dialogId: 'guide_intro' }],
  }));

  assert.equal(normalized.props.length, 1);
  assert.equal(normalized.props[0].scale, 1);
  assert.equal(normalized.props[0].yaw, 0);
  assert.equal(normalized.npcs.length, 1);
  assert.equal(normalized.npcs[0].interactRadius, 2.4);
  assert.equal(Array.isArray(normalized.npcs[0].patrol), true);
  assert.equal(typeof normalized.npcs[0].idleSpot.x, 'number');
  assert.equal(typeof normalized.npcs[0].idleSpot.z, 'number');
});
