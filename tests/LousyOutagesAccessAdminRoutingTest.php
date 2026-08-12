<?php
declare(strict_types=1);

/**
 * Admin/public routing separation for Lousy Outages access management.
 */

$GLOBALS['lo_test_options'] = [];
$GLOBALS['lo_test_pages'] = [];
$GLOBALS['lo_test_page_meta'] = [];
$GLOBALS['lo_test_next_page_id'] = 1;
$GLOBALS['lo_test_is_admin'] = false;
$GLOBALS['lo_test_is_page_slug'] = null;
$GLOBALS['lo_test_submenu'] = [];
$GLOBALS['lo_test_actions'] = [];

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {
    $GLOBALS['lo_test_actions'][] = [
        'hook' => $hook,
        'callback' => $callback,
        'priority' => $priority,
    ];
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void {}
function apply_filters($tag, $value) { return $value; }
function do_action($tag, ...$args): void {}
function sanitize_key($k) { return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $k)) ?? ''; }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function sanitize_email($e) { return trim(strtolower((string) $e)); }
function is_email($e) { return false !== strpos((string) $e, '@'); }
function wp_json_encode($v) { return json_encode($v); }
function wp_salt($s = '') { return 'salt'; }
function current_time($t = 'mysql', $gmt = false) { return $t === 'mysql' ? gmdate('Y-m-d H:i:s') : time(); }
function esc_html($v) { return (string) $v; }
function esc_url($v) { return (string) $v; }
function esc_attr($v) { return (string) $v; }
function rest_url($p = '') { return 'https://example.com/wp-json/' . ltrim((string) $p, '/'); }
function wp_create_nonce($a = '') { return 'nonce'; }
function wp_parse_url($url, $component = -1) { return parse_url((string) $url, $component); }
function trailingslashit($v) { return rtrim((string) $v, '/') . '/'; }
function home_url($path = '') { return 'https://example.com' . $path; }
function admin_url($path = '') { return 'https://example.com/wp-admin/' . ltrim((string) $path, '/'); }
function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['lo_test_options']) ? $GLOBALS['lo_test_options'][$key] : $default;
}
function update_option($key, $value, $autoload = null) {
    $GLOBALS['lo_test_options'][$key] = $value;
    return true;
}
function add_submenu_page($parent, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {
    $GLOBALS['lo_test_submenu'][] = compact('parent', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position');
    return 'lousy-outages_page_' . $menu_slug;
}
function is_admin() { return (bool) $GLOBALS['lo_test_is_admin']; }
function is_page($slug = '') {
    return $GLOBALS['lo_test_is_page_slug'] !== null && (string) $GLOBALS['lo_test_is_page_slug'] === (string) $slug;
}
function get_page_by_path($path) {
    $path = trim((string) $path, '/');
    foreach ($GLOBALS['lo_test_pages'] as $page) {
        if ((int) $page['post_parent'] > 0) {
            $parent_name = $GLOBALS['lo_test_pages'][$page['post_parent']]['post_name'] ?? '';
            $full = trim($parent_name . '/' . $page['post_name'], '/');
        } else {
            $full = $page['post_name'];
        }
        if ($full === $path) {
            return (object) $page;
        }
    }
    return null;
}
function get_post($id) {
    $id = (int) $id;
    return isset($GLOBALS['lo_test_pages'][$id]) ? (object) $GLOBALS['lo_test_pages'][$id] : null;
}
function wp_insert_post($args) {
    $id = (int) $GLOBALS['lo_test_next_page_id']++;
    $GLOBALS['lo_test_pages'][$id] = [
        'ID' => $id,
        'post_title' => (string) ($args['post_title'] ?? ''),
        'post_name' => (string) ($args['post_name'] ?? ''),
        'post_content' => (string) ($args['post_content'] ?? ''),
        'post_status' => (string) ($args['post_status'] ?? 'publish'),
        'post_type' => (string) ($args['post_type'] ?? 'page'),
        'post_parent' => (int) ($args['post_parent'] ?? 0),
    ];
    return $id;
}
function wp_update_post($args) {
    $id = (int) ($args['ID'] ?? 0);
    if (!isset($GLOBALS['lo_test_pages'][$id])) {
        return 0;
    }
    foreach (['post_title', 'post_name', 'post_content', 'post_status', 'post_type', 'post_parent'] as $field) {
        if (array_key_exists($field, $args)) {
            $GLOBALS['lo_test_pages'][$id][$field] = $args[$field];
        }
    }
    return $id;
}
function update_post_meta($id, $key, $value) {
    $GLOBALS['lo_test_page_meta'][(int) $id][$key] = $value;
    return true;
}
function get_post_meta($id, $key, $single = false) {
    $value = $GLOBALS['lo_test_page_meta'][(int) $id][$key] ?? '';
    return $single ? $value : [$value];
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function register_setting(...$args): void {}
function add_shortcode(...$args): void {}
function register_rest_route(...$args): void {}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function get_error_message() { return 'error'; }
    }
}

require_once __DIR__ . '/../lousy-outages/includes/Entitlements.php';
require_once __DIR__ . '/../lousy-outages/includes/CommerceStore.php';
require_once __DIR__ . '/../lousy-outages/includes/CommerceAdmin.php';
require_once __DIR__ . '/../lousy-outages/includes/Product.php';

use SuzyEaston\LousyOutages\CommerceAdmin;
use SuzyEaston\LousyOutages\Product;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

// Admin slug registers correctly under Lousy Outages as Manual Access.
$GLOBALS['lo_test_actions'] = [];
CommerceAdmin::bootstrap();
$menu_actions = array_values(array_filter(
    $GLOBALS['lo_test_actions'],
    static fn(array $a): bool => $a['hook'] === 'admin_menu' && $a['callback'] === [CommerceAdmin::class, 'menu']
));
$assert(count($menu_actions) === 1, 'Expected one admin_menu registration for CommerceAdmin::menu');
$assert((int) $menu_actions[0]['priority'] > 10, 'Manual Access must register after the parent Lousy Outages menu (priority > 10)');

$GLOBALS['lo_test_submenu'] = [];
CommerceAdmin::menu();
$assert(count($GLOBALS['lo_test_submenu']) === 1, 'Expected one Manual Access submenu registration');
$submenu = $GLOBALS['lo_test_submenu'][0];
$assert($submenu['menu_slug'] === 'lousy-outages-access-admin', 'Admin menu slug must be lousy-outages-access-admin');
$assert($submenu['menu_slug'] === CommerceAdmin::PAGE_SLUG, 'PAGE_SLUG constant must match registered menu slug');
$assert($submenu['menu_title'] === 'Manual Access', 'Menu title must be Manual Access');
$assert($submenu['parent'] === 'lousy-outages', 'Must nest under Lousy Outages menu');
$assert(($submenu['position'] ?? null) === 80, 'Manual Access should sit at the end of the Lousy Outages submenu');
$assert($submenu['menu_slug'] !== Product::LEGACY_BILLING_SLUG, 'Admin slug must not reuse legacy public billing slug');

$admin_src = (string) file_get_contents(__DIR__ . '/../lousy-outages/includes/CommerceAdmin.php');
$assert(!str_contains($admin_src, 'page=lousy-outages-billing'), 'Grant/revoke redirects must not use legacy admin slug');
$assert(str_contains($admin_src, 'wp_safe_redirect(self::admin_url())'), 'redirect_with_notice must use self::admin_url()');
$assert(str_contains($admin_src, 'Open public pricing'), 'Admin screen must link out to public pricing');
$assert(str_contains($admin_src, 'Open account page'), 'Admin screen must link out to account page');
$assert(
    CommerceAdmin::admin_url() === 'https://example.com/wp-admin/admin.php?page=lousy-outages-access-admin',
    'Canonical admin URL must be admin.php?page=lousy-outages-access-admin'
);
$assert(
    !str_contains(CommerceAdmin::admin_url(), '/wp-admin/lousy-outages-access-admin'),
    'Admin URL must not be a bare /wp-admin/{slug} path'
);

// Legacy public slug cannot replace/hijack the admin route.
$assert(CommerceAdmin::PAGE_SLUG !== Product::LEGACY_BILLING_SLUG, 'Admin and legacy public slugs must differ');
$product_src = (string) file_get_contents(__DIR__ . '/../lousy-outages/includes/Product.php');
$assert(!str_contains($product_src, "post_name' => 'lousy-outages-access-admin"), 'install_pages must never create the admin slug as a public page');
$assert(!str_contains($product_src, "post_name' => 'lousy-outages-billing"), 'install_pages must never recreate the legacy billing public page');

// Product::install_pages creates missing pricing/account under parent.
$GLOBALS['lo_test_pages'] = [];
$GLOBALS['lo_test_page_meta'] = [];
$GLOBALS['lo_test_next_page_id'] = 1;
$GLOBALS['lo_test_options'] = [];

Product::install_pages();
$parent = get_page_by_path('lousy-outages');
$pricing = get_page_by_path('lousy-outages/pricing');
$account = get_page_by_path('lousy-outages/account');
$assert($parent !== null, 'Parent /lousy-outages/ page must be created when missing');
$assert($pricing !== null, 'Missing pricing child page must be created');
$assert($account !== null, 'Missing account child page must be created');
$assert((int) $pricing->post_parent === (int) $parent->ID, 'Pricing must be a child of /lousy-outages/');
$assert((int) $account->post_parent === (int) $parent->ID, 'Account must be a child of /lousy-outages/');
$assert((string) get_post_meta((int) $pricing->ID, '_wp_page_template', true) === 'page-lousy-outages-pricing.php', 'Pricing template must be assigned');
$assert((string) get_post_meta((int) $account->ID, '_wp_page_template', true) === 'page-lousy-outages-account.php', 'Account template must be assigned');
$assert(str_contains((string) $account->post_content, '[lousy_outages_account]'), 'Account page content must include the account shortcode');

$page_count_after_first = count($GLOBALS['lo_test_pages']);
$pricing_id = (int) $pricing->ID;
$account_id = (int) $account->ID;
$pricing_content = (string) $pricing->post_content;

// Running page repair twice creates no duplicates; existing IDs retained.
Product::install_pages();
$assert(count($GLOBALS['lo_test_pages']) === $page_count_after_first, 'Second install_pages must not create duplicate pages');
$pricing2 = get_page_by_path('lousy-outages/pricing');
$account2 = get_page_by_path('lousy-outages/account');
$assert((int) $pricing2->ID === $pricing_id, 'Existing pricing page ID must be retained');
$assert((int) $account2->ID === $account_id, 'Existing account page ID must be retained');
$assert((string) $pricing2->post_content === $pricing_content, 'Existing pricing content must be preserved');

// Existing child pages are retained while parent/template are repaired.
$GLOBALS['lo_test_pages'][$pricing_id]['post_parent'] = 0;
$GLOBALS['lo_test_page_meta'][$pricing_id]['_wp_page_template'] = 'default';
Product::install_pages();
$assert(count($GLOBALS['lo_test_pages']) === $page_count_after_first, 'Repair must not duplicate when fixing parent/template');
$repaired = get_post($pricing_id);
$assert((int) $repaired->post_parent === (int) $parent->ID, 'Repair must reattach pricing to parent');
$assert((string) get_post_meta($pricing_id, '_wp_page_template', true) === 'page-lousy-outages-pricing.php', 'Repair must restore pricing template');

// Versioned repair is idempotent.
Product::maybe_repair_pages();
$assert((string) get_option('lousy_outages_product_pages_version') === Product::PAGES_VERSION, 'Page repair must stamp product pages version');
$before = count($GLOBALS['lo_test_pages']);
Product::maybe_repair_pages();
$assert(count($GLOBALS['lo_test_pages']) === $before, 'Versioned repair must not recreate pages when already healthy');

// No frontend canonical redirect during is_admin() or /wp-admin/.
$GLOBALS['lo_test_is_admin'] = true;
$GLOBALS['lo_test_is_page_slug'] = Product::LEGACY_BILLING_SLUG;
$_SERVER['REQUEST_URI'] = '/lousy-outages-billing/';
$_GET = [];
$assert(Product::legacy_billing_redirect_target() === null, 'No frontend canonical redirect during is_admin()');

$GLOBALS['lo_test_is_admin'] = false;
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=lousy-outages-access-admin';
$_GET = ['page' => CommerceAdmin::PAGE_SLUG];
$GLOBALS['lo_test_is_page_slug'] = null;
$assert(Product::legacy_billing_redirect_target() === null, 'Must not redirect wp-admin access-admin requests');

$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=lousy-outages-billing';
$_GET = ['page' => Product::LEGACY_BILLING_SLUG];
$assert(Product::legacy_billing_redirect_target() === null, 'Must not redirect wp-admin even for legacy page query');

$_SERVER['REQUEST_URI'] = '/lousy-outages-billing/';
$_GET = [];
$GLOBALS['lo_test_is_page_slug'] = Product::LEGACY_BILLING_SLUG;
$assert(
    Product::legacy_billing_redirect_target() === 'https://example.com/lousy-outages/pricing/',
    'Legacy public /lousy-outages-billing/ must resolve to /lousy-outages/pricing/'
);

// Path-only detection when is_page() is false.
$GLOBALS['lo_test_is_page_slug'] = null;
$_SERVER['REQUEST_URI'] = '/lousy-outages-billing/';
$_GET = [];
$assert(
    Product::legacy_billing_redirect_target() === 'https://example.com/lousy-outages/pricing/',
    'Legacy path must redirect even without is_page() match'
);

$deploy_src = (string) file_get_contents(__DIR__ . '/../scripts/deploy-lousy-outages-ssh.py');
$assert(str_contains($deploy_src, '"page-lousy-outages-pricing.php"'), 'Theme manifest must include pricing template');
$assert(str_contains($deploy_src, '"page-lousy-outages-account.php"'), 'Theme manifest must include account template');

$workflow = (string) file_get_contents(__DIR__ . '/../.github/workflows/deploy-production.yml');
$assert(str_contains($workflow, 'page-lousy-outages-pricing.php'), 'Deploy path filters must include pricing template');
$assert(str_contains($workflow, 'page-lousy-outages-account.php'), 'Deploy path filters must include account template');

echo "ok - access-admin slug, page self-heal, and legacy public redirect guards\n";
