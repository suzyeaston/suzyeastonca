#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const EARTH_RADIUS_METERS = 6371008.8;

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function tryReadJson(filePath) {
  if (!fs.existsSync(filePath)) return null;
  return readJson(filePath);
}

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

function closePolygon(points) {
  if (!points.length) return points;
  const first = points[0];
  const last = points[points.length - 1];
  if (first.x !== last.x || first.z !== last.z) {
    return points.concat([{ x: first.x, z: first.z }]);
  }
  return points;
}

function getProps(feature) {
  return feature && feature.properties ? feature.properties : {};
}

function getStreetName(feature) {
  const props = getProps(feature);
  return (
    props.street_name ||
    props.name ||
    props.full_name ||
    props.road_name ||
    props.STREET ||
    props.ST_NAME ||
    ''
  );
}

function parseHblockStreet(feature) {
  const props = getProps(feature);
  const hblock = String(props.hblock || '').trim().toUpperCase();
  if (!hblock) return null;

  const match = hblock.match(/^(\d+(?:\s*-\s*\d+)?)\s+((?:N|S|E|W)\s+)?([A-Z0-9'\- ]+?)\s+(ST|AVE|RD|DR|BLVD|PL|WAY|LANE|LN|CT|CRES|TER|TRL|PKWY)$/);
  if (!match) return null;

  const block = match[1].replace(/\s+/g, '');
  const directional = (match[2] || '').trim();
  const base = normalizeStreet(match[3]);
  const suffix = normalizeStreet(match[4]);
  return {
    hblock,
    block,
    street: normalizeStreet(`${directional} ${base} ${suffix}`),
  };
}

function isTargetCorridorBlock(parsed) {
  if (!parsed) return false;

  const validByStreet = {
    'w cordova st': new Set(['0', '100', '300', '400', '500', '600', '700', '800-900']),
    'water st': new Set(['0', '100', '200-300']),
  };

  const blocks = validByStreet[parsed.street];
  if (!blocks) return false;
  return blocks.has(parsed.block);
}

function normalizeStreet(name) {
  return String(name || '').toLowerCase().replace(/\./g, '').replace(/\s+/g, ' ').trim();
}

function extractLineStrings(feature) {
  if (!feature || !feature.geometry) return [];
  const geometry = feature.geometry;
  if (geometry.type === 'LineString') return [geometry.coordinates];
  if (geometry.type === 'MultiLineString') return geometry.coordinates;
  return [];
}

function extractPolygons(feature) {
  if (!feature || !feature.geometry) return [];
  const geometry = feature.geometry;
  if (geometry.type === 'Polygon') return [geometry.coordinates];
  if (geometry.type === 'MultiPolygon') return geometry.coordinates;
  return [];
}

function extractPoints(feature) {
  if (!feature || !feature.geometry) return [];
  const geometry = feature.geometry;
  if (geometry.type === 'Point') return [geometry.coordinates];
  if (geometry.type === 'MultiPoint') return geometry.coordinates;
  return [];
}

function distance(a, b) {
  return Math.hypot(a.x - b.x, a.z - b.z);
}

function dot(a, b) {
  return a.x * b.x + a.z * b.z;
}

function normalize(v) {
  const mag = Math.hypot(v.x, v.z) || 1;
  return { x: v.x / mag, z: v.z / mag };
}

function perpendicularLeft(v) {
  return { x: -v.z, z: v.x };
}

function rdp(points, epsilon) {
  if (points.length < 3) return points.slice();
  const start = points[0];
  const end = points[points.length - 1];
  let maxDistance = -1;
  let index = -1;

  for (let i = 1; i < points.length - 1; i += 1) {
    const point = points[i];
    const seg = { x: end.x - start.x, z: end.z - start.z };
    const segLenSq = seg.x * seg.x + seg.z * seg.z;
    let projected;
    if (segLenSq < 1e-9) {
      projected = start;
    } else {
      const t = Math.max(0, Math.min(1, dot({ x: point.x - start.x, z: point.z - start.z }, seg) / segLenSq));
      projected = { x: start.x + seg.x * t, z: start.z + seg.z * t };
    }
    const d = distance(point, projected);
    if (d > maxDistance) {
      maxDistance = d;
      index = i;
    }
  }

  if (maxDistance > epsilon) {
    const left = rdp(points.slice(0, index + 1), epsilon);
    const right = rdp(points.slice(index), epsilon);
    return left.slice(0, -1).concat(right);
  }

  return [start, end];
}

function pointToSegmentDistance(point, a, b) {
  const ab = { x: b.x - a.x, z: b.z - a.z };
  const lenSq = ab.x * ab.x + ab.z * ab.z;
  if (lenSq < 1e-9) return distance(point, a);
  const t = Math.max(0, Math.min(1, dot({ x: point.x - a.x, z: point.z - a.z }, ab) / lenSq));
  const projected = { x: a.x + ab.x * t, z: a.z + ab.z * t };
  return distance(point, projected);
}

function pointToPolylineDistance(point, line) {
  if (line.length === 0) return Infinity;
  if (line.length === 1) return distance(point, line[0]);
  let best = Infinity;
  for (let i = 0; i < line.length - 1; i += 1) {
    best = Math.min(best, pointToSegmentDistance(point, line[i], line[i + 1]));
  }
  return best;
}

function polylineLength(points) {
  let len = 0;
  for (let i = 0; i < points.length - 1; i += 1) {
    len += distance(points[i], points[i + 1]);
  }
  return len;
}

function samplePointAtDistance(points, target) {
  if (!points.length) return { x: 0, z: 0 };
  if (points.length === 1) return points[0];
  let traversed = 0;
  for (let i = 0; i < points.length - 1; i += 1) {
    const segLen = distance(points[i], points[i + 1]);
    if (traversed + segLen >= target) {
      const t = segLen < 1e-9 ? 0 : (target - traversed) / segLen;
      return {
        x: points[i].x + (points[i + 1].x - points[i].x) * t,
        z: points[i].z + (points[i + 1].z - points[i].z) * t,
      };
    }
    traversed += segLen;
  }
  return points[points.length - 1];
}

function lineOffset(points, distanceMeters) {
  if (points.length < 2) return points.slice();
  const segmentNormals = [];
  for (let i = 0; i < points.length - 1; i += 1) {
    const direction = normalize({
      x: points[i + 1].x - points[i].x,
      z: points[i + 1].z - points[i].z,
    });
    segmentNormals.push(perpendicularLeft(direction));
  }

  const result = [];
  for (let i = 0; i < points.length; i += 1) {
    let offset;
    if (i === 0) {
      offset = { x: segmentNormals[0].x * distanceMeters, z: segmentNormals[0].z * distanceMeters };
    } else if (i === points.length - 1) {
      const n = segmentNormals[segmentNormals.length - 1];
      offset = { x: n.x * distanceMeters, z: n.z * distanceMeters };
    } else {
      const n1 = segmentNormals[i - 1];
      const n2 = segmentNormals[i];
      const miter = normalize({ x: n1.x + n2.x, z: n1.z + n2.z });
      const denom = dot(miter, n2);
      const scale = Math.abs(denom) < 0.2 ? distanceMeters : Math.max(-3 * Math.abs(distanceMeters), Math.min(3 * Math.abs(distanceMeters), distanceMeters / denom));
      offset = { x: miter.x * scale, z: miter.z * scale };
    }
    result.push({ x: points[i].x + offset.x, z: points[i].z + offset.z });
  }
  return result;
}

function ribbonPolygon(points, halfWidth) {
  const left = lineOffset(points, halfWidth);
  const right = lineOffset(points, -halfWidth);
  return closePolygon(left.concat(right.reverse()));
}

function mergeCorridorSegments(segments, origin, dest) {
  if (!segments.length) return [];

  const unused = segments.map((segment) => segment.slice());
  let bestIndex = 0;
  let bestDist = Infinity;
  let bestReverse = false;
  for (let i = 0; i < unused.length; i += 1) {
    const seg = unused[i];
    const dStart = distance(seg[0], origin);
    const dEnd = distance(seg[seg.length - 1], origin);
    if (dStart < bestDist) {
      bestDist = dStart;
      bestIndex = i;
      bestReverse = false;
    }
    if (dEnd < bestDist) {
      bestDist = dEnd;
      bestIndex = i;
      bestReverse = true;
    }
  }

  let route = unused.splice(bestIndex, 1)[0];
  if (bestReverse) route = route.slice().reverse();

  while (unused.length) {
    const tail = route[route.length - 1];
    let nextIndex = -1;
    let reverse = false;
    let gap = Infinity;

    for (let i = 0; i < unused.length; i += 1) {
      const seg = unused[i];
      const dStart = distance(tail, seg[0]);
      const dEnd = distance(tail, seg[seg.length - 1]);
      if (dStart < gap) {
        gap = dStart;
        nextIndex = i;
        reverse = false;
      }
      if (dEnd < gap) {
        gap = dEnd;
        nextIndex = i;
        reverse = true;
      }
    }

    if (nextIndex === -1 || gap > 80) break;
    const segment = unused.splice(nextIndex, 1)[0];
    const oriented = reverse ? segment.slice().reverse() : segment;
    route = route.concat(oriented.slice(1));
  }

  const startDist = distance(route[0], origin) + distance(route[route.length - 1], dest);
  const endDist = distance(route[0], dest) + distance(route[route.length - 1], origin);
  if (endDist < startDist) {
    route.reverse();
  }

  return route;
}

function segmentAnchorScore(segment, anchors) {
  if (!segment.length || !anchors.length) return Infinity;
  return anchors.reduce((acc, anchor) => {
    const best = segment.reduce((min, point) => Math.min(min, distance(point, anchor)), Infinity);
    return acc + best;
  }, 0);
}

function sortSegmentsByAnchorProximity(segments, anchors) {
  return segments
    .map((segment) => ({ segment: segment.coords, score: segmentAnchorScore(segment.coords, anchors) - segment.arterialBias }))
    .sort((a, b) => a.score - b.score)
    .map((entry) => entry.segment);
}

function getArterialBias(feature) {
  const streetUse = String(getProps(feature).streetuse || '').toLowerCase();
  return streetUse.includes('arterial') ? 75 : 0;
}

function sanitizeNumber(value, fallback) {
  return Number.isFinite(value) ? value : fallback;
}

function deterministicValue(seed, min, max) {
  let hash = 2166136261;
  const text = String(seed || 'default');
  for (let i = 0; i < text.length; i += 1) {
    hash ^= text.charCodeAt(i);
    hash = Math.imul(hash, 16777619);
  }
  const normalized = ((hash >>> 0) % 1000) / 999;
  return min + (max - min) * normalized;
}

function buildWorld(options = {}) {
  const root = options.root || path.resolve(__dirname, '..');
  const outputPath = options.outputPath || path.join(root, 'assets', 'world', 'gastown-water-street.json');
  const routeAnchorsPath = path.join(root, 'data', 'reference', 'route-anchors.json');
  const streetsPath = path.join(root, 'data', 'cov', 'public-streets.geojson');
  const buildingsPath = path.join(root, 'data', 'cov', 'building-footprints.geojson');
  const polesPath = path.join(root, 'data', 'cov', 'street-lighting-poles.geojson');
  const treesPath = path.join(root, 'data', 'cov', 'public-trees.geojson');

  if (!fs.existsSync(routeAnchorsPath) || !fs.existsSync(streetsPath) || !fs.existsSync(buildingsPath)) {
    return {
      generated: false,
      reason: 'Required offline inputs missing (need route-anchors.json + public-streets.geojson + building-footprints.geojson).',
    };
  }

  const anchors = readJson(routeAnchorsPath);
  const streets = readJson(streetsPath);
  const buildingsGeo = readJson(buildingsPath);
  const poles = tryReadJson(polesPath);
  const trees = tryReadJson(treesPath);

  const origin = anchors.origin;
  const dest = anchors.dest;
  const originProjected = projectLonLat(origin.lon, origin.lat, origin);
  const destProjected = projectLonLat(dest.lon, dest.lat, origin);

  const corridorAnchorPath = [
    originProjected,
    {
      x: originProjected.x + (destProjected.x - originProjected.x) * 0.4,
      z: originProjected.z + (destProjected.z - originProjected.z) * 0.4,
    },
    {
      x: originProjected.x + (destProjected.x - originProjected.x) * 0.72,
      z: originProjected.z + (destProjected.z - originProjected.z) * 0.72,
    },
    destProjected,
  ];

  const corridorSegments = sortSegmentsByAnchorProximity((streets.features || [])
    .filter((feature) => isTargetCorridorBlock(parseHblockStreet(feature)))
    .flatMap((feature) => extractLineStrings(feature).map((line) => ({
      coords: line.map((coord) => projectLonLat(coord[0], coord[1], origin)),
      arterialBias: getArterialBias(feature),
    }))), corridorAnchorPath);

  if (!corridorSegments.length) {
    return {
      generated: false,
      reason: 'No Water/W Cordova street centerline segments found in public-streets.geojson.',
    };
  }

  let mergedRoute = mergeCorridorSegments(corridorSegments, originProjected, destProjected);
  const epsilon = 1.1;
  mergedRoute = rdp(mergedRoute, epsilon);

  if (mergedRoute.length < 2) {
    return { generated: false, reason: 'Failed to construct route polyline from street geometry.' };
  }

  const totalLength = polylineLength(mergedRoute);
  const beatSpacing = 28;
  const beatCount = Math.max(4, Math.ceil(totalLength / beatSpacing));
  const centerline = [];
  for (let i = 0; i <= beatCount; i += 1) {
    const at = (totalLength * i) / beatCount;
    const point = samplePointAtDistance(mergedRoute, at);
    centerline.push({
      id: 'beat-' + i,
      label: i === 0 ? 'Waterfront Station' : i === beatCount ? 'Gastown Steam Clock' : 'Corridor Beat ' + i,
      x: Number(point.x.toFixed(2)),
      z: Number(point.z.toFixed(2)),
    });
  }

  centerline[0].id = 'station-threshold';
  centerline[0].label = 'Waterfront Station Threshold';
  const midIndex = Math.floor(centerline.length / 2);
  centerline[midIndex].id = 'water-mid';
  centerline[midIndex].label = 'Water/Cordova Mid';
  centerline[centerline.length - 1].id = 'steam-clock';
  centerline[centerline.length - 1].label = 'Steam Clock';

  const routeNodes = [
    { ...centerline[0] },
    { ...centerline[midIndex] },
    { ...centerline[centerline.length - 1] },
  ];

  const streetWidth = sanitizeNumber(anchors.streetWidth, 11);
  const sidewalkWidth = sanitizeNumber(anchors.sidewalkWidth, 4.5);
  const softBoundary = sanitizeNumber(anchors.softBoundary, 3);

  const streetHalf = streetWidth / 2;
  const sidewalkOuter = streetHalf + sidewalkWidth;
  const walkOuter = sidewalkOuter + softBoundary;

  const streetPolygon = ribbonPolygon(mergedRoute, streetHalf);
  const leftSidewalk = closePolygon(lineOffset(mergedRoute, sidewalkOuter).concat(lineOffset(mergedRoute, streetHalf).reverse()));
  const rightSidewalk = closePolygon(lineOffset(mergedRoute, -streetHalf).concat(lineOffset(mergedRoute, -sidewalkOuter).reverse()));
  const walkBounds = ribbonPolygon(mergedRoute, walkOuter);

  const buildingCandidates = [];
  (buildingsGeo.features || []).forEach((feature, index) => {
    const polygons = extractPolygons(feature);
    if (!polygons.length) return;

    const ring = polygons[0][0] || [];
    if (ring.length < 4) return;

    let footprint = ring.map((coord) => projectLonLat(coord[0], coord[1], origin));
    footprint = footprint.slice(0, -1);
    footprint = rdp(footprint, 0.6);
    if (footprint.length < 3) return;

    const centroid = footprint.reduce((acc, point) => ({ x: acc.x + point.x, z: acc.z + point.z }), { x: 0, z: 0 });
    centroid.x /= footprint.length;
    centroid.z /= footprint.length;

    const d = pointToPolylineDistance(centroid, mergedRoute);
    if (d > 40) return;

    const props = getProps(feature);
    const id = props.id || props.ID || props.objectid || props.OBJECTID || 'building-' + index;
    buildingCandidates.push({
      distance: d,
      building: {
        id: String(id),
        footprint: closePolygon(footprint.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) }))),
        height: Number(deterministicValue(id, 12, 22).toFixed(1)),
        facade_profile: 'gastown_heritage_row',
        tone: d > 24 ? 'stoneMuted' : 'brickWarm',
        reference_name: props.name || props.civic_address || props.address || 'Corridor building',
      },
    });
  });

  const buildings = buildingCandidates
    .sort((a, b) => a.distance - b.distance)
    .slice(0, 60)
    .map((entry) => entry.building);

  function thinPoints(features, strideMeters, maxCount, mapper) {
    const accepted = [];
    (features || []).forEach((feature, index) => {
      const points = extractPoints(feature);
      points.forEach((coord) => {
        const point = projectLonLat(coord[0], coord[1], origin);
        if (pointToPolylineDistance(point, mergedRoute) > 35) return;
        const tooClose = accepted.some((item) => distance(item, point) < strideMeters);
        if (tooClose || accepted.length >= maxCount) return;
        accepted.push(point);
      });
    });
    return accepted.map(mapper);
  }

  const lamps = poles
    ? thinPoints(poles.features, 10, 80, (point, index) => ({ id: 'lamp-' + index, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), height: 5.6 }))
    : [];
  const treePoints = trees
    ? thinPoints(trees.features, 9, 90, (point, index) => ({ id: 'tree-' + index, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), radius: Number(deterministicValue(index, 1.1, 2.4).toFixed(2)) }))
    : [];

  const existingWorld = fs.existsSync(outputPath) ? readJson(outputPath) : {};
  const existingMeta = existingWorld.meta || {};

  const world = {
    ...existingWorld,
    routeId: existingWorld.routeId || 'gastown_water_street_slice_offline_generated',
    meta: {
      ...existingMeta,
      title: existingMeta.title || 'Waterfront Station to Steam Clock Corridor',
      units: 'meters',
      source: 'Offline City of Vancouver Open Data exports',
      lastBuild: new Date().toISOString(),
      importManifest: existingMeta.importManifest || {
        inputs: {
          cityOfVancouver: {
            streetCenterlines: 'data/cov/public-streets.geojson',
            buildingFootprints: 'data/cov/building-footprints.geojson',
            lightingPolesOptional: 'data/cov/street-lighting-poles.geojson',
            publicTreesOptional: 'data/cov/public-trees.geojson',
          },
          reference: {
            routeAnchors: 'data/reference/route-anchors.json',
          },
        },
      },
    },
    route: {
      ...(existingWorld.route || {}),
      name: 'Waterfront Station → Water/Cordova → Steam Clock',
      centerline,
      walkBounds,
      streetWidth,
      sidewalkWidth,
      softBoundary,
      hardResetDistance: 14,
    },
    nodes: routeNodes,
    zones: {
      street: [{ id: 'water-cordova-roadway', surface: 'road', polygon: streetPolygon }],
      sidewalk: [
        { id: 'water-cordova-sidewalk-left', side: 'left', polygon: leftSidewalk },
        { id: 'water-cordova-sidewalk-right', side: 'right', polygon: rightSidewalk },
      ],
    },
    buildings,
    streetscape: {
      ...(existingWorld.streetscape || {}),
      lamps,
      trees: treePoints,
    },
    spawn: {
      ...(existingWorld.spawn || {}),
      x: Number(centerline[0].x.toFixed(2)),
      y: (existingWorld.spawn && existingWorld.spawn.y) || 1.7,
      z: Number(centerline[0].z.toFixed(2)),
      yaw: (existingWorld.spawn && existingWorld.spawn.yaw) || -0.25,
    },
  };

  fs.writeFileSync(outputPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  return { generated: true, outputPath, routeLength: totalLength };
}

if (require.main === module) {
  const result = buildWorld();
  if (!result.generated) {
    process.stderr.write(result.reason + '\n');
    process.exit(1);
  }
  process.stdout.write(`Generated ${path.relative(process.cwd(), result.outputPath)} (route ${result.routeLength.toFixed(1)}m)\n`);
}

module.exports = { buildWorld, parseHblockStreet, isTargetCorridorBlock, getArterialBias };
