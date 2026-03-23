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

test('production landmark rendering suppresses generic cylinder and halo markers for hero, intersection, and Water Street route-cue landmarks', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /const heroLandmarkIds = new Set/);
  assert.match(src, /const suppressedKinds = new Set\(\['clock', 'street_pivot', 'plaza_edge', 'district_gate', 'view_axis'\]\)/);
  assert.match(src, /if \(state\.debugEnabled \|\| !suppressGenericMarker\) \{/);
  assert.match(src, /landmark\.id === 'steam-clock'/);
  assert.match(src, /landmark\.id === 'water-cambie-intersection'/);
  assert.match(src, /landmark\.id === 'water-street-mid-block'/);
});

test('production landmark rendering also suppresses reflection discs for suppressed production-only route and hero/intersection markers', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /if \(state\.debugEnabled \|\| !suppressGenericMarker\) \{\s*const reflectionMaterial = new THREE\.MeshStandardMaterial/s);
  assert.equal(src.includes('visualState.landmarkVisuals.push({ reflectionMaterial });\n      }'), true);
});

test('heading-up minimap derives heading from rendered world direction instead of mirrored yaw math', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function getHeadingVector\(\) \{\s*const direction = new THREE\.Vector3\(\);\s*player\.getWorldDirection\(direction\);/s);
  assert.match(src, /const planarLength = Math\.hypot\(direction\.x, direction\.z\) \|\| 1;/);
  assert.doesNotMatch(src, /baseForward\.clone\(\)\.applyAxisAngle/);
  assert.match(src, /const headingRotation = minimapState\.mode === 'heading-up' \? \(-Math\.atan2\(-heading\.z, heading\.x\) - \(Math\.PI \/ 2\)\) : 0;/);
});


test('minimap player marker renders as a tiny person with a forward cue instead of a wedge', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /ctx\.arc\(playerPoint\.x, playerPoint\.y - 4\.2, 2\.2, 0, Math\.PI \* 2\)/);
  assert.match(src, /ctx\.moveTo\(playerPoint\.x, playerPoint\.y - 1\.4\);\s*ctx\.lineTo\(playerPoint\.x, playerPoint\.y \+ 4\.3\);/s);
  assert.match(src, /ctx\.moveTo\(playerPoint\.x \+ \(headingX \* 9\.4\), playerPoint\.y \+ \(headingY \* 9\.4\)\);/);
  assert.doesNotMatch(src, /ctx\.arc\(playerPoint\.x, playerPoint\.y, dirLength, headingAngle - 0\.5, headingAngle \+ 0\.5\)/);
});

test('ground meshes sanitize malformed polygon rings before building shape geometry', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function sanitizePolygon\(points\) \{/);
  assert.match(src, /const shape = toShape\(points\);\s*if \(!shape\) \{\s*return null;\s*\}/s);
  assert.match(src, /if \(cleaned.length >= 3 && polygonSignedArea\(cleaned\) < 0\) \{\s*cleaned\.reverse\(\);\s*\}/s);
});
