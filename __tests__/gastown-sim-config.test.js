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
  assert.match(php, /hold to run/);
  assert.doesNotMatch(php, /AI-generated welcome/);
});

test('Gastown production assets load for slug-based WordPress routes and deploy together', () => {
  const root = path.join(__dirname, '..');
  const themeFunctions = fs.readFileSync(path.join(root, 'functions.php'), 'utf8');
  const assetLoader = themeFunctions.match(/function se_enqueue_gastown_sim_assets\(\) \{[\s\S]*?\n\}\nadd_action\( 'wp_enqueue_scripts', 'se_enqueue_gastown_sim_assets' \);/);
  assert.ok(assetLoader, 'Gastown asset loader exists');
  assert.match(assetLoader[0], /is_page\( 'gastown-sim' \)/, 'Automatically selected page-slug template must enqueue assets');
  assert.match(assetLoader[0], /is_page_template\( 'page-gastown-sim\.php' \)/);
  assert.match(assetLoader[0], /wp_enqueue_style\( 'se-gastown-sim'/);
  assert.match(assetLoader[0], /'se-gastown-sim',\s*\$uri \. \$app_path/);

  const manifest = fs.readFileSync(path.join(root, 'scripts/theme_deploy_manifest.py'), 'utf8');
  const required = [
    'page-gastown-sim.php',
    'assets/css/gastown-sim.css',
    'js/gastown-sim.js',
    'js/gastown-world-loader.js',
    'js/gastown-building-normalizer.js',
    'js/gastown-dialog.js',
    'assets/world/gastown-water-street.json',
    'assets/dialog/gastown.json',
    ...['cobblestone', 'concrete-slabs'].flatMap(material =>
      ['albedo', 'normal', 'roughness', 'ao'].map(map => `assets/textures/${material}/${map}.svg`)
    ),
  ];
  for (const file of required) {
    assert.ok(fs.existsSync(path.join(root, file)), `Runtime asset must exist: ${file}`);
    assert.ok(manifest.includes(`"${file}"`), `Runtime asset must deploy: ${file}`);
  }

  const workflow = fs.readFileSync(path.join(root, '.github/workflows/deploy-production.yml'), 'utf8');
  for (const glob of ['assets/world/**', 'assets/dialog/**', 'assets/textures/**']) {
    assert.ok(workflow.includes(`'${glob}'`), `Future asset edits must trigger deployment: ${glob}`);
  }
});
