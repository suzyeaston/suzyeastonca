const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const php = fs.readFileSync('lousy-outages/public/shortcode.php', 'utf8');
const css = fs.readFileSync('lousy-outages/assets/lousy-outages.css', 'utf8');
const js = fs.readFileSync('lousy-outages/assets/lousy-outages.js', 'utf8');

test('attention services use semantic cards with unambiguous timestamps', () => {
  assert.match(php, /<section class="lo-attention-services"[^>]*aria-labelledby/);
  assert.match(php, /<ul class="lo-attention-services__grid"/);
  assert.match(php, /<li class="lo-attention-services__item"[\s\S]*<article class="lo-service-card/);
  assert.match(php, /<dt>Provider update<\/dt>/);
  assert.match(php, /<strong>Checked by Lousy Outages:<\/strong>/);
  assert.doesNotMatch(php, /data-label="Last checked"/);
});

test('attention badges and cards have intrinsic desktop and mobile bounds', () => {
  assert.match(css, /\.lo-attention-services__grid\s*{[^}]*repeat\(2, minmax\(0, 1fr\)\)/s);
  assert.match(css, /\.lo-service-card__badge\s*{[^}]*display: inline-flex[^}]*max-width: 14rem[^}]*white-space: normal[^}]*overflow-wrap: anywhere/s);
  assert.match(css, /@media \(max-width: 900px\)[\s\S]*\.lo-attention-services__grid\s*{\s*grid-template-columns: 1fr/s);
  assert.match(css, /@media \(max-width: 680px\)[\s\S]*\.lo-service-card__header\s*{\s*grid-template-columns: 1fr/s);
  assert.match(css, /\.lo-service-card__diagnostic\s*{[^}]*flex: 1/s);
});

test('operational services use a four-column semantic table and one filtering path', () => {
  assert.match(php, /<table class="lo-operational-table">[\s\S]*Provider[\s\S]*Category \/ source[\s\S]*Checked by Lousy Outages[\s\S]*Status page/);
  assert.doesNotMatch(php, /lo-operational-table[^]*<th scope="col">State<\/th>/);
  assert.match(js, /querySelectorAll\('\[data-lo-provider-row\]'\)/);
  assert.match(js, /visibleAttentionCounts/);
  assert.match(js, /data-lo-operational-summary/);
  assert.match(js, /Show ' \+ visibleOperational/);
});

test('0.4.7 release metadata is canonical', () => {
  const plugin = fs.readFileSync('lousy-outages/lousy-outages.php', 'utf8');
  const readme = fs.readFileSync('lousy-outages/README.md', 'utf8');
  const build = fs.readFileSync('scripts/build-lousy-outages-release.sh', 'utf8');
  assert.match(plugin, /Version: 0\.4\.7/);
  assert.match(plugin, /LOUSY_OUTAGES_VERSION', '0\.4\.7'/);
  assert.match(readme, /## 0\.4\.7/);
  assert.match(build, /VERSION="0\.4\.7"/);
});
