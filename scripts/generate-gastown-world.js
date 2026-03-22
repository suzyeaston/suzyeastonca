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

function getFeatureIdentifier(feature, index, prefix) {
  const props = getProps(feature);
  return String(
    feature.id ||
    props.id ||
    props.ID ||
    props.objectid ||
    props.OBJECTID ||
    props.site_id ||
    props.heritage_id ||
    props.HERITAGE_ID ||
    props.building_id ||
    props.BuildingID ||
    `${prefix}-${index}`
  );
}


function rotatePoint(point, angle) {
  const cos = Math.cos(angle);
  const sin = Math.sin(angle);
  return {
    x: (point.x * cos) - (point.z * sin),
    z: (point.x * sin) + (point.z * cos),
  };
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

function deriveFootprintMetrics(footprint) {
  if (!Array.isArray(footprint) || footprint.length < 3) {
    return {
      x: 0,
      z: 0,
      width: 8,
      depth: 8,
      yaw: 0,
      localFootprint: [
        { x: -4, z: -4 },
        { x: 4, z: -4 },
        { x: 4, z: 4 },
        { x: -4, z: 4 },
        { x: -4, z: -4 },
      ],
    };
  }

  let minX = Infinity;
  let maxX = -Infinity;
  let minZ = Infinity;
  let maxZ = -Infinity;
  let sumX = 0;
  let sumZ = 0;

  footprint.forEach((point) => {
    minX = Math.min(minX, point.x);
    maxX = Math.max(maxX, point.x);
    minZ = Math.min(minZ, point.z);
    maxZ = Math.max(maxZ, point.z);
    sumX += point.x;
    sumZ += point.z;
  });

  const centroid = {
    x: sumX / footprint.length,
    z: sumZ / footprint.length,
  };

  let longestEdge = { dx: 0, dz: 0, length: 0 };
  for (let i = 0; i < footprint.length - 1; i += 1) {
    const dx = footprint[i + 1].x - footprint[i].x;
    const dz = footprint[i + 1].z - footprint[i].z;
    const edgeLength = Math.hypot(dx, dz);
    if (edgeLength > longestEdge.length) {
      longestEdge = { dx, dz, length: edgeLength };
    }
  }

  const yaw = longestEdge.length > 0.01 ? Math.atan2(longestEdge.dz, longestEdge.dx) : 0;
  const centered = footprint.map((point) => ({ x: point.x - centroid.x, z: point.z - centroid.z }));
  const localFootprint = closePolygon(centered.map((point) => rotatePoint(point, -yaw)));

  let localMinX = Infinity;
  let localMaxX = -Infinity;
  let localMinZ = Infinity;
  let localMaxZ = -Infinity;
  localFootprint.forEach((point) => {
    localMinX = Math.min(localMinX, point.x);
    localMaxX = Math.max(localMaxX, point.x);
    localMinZ = Math.min(localMinZ, point.z);
    localMaxZ = Math.max(localMaxZ, point.z);
  });

  const width = Math.max(3, localMaxX - localMinX);
  const depth = Math.max(3, localMaxZ - localMinZ);

  return {
    x: centroid.x,
    z: centroid.z,
    width,
    depth,
    yaw,
    localFootprint,
  };
}

function proceduralStreetscape(points, options) {
  const {
    idPrefix,
    strideMeters,
    laneOffset,
    maxCount,
    mapper,
  } = options;
  const placements = [];
  if (!points || points.length < 2) return placements;

  const routeLength = polylineLength(points);
  if (routeLength < 5) return placements;

  const halfCount = Math.max(2, Math.min(maxCount, Math.floor(routeLength / strideMeters)));
  for (let i = 0; i <= halfCount; i += 1) {
    const d = (routeLength * i) / halfCount;
    const center = samplePointAtDistance(points, d);
    const tangent = normalize({
      x: samplePointAtDistance(points, Math.min(routeLength, d + 1)).x - samplePointAtDistance(points, Math.max(0, d - 1)).x,
      z: samplePointAtDistance(points, Math.min(routeLength, d + 1)).z - samplePointAtDistance(points, Math.max(0, d - 1)).z,
    });
    const normal = perpendicularLeft(tangent);

    [-1, 1].forEach((side) => {
      if (placements.length >= maxCount) return;
      const point = {
        x: center.x + normal.x * laneOffset * side,
        z: center.z + normal.z * laneOffset * side,
      };
      placements.push(mapper(point, `${idPrefix}-${placements.length}`));
    });
  }

  return placements.slice(0, maxCount);
}

function makeRectFootprint(center, tangent, width, depth) {
  const normal = perpendicularLeft(tangent);
  const halfW = width / 2;
  const halfD = depth / 2;
  const corners = [
    { x: (-halfW), z: (-halfD) },
    { x: halfW, z: (-halfD) },
    { x: halfW, z: halfD },
    { x: (-halfW), z: halfD },
  ];
  const world = corners.map((corner) => ({
    x: center.x + (normal.x * corner.x) + (tangent.x * corner.z),
    z: center.z + (normal.z * corner.x) + (tangent.z * corner.z),
  }));
  return closePolygon(world.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })));
}

