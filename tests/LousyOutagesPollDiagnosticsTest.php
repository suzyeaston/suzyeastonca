<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (!function_exists('get_option')) { function get_option($k, $d = null){ return $d; } }
if (!function_exists('wp_date')) { function wp_date($f, $t = null){ return gmdate($f, (int) $t); } }
if (!function_exists('add_action')) { function add_action(...$args){ return true; } }
if (!function_exists('add_shortcode')) { function add_shortcode(...$args){ return true; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style(...$args){ return true; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script(...$args){ return true; } }
if (!function_exists('add_filter')) { function add_filter(...$args){ return true; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f){ return dirname($f) . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f){ return 'https://example.com/'; } }
if (!function_exists('home_url')) { function home_url($p=''){ return 'https://example.com' . $p; } }
if (!function_exists('register_activation_hook')) { function register_activation_hook(...$args){ return true; } }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(...$args){ return true; } }
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once __DIR__ . '/../plugins/lousy-outages/lousy-outages.php';

$unknown = lousy_outages_format_external_collection_summary(['sources_attempted'=>3,'stored'=>0,'errors'=>[]]);
if (strpos($unknown, 'Last unknown') !== false || strpos($unknown, 'No successful external collection yet') === false) { echo "FAIL\n"; exit(1); }

$normal = lousy_outages_format_external_collection_summary(['timestamp'=>'2026-05-04T11:43:00+00:00','sources_attempted'=>3,'stored'=>2,'errors'=>[]]);
if (strpos($normal, 'Last collection:') === false) { echo "FAIL\n"; exit(1); }

echo "OK\n";
