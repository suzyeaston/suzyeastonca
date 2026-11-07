const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('Homepage places Gastown immediately under Meanwhile, inside hero deck', () => {
  const home = read('page-home.php');
  const start = home.indexOf('<section class="home-yvr-radar-deck"');
  const close = home.indexOf('</section>', start);
  const pitch = home.indexOf("get_template_part( 'parts/home-hero-pitch' )");
  const meanwhile = home.indexOf("get_template_part( 'parts/home', 'mission-meanwhile' )");
  const gastown = home.indexOf("get_template_part( 'parts/home-gastown-spotlight' )");
  const outages = home.indexOf("get_template_part( 'parts/home-commercial-strip' )");
  assert.ok(start >= 0 && pitch > start && meanwhile > pitch);
  assert.ok(gastown > meanwhile && gastown < outages, 'Gastown directly follows Meanwhile');
  assert.ok(gastown < close, 'spotlight stays inside the homepage hero deck');
  assert.equal(home.split("get_template_part( 'parts/home-gastown-spotlight' )").length - 1, 1);
});

test('Artist-rendered Station East lookout shows the actual three-site relationship', () => {
  const svg = read('assets/illustrations/gastown-station-east-lookout.svg');
  assert.match(svg, /^<svg\b/);
  assert.match(svg, /<\/svg>\s*$/);
  assert.match(svg, /viewBox="0 0 1000 580"/);
  assert.match(svg, /WATERFRONT STATION/);
  assert.match(svg, /STATION EAST \/ OPEN PARKING/);
  assert.match(svg, /THE LANDING/);
  assert.match(svg, /Canada Place/);
  assert.match(svg, /cruise ship/i);
  assert.match(svg, /North Shore/);
  assert.match(svg, /<linearGradient id="lot"/);
  assert.match(svg, /<clipPath id="station-mask"/);
  assert.match(svg, /<clipPath id="landing-mask"/);
  const lot = svg.indexOf('STATION EAST / OPEN PARKING');
  const station = svg.indexOf('WATERFRONT STATION');
  const landing = svg.lastIndexOf('THE LANDING');
  assert.ok(station > 0 && landing > station && lot > 0);
  assert.ok(svg.length < 80000, 'keep illustration lightweight');
  assert.doesNotMatch(svg, /<script|<foreignObject|<image\b|(?:href|src)\s*=\s*["']https?:\/\//i, 'no remote images or script execution');
});

test('Spotlight loads the actual lookout artwork, stays accessible and makes the simulator easy to enter', () => {
  const spotlight = read('parts/home-gastown-spotlight.php');
  const css = read('assets/css/home-feature-previews.css');
  const manifest = read('scripts/theme_deploy_manifest.py');
  assert.match(spotlight, /GASTOWN<br><span>SIMULATOR\.<\/span>/);
  assert.match(spotlight, /555 W Cordova/);
  assert.match(spotlight, /Station East/);
  assert.match(spotlight, /The Landing/);
  assert.match(spotlight, /'\/assets\/illustrations\/gastown-station-east-lookout\.svg'/);
  assert.match(spotlight, /width="1000"/);
  assert.match(spotlight, /height="580"/);
  assert.match(spotlight, /alt="Artist's street-level view/);
  assert.match(spotlight, /ENTER GASTOWN/);
  assert.match(spotlight, /SHIP REPRESENTATIVE/);
  assert.match(css, /\.home-yvr-radar-deck > \.home-gastown-spotlight/);
  assert.match(css, /\.home-gastown-spotlight__art img/);
  assert.match(css, /@media \(max-width: 850px\)/);
  assert.ok(manifest.includes('"assets/illustrations/gastown-station-east-lookout.svg"'));
});
