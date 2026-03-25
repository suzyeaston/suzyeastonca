const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const { buildWorld } = require('../scripts/generate-gastown-world');

function assertFinitePoints(points) {
  points.forEach((point) => {
    assert.equal(Number.isFinite(point.x), true);
    assert.equal(Number.isFinite(point.z), true);
  });
}

function pointInPolygon(point, polygon) {
  let inside = false;
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const xi = polygon[i].x;
    const zi = polygon[i].z;
    const xj = polygon[j].x;
    const zj = polygon[j].z;
    const intersects = ((zi > point.z) !== (zj > point.z))
      && (point.x < ((xj - xi) * (point.z - zi)) / ((zj - zi) || 1e-9) + xi);
    if (intersects) inside = !inside;
  }
  return inside;
}

test('generator keeps world polygons valid with existing or generated output', () => {
  const root = path.resolve(__dirname, '..');
  const tmpPath = path.join(root, 'assets', 'world', 'gastown-water-street.test-output.json');
  const result = buildWorld({ root, outputPath: tmpPath });

  const sourcePath = result.generated ? tmpPath : path.join(root, 'assets', 'world', 'gastown-water-street.json');
  const data = JSON.parse(fs.readFileSync(sourcePath, 'utf8'));

  assert.ok(Array.isArray(data.route.walkBounds));
  assert.ok(data.route.walkBounds.length >= 3);
  assert.ok(Array.isArray(data.zones.street[0].polygon));
  assert.ok(data.zones.street[0].polygon.length >= 3);
  assert.ok(Array.isArray(data.zones.sidewalk[0].polygon));
  assert.ok(data.zones.sidewalk[0].polygon.length >= 3);

  assertFinitePoints(data.route.walkBounds);
  data.zones.street.forEach((zone) => assertFinitePoints(zone.polygon));
  data.zones.sidewalk.forEach((zone) => assertFinitePoints(zone.polygon));

  if (result.generated) {
    const sampleBuilding = (data.buildings || [])[0];
    assert.ok(sampleBuilding, 'generated world should contain at least one building');
    ['x', 'z', 'width', 'depth', 'yaw'].forEach((field) => {
      assert.equal(Number.isFinite(sampleBuilding[field]), true, 'generated building.' + field + ' should be finite');
    });
    assert.ok(Array.isArray(sampleBuilding.footprint_local));
    assert.ok(sampleBuilding.footprint_local.length >= 3);
    assertFinitePoints(sampleBuilding.footprint_local);

    if (data.meta && data.meta.fallbackMode === 'working-gastown-corridor') {
      assert.ok(Array.isArray(data.streetscape.surfaceBands));
      assert.equal(data.streetscape.surfaceBands.length, 48, 'working fallback should keep a tightly constrained but hero-weighted surface-band count');
      const surfaceTones = new Set(data.streetscape.surfaceBands.map((band) => band.tone));
      ['curb_grime', 'intersection_pavers', 'wheel_track'].forEach((tone) => {
        assert.equal(surfaceTones.has(tone), true, 'working fallback surface bands should include ' + tone);
      });
      const crossBands = data.streetscape.surfaceBands.filter((band) => band.tone === 'intersection_pavers' || band.tone === 'cobble_break');
      assert.equal(crossBands.length, 4, 'plaza brick/cobble bands should stay localized near the clock intersection');

      const propKinds = new Set((data.props || []).map((prop) => prop.kind));
      ['trash_bag', 'newspaper_box', 'utility_box', 'bench', 'planter'].forEach((kind) => {
        assert.equal(propKinds.has(kind), true, 'working fallback props should include ' + kind);
      });

      assert.ok(Array.isArray(data.landmarks));
      assert.equal(data.landmarks.length >= 6, true, 'working fallback should expose route beats as landmarks');
      assert.equal(Array.isArray(data.hero_landmarks), true, 'working fallback should expose hero landmarks');
      assert.equal(data.hero_landmarks.some((hero) => hero.id === 'steam-clock-hero'), true, 'working fallback should expose a Steam Clock hero anchor');
      ['waterfront-station-threshold', 'water-cordova-seam', 'water-street-mid-block', 'steam-clock', 'maple-tree-square-edge', 'cambie-rise-continuation'].forEach((id) => {
        assert.equal(data.landmarks.some((landmark) => landmark.id === id), true, 'working fallback should expose landmark ' + id);
        assert.equal(data.nodes.some((node) => node.id === id), true, 'working fallback should expose node ' + id);
      });

      assert.equal(data.zones.street.length >= 3, true, 'working fallback should stage multiple street polygons around the Steam Clock');
      assert.equal(data.zones.sidewalk.length >= 4, true, 'working fallback should stage multiple sidewalk/plaza polygons');
      assert.equal(Array.isArray(data.navigator.focusCorridor), true, 'working fallback should expose minimap focus corridors');
      assert.equal(data.navigator.focusCorridor.length >= 4, true, 'working fallback should expose at least west leg, plaza loop, east leg, and Cambie crossing minimap corridors');
      assert.equal(data.navigator.focusCorridor.some((segment) => segment.id === 'steam-clock-plaza-loop'), true, 'working fallback should expose a plaza loop in navigator data');
      assert.equal(data.navigator.focusCorridor.some((segment) => segment.id === 'cambie-crossing'), true, 'working fallback should expose a Cambie crossing in navigator data');
      data.navigator.focusCorridor.forEach((segment) => {
        assert.equal(Array.isArray(segment.points), true, segment.id + ' should expose minimap points');
        assert.equal(segment.points.length >= 2, true, segment.id + ' should expose at least two minimap points');
        assertFinitePoints(segment.points);
      });

      const steamClockNode = data.nodes.find((node) => node.id === 'steam-clock');
      const approachNode = data.route.centerline.find((node) => node.id === 'steam-clock-approach');
      const eastNode = data.nodes.find((node) => node.id === 'maple-tree-square-edge');
      const cambieIntersectionNode = data.nodes.find((node) => node.id === 'water-cambie-intersection');
      assert.ok(steamClockNode && approachNode && eastNode, 'clock approach/east nodes should exist');
      assert.ok(cambieIntersectionNode, 'water/cambie intersection node should exist');
      assert.equal(Math.hypot(steamClockNode.x - approachNode.x, steamClockNode.z - approachNode.z) > 1.5, true, 'clock node should no longer sit on the same ribbon centerline point as the approach');
      assert.equal(Math.abs(eastNode.x - steamClockNode.x) > 8 || Math.abs(eastNode.z - steamClockNode.z) > 8, true, 'clock should lead into a distinct east/plaza continuation');
      assert.equal(Math.hypot(cambieIntersectionNode.x - steamClockNode.x, cambieIntersectionNode.z - steamClockNode.z) > 4, true, 'steam clock should sit off the roadway intersection on the plaza corner');

      const plazaZone = data.zones.sidewalk.find((zone) => zone.id === 'steam-clock-plaza-sidewalk');
      assert.ok(plazaZone, 'steam clock plaza sidewalk should exist');
      assert.equal(pointInPolygon(steamClockNode, plazaZone.polygon), true, 'steam clock should sit inside the dedicated corner plaza sidewalk zone');
      assert.equal(data.zones.street.some((zone) => pointInPolygon(steamClockNode, zone.polygon)), false, 'steam clock should not sit inside any travel lane polygon');

      assert.ok(Array.isArray(data.npcs));
      assert.equal(data.npcs.length >= 7, true, 'working fallback should expose a concise deterministic NPC set');
      const touristCluster = data.npcs.filter((npc) => String(npc.id).includes('tourist-clock'));
      assert.equal(touristCluster.length >= 4, true, 'working fallback should expose a focused tourist cluster near the Steam Clock');
      assert.equal(touristCluster.some((npc) => npc.pose === 'taking_photo'), true, 'tourist cluster should include a photo pose');
      assert.equal(touristCluster.some((npc) => npc.pose === 'being_photographed'), true, 'tourist cluster should include a photographed pose');
      assert.equal(touristCluster.some((npc) => npc.heldProp === 'camera'), true, 'tourist cluster should include a held camera prop');
      assert.equal(touristCluster.filter((npc) => Array.isArray(npc.patrol) && npc.patrol.length > 1).length >= 2, true, 'tourist cluster should include multiple walkers');
      assert.equal(data.npcs.some((npc) => npc.role === 'busker' && npc.heldProp === 'guitar'), true, 'busker should carry a guitar');
      const roleCounts = data.npcs.reduce((acc, npc) => {
        acc[npc.role] = (acc[npc.role] || 0) + 1;
        return acc;
      }, {});
      assert.deepEqual(roleCounts, { guide: 1, busker: 1, pedestrian: 2, tourist: 3, photographer: 1 }, 'working fallback role mix should remain deterministic and lighter-weight');
      assert.equal(data.routeId, 'gastown_water_street_working_corridor');
      assert.equal(data.meta.buildClassification, 'approximate-fallback');
      assert.match(data.meta.provenanceSummary, /Approximate fallback corridor retained/);
    }
  }

  if (fs.existsSync(tmpPath)) {
    fs.unlinkSync(tmpPath);
  }
});

