<?php
declare(strict_types=1);

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../../');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('YEAR_IN_SECONDS')) define('YEAR_IN_SECONDS', 31536000);
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f){ return dirname($f) . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f){ return 'https://example.com/wp-content/lousy-outages/'; } }
if (!function_exists('add_action')) { function add_action(...$args){} }
if (!function_exists('add_filter')) { function add_filter(...$args){} }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }
if (!function_exists('add_shortcode')) { function add_shortcode(...$args){} }
if (!function_exists('register_activation_hook')) { function register_activation_hook(...$args){} }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(...$args){} }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled(){ return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event(){ return true; } }
if (!function_exists('wp_clear_scheduled_hook')) { function wp_clear_scheduled_hook(){} }
if (!function_exists('wp_date')) { function wp_date($format,$timestamp=null){ return gmdate($format, $timestamp ? (int)$timestamp : time()); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($url,$component=-1){ return parse_url((string)$url, $component); } }
if (!function_exists('trailingslashit')) { function trailingslashit($v){ return rtrim((string)$v,'/').'/'; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v){ return strip_tags((string)$v); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_]/','',strtolower((string)$v)) ?? ''; } }
if (!function_exists('esc_html__')) { function esc_html__($v){ return $v; } }
if (!function_exists('__')) { function __($v){ return $v; } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $GLOBALS['lo_options'][$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v,$autoload=null){ $GLOBALS['lo_options'][$k]=$v; return true; } }
if (!function_exists('get_transient')) { function get_transient($k){ return $GLOBALS['lo_transients'][$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$ttl=0){ $GLOBALS['lo_transients'][$k]=$v; return true; } }

require __DIR__ . '/../../lousy-outages/lousy-outages.php';

use SuzyEaston\LousyOutages\Fetcher;
use function SuzyEaston\LousyOutages\Adapters\from_rss_atom;

function assert_true($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
function call_private($object, string $method, array $args) { $ref = new ReflectionMethod($object, $method); $ref->setAccessible(true); return $ref->invokeArgs($object, $args); }

$GLOBALS['lo_options'] = ['lousy_outages_last_poll' => '2026-07-19T10:00:00+00:00'];
$GLOBALS['lo_transients'] = [];
$xml = '<rss><channel><item><title>Operational issue - Multiple services (UAE)</title><link>https://status.aws.amazon.com/</link><pubDate>Thu, 30 Apr 2026 12:00:00 GMT</pubDate><description><![CDATA[We are experiencing a service disruption affecting multiple services in the UAE region (ME-CENTRAL-1). Full restoration is expected to take several months.]]></description></item></channel></rss>';
$normalized = from_rss_atom($xml);
$fetcher = new Fetcher();
$provider = ['id'=>'aws','name'=>'AWS','provider'=>'AWS','status_url'=>'https://status.aws.amazon.com/','type'=>'rss'];
$state = call_private($fetcher, 'assemble_result', [[
  'id'=>'aws','name'=>'AWS','provider'=>'AWS','status'=>'unknown','status_label'=>'Unknown','summary'=>'Waiting','message'=>'Waiting','updated_at'=>'2026-07-19T10:00:00+00:00','checked_at'=>'2026-07-19T10:00:00+00:00','url'=>'https://status.aws.amazon.com/','incidents'=>[],'error'=>null,'source_type'=>'rss'
], $normalized, $provider]);
$state = call_private($fetcher, 'apply_tile_metadata', [$state, $provider, false]);
$snapshot = lousy_outages_build_snapshot(['aws'=>$state], '2026-07-19T10:00:00+00:00', 'test');
$aws = $snapshot['providers'][0];
$incident = $aws['incidents'][0] ?? [];
assert_true($snapshot['schema_version'] === LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION, 'schema version is stored');
assert_true($aws['tile_kind'] === 'outage', 'AWS is outage tile');
assert_true(count($aws['incidents']) === 1, 'AWS active incident serialized');
assert_true($incident['display_title'] === 'Multiple AWS services disrupted in UAE (ME-CENTRAL-1)', 'display title enriched');
assert_true($incident['source_title'] === 'Operational issue - Multiple services (UAE)', 'source title preserved');
assert_true($incident['scope'] === 'regional' && $incident['region_name'] === 'UAE' && $incident['region_code'] === 'ME-CENTRAL-1', 'regional metadata preserved');
assert_true($incident['is_long_running'] === true, 'long-running preserved');
assert_true(substr($incident['last_official_update'], 0, 10) === '2026-04-30', 'official update remains Apr 30');
assert_true(substr($incident['checked_at'], 0, 10) === '2026-07-19', 'checked_at is separate check time');
assert_true($aws['summary'] !== 'Major outage reported.', 'provider summary is not generic');

// Migration: old snapshots are rejected, current snapshots reused from cache.
$legacy = ['providers'=>[['id'=>'aws','name'=>'AWS','tile_kind'=>'signal','summary'=>'Major outage reported.','incidents'=>[]]], 'fetched_at'=>'2026-07-19T09:00:00+00:00'];
$GLOBALS['lo_transients'][lousy_outages_snapshot_cache_key()] = $legacy;
$GLOBALS['lo_options']['lousy_outages_snapshot'] = $legacy;
$GLOBALS['lo_options']['lousy_outages_states'] = ['aws'=>$state];
$migrated = lousy_outages_get_snapshot(false);
assert_true(($migrated['schema_version'] ?? 0) === LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION, 'legacy unversioned snapshot rebuilt');
assert_true(($migrated['providers'][0]['incidents'][0]['display_title'] ?? '') === 'Multiple AWS services disrupted in UAE (ME-CENTRAL-1)', 'rebuilt summary includes expanded metadata');
$current = lousy_outages_get_snapshot(false);
assert_true($current === $migrated, 'current-version transient reused without rebuild churn');

echo json_encode($aws, JSON_PRETTY_PRINT) . "\nOK\n";
