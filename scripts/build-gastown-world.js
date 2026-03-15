#!/usr/bin/env node

/**
 * Build-time scaffold for generating assets/world/gastown-water-street.json
 * from offline exports sourced from City of Vancouver Open Data + optional OSM data.
 *
 * Runtime simulator deliberately does not call external map/data APIs.
 */

const fs = require('fs');
const path = require('path');
const { buildWorld } = require('./generate-gastown-world');

const root = path.resolve(__dirname, '..');
const worldPath = path.join(root, 'assets', 'world', 'gastown-water-street.json');

const importManifest = {
  inputs: {
    cityOfVancouver: {
      buildingFootprints: 'data/cov/building-footprints.geojson',
      streetCenterlines: 'data/cov/public-streets.geojson',
      streetLightingPolesOptional: 'data/cov/street-lighting-poles.geojson',
      publicTreesOptional: 'data/cov/public-trees.geojson',
    },
    reference: {
      routeAnchors: 'data/reference/route-anchors.json',
    },
  },
  pipeline: [
    '1) load local civic exports (if present)',
    '2) project lat/lon to local meter x/z frame anchored at Waterfront Station',
    '3) merge + simplify Water/Cordova route centerline',
    '4) derive street, sidewalks, and walk bounds buffers',
    '5) fit nearby building footprints and optional streetscape points',
    '6) write compact runtime JSON (no external dependencies)',
  ],
};

const referenceTemplate = {
  reference_name: null,
  reference_address: null,
  style_notes: null,
  silhouette_notes: null,
  facade_profile: null,
  segment_style: null,
  storefront_notes: null,
  landmark_priority: 'supporting',
};

function applyReferenceScaffold(entity) {
  Object.keys(referenceTemplate).forEach((key) => {
    if (!(key in entity)) {
      entity[key] = referenceTemplate[key];
    }
  });
}

function runScaffold() {
  if (!fs.existsSync(worldPath)) {
    throw new Error('Missing base world file at assets/world/gastown-water-street.json');
  }

  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));
  world.meta = world.meta || {};
  world.meta.lastBuild = new Date().toISOString();
  world.meta.buildNotes = [
    'Runtime geometry is compact and static; no live map APIs.',
    'Prepared for offline City of Vancouver footprints/streets/ROW widths + optional OSM route alignment.',
    'Supports hero_landmarks + facade_profiles to prioritize recognizability over photoreal detail.',
    'Scaffold includes reference-driven world notes for segment style, silhouette, and storefront cadence.',
  ];
  world.meta.importManifest = world.meta.importManifest || importManifest;

  (world.buildings || []).forEach((building) => applyReferenceScaffold(building));
  (world.hero_landmarks || []).forEach((landmark) => applyReferenceScaffold(landmark));

  fs.writeFileSync(worldPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  process.stdout.write('Updated scaffold ' + path.relative(root, worldPath) + '\n');
}

function run() {
  const generated = buildWorld({ root, outputPath: worldPath });
  if (generated.generated) {
    process.stdout.write('Generated offline world data at ' + path.relative(root, worldPath) + '\n');
    return;
  }

  process.stdout.write('Offline source data missing; keeping scaffold behavior.\n');
  process.stdout.write(generated.reason + '\n');
  runScaffold();
}

run();
