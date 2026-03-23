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



test('minimap locks Steam Clock corridor side to a canonical route-relative helper in north-up and heading-up modes', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const worldPath = path.join(__dirname, '..', 'assets', 'world', 'gastown-water-street.json');
  const src = fs.readFileSync(simPath, 'utf8');
  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));

  assert.match(src, /function getCanonicalCorridorSegment\(world\) \{/);
  assert.match(src, /const start = nodes\.find\(\(node\) => node\.id === 'water-street-mid-block'\);/);
  assert.match(src, /const end = nodes\.find\(\(node\) => node\.id === 'water-cambie-intersection'\)/);
  assert.match(src, /rightNormal: \{ x: tangent\.z, z: -tangent\.x \},/);
  assert.match(src, /function classifyCorridorSide\(point, corridor, origin\) \{/);
  assert.match(src, /side: sideValue < 0 \? 'right' : 'left',/);
  assert.match(src, /function getMinimapDebugVectors\(world, metrics, padding, view, projectionOptions\) \{/);
  assert.match(src, /Steam Clock side: /);
  assert.match(src, /ctx\.lineTo\(debugVectors\.rightTip\.x, debugVectors\.rightTip\.y\);/);
  assert.doesNotMatch(src, /ctx\.translate\(playerPoint\.x, playerPoint\.y\);\s*ctx\.rotate\(headingRotation\);/s);

  const findById = (arr, id) => arr.find((entry) => entry.id === id);
  const midBlock = findById(world.nodes, 'water-street-mid-block');
  const cambie = findById(world.nodes, 'water-cambie-intersection');
  const steamClock = findById(world.landmarks, 'steam-clock');

  assert.ok(midBlock && cambie && steamClock);

  function getCanonicalCorridorSegment() {
    const tangent = { x: cambie.x - midBlock.x, z: cambie.z - midBlock.z };
    const length = Math.hypot(tangent.x, tangent.z) || 1;
    tangent.x /= length;
    tangent.z /= length;
    return {
      start: midBlock,
      end: cambie,
      tangent,
      rightNormal: { x: tangent.z, z: -tangent.x },
    };
  }

  function getCorridorSideValue(point, corridor, origin = corridor.start) {
    const relX = point.x - origin.x;
    const relZ = point.z - origin.z;
    return (corridor.tangent.x * relZ) - (corridor.tangent.z * relX);
  }

  function classifyCorridorSide(point, corridor, origin) {
    const sideValue = getCorridorSideValue(point, corridor, origin);
    return {
      sideValue,
      side: Math.abs(sideValue) < 1e-6 ? 'center' : sideValue < 0 ? 'right' : 'left',
    };
  }

  const corridor = getCanonicalCorridorSegment();
  const worldSide = classifyCorridorSide(steamClock, corridor, corridor.end);
  assert.equal(worldSide.side, 'right', `expected fallback world geometry to place Steam Clock on the canonical right side, got ${worldSide.side} (${worldSide.sideValue})`);
  assert.ok(corridor.rightNormal.x < 0 && corridor.rightNormal.z < 0, 'expected canonical route-right normal to point east/south toward the Steam Clock curb side');

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

  const northCambie = projectWorldToMinimap(cambie);
  const northRightTip = projectWorldToMinimap({
    x: cambie.x + (corridor.rightNormal.x * 10),
    z: cambie.z + (corridor.rightNormal.z * 10),
  });
  const northSteam = projectWorldToMinimap(steamClock);
  const northRightVector = {
    x: northRightTip.x - northCambie.x,
    y: northRightTip.y - northCambie.y,
  };
  const northSteamOffset = {
    x: northSteam.x - northCambie.x,
    y: northSteam.y - northCambie.y,
  };
  const northAlignment = (northRightVector.x * northSteamOffset.x) + (northRightVector.y * northSteamOffset.y);
  assert.ok(northAlignment > 0, `expected north-up minimap to keep Steam Clock on the canonical right/east curb side, got alignment ${northAlignment}`);

  const headingRotation = -Math.atan2(-corridor.tangent.z, corridor.tangent.x) - (Math.PI / 2);
  const headingSteam = projectWorldToMinimap(steamClock, { rotation: headingRotation, anchor: northCambie });
  const headingRightTip = projectWorldToMinimap({
    x: cambie.x + (corridor.rightNormal.x * 10),
    z: cambie.z + (corridor.rightNormal.z * 10),
  }, { rotation: headingRotation, anchor: northCambie });
  const headingRightVector = {
    x: headingRightTip.x - northCambie.x,
    y: headingRightTip.y - northCambie.y,
  };
  const headingSteamOffset = {
    x: headingSteam.x - northCambie.x,
    y: headingSteam.y - northCambie.y,
  };
  const headingAlignment = (headingRightVector.x * headingSteamOffset.x) + (headingRightVector.y * headingSteamOffset.y);
  assert.ok(headingAlignment > 0, `expected heading-up minimap to preserve the canonical right side, got alignment ${headingAlignment}`);
  assert.ok(headingSteam.x > northCambie.x, `expected heading-up minimap to keep the Steam Clock on screen-right, got ${headingSteam.x} <= ${northCambie.x}`);
  assert.ok(Math.abs(headingSteam.y - northCambie.y) < 20, 'expected heading-up projection to keep the Steam Clock laterally beside the corridor near Water/Cambie');
});


test('minimap Steam Clock uses a dedicated plaza anchor that moves farther onto the canonical right curb side than the raw node', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const worldPath = path.join(__dirname, '..', 'assets', 'world', 'gastown-water-street.json');
  const src = fs.readFileSync(simPath, 'utf8');
  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));

  assert.match(src, /function getMinimapLandmarkAnchor\(landmark, world\) \{/);
  assert.match(src, /const plazaZone = getZoneById\(world, 'steam-clock-plaza-sidewalk', 'sidewalk'\);/);
  assert.match(src, /const targetSide = Math\.max\(rawSide, sidewalkTarget, heroTarget, rawSide \+ Math\.max\(1\.2, \(rightmostSide - rawSide\) \* 0\.45\)\);/);
  assert.match(src, /const markerPoint = node\.id === 'steam-clock' \? getMinimapLandmarkAnchor\(node, state\.world\) : node;/);

  const midBlock = world.nodes.find((node) => node.id === 'water-street-mid-block');
  const cambie = world.nodes.find((node) => node.id === 'water-cambie-intersection');
  const steamClock = world.nodes.find((node) => node.id === 'steam-clock');
  const steamHero = world.hero_landmarks.find((landmark) => landmark.id === 'steam-clock-hero');
  const plazaZone = world.zones.sidewalk.find((zone) => zone.id === 'steam-clock-plaza-sidewalk');
  assert.ok(midBlock && cambie && steamClock && steamHero && plazaZone);

  const tangent = { x: cambie.x - midBlock.x, z: cambie.z - midBlock.z };
  const tangentLength = Math.hypot(tangent.x, tangent.z) || 1;
  tangent.x /= tangentLength;
  tangent.z /= tangentLength;
  const corridor = {
    end: cambie,
    tangent,
    rightNormal: { x: tangent.z, z: -tangent.x },
  };

  const getCorridorSideValue = (point) => {
    const relX = point.x - corridor.end.x;
    const relZ = point.z - corridor.end.z;
    return (corridor.tangent.x * relZ) - (corridor.tangent.z * relX);
  };
  const getAlongValue = (point) => ((point.x - corridor.end.x) * corridor.tangent.x) + ((point.z - corridor.end.z) * corridor.tangent.z);
  const rawSide = Math.abs(getCorridorSideValue(steamClock));
  const rightmostSide = Math.max(...plazaZone.polygon.map((point) => Math.abs(Math.min(getCorridorSideValue(point), 0))));
  const curbTarget = world.route.streetWidth / 2;
  const sidewalkTarget = curbTarget + (world.route.sidewalkWidth * 0.85);
  const heroTarget = steamHero.plaza.radius + (steamHero.plaza.apronWidth * 0.35);
  const targetSide = Math.max(rawSide, sidewalkTarget, heroTarget, rawSide + Math.max(1.2, (rightmostSide - rawSide) * 0.45));
  const rawAlong = getAlongValue(steamClock);
  const anchor = {
    x: corridor.end.x + (corridor.tangent.x * rawAlong) + (corridor.rightNormal.x * targetSide),
    z: corridor.end.z + (corridor.tangent.z * rawAlong) + (corridor.rightNormal.z * targetSide),
  };

  const anchorSide = Math.abs(getCorridorSideValue(anchor));
  const anchorAlong = getAlongValue(anchor);
  assert.ok(anchorSide > rawSide + 1, `expected dedicated anchor to push the Steam Clock farther off the centerline than the raw node (${anchorSide} <= ${rawSide})`);
  assert.ok(anchorSide < rightmostSide + 0.01, `expected dedicated anchor to stay inside the outer plaza envelope (${anchorSide} > ${rightmostSide})`);
  assert.ok(Math.abs(anchorAlong - rawAlong) < 1e-6, `expected dedicated anchor to preserve along-corridor placement (${anchorAlong} vs ${rawAlong})`);
  assert.ok(anchor.x < steamClock.x && anchor.z < steamClock.z, 'expected dedicated anchor to move the Steam Clock farther onto the east/south plaza side in world space');
});

