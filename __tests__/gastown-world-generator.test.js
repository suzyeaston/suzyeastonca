const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const { buildWorld } = require('../scripts/generate-gastown-world');

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

  function assertFinitePoints(points) {
    points.forEach((point) => {
      assert.equal(Number.isFinite(point.x), true);
      assert.equal(Number.isFinite(point.z), true);
    });
  }

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
    }
  }

  if (fs.existsSync(tmpPath)) {
    fs.unlinkSync(tmpPath);
  }
});
