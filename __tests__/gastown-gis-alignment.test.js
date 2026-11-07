const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { rebuild, officialRoute, polyLength, project, distance } = require('../scripts/rebuild-gastown-civic-world');

const root = path.resolve(__dirname, '..');
const json = relative => JSON.parse(fs.readFileSync(path.join(root, relative), 'utf8'));
const streets = json('data/cov/public-streets.geojson');
const buildings = json('data/cov/building-footprints.geojson');
const poles = json('data/cov/street-lighting-poles.geojson');
const trees = json('data/cov/public-trees.geojson');
const anchors = json('data/reference/route-anchors.json');
const world = json('assets/world/gastown-water-street.json');
function polygonContains(point, polygon) {
  let inside = false;
  for (let i=0,j=polygon.length-1;i<polygon.length;j=i++) {
    const a=polygon[i],b=polygon[j];
    if (((a.z>point.z)!==(b.z>point.z))
      && (point.x<(((b.x-a.x)*(point.z-a.z))/((b.z-a.z)||0.00001))+a.x)) inside=!inside;
  }
  return inside;
}
test('real 600/500 Cordova and 200-300 Water road pieces connect and end near the Steam Clock', () => {
  const route = officialRoute(streets, anchors.origin);
  const length = polyLength(route);
  assert.ok(length > 275 && length < 340, 'GIS route ' + length.toFixed(1) + 'm must not be the old 550m stitched route');
  const steamClock = project([anchors.dest.lon, anchors.dest.lat], anchors.origin);
  assert.ok(distance(route[route.length-1], steamClock) < 15, 'Water Street terminates by Water/Cambie');
  assert.ok(route.every(pt => Number.isFinite(pt.x) && Number.isFinite(pt.z)));
});

test('committed world is deterministic and keeps City GIS attribution', () => {
  const regenerated = rebuild(world, streets, buildings, anchors, poles, trees);
  assert.deepEqual(regenerated, world, 'built world must exactly match the committed JSON');
  assert.deepEqual(world.meta.gis.streetHblocks, ['600 W CORDOVA ST', '500 W CORDOVA ST', '200-300 WATER ST']);
  assert.equal(world.meta.gis.sceneAxisConvention, '+X east / +Z south (right-handed Three.js)');
  assert.equal(world.meta.source.includes('2015'), true);
  assert.equal(world.meta.fallbackMode, null);
  assert.equal(world.routeId, 'gastown_civic_route_2026_11_07');
  assert.ok(world.buildings.length >= 15);
  assert.ok(world.buildings.every(b => b.id !== world.station.sourceFootprintId), 'do not render a generic box over the hero facade');
});

test('spawn is on a walkable sidewalk and outside all building footprints', () => {
  assert.equal(polygonContains(world.spawn, world.route.walkBounds), true);
  assert.equal(world.zones.sidewalk.some(z => polygonContains(world.spawn, z.polygon)), true);
  assert.equal(world.buildings.some(b => polygonContains(world.spawn, b.footprint)), false);
  assert.ok(distance(world.spawn, world.station) > 22 && distance(world.spawn, world.station) < 43);
  assert.ok(world.landmarks.some(l => l.id === 'waterfront-station-threshold'));
  assert.ok(world.hero_landmarks.some(l => l.id === 'steam-clock-hero'));
  const clock = world.landmarks.find(l => l.id === 'steam-clock');
  assert.ok(clock);
  const civicClock = project([anchors.dest.lon, anchors.dest.lat], anchors.origin);
  assert.ok(distance(clock, { x: civicClock.x, z: -civicClock.z }) < 0.8,
    'render-space Steam Clock must be projected with true north on -Z');
  assert.ok(!world.nodes.some(n => Math.abs(n.x) > 450), 'no orphaned fictional route nodes');
});

test('station model reads civic position and faces the player instead of synthetic offset', () => {
  const src = fs.readFileSync(path.join(root, 'js/gastown-waterfront-station.js'), 'utf8');
  const runtime = fs.readFileSync(path.join(root, 'js/gastown-sim.js'), 'utf8');
  const fakeWindow = {};
  new Function('window', src)(fakeWindow);
  const model = fakeWindow.GastownWaterfrontStation;
  const anchor = model.anchorFor(world);
  assert.equal(anchor.x, world.station.x);
  assert.equal(anchor.z, world.station.z);
  const yaw = model.arrivalYaw(world);
  const forward = { x: -Math.sin(yaw), z: -Math.cos(yaw) };
  const diff = { x: anchor.x-world.spawn.x, z: anchor.z-world.spawn.z };
  const dot = (forward.x*diff.x + forward.z*diff.z)/Math.hypot(diff.x,diff.z);
  assert.ok(dot > 0.999);
  assert.ok(model.isWalkableForecourt(world, model.toWorld(world,0,10)));
  assert.ok(model.commutersFor(world).every(npc => npc.patrol.every(p => distance(p,anchor) < 45)));
  assert.match(runtime, /isPointInPolygon\(spawn, building\.footprint\)/);
});

test('generator prefers the verified source over the old nearest-segment path', () => {
  const source = fs.readFileSync(path.join(root,'scripts/generate-gastown-world.js'), 'utf8');
  assert.match(source, /require\('\.\/rebuild-gastown-civic-world'\)/);
  assert.match(source, /600 W CORDOVA ST/);
});
