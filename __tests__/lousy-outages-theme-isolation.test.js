const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const css = fs.readFileSync('assets/css/lousy-outages-page.css', 'utf8');

test('page-level card prose isolation wins hostile important theme rules', () => {
  assert.match(css, /\.lousy-outages-page \.lousy-outages \.lo-service-card \.lo-service-card__diagnostic p/);
  assert.match(css, /\.lousy-outages-page \.lousy-outages \.lo-service-card \.lo-service-card__footer p/);
  assert.match(css, /background: transparent !important/);
  assert.match(css, /font-family: Inter,[^;]+!important/);
  assert.match(css, /text-align: left !important/);
  assert.match(css, /writing-mode: horizontal-tb !important/);
  assert.match(css, /white-space: normal !important/);
  assert.match(css, /overflow-wrap: anywhere/);
});
