const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const root = path.resolve(__dirname, "..");
const read = (p) => fs.readFileSync(path.join(root, p), "utf8");

test("WordPress creation is a deploy step after theme upload, not just a virtual PHP route", () => {
  const workflow = read(".github/workflows/deploy-production.yml");
  const deploy = workflow.indexOf("name: Deploy theme files");
  const create = workflow.indexOf("name: Create and verify published WordPress Events page");
  const imageCheck = workflow.indexOf("name: Verify live Open Graph image for The Toaster Was a Decoy");
  assert.ok(deploy > 0 && create > deploy && imageCheck > create,
    "provisioning must occur after theme files, before unrelated image verification");
  assert.match(workflow, /run: python3 scripts\/ensure_wordpress_public_pages\.py/);
  assert.match(workflow, /'scripts\/wp_ensure_public_pages\.php'/);
  assert.match(workflow, /'scripts\/ensure_wordpress_public_pages\.py'/);
});

test("PHP importer is CLI-only, idempotent, and confirms a real published WP Page", () => {
  const php = read("scripts/wp_ensure_public_pages.php");
  for (const marker of [
    "PHP_SAPI !== 'cli'", "get_page_by_path( $slug, OBJECT, 'page' )",
    "'vancouver-events'", "'page-vancouver-events.php'", "'post_type' => 'page'",
    "'post_status' => 'publish'", "wp_insert_post(", "wp_update_post(",
    "get_post( $id )", "get_page_template_slug( $id )", "get_permalink( $id )",
    "SUZY_WP_PAGE:", "clean_post_cache( $id )"
  ]) assert.ok(php.includes(marker), marker);
  assert.doesNotMatch(php, /wp_delete_post|wp_trash_post|DELETE FROM/i);
  assert.doesNotMatch(php, /update_option\(/);
  assert.match(php, /'unchanged'/);
});

test("deployment uses existing SSH secrets, verifies JSON read-back, and cleans up staged script", () => {
  const py = read("scripts/ensure_wordpress_public_pages.py");
  for (const marker of [
    "LOUSY_SSH_HOST", "LOUSY_SSH_USER", "LOUSY_SSH_PASSWORD",
    "wp_ensure_public_pages.php", "SUZY_WP_PAGE:", "sftp.put",
    "sftp.chmod", "client.exec_command", "json.loads",
    "page-vancouver-events.php", "/vancouver-events", "rm -f"
  ]) assert.ok(py.includes(marker), marker);
  assert.match(py, /finally:/);
  assert.doesNotMatch(py, /https?:\/\/[^\s]*wp-json\/wp\/v2\/pages/);
});

test("hub template remains available and production manifest still includes it", () => {
  const page = read("page-vancouver-events.php");
  const manifest = read("scripts/theme_deploy_manifest.py");
  assert.match(page, /Template Name: Vancouver Events/);
  assert.match(manifest, /"page-vancouver-events\.php"/);
  assert.match(read("inc/vancouver-events.php"), /page-vancouver-events\.php/);
});
