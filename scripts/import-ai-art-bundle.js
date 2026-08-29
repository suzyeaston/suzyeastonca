#!/usr/bin/env node
/**
 * Import a Moving Picture Machine publish bundle into the site catalog.
 *
 * Usage:
 *   node scripts/import-ai-art-bundle.js <bundle-dir>           # preview
 *   node scripts/import-ai-art-bundle.js <bundle-dir> --apply   # write changes
 *
 * Never deploys. Never invents artwork. Requires an explicit --apply to mutate.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const ROOT = path.resolve(__dirname, '..');
const CATALOG_PATH = path.join(ROOT, 'assets/data/ai-art/works.json');
const MEDIA_DIR = path.join(ROOT, 'assets/data/ai-art/media');
const SCHEMA_PATH = path.join(ROOT, 'schemas/ai-art-publish-bundle.schema.json');

const REQUIRED_ARTWORK = [
  'id',
  'slug',
  'status',
  'title',
  'date',
  'kind',
  'description',
  'alt',
  'src',
  'thumbnailSrc',
  'width',
  'height',
  'tags',
  'tools',
  'humanContribution',
  'featured',
  'sortOrder'
];

const KINDS = new Set(['film', 'still', 'loop', 'process']);
const STATUSES = new Set(['draft', 'published']);

function fail(message, code = 1) {
  console.error(`import-ai-art-bundle: ${message}`);
  process.exit(code);
}

function readJson(filePath) {
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch (err) {
    fail(`cannot read JSON at ${filePath}: ${err.message}`);
  }
}

function sha256File(filePath) {
  const hash = crypto.createHash('sha256');
  hash.update(fs.readFileSync(filePath));
  return hash.digest('hex');
}

function validateArtwork(artwork) {
  const errors = [];
  for (const key of REQUIRED_ARTWORK) {
    if (!(key in artwork)) errors.push(`missing artwork.${key}`);
  }
  if (artwork.status && !STATUSES.has(artwork.status)) {
    errors.push('artwork.status must be draft|published');
  }
  if (artwork.kind && !KINDS.has(artwork.kind)) {
    errors.push('artwork.kind must be film|still|loop|process');
  }
  if (artwork.slug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(artwork.slug)) {
    errors.push('artwork.slug must be lowercase kebab-case');
  }
  if (!Array.isArray(artwork.humanContribution) || artwork.humanContribution.length < 1) {
    errors.push('artwork.humanContribution needs at least one entry');
  }
  if (!Array.isArray(artwork.tools)) errors.push('artwork.tools must be an array');
  if (!Array.isArray(artwork.tags)) errors.push('artwork.tags must be an array');
  if (!(Number(artwork.width) >= 1) || !(Number(artwork.height) >= 1)) {
    errors.push('artwork width/height must be positive');
  }
  if (!String(artwork.alt || '').trim()) errors.push('artwork.alt is required');

  // Public safety: reject absolute local paths and secret-looking fields.
  const blob = JSON.stringify(artwork);
  if (/\/Users\/|\/home\/|C:\\\\|api[_-]?key|secret|password/i.test(blob)) {
    errors.push('artwork appears to contain private paths or secrets');
  }

  return errors;
}

function validateBundle(bundleDir, manifest) {
  const errors = [];
  if (!manifest || typeof manifest !== 'object') {
    return ['manifest.json missing or invalid'];
  }
  if (manifest.schemaVersion !== '1.0.0') {
    errors.push(`unsupported schemaVersion: ${manifest.schemaVersion}`);
  }
  if (!manifest.artwork || typeof manifest.artwork !== 'object') {
    errors.push('manifest.artwork is required');
  } else {
    errors.push(...validateArtwork(manifest.artwork));
  }

  const mediaFiles = Array.isArray(manifest.mediaFiles) ? manifest.mediaFiles : [];
  for (const entry of mediaFiles) {
    const abs = path.join(bundleDir, entry.path || '');
    if (!entry.path || entry.path.includes('..')) {
      errors.push(`unsafe media path: ${entry.path}`);
      continue;
    }
    if (!fs.existsSync(abs)) {
      errors.push(`missing media file: ${entry.path}`);
      continue;
    }
    const digest = sha256File(abs);
    if (entry.sha256 && entry.sha256 !== digest) {
      errors.push(`hash mismatch for ${entry.path}`);
    }
  }

  // Primary media must exist relative to bundle.
  if (manifest.artwork) {
    for (const field of ['src', 'thumbnailSrc', 'posterSrc']) {
      const rel = manifest.artwork[field];
      if (!rel) continue;
      // Paths in the catalog may be rewritten; in the bundle they should live under media/.
      const candidates = [
        path.join(bundleDir, rel),
        path.join(bundleDir, 'media', path.basename(rel))
      ];
      if (!candidates.some((p) => fs.existsSync(p))) {
        // Only hard-fail for src/thumbnail.
        if (field === 'src' || field === 'thumbnailSrc') {
          errors.push(`cannot locate ${field} media for ${rel}`);
        }
      }
    }
  }

  return errors;
}

function previewDiff(catalog, artwork) {
  const existing = (catalog.works || []).find(
    (w) => w.id === artwork.id || w.slug === artwork.slug
  );
  if (!existing) {
    return { action: 'create', slug: artwork.slug, status: artwork.status };
  }
  return {
    action: 'update',
    slug: artwork.slug,
    fromStatus: existing.status,
    toStatus: artwork.status,
    titleChanged: existing.title !== artwork.title
  };
}

function copyMedia(bundleDir, artwork) {
  const slugDir = path.join(MEDIA_DIR, artwork.slug);
  fs.mkdirSync(slugDir, { recursive: true });

  const rewritten = { ...artwork };
  const fields = ['src', 'thumbnailSrc', 'posterSrc', 'captionsSrc'];
  for (const field of fields) {
    const rel = artwork[field];
    if (!rel) continue;
    const base = path.basename(rel);
    const candidates = [
      path.join(bundleDir, rel),
      path.join(bundleDir, 'media', base)
    ];
    const source = candidates.find((p) => fs.existsSync(p));
    if (!source) continue;
    const dest = path.join(slugDir, base);
    fs.copyFileSync(source, dest);
    rewritten[field] = `${artwork.slug}/${base}`;
  }
  return rewritten;
}

function main() {
  const args = process.argv.slice(2);
  const apply = args.includes('--apply');
  const bundleDir = args.find((a) => !a.startsWith('--'));

  if (!bundleDir) {
    fail('usage: node scripts/import-ai-art-bundle.js <bundle-dir> [--apply]');
  }

  const absBundle = path.resolve(bundleDir);
  if (!fs.existsSync(absBundle) || !fs.statSync(absBundle).isDirectory()) {
    fail(`bundle directory not found: ${absBundle}`);
  }

  const manifestPath = path.join(absBundle, 'manifest.json');
  if (!fs.existsSync(manifestPath)) {
    fail('manifest.json not found in bundle');
  }

  if (!fs.existsSync(SCHEMA_PATH)) {
    fail(`schema missing at ${SCHEMA_PATH}`);
  }

  const manifest = readJson(manifestPath);
  const errors = validateBundle(absBundle, manifest);
  if (errors.length) {
    console.error('Validation failed:');
    errors.forEach((e) => console.error(`  - ${e}`));
    process.exit(1);
  }

  const catalog = fs.existsSync(CATALOG_PATH)
    ? readJson(CATALOG_PATH)
    : { schemaVersion: '1.0.0', gallery: {}, works: [] };

  const artwork = manifest.artwork;
  const diff = previewDiff(catalog, artwork);

  console.log('Publish bundle preview');
  console.log(`  schema: ${manifest.schemaVersion}`);
  console.log(`  artwork: ${artwork.title} (${artwork.slug})`);
  console.log(`  status: ${artwork.status}`);
  console.log(`  kind: ${artwork.kind}`);
  console.log(`  action: ${diff.action}`);
  if (diff.action === 'update') {
    console.log(`  status change: ${diff.fromStatus} -> ${diff.toStatus}`);
  }
  console.log(`  apply: ${apply ? 'YES' : 'no (dry run)'}`);

  if (!apply) {
    console.log('\nDry run only. Re-run with --apply to write catalog + media.');
    return;
  }

  const rewritten = copyMedia(absBundle, artwork);
  const works = Array.isArray(catalog.works) ? [...catalog.works] : [];
  const idx = works.findIndex((w) => w.id === rewritten.id || w.slug === rewritten.slug);
  if (idx >= 0) {
    works[idx] = rewritten;
  } else {
    works.push(rewritten);
  }

  catalog.works = works;
  catalog.schemaVersion = catalog.schemaVersion || '1.0.0';
  fs.mkdirSync(path.dirname(CATALOG_PATH), { recursive: true });
  fs.writeFileSync(CATALOG_PATH, `${JSON.stringify(catalog, null, 2)}\n`, 'utf8');
  console.log(`\nApplied. Catalog updated at ${path.relative(ROOT, CATALOG_PATH)}`);
}

module.exports = {
  validateArtwork,
  validateBundle,
  previewDiff
};

if (require.main === module) {
  main();
}