test('minimap player marker renders as a tiny person with a forward cue instead of a wedge', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /ctx\.arc\(playerPoint\.x, playerPoint\.y - 4\.2, 2\.8, 0, Math\.PI \* 2\)/);
  assert.match(src, /ctx\.moveTo\(playerPoint\.x, playerPoint\.y - 1\.4\);\s*ctx\.lineTo\(playerPoint\.x, playerPoint\.y \+ 4\.3\);/s);
  assert.match(src, /ctx\.moveTo\(playerPoint\.x \+ \(headingX \* 9\.4\), playerPoint\.y \+ \(headingY \* 9\.4\)\);/);
  assert.doesNotMatch(src, /ctx\.arc\(playerPoint\.x, playerPoint\.y, dirLength, headingAngle - 0\.5, headingAngle \+ 0\.5\)/);
});

test('minimap mode control includes a plain-language status label for north-up and heading-up', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /const minimapModeStatusEl = app\.querySelector\('\[data-sim-minimap-mode-status\]'\);/);
  assert.match(src, /minimapModeBtn\.dataset\.minimapMode = minimapState\.mode;/);
  assert.match(src, /Heading-up — the top of the map follows the way you are facing\./);
  assert.match(src, /North-up — the top of the map is geographic north\./);
});

test('ground meshes sanitize malformed polygon rings before building shape geometry', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function sanitizePolygon\(points\) \{/);
  assert.match(src, /const shape = toShape\(points\);\s*if \(!shape\) \{\s*return null;\s*\}/s);
  assert.match(src, /if \(cleaned.length >= 3 && polygonSignedArea\(cleaned\) < 0\) \{\s*cleaned\.reverse\(\);\s*\}/s);
});