test('committed world json files include the expanded intersection-based fallback layout', () => {
  const root = path.resolve(__dirname, '..');
  ['assets/world/gastown-water-street.json'].forEach((relPath) => {
    const data = JSON.parse(fs.readFileSync(path.join(root, relPath), 'utf8'));
    assert.ok(Array.isArray(data.npcs), relPath + ' should include npcs');
    assert.ok(data.npcs.length > 4, relPath + ' should have more than the original 4 npcs');
    assert.ok(Array.isArray(data.hero_landmarks), relPath + ' should include hero_landmarks');
    assert.ok(data.hero_landmarks.some((hero) => hero.id === 'steam-clock-hero'), relPath + ' should include the Steam Clock hero anchor');
    assert.ok(data.nodes.some((node) => node.id === 'steam-clock'), relPath + ' should include the Steam Clock node');
    assert.ok(data.landmarks.some((landmark) => landmark.id === 'steam-clock'), relPath + ' should include the Steam Clock landmark');
    assert.equal(data.routeId, 'gastown_water_street_working_corridor', relPath + ' should treat fallback as the working corridor');
    assert.equal(data.meta.fallbackMode, 'working-gastown-corridor', relPath + ' should identify the working fallback mode');
    assert.equal(data.meta.buildClassification, 'approximate-fallback', relPath + ' should keep fallback build classification explicit');
    assert.match(data.meta.provenanceSummary, /Approximate fallback corridor retained/, relPath + ' should keep fallback provenance explicit');
    assert.ok(data.nodes.some((node) => node.id === 'water-cordova-seam'), relPath + ' should include the Water/Cordova seam node');
    assert.equal(data.nodes.find((node) => node.id === 'steam-clock').label, 'Cambie / Steam Clock corner', relPath + ' should label the Steam Clock node as a corner');
    assert.ok(data.nodes.some((node) => node.id === 'maple-tree-square-edge'), relPath + ' should include the Maple Tree Square edge node');
    assert.ok(data.nodes.some((node) => node.id === 'water-cambie-intersection'), relPath + ' should include the Water/Cambie intersection node');
    assert.ok(Array.isArray(data.streetscape.surfaceBands) && data.streetscape.surfaceBands.length === 48, relPath + ' should constrain surface band clutter while allowing the hero block extra wear detail');
    assert.ok(Array.isArray(data.zones.street) && data.zones.street.length >= 3, relPath + ' should stage multiple road polygons');
    assert.ok(Array.isArray(data.zones.sidewalk) && data.zones.sidewalk.length >= 5, relPath + ' should stage multiple sidewalks/plaza polygons');
    assert.ok(data.zones.street.some((zone) => zone.id === 'cambie-street-crossing'), relPath + ' should include a Cambie crossing roadway polygon');
    assert.ok(data.navigator.focusCorridor.some((segment) => segment.id === 'cambie-crossing'), relPath + ' should include the Cambie crossing on the minimap');
  });
});

