'use strict';

/**
 * Build a geographically coherent Water Street corridor from the City of
 * Vancouver's cached, named public-streets centerlines and 2015 building
 * footprints. All coordinates are local metres: +X east, +Z north.
 *
 * THIS IS NOT A SURVEY or turn-by-turn routing service. Curb/sidewalk width,
 * building heights and the station facade detail remain authored estimates.
 * No invented 500-metre "stitched" route is allowed.
 */
const R = 6371008.8;
const rad = Math.PI / 180;
function project(coordinates, origin) {
  return {
    x: (coordinates[0] - origin.lon) * rad * R * Math.cos(origin.lat * rad),
    z: (coordinates[1] - origin.lat) * rad * R,
  };
}
function pt(point) {
  return { x: Number(point.x.toFixed(2)), z: Number(point.z.toFixed(2)) };
}
function distance(a, b) { return Math.hypot(a.x - b.x, a.z - b.z); }
function segmentDistance(p, a, b) {
  const dx = b.x - a.x, dz = b.z - a.z;
  const q = dx * dx + dz * dz || 1;
  const t = Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.z - a.z) * dz) / q));
  return Math.hypot(p.x - a.x - t * dx, p.z - a.z - t * dz);
}
function toLineDistance(point, line) {
  let best = Infinity;
  for (let i = 1; i < line.length; i += 1) {
    best = Math.min(best, segmentDistance(point, line[i - 1], line[i]));
  }
  return best;
}
function along(a, b, t) {
  return { x: a.x + (b.x - a.x) * t, z: a.z + (b.z - a.z) * t };
}
function midpoint(a, b) { return along(a, b, 0.5); }
function polyLength(points) {
  return points.slice(1).reduce((sum, p, i) => sum + distance(points[i], p), 0);
}
function sampleLine(points, metres) {
  let left = metres;
  for (let i = 1; i < points.length; i += 1) {
    const len = distance(points[i - 1], points[i]);
    if (left <= len || i === points.length - 1) {
      return along(points[i - 1], points[i], Math.max(0, Math.min(1, left / (len || 1))));
    }
    left -= len;
  }
  return points[points.length - 1];
}
function offsets(points, offset) {
  return points.map((p, i) => {
    const a = points[Math.max(0, i - 1)];
    const b = points[Math.min(points.length - 1, i + 1)];
    const len = distance(a, b) || 1;
    const leftX = -(b.z - a.z) / len;
    const leftZ = (b.x - a.x) / len;
    return pt({ x: p.x + leftX * offset, z: p.z + leftZ * offset });
  });
}
function strip(points, outer, inner) {
  return offsets(points, outer).concat(offsets(points, inner).reverse());
}
function sqNear(p, x, z, size) {
  return [
    { x: p.x - size, z: p.z - size },
    { x: p.x + size, z: p.z - size },
    { x: p.x + size, z: p.z + size },
    { x: p.x - size, z: p.z + size },
  ];
}
function getStreet(streets, name) {
  const records = (streets.features || []).filter(f => String((f.properties || {}).hblock || '').toUpperCase() === name);
  if (!records.length) throw Error('Missing official street centerline: ' + name);
  return records;
}
function appendConnected(path, segment, tolerance) {
  const tail = path[path.length - 1];
  const firstGap = distance(tail, segment[0]), lastGap = distance(tail, segment[segment.length - 1]);
  const oriented = lastGap < firstGap ? segment.slice().reverse() : segment;
  if (Math.min(firstGap, lastGap) > tolerance) throw Error('Civic street centerlines do not connect within ' + tolerance + 'm');
  return path.concat(oriented.slice(1));
}
function officialRoute(streets, origin) {
  const convert = f => f.geometry.coordinates.map(c => project(c, origin));
  const west = convert(getStreet(streets, '600 W CORDOVA ST')[0]);
  const westOrdered = west[0].x < west[west.length - 1].x ? west : west.slice().reverse();
  const start = along(westOrdered[0], westOrdered[1], 0.70);
  let path = [start].concat(westOrdered.slice(1));
  path = appendConnected(path, convert(getStreet(streets, '500 W CORDOVA ST')[0]), 1.8);
  const unjoined = getStreet(streets, '200-300 WATER ST').map(convert);
  while (unjoined.length) {
    const tail = path[path.length - 1];
    let best = 0, bestGap = Infinity;
    unjoined.forEach((line, index) => {
      const gap = Math.min(distance(tail, line[0]), distance(tail, line[line.length - 1]));
      if (gap < bestGap) { best = index; bestGap = gap; }
    });
    if (bestGap > 1.8) throw Error('Water Street segments do not form a contiguous connected path');
    path = appendConnected(path, unjoined.splice(best, 1)[0], 1.8);
  }
  const length = polyLength(path);
  if (length < 225 || length > 390) {
    throw Error('Official station-to-clock corridor out of expected geographic bounds: ' + length.toFixed(1) + 'm');
  }
  return path;
}
function geometryPoints(feature, origin) {
  const geom = feature && feature.geometry;
  if (!geom || (geom.type !== 'Polygon' && geom.type !== 'MultiPolygon')) return [];
  const ring = geom.type === 'Polygon' ? geom.coordinates[0] : geom.coordinates[0][0];
  if (!Array.isArray(ring)) return [];
  const points = ring.map(c => project(c, origin));
  if (points.length > 1 && distance(points[0], points[points.length - 1]) < 0.01) points.pop();
  return points;
}
function polygonArea(points) {
  return Math.abs(points.reduce((a, p, i) => {
    const n = points[(i + 1) % points.length];
    return a + p.x * n.z - n.x * p.z;
  }, 0) * 0.5);
}
function nearestStationFootprint(features, origin, route) {
  // Stable City of Vancouver 2015 object_id for the station's Cordova-facing
  // footprint section in our cached extract, not a generated feature index.
  const frontage = features.find(f => Number((f.properties || {}).object_id) === 6563395);
  if (frontage) {
    const points = geometryPoints(frontage, origin);
    if (points.length >= 4 && points.some(p => toLineDistance(p, route) < 36)) {
      return { feature: frontage, points };
    }
  }
  const candidates = features
    .map(f => ({ feature: f, points: geometryPoints(f, origin) }))
    .filter(c => c.points.length >= 4 && polygonArea(c.points) >= 1800)
    .map(c => ({
      ...c,
      closest: Math.min(...c.points.map(p => toLineDistance(p, route))),
      nearby: c.points.some(p => distance(p, { x: -20, z: -20 }) < 46),
    }))
    .filter(c => c.nearby && c.closest < 40)
    .sort((a, b) => polygonArea(b.points) - polygonArea(a.points));
  if (!candidates.length) throw Error('No identifiable civic building footprint around Waterfront Station');
  return candidates[0];
}
function stationFacade(frontage, route) {
  const points = frontage.points.filter(p => toLineDistance(p, route) < 30);
  const center = points.length
    ? points.reduce((a, p) => ({ x: a.x + p.x / points.length, z: a.z + p.z / points.length }), { x: 0, z: 0 })
    : { x: -25, z: -16 };
  // W Cordova runs SE. Building's Cordova-facing facade looks SW.
  const dx = route[1].x - route[0].x, dz = route[1].z - route[0].z;
  const tangent = Math.atan2(dz, dx);
  const yaw = tangent + Math.PI; // local facade +X runs NW; local +Z faces the roadway SW
  return {
    id: 'waterfront-station',
    x: Number(center.x.toFixed(2)),
    z: Number(center.z.toFixed(2)),
    yaw: Number(yaw.toFixed(5)),
    sourceFootprintId: String(frontage.feature.id || (frontage.feature.properties || {}).object_id),
    provenance: 'City of Vancouver 2015 building outline / approximate street-facing facade anchor',
    address: '601 West Cordova Street'
  };
}
function footprintAsBuilding(feature, points, route) {
  const center = points.reduce((a, p) => ({ x: a.x + p.x / points.length, z: a.z + p.z / points.length }), { x: 0, z: 0 });
  let longest = { d: 0, dx: 1, dz: 0 };
  for (let i = 0; i < points.length; i += 1) {
    const a = points[i], b = points[(i + 1) % points.length];
    const d = distance(a, b);
    if (d > longest.d) longest = { d, dx: b.x - a.x, dz: b.z - a.z };
  }
  const yaw = Math.atan2(longest.dz, longest.dx), cos = Math.cos(yaw), sin = Math.sin(yaw);
  const local = points.map(p => ({ x: (p.x - center.x) * cos + (p.z - center.z) * sin, z: -(p.x - center.x) * sin + (p.z - center.z) * cos }));
  const w = Math.max(...local.map(p => p.x)) - Math.min(...local.map(p => p.x));
  const d = Math.max(...local.map(p => p.z)) - Math.min(...local.map(p => p.z));
  const rawId = String(feature.id || (feature.properties || {}).object_id);
  const seed = rawId.split('').reduce((a, c) => a * 33 + c.charCodeAt(0) >>> 0, 5381);
  const near = toLineDistance(center, route);
  return {
    id: rawId, x: +center.x.toFixed(2), z: +center.z.toFixed(2),
    yaw: +yaw.toFixed(5), width: +w.toFixed(2), depth: +d.toFixed(2),
    footprint: points.map(pt), footprint_local: local.map(pt),
    height: +(11 + (seed % 9)).toFixed(2), // representative only, no height source
    facade_profile: 'gastown_heritage_masonry', hero_fidelity: near < 20 ? 'major' : 'standard',
    tone: seed % 3 ? 'brickWarm' : 'stoneMuted',
    window_bay_count: 3 + seed % 5, roofline_type: 'flat_cornice',
    recessed_entry_count: 1, awning_presence: true,
    reference_name: 'Civic building outline ' + rawId,
    source: 'City of Vancouver 2015 building footprints',
  };
}
function thinPoints(geo, origin, route, maxDistance, stride, limit, make) {
  if (!geo || !Array.isArray(geo.features)) return [];
  const selected = [];
  for (const feature of geo.features) {
    const geom = feature.geometry;
    if (!geom || geom.type !== 'Point' || !Array.isArray(geom.coordinates)) continue;
    const p = project(geom.coordinates, origin);
    if (toLineDistance(p, route) > maxDistance) continue;
    if (selected.some(q => distance(q, p) < stride)) continue;
    selected.push(p);
    if (selected.length >= limit) break;
  }
  return selected.map((p, i) => make(pt(p), i));
}
function rebuild(world, streets, footprints, anchors, poles, trees) {
  if (!world || !streets || !footprints || !anchors || !anchors.origin || !anchors.dest) {
    throw Error('Missing required cached civic data or route anchors');
  }
  const origin = anchors.origin;
  const route = officialRoute(streets, origin);
  const routeLength = polyLength(route);
  const clock = pt(project([anchors.dest.lon, anchors.dest.lat], origin));
  if (distance(clock, route[route.length - 1]) > 15) throw Error('Steam Clock geolocation does not match the Water/Cambie centerline');
  const stationBuilding = nearestStationFootprint(footprints.features || [], origin, route);
  const station = stationFacade(stationBuilding, route);
  const samples = Math.ceil(routeLength / 19);
  const centerline = Array.from({ length: samples + 1 }, (_, i) => ({
    ...pt(sampleLine(route, routeLength * i / samples)),
    id: i === 0 ? 'station-threshold' : (i === samples ? 'steam-clock-approach' : 'civic-beat-' + i),
    label: i === 0 ? 'Waterfront Station / West Cordova' : (i === samples ? 'Water & Cambie / Steam Clock' : 'Gastown walk ' + i),
  }));
  // Opposite-side sidewalk creates a view across W Cordova to the station.
  const tangent = { x: route[1].x - route[0].x, z: route[1].z - route[0].z };
  const len = Math.hypot(tangent.x, tangent.z) || 1;
  const spawn = {
    ...world.spawn,
    ...pt({ x: route[0].x + tangent.z / len * 8.2, z: route[0].z - tangent.x / len * 8.2 }),
    y: 1.7, yaw: -2.5, pitch: 0.06,
  };
  const streetHalf = 5.4, sidewalkOuter = 10.2, walkOuter = 15.2;
  const zones = {
    street: [{ id: 'cordova-water-roadway', surface: 'road', polygon: strip(route, streetHalf, -streetHalf) }],
    sidewalk: [
      { id: 'cordovawater-north-sidewalk', side: 'left', polygon: strip(route, sidewalkOuter, streetHalf + 0.05) },
      { id: 'cordovawater-south-sidewalk', side: 'right', polygon: strip(route, -streetHalf - 0.05, -sidewalkOuter) },
    ],
  };
  const entry = pt(sampleLine(route, 90));
  const bend = pt(sampleLine(route, 148));
  const mid = pt(sampleLine(route, routeLength * 0.73));
  const nearStationBuildings = [];
  for (const feature of footprints.features || []) {
    if (feature === stationBuilding.feature || [6563395, 6562725].includes(Number((feature.properties || {}).object_id))) continue;
    const pts = geometryPoints(feature, origin);
    if (pts.length < 3) continue;
    const near = Math.min(...pts.map(p => toLineDistance(p, route)));
    if (near > 51 || polygonArea(pts) < 22) continue;
    nearStationBuildings.push({ near, building: footprintAsBuilding(feature, pts, route) });
  }
  nearStationBuildings.sort((a, b) => a.near - b.near);
  const buildings = nearStationBuildings.slice(0, 75).map(x => x.building);
  const guidePoint = pt({ x: station.x - 11, z: station.z - 8 });
  const clockSidewalk = clock;
  const newNpcs = [
    { id: 'station-local-guide', role: 'guide', idleSpot: guidePoint, behavior: 'guide_pace', dialogId: 'guide_intro', interactRadius: 2.6 },
    { id: 'water-street-walker', role: 'pedestrian', behavior: 'route_walk', dialogId: 'pedestrian_route_tip', interactRadius: 2.2,
      patrol: [pt(sampleLine(route, 115)), pt(sampleLine(route, 175)), pt(sampleLine(route, 215))] },
    { id: 'steamclock-photo-visitor', role: 'photographer', behavior: 'photo_idle', dialogId: 'tourist_clock_photo',
      idleSpot: pt({ x: clock.x + 2.7, z: clock.z + 3 }), heldProp: 'camera', interactRadius: 2.2 },
    { id: 'steamclock-tourist', role: 'tourist', behavior: 'tourist_pause', dialogId: 'tourist_clock_stop',
      idleSpot: pt({ x: clock.x - 2.8, z: clock.z + 2.5 }), patrol: [
        pt({ x: clock.x - 3.4, z: clock.z + 2.3 }), pt({ x: clock.x - 2, z: clock.z + 4 })], interactRadius: 2.2 },
  ];
  const landmarks = [
    { id: 'waterfront-station-threshold', label: 'Waterfront Station / 601 W Cordova', kind: 'district_gate', x: station.x, z: station.z, y: 0,
      cue: '1914 CPR station entrance, across West Cordova.' },
    { id: 'water-street-transition', label: 'Water Street bend', kind: 'street_pivot', ...entry, y: 0,
      cue: 'West Cordova meets the diagonal approach to Water Street.' },
    { id: 'water-street-mid-block', label: 'Water Street storefronts', kind: 'heritage_frontage', ...mid, y: 0,
      cue: 'Heritage storefronts along Water Street.' },
    { id: 'steam-clock', label: 'Gastown Steam Clock / Water & Cambie', kind: 'clock', ...clock, y: 0, radius: 4,
      cue: 'Steam Clock, northwest corner of Water and Cambie.' },
  ];
  return {
    ...world,
    routeId: 'gastown_civic_route_2026_11_07',
    meta: {
      ...world.meta,
      title: 'Waterfront Station → Water Street → Steam Clock / civic-aligned',
      source: 'City of Vancouver public streets and 2015 approximate building footprints',
      isRealCivicBuild: true,
      buildClassification: 'offline-civic-build',
      runtimeFallbackActive: false,
      fallbackMode: null,
      provenanceSummary: 'Aligned to the connected 600/500 West Cordova and 200–300 Water public street centerlines; buildings from approximate 2015 city footprints.',
      referenceInputsUsed: ['data/cov/public-streets.geojson', 'data/cov/building-footprints.geojson',
        'data/cov/street-lighting-poles.geojson', 'data/cov/public-trees.geojson',
        'data/reference/route-anchors.json'],
      buildNotes: [
        'Street centerlines are anchored to the City of Vancouver public-streets dataset.',
        'Station building envelope uses the City 2015 approximate footprint; architectural detailing is authored and approximate.',
        'Sidewalk widths, building heights, door positions and NPC movement are visual approximations, not survey measurements.',
        'No live mapping API is called during gameplay.',
      ],
      gis: { crs: 'local metres, +x east/+z north (WGS84 equirectangular tangent at station anchor)',
        origin, routeDistanceMeters: Number(routeLength.toFixed(1)), sourceFootprintId: station.sourceFootprintId,
        streetHblocks: ['600 W CORDOVA ST', '500 W CORDOVA ST', '200-300 WATER ST'],
        sourceVintage: { buildings: 2015, streets: 'cached 2026 snapshot' } },
    },
    route: { ...world.route, name: 'Waterfront Station → W Cordova → Water Street → Steam Clock', centerline,
      walkBounds: strip(route, walkOuter, -walkOuter), streetWidth: streetHalf * 2,
      sidewalkWidth: sidewalkOuter - streetHalf, softBoundary: 5.0, hardResetDistance: 22 },
    nodes: [centerline[0], centerline[Math.floor(centerline.length * 0.32)], centerline[Math.floor(centerline.length * 0.72)], centerline[centerline.length - 1]],
    navigator: { focusCorridor: [{ id: 'civic-cordova-water', label: 'Cordova / Water Street', points: centerline.map(p => ({ x:p.x,z:p.z })) }] },
    zones, spawn, station,
    hero_landmarks: [{ id: 'steam-clock-hero', label: 'Gastown Steam Clock', ...clock, y: 0,
      yaw: -1.4, ground_emphasis_radius: 5, plaza: { radius: 5.5, depth: 6 } }],
    landmarks, buildings, npcs: newNpcs, props: [], microAreas: [
      { id: 'station-plaza', label: 'Waterfront Station frontage', identity: 'West Cordova transit entrance',
        nodeId: 'station-threshold', polygon: sqNear(spawn, spawn.x, spawn.z, 18),
        stopReasons: ['See the original CPR facade and station entrance.'], returnReasons: ['Start of the Gastown walk.'] },
      { id: 'steam-clock-corner', label: 'Steam Clock corner', identity: 'Water and Cambie',
        nodeId: 'steam-clock-approach', polygon: sqNear(clock, clock.x, clock.z, 11),
        stopReasons: ['Hear the Steam Clock chime.'], returnReasons: ['The Water Street landmark.'] },
    ],
    streetscape: {
      ...(world.streetscape || {}),
      lamps: thinPoints(poles, origin, route, 17, 10, 36, (p,i) => ({ id: 'civic-lamp-'+i, ...p, height: 5.6 })),
      trees: thinPoints(trees, origin, route, 19, 9, 35, (p,i) => ({ id: 'civic-tree-'+i, ...p, radius: 1.3 })),
      bollards: [], surfaceBands: [], streetFurniture: [], signs: [],
      heroViewCorridors: [{ id: 'station-to-clock', from: 'station-threshold', to: 'steam-clock', emphasis: 'hero' }],
    },
  };
}
module.exports = { project, officialRoute, rebuild, polyLength, distance, toLineDistance };
