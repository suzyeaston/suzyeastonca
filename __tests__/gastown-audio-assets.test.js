const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const renderer = read('js/gastown-sim.js');
const php = read('functions.php');
const manifest = read('scripts/theme_deploy_manifest.py');

function audioSetup(config, world) {
  const start = renderer.indexOf('  function setupAudio(world) {');
  const end = renderer.indexOf('  function applyVisualState() {', start);
  assert.ok(start > 0 && end > start, 'setupAudio is extractable for smoke testing');
  const calls = [];
  const state = { sounds: { beds: {}, zoneBeds: {}, rainLoop: null, thunder: null } };
  const createSafeHowl = options => {
    calls.push(options);
    return { _sound: options };
  };
  const warnAudioUnavailable = message => { throw new Error(message); };
  const AUDIO_MIX = { thunderMax: 0.16 };
  const factory = new Function(
    'config', 'state', 'createSafeHowl', 'AUDIO_MIX', 'warnAudioUnavailable',
    renderer.slice(start, end) + '\nreturn setupAudio;'
  );
  factory(config, state, createSafeHowl, AUDIO_MIX, warnAudioUnavailable)(world);
  return { calls, sounds: state.sounds };
}

test('missing ambience entries produce no sound requests, including MP3/OGG probes', () => {
  const result = audioSetup({ audioSources: {} }, {
    audioZones: [
      { id: 'distant', bed: 'distant_transit' },
      { id: 'steam', bed: 'steam_clock_mech' },
      { id: 'crowd', bed: 'street_crowd' }
    ]
  });
  assert.equal(result.calls.length, 0);
  assert.equal(Object.keys(result.sounds.beds).length, 0);
  assert.equal(Object.keys(result.sounds.zoneBeds).length, 0);
  assert.equal(result.sounds.rainLoop, null);
  assert.equal(result.sounds.thunder, null);
  const noConfig = audioSetup({}, { audioZones: [] });
  assert.equal(noConfig.calls.length, 0);
  assert.doesNotMatch(renderer, /const src = \(name\) => \[base \+ '\/' \+ name \+ '\.mp3'/);
  assert.doesNotMatch(renderer, /config\.audioBaseUrl/);
});

test('only allowlisted, already deployed file URLs are passed to Howler, once each', () => {
  const rain = 'https://www.suzyeaston.ca/wp-content/themes/suzyeastonca-main/assets/audio/yvr/rain-loop.mp3';
  const transit = 'https://www.suzyeaston.ca/wp-content/themes/suzyeastonca-main/assets/audio/yvr/skytrain-rumble.mp3';
  const config = { audioSources: {
    'weather/rain_pavement': rain,
    'beds/commuter_mix': transit,
    'zones/distant_transit': transit,
    'weather/thunder_roll': '', // deliberately missing
    'beds/quiet_night': '/not-a-verified-url.mp3', // rejected, no source probing
  } };
  const result = audioSetup(config, { audioZones: [
    { id: 'transit', bed: 'distant_transit' },
    { id: 'unavailable', bed: 'street_crowd' },
  ] });
  assert.equal(result.calls.length, 3);
  assert.deepEqual(result.calls.map(call => call.src), [[transit], [rain], [transit]]);
  assert.ok(result.sounds.beds.commuter_mix);
  assert.ok(!result.sounds.beds.quiet_night);
  assert.ok(result.sounds.rainLoop);
  assert.equal(result.sounds.thunder, null);
  assert.ok(result.sounds.zoneBeds.transit);
  assert.ok(!result.sounds.zoneBeds.unavailable);
  assert.ok(result.calls.every(sound => sound.src.length === 1 && sound.src[0].endsWith('.mp3')));
});

test('WordPress publishes sound URLs only after a readable file check', () => {
  assert.match(php, /\$gastown_audio_ids\s*=\s*array\(/);
  assert.match(php, /'beds\/quiet_night'/);
  assert.match(php, /'beds\/commuter_mix'/);
  assert.match(php, /'weather\/rain_pavement'/);
  assert.match(php, /'weather\/thunder_roll'/);
  assert.match(php, /'zones\/distant_transit'/);
  assert.match(php, /is_file\( \$dir \. \$candidate_path \) && is_readable\( \$dir \. \$candidate_path \)/);
  assert.match(php, /'audioSources'\s*=>\s*\$gastown_audio_sources/);
  assert.doesNotMatch(php, /'audioBaseUrl'\s*=>/);
  // Fallbacks are files already deployed by the production theme manifest,
  // not speculative network URLs or placeholders.
  assert.ok(!php.includes("'beds/commuter_mix'    => '/assets/audio/yvr/skytrain-rumble.mp3'"));
  assert.ok(!php.includes("'zones/distant_transit'=> '/assets/audio/yvr/skytrain-rumble.mp3'"));
  for (const relative of ['assets/audio/yvr/rain-loop.mp3']) {
    assert.ok(fs.existsSync(path.join(ROOT,relative)), relative + ' exists in the repository');
    assert.ok(manifest.includes('"'+relative+'"'), relative + ' included in production deployment');
    assert.ok(php.includes("'/" + relative + "'"), relative + ' referenced as a safe fallback');
  }
});
