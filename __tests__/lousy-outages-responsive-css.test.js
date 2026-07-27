const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const css = fs.readFileSync('lousy-outages/assets/lousy-outages.css', 'utf8');
const shortcode = fs.readFileSync('lousy-outages/public/shortcode.php', 'utf8');

test('canonical Lousy Outages mobile selectors and early versioned assets remain present', () => {
  assert.match(css, /@media \(max-width: 900px\)[\s\S]*data-lo-section-grid="incidents"[\s\S]*grid-template-columns: 1fr/);
  assert.match(css, /@media \(max-width: 680px\)[\s\S]*\.lo-operational-table td::before[^}]*content: attr\(data-label\)/);
  assert.match(css, /@media \(max-width: 320px\)[\s\S]*\.lo-attention-services__grid[\s\S]*grid-template-columns: minmax\(0, 1fr\)/);
  assert.match(css, /\.lo-actions \.lo-meta[^}]*grid-column: 1 \/ -1/);
  assert.match(css, /\.lousy-outages, \.lousy-outages \*, \.lousy-outages \*::before/);
  assert.match(shortcode, /add_action\('wp_enqueue_scripts',[\s\S]*enqueue_dashboard_assets/);
  assert.match(shortcode, /asset_version\(\$base_path, 'lousy-outages\.css'\)/);
});
