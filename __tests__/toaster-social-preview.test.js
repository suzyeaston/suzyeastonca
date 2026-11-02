const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const root = path.resolve(__dirname, "..");
const read = (filename) => fs.readFileSync(path.join(root, filename), "utf8");

test("The Toaster Was a Decoy gets a dedicated 1200x630 preview from its real performance photo", () => {
  const script = read("scripts/build_toaster_social_card.py");
  assert.match(script, /IMG_3556\(2\)\.JPG/);
  assert.match(script, /W, H = 1200, 630/);
  assert.match(script, /toaster-was-a-decoy-social\.jpg/);
  assert.match(read("inc/seo.php"), /the-toaster-was-a-decoy/);
  assert.match(read("inc/seo.php"), /assets\/brand\/toaster-was-a-decoy-social\.jpg/);
  assert.match(read("header.php"), /The Toaster Was a Decoy, Suzy Easton performing at Futureproof Festival/);
  assert.match(read("scripts/theme_deploy_manifest.py"), /assets\/brand\/toaster-was-a-decoy-social\.jpg/);
});

test("Build is invoked before the image is validated and uploaded by production deployment", () => {
  const workflow = read(".github/workflows/deploy-production.yml");
  const build = workflow.indexOf("python3 scripts/build_toaster_social_card.py");
  const validate = workflow.indexOf("Validate theme deploy manifest");
  const deploy = workflow.indexOf("Deploy theme files");
  assert.ok(build > 0 && build < validate && validate < deploy);
});
