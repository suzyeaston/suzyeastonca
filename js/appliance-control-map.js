(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
    return;
  }

  root.ApplianceControlMap = factory();
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const CONTROL_KINDS = ['continuous', 'momentary', 'toggle'];
  const MIDI_BINDING_TYPES = ['cc', 'note'];
  const INPUT_SOURCES = ['pointer', 'keyboard', 'midi'];
  const DEFAULT_RANGE = [0, 1];
  const DEFAULT_STEP = 0.05;

  function isPlainObject(value) {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
  }

  function isFiniteNumber(value) {
    return typeof value === 'number' && Number.isFinite(value);
  }

  function sanitizeControlId(value) {
    return typeof value === 'string' && /^[a-z][a-z0-9_]*$/.test(value) ? value : '';
  }

  function sanitizeSlug(value) {
    return typeof value === 'string' && /^[a-z][a-z0-9-]*$/.test(value) ? value : '';
  }

  function sanitizeText(value, fallback) {
    const trimmed = typeof value === 'string' ? value.trim() : '';
    return trimmed || fallback;
  }

  function sanitizeRange(value) {
    if (!Array.isArray(value) || value.length !== 2) return DEFAULT_RANGE.slice();
    if (!isFiniteNumber(value[0]) || !isFiniteNumber(value[1]) || value[0] >= value[1]) return DEFAULT_RANGE.slice();
    return [value[0], value[1]];
  }

  // Keys are compared against KeyboardEvent.key, which reports a single space for Space.
  function sanitizeKey(value) {
    if (typeof value !== 'string' || !value.length) return '';
    if (value === ' ') return ' ';
    return value.trim().toLowerCase();
  }

  function sanitizeKeyboard(value) {
    if (!isPlainObject(value)) return null;
    const key = sanitizeKey(value.key);
    if (!key) return null;
    return { key, step: isFiniteNumber(value.step) && value.step > 0 ? value.step : DEFAULT_STEP };
  }

  function sanitizeMidi(value) {
    if (!isPlainObject(value)) return null;
    const type = typeof value.type === 'string' ? value.type.trim().toLowerCase() : '';
    if (MIDI_BINDING_TYPES.indexOf(type) === -1) return null;
    if (!Number.isInteger(value.channel) || value.channel < 1 || value.channel > 16) return null;
    if (!Number.isInteger(value.number) || value.number < 0 || value.number > 127) return null;
    return { type, channel: value.channel, number: value.number };
  }

  // The appliance does not exist yet, so hardware bindings stay optional until there is
  // something to solder them to.
  function sanitizeHardware(value) {
    if (!isPlainObject(value)) return null;
    const source = sanitizeText(value.source, '');
    const notes = sanitizeText(value.notes, '');
    if (!source && !notes) return null;
    return { source, notes };
  }

  function clampControlValue(control, value) {
    if (!isPlainObject(control)) return 0;
    const numeric = isFiniteNumber(value) ? value : 0;
    if (control.kind !== 'continuous') return numeric >= 0.5 ? 1 : 0;
    const range = sanitizeRange(control.range);
    return Math.min(range[1], Math.max(range[0], numeric));
  }

  function normalizeControl(raw, groupIds) {
    if (!isPlainObject(raw)) return null;
    const id = sanitizeControlId(raw.id);
    if (!id) return null;
    if (CONTROL_KINDS.indexOf(raw.kind) === -1) return null;

    const control = {
      id,
      label: sanitizeText(raw.label, id),
      description: sanitizeText(raw.description, ''),
      kind: raw.kind,
      group: groupIds.indexOf(raw.group) === -1 ? '' : raw.group,
      range: raw.kind === 'continuous' ? sanitizeRange(raw.range) : DEFAULT_RANGE.slice(),
      default: 0,
      keyboard: sanitizeKeyboard(raw.keyboard),
      midi: sanitizeMidi(raw.midi),
      hardware: sanitizeHardware(raw.hardware),
    };
    control.default = clampControlValue(control, isFiniteNumber(raw.default) ? raw.default : control.range[0]);
    return control;
  }

  function midiBindingKey(binding) {
    const sanitized = sanitizeMidi(binding);
    return sanitized ? `${sanitized.type}:${sanitized.channel}:${sanitized.number}` : '';
  }

  function normalizeGroups(raw) {
    const groups = [];
    const seen = [];
    (Array.isArray(raw) ? raw : []).forEach((entry) => {
      if (!isPlainObject(entry)) return;
      const id = sanitizeControlId(entry.id);
      if (!id || seen.indexOf(id) !== -1) return;
      seen.push(id);
      groups.push({ id, label: sanitizeText(entry.label, id) });
    });
    return groups;
  }

  // Two controls fighting over one key or one CC would make the physical and browser
  // instruments disagree, so the first claim wins and later duplicates lose the binding.
  function normalizeControlMap(raw) {
    const source = isPlainObject(raw) ? raw : {};
    const groups = normalizeGroups(source.groups);
    const groupIds = groups.map((group) => group.id);
    const controls = [];
    const claimedIds = [];
    const claimedKeys = [];
    const claimedMidi = [];

    (Array.isArray(source.controls) ? source.controls : []).forEach((entry) => {
      const control = normalizeControl(entry, groupIds);
      if (!control || claimedIds.indexOf(control.id) !== -1) return;
      claimedIds.push(control.id);

      if (control.keyboard) {
        if (claimedKeys.indexOf(control.keyboard.key) === -1) claimedKeys.push(control.keyboard.key);
        else control.keyboard = null;
      }

      const midiKey = midiBindingKey(control.midi);
      if (midiKey) {
        if (claimedMidi.indexOf(midiKey) === -1) claimedMidi.push(midiKey);
        else control.midi = null;
      }

      controls.push(control);
    });

    return {
      schemaVersion: sanitizeText(source.schemaVersion, '0.0.0'),
      instrumentId: sanitizeSlug(source.instrumentId) || 'appliance-latent-space',
      instrumentName: sanitizeText(source.instrumentName, 'The Appliance Latent Space'),
      groups,
      controls,
    };
  }

  function mapControls(map) {
    return isPlainObject(map) && Array.isArray(map.controls) ? map.controls : [];
  }

  function controlById(map, id) {
    const wanted = sanitizeControlId(id);
    if (!wanted) return null;
    return mapControls(map).find((control) => control.id === wanted) || null;
  }

  function resolveKeyBinding(map, key) {
    const wanted = sanitizeKey(key);
    if (!wanted) return null;
    return mapControls(map).find((control) => control.keyboard && control.keyboard.key === wanted) || null;
  }

  function resolveMidiBinding(map, message) {
    const wanted = midiBindingKey(message);
    if (!wanted) return null;
    return mapControls(map).find((control) => midiBindingKey(control.midi) === wanted) || null;
  }

  function createControlState(map) {
    const state = {};
    mapControls(map).forEach((control) => {
      state[control.id] = clampControlValue(control, control.default);
    });
    return state;
  }

  function applyKeyboardStep(control, currentValue, options) {
    if (!isPlainObject(control)) return 0;
    if (control.kind === 'momentary') return 1;
    if (control.kind === 'toggle') return clampControlValue(control, currentValue) ? 0 : 1;
    const settings = isPlainObject(options) ? options : {};
    const step = control.keyboard && isFiniteNumber(control.keyboard.step) ? control.keyboard.step : DEFAULT_STEP;
    const next = clampControlValue(control, currentValue) + (settings.shiftKey ? -step : step);
    // Holding a key walks this function hundreds of times; without the rounding the readout
    // drifts into 0.45000000000000007 territory within a few presses.
    return clampControlValue(control, Math.round(next * 1e6) / 1e6);
  }

  // Every input source collapses into this one event shape. A virtual knob, a keypress and
  // a future toaster all have to hand the engine the same thing or the vocabulary is a lie.
  function createControlEvent(control, value, options) {
    if (!isPlainObject(control)) return null;
    const settings = isPlainObject(options) ? options : {};
    return {
      id: control.id,
      kind: control.kind,
      group: control.group,
      value: clampControlValue(control, value),
      source: INPUT_SOURCES.indexOf(settings.source) === -1 ? 'pointer' : settings.source,
      at: isFiniteNumber(settings.at) ? settings.at : 0,
    };
  }

  return {
    CONTROL_KINDS,
    MIDI_BINDING_TYPES,
    INPUT_SOURCES,
    normalizeControlMap,
    controlById,
    resolveKeyBinding,
    resolveMidiBinding,
    midiBindingKey,
    clampControlValue,
    createControlState,
    applyKeyboardStep,
    createControlEvent,
  };
});
