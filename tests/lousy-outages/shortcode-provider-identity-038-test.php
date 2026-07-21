<?php
declare(strict_types=1);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../../');
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
foreach (['add_action','add_filter','register_activation_hook','register_deactivation_hook','add_shortcode','add_management_page','wp_enqueue_style','wp_enqueue_script','wp_localize_script'] as $fn) { if (!function_exists($fn)) { eval('function '.$fn.'(...$a){ $GLOBALS["lo_calls"][]=["'.$fn.'",$a]; return true; }'); } }
if (!function_exists('has_action')) { function has_action(){ return false; } }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f){ return dirname($f).'/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f){ return 'https://example.test/wp-content/plugins/lousy-outages/'; } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; } }
if (!function_exists('sanitize_html_class')) { function sanitize_html_class($v){ return sanitize_key($v); } }
if (!function_exists('sanitize_title')) { function sanitize_title($v){ return sanitize_key(str_replace(' ', '-', (string)$v)); } }
if (!function_exists('esc_attr')) { function esc_attr($v){ return htmlspecialchars((string)$v, ENT_QUOTES); } }
if (!function_exists('esc_html')) { function esc_html($v){ return htmlspecialchars((string)$v, ENT_QUOTES); } }
if (!function_exists('esc_url')) { function esc_url($v){ return (string)$v; } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($v){ return (string)$v; } }
if (!function_exists('trailingslashit')) { function trailingslashit($v){ return rtrim((string)$v, '/').'/'; } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($u,$c=-1){ return parse_url((string)$u, $c); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v){ return strip_tags((string)$v); } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $GLOBALS['lo_options'][$k] ?? ($k==='date_format'?'M j, Y':($k==='time_format'?'g:i a':$d)); } }
if (!function_exists('update_option')) { function update_option($k,$v,$a=null){ $GLOBALS['lo_options'][$k]=$v; return true; } }
if (!function_exists('get_transient')) { function get_transient($k){ return false; } }
if (!function_exists('set_transient')) { function set_transient(){ return true; } }
if (!function_exists('delete_transient')) { function delete_transient(){} }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('wp_date')) { function wp_date($f,$t=null){ return gmdate($f,$t?:time()); } }
if (!function_exists('home_url')) { function home_url($p=''){ return 'https://example.test'.$p; } }
if (!function_exists('rest_url')) { function rest_url($p=''){ return 'https://example.test/wp-json/'.$p; } }
if (!function_exists('current_user_can')) { function current_user_can(){ return false; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce(){ return 'nonce'; } }
if (!function_exists('__')) { function __($v){ return $v; } }
if (!function_exists('esc_html__')) { function esc_html__($v){ return $v; } }
$GLOBALS['wpdb'] = new class { public $prefix='wp_'; public function esc_like($v){ return addcslashes((string)$v, '_%\\'); } public function get_var($q=null){ return 0; } public function get_results($q=null,$fmt=null){ return []; } public function prepare($q,...$a){ return $q; } };
require __DIR__.'/../../lousy-outages/lousy-outages.php';
function fail($m){ fwrite(STDERR,"FAIL: $m\n"); exit(1); }
function ok($c,$m){ if(!$c) fail($m); }
$now = '2026-07-21T12:00:00+00:00';
$inc = fn($id,$status='investigating') => ['id'=>$id,'title'=>'Incident '.$id,'status'=>$status,'updatedAt'=>$now];
$GLOBALS['lo_options']['lousy_outages_snapshot'] = ['schema_version'=>LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION,'fetched_at'=>$now,'source'=>'snapshot','providers'=>[
 ['id'=>'cloudflare','provider'=>'Cloudflare','name'=>'Cloudflare','stateCode'=>'degraded','tile_kind'=>'outage','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[$inc('c1'),$inc('c2')]],
 ['id'=>'openai','provider'=>'OpenAI','name'=>'OpenAI','stateCode'=>'degraded','tile_kind'=>'outage','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[$inc('o1')]],
 ['id'=>'aws','provider'=>'AWS','name'=>'AWS','stateCode'=>'degraded','tile_kind'=>'outage','verification_status'=>'verified','checked_at'=>$now,'incidents'=>[$inc('a1'),$inc('a2'),$inc('a3')]],
]];
$state = lousy_outages_get_current_state();
$ids = array_values(array_unique(array_map(fn($p)=>SuzyEaston\LousyOutages\provider_identity($p), $state['providers'])));
sort($ids);
ok($ids === ['aws','cloudflare','openai'], 'provider identity resolves to aws, cloudflare, openai');
ok(($state['meta']['active_outage_count'] ?? 0) === 6, 'active incident count remains incident count');
ok(count($state['meta']['current_official_provider_ids'] ?? []) === 3, 'affected-provider count remains three');
$html = SuzyEaston\LousyOutages\render_shortcode();
foreach (['cloudflare','openai','aws'] as $id) {
 ok(substr_count($html, 'data-provider-id="'.$id.'"') === 1, 'SSR has exactly one card for '.$id);
 ok(strpos($html, 'value="'.$id.'"') !== false, 'provider checkbox uses machine id '.$id);
}
ok(strpos($html, 'data-provider-id="Cloudflare"') === false, 'no human-readable provider id in SSR');
ok(substr_count($html, 'Assuming operational') === 0, 'no placeholders created for live outage providers');
ok(strpos($html, 'data-lo-section-grid="incidents"') !== false, 'active incidents grid exists');
$config = null; foreach (($GLOBALS['lo_calls'] ?? []) as $call) { if ($call[0] === 'wp_localize_script' && ($call[1][1] ?? '') === 'LousyOutagesConfig') { $config = $call[1][2]; } }
ok(is_array($config), 'initial localized JavaScript payload emitted');
$localizedIds = [];
foreach ($config['initial']['providers'] as $provider) { $localizedIds[] = $provider['id'] ?? ''; }
foreach (['cloudflare','openai','aws'] as $expectedId) { ok(in_array($expectedId, $localizedIds, true), 'live tile is not rejected by enabled-provider filtering: '.$expectedId); }
foreach ($config['initial']['providers'] as $provider) { if (in_array($provider['id'] ?? '', ['cloudflare','openai','aws'], true)) { ok(($provider['tile_kind'] ?? '') === 'outage', 'card eligible for Active incidents: '.$provider['id']); } }
$js = file_get_contents(__DIR__.'/../../lousy-outages/assets/lousy-outages.js');
ok(strpos($js, 'var id = getProviderId(provider);') !== false, 'JavaScript finds SSR cards by shared normalized provider id');
ok(strpos($js, 'provider.provider_id || provider.id || provider.provider') !== false, 'JavaScript provider-ID selection prefers provider_id, id, provider');
echo "shortcode-provider-identity-038-ok\n";
