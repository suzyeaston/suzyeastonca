const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

test('ground rendering no longer uses scaled street polygon curb hack', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('curb.scale.set(1.008, 1.008, 1.008)'), false);
  assert.equal(src.includes('new THREE.Line(new THREE.BufferGeometry().setFromPoints(borderPoints), visualState.curbMaterial)'), true);
});

test('ground rendering builds curb border lines from closed polygon points helper', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('function buildClosedBorderPoints(points, y)'), true);
  assert.equal(src.includes('borderPoints.push(new THREE.Vector3(first.x, y, first.z));'), true);
  assert.equal(src.includes('const borderPoints = buildClosedBorderPoints(zone.polygon, 0.16);'), true);
});

test('main road path no longer generates repeated divider plane markers', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('new THREE.PlaneGeometry(0.7, 2.2)'), false);
  assert.match(src, /laneMaterial = new THREE\.MeshStandardMaterial\([^)]*opacity: 0\.02/s);
});

test('default clear and afternoon play keeps rain as particles without divider blades', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /if \(intensity < 0\.72\) \{\s*return;/s);
  assert.match(src, /new THREE\.PlaneGeometry\(0\.025, 0\.7 \+ \(intensity \* 0\.85\)\)/);
  assert.doesNotMatch(src, /new THREE\.PlaneGeometry\(0\.05, 2\.8 \+ \(intensity \* 2\.4\)\)/);
});

test('production landmark rendering suppresses generic cylinder and halo markers for hero and intersection landmarks', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /const heroLandmarkIds = new Set/);
  assert.match(src, /const suppressedKinds = new Set\(\['clock', 'street_pivot', 'plaza_edge', 'district_gate', 'view_axis'\]\)/);
  assert.match(src, /if \(state\.debugEnabled \|\| !suppressGenericMarker\) \{/);
  assert.match(src, /landmark\.id === 'steam-clock'/);
  assert.match(src, /landmark\.id === 'water-cambie-intersection'/);
});
