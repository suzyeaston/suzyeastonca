#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const EARTH_RADIUS_METERS = 6371008.8;
const DEFAULT_ART_DIRECTION = {
  label: 'stylized realism with cinematic Vancouver rain-lighting',
  pillars: [
    'wet cobblestone reflections and low-angle dawn highlights',
    'heritage masonry with richer brick-stone-glass contrast',
    'dense civic clutter and postcard framing around the Steam Clock and Water Street bends',
  ],
};

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function tryReadJson(filePath) {
  if (!fs.existsSync(filePath)) return null;
  return readJson(filePath);
}

function tryReadGeoJsonFeatures(filePath) {
  const json = tryReadJson(filePath);
  if (!json || !Array.isArray(json.features)) return [];
  return json.features;
}

function firstExistingPath(paths) {
  for (const filePath of paths) {
    if (filePath && fs.existsSync(filePath)) return filePath;
  }
  return paths[0] || null;
}

function getFeatureCollectionById(collection, id) {
  const features = collection && Array.isArray(collection.features) ? collection.features : [];
  return features.find((feature) => getProps(feature).id === id) || null;
}

function getFeatureCollectionByKind(collection, kind) {
  const features = collection && Array.isArray(collection.features) ? collection.features : [];
  return features.filter((feature) => getProps(feature).kind === kind);
}

function projectFeaturePoint(feature, origin, fallback) {
  const coords = feature && feature.geometry && Array.isArray(feature.geometry.coordinates) ? feature.geometry.coordinates : null;
  if (!coords || coords.length < 2) return fallback;
  return projectLonLat(coords[0], coords[1], origin);
}

function sanitizeNumber(value, fallback) {
  return Number.isFinite(value) ? value : fallback;
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

function polygonSignedArea(points) {
  if (!Array.isArray(points) || points.length < 3) return 0;
  const ring = points[0] && points[points.length - 1]
    && points[0].x === points[points.length - 1].x
    && points[0].z === points[points.length - 1].z
    ? points.slice(0, -1)
    : points.slice();
  let area = 0;
  for (let i = 0; i < ring.length; i += 1) {
    const current = ring[i];
    const next = ring[(i + 1) % ring.length];
    area += (current.x * next.z) - (next.x * current.z);
  }
  return area / 2;
}

function sanitizePolygon(points) {
  if (!Array.isArray(points)) return [];
  const deduped = [];
  points.forEach((point) => {
    if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.z)) return;
    const last = deduped[deduped.length - 1];
    if (last && Math.abs(last.x - point.x) < 0.001 && Math.abs(last.z - point.z) < 0.001) return;
    deduped.push({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) });
  });
  if (deduped.length > 2) {
    const first = deduped[0];
    const last = deduped[deduped.length - 1];
    if (Math.abs(first.x - last.x) < 0.001 && Math.abs(first.z - last.z) < 0.001) {
      deduped.pop();
    }
  }
  const cleaned = deduped.filter((point, index, arr) => {
    if (arr.length < 3) return true;
    const prev = arr[(index + arr.length - 1) % arr.length];
    const next = arr[(index + 1) % arr.length];
    const cross = ((point.x - prev.x) * (next.z - point.z)) - ((point.z - prev.z) * (next.x - point.x));
    return Math.abs(cross) > 0.0005;
  });
  if (cleaned.length >= 3 && polygonSignedArea(cleaned) < 0) {
    cleaned.reverse();
  }
  return closePolygon(cleaned);
}

function sanitizeZonePolygon(points) {
  const polygon = sanitizePolygon(points);
  if (polygon.length < 4) {
    throw new Error('Zone polygon must contain at least three unique vertices.');
  }
  return polygon;
}

function polygonContainsPoint(point, polygon) {
  if (!Array.isArray(polygon) || polygon.length < 3) return false;
  let inside = false;
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const xi = polygon[i].x;
    const zi = polygon[i].z;
    const xj = polygon[j].x;
    const zj = polygon[j].z;
    const intersects = ((zi > point.z) !== (zj > point.z))
      && (point.x < ((xj - xi) * (point.z - zi)) / ((zj - zi) || 1e-9) + xi);
    if (intersects) inside = !inside;
  }
  return inside;
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

