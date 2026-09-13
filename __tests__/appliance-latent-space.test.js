const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {
  CONTROL_KINDS,
  normalizeControlMap,
  controlById,
  resolveKeyBinding,
  resolveMidiBinding,
  midiBindingKey,
  clampControlValue,
  createControlState,
  applyKeyboardStep,
  createControlEvent,
} = require('../js/appliance-control-map.js');

const ROOT = path.resolve(__dirname, '..');
const CONTROL_MAP_PATH = path.join(ROOT, 'assets', 'data', 'appliance-latent-space', 'control-map.json');
const RAW_MAP = JSON.parse(fs.readFileSync(CONTROL_MAP_PATH, 'utf8'));
const MAP = normalizeControlMap(RAW_MAP);
const CONTROL_MAP_DOC = fs.readFileSync(path.join(ROOT, 'docs', 'appliance-latent-space', 'control-map.md'), 'utf8');
const MODULE_SOURCE = fs.readFileSync(path.join(ROOT, 'js', 'appliance-control-map.js'), 'utf8');

const EXPECTED_IDS = [
  'browning',
  'destruction',
  'latent_x',
  'latent_y',
  'neural_mix',
  'memory',
  'freeze',
  'plunge',
  'capture',
  'kill',
];

test('the shipped control map declares the agreed vocabulary', () => {
  assert.equal(MAP.schemaVersion, '0.1.0');
  assert.equal(MAP.instrumentId, 'appliance-latent-space');
  assert.equal(MAP.instrumentName, 'The Appliance Latent Space');
  assert.deepEqual(MAP.controls.map((control) => control.id).sort(), EXPECTED_IDS.slice().sort());
});

test('every raw control survives normalization', () => {
  assert.equal(MAP.controls.length, RAW_MAP.controls.length);
});

test('controls use declared groups and known kinds', () => {
  const groupIds = MAP.groups.map((group) => group.id);
  assert.deepEqual(groupIds, ['tone', 'latent', 'time', 'gesture']);
  for (const control of MAP.controls) {
    assert.ok(CONTROL_KINDS.includes(control.kind), `${control.id} has an unknown kind`);
    assert.ok(groupIds.includes(control.group), `${control.id} is not in a declared group`);
    assert.ok(control.label.length, `${control.id} needs a label`);
    assert.ok(control.description.length, `${control.id} needs a description`);
  }
});

test('ranges are ordered and defaults sit inside them', () => {
  for (const control of MAP.controls) {
    const [min, max] = control.range;
    assert.ok(min < max, `${control.id} has an empty range`);
    assert.ok(control.default >= min && control.default <= max, `${control.id} default is outside its range`);
    if (control.kind !== 'continuous') {
      assert.ok(control.default === 0 || control.default === 1, `${control.id} default must be binary`);
    }
  }
});

test('no two controls claim the same key or the same MIDI binding', () => {
  const keys = MAP.controls.filter((control) => control.keyboard).map((control) => control.keyboard.key);
  const bindings = MAP.controls.map((control) => midiBindingKey(control.midi)).filter(Boolean);
  assert.equal(new Set(keys).size, keys.length);
  assert.equal(new Set(bindings).size, bindings.length);
  // Normalization strips duplicate claims, so a clean map keeps every binding it declared.
  assert.equal(keys.length, MAP.controls.length);
  assert.equal(bindings.length, MAP.controls.length);
});

test('hardware bindings stay unset until there is an appliance', () => {
  assert.doesNotMatch(fs.readFileSync(CONTROL_MAP_PATH, 'utf8'), /"hardware"/);
  for (const control of MAP.controls) {
    assert.equal(control.hardware, null, `${control.id} should not have hardware metadata yet`);
  }
});

test('every control id is documented in control-map.md', () => {
  for (const id of EXPECTED_IDS) {
    assert.match(CONTROL_MAP_DOC, new RegExp(`\`${id}\``), `${id} is missing from the control map doc`);
  }
});

test('keyboard and MIDI bindings resolve back to their own control', () => {
  for (const control of MAP.controls) {
    assert.equal(resolveKeyBinding(MAP, control.keyboard.key).id, control.id);
    assert.equal(resolveMidiBinding(MAP, control.midi).id, control.id);
  }
  assert.equal(resolveKeyBinding(MAP, 'plunge'), null, 'a control id is not a key binding');
});

test('binding lookups reject unknown and malformed input', () => {
  assert.equal(resolveKeyBinding(MAP, 'q'), null);
  assert.equal(resolveKeyBinding(MAP, ''), null);
  assert.equal(resolveKeyBinding(null, 'b'), null);
  assert.equal(resolveMidiBinding(MAP, { type: 'cc', channel: 1, number: 99 }), null);
  assert.equal(resolveMidiBinding(MAP, { type: 'cc', channel: 2, number: 21 }), null);
  assert.equal(resolveMidiBinding(MAP, { type: 'note', channel: 1, number: 21 }), null);
  assert.equal(resolveMidiBinding(MAP, null), null);
});

