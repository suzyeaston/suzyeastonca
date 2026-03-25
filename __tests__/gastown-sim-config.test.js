const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

test('localized simulator config uses one canonical deterministic world URL', () => {
  const functionsPath = path.join(__dirname, '..', 'functions.php');
  const php = fs.readFileSync(functionsPath, 'utf8');

  assert.match(php, /'worldDataUrl'\s*=>\s*esc_url_raw\( \$uri \. '\/assets\/world\/gastown-water-street\.json' \)/);
  assert.doesNotMatch(php, /starterWorldDataUrl/);
  assert.doesNotMatch(php, /startupStingEndpoint/);
  assert.doesNotMatch(php, /bandArrangementEndpoint/);
  assert.doesNotMatch(php, /conversationEndpoint/);
  assert.doesNotMatch(php, /voiceEndpoint/);
  assert.match(php, /'defaultWeather'\s*=>\s*'clear'/);
  assert.match(php, /'defaultTimeOfDay'\s*=>\s*'morning'/);
  assert.match(php, /'defaultMood'\s*=>\s*'calm'/);
});

test('simulator boot path is fallback-first and non-blocking for optional systems', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /const RUNTIME_PROFILE = \{/);
  assert.match(src, /fallbackOnly:\s*true/);
  assert.match(src, /enableBandSystem:\s*false/);
  assert.match(src, /const loadedWorld = await window\.GastownWorldLoader\.load\(config\.worldDataUrl\);/);
  assert.match(src, /state\.dialogData = \{\};/);
  assert.match(src, /window\.setTimeout\(\(\) => \{/);
  assert.match(src, /loadDialogData\(\)\.then/);
  assert.match(src, /setupAudio\(state\.world\)/);
  assert.doesNotMatch(src, /const dialogData = await loadDialogData\(\)\.catch/);
});

test('simulator page copy presents working corridor as intended product', () => {
  const pagePath = path.join(__dirname, '..', 'page-gastown-sim.php');
  const php = fs.readFileSync(pagePath, 'utf8');

  assert.match(php, /Gastown working corridor/);
  assert.match(php, /deterministic local world build/);
  assert.doesNotMatch(php, /AI-generated welcome/);
});
