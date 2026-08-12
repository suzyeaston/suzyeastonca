const assert = require("node:assert/strict");
const { execFileSync } = require("node:child_process");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");
const MANIFEST = path.join(ROOT, "scripts", "theme_deploy_manifest.py");
const DEPLOY_SCRIPT = path.join(ROOT, "scripts", "deploy-lousy-outages-ssh.py");

function runManifest(args = []) {
  return execFileSync("python3", [MANIFEST, ...args], {
    cwd: ROOT,
    encoding: "utf8",
  });
}

test("theme deploy manifest validates on main", () => {
  const output = runManifest(["validate"]);
  assert.match(output, /Theme deploy manifest OK/);
});

test("Meanwhile runtime files are listed in THEME_FILES", () => {
  const source = fs.readFileSync(MANIFEST, "utf8");
  const required = [
    "inc/blog.php",
    "home.php",
    "single.php",
    "archive.php",
    "category.php",
    "parts/home-signal-log.php",
    "parts/blog-card.php",
    "parts/blog-empty.php",
    "parts/blog-hero.php",
    "parts/blog-pagination.php",
    "assets/css/blog.css",
  ];
  for (const relative of required) {
    assert.match(source, new RegExp(`"${relative.replace("/", "\\/")}"`));
  }
});

test("inc/blog.php is ordered before functions.php in the manifest", () => {
  const source = fs.readFileSync(MANIFEST, "utf8");
  const blogIndex = source.indexOf('"inc/blog.php"');
  const functionsIndex = source.indexOf('"functions.php"');
  assert.ok(blogIndex > -1 && functionsIndex > -1);
  assert.ok(blogIndex < functionsIndex);
});

test("deploy script imports THEME_FILES from the shared manifest module", () => {
  const source = fs.readFileSync(DEPLOY_SCRIPT, "utf8");
  assert.match(source, /from theme_deploy_manifest import THEME_FILES/);
  assert.doesNotMatch(source, /THEME_FILES = \[/);
});