function starterRouteFrame(routePoints, distanceMeters) {
  const routeLength = polylineLength(routePoints);
  const center = samplePointAtDistance(routePoints, Math.max(0, Math.min(routeLength, distanceMeters)));
  const ahead = samplePointAtDistance(routePoints, Math.min(routeLength, distanceMeters + 1.5));
  const behind = samplePointAtDistance(routePoints, Math.max(0, distanceMeters - 1.5));
  const tangent = normalize({ x: ahead.x - behind.x, z: ahead.z - behind.z });
  const normal = perpendicularLeft(tangent);
  return { center, tangent, normal, routeLength };
}

function placeStarterProp(routePoints, distanceMeters, lateralOffset, id, kind, extra = {}) {
  const frame = starterRouteFrame(routePoints, distanceMeters);
  return {
    id,
    kind,
    x: Number((frame.center.x + (frame.normal.x * lateralOffset)).toFixed(2)),
    y: Number((extra.y || 0).toFixed(2)),
    z: Number((frame.center.z + (frame.normal.z * lateralOffset)).toFixed(2)),
    yaw: Number(((extra.yawOffset || 0) + Math.atan2(frame.normal.x, frame.normal.z)).toFixed(4)),
    scale: Number((extra.scale || 1).toFixed(2)),
  };
}

function buildStarterProps(routePoints, sidewalkOuter, spawnDistance) {
  const propSpecs = [
    { id: 'starter-prop-threshold-box', kind: 'newspaper_box', d: 18, side: -1, offset: sidewalkOuter + 0.45, scale: 1.05 },
    { id: 'starter-prop-threshold-utility', kind: 'utility_box', d: 24, side: 1, offset: sidewalkOuter + 0.65, scale: 1.1 },
    { id: 'starter-prop-corner-bags', kind: 'trash_bag', d: 46, side: 1, offset: sidewalkOuter + 0.3, scale: 1.1 },
    { id: 'starter-prop-planter-west', kind: 'planter', d: 62, side: -1, offset: sidewalkOuter + 0.85, scale: 1.15 },
    { id: 'starter-prop-planter-east', kind: 'planter', d: 84, side: 1, offset: sidewalkOuter + 0.9, scale: 1.08 },
    { id: 'starter-prop-bench-clock', kind: 'bench', d: 101, side: -1, offset: sidewalkOuter + 0.8, scale: 1.04 },
    { id: 'starter-prop-clock-box', kind: 'newspaper_box', d: 108, side: 1, offset: sidewalkOuter + 0.5, scale: 0.98 },
    { id: 'starter-prop-postclock-utility', kind: 'utility_box', d: 132, side: -1, offset: sidewalkOuter + 0.7, scale: 1.06 },
    { id: 'starter-prop-postclock-bags', kind: 'trash_bag', d: 146, side: 1, offset: sidewalkOuter + 0.28, scale: 0.96 },
    { id: 'starter-prop-postclock-bench', kind: 'bench', d: 164, side: 1, offset: sidewalkOuter + 0.86, scale: 1 },
  ];

  return propSpecs
    .filter((spec) => Math.abs(spec.d - spawnDistance) > 10)
    .map((spec) => placeStarterProp(routePoints, spec.d, spec.side * spec.offset, spec.id, spec.kind, {
      scale: spec.scale,
      yawOffset: spec.kind === 'bench' ? Math.PI / 2 : 0,
    }));
}

