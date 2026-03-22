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
      assert.ok(data.streetscape.surfaceBands.length > 0, 'working fallback should include deterministic surface bands');
      const surfaceTones = new Set(data.streetscape.surfaceBands.map((band) => band.tone));
      ['curb_grime', 'intersection_pavers'].forEach((tone) => {
        assert.equal(surfaceTones.has(tone), true, 'working fallback surface bands should include ' + tone);
      });

      const propKinds = new Set((data.props || []).map((prop) => prop.kind));
      ['trash_bag', 'newspaper_box', 'utility_box', 'bench', 'planter'].forEach((kind) => {
        assert.equal(propKinds.has(kind), true, 'working fallback props should include ' + kind);
      });

      assert.ok(Array.isArray(data.landmarks));
      assert.equal(data.landmarks.length >= 5, true, 'working fallback should expose route beats as landmarks');
      assert.equal(Array.isArray(data.hero_landmarks), true, 'working fallback should expose hero landmarks');
      assert.equal(data.hero_landmarks.some((hero) => hero.id === 'steam-clock-hero'), true, 'working fallback should expose a Steam Clock hero anchor');
      ['waterfront-station-threshold', 'water-street-gateway', 'steam-clock', 'maple-tree-square-edge', 'cambie-rise-continuation'].forEach((id) => {
        assert.equal(data.landmarks.some((landmark) => landmark.id === id), true, 'working fallback should expose landmark ' + id);
      });
      ['waterfront-station-threshold', 'water-cordova-seam', 'water-street-mid-block', 'steam-clock', 'cambie-rise-continuation'].forEach((id) => {
        assert.equal(data.nodes.some((node) => node.id === id), true, 'working fallback should expose node ' + id);
      });
      assert.ok(Array.isArray(data.npcs));
      assert.equal(data.npcs.length >= 12, true, 'working fallback should expose a denser deterministic NPC set');
      const touristCluster = data.npcs.filter((npc) => String(npc.id).includes('tourist-clock'));
      assert.equal(touristCluster.length >= 6, true, 'working fallback should expose a tourist cluster near the Steam Clock');
      assert.equal(touristCluster.some((npc) => npc.pose === 'taking_photo'), true, 'tourist cluster should include a photo pose');
      assert.equal(touristCluster.some((npc) => npc.pose === 'being_photographed'), true, 'tourist cluster should include a photographed pose');
      assert.equal(touristCluster.some((npc) => npc.heldProp === 'camera'), true, 'tourist cluster should include a held camera prop');
      assert.equal(touristCluster.filter((npc) => Array.isArray(npc.patrol) && npc.patrol.length > 1).length >= 2, true, 'tourist cluster should include multiple walkers');
      assert.equal(data.npcs.some((npc) => npc.role === 'busker' && npc.heldProp === 'guitar'), true, 'busker should carry a guitar');
      assert.equal(data.npcs.some((npc) => npc.role === 'skateboarder' && npc.heldProp === 'skateboard'), true, 'working fallback should include a skateboarder role');
      assert.equal(data.npcs.some((npc) => npc.role === 'cyclist' && npc.heldProp === 'bike'), true, 'working fallback should include a cyclist role');
      const roleCounts = data.npcs.reduce((acc, npc) => {
        acc[npc.role] = (acc[npc.role] || 0) + 1;
        return acc;
      }, {});
      assert.deepEqual(roleCounts, { guide: 1, busker: 1, pedestrian: 2, skateboarder: 1, cyclist: 1, tourist: 5, photographer: 1 }, 'working fallback role mix should remain deterministic');
      assert.equal(data.routeId, 'gastown_water_street_working_corridor');
    }
  }

  if (fs.existsSync(tmpPath)) {
    fs.unlinkSync(tmpPath);
  }
});

test('committed world json files include expanded npc arrays', () => {
  const root = path.resolve(__dirname, '..');
  ['assets/world/gastown-water-street.json', 'assets/world/gastown-water-street-starter.json'].forEach((relPath) => {
    const data = JSON.parse(fs.readFileSync(path.join(root, relPath), 'utf8'));
    assert.ok(Array.isArray(data.npcs), relPath + ' should include npcs');
    assert.ok(data.npcs.length > 4, relPath + ' should have more than the original 4 npcs');
    assert.ok(Array.isArray(data.hero_landmarks), relPath + ' should include hero_landmarks');
    assert.ok(data.hero_landmarks.some((hero) => hero.id === 'steam-clock-hero'), relPath + ' should include the Steam Clock hero anchor');
    assert.ok(data.nodes.some((node) => node.id === 'steam-clock'), relPath + ' should include the Steam Clock node');
    assert.ok(data.landmarks.some((landmark) => landmark.id === 'steam-clock'), relPath + ' should include the Steam Clock landmark');
    assert.equal(data.routeId, 'gastown_water_street_working_corridor', relPath + ' should treat fallback as the working corridor');
    assert.equal(data.meta.fallbackMode, 'working-gastown-corridor', relPath + ' should identify the working fallback mode');
    assert.ok(data.landmarks.some((landmark) => landmark.id === 'water-street-gateway'), relPath + ' should include the Water Street gateway landmark');
    assert.ok(data.nodes.some((node) => node.id === 'water-cordova-seam'), relPath + ' should include the Water/Cordova seam node');
    assert.ok(data.npcs.some((npc) => npc.role === 'skateboarder'), relPath + ' should include a skateboarder');
    assert.ok(data.npcs.some((npc) => npc.role === 'cyclist'), relPath + ' should include a cyclist');
    assert.ok(Array.isArray(data.streetscape.surfaceBands) && data.streetscape.surfaceBands.some((band) => band.tone === 'road_base_dark'), relPath + ' should include darker road surface bands');
  });
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

  fs.rmSync(refreshDir, { recursive: true, force: true });
  fs.rmSync(tmpPath, { force: true });
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
