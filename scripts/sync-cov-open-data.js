
const fs = require('fs');
const path = require('path');

const DEFAULT_ROOT = path.resolve(__dirname, '..');
const DEFAULT_EXPLORE_BASE = process.env.COV_EXPLORE_API_BASE || 'https://opendata.vancouver.ca/api/explore/v2.1/catalog/datasets';
const DEFAULT_FETCH_TIMEOUT_MS = Number(process.env.COV_FETCH_TIMEOUT_MS || 30000);
const DEFAULT_FILTER_RADIUS_METERS = Number(process.env.COV_FILTER_RADIUS_METERS || 400);
const DEFAULT_ROUTE_PADDING_METERS = Number(process.env.COV_ROUTE_PADDING_METERS || 120);
const DEFAULT_EXPORT_LIMIT = Number(process.env.COV_EXPORT_LIMIT || 25000);
const DEFAULT_INCLUDE_BUSINESS_LICENCES = String(process.env.COV_INCLUDE_BUSINESS_LICENCES || '').toLowerCase() === 'true';

const DATASETS = [
  { ids: ['public-streets'], output: 'public-streets.geojson', required: true, format: 'geojson' },
  { ids: ['building-footprints-2015', 'building-footprints-2009'], output: 'building-footprints.geojson', required: true, format: 'geojson' },
  { ids: ['street-lighting-poles'], output: 'street-lighting-poles.geojson', required: false, format: 'geojson' },
  { ids: ['public-trees'], output: 'public-trees.geojson', required: false, format: 'geojson' },
  { ids: ['heritage-sites'], output: 'heritage-sites.geojson', required: false, format: 'geojson' },
  { ids: ['business-licences'], output: 'business-licences.json', required: false, format: 'json', envFlag: 'includeBusinessLicences' },
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
  const candidate = feature.id || props.id || props.ID || props.objectid || props.OBJECTID || props.site_id || props.tree_id || props.block_id || props.hblock || props.civic_address || props.name || props.businessName;
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

  const radiusMeters = Number(options.radiusMeters || DEFAULT_FILTER_RADIUS_METERS);
  const routePaddingMeters = Number(options.routePaddingMeters || DEFAULT_ROUTE_PADDING_METERS);
  const lonPadding = ((radiusMeters + routePaddingMeters) / (EARTH_RADIUS_METERS * Math.cos(toRadians(midLat)))) * (180 / Math.PI);
  const latPadding = ((radiusMeters + routePaddingMeters) / EARTH_RADIUS_METERS) * (180 / Math.PI);
  const bbox = {
    minLon: Math.min(origin.lon, dest.lon) - lonPadding,
    minLat: Math.min(origin.lat, dest.lat) - latPadding,
    maxLon: Math.max(origin.lon, dest.lon) + lonPadding,
    maxLat: Math.max(origin.lat, dest.lat) + latPadding,
  };
  const polygon = [
    [bbox.minLon, bbox.minLat],
    [bbox.maxLon, bbox.minLat],
    [bbox.maxLon, bbox.maxLat],
    [bbox.minLon, bbox.maxLat],
    [bbox.minLon, bbox.minLat],
  ];

  return {
    anchors,
    origin,
    midpoint: { lon: midLon, lat: midLat },
    radiusMeters,
    routePaddingMeters,
    routeLine,
    bbox,
    bboxPolygon: polygon,
    query: {
      midpoint: { lon: Number(midLon.toFixed(7)), lat: Number(midLat.toFixed(7)) },
      radiusMeters,
      routePaddingMeters,
      bbox: Object.fromEntries(Object.entries(bbox).map(([key, value]) => [key, Number(value.toFixed(7))])),
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

function normalizeJsonRows(data, datasetId) {
  const rows = Array.isArray(data.results) ? data.results : Array.isArray(data.records) ? data.records : Array.isArray(data) ? data : null;
  if (!rows) {
    throw new Error(`Dataset ${datasetId} did not return a JSON array-like export.`);
  }
  return rows;
}

function buildPolygonWkt(points) {
  const serialized = points.map(([lon, lat]) => `${lon} ${lat}`).join(', ');
  return `POLYGON((${serialized}))`;
}

function buildWithinDistanceClause(field, filterContext) {
  const lon = Number(filterContext.midpoint.lon.toFixed(7));
  const lat = Number(filterContext.midpoint.lat.toFixed(7));
  const radius = Number(filterContext.radiusMeters);
  return `within_distance(${field}, geom'POINT(${lon} ${lat})', ${radius} m)`;
}

function buildBboxClause(field, filterContext) {
  const { minLat, minLon, maxLat, maxLon } = filterContext.query.bbox;
  return `in_bbox(${field}, ${minLat}, ${minLon}, ${maxLat}, ${maxLon})`;
}

function buildWhereClause(filterContext, dataset) {
  const availableFields = new Set(
    (Array.isArray(dataset.fields) ? dataset.fields : [])
      .map((field) => String(field.name || ''))
      .filter((name) => name === 'geo_shape' || name === 'geo_point_2d')
  );

  const orderedFields = ['geo_shape', 'geo_point_2d'].filter((name) => availableFields.has(name));
  if (!orderedFields.length) return null;

  const clauses = orderedFields.flatMap((field) => {
    if (field === 'geo_point_2d') {
      return [
        buildWithinDistanceClause(field, filterContext),
        buildBboxClause(field, filterContext),
      ];
    }
    return [buildBboxClause(field, filterContext)];
  });

  return Array.from(new Set(clauses)).join(' OR ');
}

function sortRows(rows) {
  return rows.slice().sort((a, b) => stableString(a).localeCompare(stableString(b)));
}

async function fetchDatasetMetadata(datasetId, options) {
  const metadataUrl = `${options.exploreBase.replace(/\/$/, '')}/${encodeURIComponent(datasetId)}`;
  const metadata = await fetchJson(metadataUrl, options);
  return { metadataUrl, metadata };
}

function makeExportUrl(metadataUrl, datasetId, format, whereClause, options) {
  const exportUrl = new URL(`${metadataUrl}/exports/${format}`);
  exportUrl.searchParams.set('limit', String(options.exportLimit || DEFAULT_EXPORT_LIMIT));
  if (whereClause) exportUrl.searchParams.set('where', whereClause);
  if (format === 'geojson') exportUrl.searchParams.set('timezone', 'UTC');
  return exportUrl.toString();
}

async function fetchDataset(datasetConfig, filterContext, options) {
  if (datasetConfig.envFlag && !options[datasetConfig.envFlag]) {
    return {
      dataset: datasetConfig.ids[0],
      datasetId: datasetConfig.ids[0],
      required: datasetConfig.required,
      optional: true,
      skipped: true,
      reason: `Skipped because ${datasetConfig.envFlag} is disabled.`,
      output: path.join('data', 'cov', datasetConfig.output),
      query: filterContext.query,
    };
  }

  let lastError = null;
  for (const datasetId of datasetConfig.ids) {
    try {
      const { metadataUrl, metadata } = await fetchDatasetMetadata(datasetId, options);
      const whereClause = buildWhereClause(filterContext, metadata);
      const exportUrl = makeExportUrl(metadataUrl, datasetId, datasetConfig.format, whereClause, options);
      const payload = await fetchJson(exportUrl, options);
      const outputPath = path.join(options.outputDir, datasetConfig.output);

      if (datasetConfig.format === 'geojson') {
        const rawGeoJson = normalizeFeatureCollection(payload, datasetId);
        const filteredFeatures = sortFeatures(
          rawGeoJson.features
            .filter((feature) => shouldKeepFeature(feature, filterContext))
            .map((feature, index) => ({ ...feature, id: featureId(feature, index) }))
        );

        const output = {
          type: 'FeatureCollection',
          name: rawGeoJson.name || datasetId,
          features: filteredFeatures,
        };
        fs.writeFileSync(outputPath, `${JSON.stringify(output, null, 2)}\n`, 'utf8');
        return {
          dataset: datasetConfig.ids[0],
          datasetId,
          required: datasetConfig.required,
          output: path.relative(options.root, outputPath),
          metadataUrl,
          exportUrl,
          query: filterContext.query,
          totalFeatures: rawGeoJson.features.length,
          keptFeatures: filteredFeatures.length,
        };
      }

      const rawRows = normalizeJsonRows(payload, datasetId);
      const output = { dataset: datasetId, results: sortRows(rawRows) };
      fs.writeFileSync(outputPath, `${JSON.stringify(output, null, 2)}\n`, 'utf8');
      return {
        dataset: datasetConfig.ids[0],
        datasetId,
        required: datasetConfig.required,
        output: path.relative(options.root, outputPath),
        metadataUrl,
        exportUrl,
        query: filterContext.query,
        totalFeatures: rawRows.length,
        keptFeatures: rawRows.length,
      };
    } catch (error) {
      lastError = error;
    }
  }

  if (datasetConfig.required) {
    throw lastError || new Error(`Failed to fetch required dataset ${datasetConfig.ids[0]}.`);
  }

  return {
    dataset: datasetConfig.ids[0],
    datasetId: datasetConfig.ids[0],
    required: datasetConfig.required,
    optional: true,
    skipped: true,
    reason: lastError ? lastError.message : 'Dataset unavailable.',
    output: path.join('data', 'cov', datasetConfig.output),
    query: filterContext.query,
  };
}

async function run(options = {}) {
  const root = options.root || DEFAULT_ROOT;
  const routeAnchorsPath = path.join(root, 'data', 'reference', 'route-anchors.json');
  const outputDir = path.join(root, 'data', 'cov');
  const manifestPath = path.join(outputDir, '_manifest.json');
  const runtimeOptions = {
    exploreBase: options.exploreBase || DEFAULT_EXPLORE_BASE,
    fetchTimeoutMs: options.fetchTimeoutMs || DEFAULT_FETCH_TIMEOUT_MS,
    radiusMeters: options.radiusMeters || DEFAULT_FILTER_RADIUS_METERS,
    routePaddingMeters: options.routePaddingMeters || DEFAULT_ROUTE_PADDING_METERS,
    exportLimit: options.exportLimit || DEFAULT_EXPORT_LIMIT,
    includeBusinessLicences: options.includeBusinessLicences != null ? options.includeBusinessLicences : DEFAULT_INCLUDE_BUSINESS_LICENCES,
    outputDir,
    root,
  };

  ensureDir(outputDir);
  const filterContext = buildFilterContext(routeAnchorsPath, runtimeOptions);
  const manifest = {
    timestamp: new Date().toISOString(),
    api: { base: runtimeOptions.exploreBase, version: 'explore-v2.1' },
    datasets: [],
    filter: filterContext.query,
  };

  const summary = [];
  for (const dataset of DATASETS) {
    const result = await fetchDataset(dataset, filterContext, runtimeOptions);
    manifest.datasets.push(result);
    if (result.skipped) {
      summary.push(`${result.dataset}: skipped`);
    } else {
      summary.push(`${result.datasetId}: ${result.keptFeatures}/${result.totalFeatures}`);
    }
  }

  fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
  process.stdout.write(`Synced CoV open data via Explore API v2.1 (${summary.join(', ')})\n`);
}

if (require.main === module) {
  run().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exit(1);
  });
}

module.exports = { run, buildFilterContext, shouldKeepFeature, sortFeatures, featureId, normalizeFeatureCollection, buildWhereClause, normalizeJsonRows };