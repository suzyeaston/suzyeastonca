const test = require('node:test');
const assert = require('node:assert/strict');
const { installMockFetch } = require('./helpers');

installMockFetch();

const app = require('../lousy-outages/assets/lousy-outages.js');

test('normalizeStatus returns normalized code and label for operational', () => {
  const normalized = app.normalizeStatus('operational');
  assert.deepEqual(normalized, {
    code: 'operational',
    label: 'Operational',
    className: 'status--operational'
  });
});

test('normalizeStatus falls back to unknown for unexpected values', () => {
  const normalized = app.normalizeStatus('critical');
  assert.equal(normalized.code, 'unknown');
  assert.equal(normalized.label, 'Unknown');
  assert.equal(normalized.className, 'status--unknown');
});
