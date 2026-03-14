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
      rightOfWayWidths: 'data/cov/right-of-way-widths.geojson',
    },
    gastownHeritageOptional: {
      register: 'data/heritage/gastown-register.geojson',
      styleReferenceNotes: 'data/heritage/gastown-style-notes.json',
    },
    referenceLibraryOptional: {
      curatedNotes: 'data/reference/gastown-curated-notes.json',
      screenshotIndex: 'data/reference/gastown-screenshots.json',
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
    '5) fit building masses from footprint blocks + choose facade profile presets',
    '6) attach hero landmark metadata and priority weighting',
    '7) write compact runtime JSON (no external dependencies)',
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

function run() {
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
  world.meta.importManifest = importManifest;

  (world.buildings || []).forEach((building) => applyReferenceScaffold(building));
  (world.hero_landmarks || []).forEach((landmark) => applyReferenceScaffold(landmark));

  fs.writeFileSync(worldPath, JSON.stringify(world, null, 2) + '\n', 'utf8');
  process.stdout.write('Updated ' + path.relative(root, worldPath) + '\n');
}

run();
