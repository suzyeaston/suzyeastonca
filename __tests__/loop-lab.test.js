const test = require('node:test');
const assert = require('node:assert/strict');
const { formatDuration, createLayerName } = require('../js/loop-lab.js');

test('formatDuration returns a one-decimal seconds label', () => {
  assert.equal(formatDuration(0), '00.0s');
  assert.equal(formatDuration(1.24), '01.2s');
  assert.equal(formatDuration(12.99), '13.0s');
});

test('createLayerName uses human track numbering', () => {
  assert.equal(createLayerName(0), 'Layer 01');
  assert.equal(createLayerName(9), 'Layer 10');
});
