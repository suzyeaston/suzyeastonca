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

test('normalizeStatus maps critical to major outage', () => {
  const normalized = app.normalizeStatus('critical');
  assert.equal(normalized.code, 'major');
  assert.equal(normalized.label, 'Major Outage');
  assert.equal(normalized.className, 'status--outage');
});
test('snarkOutage uses provider-specific quip when available', () => {
  const originalRandom = Math.random;
  Math.random = () => 0;
  const line = app.snarkOutage('AWS', 'Outage', 'Everything is on fire');
  assert.ok(/us-east-1 is a lifestyle choice/.test(line));
  Math.random = originalRandom;
});

test('snarkOutage falls back to summary when quips missing', () => {
  const originalRandom = Math.random;
  Math.random = () => 0;
  const summary = 'Service recovered after a wobble';
  const line = app.snarkOutage('Unknown Provider', 'Operational', summary);
  assert.ok(line.includes(summary));
  Math.random = originalRandom;
});
