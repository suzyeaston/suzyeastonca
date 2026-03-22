const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

const { run } = require('../scripts/sync-cov-open-data');

function makeFeatureCollection(features) {
  return { type: 'FeatureCollection', features };
}

function lineFeature(id, coords, properties = {}) {
  return { type: 'Feature', id, properties, geometry: { type: 'LineString', coordinates: coords } };
}

function polygonFeature(id, ring, properties = {}) {
  return { type: 'Feature', id, properties, geometry: { type: 'Polygon', coordinates: [ring] } };
}

function pointFeature(id, coord, properties = {}) {
  return { type: 'Feature', id, properties, geometry: { type: 'Point', coordinates: coord } };
}

async function withServer(routes, callback) {
  const server = http.createServer((req, res) => {
    const target = routes[req.url];
    if (!target) {
      res.statusCode = 404;
      res.end('missing');
      return;
    }
    res.setHeader('content-type', 'application/json');
    res.end(JSON.stringify(target));
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const { port } = server.address();
  try {
    await callback(`http://127.0.0.1:${port}`);
  } finally {
    await new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())));
  }
}

test('sync-cov-open-data writes cropped feature collections and manifest', async () => {
  const tmpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'cov-sync-'));
  fs.mkdirSync(path.join(tmpRoot, 'data', 'reference'), { recursive: true });
  fs.writeFileSync(path.join(tmpRoot, 'data', 'reference', 'route-anchors.json'), JSON.stringify({
    origin: { lat: 49.2858333333, lon: -123.1116666667 },
    dest: { lat: 49.2843841111, lon: -123.10889 },
  }, null, 2));

  const nearbyLine = [[-123.1117, 49.2858], [-123.1095, 49.2848]];
  const farLine = [[-123.15, 49.30], [-123.149, 49.301]];
  const nearbyPolygon = [[-123.1108, 49.2852], [-123.1105, 49.2852], [-123.1105, 49.2850], [-123.1108, 49.2850], [-123.1108, 49.2852]];
  const farPolygon = [[-123.15, 49.30], [-123.1495, 49.30], [-123.1495, 49.2995], [-123.15, 49.2995], [-123.15, 49.30]];

  const datasets = {
    'public-streets': makeFeatureCollection([
      lineFeature('street-near', nearbyLine, { hblock: '100 W CORDOVA ST' }),
      lineFeature('street-far', farLine, { hblock: '999 FAR AWAY ST' }),
    ]),
    'street-intersections': makeFeatureCollection([
      pointFeature('intersection-near', [-123.1091, 49.2846], { street_1: 'Water', street_2: 'Cambie' }),
      pointFeature('intersection-far', [-123.15, 49.30], { street_1: 'Far', street_2: 'Away' }),
    ]),
    'right-of-way-widths': makeFeatureCollection([
      pointFeature('row-near', [-123.1092, 49.2847], { street_name: 'Water Street', right_of_way_width: 18.4, carriageway_width: 10.2, sidewalk_width: 4.1 }),
      pointFeature('row-far', [-123.15, 49.30], { street_name: 'Far Away', right_of_way_width: 99 }),
    ]),
    'building-footprints-2015': makeFeatureCollection([
      polygonFeature('building-near', nearbyPolygon, { civic_address: '1 Water St' }),
      polygonFeature('building-far', farPolygon, { civic_address: '999 Far St' }),
    ]),
    'street-lighting-poles': makeFeatureCollection([
      pointFeature('lamp-near', [-123.1109, 49.2851]),
      pointFeature('lamp-far', [-123.15, 49.30]),
    ]),
    'orthophoto-imagery-2015': makeFeatureCollection([
      polygonFeature('ortho-near', nearbyPolygon, { tile: '2015-near' }),
      polygonFeature('ortho-far', farPolygon, { tile: '2015-far' }),
    ]),
    'public-trees': makeFeatureCollection([
      pointFeature('tree-near', [-123.1107, 49.28505]),
      pointFeature('tree-far', [-123.15, 49.30]),
    ]),
    'heritage-sites': makeFeatureCollection([
      polygonFeature('heritage-near', nearbyPolygon, { site_id: 'H1' }),
      polygonFeature('heritage-far', farPolygon, { site_id: 'HFAR' }),
    ]),
  };

  const routes = {};
  for (const datasetId of Object.keys(datasets)) {
    routes[`/api/3/action/package_show?id=${encodeURIComponent(datasetId)}`] = {
      success: true,
      result: {
        id: datasetId,
        name: datasetId,
        resources: [
          { name: `${datasetId} GeoJSON`, format: 'GeoJSON', url: `/datasets/${datasetId}.geojson` },
        ],
      },
    };
    routes[`/datasets/${datasetId}.geojson`] = datasets[datasetId];
  }

  await withServer(routes, async (baseUrl) => {
    const packageShowBase = `${baseUrl}/api/3/action/package_show?id=`;
    await run({ root: tmpRoot, packageShowBase, fetchTimeoutMs: 2000, radiusMeters: 400, routePaddingMeters: 120 });
    await run({ root: tmpRoot, packageShowBase, fetchTimeoutMs: 2000, radiusMeters: 400, routePaddingMeters: 120 });
  });

  const covDir = path.join(tmpRoot, 'data', 'cov');
  const expected = [
    'public-streets.geojson',
    'street-intersections.geojson',
    'right-of-way-widths.geojson',
    'building-footprints.geojson',
    'street-lighting-poles.geojson',
    'orthophoto-imagery-2015.geojson',
    'public-trees.geojson',
    'heritage-sites.geojson',
  ];

  expected.forEach((name) => {
    const filePath = path.join(covDir, name);
    assert.equal(fs.existsSync(filePath), true, `${name} should exist`);
    const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    assert.equal(data.type, 'FeatureCollection');
    assert.equal(Array.isArray(data.features), true);
    assert.equal(data.features.length >= 1, true);
  });

  const streets = JSON.parse(fs.readFileSync(path.join(covDir, 'public-streets.geojson'), 'utf8'));
  assert.deepEqual(streets.features.map((feature) => feature.id), ['street-near']);

  const manifest = JSON.parse(fs.readFileSync(path.join(covDir, '_manifest.json'), 'utf8'));
  assert.equal(Array.isArray(manifest.datasets), true);
  assert.equal(manifest.datasets.length, 8);
  manifest.datasets.forEach((entry) => {
    assert.equal(typeof entry.dataset, 'string');
    assert.equal(typeof entry.keptFeatures, 'number');
    assert.equal(typeof entry.totalFeatures, 'number');
    assert.equal(typeof entry.output, 'string');
    assert.ok(entry.output.startsWith('data/cov/'));
  });
});