test('fallback Steam Clock plaza stays on the signed plaza side of the Water/Cambie approach frame', () => {
  const root = path.resolve(__dirname, '..');
  const data = JSON.parse(fs.readFileSync(path.join(root, 'assets', 'world', 'gastown-water-street.json'), 'utf8'));

  const approachNode = data.route.centerline.find((node) => node.id === 'steam-clock-approach');
  const intersectionNode = data.nodes.find((node) => node.id === 'water-cambie-intersection');
  const clockNode = data.nodes.find((node) => node.id === 'steam-clock');
  const plazaZone = data.zones.sidewalk.find((zone) => zone.id === 'steam-clock-plaza-sidewalk');
  assert.ok(approachNode && intersectionNode && clockNode && plazaZone, 'approach/intersection/clock/plaza data should exist');

  const tangentLength = Math.hypot(intersectionNode.x - approachNode.x, intersectionNode.z - approachNode.z);
  assert.ok(tangentLength > 0.001, 'approach frame should have a usable tangent');
  const tangent = {
    x: (intersectionNode.x - approachNode.x) / tangentLength,
    z: (intersectionNode.z - approachNode.z) / tangentLength,
  };
  const leftNormal = { x: -tangent.z, z: tangent.x };
  const clockOffset = {
    x: clockNode.x - intersectionNode.x,
    z: clockNode.z - intersectionNode.z,
  };

  assert.ok(((clockOffset.x * leftNormal.x) + (clockOffset.z * leftNormal.z)) < 0, 'Steam Clock should stay on the plaza side of the Water/Cambie approach frame');
  assert.equal(pointInPolygon(clockNode, plazaZone.polygon), true, 'Steam Clock should remain inside the dedicated plaza sidewalk polygon');
  assert.equal(data.zones.street.some((zone) => pointInPolygon(clockNode, zone.polygon)), false, 'Steam Clock should remain outside every roadway polygon');
});