function buildStarterLandmarks(routePoints, sidewalkOuter) {
  return [
    {
      id: 'starter-waterfront-threshold',
      label: 'Waterfront threshold',
      kind: 'district_gate',
      ...placeStarterProp(routePoints, 12, -1 * (sidewalkOuter + 2.6), 'starter-landmark-threshold-anchor', 'cardboard_box', { scale: 1 }),
      y: 0,
      scale: 1.35,
      cue: 'Station canopies give way to brick-front Gastown blocks.',
    },
    {
      id: 'starter-steam-clock-approach',
      label: 'Steam Clock approach',
      kind: 'clock-approach',
      ...placeStarterProp(routePoints, 104, sidewalkOuter + 2.4, 'starter-landmark-clock-anchor', 'cardboard_box', { scale: 1 }),
      y: 0,
      scale: 1.25,
      cue: 'Denser storefront rhythm, pavers, and curb clutter build toward the clock node.',
    },
    {
      id: 'starter-post-clock-continuation',
      label: 'Post-clock continuation',
      kind: 'view-axis',
      ...placeStarterProp(routePoints, 166, -1 * (sidewalkOuter + 2.2), 'starter-landmark-postclock-anchor', 'cardboard_box', { scale: 1 }),
      y: 0,
      scale: 1.18,
      cue: 'The corridor loosens into longer facades and a quieter continuation east.',
    },
  ].map((landmark) => ({
    id: landmark.id,
    label: landmark.label,
    kind: landmark.kind,
    x: landmark.x,
    y: landmark.y,
    z: landmark.z,
    scale: landmark.scale,
    cue: landmark.cue,
  }));
}

