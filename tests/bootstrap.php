<?php
declare(strict_types=1);
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS',60);
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('add_filter')) { function add_filter($h,$c,$p=10,$a=1){} }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }
if (!function_exists('do_action')) { function do_action($tag,...$args): void {} }
if (!function_exists('sanitize_key')) { function sanitize_key($k){ return preg_replace('/[^a-z0-9_]/','',strtolower((string)$k))??''; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return trim(strip_tags((string)$t)); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e){ return trim(strtolower((string)$e)); } }
if (!function_exists('is_email')) { function is_email($e){ return false!==strpos((string)$e,'@'); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('wp_salt')) { function wp_salt($s=''){ return 'salt'; } }
if (!function_exists('current_time')) { function current_time($t='mysql',$gmt=false){ return $t==='mysql' ? gmdate('Y-m-d H:i:s') : time(); } }
if (!function_exists('esc_html')) { function esc_html($v){ return (string)$v; } }
if (!function_exists('esc_url')) { function esc_url($v){ return (string)$v; } }
if (!function_exists('home_url')) { function home_url($p=''){ return 'https://example.com'.$p; } }
if (!function_exists('rest_url')) { function rest_url($p=''){ return 'https://example.com/wp-json/'.ltrim((string)$p,'/'); } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a=''){ return 'nonce'; } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($url,$component=-1){ return parse_url((string)$url,$component); } }
if (!function_exists('trailingslashit')) { function trailingslashit($v){ return rtrim((string)$v,'/').'/'; } }
