<?php
declare(strict_types=1);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../../');
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
foreach (['add_action','add_filter','register_activation_hook','register_deactivation_hook','add_shortcode','has_action','add_management_page'] as $fn) { if (!function_exists($fn)) { eval('function '.$fn.'(...$a){ return false; }'); } }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f){ return dirname($f).'/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f){ return 'https://example.test/wp-content/plugins/lousy-outages/'; } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $GLOBALS['lo_options'][$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v,$a=null){ $GLOBALS['lo_options'][$k]=$v; return true; } }
if (!function_exists('get_transient')) { function get_transient($k){ return false; } }
if (!function_exists('set_transient')) { function set_transient(){ return true; } }
if (!function_exists('delete_transient')) { function delete_transient(){} }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('wp_date')) { function wp_date($f,$t=null){ return gmdate($f,$t?:time()); } }
if (!function_exists('home_url')) { function home_url($p=''){ return 'https://example.test'.$p; } }
if (!function_exists('rest_url')) { function rest_url($p=''){ return 'https://example.test/wp-json/'.$p; } }
require __DIR__.'/../../lousy-outages/lousy-outages.php';
$now = gmdate('c');
$GLOBALS['lo_options']['lousy_outages_snapshot'] = ['schema_version'=>LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION,'fetched_at'=>$now,'source'=>'fixture','providers'=>[
 ['id'=>'openai','name'=>'OpenAI','stateCode'=>'operational','tile_kind'=>'operational','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[['id'=>'o1','title'=>'OpenAI incident','status'=>'investigating','updatedAt'=>$now]]],
 ['id'=>'cloudflare','name'=>'Cloudflare','stateCode'=>'operational','tile_kind'=>'operational','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[['id'=>'c1','title'=>'Cloudflare incident','status'=>'identified','updatedAt'=>$now]]],
 ['id'=>'aws','name'=>'AWS','stateCode'=>'operational','tile_kind'=>'operational','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[
  ['id'=>'a1','title'=>'AWS incident 1','status'=>'investigating','updatedAt'=>$now],['id'=>'a2','title'=>'AWS incident 2','status'=>'identified','updatedAt'=>$now],['id'=>'a3','title'=>'AWS incident 3','status'=>'monitoring','updatedAt'=>$now]
 ]],
]];
$state = lousy_outages_get_current_state();
$fail = fn($m) => (fwrite(STDERR, "FAIL: $m\n") && exit(1));
if (count($state['outages']) !== 5) $fail('current_state.outages must contain 5 records');
if (($state['meta']['active_outage_count'] ?? 0) !== 5) $fail('meta.active_outage_count must equal 5');
if (count(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents()) !== 5) $fail('homepage summary incident source must see 5 records');
[$path,$url] = \SuzyEaston\LousyOutages\locate_assets_base();
if (!str_contains($url, '/wp-content/plugins/lousy-outages/assets/')) $fail('assets must load from plugin directory');
echo "live-parity-036-ok\n";
