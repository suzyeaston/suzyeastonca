const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const root = path.resolve(__dirname, "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");

test("Meanwhile design overrides global arcade typography without changing style.css", () => {
  const styles = read("assets/css/blog.css");
  const main = read("style.css");
  assert.match(main, /Press Start 2P/, "arcade remains available");
  assert.match(styles, /MEANWHILE \/ AFTER THE NOISE/);
  assert.match(styles, /#main-content\.se-signal-log \.se-signal-log__article-body :is\(p,/);
  assert.match(styles, /background: transparent;/);
  assert.match(styles, /font-family: "DM Sans"/);
  assert.match(styles, /font-family: "Cormorant Garamond"/);
});

test("Meanwhile fixes sticky header overlap and honors small screens / reduced motion", () => {
  const styles = read("assets/css/blog.css");
  assert.match(styles, /body:is\(\.blog, \.single-post, \.category/);
  assert.match(styles, /padding-top: 0;/);
  assert.match(styles, /position: sticky;/);
  assert.match(styles, /background: #0c0b19;/);
  assert.match(styles, /@media \(max-width: 700px\)/);
  assert.match(styles, /prefers-reduced-motion: reduce/);
});

test("single post keeps WordPress content but removes exactly matching duplicate opening heading", () => {
  const source = read("single.php");
  assert.match(source, /apply_filters\( 'the_content', get_the_content\(\) \)/);
  assert.match(source, /preg_match\(/);
  assert.match(source, /if \( \$opening_text === \$post_title \)/);
  assert.match(source, /echo \$rendered_content/);
  assert.match(source, /'uncategorized' !== \$cat->slug/);
});

test("blog fonts are loaded only when blog assets are enqueued", () => {
  const source = read("inc/blog.php");
  assert.match(source, /function se_enqueue_blog_assets\(\)/);
  assert.match(source, /se_blog_is_active\(\)/);
  assert.match(source, /se-meanwhile-type/);
  assert.match(source, /'main-styles', 'se-meanwhile-type'/);
});
