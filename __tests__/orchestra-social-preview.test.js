const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const root = path.resolve(__dirname, "..");
const read = (filename) => fs.readFileSync(path.join(root, filename), "utf8");

test("Part II Open Graph image is built from Suzy's original Futureproof performance photo", () => {
  const builder = read("scripts/build_orchestra_social_card.py");
  assert.match(builder, /IMG_3557\(2\)\.JPG/);
  assert.match(builder, /W, H = 1200, 630/);
  assert.match(builder, /orchestra-in-8gb-social\.jpg/);
  assert.match(builder, /An Orchestra/);
  assert.match(read("inc/seo.php"), /an-orchestra-in-8gb/);
  assert.match(read("inc/seo.php"), /assets\/brand\/orchestra-in-8gb-social\.jpg/);
  assert.match(read("header.php"), /'an-orchestra-in-8gb' === get_post_field/);
  assert.match(read("header.php"), /An Orchestra in 8GB, Suzy Easton performing/);
  assert.match(read("scripts/theme_deploy_manifest.py"), /assets\/brand\/orchestra-in-8gb-social\.jpg/);
});

test("Part II build and live OG validation are enforced in theme deploy workflow", () => {
  const yml = read(".github/workflows/deploy-production.yml");
  const builder = yml.indexOf("python3 scripts/build_orchestra_social_card.py");
  const validate = yml.indexOf("Validate theme deploy manifest");
  const deploy = yml.indexOf("Deploy theme files");
  const live = yml.indexOf("python3 scripts/verify_orchestra_social_preview.py");
  assert.ok(builder > 0 && builder < validate, "Image must be generated before validation");
  assert.ok(validate < deploy && deploy < live, "Verify the deployed thumbnail after upload");
  assert.ok(yml.includes("- 'scripts/verify_orchestra_social_preview.py'"), "Checker edits trigger the theme verification job");
});

test("Part II live preview checker targets the published post slug", () => {
  const post = read("content/meanwhile/2026-11-an-orchestra-in-8gb.md");
  const checker = read("scripts/verify_orchestra_social_preview.py");
  const slug = post.match(/^slug:\s*([^\s]+)$/m)?.[1];
  assert.equal(slug, "an-orchestra-in-8gb");
  assert.ok(checker.includes('ARTICLE = "https://www.suzyeaston.ca/2026/11/02/' + slug + '/"'));
  assert.doesNotMatch(checker, /the-an-orchestra-in-8gb/);
});
