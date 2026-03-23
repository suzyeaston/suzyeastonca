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

test('minimap heading derives from gameplay forward yaw instead of mirrored player rig world direction', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function getHeadingVector\(\) \{\s*const forward = new THREE\.Vector3\(0, 0, -1\);\s*forward\.applyAxisAngle\(new THREE\.Vector3\(0, 1, 0\), state\.yaw\);/s);
  assert.match(src, /const planarLength = Math\.hypot\(forward\.x, forward\.z\) \|\| 1;/);
  assert.doesNotMatch(src, /player\.getWorldDirection\(direction\)/);
  assert.match(src, /function getMinimapHeadingRotation\(heading\) \{\s*return minimapState\.mode === 'heading-up' \? \(-Math\.atan2\(-heading\.z, heading\.x\) - \(Math\.PI \/ 2\)\) : 0;\s*\}/s);
  assert.match(src, /const headingRotation = getMinimapHeadingRotation\(heading\);/);
  assert.match(src, /const headingX = minimapState\.mode === 'heading-up' \? 0 : heading\.x;/);
  assert.match(src, /const headingY = minimapState\.mode === 'heading-up' \? -1 : -heading\.z;/);
});



test('minimap uses the same world-to-map handedness for Steam Clock corridor landmarks in north-up and heading-up modes', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const worldPath = path.join(__dirname, '..', 'assets', 'world', 'gastown-water-street.json');
  const src = fs.readFileSync(simPath, 'utf8');
  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));

  assert.match(src, /function projectWorldToMinimap\(point, metrics, padding, view, options = \{\}\) \{/);
  assert.match(src, /drawPolygon\(zone\.polygon, metrics, pad, '#3f4d60', 'rgba\(136, 161, 188, 0\.4\)', 1, view, projectionOptions\);/);
  assert.match(src, /const mini = projectWorldToMinimap\(node, metrics, pad, view, projectionOptions\);/);
  assert.doesNotMatch(src, /ctx\.translate\(playerPoint\.x, playerPoint\.y\);\s*ctx\.rotate\(headingRotation\);/s);

  const midBlock = world.nodes.find((node) => node.id === 'water-street-mid-block');
  const approach = world.route.centerline.find((point) => point.id === 'steam-clock-approach');
  const steamClock = world.landmarks.find((landmark) => landmark.id === 'steam-clock');

  assert.ok(midBlock && approach && steamClock);

  const metrics = {
    minX: -20,
    maxX: 240,
    minZ: -210,
    maxZ: 20,
    width: 260,
    height: 230,
  };
  const view = metrics;
  const minimap = { width: 240, height: 240 };
  const padding = 16;

  function toMinimapPoint(point) {
    const usableW = minimap.width - (padding * 2);
    const usableH = minimap.height - (padding * 2);
    return {
      x: padding + (((point.x - view.minX) / view.width) * usableW),
      y: minimap.height - (padding + (((point.z - view.minZ) / view.height) * usableH)),
    };
  }

  function rotateMinimapVector(x, y, angle) {
    const sin = Math.sin(angle);
    const cos = Math.cos(angle);
    return {
      x: (x * cos) - (y * sin),
      y: (x * sin) + (y * cos),
    };
  }

  function projectWorldToMinimap(point, options = {}) {
    const basePoint = toMinimapPoint(point);
    const rotation = options.rotation || 0;
    const anchor = options.anchor;
    if (!rotation || !anchor) {
      return basePoint;
    }
    const offsetX = basePoint.x - anchor.x;
    const offsetY = basePoint.y - anchor.y;
    const rotated = rotateMinimapVector(offsetX, offsetY, rotation);
    return {
      x: anchor.x + rotated.x,
      y: anchor.y + rotated.y,
    };
  }

  const northMid = projectWorldToMinimap(midBlock);
  const northApproach = projectWorldToMinimap(approach);
  const northSteam = projectWorldToMinimap(steamClock);
  const northCorridor = {
    x: northApproach.x - northMid.x,
    y: northApproach.y - northMid.y,
  };
  const northOffset = {
    x: northSteam.x - northApproach.x,
    y: northSteam.y - northApproach.y,
  };
  const northScreenCross = (northCorridor.x * northOffset.y) - (northCorridor.y * northOffset.x);
  assert.ok(northScreenCross > 0, `expected Steam Clock to stay on the right side of the corridor in north-up, got ${northScreenCross}`);

  const heading = {
    x: approach.x - midBlock.x,
    z: approach.z - midBlock.z,
  };
  const planarLength = Math.hypot(heading.x, heading.z) || 1;
  heading.x /= planarLength;
  heading.z /= planarLength;
  const headingRotation = -Math.atan2(-heading.z, heading.x) - (Math.PI / 2);
  const headingApproach = projectWorldToMinimap(approach);
  const headingSteam = projectWorldToMinimap(steamClock, { rotation: headingRotation, anchor: headingApproach });

  assert.ok(headingSteam.x > headingApproach.x, `expected Steam Clock to stay on the right side of the corridor in heading-up, got ${headingSteam.x} <= ${headingApproach.x}`);
  assert.ok(headingSteam.y > headingApproach.y - 20 && headingSteam.y < headingApproach.y + 20, 'expected heading-up projection to keep the Steam Clock laterally beside the corridor near Water/Cambie');
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
