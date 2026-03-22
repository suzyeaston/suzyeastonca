const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

test('localized simulator defaults boot into morning clear mode', () => {
  const functionsPath = path.join(__dirname, '..', 'functions.php');
  const php = fs.readFileSync(functionsPath, 'utf8');

  assert.match(php, /'defaultWeather'\s*=>\s*'clear'/);
  assert.match(php, /'defaultTimeOfDay'\s*=>\s*'morning'/);
});

test('simulator page exposes morning clear defaults and thunderstorm option', () => {
  const pagePath = path.join(__dirname, '..', 'page-gastown-sim.php');
  const php = fs.readFileSync(pagePath, 'utf8');

  assert.match(php, /<option value="morning" selected>Morning<\/option>/);
  assert.match(php, /<option value="clear" selected>Clear<\/option>/);
  assert.match(php, /<option value="thunderstorm">Thunderstorm<\/option>/);
});