function makeStarterWorld(outputPath) {
  const routePoints = [
    { x: 0, z: 0 },
    { x: 4, z: -46 },
    { x: 8, z: -95 },
    { x: 12, z: -154 },
    { x: 15, z: -188 },
  ];
  const routeLength = polylineLength(routePoints);
  const beatCount = 10;
  const centerline = [];
  for (let i = 0; i <= beatCount; i += 1) {
    const at = (routeLength * i) / beatCount;
    const point = samplePointAtDistance(routePoints, at);
    centerline.push({
      id: 'starter-beat-' + i,
      label: i === 0 ? 'Starter Corridor Start' : i === beatCount ? 'Starter Corridor End' : 'Starter Beat ' + i,
      x: Number(point.x.toFixed(2)),
      z: Number(point.z.toFixed(2)),
    });
  }

  centerline[0].id = 'starter-corridor-start';
  centerline[Math.floor(centerline.length / 2)].id = 'starter-mid-block';
  centerline[centerline.length - 1].id = 'starter-corridor-end';

  const streetWidth = 9.8;
  const sidewalkWidth = 3.2;
  const softBoundary = 3.2;
  const streetHalf = streetWidth / 2;
  const sidewalkOuter = streetHalf + sidewalkWidth;
  const walkOuter = sidewalkOuter + softBoundary;

  const streetPolygon = ribbonPolygon(routePoints, streetHalf);
  const leftSidewalk = closePolygon(lineOffset(routePoints, sidewalkOuter).concat(lineOffset(routePoints, streetHalf).reverse()));
  const rightSidewalk = closePolygon(lineOffset(routePoints, -streetHalf).concat(lineOffset(routePoints, -sidewalkOuter).reverse()));
  const walkBounds = ribbonPolygon(routePoints, walkOuter);

  const frontagePattern = [8, 11, 7, 15, 9, 13, 10, 18, 12, 8, 16, 9];
  const depthPattern = [16, 21, 14, 24, 18, 22, 19, 20, 23, 17, 25, 18];
  const heightPattern = [11, 15, 12, 19, 10, 21, 16, 18, 14, 9, 20, 13];
  const recessPattern = [1, 2, 1, 3, 1, 2, 2, 1, 3, 1, 2, 1];
  const palettePattern = [
    { primary: '#74493c', trim: '#cfb8a2', accent: '#4f5f6f', tone: 'brickWarm' },
    { primary: '#8a857d', trim: '#d8c9b6', accent: '#57656e', tone: 'stoneMuted' },
    { primary: '#6e3f36', trim: '#caa98c', accent: '#445666', tone: 'brickWarm' },
    { primary: '#6f747c', trim: '#d0c3b3', accent: '#3f5363', tone: 'stoneMuted' },
  ];
  const facadePattern = ['gastown_heritage_row', 'cordova_commercial_transition', 'gastown_heritage_row', 'narrow_brick_shaft'];
  const buildings = [];
  let cursor = 6;
  let i = 0;
  while (cursor < routeLength - 12 && i < 30) {
    const frontage = frontagePattern[i % frontagePattern.length];
    const depth = depthPattern[i % depthPattern.length];
    const height = heightPattern[i % heightPattern.length];
    const frame = starterRouteFrame(routePoints, cursor);

    [-1, 1].forEach((side) => {
      const sideIndex = side < 0 ? 0 : 1;
      const palette = palettePattern[(i + sideIndex) % palettePattern.length];
      const blockPhase = cursor / routeLength;
      const cornerMoment = i % 7 === (side < 0 ? 2 : 4);
      const waterfrontThreshold = blockPhase < 0.22;
      const steamClockApproach = blockPhase > 0.46 && blockPhase < 0.7;
      const postClock = blockPhase >= 0.7;
      const localDepth = depth + (cornerMoment ? 3.5 : 0) + (waterfrontThreshold && side > 0 ? 1.5 : 0);
      const inset = cornerMoment ? 1.9 : (steamClockApproach ? 1.3 : 0.85);
      const centerOffset = sidewalkOuter + (localDepth / 2) + inset;
      const footprintCenter = {
        x: frame.center.x + (frame.normal.x * centerOffset * side),
        z: frame.center.z + (frame.normal.z * centerOffset * side),
      };
      const localFrontage = frontage + (cornerMoment ? 3 : 0) + (postClock && side < 0 ? 2 : 0);
      const footprint = makeRectFootprint(footprintCenter, frame.tangent, localFrontage, localDepth);
      const metrics = deriveFootprintMetrics(footprint);
      const id = `starter-bldg-${i}-${side < 0 ? 'left' : 'right'}`;
      const recessedEntryCount = recessPattern[(i + sideIndex) % recessPattern.length];
      const corniceEmphasis = cornerMoment ? 0.42 : (steamClockApproach ? 0.34 : 0.26);
      const massInset = waterfrontThreshold ? 0.95 : (postClock ? 0.93 : 0.96);
      buildings.push({
        id,
        reference_name: cornerMoment ? 'Starter corner heritage anchor' : side < 0 ? 'Starter west frontage' : 'Starter east frontage',
        segment_style: waterfrontThreshold ? 'waterfront-threshold' : steamClockApproach ? 'steam-clock-approach' : postClock ? 'post-clock-continuation' : 'mid-corridor-heritage-rhythm',
        style_notes: cornerMoment ? 'Corner building massing with stronger cornice to punctuate the route.' : 'Stylized heritage storefront cadence tuned for fallback readability.',
        x: Number(metrics.x.toFixed(2)),
        z: Number(metrics.z.toFixed(2)),
        width: Number(metrics.width.toFixed(2)),
        depth: Number(metrics.depth.toFixed(2)),
        yaw: Number(metrics.yaw.toFixed(4)),
        height: Number((height + (cornerMoment ? 2 : 0) + (waterfrontThreshold && side < 0 ? 1 : 0)).toFixed(1)),
        footprint,
        footprint_local: metrics.localFootprint.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })),
        facade_profile: facadePattern[(i + sideIndex) % facadePattern.length],
        tone: palette.tone,
        roofline_type: cornerMoment ? 'wrapped_cornice' : steamClockApproach ? 'ornate_cornice' : 'flat_cornice',
        window_bay_count: Math.max(3, Math.round(localFrontage / (cornerMoment ? 3.4 : 2.6))),
        recessed_entry_count: recessedEntryCount,
        storefront_rhythm: { base_band: Number((waterfrontThreshold ? 0.16 : steamClockApproach ? 0.22 : 0.19).toFixed(2)), upper_rows: height >= 16 ? 4 : 3 },
        material_palette: palette,
        cornice_emphasis: Number(corniceEmphasis.toFixed(2)),
        mass_inset: Number(massInset.toFixed(2)),
      });
    });

    cursor += frontage + (i % 3 === 0 ? 1.8 : 2.9);
    i += 1;
  }

  const start = centerline[0];
  const next = centerline[1] || centerline[0];
  const dir = normalize({ x: next.x - start.x, z: next.z - start.z });
  const spawn = {
    x: Number((start.x + (dir.x * 9)).toFixed(2)),
    y: 1.7,
    z: Number((start.z + (dir.z * 9)).toFixed(2)),
    yaw: Number(Math.atan2(-dir.x, -dir.z).toFixed(4)),
  };

  const props = buildStarterProps(routePoints, sidewalkOuter, 9);
  const landmarks = buildStarterLandmarks(routePoints, sidewalkOuter);

  const world = {
    routeId: 'gastown_water_street_starter_corridor',
    meta: {
      title: 'Gastown Starter Corridor (deterministic fallback)',
      units: 'meters',
      source: 'deterministic starter fallback',
      fallbackMode: 'starter-corridor',
      isRealCivicBuild: false,
      buildNotes: [
        'Starter corridor fallback is active because required offline civic source files are missing.',
        'This world is intentionally human-scaled and deterministic for readability; it is not GIS-accurate Gastown reconstruction.',
      ],
      lastBuild: new Date().toISOString(),
    },
    route: {
      name: 'Gastown starter block corridor',
      centerline,
      walkBounds,
      streetWidth,
      sidewalkWidth,
      softBoundary,
      hardResetDistance: 12,
    },
    nodes: [
      { ...centerline[0], label: 'Starter start' },
      { ...centerline[Math.floor(centerline.length / 2)], label: 'Starter mid block' },
      { ...centerline[centerline.length - 1], label: 'Starter end' },
    ],
    zones: {
      street: [{ id: 'starter-roadway', surface: 'road', polygon: streetPolygon }],
      sidewalk: [
        { id: 'starter-sidewalk-left', side: 'left', polygon: leftSidewalk },
        { id: 'starter-sidewalk-right', side: 'right', polygon: rightSidewalk },
      ],
    },
    buildings,
    landmarks,
    props,
    streetscape: {
      lamps: proceduralStreetscape(routePoints, {
        idPrefix: 'starter-lamp', strideMeters: 24, laneOffset: sidewalkOuter - 0.5, maxCount: 24,
        mapper: (point, id) => ({ id, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), height: 5.4 }),
      }),
      trees: proceduralStreetscape(routePoints, {
        idPrefix: 'starter-tree', strideMeters: 30, laneOffset: sidewalkOuter + 2.2, maxCount: 16,
        mapper: (point, id) => ({ id, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), radius: 1.4 }),
      }),
      bollards: [],
      surfaceBands: buildStarterSurfaceBands(centerline, streetWidth, routeLength),
    },
    spawn,
    bounds: {
      floorY: 0,
      edgeMessage: 'Stayed within the starter street corridor.',
      resetMessage: 'Returned to the starter street corridor.',
    },
  };

  fs.writeFileSync(outputPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  return { generated: true, outputPath, routeLength, usedStarterFallback: true };
}


