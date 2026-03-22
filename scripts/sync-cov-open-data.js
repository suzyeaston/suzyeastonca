#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const DEFAULT_ROOT = path.resolve(__dirname, '..');
const DEFAULT_PACKAGE_SHOW_BASE = process.env.COV_PACKAGE_SHOW_BASE || 'https://opendata.vancouver.ca/api/3/action/package_show?id=';
const DEFAULT_FETCH_TIMEOUT_MS = Number(process.env.COV_FETCH_TIMEOUT_MS || 30000);
const DEFAULT_FILTER_RADIUS_METERS = Number(process.env.COV_FILTER_RADIUS_METERS || 400);
const DEFAULT_ROUTE_PADDING_METERS = Number(process.env.COV_ROUTE_PADDING_METERS || 120);

const DATASETS = [
  { id: 'public-streets', output: 'public-streets.geojson', required: true },
  { id: 'street-intersections', output: 'street-intersections.geojson', required: false },
  { id: 'right-of-way-widths', output: 'right-of-way-widths.geojson', required: false },
  { id: 'building-footprints-2015', output: 'building-footprints.geojson', required: true },
  { id: 'street-lighting-poles', output: 'street-lighting-poles.geojson', required: false },
  { id: 'public-trees', output: 'public-trees.geojson', required: false },
  { id: 'heritage-sites', output: 'heritage-sites.geojson', required: false },
];

const EARTH_RADIUS_METERS = 6371008.8;

function toRadians(value) {
  return (value * Math.PI) / 180;
}

function projectLonLat(lon, lat, origin) {
  const lat0 = toRadians(origin.lat);
  const lon0 = toRadians(origin.lon);
  const latRad = toRadians(lat);
  const lonRad = toRadians(lon);
  return {
    x: (lonRad - lon0) * Math.cos(lat0) * EARTH_RADIUS_METERS,
    z: (latRad - lat0) * EARTH_RADIUS_METERS,
  };
}

function distance(a, b) {
  return Math.hypot(a.x - b.x, a.z - b.z);
}

function dot(a, b) {
  return (a.x * b.x) + (a.z * b.z);
}

function pointToSegmentDistance(point, a, b) {
  const ab = { x: b.x - a.x, z: b.z - a.z };
  const lenSq = dot(ab, ab);
  if (lenSq < 1e-9) return distance(point, a);
  const t = Math.max(0, Math.min(1, dot({ x: point.x - a.x, z: point.z - a.z }, ab) / lenSq));
  return distance(point, { x: a.x + (ab.x * t), z: a.z + (ab.z * t) });
}

function pointToPolylineDistance(point, line) {
  if (!line.length) return Infinity;
  if (line.length === 1) return distance(point, line[0]);
  let best = Infinity;
  for (let i = 0; i < line.length - 1; i += 1) {
    best = Math.min(best, pointToSegmentDistance(point, line[i], line[i + 1]));
  }
  return best;
}

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function ensureDir(dirPath) {
  fs.mkdirSync(dirPath, { recursive: true });
}

function stableString(value) {
  return JSON.stringify(value, (_key, inner) => {
    if (inner && typeof inner === 'object' && !Array.isArray(inner)) {
      return Object.keys(inner).sort().reduce((acc, key) => {
        acc[key] = inner[key];
        return acc;
      }, {});
    }
    return inner;
  });
}

function featureId(feature, index) {
  const props = (feature && feature.properties) || {};
  const candidate = feature.id || props.id || props.ID || props.objectid || props.OBJECTID || props.site_id || props.tree_id || props.block_id || props.hblock || props.civic_address || props.name;
  return String(candidate == null ? `feature-${index}` : candidate);
}

