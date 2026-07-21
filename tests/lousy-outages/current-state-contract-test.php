<?php
declare(strict_types=1);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../../');
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
foreach (['add_action','add_filter','register_activation_hook','register_deactivation_hook','add_shortcode','has_action','add_management_page'] as $fn) { if (!function_exists($fn)) { eval('function '.$fn.'(...$a){}'); } }
if (!function_exists('has_action')) { function has_action(){ return false; } }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f){ return dirname($f).'/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f){ return 'https://example.test/'; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled(){ return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event(){ return true; } }
if (!function_exists('wp_clear_scheduled_hook')) { function wp_clear_scheduled_hook(){} }
if (!function_exists('wp_date')) { function wp_date($f,$t=null){ return gmdate($f,$t?:time()); } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $GLOBALS['lo_options'][$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v,$a=null){ $GLOBALS['lo_options'][$k]=$v; return true; } }
if (!function_exists('get_transient')) { function get_transient($k){ return $GLOBALS['lo_transients'][$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$ttl=0){ $GLOBALS['lo_transients'][$k]=$v; return true; } }
if (!function_exists('delete_transient')) { function delete_transient($k){ unset($GLOBALS['lo_transients'][$k]); } }
if (!function_exists('__')) { function __($v){ return $v; } }
if (!function_exists('esc_html__')) { function esc_html__($v){ return $v; } }
require __DIR__ . '/../../lousy-outages/lousy-outages.php';
function assert_true($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: $m\n"); exit(1);} }
$now = gmdate('c'); $old = gmdate('c', time() - 2 * HOUR_IN_SECONDS);
$snapshot = ['providers'=>[
 ['id'=>'one','name'=>'One','stateCode'=>'degraded','tile_kind'=>'outage','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[
  ['id'=>'i1','title'=>'First','status'=>'investigating','updatedAt'=>$now],
  ['id'=>'i2','title'=>'Second','status'=>'identified','updatedAt'=>$now],
  ['id'=>'i3','title'=>'Resolved','status'=>'resolved','updatedAt'=>$now],
 ]],
 ['id'=>'sig','name'=>'Signal','stateCode'=>'degraded','tile_kind'=>'signal','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[]],
 ['id'=>'fail','name'=>'Fail','stateCode'=>'unknown','verification_status'=>'failed','error'=>'timeout','checked_at'=>$now,'incidents'=>[]],
 ['id'=>'stale','name'=>'Stale','stateCode'=>'degraded','tile_kind'=>'signal','verification_status'=>'verified','checked_at'=>$old,'incidents'=>[]],
 ['id'=>'ok','name'=>'Okay','stateCode'=>'operational','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[]],
]];
$state = lousy_outages_current_state_from_snapshot($snapshot);
assert_true(count($state['outages'])===2, 'one provider with two active incidents yields two outage records');
assert_true(count($state['signals'])===1, 'fresh provider signal counted once');
assert_true(count($state['unverified'])===1, 'failed provider is unverified');
assert_true($state['meta']['active_outage_count']===2 && $state['meta']['signal_count']===1 && $state['meta']['unverified_count']===1, 'meta counts match lanes');
assert_true(count($state['outages']) + count($state['signals']) === $state['meta']['active_outage_count'] + $state['meta']['signal_count'], 'homepage count parity');
$GLOBALS['lo_options']['lousy_outages_snapshot'] = $snapshot + [
    'schema_version' => LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION,
    'fetched_at' => $now,
    'source' => 'test',
    'current_state' => ['outages'=>[], 'signals'=>[], 'unverified'=>[], 'operational'=>[], 'meta'=>['active_outage_count'=>0]],
];
$recomputed = lousy_outages_get_current_state();
assert_true(count($recomputed['outages'])===2, 'get_current_state recomputes inconsistent stored current_state');
assert_true($recomputed['meta']['active_outage_count']===2, 'recomputed current_state keeps incident count separate from providers');
echo "OK\n";