test('key lookup is case-insensitive and understands the space bar', () => {
  assert.equal(resolveKeyBinding(MAP, 'B').id, 'browning');
  assert.equal(resolveKeyBinding(MAP, ' ').id, 'plunge');
});

test('controlById finds controls and rejects junk', () => {
  assert.equal(controlById(MAP, 'latent_x').label, 'latent x');
  assert.equal(controlById(MAP, 'toaster'), null);
  assert.equal(controlById(MAP, ''), null);
  assert.equal(controlById(MAP, null), null);
});

test('normalizeControlMap drops malformed controls and fills defaults', () => {
  const map = normalizeControlMap({
    groups: [{ id: 'tone', label: 'tone' }, { id: 'tone' }, { label: 'nameless' }],
    controls: [
      { id: 'good', kind: 'continuous', group: 'tone' },
      { id: 'Bad-Id', kind: 'continuous' },
      { id: 'no_kind' },
      { id: 'unknown_kind', kind: 'sideways' },
      null,
      'nope',
    ],
  });

  assert.deepEqual(map.groups, [{ id: 'tone', label: 'tone' }]);
  assert.equal(map.controls.length, 1);
  assert.deepEqual(map.controls[0], {
    id: 'good',
    label: 'good',
    description: '',
    kind: 'continuous',
    group: 'tone',
    range: [0, 1],
    default: 0,
    keyboard: null,
    midi: null,
    hardware: null,
  });
  assert.equal(map.schemaVersion, '0.0.0');
  assert.equal(map.instrumentId, 'appliance-latent-space');
});

test('normalizeControlMap survives being handed nothing', () => {
  const map = normalizeControlMap(null);
  assert.deepEqual(map.groups, []);
  assert.deepEqual(map.controls, []);
  assert.equal(map.instrumentName, 'The Appliance Latent Space');
});

test('duplicate ids and duplicate bindings lose to the first claim', () => {
  const map = normalizeControlMap({
    groups: [{ id: 'tone' }],
    controls: [
      { id: 'first', kind: 'continuous', group: 'tone', keyboard: { key: 'b' }, midi: { type: 'cc', channel: 1, number: 21 } },
      { id: 'second', kind: 'continuous', group: 'tone', keyboard: { key: 'B' }, midi: { type: 'cc', channel: 1, number: 21 } },
      { id: 'first', kind: 'toggle', group: 'tone' },
    ],
  });

  assert.deepEqual(map.controls.map((control) => control.id), ['first', 'second']);
  assert.equal(map.controls[0].keyboard.key, 'b');
  assert.equal(map.controls[1].keyboard, null);
  assert.equal(map.controls[1].midi, null);
  assert.equal(resolveKeyBinding(map, 'b').id, 'first');
});

test('invalid bindings and ranges fall back instead of throwing', () => {
  const map = normalizeControlMap({
    groups: [{ id: 'tone' }],
    controls: [
      {
        id: 'wobbly',
        kind: 'continuous',
        group: 'ghost',
        range: [1, 1],
        default: 9,
        keyboard: { key: '', step: -1 },
        midi: { type: 'cc', channel: 0, number: 21 },
        hardware: {},
      },
      { id: 'stepless', kind: 'continuous', group: 'tone', keyboard: { key: 's', step: 'fast' } },
      { id: 'wide_note', kind: 'momentary', group: 'tone', midi: { type: 'note', channel: 1, number: 200 } },
    ],
  });

  const [wobbly, stepless, wideNote] = map.controls;
  assert.deepEqual(wobbly.range, [0, 1]);
  assert.equal(wobbly.default, 1);
  assert.equal(wobbly.group, '');
  assert.equal(wobbly.keyboard, null);
  assert.equal(wobbly.midi, null);
  assert.equal(wobbly.hardware, null);
  assert.equal(stepless.keyboard.step, 0.05);
  assert.equal(wideNote.midi, null);
});

test('hardware metadata is kept once a control declares it', () => {
  const map = normalizeControlMap({
    groups: [{ id: 'tone' }],
    controls: [{ id: 'browning', kind: 'continuous', group: 'tone', hardware: { source: 'front-dial' } }],
  });
  assert.deepEqual(map.controls[0].hardware, { source: 'front-dial', notes: '' });
});

