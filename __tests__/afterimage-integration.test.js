const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('AFTERIMAGE serves a dedicated route without creating a WordPress page', () => {
  const php = read('functions.php');
  assert.match(php, /function se_afterimage_is_request\(\)/);
  assert.match(php, /'afterimage' === \$relative/);
  assert.match(php, /add_filter\( 'pre_handle_404'/);
  assert.match(php, /add_filter\( 'template_include'/);
  assert.match(php, /page-afterimage\.php/);
  assert.match(php, /is_file\( \$atlas_template \)/);
});

test('AFTERIMAGE page embeds only the public standalone atlas', () => {
  const html = read('page-afterimage.php');
  assert.match(html, /suzyeaston\.github\.io\/afterimage-vancouver\//);
  assert.match(html, /<iframe/);
  assert.match(html, /title="AFTERIMAGE Vancouver:/);
  assert.doesNotMatch(html, /track\.py|private\.sqlite|applications\.db/);
});

test('AFTERIMAGE is discoverable from the home and projects pages', () => {
  for (const filename of ['page-home.php', 'page-projects.php']) {
    assert.match(read(filename), /AFTERIMAGE \/ Vancouver/);
    assert.match(read(filename), /home_url\( '\/afterimage\/' \)/);
  }
  assert.match(read('inc/seo.php'), /AFTERIMAGE \/ Vancouver \| Creative work atlas/);
});

test('Atlas template is in the selected production deployment manifest', () => {
  const manifest = read('scripts/theme_deploy_manifest.py');
  assert.match(manifest, /"page-afterimage\.php"/);
});
