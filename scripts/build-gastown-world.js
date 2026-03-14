#!/usr/bin/env node

/**
 * Build-time scaffold for generating assets/world/gastown-water-street.json
 * from offline exports sourced from City of Vancouver Open Data + OSM/Overpass.
 *
 * Runtime player deliberately does not call external map/data APIs.
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const worldPath = path.join(root, 'assets', 'world', 'gastown-water-street.json');

function run() {
  const hasWorld = fs.existsSync(worldPath);
  if (!hasWorld) {
    throw new Error('Missing base world file at assets/world/gastown-water-street.json');
  }

  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));
  world.meta = world.meta || {};
  world.meta.lastBuild = new Date().toISOString();
  world.meta.buildNotes = [
    'Placeholder scaffold only.',
    'Future: read offline GeoJSON / CSV exports from Open Data and OSM extracts.',
    'Future: normalize centerline, landmark anchors, and facades into compact runtime format.'
  ];

  fs.writeFileSync(worldPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  process.stdout.write('Updated ' + path.relative(root, worldPath) + '\n');
}

run();
