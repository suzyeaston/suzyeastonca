const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const root = path.resolve(__dirname, "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");
const asset = "assets/brand/meanwhile-october-portrait.webp";

test("October portrait is published as a real local image and in the theme deploy manifest", () => {
  assert.ok(fs.existsSync(path.join(root, asset)));
  const image = fs.readFileSync(path.join(root, asset));
  assert.equal(image.subarray(0, 4).toString("ascii"), "RIFF");
  assert.equal(image.subarray(8, 12).toString("ascii"), "WEBP");
  assert.match(read("scripts/theme_deploy_manifest.py"), /assets\/brand\/meanwhile-october-portrait\.webp/);
});

test("Photo is displayed only on the requested Meanwhile essay and has alt text", () => {
  const single = read("single.php");
  assert.match(single, /is_single\( 'what-we-leave-open' \)/);
  assert.match(single, /if \( \$show_october_portrait \)/);
  assert.match(single, /meanwhile-october-portrait\.webp/);
  assert.match(single, /alt="Suzy smiling/);
  assert.match(single, /get_template_directory_uri\(\)/);
});

test("Editorial frame stacks responsively on mobile", () => {
  const css = read("assets/css/blog.css");
  assert.match(css, /se-signal-log__article-head--with-portrait/);
  assert.match(css, /se-signal-log__october-portrait/);
  assert.match(css, /max-width: 720px/);
  assert.match(css, /aspect-ratio: 280 \/ 393/);
});
