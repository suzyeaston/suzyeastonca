<?php
require_once __DIR__ . '/../lousy-outages/includes/SignalSourceInterface.php';
require_once __DIR__ . '/../lousy-outages/includes/Sources/IntelConduitSources.php';
require_once __DIR__ . '/../lousy-outages/includes/Sources/SyntheticCanarySource.php';

if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return (string)$v; } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($v){ return (string)$v; } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_]/','',(string)$v)); } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($v){ return (string)$v; } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($u,$c=-1){ return parse_url($u,$c); } }
if (!function_exists('apply_filters')) { function apply_filters($h,$v){ return $v; } }
if (!function_exists('wp_remote_get')) { function wp_remote_get($u,$a=[]){ return ['body'=>'{"incidents":[{"id":"1","name":"API outage","status":"investigating","shortlink":"https://status.example/inc"}]}']; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($r){ return $r['body'] ?? ''; } }
if (!function_exists('is_wp_error')) { function is_wp_error($v){ return false; } }
if (!function_exists('get_transient')) { function get_transient($k){ return false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$t){ return true; } }
if (!function_exists('gmdate')) {}

$src = new SuzyEaston\LousyOutages\Sources\StatuspageIntelSource();
$rows = $src->collect();
if (!is_array($rows)) { throw new RuntimeException('statuspage collect failed'); }
echo "PASS\n";
