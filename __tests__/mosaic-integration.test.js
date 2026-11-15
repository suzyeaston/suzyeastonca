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
  for (const asset of ['index.html','styles.css','core.js','app.js']) {
    assert.ok(fs.existsSync(path.join(__dirname,'../assets/mosaic',asset)),asset);
  }
  const html = read('assets/mosaic/index.html');
  assert.match(html, /src="core\.js"/);
  assert.match(html, /src="app\.js"/);
  assert.match(html, /href="styles\.css"/);
  assert.match(html, /planning target/i);
});
test('Theme manifest and workflow deploy all MOSAIC assets', () => {
  const manifest = read('scripts/theme_deploy_manifest.py');
  const workflow = read('.github/workflows/deploy-production.yml');
  for (const asset of ['page-mosaic.php', 'assets/mosaic/index.html','assets/mosaic/styles.css','assets/mosaic/core.js','assets/mosaic/app.js']) {
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
