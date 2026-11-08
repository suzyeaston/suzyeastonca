'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { DURATION, CUES, sample, cueAt } = require('../experiments/soft-reset/cues.js');

test('the film is exactly 60 seconds and every act touches the next', () => {
  assert.equal(DURATION, 60);
  assert.equal(CUES.length, 5);
  assert.equal(CUES[0].from, 0);
  assert.equal(CUES[CUES.length - 1].to, DURATION);
  for (let i = 1; i < CUES.length; i++) {
    assert.equal(CUES[i - 1].to, CUES[i].from);
  }
});

test('cue lookup follows the five shot blocks, including final frame', () => {
  assert.equal(cueAt(0).id, 'the-world');
  assert.equal(cueAt(10).id, 'accelerating');
  assert.equal(cueAt(24).id, 'war');
  assert.equal(cueAt(38).id, 'interruption');
  assert.equal(cueAt(48).id, 'bloom');
  assert.equal(cueAt(60).id, 'bloom');
});

test('all exported intensity controls are finite normalized values at every frame', () => {
  const controls = ['distortion', 'mechanicalGrowth', 'warningGlow', 'fracturedSky', 'bioluminescence', 'collectiveMotion', 'humanSignal', 'peace', 'visualPulse'];
  for (let frame = 0; frame <= 60 * 30; frame++) {
    const state = sample(frame / 30, {bass: 0.82, mids: 0.38, highs: 0.57});
    for (const key of controls) {
      assert.ok(Number.isFinite(state[key]), key + ' finite at frame ' + frame);
      assert.ok(state[key] >= 0 && state[key] <= 1, key + ' normalized at frame ' + frame);
    }
  }
});

test('film begins unbroken, reaches conflict, then regrows instead of merely changing tint', () => {
  assert.equal(sample(0).distortion, 0);
  assert.equal(sample(0).bioluminescence, 0);
  assert.ok(sample(30, {bass: 1}).warningGlow > 0.5);
  assert.ok(sample(57, {highs: 1}).bioluminescence > sample(45, {highs: 1}).bioluminescence);
  assert.ok(sample(60).collectiveMotion > 0);
  assert.ok(sample(60).distortion < sample(30).distortion);
  assert.equal(sample(39).isSilentTurn, true);
  assert.equal(sample(44).isSilentTurn, false);
});

test('scrubbing clamps time and bad meter input without NaN or state dependence', () => {
  assert.equal(sample(-200).seconds, 0);
  assert.equal(sample(400).seconds, 60);
  const a = sample(24, {bass: Number.NaN, mids: -20, highs: 900});
  const b = sample(24, {bass: 0, mids: 0, highs: 1});
  assert.deepEqual(a, b);
  assert.deepEqual(sample(28, {bass: 0.6}), sample(28, {bass: 0.6}));
});
