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

  if (fs.existsSync(tmpPath)) {
    fs.unlinkSync(tmpPath);
  }
});
