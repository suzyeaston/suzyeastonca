const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

test('localized simulator defaults boot into morning clear mode with one canonical world URL', () => {
  const functionsPath = path.join(__dirname, '..', 'functions.php');
  const php = fs.readFileSync(functionsPath, 'utf8');

  assert.match(php, /'defaultWeather'\s*=>\s*'clear'/);
  assert.match(php, /'defaultTimeOfDay'\s*=>\s*'morning'/);
  assert.match(php, /'defaultMood'\s*=>\s*'calm'/);
  assert.match(php, /'worldDataUrl'\s*=>\s*esc_url_raw\( \$uri \. '\/assets\/world\/gastown-water-street\.json' \)/);
  assert.doesNotMatch(php, /'starterWorldDataUrl'\s*=>/);
  assert.match(php, /'conversationEndpoint'\s*=>\s*esc_url_raw\( rest_url\( 'se\/v1\/gastown-npc-chat' \) \)/);
  assert.doesNotMatch(php, /'defaultMood'\s*=>\s*'eerie'/);
});

test('gastown sim enqueue depends on Tone safely', () => {
  const functionsPath = path.join(__dirname, '..', 'functions.php');
  const php = fs.readFileSync(functionsPath, 'utf8');

  assert.match(php, /wp_enqueue_script\(\s*'tone-js',\s*'https:\/\/unpkg\.com\/tone@14\.7\.77\/build\/Tone\.js'/s);
  assert.match(php, /'se-gastown-sim'[\s\S]*array\( 'three-js', 'howler-js', 'tone-js', 'se-gastown-world-loader', 'se-gastown-building-normalizer', 'se-gastown-dialog' \)/);
});

test('simulator page exposes aligned supported mood options and daytime defaults', () => {
  const pagePath = path.join(__dirname, '..', 'page-gastown-sim.php');
  const php = fs.readFileSync(pagePath, 'utf8');

  assert.match(php, /<option value="morning" selected>Morning<\/option>/);
  assert.match(php, /<option value="afternoon">Afternoon<\/option>/);
  assert.match(php, /<option value="clear" selected>Clear<\/option>/);
  assert.match(php, /<option value="thunderstorm">Thunderstorm<\/option>/);
  ['calm', 'commuter', 'lively', 'eerie'].forEach((mood) => {
    assert.match(php, new RegExp(`<option value="${mood}"`));
  });
  assert.match(php, /<option value="calm" selected>Calm<\/option>/);
  assert.match(php, /data-sim-world-status/);
  assert.match(php, /data-sim-minimap-mode-status/);
  assert.match(php, /Route line/);
  assert.match(php, /Sidewalk \/ plaza/);
  assert.doesNotMatch(php, /<option value="quiet"/);
  assert.doesNotMatch(php, /<option value="nightlife"/);
});

test('clock chime system initializes or fails softly without blocking startup', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function unlockSteamClockAudio\(\)/);
  assert.match(src, /function ensureNpcLoop\(npcState\)/);
  assert.match(src, /Tone\.PolySynth/);
  assert.match(src, /Tone\.MembraneSynth/);
  assert.doesNotMatch(src, /oscillator\.type = 'triangle'/);
  assert.doesNotMatch(src, /overtone\.type = 'sine'/);
  assert.match(src, /return false;\n\s*}\n\s*try \{/s);
  assert.match(src, /warnAudioUnavailable\('Tone\.js steam clock chimes unavailable; simulator continuing without musical chimes\.'/);
  assert.match(src, /STEAM_CLOCK_CHIME_MOTIF/);
  assert.doesNotMatch(src, /clockBurst/);
  assert.match(src, /Tone\.MetalSynth/);
  assert.match(src, /heroLandmarks\.find\(\(hero\) => hero\.id === 'steam-clock-hero'\)/);
});

test('simulator mood fallbacks no longer default back to eerie and eerie bed is explicit only', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /activeMood:\s*config\.defaultMood \|\| 'calm'/);
  assert.match(src, /state\.world\.moodPresets\[state\.activeMood\] \|\| state\.world\.moodPresets\.calm \|\| state\.world\.moodPresets\.eerie/);
  assert.match(src, /state\.world\.moodPresets\[moodId\] \|\| state\.world\.moodPresets\.calm \|\| state\.world\.moodPresets\.eerie/);
  assert.match(src, /\['quiet_night', 'eerie_drones', 'nightlife_hum', 'commuter_mix'\]/);
});


test('simulator and classic simulator both request exactly one world URL', () => {
  const root = path.join(__dirname, '..');
  const simSrc = fs.readFileSync(path.join(root, 'js', 'gastown-sim.js'), 'utf8');
  const classicSrc = fs.readFileSync(path.join(root, 'js', 'gastown-sim-classic.js'), 'utf8');
  const loaderSrc = fs.readFileSync(path.join(root, 'js', 'gastown-world-loader.js'), 'utf8');

  assert.match(simSrc, /GastownWorldLoader\.load\(config\.worldDataUrl\)/);
  assert.match(classicSrc, /GastownWorldLoader\.load\(config\.worldDataUrl\)/);
  assert.doesNotMatch(simSrc, /starterWorldDataUrl/);
  assert.doesNotMatch(classicSrc, /starterWorldDataUrl/);
  assert.doesNotMatch(loaderSrc, /fallbackUrl/);
  assert.match(loaderSrc, /Missing Gastown world data URL\./);
});
