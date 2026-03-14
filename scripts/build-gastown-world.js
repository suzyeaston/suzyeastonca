#!/usr/bin/env node

/**
 * Build-time scaffold for generating assets/world/gastown-water-street.json
 * from offline exports sourced from City of Vancouver Open Data + optional OSM data.
 *
 * Runtime simulator deliberately does not call external map/data APIs.
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const worldPath = path.join(root, 'assets', 'world', 'gastown-water-street.json');

const importManifest = {
  inputs: {
    cityOfVancouver: {
      buildingFootprints: 'data/cov/buildings.geojson',
      streetCenterlines: 'data/cov/streets.geojson',
      intersections: 'data/cov/intersections.geojson',
    },
    osmOptional: {
      routeAlignment: 'data/osm/waterfront-to-steam-clock.geojson',
    },
  },
  pipeline: [
    '1) load local civic/OSM exports (if present)',
    '2) normalize projection to local meters-ish XY frame',
    '3) simplify centerline and derive walk corridor polygon',
    '4) split surfaces into street + sidewalk polygons',
    '5) fit building masses from footprint blocks',
    '6) write compact runtime JSON (no external dependencies)',
  ],
};

function run() {
  if (!fs.existsSync(worldPath)) {
    throw new Error('Missing base world file at assets/world/gastown-water-street.json');
  }

  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));
  world.meta = world.meta || {};
  world.meta.lastBuild = new Date().toISOString();
  world.meta.buildNotes = [
    'Runtime geometry is compact and static; no live map APIs.',
    'Prepared for future ingestion of offline City of Vancouver streets/buildings + optional OSM route alignment.',
    'Corridor includes walk bounds, street and sidewalk zones, building masses, and landmark anchors.',
  ];
  world.meta.importManifest = importManifest;

  fs.writeFileSync(worldPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  process.stdout.write('Updated ' + path.relative(root, worldPath) + '\n');
}

run();