function orientedRect(center, tangent, width, depth) {
  return makeRectFootprint(center, tangent, width, depth);
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

function buildStarterLandmarks(layout) {
  const heroRadius = 5.2;
  const clockYaw = Math.atan2(layout.plaza.normal.x, layout.plaza.normal.z);

  return {
    landmarks: [
      {
        id: 'waterfront-station-threshold',
        label: 'Waterfront Station threshold',
        kind: 'district_gate',
        x: Number(layout.station.x.toFixed(2)),
        y: 0,
        z: Number(layout.station.z.toFixed(2)),
        scale: 1.35,
        cue: 'Station canopies give way to brick-front Gastown blocks and the Water Street bend begins.',
      },
      {
        id: 'water-cordova-seam',
        label: 'Water/Cordova seam',
        kind: 'street_pivot',
        x: Number(layout.seam.x.toFixed(2)),
        y: 0,
        z: Number(layout.seam.z.toFixed(2)),
        scale: 1.16,
        cue: 'The corridor bends into Water Street and starts reading like a heritage streetscape instead of a starter ribbon.',
      },
      {
        id: 'water-street-mid-block',
        label: 'Water Street mid block',
        kind: 'heritage_frontage',
        x: Number(layout.midBlock.x.toFixed(2)),
        y: 0,
        z: Number(layout.midBlock.z.toFixed(2)),
        scale: 1.12,
        cue: 'Longer brick storefront rhythm and darker carriageway paving make the street read more like Water Street.',
      },
      {
        id: 'steam-clock',
        label: 'Gastown Steam Clock',
        kind: 'clock',
        x: Number(layout.clock.x.toFixed(2)),
        y: 0,
        z: Number(layout.clock.z.toFixed(2)),
        radius: heroRadius,
        scale: 1.28,
        cue: 'A brick-and-stone plaza opens at the intersection and stages the Steam Clock at the corner landmark moment.',
      },
      {
        id: 'maple-tree-square-edge',
        label: 'Maple Tree Square edge',
        kind: 'plaza_edge',
        x: Number(layout.mapleEdge.x.toFixed(2)),
        y: 0,
        z: Number(layout.mapleEdge.z.toFixed(2)),
        scale: 1.1,
        cue: 'Beyond the Steam Clock the street broadens and loosens toward Maple Tree Square.',
      },
      {
        id: 'cambie-rise-continuation',
        label: 'Cambie rise continuation',
        kind: 'view_axis',
        x: Number(layout.cambie.x.toFixed(2)),
        y: 0,
        z: Number(layout.cambie.z.toFixed(2)),
        scale: 1.18,
        cue: 'The eastern leg of the route continues beyond the clock intersection toward the Cambie rise.',
      },
    ],
    heroLandmarks: [
      {
        id: 'steam-clock-hero',
        label: 'Gastown Steam Clock',
        x: Number(layout.clock.x.toFixed(2)),
        y: 0,
        z: Number(layout.clock.z.toFixed(2)),
        yaw: Number((clockYaw + Math.PI * 0.25).toFixed(4)),
        ground_emphasis_radius: heroRadius,
        steamVentOffsets: [
          { x: -0.42, z: 0.64, y: 8.72 },
          { x: 0.42, z: -0.64, y: 8.72 },
        ],
        plaza: {
          radius: 5.8,
          depth: 7.2,
          apronWidth: 3.2,
        },
      },
    ],
  };
}


function buildStarterNpcs(layout, streetWidth, sidewalkOuter) {
  const corridorLaneOffset = Math.max((streetWidth * 0.22), 1.8);
  const sidewalkOffset = sidewalkOuter + 0.92;
  const placeAlong = (pathPoints, distanceMeters, offsetMeters) => {
    const frame = starterRouteFrame(pathPoints, distanceMeters);
    return {
      x: Number((frame.center.x + (frame.normal.x * offsetMeters)).toFixed(2)),
      z: Number((frame.center.z + (frame.normal.z * offsetMeters)).toFixed(2)),
    };
  };
  const placeMain = (distanceMeters, side, extraOffset) => placeAlong(layout.mainCorridor, distanceMeters, (side * (sidewalkOffset + extraOffset)));
  const placeLane = (distanceMeters, side, extraOffset) => placeAlong(layout.mainCorridor, distanceMeters, (side * (corridorLaneOffset + extraOffset)));
  const placeCross = (distanceMeters, side, extraOffset) => placeAlong(layout.crossCorridor, distanceMeters, (side * (2.1 + extraOffset)));
  const placePlaza = (forward, lateral) => ({
    x: Number((layout.clock.x + (layout.plaza.tangent.x * forward) + (layout.plaza.normal.x * lateral)).toFixed(2)),
    z: Number((layout.clock.z + (layout.plaza.tangent.z * forward) + (layout.plaza.normal.z * lateral)).toFixed(2)),
  });

  return [
    {
      id: 'starter-guide-threshold',
      role: 'guide',
      behavior: 'guide_pace',
      dialogId: 'guide_intro',
      interactRadius: 2.8,
      idleSpot: placeMain(14, -1, 1.1),
      patrol: [placeMain(11, -1, 1.05), placeMain(18, -1, 1.2)],
    },
    {
      id: 'starter-busker-clock',
      role: 'busker',
      behavior: 'busker_perform',
      pose: 'strum',
      heldProp: 'guitar',
      voiceCue: 'busker-hook',
      dialogId: 'busker_clock_corner',
      interactRadius: 3,
      idleSpot: placePlaza(-3.4, -2.2),
    },
    {
      id: 'starter-pedestrian-west',
      role: 'pedestrian',
      behavior: 'route_walk',
      dialogId: 'pedestrian_route_tip',
      interactRadius: 2.2,
      idleSpot: placeMain(48, -1, 0.55),
      patrol: [placeMain(38, -1, 0.58), placeMain(61, -1, 0.7)],
    },
    {
      id: 'starter-pedestrian-east',
      role: 'pedestrian',
      behavior: 'route_walk',
      dialogId: 'pedestrian_clock_hint',
      interactRadius: 2.2,
      idleSpot: placeCross(18, 1, 1.05),
      patrol: [placeCross(9, 1, 1), placeCross(30, 1, 1.1)],
    },
    {
      id: 'starter-skateboarder-mid',
      role: 'skateboarder',
      behavior: 'skate_glide',
      pose: 'glide',
      heldProp: 'skateboard',
      dialogId: 'skateboarder_corridor',
      interactRadius: 2.5,
      idleSpot: placeMain(78, -1, -1.35),
      patrol: [placeMain(58, -1, -1.3), placeMain(104, -1, -1.2)],
    },
    {
      id: 'starter-cyclist-eastbound',
      role: 'cyclist',
      behavior: 'cycle_cruise',
      pose: 'ride',
      heldProp: 'bike',
      dialogId: 'cyclist_water_street',
      interactRadius: 2.8,
      idleSpot: placeMain(116, 1, -1.05),
      patrol: [placeMain(70, 1, -1.1), placeCross(44, 1, -1)],
    },
    { id: 'starter-tourist-clock-west', role: 'tourist', behavior: 'tourist_pause', pose: 'group_gather', companionGroup: 'clock-family-a', voiceCue: 'tourist-cluster', dialogId: 'tourist_clock_stop', interactRadius: 2.2, idleSpot: placePlaza(1.8, -3.1), patrol: [placePlaza(0.6, -2.8), placePlaza(2.6, -3.4)] },
    { id: 'starter-tourist-clock-east', role: 'tourist', behavior: 'tourist_pause', pose: 'being_photographed', companionGroup: 'clock-family-a', voiceCue: 'tourist-cluster', dialogId: 'tourist_clock_stop', interactRadius: 2.2, idleSpot: placePlaza(2.1, -1.7), patrol: [placePlaza(1.2, -1.5), placePlaza(2.8, -1.95)] },
    { id: 'starter-tourist-clock-photo', role: 'photographer', behavior: 'photo_idle', pose: 'taking_photo', heldProp: 'camera', voiceCue: 'photo-direction', companionGroup: 'clock-family-a', dialogId: 'tourist_clock_photo', interactRadius: 2.4, idleSpot: placePlaza(-0.8, 1.8) },
    { id: 'starter-tourist-clock-stroller', role: 'tourist', behavior: 'tourist_wander', pose: 'group_gather', companionGroup: 'clock-family-b', voiceCue: 'tourist-cluster', dialogId: 'tourist_clock_stop', interactRadius: 2.2, idleSpot: placePlaza(3.5, 0.8), patrol: [placePlaza(2.8, 0.5), placePlaza(4.4, 1.1)] },
    { id: 'starter-tourist-clock-bench', role: 'tourist', behavior: 'photo_idle', pose: 'gathered', companionGroup: 'clock-family-b', voiceCue: 'tourist-cluster', dialogId: 'tourist_clock_stop', interactRadius: 2.2, idleSpot: placePlaza(-2.1, -0.9) },
    { id: 'starter-tourist-clock-child', role: 'tourist', behavior: 'tourist_pause', pose: 'group_gather', silhouetteScale: 0.82, companionGroup: 'clock-family-b', voiceCue: 'tourist-cluster', dialogId: 'tourist_clock_stop', interactRadius: 2, idleSpot: placePlaza(0.9, -0.2), patrol: [placePlaza(0.3, -0.1), placePlaza(1.4, -0.45)] },
  ];
}


function buildStarterReferenceBundle(root) {
  const refreshDir = path.join(root, 'data', 'reference', 'refresh');
  const covDir = path.join(root, 'data', 'cov');
  return {
    routeReference: tryReadJson(path.join(refreshDir, 'gastown-route-reference.geojson')),
    streetContext: tryReadJson(path.join(refreshDir, 'gastown-street-context.geojson')),
    landmarkReference: tryReadJson(path.join(refreshDir, 'gastown-landmark-reference.geojson')),
    buildingCues: tryReadJson(path.join(refreshDir, 'gastown-building-cues.geojson')),
    poiReference: tryReadJson(path.join(refreshDir, 'gastown-poi-reference.geojson')),
    publicStreets: tryReadJson(path.join(covDir, 'public-streets.geojson')),
    streetIntersections: tryReadJson(path.join(covDir, 'street-intersections.geojson')),
    rightOfWayWidths: tryReadJson(path.join(covDir, 'right-of-way-widths.geojson')),
    streetLightingPoles: tryReadJson(path.join(covDir, 'street-lighting-poles.geojson')),
    buildingFootprints2015: tryReadJson(firstExistingPath([path.join(covDir, 'building-footprints-2015.geojson'), path.join(covDir, 'building-footprints.geojson')])),
    orthophotoImagery2015: tryReadJson(path.join(covDir, 'orthophoto-imagery-2015.geojson')),
  };
}

function makeStarterWorld(outputPath, options = {}) {
  const reference = options.reference || {};
  const anchorOrigin = { lon: -123.11182, lat: 49.28602 };
  const routeFeature = getFeatureCollectionById(reference.routeReference, 'gastown-working-route');
  const fallbackRouteCoords = [
    [-123.11182, 49.28602],
    [-123.11142, 49.28580],
    [-123.11095, 49.28555],
    [-123.11030, 49.28523],
    [-123.10972, 49.28495],
    [-123.10918, 49.28468],
    [-123.10892, 49.28446],
  ];
  const routePoints = routeFeature && routeFeature.geometry && Array.isArray(routeFeature.geometry.coordinates)
    ? routeFeature.geometry.coordinates.map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin))
    : fallbackRouteCoords.map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin));

  const streetContextFeatures = Array.isArray(reference.streetContext && reference.streetContext.features) ? reference.streetContext.features : [];
  const waterCenterlineFeature = streetContextFeatures.find((feature) => getProps(feature).id === 'water-street-context-centerline');
  const waterEastFeature = streetContextFeatures.find((feature) => getProps(feature).id === 'water-street-east-context-centerline');
  const cambieCenterlineFeature = streetContextFeatures.find((feature) => getProps(feature).id === 'cambie-street-centerline')
    || streetContextFeatures.find((feature) => getProps(feature).id === 'cordova-transition-context');
  const plazaEdgeFeature = streetContextFeatures.find((feature) => getProps(feature).id === 'steam-clock-plaza-edge');
  const intersectionFeature = streetContextFeatures.find((feature) => getProps(feature).id === 'water-cambie-intersection');
  const rowWidthFeature = streetContextFeatures.find((feature) => getProps(feature).id === 'water-cambie-row-width');

  const waterCenterline = waterCenterlineFeature
    ? extractLineStrings(waterCenterlineFeature)[0].map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin))
    : routePoints;
  const waterEastLeg = waterEastFeature
    ? extractLineStrings(waterEastFeature)[0].map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin))
    : waterCenterline.slice(Math.max(0, waterCenterline.length - 3));
  const cambieCorridor = cambieCenterlineFeature
    ? extractLineStrings(cambieCenterlineFeature)[0].map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin))
    : [
      { x: routePoints[routePoints.length - 2].x + 10, z: routePoints[routePoints.length - 2].z - 20 },
      routePoints[routePoints.length - 2],
      { x: routePoints[routePoints.length - 2].x - 12, z: routePoints[routePoints.length - 2].z + 20 },
    ];

  const layout = {
    station: projectFeaturePoint(getFeatureCollectionById(reference.landmarkReference, 'waterfront-station-threshold'), anchorOrigin, routePoints[0]),
    seam: projectFeaturePoint(getFeatureCollectionById(reference.landmarkReference, 'water-cordova-seam'), anchorOrigin, samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.26)),
    midBlock: projectFeaturePoint(getFeatureCollectionById(reference.landmarkReference, 'water-street-mid-block'), anchorOrigin, samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.62)),
    mapleEdge: projectFeaturePoint(getFeatureCollectionById(reference.landmarkReference, 'maple-tree-square-edge'), anchorOrigin, routePoints[routePoints.length - 1]),
  };

  const intersectionPoint = projectFeaturePoint(intersectionFeature, anchorOrigin, samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.9));
  const intersectionFrame = starterRouteFrame(waterCenterline, Math.max(0, polylineLength(waterCenterline) * 0.9));
  const rowProps = getProps(rowWidthFeature);
  const rightOfWayWidth = sanitizeNumber(Number(rowProps.right_of_way_width || rowProps.row_width || rowProps.width_m), streetContextFeatures.length ? 19.6 : 20.2);
  const streetWidth = sanitizeNumber(Number(rowProps.carriageway_width || rowProps.roadway_width || rowProps.street_width), streetContextFeatures.length ? 9.4 : 9.8);
  const sidewalkWidth = sanitizeNumber(Number(rowProps.sidewalk_width || ((rightOfWayWidth - streetWidth) / 2)), Array.isArray(reference.buildingCues && reference.buildingCues.features) ? 5.1 : 4.9);
  const softBoundary = 3.6;
  const streetHalf = streetWidth / 2;
  const sidewalkOuter = streetHalf + sidewalkWidth;
  const walkOuter = sidewalkOuter + softBoundary;

  const plazaSideSign = -1;
  layout.clock = projectFeaturePoint(
    getFeatureCollectionById(reference.landmarkReference, 'steam-clock'),
    anchorOrigin,
    {
      x: intersectionPoint.x + (intersectionFrame.normal.x * plazaSideSign * (streetHalf + (sidewalkWidth * 1.18))) - (intersectionFrame.tangent.x * 4.8),
      z: intersectionPoint.z + (intersectionFrame.normal.z * plazaSideSign * (streetHalf + (sidewalkWidth * 1.18))) - (intersectionFrame.tangent.z * 4.8),
    }
  );
  layout.cambie = {
    x: Number((intersectionPoint.x + ((cambieCorridor[cambieCorridor.length - 1].x - cambieCorridor[0].x) * 0.28)).toFixed(2)),
    z: Number((intersectionPoint.z + ((cambieCorridor[cambieCorridor.length - 1].z - cambieCorridor[0].z) * 0.28)).toFixed(2)),
  };
  layout.plaza = {
    center: layout.clock,
    tangent: intersectionFrame.tangent,
    normal: {
      x: intersectionFrame.normal.x * plazaSideSign,
      z: intersectionFrame.normal.z * plazaSideSign,
    },
    edge: plazaEdgeFeature ? extractLineStrings(plazaEdgeFeature)[0].map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin)) : [],
    intersection: intersectionPoint,
  };
  layout.mainCorridor = waterCenterline;
  layout.crossCorridor = cambieCorridor;

  const waterWestLeg = waterCenterline.filter((point) => point.x <= intersectionPoint.x + 0.1);
  const waterRoute = waterWestLeg.concat([layout.clock], waterEastLeg.slice(1));
  const routeLength = polylineLength(waterRoute);
  const centerline = [
    { id: 'waterfront-station-threshold', label: 'Waterfront Station threshold', x: Number(layout.station.x.toFixed(2)), z: Number(layout.station.z.toFixed(2)) },
    { id: 'gastown-beat-1', label: 'Cordova heritage lead-in', x: Number(samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.14).x.toFixed(2)), z: Number(samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.14).z.toFixed(2)) },
    { id: 'water-cordova-seam', label: 'Water/Cordova seam', x: Number(layout.seam.x.toFixed(2)), z: Number(layout.seam.z.toFixed(2)) },
    { id: 'gastown-beat-3', label: 'West Water Street', x: Number(samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.4).x.toFixed(2)), z: Number(samplePointAtDistance(waterCenterline, polylineLength(waterCenterline) * 0.4).z.toFixed(2)) },
    { id: 'water-street-mid-block', label: 'Water Street mid block', x: Number(layout.midBlock.x.toFixed(2)), z: Number(layout.midBlock.z.toFixed(2)) },
    { id: 'steam-clock-approach', label: 'Steam Clock approach', x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 5.1)).toFixed(2)), z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 5.1)).toFixed(2)) },
    { id: 'steam-clock', label: 'Steam Clock plaza', x: Number(layout.clock.x.toFixed(2)), z: Number(layout.clock.z.toFixed(2)) },
    { id: 'maple-tree-square-edge', label: 'Maple Tree Square edge', x: Number(layout.mapleEdge.x.toFixed(2)), z: Number(layout.mapleEdge.z.toFixed(2)) },
    { id: 'cambie-rise-continuation', label: 'Cambie rise continuation', x: Number(layout.cambie.x.toFixed(2)), z: Number(layout.cambie.z.toFixed(2)) },
  ];

  const westRoadwayCenterline = waterWestLeg.concat([{
    x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 8.4)).toFixed(2)),
    z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 8.4)).toFixed(2)),
  }]);
  const eastRoadwayCenterline = [{
    x: Number((intersectionPoint.x + (intersectionFrame.tangent.x * 8.1)).toFixed(2)),
    z: Number((intersectionPoint.z + (intersectionFrame.tangent.z * 8.1)).toFixed(2)),
  }].concat(waterEastLeg.slice(1));

  const mainStreetWest = sanitizeZonePolygon(ribbonPolygon(westRoadwayCenterline, streetHalf));
  const mainStreetEast = sanitizeZonePolygon(ribbonPolygon(eastRoadwayCenterline, streetHalf * 0.92));
  const cambieStreet = sanitizeZonePolygon(ribbonPolygon(cambieCorridor, streetHalf * 0.72));
  const intersectionRoadway = sanitizeZonePolygon(orientedRect(intersectionPoint, intersectionFrame.tangent, 17.8, streetWidth + 3.1));
  const southSidewalk = sanitizeZonePolygon(closePolygon(lineOffset(waterCenterline, sidewalkOuter * 1.02).concat(lineOffset(waterCenterline, streetHalf + 0.18).reverse())));
  const northSidewalk = sanitizeZonePolygon(closePolygon(lineOffset(waterCenterline, -(streetHalf + 0.1)).concat(lineOffset(waterCenterline, -(sidewalkOuter * 1.08)).reverse())));
  const cambieWestSidewalk = sanitizeZonePolygon(closePolygon(lineOffset(cambieCorridor, sidewalkOuter * 0.96).concat(lineOffset(cambieCorridor, streetHalf * 0.72).reverse())));
  const curbReturn = sanitizeZonePolygon(orientedRect(
    {
      x: intersectionPoint.x + (layout.plaza.normal.x * (streetHalf + (sidewalkWidth * 0.28))),
      z: intersectionPoint.z + (layout.plaza.normal.z * (streetHalf + (sidewalkWidth * 0.28))),
    },
    layout.plaza.tangent,
    8.8,
    7.6
  ));
  const plazaPad = sanitizeZonePolygon(orientedRect(
    {
      x: layout.clock.x + (layout.plaza.normal.x * 0.72) - (layout.plaza.tangent.x * 0.55),
      z: layout.clock.z + (layout.plaza.normal.z * 0.72) - (layout.plaza.tangent.z * 0.55),
    },
    layout.plaza.tangent,
    13.6,
    14.2
  ));
  const stationPlaza = sanitizeZonePolygon(orientedRect(
    {
      x: layout.station.x + (intersectionFrame.tangent.x * 2.1) - (intersectionFrame.normal.x * 1.6),
      z: layout.station.z + (intersectionFrame.tangent.z * 2.1) - (intersectionFrame.normal.z * 1.6),
    },
    intersectionFrame.tangent,
    10.8,
    9.6
  ));
  const storefrontEdge = sanitizeZonePolygon(orientedRect(
    {
      x: layout.midBlock.x - (intersectionFrame.normal.x * (streetHalf + (sidewalkWidth * 0.9))),
      z: layout.midBlock.z - (intersectionFrame.normal.z * (streetHalf + (sidewalkWidth * 0.9))),
    },
    intersectionFrame.tangent,
    16.4,
    7.6
  ));
  const alleyThreshold = sanitizeZonePolygon(orientedRect(
    {
      x: intersectionPoint.x - (intersectionFrame.tangent.x * 8.8) + (intersectionFrame.normal.x * (streetHalf + (sidewalkWidth * 0.82))),
      z: intersectionPoint.z - (intersectionFrame.tangent.z * 8.8) + (intersectionFrame.normal.z * (streetHalf + (sidewalkWidth * 0.82))),
    },
    intersectionFrame.tangent,
    7.2,
    7.8
  ));
  const transitEdge = sanitizeZonePolygon(orientedRect(
    {
      x: layout.cambie.x - (layout.plaza.normal.x * 2.2) - (layout.plaza.tangent.x * 1.8),
      z: layout.cambie.z - (layout.plaza.normal.z * 2.2) - (layout.plaza.tangent.z * 1.8),
    },
    layout.plaza.normal,
    10.4,
    6.8
  ));
  const landmarkCorner = sanitizeZonePolygon(orientedRect(
    {
      x: layout.clock.x + (layout.plaza.tangent.x * 1.4) + (layout.plaza.normal.x * 1.5),
      z: layout.clock.z + (layout.plaza.tangent.z * 1.4) + (layout.plaza.normal.z * 1.5),
    },
    layout.plaza.tangent,
    9.4,
    7.4
  ));
  const mapleTriangle = sanitizeZonePolygon(orientedRect(
    {
      x: layout.mapleEdge.x + (layout.plaza.tangent.x * 1.8) + (layout.plaza.normal.x * 1.4),
      z: layout.mapleEdge.z + (layout.plaza.tangent.z * 1.8) + (layout.plaza.normal.z * 1.4),
    },
    layout.plaza.tangent,
    9.6,
    8.2
  ));
  const walkBounds = sanitizeZonePolygon([
    { x: layout.station.x - 12, z: layout.station.z - 11 },
    { x: layout.station.x + 28, z: layout.station.z - 36 },
    { x: layout.midBlock.x + 6, z: layout.midBlock.z - 82 },
    { x: layout.clock.x + 5, z: layout.clock.z - 24 },
    { x: layout.mapleEdge.x + 22, z: layout.mapleEdge.z - 6 },
    { x: layout.mapleEdge.x + 18, z: layout.mapleEdge.z + 15 },
    { x: layout.cambie.x - 14, z: layout.cambie.z + 22 },
    { x: layout.midBlock.x - 52, z: layout.midBlock.z + 42 },
    { x: layout.station.x - 18, z: layout.station.z + 10 },
  ]);

  const frontagePattern = [6.2, 7.1, 8.4, 9.2, 6.6, 10.8, 7.4, 11.6, 8.2, 6.8, 9.8, 7.6, 12.4];
  const depthPattern = [16, 18, 15, 22, 17, 24, 16, 21, 19, 15, 23, 17, 25];
  const heightPattern = [12, 14, 13, 18, 11, 20, 15, 17, 14, 12, 19, 13, 21];
  const recessPattern = [2, 1, 2, 3, 1, 2, 2, 1, 3, 2, 1, 2, 3];
  const palettePattern = [
    { primary: '#74493c', trim: '#cfb8a2', accent: '#4f5f6f', tone: 'brickWarm' },
    { primary: '#8a857d', trim: '#d8c9b6', accent: '#57656e', tone: 'stoneMuted' },
    { primary: '#6e3f36', trim: '#caa98c', accent: '#445666', tone: 'brickWarm' },
    { primary: '#6f747c', trim: '#d0c3b3', accent: '#3f5363', tone: 'stoneMuted' },
  ];
  const facadePattern = ['gastown_heritage_row', 'cordova_commercial_transition', 'gastown_heritage_masonry', 'narrow_brick_shaft', 'steam_clock_corner'];
  const buildings = [];
  let cursor = 6;
  let i = 0;
  const primaryLength = polylineLength(waterCenterline);
  while (cursor < primaryLength - 10 && i < 26) {
    const frontage = frontagePattern[i % frontagePattern.length];
    const depth = depthPattern[i % depthPattern.length];
    const height = heightPattern[i % heightPattern.length];
    const frame = starterRouteFrame(waterCenterline, cursor);
    [-1, 1].forEach((side) => {
      const sideIndex = side < 0 ? 0 : 1;
      const palette = palettePattern[(i + sideIndex) % palettePattern.length];
      const blockPhase = cursor / primaryLength;
      const cornerMoment = Math.abs(cursor - (primaryLength * 0.9)) < 13.5 && side > 0;
      const waterfrontThreshold = blockPhase < 0.22;
      const steamClockApproach = blockPhase > 0.58 && blockPhase < 0.94;
      const postClock = blockPhase >= 0.94;
      const heroZone = blockPhase > 0.72 && blockPhase < 0.97;
      const localDepth = depth + (cornerMoment ? 5.6 : 0) + (waterfrontThreshold && side > 0 ? 2.6 : 0) + (steamClockApproach ? 2.4 : 0) + (heroZone && side < 0 ? 1.4 : 0);
      const inset = cornerMoment ? 3.6 : (steamClockApproach ? 1.55 : 1.05);
      const centerOffset = sidewalkOuter + (localDepth / 2) + inset;
      const footprintCenter = {
        x: frame.center.x + (frame.normal.x * centerOffset * side),
        z: frame.center.z + (frame.normal.z * centerOffset * side),
      };
      const localFrontage = frontage + (cornerMoment ? 4.2 : 0) + (postClock && side < 0 ? 2.4 : 0) + (heroZone && side > 0 ? 0.9 : 0);
      const footprint = makeRectFootprint(footprintCenter, frame.tangent, localFrontage, localDepth);
      const metrics = deriveFootprintMetrics(footprint);
      const id = `gastown-frontage-${i}-${side < 0 ? 'south' : 'north'}`;
      const recessedEntryCount = recessPattern[(i + sideIndex) % recessPattern.length];
      const corniceEmphasis = cornerMoment ? 0.5 : (steamClockApproach ? 0.36 : (heroZone ? 0.31 : 0.26));
      buildings.push({
        id,
        reference_name: cornerMoment ? 'Steam Clock corner anchor' : side < 0 ? 'Water Street south frontage' : 'Water Street north frontage',
        segment_style: waterfrontThreshold ? 'waterfront-threshold' : steamClockApproach ? 'steam-clock-approach' : postClock ? 'post-clock-continuation' : 'mid-corridor-heritage-rhythm',
        style_notes: cornerMoment ? 'Corner building massing is widened and wrapped to frame the Steam Clock as a true corner-plaza landmark outside the carriageway.' : heroZone ? 'Hero-zone frontage uses narrower bay spacing, deeper entries, and varied masonry tones to read closer to Water Street heritage blocks.' : 'Heritage frontage cadence is tuned around narrower Water Street carriageway proportions and more faithful storefront rhythms.',
        x: Number(metrics.x.toFixed(2)), z: Number(metrics.z.toFixed(2)), width: Number(metrics.width.toFixed(2)), depth: Number(metrics.depth.toFixed(2)), yaw: Number(metrics.yaw.toFixed(4)),
        height: Number((height + (cornerMoment ? 2 : 0) + (waterfrontThreshold && side < 0 ? 1 : 0)).toFixed(1)),
        footprint,
        footprint_local: metrics.localFootprint.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })),
        facade_profile: cornerMoment ? 'steam_clock_corner' : facadePattern[(i + sideIndex) % facadePattern.length],
        hero_fidelity: heroZone || cornerMoment ? 'hero' : steamClockApproach ? 'high' : 'standard',
        tone: palette.tone,
        roofline_type: cornerMoment ? 'wrapped_cornice' : steamClockApproach ? 'ornate_cornice' : 'flat_cornice',
        window_bay_count: Math.max(3, Math.round(localFrontage / (cornerMoment ? 3.5 : 2.6))),
        recessed_entry_count: recessedEntryCount,
        storefront_rhythm: { base_band: Number((waterfrontThreshold ? 0.16 : steamClockApproach ? 0.22 : 0.19).toFixed(2)), upper_rows: height >= 16 ? 4 : 3, bay_spacing: Number((cornerMoment ? 3.15 : heroZone ? 2.45 : 2.85).toFixed(2)), entry_depth: Number((cornerMoment ? 1.15 : heroZone ? 0.95 : 0.72).toFixed(2)), transom_band: heroZone ? 0.22 : 0.18 },
        material_palette: Object.assign({}, palette, { secondary: heroZone ? '#8d6f55' : '#70584a' }),
        cornice_emphasis: Number(corniceEmphasis.toFixed(2)),
        mass_inset: Number((cornerMoment ? 0.92 : waterfrontThreshold ? 0.95 : heroZone ? 0.94 : 0.96).toFixed(2)),
        facade_variation_seed: Number((blockPhase + (side > 0 ? 0.31 : 0.17)).toFixed(3)),
      });
    });
    cursor += frontage + (i % 3 === 0 ? 2 : 3.1);
    i += 1;
  }

  const cueBuildings = getFeatureCollectionByKind(reference.buildingCues, 'frontage_massing').flatMap((feature, index) => {
    const polygons = extractPolygons(feature);
    const ring = polygons[0] && polygons[0][0] ? polygons[0][0] : [];
    if (ring.length < 4) return [];
    const footprint = closePolygon(ring.slice(0, -1).map((coord) => projectLonLat(coord[0], coord[1], anchorOrigin)).map((point) => ({
      x: Number(point.x.toFixed(2)),
      z: Number(point.z.toFixed(2)),
    })));
    const metrics = deriveFootprintMetrics(footprint);
    const props = getProps(feature);
    return [{
      id: String(props.id || ('building-cue-' + index)),
      reference_name: props.label || 'Gastown frontage massing cue',
      segment_style: props.segment_style || 'reference-massing',
      style_notes: 'Simplified frontage massing derived from City of Vancouver building footprints 2015.',
      x: Number(metrics.x.toFixed(2)),
      z: Number(metrics.z.toFixed(2)),
      width: Number(metrics.width.toFixed(2)),
      depth: Number(metrics.depth.toFixed(2)),
      yaw: Number(metrics.yaw.toFixed(4)),
      height: Number(sanitizeNumber(Number(props.height_hint), 15).toFixed(1)),
      footprint,
      footprint_local: metrics.localFootprint.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })),
      facade_profile: 'gastown_heritage_row',
      tone: String(props.tone || 'stoneMuted'),
      roofline_type: 'flat_cornice',
      window_bay_count: Math.max(3, Math.round(metrics.width / 2.8)),
      recessed_entry_count: 1,
      storefront_rhythm: { base_band: 0.18, upper_rows: 3 },
      material_palette: { primary: '#786155', trim: '#d6c7b1', accent: '#4f5d69' },
      cornice_emphasis: 0.24,
      mass_inset: 0.96,
    }];
  });
  if (cueBuildings.length) {
    buildings.splice(0, buildings.length, ...cueBuildings);
  }

  const spawnDir = normalize({ x: centerline[1].x - centerline[0].x, z: centerline[1].z - centerline[0].z });
  const spawn = { x: Number((centerline[0].x + (spawnDir.x * 9)).toFixed(2)), y: 1.7, z: Number((centerline[0].z + (spawnDir.z * 9)).toFixed(2)), yaw: Number(Math.atan2(-spawnDir.x, -spawnDir.z).toFixed(4)) };

  const props = [
    placeStarterProp(waterCenterline, 18, -(sidewalkOuter + 0.55), 'starter-prop-threshold-box', 'newspaper_box', { scale: 1.05 }),
    placeStarterProp(waterCenterline, 24, sidewalkOuter + 0.72, 'starter-prop-threshold-utility', 'utility_box', { scale: 1.1 }),
    placeStarterProp(waterCenterline, 20, -(sidewalkOuter + 1.72), 'starter-prop-threshold-bench', 'bench', { scale: 0.92, yawOffset: Math.PI / 2 }),
    placeStarterProp(waterCenterline, 62, -(sidewalkOuter + 0.88), 'starter-prop-planter-west', 'planter', { scale: 1.15 }),
    placeStarterProp(waterCenterline, primaryLength * 0.72, sidewalkOuter + 0.95, 'starter-prop-planter-east', 'planter', { scale: 1.08 }),
    { id: 'starter-prop-bench-clock', kind: 'bench', x: Number((layout.clock.x - (layout.plaza.tangent.x * 1.8) + (layout.plaza.normal.x * 2.2)).toFixed(2)), y: 0, z: Number((layout.clock.z - (layout.plaza.tangent.z * 1.8) + (layout.plaza.normal.z * 2.2)).toFixed(2)), yaw: Number((Math.atan2(layout.plaza.tangent.x, layout.plaza.tangent.z) + (Math.PI / 2)).toFixed(4)), scale: 1.04 },
    { id: 'starter-prop-clock-box', kind: 'newspaper_box', x: Number((layout.clock.x + (layout.plaza.tangent.x * 1.6) + (layout.plaza.normal.x * 2.6)).toFixed(2)), y: 0, z: Number((layout.clock.z + (layout.plaza.tangent.z * 1.6) + (layout.plaza.normal.z * 2.6)).toFixed(2)), yaw: 0, scale: 0.98 },
    { id: 'starter-prop-station-kiosk', kind: 'newspaper_box', x: Number((layout.station.x + (intersectionFrame.tangent.x * 4.6) - (intersectionFrame.normal.x * 2.7)).toFixed(2)), y: 0, z: Number((layout.station.z + (intersectionFrame.tangent.z * 4.6) - (intersectionFrame.normal.z * 2.7)).toFixed(2)), yaw: Number(Math.atan2(intersectionFrame.tangent.x, intersectionFrame.tangent.z).toFixed(4)), scale: 1, collectible: true, collectibleKey: 'newspaper_box', collectibleLabel: 'Newspaper box', minimapIcon: 'collectible' },
    { id: 'starter-prop-maple-mural', kind: 'cardboard_box', x: Number((layout.mapleEdge.x + (layout.plaza.tangent.x * 2.1) + (layout.plaza.normal.x * 2.3)).toFixed(2)), y: 0, z: Number((layout.mapleEdge.z + (layout.plaza.tangent.z * 2.1) + (layout.plaza.normal.z * 2.3)).toFixed(2)), yaw: Number(Math.atan2(layout.plaza.tangent.x, layout.plaza.tangent.z).toFixed(4)), scale: 0.95, collectible: true, collectibleKey: 'mural', collectibleLabel: 'Painted brick panel', minimapIcon: 'mural', randomOffset: true },
    { id: 'starter-prop-clock-plaque', kind: 'utility_box', x: Number((layout.clock.x + (layout.plaza.normal.x * 1.9) + (layout.plaza.tangent.x * 0.5)).toFixed(2)), y: 0, z: Number((layout.clock.z + (layout.plaza.normal.z * 1.9) + (layout.plaza.tangent.z * 0.5)).toFixed(2)), yaw: Number(Math.atan2(layout.plaza.normal.x, layout.plaza.normal.z).toFixed(4)), scale: 0.65 },
    { id: 'starter-prop-alley-plaque', kind: 'utility_box', x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 9.1) + (intersectionFrame.normal.x * 6.6)).toFixed(2)), y: 0, z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 9.1) + (intersectionFrame.normal.z * 6.6)).toFixed(2)), yaw: Number(Math.atan2(intersectionFrame.normal.x, intersectionFrame.normal.z).toFixed(4)), scale: 1, collectible: true, collectibleKey: 'historic_plaque', collectibleLabel: 'Historic plaque', minimapIcon: 'collectible' },
    placeStarterProp(waterEastLeg, Math.max(3, polylineLength(waterEastLeg) * 0.3), sidewalkOuter * 0.88, 'starter-prop-east-newsbox', 'newspaper_box', { scale: 1.02 }),
    placeStarterProp(cambieCorridor, 18, -(sidewalkOuter * 0.82), 'starter-prop-postclock-utility', 'utility_box', { scale: 1.06 }),
    placeStarterProp(cambieCorridor, 30, sidewalkOuter * 0.75, 'starter-prop-postclock-bags', 'trash_bag', { scale: 0.96 }),
    placeStarterProp(cambieCorridor, 36, sidewalkOuter * 0.88, 'starter-prop-postclock-bench', 'bench', { scale: 1, yawOffset: Math.PI / 2 }),
    placeStarterProp(cambieCorridor, 32, -(sidewalkOuter * 0.92), 'starter-prop-postclock-planter', 'planter', { scale: 1.12 }),
  ];

  const landmarkBundle = buildStarterLandmarks(layout);
  const landmarks = landmarkBundle.landmarks;
  const heroLandmarks = landmarkBundle.heroLandmarks;
  const npcs = buildStarterNpcs(layout, streetWidth, sidewalkOuter);
  const referenceFeaturesUsed = [
    ...(reference.routeReference && Array.isArray(reference.routeReference.features) ? ['gastown-route-reference.geojson'] : []),
    ...(reference.streetContext && Array.isArray(reference.streetContext.features) ? ['gastown-street-context.geojson'] : []),
    ...(reference.landmarkReference && Array.isArray(reference.landmarkReference.features) ? ['gastown-landmark-reference.geojson'] : []),
    ...(reference.buildingCues && Array.isArray(reference.buildingCues.features) ? ['gastown-building-cues.geojson'] : []),
    ...(reference.poiReference && Array.isArray(reference.poiReference.features) ? ['gastown-poi-reference.geojson'] : []),
    ...(reference.publicStreets && Array.isArray(reference.publicStreets.features) ? ['public-streets.geojson'] : []),
    ...(reference.streetIntersections && Array.isArray(reference.streetIntersections.features) ? ['street-intersections.geojson'] : []),
    ...(reference.rightOfWayWidths && Array.isArray(reference.rightOfWayWidths.features) ? ['right-of-way-widths.geojson'] : []),
    ...(reference.streetLightingPoles && Array.isArray(reference.streetLightingPoles.features) ? ['street-lighting-poles.geojson'] : []),
    ...(reference.buildingFootprints2015 && Array.isArray(reference.buildingFootprints2015.features) ? ['building-footprints-2015.geojson'] : []),
    ...(reference.orthophotoImagery2015 && Array.isArray(reference.orthophotoImagery2015.features) ? ['orthophoto-imagery-2015.geojson'] : []),
  ];

  const existingWorld = fs.existsSync(outputPath) ? readJson(outputPath) : {};
  const existingMeta = existingWorld.meta || {};

  const lampFeatures = getFeatureCollectionByKind(reference.poiReference, 'heritage_lamp');
  const lamps = lampFeatures.length
    ? lampFeatures.map((feature, index) => {
      const point = projectFeaturePoint(feature, anchorOrigin, layout.clock);
      return { id: getProps(feature).id || ('starter-lamp-' + index), x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), height: 5.4 };
    })
    : proceduralStreetscape(waterRoute, { idPrefix: 'starter-lamp', strideMeters: 24, laneOffset: sidewalkOuter - 0.5, maxCount: 18, mapper: (point, id) => ({ id, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), height: 5.4 }) });

  const world = {
    routeId: 'gastown_water_street_working_corridor',
    meta: {
      title: 'Gastown Working Corridor (deterministic fallback)', units: 'meters', source: 'deterministic fallback with optional open reference inputs', fallbackMode: 'working-gastown-corridor', isRealCivicBuild: false,
      buildClassification: 'approximate-fallback',
      buildNotes: [
        'Working fallback corridor is active because required offline civic source files are missing.',
        'This fallback now stages Water Street as a compact exploration district with plaza, storefront edge, alley threshold, transit edge, and landmark corner micro-areas instead of a single bent ribbon corridor.',
        referenceFeaturesUsed.length ? `Optional refreshed reference inputs were present: ${referenceFeaturesUsed.join(', ')}.` : 'No refreshed reference inputs were present; deterministic default route staging was used.',
      ],
      referenceInputsUsed: referenceFeaturesUsed,
      openDataInputs: {
        publicStreets: !!(reference.publicStreets && Array.isArray(reference.publicStreets.features) && reference.publicStreets.features.length),
        streetIntersections: !!(reference.streetIntersections && Array.isArray(reference.streetIntersections.features) && reference.streetIntersections.features.length),
        rightOfWayWidths: !!(reference.rightOfWayWidths && Array.isArray(reference.rightOfWayWidths.features) && reference.rightOfWayWidths.features.length),
        streetLightingPoles: !!(reference.streetLightingPoles && Array.isArray(reference.streetLightingPoles.features) && reference.streetLightingPoles.features.length),
        buildingFootprints2015: !!(reference.buildingFootprints2015 && Array.isArray(reference.buildingFootprints2015.features) && reference.buildingFootprints2015.features.length),
        orthophotoImagery2015: !!(reference.orthophotoImagery2015 && Array.isArray(reference.orthophotoImagery2015.features) && reference.orthophotoImagery2015.features.length),
      },
      provenanceSummary: 'Approximate fallback corridor retained because the offline civic/open-data pipeline did not have all required local inputs.',
      lastBuild: existingMeta.lastBuild || new Date().toISOString(),
      artDirection: existingMeta.artDirection || DEFAULT_ART_DIRECTION,
    },
    route: { name: 'Explorable Gastown: Water Street loops', centerline, walkBounds, streetWidth, sidewalkWidth, softBoundary, hardResetDistance: 12 },
    nodes: [
      { ...centerline[0], label: 'Waterfront Station threshold' },
      { id: 'station-plaza', label: 'Station plaza', x: Number((layout.station.x + (intersectionFrame.tangent.x * 5.4) - (intersectionFrame.normal.x * 2.2)).toFixed(2)), z: Number((layout.station.z + (intersectionFrame.tangent.z * 5.4) - (intersectionFrame.normal.z * 2.2)).toFixed(2)) },
      { ...centerline[2], label: 'Water/Cordova seam' },
      { id: 'storefront-edge', label: 'Storefront edge', x: Number((layout.midBlock.x - (intersectionFrame.normal.x * (streetHalf + (sidewalkWidth * 0.88)))).toFixed(2)), z: Number((layout.midBlock.z - (intersectionFrame.normal.z * (streetHalf + (sidewalkWidth * 0.88)))).toFixed(2)) },
      { ...centerline[4], label: 'Water Street mid block' },
      { id: 'alley-threshold', label: 'Alley threshold', x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 8.8) + (intersectionFrame.normal.x * (streetHalf + (sidewalkWidth * 0.82)))).toFixed(2)), z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 8.8) + (intersectionFrame.normal.z * (streetHalf + (sidewalkWidth * 0.82)))).toFixed(2)) },
      { ...centerline[6], label: 'Steam Clock plaza' },
      { id: 'landmark-corner', label: 'Landmark corner', x: Number((layout.clock.x + (layout.plaza.tangent.x * 1.4) + (layout.plaza.normal.x * 1.5)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.tangent.z * 1.4) + (layout.plaza.normal.z * 1.5)).toFixed(2)) },
      { ...centerline[7], label: 'Maple Tree Square edge' },
      { id: 'transit-edge', label: 'Transit edge', x: Number((layout.cambie.x - (layout.plaza.normal.x * 2.2) - (layout.plaza.tangent.x * 1.8)).toFixed(2)), z: Number((layout.cambie.z - (layout.plaza.normal.z * 2.2) - (layout.plaza.tangent.z * 1.8)).toFixed(2)) },
      { ...centerline[8], label: 'Cambie rise continuation' },
      { id: 'water-cambie-intersection', label: 'Water/Cambie intersection', x: Number(intersectionPoint.x.toFixed(2)), z: Number(intersectionPoint.z.toFixed(2)) },
    ],
    navigator: {
      focusCorridor: [
        { id: 'water-street-west-leg', label: 'Water Street west leg', points: waterWestLeg.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })) },
        { id: 'station-plaza-loop', label: 'Station plaza loop', points: [
          { x: Number((layout.station.x - (intersectionFrame.tangent.x * 1.5) - (intersectionFrame.normal.x * 3.8)).toFixed(2)), z: Number((layout.station.z - (intersectionFrame.tangent.z * 1.5) - (intersectionFrame.normal.z * 3.8)).toFixed(2)) },
          { x: Number((layout.station.x + (intersectionFrame.tangent.x * 6.2) - (intersectionFrame.normal.x * 4.1)).toFixed(2)), z: Number((layout.station.z + (intersectionFrame.tangent.z * 6.2) - (intersectionFrame.normal.z * 4.1)).toFixed(2)) },
          { x: Number((layout.station.x + (intersectionFrame.tangent.x * 6.8) + (intersectionFrame.normal.x * 1.1)).toFixed(2)), z: Number((layout.station.z + (intersectionFrame.tangent.z * 6.8) + (intersectionFrame.normal.z * 1.1)).toFixed(2)) },
          { x: Number((layout.station.x - (intersectionFrame.tangent.x * 1.1) + (intersectionFrame.normal.x * 0.8)).toFixed(2)), z: Number((layout.station.z - (intersectionFrame.tangent.z * 1.1) + (intersectionFrame.normal.z * 0.8)).toFixed(2)) },
        ] },
        { id: 'storefront-pocket-loop', label: 'Storefront pocket loop', points: [
          { x: Number((layout.midBlock.x - (intersectionFrame.tangent.x * 5.4) - (intersectionFrame.normal.x * 3.8)).toFixed(2)), z: Number((layout.midBlock.z - (intersectionFrame.tangent.z * 5.4) - (intersectionFrame.normal.z * 3.8)).toFixed(2)) },
          { x: Number((layout.midBlock.x + (intersectionFrame.tangent.x * 4.8) - (intersectionFrame.normal.x * 3.9)).toFixed(2)), z: Number((layout.midBlock.z + (intersectionFrame.tangent.z * 4.8) - (intersectionFrame.normal.z * 3.9)).toFixed(2)) },
          { x: Number((layout.midBlock.x + (intersectionFrame.tangent.x * 4.6) - (intersectionFrame.normal.x * 7.4)).toFixed(2)), z: Number((layout.midBlock.z + (intersectionFrame.tangent.z * 4.6) - (intersectionFrame.normal.z * 7.4)).toFixed(2)) },
          { x: Number((layout.midBlock.x - (intersectionFrame.tangent.x * 5.1) - (intersectionFrame.normal.x * 7.1)).toFixed(2)), z: Number((layout.midBlock.z - (intersectionFrame.tangent.z * 5.1) - (intersectionFrame.normal.z * 7.1)).toFixed(2)) },
        ] },
        { id: 'alley-threshold-loop', label: 'Alley threshold loop', points: [
          { x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 11.4) + (intersectionFrame.normal.x * 2.5)).toFixed(2)), z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 11.4) + (intersectionFrame.normal.z * 2.5)).toFixed(2)) },
          { x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 7.6) + (intersectionFrame.normal.x * 4.8)).toFixed(2)), z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 7.6) + (intersectionFrame.normal.z * 4.8)).toFixed(2)) },
          { x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 9.3) + (intersectionFrame.normal.x * 8.2)).toFixed(2)), z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 9.3) + (intersectionFrame.normal.z * 8.2)).toFixed(2)) },
          { x: Number((intersectionPoint.x - (intersectionFrame.tangent.x * 12.8) + (intersectionFrame.normal.x * 5.9)).toFixed(2)), z: Number((intersectionPoint.z - (intersectionFrame.tangent.z * 12.8) + (intersectionFrame.normal.z * 5.9)).toFixed(2)) },
        ] },
        { id: 'steam-clock-plaza-loop', label: 'Steam Clock plaza', points: [
          { x: Number((layout.clock.x + (layout.plaza.normal.x * -2.2)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.normal.z * -2.2)).toFixed(2)) },
          { x: Number((layout.clock.x + (layout.plaza.tangent.x * 2.5) + (layout.plaza.normal.x * -1.3)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.tangent.z * 2.5) + (layout.plaza.normal.z * -1.3)).toFixed(2)) },
          { x: Number((layout.clock.x + (layout.plaza.tangent.x * 2.2) + (layout.plaza.normal.x * 2.1)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.tangent.z * 2.2) + (layout.plaza.normal.z * 2.1)).toFixed(2)) },
          { x: Number((layout.clock.x + (layout.plaza.normal.x * 2.3)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.normal.z * 2.3)).toFixed(2)) },
        ] },
        { id: 'water-street-east-leg', label: 'Water Street east leg', points: waterEastLeg.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })) },
        { id: 'maple-square-loop', label: 'Maple Tree Square loop', points: [
          { x: Number((layout.mapleEdge.x - (layout.plaza.tangent.x * 1.8) - (layout.plaza.normal.x * 1.7)).toFixed(2)), z: Number((layout.mapleEdge.z - (layout.plaza.tangent.z * 1.8) - (layout.plaza.normal.z * 1.7)).toFixed(2)) },
          { x: Number((layout.mapleEdge.x + (layout.plaza.tangent.x * 3.8) - (layout.plaza.normal.x * 0.8)).toFixed(2)), z: Number((layout.mapleEdge.z + (layout.plaza.tangent.z * 3.8) - (layout.plaza.normal.z * 0.8)).toFixed(2)) },
          { x: Number((layout.mapleEdge.x + (layout.plaza.tangent.x * 4.4) + (layout.plaza.normal.x * 3.2)).toFixed(2)), z: Number((layout.mapleEdge.z + (layout.plaza.tangent.z * 4.4) + (layout.plaza.normal.z * 3.2)).toFixed(2)) },
          { x: Number((layout.mapleEdge.x - (layout.plaza.tangent.x * 0.9) + (layout.plaza.normal.x * 3.8)).toFixed(2)), z: Number((layout.mapleEdge.z - (layout.plaza.tangent.z * 0.9) + (layout.plaza.normal.z * 3.8)).toFixed(2)) },
        ] },
        { id: 'cambie-crossing', label: 'Cambie crossing', points: cambieCorridor.map((point) => ({ x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) })) },
        { id: 'transit-edge-loop', label: 'Transit edge loop', points: [
          { x: Number((layout.cambie.x - (layout.plaza.normal.x * 4.2) - (layout.plaza.tangent.x * 5.1)).toFixed(2)), z: Number((layout.cambie.z - (layout.plaza.normal.z * 4.2) - (layout.plaza.tangent.z * 5.1)).toFixed(2)) },
          { x: Number((layout.cambie.x - (layout.plaza.normal.x * 0.8) - (layout.plaza.tangent.x * 4.8)).toFixed(2)), z: Number((layout.cambie.z - (layout.plaza.normal.z * 0.8) - (layout.plaza.tangent.z * 4.8)).toFixed(2)) },
          { x: Number((layout.cambie.x - (layout.plaza.normal.x * 0.5) + (layout.plaza.tangent.x * 0.9)).toFixed(2)), z: Number((layout.cambie.z - (layout.plaza.normal.z * 0.5) + (layout.plaza.tangent.z * 0.9)).toFixed(2)) },
          { x: Number((layout.cambie.x - (layout.plaza.normal.x * 4.1) + (layout.plaza.tangent.x * 1.2)).toFixed(2)), z: Number((layout.cambie.z - (layout.plaza.normal.z * 4.1) + (layout.plaza.tangent.z * 1.2)).toFixed(2)) },
        ] },
      ],
    },
    zones: {
      street: [
        { id: 'water-street-west-roadway', surface: 'road', polygon: mainStreetWest },
        { id: 'water-cambie-intersection-roadway', surface: 'road', polygon: intersectionRoadway },
        { id: 'water-street-east-roadway', surface: 'road', polygon: mainStreetEast },
        { id: 'cambie-street-crossing', surface: 'road', polygon: cambieStreet },
      ],
      sidewalk: [
        { id: 'water-street-south-sidewalk', side: 'south', polygon: southSidewalk },
        { id: 'water-street-north-sidewalk', side: 'north', polygon: northSidewalk },
        { id: 'waterfront-station-plaza', side: 'plaza', polygon: stationPlaza },
        { id: 'water-street-storefront-pocket', side: 'storefront', polygon: storefrontEdge },
        { id: 'water-street-alley-threshold', side: 'alley', polygon: alleyThreshold },
        { id: 'cambie-west-sidewalk', side: 'west', polygon: cambieWestSidewalk },
        { id: 'cambie-transit-edge', side: 'transit', polygon: transitEdge },
        { id: 'water-cambie-curb-return', side: 'corner', polygon: curbReturn },
        { id: 'steam-clock-plaza-sidewalk', side: 'plaza', polygon: plazaPad },
        { id: 'steam-clock-landmark-corner', side: 'corner', polygon: landmarkCorner },
        { id: 'maple-tree-square-pocket', side: 'plaza', polygon: mapleTriangle },
      ],
    },
    buildings,
    hero_landmarks: heroLandmarks,
    landmarks,
    props,
    npcs,
    exploration: {
      publicGoal: 'I’m exploring Gastown.',
      fallbackPrompt: 'Wander the connected plazas, storefront edges, alley mouths, and landmark corners to build your own short loop.',
    },
    microAreas: [
      { id: 'station-plaza', label: 'Station plaza', identity: 'transit edge', nodeId: 'station-plaza', polygon: stationPlaza, stopReasons: ['Watch people spill out from Waterfront Station.', 'Reorient before committing to Water Street.'], returnReasons: ['It works as a reliable reset point.', 'The wider pad gives you a clear view back into Gastown.'] },
      { id: 'storefront-edge', label: 'Storefront edge', identity: 'storefront edge', nodeId: 'storefront-edge', polygon: storefrontEdge, stopReasons: ['Window-shop the heritage frontage rhythm.', 'Use the pocket as a pause between the station and the clock.'], returnReasons: ['It makes a good midway meeting point.', 'The tighter edge reveals the street from a different angle on the way back.'] },
      { id: 'alley-threshold', label: 'Alley threshold', identity: 'alley threshold', nodeId: 'alley-threshold', polygon: alleyThreshold, stopReasons: ['Peek into the service lane without leaving the public edge.', 'Listen for the quieter pocket off the main block.'], returnReasons: ['It turns the approach into a loop instead of a one-way run.', 'The threshold frames the Steam Clock corner from the side.'] },
      { id: 'steam-clock-plaza', label: 'Steam Clock plaza', identity: 'plaza', nodeId: 'steam-clock', polygon: plazaPad, stopReasons: ['Pause for the clock, seating, and busker activity.', 'Use the open pad to decide where to head next.'], returnReasons: ['The plaza is the natural social hub.', 'It links back out to Water Street and Maple Tree Square.'] },
      { id: 'landmark-corner', label: 'Landmark corner', identity: 'landmark corner', nodeId: 'landmark-corner', polygon: landmarkCorner, stopReasons: ['Take in the clock and corner facades together.', 'Catch the diagonal view into Maple Tree Square.'], returnReasons: ['It is the clearest landmark rendezvous point.', 'The corner keeps changing depending on approach direction.'] },
      { id: 'maple-tree-square-pocket', label: 'Maple Tree Square pocket', identity: 'plaza edge', nodeId: 'maple-tree-square-edge', polygon: mapleTriangle, stopReasons: ['Step to the square edge for the crossroads feel.', 'Use the pocket as the eastern half of the loop.'], returnReasons: ['It rewards a second pass after circling the clock.', 'The square reads differently when you approach from Cambie.'] },
      { id: 'transit-edge', label: 'Transit edge', identity: 'transit edge', nodeId: 'transit-edge', polygon: transitEdge, stopReasons: ['Look uphill toward the Cambie continuation.', 'Feel the district open toward the station/transit seam.'], returnReasons: ['It gives the loop a practical edge instead of a dead end.', 'The shift in grade changes your sense of the block.'] },
    ],
    streetscape: {
      lamps,
      trees: proceduralStreetscape(waterRoute, { idPrefix: 'starter-tree', strideMeters: 38, laneOffset: sidewalkOuter + 2.3, maxCount: 10, mapper: (point, id) => ({ id, x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)), radius: 1.4 }) }),
      bollards: [
        { id: 'clock-bollard-a', x: Number((layout.clock.x + (layout.plaza.normal.x * -2.6)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.normal.z * -2.6)).toFixed(2)) },
        { id: 'clock-bollard-b', x: Number((layout.clock.x + (layout.plaza.tangent.x * 2.2) + (layout.plaza.normal.x * -2.4)).toFixed(2)), z: Number((layout.clock.z + (layout.plaza.tangent.z * 2.2) + (layout.plaza.normal.z * -2.4)).toFixed(2)), chain_to: 'clock-bollard-a' },
      ],
      surfaceBands: buildStarterSurfaceBands(centerline, streetWidth, routeLength),
      heroViewCorridors: [
        { id: 'steam-clock-west-sightline', from: 'water-street-mid-block', to: 'steam-clock', emphasis: 'hero' },
        { id: 'steam-clock-east-sightline', from: 'maple-tree-square-edge', to: 'steam-clock', emphasis: 'hero' }
      ],
    },
    spawn,
    bounds: { floorY: 0, edgeMessage: 'Stayed inside the explorable Gastown district.', resetMessage: 'Returned to the explorable Gastown district.' },
  };

  const plazaZone = world.zones.sidewalk.find((zone) => zone.id === 'steam-clock-plaza-sidewalk');
  if (!polygonContainsPoint(layout.clock, plazaZone.polygon)) {
    throw new Error('Steam Clock fallback anchor must remain inside the corner plaza sidewalk zone.');
  }
  if (world.zones.street.some((zone) => polygonContainsPoint(layout.clock, zone.polygon))) {
    throw new Error('Steam Clock fallback anchor must remain outside street travel lanes.');
  }

  fs.writeFileSync(outputPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  return { generated: true, outputPath, routeLength, usedStarterFallback: true };
}

