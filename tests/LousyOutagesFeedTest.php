<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $d; } }
if (!function_exists('update_option')) { function update_option($k,$v,$a=false){ return true; } }
if (!function_exists('delete_option')) { function delete_option($k){ return true; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(){ return false; } }
if (!function_exists('current_user_can')) { function current_user_can($c){ return false; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($t){ return strip_tags((string)$t); } }

require_once __DIR__ . '/../plugins/lousy-outages/includes/Feeds.php';

$diag = SuzyEaston\LousyOutages\Feeds::get_status_feed_diagnostics();
if (!is_array($diag)) { echo "FAIL\n"; exit(1);} 
SuzyEaston\LousyOutages\Feeds::clear_status_feed_cache();
echo "OK\n";
