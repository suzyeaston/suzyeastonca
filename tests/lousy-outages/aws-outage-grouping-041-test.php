<?php
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS',86400);
if (!function_exists('wp_json_encode')) { function wp_json_encode($d){return json_encode($d);} }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s){return strip_tags($s);} }
if (!function_exists('wp_parse_url')) { function wp_parse_url($u){return parse_url($u);} }
if (!function_exists('trailingslashit')) { function trailingslashit($s){return rtrim($s,'/').'/';} }
require_once __DIR__ . '/../../lousy-outages/includes/Fetch.php';
require_once __DIR__ . '/../../lousy-outages/includes/Adapters.php';
require_once __DIR__ . '/../../lousy-outages/includes/Adapters/Statuspage.php';
require_once __DIR__ . '/../../lousy-outages/includes/Fetcher.php';
function fail($m){fwrite(STDERR,$m."\n"); exit(1);} 
$fetcher = new SuzyEaston\LousyOutages\Fetcher();
$ref = new ReflectionClass($fetcher); $m=$ref->getMethod('normalize_incident_buckets'); $m->setAccessible(true);
$inc=[]; for($i=1;$i<=9;$i++){ $inc[]=['id'=>'n'.$i,'name'=>'Operational issue - Service '.$i.' (UAE)','summary'=>'AWS Health Dashboard reports physical infrastructure damage to facilities in the UAE from drone strikes during the Middle East conflict affecting ME-CENTRAL-1. Recovery is expected to take months.','status'=>'major','updated_at'=>'2026-04-30T12:00:00Z','shortlink'=>'https://health.aws.amazon.com/health/status']; }
$out=$m->invoke($fetcher,$inc,['id'=>'aws','name'=>'AWS','status_url'=>'https://health.aws.amazon.com/health/status','type'=>'rss'],'outage');
$active=$out['active'] ?? [];
if (count($active)!==1) fail('AWS notices should group to one outage event');
$event=$active[0];
if (($event['official_notice_count'] ?? 0)!==9) fail('official notice count not preserved');
if (empty($event['official_context']) || stripos($event['official_context'],'drone')===false) fail('official context not retained');
echo "aws-outage-grouping-041 ok\n";
