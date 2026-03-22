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

test('gastown sim enqueue depends on Tone safely', () => {
  const functionsPath = path.join(__dirname, '..', 'functions.php');
  const php = fs.readFileSync(functionsPath, 'utf8');

  assert.match(php, /wp_enqueue_script\(\s*'tone-js',\s*'https:\/\/unpkg\.com\/tone@14\.7\.77\/build\/Tone\.js'/s);
  assert.match(php, /'se-gastown-sim'[\s\S]*array\( 'three-js', 'howler-js', 'tone-js', 'se-gastown-world-loader', 'se-gastown-building-normalizer', 'se-gastown-dialog' \)/);
});

test('simulator page exposes morning clear defaults and thunderstorm option', () => {
  const pagePath = path.join(__dirname, '..', 'page-gastown-sim.php');
  const php = fs.readFileSync(pagePath, 'utf8');

  assert.match(php, /<option value="morning" selected>Morning<\/option>/);
  assert.match(php, /<option value="clear" selected>Clear<\/option>/);
  assert.match(php, /<option value="thunderstorm">Thunderstorm<\/option>/);
});

test('clock chime system initializes or fails softly without blocking startup', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function unlockSteamClockAudio\(\)/);
  assert.match(src, /return false;\n\s*}\n\s*try \{/s);
  assert.match(src, /warnAudioUnavailable\('Tone\.js steam clock chimes unavailable; simulator continuing without musical chimes\.'/);
  assert.match(src, /STEAM_CLOCK_CHIME_MOTIF/);
});