function buildStarterSurfaceBands(centerline, streetWidth, routeLength) {
  const bands = [];
  centerline.forEach((point, index) => {
    const next = centerline[index + 1] || centerline[index - 1];
    if (!next) return;
    const heading = Math.atan2(next.x - point.x, next.z - point.z);
    const length = Math.max(9.2, Math.min(18.5, distance(point, next) * 0.98 || 10));
    const progress = routeLength > 0 ? index / Math.max(1, centerline.length - 1) : 0;
    const edgeOffset = streetWidth * 0.41;
    const heroZone = progress > 0.62;
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.98).toFixed(2)), length: Number((length * 1.06).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: 0, offset_z: 0, tone: heroZone ? 'asphalt_patchwork' : 'road_base_dark', opacity: heroZone ? 0.3 : (progress > 0.55 ? 0.24 : 0.2), elevation: 0.012 });
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.28).toFixed(2)), length: Number((length * 0.86).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: 0, offset_z: 0, tone: heroZone ? 'tram_polish' : 'wheel_track', opacity: progress < 0.2 ? 0.1 : 0.18, elevation: 0.016 });
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.11).toFixed(2)), length: Number((length * 1.04).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number(edgeOffset.toFixed(2)), offset_z: 0, tone: 'curb_grime', opacity: 0.2, elevation: 0.024 });
    bands.push({ segment_id: point.id, width: Number((streetWidth * 0.11).toFixed(2)), length: Number((length * 1.04).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number((-edgeOffset).toFixed(2)), offset_z: 0, tone: 'curb_grime', opacity: 0.2, elevation: 0.024 });
    if (heroZone) {
      bands.push({ segment_id: point.id, width: Number((streetWidth * 0.62).toFixed(2)), length: Number((length * 0.36).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: 0, offset_z: 0, tone: 'setts_patch', opacity: 0.18, elevation: 0.02 });
    }
    if (point.id === 'steam-clock' || point.id === 'steam-clock-approach') {
      bands.push({ segment_id: point.id, width: Number((streetWidth * 0.82).toFixed(2)), length: Number(Math.max(7.2, length * 0.54).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: 0, offset_z: 0, tone: 'intersection_pavers', opacity: 0.28, elevation: 0.02 });
      bands.push({ segment_id: point.id, width: Number((streetWidth * 0.42).toFixed(2)), length: Number(Math.max(5.8, length * 0.28).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: 0, offset_z: 0, tone: 'cobble_break', opacity: 0.2, elevation: 0.022 });
      bands.push({ segment_id: point.id, width: Number((streetWidth * 0.26).toFixed(2)), length: Number(Math.max(4.8, length * 0.18).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number((streetWidth * 0.18).toFixed(2)), offset_z: 0, tone: 'service_wear', opacity: 0.16, elevation: 0.023 });
      bands.push({ segment_id: point.id, width: Number((streetWidth * 0.26).toFixed(2)), length: Number(Math.max(4.8, length * 0.18).toFixed(2)), yaw: Number(heading.toFixed(4)), offset_x: Number((-streetWidth * 0.18).toFixed(2)), offset_z: 0, tone: 'service_wear', opacity: 0.16, elevation: 0.023 });
    }
  });
  return bands;
}


function buildWorld(options = {}) {
  const root = options.root || path.resolve(__dirname, '..');
  const outputPath = options.outputPath || path.join(root, 'assets', 'world', 'gastown-water-street.json');
  const routeAnchorsPath = path.join(root, 'data', 'reference', 'route-anchors.json');
  const streetsPath = path.join(root, 'data', 'cov', 'public-streets.geojson');
  const buildingsPath = firstExistingPath([path.join(root, 'data', 'cov', 'building-footprints-2015.geojson'), path.join(root, 'data', 'cov', 'building-footprints.geojson')]);
  const polesPath = path.join(root, 'data', 'cov', 'street-lighting-poles.geojson');
  const treesPath = path.join(root, 'data', 'cov', 'public-trees.geojson');
  const heritagePath = path.join(root, 'data', 'cov', 'heritage-sites.geojson');

  if (!fs.existsSync(routeAnchorsPath) || !fs.existsSync(streetsPath) || !fs.existsSync(buildingsPath)) {
    return makeStarterWorld(outputPath, { root, reference: buildStarterReferenceBundle(root) });
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
      buildClassification: 'offline-civic-build',
      lastBuild: existingMeta.lastBuild || new Date().toISOString(),
      importManifest: existingMeta.importManifest || {
        inputs: {
          cityOfVancouver: {
            streetCenterlines: 'data/cov/public-streets.geojson',
            buildingFootprints: 'data/cov/building-footprints-2015.geojson (or legacy data/cov/building-footprints.geojson)',
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
      referenceInputsUsed: [
        'public-streets.geojson',
        ...(fs.existsSync(path.join(root, 'data', 'cov', 'street-intersections.geojson')) ? ['street-intersections.geojson'] : []),
        ...(fs.existsSync(path.join(root, 'data', 'cov', 'right-of-way-widths.geojson')) ? ['right-of-way-widths.geojson'] : []),
        ...(poles ? ['street-lighting-poles.geojson'] : []),
        ...((buildingsPath || '').includes('building-footprints-2015.geojson') ? ['building-footprints-2015.geojson'] : ['building-footprints.geojson']),
        ...(fs.existsSync(path.join(root, 'data', 'cov', 'orthophoto-imagery-2015.geojson')) ? ['orthophoto-imagery-2015.geojson'] : []),
      ],
      openDataInputs: {
        publicStreets: true,
        streetIntersections: fs.existsSync(path.join(root, 'data', 'cov', 'street-intersections.geojson')),
        rightOfWayWidths: fs.existsSync(path.join(root, 'data', 'cov', 'right-of-way-widths.geojson')),
        streetLightingPoles: !!(poles && Array.isArray(poles.features) && poles.features.length),
        buildingFootprints2015: (buildingsPath || '').includes('building-footprints-2015.geojson'),
        orthophotoImagery2015: fs.existsSync(path.join(root, 'data', 'cov', 'orthophoto-imagery-2015.geojson')),
      },
      provenanceSummary: 'Offline civic-data build generated from local source exports and normalized for the runtime simulator.',
      artDirection: existingMeta.artDirection || DEFAULT_ART_DIRECTION,
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
    exploration: existingWorld.exploration || {
      publicGoal: 'I’m exploring Gastown.',
      fallbackPrompt: 'Use the connected sidewalks and corners as a small exploration district rather than a single route segment.',
    },
    microAreas: Array.isArray(existingWorld.microAreas) ? existingWorld.microAreas : [],
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
