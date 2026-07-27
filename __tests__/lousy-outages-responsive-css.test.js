const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const css = fs.readFileSync('lousy-outages/assets/lousy-outages.css', 'utf8');
const shortcode = fs.readFileSync('lousy-outages/public/shortcode.php', 'utf8');
const fixture = fs.readFileSync('tests/fixtures/lousy-outages-responsive.html', 'utf8');
const responsiveSpec = fs.readFileSync('tests/e2e/lousy-outages-responsive.spec.js', 'utf8');

test('canonical Lousy Outages mobile selectors and early versioned assets remain present', () => {
  assert.match(css, /@media \(max-width: 900px\)[\s\S]*data-lo-section-grid="incidents"[\s\S]*grid-template-columns: 1fr/);
  assert.match(css, /@media \(max-width: 680px\)[\s\S]*\.lo-operational-table td::before[^}]*content: attr\(data-label\)/);
  assert.match(css, /@media \(max-width: 320px\)[\s\S]*\.lo-attention-services__grid[\s\S]*grid-template-columns: minmax\(0, 1fr\)/);
  assert.match(css, /\.lo-actions \.lo-meta[^}]*grid-column: 1 \/ -1/);
  assert.match(css, /\.lousy-outages, \.lousy-outages \*, \.lousy-outages \*::before/);
  assert.match(css, /\.lousy-outages \.lo-service-card \.lo-service-card__diagnostic p,[^}]*\.lousy-outages \.lo-service-card \.lo-service-card__footer p/);
  assert.match(shortcode, /add_action\('wp_enqueue_scripts',[\s\S]*enqueue_dashboard_assets/);
  assert.match(shortcode, /asset_version\(\$base_path, 'lousy-outages\.css'\)/);
});

test('responsive browser QA challenges and verifies theme isolation', () => {
  assert.match(fixture, /id="hostile-theme"[^]*background:#fff!important[^]*writing-mode:vertical-rl!important/);
  assert.match(responsiveSpec, /isolatedProse[^]*backgroundColor[^]*fontFamily[^]*writingMode/);
  assert.match(responsiveSpec, /isolatedProse\.background\)\.toBe\('rgba\(0, 0, 0, 0\)'\)/);
  assert.match(responsiveSpec, /isolatedProse\.writingMode\)\.toBe\('horizontal-tb'\)/);
});
