const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const source = file => fs.readFileSync(path.join(__dirname,'..',file),'utf8');

test('homepage features Suzy Pirate Radio, not a map of other stations', () => {
  const part = source('parts/home-radio-feature.php');
  const home = source('page-home.php');
  assert.match(home,/parts\/home-radio-feature/);
  assert.match(part,/PIRATE RADIO/);
  assert.match(part,/data-pirate-radio/);
  assert.match(part,/data-radio-play/);
  assert.match(part,/home_url\(\s*'\/radio\/'\s*\)/);
  assert.doesNotMatch(part,/YVR RADIO|listening map|other stations/);
});
test('radio page is a single independently operated station', () => {
  const php = source('template-yvr-radio.php');
  assert.match(php,/SUZY/);
  assert.match(php,/PIRATE RADIO/);
  assert.match(php,/data-radio-status-text/);
  assert.match(php,/data-radio-volume/);
  assert.doesNotMatch(php,/CiTR|CFRO|CBC|MapLibre|yr-map|yr-stations|Pick a signal/);
});
test('the public WordPress page only receives HTTPS URLs, never source secrets', () => {
  const php = source('inc/yvr-radio.php');
  assert.match(php,/se_radio_stream/);
  assert.match(php,/se_radio_status/);
  assert.match(php,/array\(\s*'https'\s*\)/);
  assert.doesNotMatch(php,/YvrRadio|se_radio_channels|yvr-maplibre/);
  const ui = source('assets/js/yvr-radio.js');
  assert.match(ui,/data-pirate-radio/);
  assert.match(ui,/data\.online/);
  assert.match(ui,/!streamUrl \|\| online === false/);
  assert.doesNotMatch(ui,/SOURCE_TOKEN|\/source|autoplay\s*=/);
});
