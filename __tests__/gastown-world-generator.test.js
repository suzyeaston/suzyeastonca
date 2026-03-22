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

    if (data.meta && data.meta.fallbackMode === 'starter-corridor') {
      assert.ok(Array.isArray(data.streetscape.surfaceBands));
      assert.ok(data.streetscape.surfaceBands.length > 0, 'starter fallback should include deterministic surface bands');
      const surfaceTones = new Set(data.streetscape.surfaceBands.map((band) => band.tone));
      ['curb_grime', 'intersection_pavers'].forEach((tone) => {
        assert.equal(surfaceTones.has(tone), true, 'starter fallback surface bands should include ' + tone);
      });

      const propKinds = new Set((data.props || []).map((prop) => prop.kind));
      ['trash_bag', 'newspaper_box', 'utility_box', 'bench', 'planter'].forEach((kind) => {
        assert.equal(propKinds.has(kind), true, 'starter fallback props should include ' + kind);
      });

      assert.ok(Array.isArray(data.landmarks));
      assert.equal(data.landmarks.length >= 3, true, 'starter fallback should expose route beats as landmarks');
      assert.equal(Array.isArray(data.hero_landmarks), true, 'starter fallback should expose hero landmarks');
      assert.equal(data.hero_landmarks.some((hero) => hero.id === 'steam-clock-hero'), true, 'starter fallback should expose a Steam Clock hero anchor');
      assert.equal(data.landmarks.some((landmark) => landmark.id === 'steam-clock'), true, 'starter fallback should expose a Steam Clock landmark');
      assert.equal(data.nodes.some((node) => node.id === 'steam-clock'), true, 'starter fallback should expose a Steam Clock node');
      assert.ok(Array.isArray(data.npcs));
      assert.equal(data.npcs.length >= 10, true, 'starter fallback should expose a denser deterministic NPC set');
      const touristCluster = data.npcs.filter((npc) => String(npc.id).includes('tourist-clock'));
      assert.equal(touristCluster.length >= 6, true, 'starter fallback should expose a tourist cluster near the Steam Clock');
      assert.equal(touristCluster.some((npc) => npc.pose === 'taking_photo'), true, 'tourist cluster should include a photo pose');
      assert.equal(touristCluster.some((npc) => npc.pose === 'being_photographed'), true, 'tourist cluster should include a photographed pose');
      assert.equal(touristCluster.some((npc) => npc.heldProp === 'camera'), true, 'tourist cluster should include a held camera prop');
      assert.equal(touristCluster.filter((npc) => Array.isArray(npc.patrol) && npc.patrol.length > 1).length >= 2, true, 'tourist cluster should include multiple walkers');
      assert.equal(data.npcs.some((npc) => npc.role === 'busker' && npc.heldProp === 'guitar'), true, 'busker should carry a guitar');
      const roleCounts = data.npcs.reduce((acc, npc) => {
        acc[npc.role] = (acc[npc.role] || 0) + 1;
        return acc;
      }, {});
      assert.deepEqual(roleCounts, { guide: 1, busker: 1, pedestrian: 2, tourist: 5, photographer: 1 }, 'starter fallback role mix should remain deterministic');
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
  });
});
