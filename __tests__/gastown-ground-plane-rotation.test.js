'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'js/gastown-sim.js'), 'utf8');
const world = JSON.parse(fs.readFileSync(path.join(root, 'assets/world/gastown-water-street.json'), 'utf8'));

// A local PlaneGeometry's normal points along +Z. After rotating it
// -90 degrees around X it points straight up; turning around world Y
// should NOT tilt it off the horizontal pavement.
function groundNormal(xTilt, yaw, order) {
  const normal = { x: 0, y: 0, z: 1 };
  const rotX = v => ({ x: v.x, y: v.y * Math.cos(xTilt) - v.z * Math.sin(xTilt),
    z: v.y * Math.sin(xTilt) + v.z * Math.cos(xTilt) });
  const rotY = v => ({ x: v.x * Math.cos(yaw) + v.z * Math.sin(yaw),
    y: v.y, z: -v.x * Math.sin(yaw) + v.z * Math.cos(yaw) });
  return order === 'YXZ' ? rotY(rotX(normal)) : rotX(rotY(normal));
}

test('actual street-surface transform never raises road decal planes into the skyline', () => {
  // Test the live transform expression rather than duplicating a separate
  // implementation that could pass even if the app regressed.
  const match = source.match(/paver\.rotation\.set\([^\n]+\);/);
  assert.ok(match, 'the simulator must apply an explicit paver rotation');
  const apply = new Function('paver', 'band', match[0]);
  const realHeadings = [];
  for (let i = 1; i < world.route.centerline.length; i++) {
    const a = world.route.centerline[i - 1];
    const b = world.route.centerline[i];
    realHeadings.push(Math.atan2(b.x - a.x, b.z - a.z));
  }
  for (const yaw of [...realHeadings, -Math.PI, -Math.PI / 2, 0, Math.PI / 2, Math.PI]) {
    let rotation = null;
    const paver = { rotation: { set: (...args) => { rotation = args; } } };
    apply(paver, { yaw });
    assert.ok(rotation, 'the runtime rotation must be invoked');
    assert.equal(rotation[3], 'YXZ', 'yaw must occur in world Y after X flattening');
    assert.equal(rotation[0], -Math.PI / 2);
    assert.equal(rotation[1], yaw);
    const n = groundNormal(rotation[0], rotation[1], rotation[3]);
    assert.ok(Math.abs(n.x) < 1e-8 && Math.abs(n.z) < 1e-8,
      'road plane must not acquire a horizontal normal at route yaw ' + yaw);
    assert.ok(n.y > 0.999999, 'ground normal must point upward');
  }
});

test('the reported civic world actually uses the fallback dark road bands', () => {
  assert.equal(world.streetscape.surfaceBands.length, 0);
  assert.ok(world.route.centerline.length >= 10);
  assert.match(source, /buildFallbackSurfaceBands\(world\)/);
  assert.match(source, /tone: 'road_base_dark'/);
  assert.match(source, /tone: steamZone \? 'intersection_pavers' : 'wheel_track'/);
  assert.doesNotMatch(source, /paver\.rotation\.y\s*=\s*band\.yaw/);
});

test('regression demonstrates why the previous XYZ sequence stood panels upright', () => {
  const yaw = Math.PI / 3;
  assert.ok(groundNormal(-Math.PI / 2, yaw, 'XYZ').y < 0.51,
    'the previous world transform had a sideways-facing plane normal');
  assert.ok(groundNormal(-Math.PI / 2, yaw, 'YXZ').y > 0.999,
    'the corrected world transform has a sky-facing plane normal');
});

test('simulator syntax is valid after the geometric correction', () => {
  assert.doesNotThrow(() => new Function(source));
});