test('generator consumes refreshed reference inputs when present', () => {
  const root = path.resolve(__dirname, '..');
  const refreshDir = path.join(root, 'data', 'reference', 'refresh');
  fs.mkdirSync(refreshDir, { recursive: true });

  const featureCollection = {
    type: 'FeatureCollection',
    features: [{ type: 'Feature', properties: { id: 'seed' }, geometry: { type: 'Point', coordinates: [-123.11, 49.28] } }],
  };
  ['gastown-route-reference.geojson', 'gastown-street-context.geojson', 'gastown-landmark-reference.geojson', 'gastown-building-cues.geojson', 'gastown-poi-reference.geojson'].forEach((name) => {
    fs.writeFileSync(path.join(refreshDir, name), JSON.stringify(featureCollection), 'utf8');
  });

  const tmpPath = path.join(root, 'assets', 'world', 'gastown-water-street.reference-test.json');
  const result = buildWorld({ root, outputPath: tmpPath });
  const data = JSON.parse(fs.readFileSync(tmpPath, 'utf8'));

  assert.equal(result.generated, true);
  assert.deepEqual(data.meta.referenceInputsUsed, [
    'gastown-route-reference.geojson',
    'gastown-street-context.geojson',
    'gastown-landmark-reference.geojson',
    'gastown-building-cues.geojson',
    'gastown-poi-reference.geojson',
  ]);
  assert.deepEqual(data.meta.openDataInputs, {
    publicStreets: false,
    streetIntersections: false,
    rightOfWayWidths: false,
    streetLightingPoles: false,
    buildingFootprints2015: false,
    orthophotoImagery2015: false,
  });

  fs.rmSync(refreshDir, { recursive: true, force: true });
  fs.rmSync(tmpPath, { force: true });
});