function extractCoordinates(geometry, sink = []) {
  if (!geometry) return sink;
  if (geometry.type === 'Point') {
    sink.push(geometry.coordinates);
    return sink;
  }
  if (geometry.type === 'LineString' || geometry.type === 'MultiPoint') {
    geometry.coordinates.forEach((coord) => sink.push(coord));
    return sink;
  }
  if (geometry.type === 'Polygon' || geometry.type === 'MultiLineString') {
    geometry.coordinates.forEach((part) => extractCoordinates({ type: 'LineString', coordinates: part }, sink));
    return sink;
  }
  if (geometry.type === 'MultiPolygon') {
    geometry.coordinates.forEach((polygon) => extractCoordinates({ type: 'Polygon', coordinates: polygon }, sink));
    return sink;
  }
  if (geometry.type === 'GeometryCollection') {
    (geometry.geometries || []).forEach((inner) => extractCoordinates(inner, sink));
  }
  return sink;
}

function buildFilterContext(routeAnchorsPath, options = {}) {
  const anchors = readJson(routeAnchorsPath);
  const origin = anchors.origin;
  const dest = anchors.dest;
  const midLon = (origin.lon + dest.lon) / 2;
  const midLat = (origin.lat + dest.lat) / 2;
  const midpoint = projectLonLat(midLon, midLat, origin);
  const routeLine = [
    projectLonLat(origin.lon, origin.lat, origin),
    midpoint,
    projectLonLat(dest.lon, dest.lat, origin),
  ];

  return {
    anchors,
    origin,
    midpoint: { lon: midLon, lat: midLat },
    radiusMeters: Number(options.radiusMeters || DEFAULT_FILTER_RADIUS_METERS),
    routePaddingMeters: Number(options.routePaddingMeters || DEFAULT_ROUTE_PADDING_METERS),
    routeLine,
    query: {
      midpoint: { lon: Number(midLon.toFixed(7)), lat: Number(midLat.toFixed(7)) },
      radiusMeters: Number(options.radiusMeters || DEFAULT_FILTER_RADIUS_METERS),
      routePaddingMeters: Number(options.routePaddingMeters || DEFAULT_ROUTE_PADDING_METERS),
    },
  };
}

function shouldKeepFeature(feature, filterContext) {
  const coordinates = extractCoordinates(feature && feature.geometry);
  if (!coordinates.length) return false;
  return coordinates.some((coord) => {
    const point = projectLonLat(coord[0], coord[1], filterContext.origin);
    const midpointDistance = distance(point, projectLonLat(filterContext.midpoint.lon, filterContext.midpoint.lat, filterContext.origin));
    const routeDistance = pointToPolylineDistance(point, filterContext.routeLine);
    return midpointDistance <= filterContext.radiusMeters || routeDistance <= filterContext.routePaddingMeters;
  });
}

function sortFeatures(features) {
  return features.slice().sort((a, b) => {
    const aKey = `${featureId(a, 0)}:${stableString(a.geometry)}:${stableString(a.properties || {})}`;
    const bKey = `${featureId(b, 0)}:${stableString(b.geometry)}:${stableString(b.properties || {})}`;
    return aKey.localeCompare(bKey);
  });
}

async function fetchJson(url, options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), Number(options.fetchTimeoutMs || DEFAULT_FETCH_TIMEOUT_MS));
  try {
    const response = await fetch(url, {
      headers: { accept: 'application/json, application/geo+json;q=0.9' },
      signal: controller.signal,
    });
    if (!response.ok) {
      throw new Error(`Request failed (${response.status}) for ${url}`);
    }
    return await response.json();
  } finally {
    clearTimeout(timeout);
  }
}

function normalizeFeatureCollection(data, datasetId) {
  if (!data || data.type !== 'FeatureCollection' || !Array.isArray(data.features)) {
    throw new Error(`Dataset ${datasetId} did not return a GeoJSON FeatureCollection.`);
  }
  return {
    type: 'FeatureCollection',
    name: data.name || datasetId,
    crs: data.crs || null,
    features: data.features,
  };
}

