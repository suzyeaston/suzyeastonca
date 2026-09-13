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
const TEMPLATE = fs.readFileSync(path.join(ROOT, 'page-appliance-latent-space.php'), 'utf8');
const STYLES = fs.readFileSync(path.join(ROOT, 'assets', 'css', 'appliance-latent-space.css'), 'utf8');

// Interactive elements are the only things allowed to carry data-control, so this doubles
// as the list of controls the template claims to expose.
function templateControlTags() {
  const tags = [];
  const pattern = /<(input|button|select|textarea)\b[^>]*\bdata-control="([^"]*)"[^>]*>/g;
  let match = pattern.exec(TEMPLATE);
  while (match) {
    tags.push({ element: match[1], id: match[2], markup: match[0] });
    match = pattern.exec(TEMPLATE);
  }
  return tags;
}

function attribute(markup, name) {
  const match = markup.match(new RegExp(`\\b${name}="([^"]*)"`));
  return match ? match[1] : null;
}

function keyHint(control) {
  return control.keyboard.key === ' ' ? 'SPACE' : control.keyboard.key.toUpperCase();
}

// Splits the stylesheet into the selector list in front of every declaration block.
function styleSelectors() {
  const withoutComments = STYLES.replace(/\/\*[\s\S]*?\*\//g, '');
  const selectors = [];
  const pattern = /([^{}]+)\{/g;
  let match = pattern.exec(withoutComments);
  while (match) {
    match[1]
      .split(',')
      .map((selector) => selector.trim())
      .filter(Boolean)
      .forEach((selector) => selectors.push(selector));
    match = pattern.exec(withoutComments);
  }
  return selectors;
}

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

test('the template exposes exactly the controls the map declares', () => {
  const rendered = templateControlTags().map((tag) => tag.id);
  assert.deepEqual(rendered.slice().sort(), EXPECTED_IDS.slice().sort(), 'template and control map disagree');
  assert.equal(new Set(rendered).size, rendered.length, 'a control is rendered more than once');
});

test('every rendered control id resolves against the control map', () => {
  for (const tag of templateControlTags()) {
    assert.ok(controlById(MAP, tag.id), `template renders ${tag.id}, which the control map does not define`);
  }
});

test('continuous controls render as native range inputs matching the map', () => {
  for (const control of MAP.controls.filter((entry) => entry.kind === 'continuous')) {
    const tag = templateControlTags().find((entry) => entry.id === control.id);
    assert.equal(tag.element, 'input', `${control.id} must be an input`);
    assert.equal(attribute(tag.markup, 'type'), 'range', `${control.id} must be a range input`);
    assert.equal(Number(attribute(tag.markup, 'min')), control.range[0], `${control.id} min drifted from the map`);
    assert.equal(Number(attribute(tag.markup, 'max')), control.range[1], `${control.id} max drifted from the map`);
    assert.equal(Number(attribute(tag.markup, 'step')), control.keyboard.step, `${control.id} step drifted from the map`);
    assert.equal(Number(attribute(tag.markup, 'value')), control.default, `${control.id} value drifted from the map`);
  }
});

test('momentary and toggle controls render as native buttons', () => {
  for (const control of MAP.controls.filter((entry) => entry.kind !== 'continuous')) {
    const tag = templateControlTags().find((entry) => entry.id === control.id);
    assert.equal(tag.element, 'button', `${control.id} must be a button`);
    assert.equal(attribute(tag.markup, 'type'), 'button', `${control.id} must not submit anything`);
    const pressed = attribute(tag.markup, 'aria-pressed');
    if (control.kind === 'toggle') assert.equal(pressed, 'false', `${control.id} needs a starting pressed state`);
    else assert.equal(pressed, null, `${control.id} is momentary and should not claim a pressed state`);
  }
});

test('every control sits inside its declared group', () => {
  const groupPattern = /data-control-group="([a-z_]+)"([\s\S]*?)<\/fieldset>/g;
  const rendered = {};
  let match = groupPattern.exec(TEMPLATE);
  while (match) {
    rendered[match[1]] = match[2];
    match = groupPattern.exec(TEMPLATE);
  }

  assert.deepEqual(Object.keys(rendered), MAP.groups.map((group) => group.id));
  for (const control of MAP.controls) {
    assert.match(rendered[control.group], new RegExp(`data-control="${control.id}"`), `${control.id} is outside the ${control.group} group`);
  }
});

test('groups render as fieldsets with a visible legend', () => {
  for (const group of MAP.groups) {
    assert.match(TEMPLATE, new RegExp(`<fieldset[^>]*data-control-group="${group.id}"`), `${group.id} must be a fieldset`);
    assert.match(TEMPLATE, new RegExp(`<legend[^>]*>${group.label}</legend>`), `${group.id} needs a legend`);
  }
});

test('every control shows its keyboard hint, with space spelled out', () => {
  for (const control of MAP.controls) {
    const hint = keyHint(control);
    assert.match(TEMPLATE, new RegExp(`<kbd[^>]*>${hint}</kbd>`), `${control.id} is missing its ${hint} key chip`);
    assert.match(TEMPLATE, new RegExp(`id="appliance-hint-${control.id}"[^>]*>[^<]*key ${hint}`), `${control.id} hint text should name its key`);
  }
  assert.match(TEMPLATE, /<kbd[^>]*>SPACE<\/kbd>/);
  assert.doesNotMatch(TEMPLATE, /<kbd[^>]*> <\/kbd>/, 'the space binding must never render as a literal space');
});

test('controls are described and labelled for assistive tech', () => {
  for (const tag of templateControlTags()) {
    assert.equal(attribute(tag.markup, 'aria-describedby'), `appliance-hint-${tag.id}`, `${tag.id} needs its hint wired up`);
  }
  for (const control of MAP.controls.filter((entry) => entry.kind === 'continuous')) {
    assert.match(TEMPLATE, new RegExp(`<label[^>]*for="appliance-control-${control.id}"[^>]*>${control.label}</label>`), `${control.id} needs a real label`);
  }
});

test('the template registers itself the way every other page template does', () => {
  assert.match(TEMPLATE, /^<\?php\s*\/\*\s*Template Name: Appliance Latent Space\s*\*\//);
  assert.match(TEMPLATE, /^get_header\(\);$/m);
  assert.match(TEMPLATE, /<\?php get_footer\(\); \?>\s*$/);
  assert.equal((TEMPLATE.match(/<\?php/g) || []).length, (TEMPLATE.match(/\?>/g) || []).length);
});

test('the template is static markup with no behaviour attached yet', () => {
  assert.match(TEMPLATE, /id="appliance-latent-space-app"/);
  assert.doesNotMatch(TEMPLATE, /<script/i);
  assert.doesNotMatch(TEMPLATE, /\son[a-z]+="/i, 'no inline event handlers');
});

test('status copy states the prototype has no model and no sound', () => {
  assert.match(TEMPLATE, /no audio engine behind them yet/);
  assert.match(TEMPLATE, /no model is running/);
  assert.doesNotMatch(TEMPLATE, /ai-powered|powered by ai|neural network/i);
  // The page may say a model is not running. It may never say one is.
  assert.doesNotMatch(TEMPLATE, /(?<!no )(model|inference) is running/i);
  assert.doesNotMatch(TEMPLATE, /seamless|innovative|cutting-edge|leverage/i);
});

test('every style rule is scoped to the appliance page', () => {
  const selectors = styleSelectors();
  assert.ok(selectors.length > 20, 'expected a real stylesheet');
  for (const selector of selectors) {
    if (selector.startsWith('@') || /^(from|to|\d+%)$/.test(selector)) continue;
    assert.ok(
      selector === '.appliance-page' || selector.startsWith('.appliance-page '),
      `unscoped selector would leak into the rest of the site: ${selector}`
    );
  }
});

test('slider pseudo-elements stay in separate rules per engine', () => {
  assert.doesNotMatch(STYLES, /::-webkit-[^{,]*,[^{]*::-moz-/);
  assert.doesNotMatch(STYLES, /::-moz-[^{,]*,[^{]*::-webkit-/);
  assert.match(STYLES, /::-webkit-slider-runnable-track/);
  assert.match(STYLES, /::-moz-range-track/);
});

test('appliance runtime files are listed in the theme deploy manifest', () => {
  const manifest = fs.readFileSync(path.join(ROOT, 'scripts', 'theme_deploy_manifest.py'), 'utf8');
  assert.match(manifest, /"page-appliance-latent-space\.php"/);
  assert.match(manifest, /"assets\/css\/appliance-latent-space\.css"/);
  assert.match(manifest, /"assets\/data\/appliance-latent-space\/control-map\.json"/);
  assert.match(manifest, /"js\/appliance-control-map\.js"/);
});

test('the control module stays free of DOM, audio and network', () => {
  assert.doesNotMatch(MODULE_SOURCE, /document|window\.|AudioContext|fetch\(|XMLHttpRequest|requestMIDIAccess/);
});
