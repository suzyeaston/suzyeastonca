const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const root = path.resolve(__dirname, "..");
const read = name => fs.readFileSync(path.join(root, name), "utf8");

test("the article has a dedicated 1200x630 jpeg for Facebook and Threads", () => {
  assert.match(read("inc/seo.php"), /function se_what_we_leave_open_social_image_url/);
  assert.match(read("inc/seo.php"), /what-we-leave-open-social\.jpg/);
  assert.match(read("inc/seo.php"), /'what-we-leave-open' === \$post->post_name/);
  assert.match(read("header.php"), /og:image:width" content="1200"/);
  assert.match(read("header.php"), /og:image:height" content="630"/);
  assert.match(read("header.php"), /og:image:type" content="image\/jpeg"/);
  assert.match(read("inc/blog.php"), /se_post_social_image_url\( \$post \)/);
});

test("production creates and uploads the social image before WordPress files", () => {
  const workflow = read(".github/workflows/deploy-production.yml");
  assert.match(workflow, /pip install paramiko pillow/);
  assert.ok(workflow.indexOf("Build Meanwhile social thumbnail") < workflow.indexOf("Validate theme deploy manifest"));
  assert.match(read("scripts/theme_deploy_manifest.py"), /assets\/brand\/what-we-leave-open-social\.jpg/);
  const source = read("scripts/build_meanwhile_social_card.py");
  assert.match(source, /W, H = 1200, 630/);
  assert.match(source, /assets\/brand\/meanwhile-october-portrait\.webp/);
  assert.match(source, /quality=86/);
});
