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


test('normalizer converts legacy absolute footprint into yaw-neutral local footprint', () => {
  const abs = [
    { x: 0, z: 0 },
    { x: 10, z: 10 },
    { x: 6, z: 14 },
    { x: -4, z: 4 },
    { x: 0, z: 0 },
  ];

  const normalized = normalizeBuildingForRender({ id: 'legacy-rotated', footprint: abs });
  const localYawFromEdges = (() => {
    let longest = { len: 0, dx: 0, dz: 0 };
    for (let i = 0; i < normalized.footprint_local.length; i += 1) {
      const a = normalized.footprint_local[i];
      const b = normalized.footprint_local[(i + 1) % normalized.footprint_local.length];
      const dx = b.x - a.x;
      const dz = b.z - a.z;
      const len = Math.hypot(dx, dz);
      if (len > longest.len) longest = { len, dx, dz };
    }
    return Math.atan2(longest.dz, longest.dx);
  })();

  assert.ok(Math.abs(localYawFromEdges) < 1e-6, 'local footprint should be yaw neutral');
  assert.equal(normalized.yaw > 0.1, true, 'world yaw should be preserved');
});

test('normalizer preserves trusted local geometry and yaw', () => {
  const normalized = normalizeBuildingForRender({
    id: 'trusted-local',
    x: 12,
    z: -8,
    yaw: 0.3,
    footprint_local: [
      { x: -5, z: -2 },
      { x: 5, z: -2 },
      { x: 5, z: 2 },
      { x: -5, z: 2 },
    ],
  });

  assert.equal(normalized.x, 12);
  assert.equal(normalized.z, -8);
  assert.equal(normalized.yaw, 0.3);
  assert.deepEqual(normalized.footprint_local, [
    { x: -5, z: -2 },
    { x: 5, z: -2 },
    { x: 5, z: 2 },
    { x: -5, z: 2 },
  ]);
});
