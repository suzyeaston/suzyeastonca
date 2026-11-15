const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const root = path.resolve(__dirname, "..");
const read = p => fs.readFileSync(path.join(root, p), "utf8");

test("bio tells Suzy's story instead of selling services", () => {
  const bio = read("page-bio.php");
  for (const line of ["Music was the first language", "Minto", "Steve Albini", "The Appliance Latent Space", "Gastown", "Consent and control"]) {
    assert.ok(bio.toLowerCase().includes(line.toLowerCase()), line);
  }
  assert.doesNotMatch(bio, /AVAILABLE FOR:|WHERE I.M USEFUL|WORK WITH SUZY|senior technical roles/i);
  assert.match(bio, /home_url\( '\/projects\/' \)/);
  assert.match(bio, /aria-labelledby="se-story-title"/);
});

test("bio image and CSS dependencies are local theme deploy files", () => {
  const manifest = read("scripts/theme_deploy_manifest.py");
  const bio = read("page-bio.php");
  const dependencies = [
    "page-bio.php",
    "assets/css/bio-story.css",
    "assets/brand/meanwhile-october-portrait.webp",
    "assets/brand/bio-red-toque.jpg",
    "assets/brand/bio-coffee.jpg",
  ];
  for (const file of dependencies) {
    assert.ok(fs.existsSync(path.join(root, file)), file);
    assert.ok(manifest.includes('"' + file + '"'), file + " missing from production upload");
  }
  assert.match(bio, /loading="lazy"/);
  assert.match(bio, /alt="Suzy/);
  assert.match(read("assets/css/bio-story.css"), /max-width: 740px/);
});