function buildStarterSurfaceBands(centerline, streetWidth, routeLength) {
  const bands = [];
  centerline.forEach((point, index) => {
    const next = centerline[index + 1] || centerline[index - 1];
    if (!next) return;

    const heading = Math.atan2(next.x - point.x, next.z - point.z);
    const length = Math.max(7, Math.min(14, distance(point, next) * 0.92 || 9));
    const progress = routeLength > 0 ? index / Math.max(1, centerline.length - 1) : 0;
    const edgeOffset = streetWidth * 0.35;
    const jointOffset = streetWidth * 0.22;
    const jitter = ((index % 4) - 1.5) * 0.2;
    const heritageBandWidth = progress > 0.42 && progress < 0.72 ? streetWidth * 0.72 : streetWidth * 0.56;

    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.3).toFixed(2)), length: Number(length.toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: 0, offset_z: 0, tone: 'wheel_track', opacity: 0.24, elevation: 0.032 });
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.18).toFixed(2)), length: Number(Math.max(5.4, length * (progress < 0.25 ? 0.64 : 0.84)).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number(jitter.toFixed(2)), offset_z: 0, tone: progress > 0.65 ? 'repair_patch_dark' : 'patch', opacity: progress > 0.65 ? 0.38 : 0.33, elevation: 0.038 });
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.08).toFixed(2)), length: Number(Math.max(6.2, length * 1.06).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number(edgeOffset.toFixed(2)), offset_z: 0, tone: 'curb_grime', opacity: 0.28, elevation: 0.034 });
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.08).toFixed(2)), length: Number(Math.max(6.2, length * 1.04).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number((-edgeOffset).toFixed(2)), offset_z: 0, tone: 'curb_grime', opacity: 0.28, elevation: 0.034 });

    if (index % 2 === 0) {
      bands.push({ segment_id: point.id, width: Number(heritageBandWidth.toFixed(2)), length: Number(Math.max(2.8, length * 0.22).toFixed(2)), yaw: Number((heading + Math.PI / 2).toFixed(4)), offset_x: Number((index % 4 === 0 ? -jointOffset : jointOffset).toFixed(2)), offset_z: 0, tone: progress > 0.42 && progress < 0.72 ? 'cobble_break' : 'paver_break', opacity: 0.34, elevation: 0.041 });
    }

    if (index === 1 || index === Math.floor((centerline.length - 1) / 2) || index === centerline.length - 2) {
      bands.push({ segment_id: point.id, width: Number((streetWidth * 0.82).toFixed(2)), length: Number(Math.max(3.6, length * 0.3).toFixed(2)), yaw: Number((heading + Math.PI / 2).toFixed(4)), offset_x: 0, offset_z: 0, tone: 'intersection_pavers', opacity: 0.42, elevation: 0.045 });
    }
  });
  return bands;
}