function pickGeoJsonResource(pkg) {
  const resources = Array.isArray(pkg.resources) ? pkg.resources : [];
  const scored = resources.map((resource) => {
    const format = String(resource.format || '').toLowerCase();
    const name = String(resource.name || '').toLowerCase();
    const url = String(resource.url || '');
    const looksGeoJson = format.includes('geojson') || name.includes('geojson') || /geojson/i.test(url);
    const looksApi = /api/i.test(url);
    return {
      resource,
      score: (looksGeoJson ? 20 : 0) + (looksApi ? 5 : 0) + (url.startsWith('https://') ? 1 : 0),
    };
  }).sort((a, b) => b.score - a.score);

  if (!scored.length || scored[0].score < 20) {
    throw new Error(`No GeoJSON resource found for dataset ${pkg.name || pkg.id || 'unknown'}.`);
  }

  return scored[0].resource;
}

async function fetchDataset(dataset, filterContext, options) {
  const packageUrl = `${options.packageShowBase}${encodeURIComponent(dataset.id)}`;
  const packageData = await fetchJson(packageUrl, options);
  const pkg = packageData && packageData.result ? packageData.result : null;
  if (!pkg) {
    throw new Error(`Package lookup failed for dataset ${dataset.id}.`);
  }

  const resource = pickGeoJsonResource(pkg);
  const resourceUrl = new URL(String(resource.url), packageUrl).toString();
  const rawGeoJson = normalizeFeatureCollection(await fetchJson(resourceUrl, options), dataset.id);
  const filteredFeatures = sortFeatures(
    rawGeoJson.features
      .filter((feature) => shouldKeepFeature(feature, filterContext))
      .map((feature, index) => ({
        ...feature,
        id: featureId(feature, index),
      }))
  );

  const output = {
    type: 'FeatureCollection',
    name: rawGeoJson.name || dataset.id,
    features: filteredFeatures,
  };

  const outputPath = path.join(options.outputDir, dataset.output);
  fs.writeFileSync(outputPath, `${JSON.stringify(output, null, 2)}\n`, 'utf8');

  return {
    dataset: dataset.id,
    required: dataset.required,
    output: path.relative(options.root, outputPath),
    packageUrl,
    resourceUrl,
    query: filterContext.query,
    totalFeatures: rawGeoJson.features.length,
    keptFeatures: filteredFeatures.length,
  };
}

async function run(options = {}) {
  const root = options.root || DEFAULT_ROOT;
  const routeAnchorsPath = path.join(root, 'data', 'reference', 'route-anchors.json');
  const outputDir = path.join(root, 'data', 'cov');
  const manifestPath = path.join(outputDir, '_manifest.json');
  const runtimeOptions = {
    packageShowBase: options.packageShowBase || DEFAULT_PACKAGE_SHOW_BASE,
    fetchTimeoutMs: options.fetchTimeoutMs || DEFAULT_FETCH_TIMEOUT_MS,
    radiusMeters: options.radiusMeters || DEFAULT_FILTER_RADIUS_METERS,
    routePaddingMeters: options.routePaddingMeters || DEFAULT_ROUTE_PADDING_METERS,
    outputDir,
    root,
  };

  ensureDir(outputDir);
  const filterContext = buildFilterContext(routeAnchorsPath, runtimeOptions);
  const manifest = {
    timestamp: new Date().toISOString(),
    datasets: [],
    filter: filterContext.query,
  };

  const summary = [];
  for (const dataset of DATASETS) {
    const result = await fetchDataset(dataset, filterContext, runtimeOptions);
    manifest.datasets.push(result);
    summary.push(`${dataset.id}: ${result.keptFeatures}/${result.totalFeatures}`);
  }

  fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
  process.stdout.write(`Synced CoV open data (${summary.join(', ')})\n`);
}

if (require.main === module) {
  run().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exit(1);
  });
}

module.exports = { run, buildFilterContext, shouldKeepFeature, sortFeatures, featureId, normalizeFeatureCollection, pickGeoJsonResource };