test('midiBindingKey builds a stable lookup key', () => {
  assert.equal(midiBindingKey({ type: 'cc', channel: 1, number: 21 }), 'cc:1:21');
  assert.equal(midiBindingKey({ type: 'NOTE', channel: 16, number: 0 }), 'note:16:0');
  assert.equal(midiBindingKey({ type: 'pitchbend', channel: 1, number: 0 }), '');
  assert.equal(midiBindingKey(null), '');
});

test('continuous values clamp to their range', () => {
  const browning = controlById(MAP, 'browning');
  assert.equal(clampControlValue(browning, -4), 0);
  assert.equal(clampControlValue(browning, 0.42), 0.42);
  assert.equal(clampControlValue(browning, 4), 1);
  assert.equal(clampControlValue(browning, NaN), 0);
  assert.equal(clampControlValue(null, 0.5), 0);
});

test('momentary and toggle values collapse to zero or one', () => {
  const plunge = controlById(MAP, 'plunge');
  const freeze = controlById(MAP, 'freeze');
  assert.equal(clampControlValue(plunge, 0.2), 0);
  assert.equal(clampControlValue(plunge, 0.5), 1);
  assert.equal(clampControlValue(plunge, 7), 1);
  assert.equal(clampControlValue(freeze, -1), 0);
});

test('createControlState seeds every control with its default', () => {
  const state = createControlState(MAP);
  assert.deepEqual(Object.keys(state).sort(), EXPECTED_IDS.slice().sort());
  assert.equal(state.browning, 0.35);
  assert.equal(state.latent_x, 0.5);
  assert.equal(state.neural_mix, 0);
  assert.equal(state.freeze, 0);
  assert.deepEqual(createControlState(null), {});
});

test('keyboard steps move continuous controls and stop at the rails', () => {
  const browning = controlById(MAP, 'browning');
  assert.equal(applyKeyboardStep(browning, 0.5), 0.55);
  assert.equal(applyKeyboardStep(browning, 0.5, { shiftKey: true }), 0.45);
  assert.equal(applyKeyboardStep(browning, 1), 1);
  assert.equal(applyKeyboardStep(browning, 0, { shiftKey: true }), 0);
  assert.equal(applyKeyboardStep(null, 0.5), 0);
});

test('repeated keyboard steps do not accumulate floating point drift', () => {
  const browning = controlById(MAP, 'browning');
  const walked = [];
  let value = browning.default;
  for (let press = 0; press < 13; press += 1) {
    value = applyKeyboardStep(browning, value);
    walked.push(value);
  }
  assert.deepEqual(walked, [0.4, 0.45, 0.5, 0.55, 0.6, 0.65, 0.7, 0.75, 0.8, 0.85, 0.9, 0.95, 1]);
});

test('keyboard steps fire momentary controls and flip toggles', () => {
  const plunge = controlById(MAP, 'plunge');
  const freeze = controlById(MAP, 'freeze');
  assert.equal(applyKeyboardStep(plunge, 0), 1);
  assert.equal(applyKeyboardStep(plunge, 1, { shiftKey: true }), 1);
  assert.equal(applyKeyboardStep(freeze, 0), 1);
  assert.equal(applyKeyboardStep(freeze, 1), 0);
});

test('every input source produces the same event shape', () => {
  const browning = controlById(MAP, 'browning');
  const pointer = createControlEvent(browning, 0.6, { source: 'pointer', at: 12 });
  const keyboard = createControlEvent(browning, 0.6, { source: 'keyboard', at: 12 });
  const midi = createControlEvent(browning, 0.6, { source: 'midi', at: 12 });

  assert.deepEqual(pointer, { id: 'browning', kind: 'continuous', group: 'tone', value: 0.6, source: 'pointer', at: 12 });
  assert.deepEqual({ ...keyboard, source: 'pointer' }, pointer);
  assert.deepEqual({ ...midi, source: 'pointer' }, pointer);
});

test('control events clamp their value and reject unknown sources', () => {
  const plunge = controlById(MAP, 'plunge');
  assert.equal(createControlEvent(plunge, 3).value, 1);
  assert.equal(createControlEvent(plunge, 0, { source: 'telepathy' }).source, 'pointer');
  assert.equal(createControlEvent(plunge, 0, { at: 'soon' }).at, 0);
  assert.equal(createControlEvent(null, 1), null);
});

test('appliance runtime files are listed in the theme deploy manifest', () => {
  const manifest = fs.readFileSync(path.join(ROOT, 'scripts', 'theme_deploy_manifest.py'), 'utf8');
  assert.match(manifest, /"assets\/data\/appliance-latent-space\/control-map\.json"/);
  assert.match(manifest, /"js\/appliance-control-map\.js"/);
});

test('the control module stays free of DOM, audio and network', () => {
  assert.doesNotMatch(MODULE_SOURCE, /document|window\.|AudioContext|fetch\(|XMLHttpRequest|requestMIDIAccess/);
});
