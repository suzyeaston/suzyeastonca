const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

const { run, buildWhereClause } = require('../scripts/sync-cov-open-data');

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
  const requests = [];
  const server = http.createServer((req, res) => {
    requests.push(req.url);
    const target = routes[req.url.split('?')[0]] || routes[req.url];
    if (!target) {
      res.statusCode = 404;
      res.end('missing');
      return;
    }
    res.setHeader('content-type', 'application/json');
    res.end(JSON.stringify(target(req)));
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const { port } = server.address();
  try {
    await callback(`http://127.0.0.1:${port}`, requests);
  } finally {
    await new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())));
  }
}

test('buildWhereClause targets Opendatasoft spatial fields', () => {
  const where = buildWhereClause({
    midpoint: { lon: -123.1101, lat: 49.2851 },
    radiusMeters: 400,
    bboxPolygon: [[-123.12, 49.28], [-123.10, 49.28], [-123.10, 49.29], [-123.12, 49.29], [-123.12, 49.28]],
  }, {
    fields: [
      { name: 'geo_shape', type: 'geo_shape' },
      { name: 'geo_point_2d', type: 'geo_point_2d' },
    ],
  });

  assert.match(where, /intersects\(geo_shape,/);
  assert.match(where, /within_distance\(geo_point_2d,/);
  assert.match(where, /POLYGON/);
});

test('sync-cov-open-data writes cropped feature collections and manifest via Explore API v2.1', async () => {
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
    'building-footprints-2015': makeFeatureCollection([
      polygonFeature('building-near', nearbyPolygon, { civic_address: '1 Water St' }),
      polygonFeature('building-far', farPolygon, { civic_address: '999 Far St' }),
    ]),
    'street-lighting-poles': makeFeatureCollection([
      pointFeature('lamp-near', [-123.1109, 49.2851]),
      pointFeature('lamp-far', [-123.15, 49.30]),
    ]),
    'public-trees': makeFeatureCollection([
      pointFeature('tree-near', [-123.1107, 49.28505]),
      pointFeature('tree-far', [-123.15, 49.30]),
    ]),
    'heritage-sites': makeFeatureCollection([
      polygonFeature('heritage-near', nearbyPolygon, { site_id: 'H1' }),
      polygonFeature('heritage-far', farPolygon, { site_id: 'HFAR' }),
    ]),
    'business-licences': { results: [{ licence: 'nearby-business', geom: { lon: -123.1092, lat: 49.2847 } }] },
  };

  const routes = {};
  for (const datasetId of Object.keys(datasets)) {
    routes[`/api/explore/v2.1/catalog/datasets/${datasetId}`] = () => ({
      id: datasetId,
      fields: [
        { name: 'geo_shape', type: 'geo_shape' },
        { name: 'geo_point_2d', type: 'geo_point_2d' },
      ],
    });
    routes[`/api/explore/v2.1/catalog/datasets/${datasetId}/exports/${datasetId === 'business-licences' ? 'json' : 'geojson'}`] = () => datasets[datasetId];
  }

  await withServer(routes, async (baseUrl, requests) => {
    const exploreBase = `${baseUrl}/api/explore/v2.1/catalog/datasets`;
    await run({ root: tmpRoot, exploreBase, fetchTimeoutMs: 2000, radiusMeters: 400, routePaddingMeters: 120, includeBusinessLicences: true });
    assert.ok(requests.some((url) => url.includes('/api/explore/v2.1/catalog/datasets/public-streets')));
    assert.ok(requests.some((url) => url.includes('/exports/geojson?')));
    assert.ok(requests.some((url) => url.includes('/exports/json?')));
    assert.ok(requests.some((url) => url.includes('where=')));
  });

  const covDir = path.join(tmpRoot, 'data', 'cov');
  const expected = [
    'public-streets.geojson',
    'building-footprints.geojson',
    'street-lighting-poles.geojson',
    'public-trees.geojson',
    'heritage-sites.geojson',
    'business-licences.json',
  ];

  expected.forEach((name) => {
    const filePath = path.join(covDir, name);
    assert.equal(fs.existsSync(filePath), true, `${name} should exist`);
  });

  const streets = JSON.parse(fs.readFileSync(path.join(covDir, 'public-streets.geojson'), 'utf8'));
  assert.deepEqual(streets.features.map((feature) => feature.id), ['street-near']);

  const buildings = JSON.parse(fs.readFileSync(path.join(covDir, 'building-footprints.geojson'), 'utf8'));
  assert.deepEqual(buildings.features.map((feature) => feature.id), ['building-near']);

  const businesses = JSON.parse(fs.readFileSync(path.join(covDir, 'business-licences.json'), 'utf8'));
  assert.equal(Array.isArray(businesses.results), true);
  assert.equal(businesses.results.length, 1);

  const manifest = JSON.parse(fs.readFileSync(path.join(covDir, '_manifest.json'), 'utf8'));
  assert.equal(manifest.api.version, 'explore-v2.1');
  assert.equal(Array.isArray(manifest.datasets), true);
  assert.equal(manifest.datasets.length, 6);
  manifest.datasets.forEach((entry) => {
    assert.equal(typeof entry.dataset, 'string');
    assert.equal(typeof entry.output, 'string');
    assert.ok(entry.output.startsWith('data/cov/'));
  });
});

test('sync-cov-open-data falls back to next configured dataset alias', async () => {
  const tmpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'cov-sync-fallback-'));
  fs.mkdirSync(path.join(tmpRoot, 'data', 'reference'), { recursive: true });
  fs.writeFileSync(path.join(tmpRoot, 'data', 'reference', 'route-anchors.json'), JSON.stringify({
    origin: { lat: 49.2858333333, lon: -123.1116666667 },
    dest: { lat: 49.2843841111, lon: -123.10889 },
  }, null, 2));

  const routes = {
    '/api/explore/v2.1/catalog/datasets/public-streets': () => ({ id: 'public-streets', fields: [{ name: 'geo_shape', type: 'geo_shape' }] }),
    '/api/explore/v2.1/catalog/datasets/public-streets/exports/geojson': () => makeFeatureCollection([lineFeature('street-near', [[-123.1117, 49.2858], [-123.1095, 49.2848]], { hblock: '100 W CORDOVA ST' })]),
    '/api/explore/v2.1/catalog/datasets/building-footprints-2009': () => ({ id: 'building-footprints-2009', fields: [{ name: 'geo_shape', type: 'geo_shape' }] }),
    '/api/explore/v2.1/catalog/datasets/building-footprints-2009/exports/geojson': () => makeFeatureCollection([polygonFeature('building-near', [[-123.1108, 49.2852], [-123.1105, 49.2852], [-123.1105, 49.2850], [-123.1108, 49.2850], [-123.1108, 49.2852]], { civic_address: '1 Water St' })]),
  };

  await withServer(routes, async (baseUrl) => {
    const exploreBase = `${baseUrl}/api/explore/v2.1/catalog/datasets`;
    await run({ root: tmpRoot, exploreBase, fetchTimeoutMs: 2000, radiusMeters: 400, routePaddingMeters: 120 });
  });

  const manifest = JSON.parse(fs.readFileSync(path.join(tmpRoot, 'data', 'cov', '_manifest.json'), 'utf8'));
  const footprints = manifest.datasets.find((entry) => entry.dataset === 'building-footprints-2015');
  assert.equal(footprints.datasetId, 'building-footprints-2009');
});
