'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
test('MOSAIC WordPress virtual route is self-hosted without GitHub Pages', () => {
  const php = read('functions.php');
  const page = read('page-mosaic.php');
  assert.match(php, /function se_mosaic_is_request\(/);
  assert.match(php, /'mosaic' === \$relative/);
  assert.match(php, /'\/page-mosaic\.php'/);
  assert.match(page, /get_template_directory_uri\(\)\s*\.\s*\$studio_relative/);
  assert.match(page, /allow="camera; microphone; autoplay"/);
  assert.doesNotMatch(page, /github\.io/);
});
test('MOSAIC runtime shipped locally alongside WordPress template', () => {
  for (const asset of ['index.html','styles.css','core.js','effects.js','app.js']) {
    assert.ok(fs.existsSync(path.join(__dirname,'../assets/mosaic',asset)),asset);
  }
  const html = read('assets/mosaic/index.html');
  assert.match(html, /src="core\.js(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /src="effects\.js(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /src="app\.js(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /href="styles\.css(?:\?v=[a-z0-9_-]+)?"/);
  assert.match(html, /planning target/i);
});
test('Theme manifest and workflow deploy all MOSAIC assets', () => {
  const manifest = read('scripts/theme_deploy_manifest.py');
  const workflow = read('.github/workflows/deploy-production.yml');
  for (const asset of ['page-mosaic.php', 'assets/mosaic/index.html','assets/mosaic/styles.css','assets/mosaic/core.js','assets/mosaic/effects.js','assets/mosaic/app.js']) {
    assert.ok(manifest.includes('"' + asset + '"'),asset);
  }
  assert.ok(manifest.includes('"assets/mosaic/**"'));
  assert.ok(workflow.includes("'assets/mosaic/**'"));
  assert.ok(workflow.includes('mosaic-integration.test.js'));
});
test('Recording has no automatic time limit and supports musician-controlled stop', () => {
  const app = read('assets/mosaic/app.js');
  assert.match(app, /function stopTake\(keep\)/);
  assert.match(app, /stopTake\(true\)/);
  assert.doesNotMatch(app, /activeTimer\s*=\s*setTimeout\s*\(/);
});

test('MOSAIC live global FX preview shares renderer with export and falls back for social', () => {
  const app = read('assets/mosaic/app.js');
  const fx = require('../assets/mosaic/effects.js');
  const html = read('assets/mosaic/index.html');
  assert.equal(fx.PRESETS.length, 5);
  assert.match(app, /renderFinalFrame\(previewContext,/);
  assert.match(app, /renderFinalFrame\(ctx, 720, 720,/);
  assert.match(app, /navigator\.share\(\{ files: \[file\]/);
  assert.match(app, /navigator\.canShare/);
  assert.match(app, /downloadBlob\(blob, name\)/);
  assert.match(html, /id="fx-preset"/);
  assert.match(html, /id="share-btn"/);
  assert.match(html, /id="share-caption"/);
});
