const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const functions = fs.readFileSync('functions.php', 'utf8');
const css = fs.readFileSync('assets/css/lousy-outages-theme-isolation.css', 'utf8');

test('theme isolation is a separate, late-loading page asset', () => {
  assert.match(functions, /lousy-outages-theme-isolation\.css/);
  assert.match(functions, /'se-lousy-outages-theme-isolation',[\s\S]*array\( 'se-lousy-outages-page' \)/);
  assert.match(functions, /filemtime\( \$dir \. \$isolation_path \)/);
});

test('late-loading asset restores readable service-card prose', () => {
  assert.match(css, /\.lo-service-card \.lo-service-card__diagnostic p/);
  assert.match(css, /\.lo-service-card \.lo-service-card__footer p/);
  for (const declaration of [
    'background: transparent !important',
    'font-family: Inter',
    'text-align: left !important',
    'white-space: normal !important',
    'writing-mode: horizontal-tb !important',
    'overflow-wrap: anywhere',
  ]) {
    assert.ok(css.includes(declaration), `missing ${declaration}`);
  }
});
