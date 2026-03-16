const test = require('node:test');
const assert = require('node:assert/strict');

const { normalizeBuildingForRender } = require('../js/gastown-building-normalizer.js');

test('normalizer makes absolute-footprint buildings renderable', () => {
  const normalized = normalizeBuildingForRender({
    id: 'legacy-1',
    footprint: [
      { x: 10, z: 20 },
      { x: 18, z: 20 },
      { x: 18, z: 28 },
      { x: 10, z: 28 },
      { x: 10, z: 20 },
    ],
    height: 14,
  });

  assert.equal(Number.isFinite(normalized.x), true);
  assert.equal(Number.isFinite(normalized.z), true);
  assert.equal(Array.isArray(normalized.footprint_local), true);
  assert.ok(normalized.footprint_local.length >= 3);
  assert.equal(Number.isFinite(normalized.width), true);
  assert.equal(Number.isFinite(normalized.depth), true);
  assert.equal(Number.isFinite(normalized.yaw), true);
});

test('normalizer never returns NaN positions or empty footprint', () => {
  const normalized = normalizeBuildingForRender({
    id: 'malformed',
    footprint: [{ x: NaN, z: 1 }, { x: Infinity, z: 2 }],
    x: NaN,
    z: undefined,
    width: 0,
    depth: null,
  });

  assert.equal(Number.isFinite(normalized.x), true);
  assert.equal(Number.isFinite(normalized.z), true);
  assert.equal(Number.isFinite(normalized.width), true);
  assert.equal(Number.isFinite(normalized.depth), true);
  assert.equal(Number.isFinite(normalized.yaw), true);
  assert.ok(Array.isArray(normalized.footprint_local));
  assert.ok(normalized.footprint_local.length >= 3);
});