function buildWorld(options = {}) {
  const root = options.root || path.resolve(__dirname, '..');
  const outputPath = options.outputPath || path.join(root, 'assets', 'world', 'gastown-water-street.json');
  const routeAnchorsPath = path.join(root, 'data', 'reference', 'route-anchors.json');
  const streetsPath = path.join(root, 'data', 'cov', 'public-streets.geojson');
  const buildingsPath = path.join(root, 'data', 'cov', 'building-footprints.geojson');
  const polesPath = path.join(root, 'data', 'cov', 'street-lighting-poles.geojson');
  const treesPath = path.join(root, 'data', 'cov', 'public-trees.geojson');
  const heritagePath = path.join(root, 'data', 'cov', 'heritage-sites.geojson');

  if (!fs.existsSync(routeAnchorsPath) || !fs.existsSync(streetsPath) || !fs.existsSync(buildingsPath)) {
    return makeStarterWorld(outputPath);
  }

  const anchors = readJson(routeAnchorsPath);
  const streets = readJson(streetsPath);
  const buildingsGeo = readJson(buildingsPath);
  const poles = tryReadJson(polesPath);
  const trees = tryReadJson(treesPath);
  const heritageSites = tryReadJson(heritagePath);

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

  const heritageCandidates = heritageSites
    ? (heritageSites.features || []).flatMap((feature, index) => {
      const polygons = extractPolygons(feature);
      const props = getProps(feature);
      const heritageId = getFeatureIdentifier(feature, index, 'heritage');
      return polygons.map((polygon) => {
        const ring = polygon[0] || [];
        if (ring.length < 4) return null;
        const footprint = ring.slice(0, -1).map((coord) => projectLonLat(coord[0], coord[1], origin));
        if (footprint.length < 3) return null;
        const centroid = footprint.reduce((acc, point) => ({ x: acc.x + point.x, z: acc.z + point.z }), { x: 0, z: 0 });
        centroid.x /= footprint.length;
        centroid.z /= footprint.length;
        return {
          heritageId,
          props,
          centroid,
          footprint,
        };
      }).filter(Boolean);
    })
    : [];

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
    const id = getFeatureIdentifier(feature, index, 'building');
    const projectedFootprint = closePolygon(footprint.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })));
    const metrics = deriveFootprintMetrics(projectedFootprint);
    const nearestHeritage = heritageCandidates.reduce((best, heritage) => {
      const heritageDistance = pointToPolylineDistance(heritage.centroid, projectedFootprint);
      if (!best || heritageDistance < best.distance) {
        return { heritage, distance: heritageDistance };
      }
      return best;
    }, null);
    const heritageMatch = nearestHeritage && nearestHeritage.distance <= 18 ? nearestHeritage.heritage : null;
    buildingCandidates.push({
      distance: d,
      building: {
        id: String(id),
        footprint: projectedFootprint,
        footprint_local: metrics.localFootprint.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })),
        x: Number(metrics.x.toFixed(2)),
        z: Number(metrics.z.toFixed(2)),
        width: Number(metrics.width.toFixed(2)),
        depth: Number(metrics.depth.toFixed(2)),
        yaw: Number(metrics.yaw.toFixed(4)),
        height: Number(deterministicValue(id, 12, 22).toFixed(1)),
        facade_profile: 'gastown_heritage_row',
        tone: d > 24 ? 'stoneMuted' : 'brickWarm',
        reference_name: props.name || props.civic_address || props.address || 'Corridor building',
        roofline_type: d > 20 ? 'flat_cornice' : 'angled_parapet',
        window_bay_count: Math.round(deterministicValue(id + '-bays', 3, 6)),
        recessed_entry_count: Math.round(deterministicValue(id + '-entries', 1, 2)),
        storefront_rhythm: {
          base_band: Number(deterministicValue(id + '-band', 0.14, 0.22).toFixed(2)),
          upper_rows: Math.round(deterministicValue(id + '-rows', 2, 4)),
        },
        material_palette: {
          primary: d > 24 ? '#8b8f92' : '#7f4f3f',
          trim: '#c8c2b8',
          accent: '#4b5968',
        },
        cornice_emphasis: Number(deterministicValue(id + '-cornice', 0.18, 0.42).toFixed(2)),
        mass_inset: Number(deterministicValue(id + '-massing', 0.92, 0.98).toFixed(2)),
        is_heritage_candidate: Boolean(heritageMatch),
        heritage_id: heritageMatch ? heritageMatch.heritageId : null,
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

  const lampSource = poles ? 'civic-data' : 'procedural-fallback';
  const lamps = poles
    ? thinPoints(poles.features, 10, 80, (point, index) => ({ id: 'lamp-' + index, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), height: 5.6 }))
    : proceduralStreetscape(mergedRoute, {
      idPrefix: 'lamp-fallback',
      strideMeters: 42,
      laneOffset: sidewalkOuter - 0.35,
      maxCount: 28,
      mapper: (point, id) => ({ id, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), height: 5.6 }),
    });

  const treeSource = trees ? 'civic-data' : 'procedural-fallback';
  const treePoints = trees
    ? thinPoints(trees.features, 9, 90, (point, index) => ({ id: 'tree-' + index, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), radius: Number(deterministicValue(index, 1.1, 2.4).toFixed(2)) }))
    : proceduralStreetscape(mergedRoute, {
      idPrefix: 'tree-fallback',
      strideMeters: 30,
      laneOffset: sidewalkOuter + 1.5,
      maxCount: 40,
      mapper: (point, id) => ({ id, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), radius: Number(deterministicValue(id, 1.15, 2.1).toFixed(2)) }),
    });

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
            heritageSitesOptional: 'data/cov/heritage-sites.geojson',
          },
          reference: {
            routeAnchors: 'data/reference/route-anchors.json',
          },
        },
      },
      generationNotes: {
        buildings: 'renderer-compatible derived geometry from footprint centroids/extents/headings (footprints preserved)',
        streetscapeTrees: treeSource,
        streetscapeLamps: lampSource,
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