test('generator records direct open-data inputs when present', () => {
  const root = path.resolve(__dirname, '..');
  const covDir = path.join(root, 'data', 'cov');
  fs.mkdirSync(covDir, { recursive: true });

  const files = {
    'public-streets.geojson': { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { id: 'street', hblock: '100 W CORDOVA ST', streetuse: 'Arterial' }, geometry: { type: 'LineString', coordinates: [[-123.1117, 49.2858], [-123.1095, 49.2848]] } }] },
    'street-intersections.geojson': { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { id: 'intersection' }, geometry: { type: 'Point', coordinates: [-123.1091, 49.2846] } }] },
    'right-of-way-widths.geojson': { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { id: 'row', right_of_way_width: 18.4 }, geometry: { type: 'Point', coordinates: [-123.1091, 49.2846] } }] },
    'street-lighting-poles.geojson': { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { id: 'lamp' }, geometry: { type: 'Point', coordinates: [-123.1094, 49.2847] } }] },
    'building-footprints-2015.geojson': { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { id: 'building' }, geometry: { type: 'Polygon', coordinates: [[[-123.1108, 49.2852], [-123.1105, 49.2852], [-123.1105, 49.2850], [-123.1108, 49.2850], [-123.1108, 49.2852]]] } }] },
    'orthophoto-imagery-2015.geojson': { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { id: 'ortho-tile' }, geometry: { type: 'Polygon', coordinates: [[[-123.1109, 49.2853], [-123.1103, 49.2853], [-123.1103, 49.2849], [-123.1109, 49.2849], [-123.1109, 49.2853]]] } }] },
  };

  try {
    Object.entries(files).forEach(([name, data]) => {
      fs.writeFileSync(path.join(covDir, name), JSON.stringify(data), 'utf8');
    });

    const tmpPath = path.join(root, 'assets', 'world', 'gastown-water-street.opendata-test.json');
    const result = buildWorld({ root, outputPath: tmpPath });
    const sourcePath = result.generated ? result.outputPath : path.join(root, 'assets', 'world', 'gastown-water-street.json');
    const data = JSON.parse(fs.readFileSync(sourcePath, 'utf8'));

    ['public-streets.geojson', 'street-intersections.geojson', 'right-of-way-widths.geojson', 'street-lighting-poles.geojson', 'building-footprints-2015.geojson', 'orthophoto-imagery-2015.geojson'].forEach((name) => {
      assert.equal(data.meta.referenceInputsUsed.includes(name), true, name + ' should be tracked in referenceInputsUsed when present');
    });
    assert.deepEqual(data.meta.openDataInputs, {
      publicStreets: true,
      streetIntersections: true,
      rightOfWayWidths: true,
      streetLightingPoles: true,
      buildingFootprints2015: true,
      orthophotoImagery2015: true,
    });

    fs.rmSync(tmpPath, { force: true });
  } finally {
    Object.keys(files).forEach((name) => fs.rmSync(path.join(covDir, name), { force: true }));
  }
});

test('refresh helper documents expanded expected output files', () => {
  const root = path.resolve(__dirname, '..');
  const src = fs.readFileSync(path.join(root, 'scripts', 'refresh-gastown-reference-data.ps1'), 'utf8');

  [
    'gastown-route-reference.geojson',
    'gastown-street-context.geojson',
    'gastown-landmark-reference.geojson',
    'gastown-building-cues.geojson',
    'gastown-poi-reference.geojson',
    'gastown-overpass-buildings.json',
  ].forEach((name) => {
    assert.match(src, new RegExp(name.replace('.', '\\.')));
  });
  ['water-street-context-centerline', 'cambie-street-centerline', 'water-cambie-intersection', 'frontage_massing', 'heritage_lamp', 'street-intersections', 'right-of-way-widths'].forEach((id) => {
    assert.match(src, new RegExp(id));
  });
});

test('fallback generator remains deterministic across repeated runs', () => {
  const root = path.resolve(__dirname, '..');
  const outputA = path.join(root, 'assets', 'world', 'gastown-water-street.det-a.json');
  const outputB = path.join(root, 'assets', 'world', 'gastown-water-street.det-b.json');
  const first = buildWorld({ root, outputPath: outputA });
  const second = buildWorld({ root, outputPath: outputB });
  const worldA = JSON.parse(fs.readFileSync(first.outputPath, 'utf8'));
  const worldB = JSON.parse(fs.readFileSync(second.outputPath, 'utf8'));
  worldA.meta.lastBuild = 'stable';
  worldB.meta.lastBuild = 'stable';
  assert.deepEqual(worldA, worldB);
  fs.rmSync(outputA, { force: true });
  fs.rmSync(outputB, { force: true });
});


test('fallback world splits Water Street roadway around the Cambie intersection and sanitizes zone polygons', () => {
  const root = path.resolve(__dirname, '..');
  const data = JSON.parse(fs.readFileSync(path.join(root, 'assets', 'world', 'gastown-water-street.json'), 'utf8'));

  const streetIds = new Set(data.zones.street.map((zone) => zone.id));
  assert.equal(streetIds.has('water-street-west-roadway'), true);
  assert.equal(streetIds.has('water-street-east-roadway'), true);
  assert.equal(streetIds.has('water-cambie-intersection-roadway'), true);

  data.zones.street.concat(data.zones.sidewalk).forEach((zone) => {
    const polygon = zone.polygon;
    assert.ok(Array.isArray(polygon) && polygon.length >= 4, zone.id + ' should keep a closed polygon');
    const first = polygon[0];
    const last = polygon[polygon.length - 1];
    assert.equal(first.x, last.x, zone.id + ' should be explicitly closed on x');
    assert.equal(first.z, last.z, zone.id + ' should be explicitly closed on z');
  });
});
