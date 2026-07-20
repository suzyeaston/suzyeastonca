<?php
declare(strict_types=1);
namespace {
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
function sanitize_key($key){ return preg_replace('/[^a-z0-9_\-]/','', strtolower((string)$key)); }
function sanitize_title($title){ return trim(preg_replace('/[^a-z0-9]+/','-', strtolower((string)$title)),'-'); }
function home_url($path=''){ return 'https://example.test'.$path; }
function rest_url($path=''){ return 'https://example.test/wp-json/'.ltrim($path, '/'); }
function esc_url_raw($v){ return $v; }
$GLOBALS['lo_test_snapshot'] = ['providers'=>[]];
function lousy_outages_get_snapshot(bool $force_refresh=false): array { $GLOBALS['lo_snapshot_force'] = $force_refresh; return $GLOBALS['lo_test_snapshot']; }
}
namespace SuzyEaston\LousyOutages {
class Fetcher { public static function status_label($s){ return ucwords(str_replace('_',' ', (string)$s)); } }
class Store { public function get_all(){ return []; } }
}
namespace {
require_once __DIR__ . '/../plugins/lousy-outages/includes/Summary.php';
function assert_true($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: $msg\n"); exit(1);} }
function set_snapshot(array $providers): void { $GLOBALS['lo_test_snapshot'] = ['providers'=>$providers, 'fetched_at'=>'2026-07-19T10:00:00Z']; }
$base = '2026-07-19T10:00:00Z';
set_snapshot([['id'=>'github','name'=>'GitHub','stateCode'=>'degraded','tile_kind'=>'outage','sort_key'=>0,'updatedAt'=>$base,'incidents'=>[['id'=>'i1','title'=>'Actions down','status'=>'investigating','impact'=>'minor','startedAt'=>'2026-07-19T09:00:00Z','updatedAt'=>'2026-07-19T09:30:00Z','url'=>'https://status.example/i1']]]]);
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5);
assert_true($GLOBALS['lo_snapshot_force'] === false && count($rows)===1 && $rows[0]['provider']==='GitHub' && $rows[0]['summary']==='Actions down', 'uses canonical snapshot and active incident appears');
set_snapshot([['id'=>'eta','name'=>'Eta','stateCode'=>'degraded','tile_kind'=>'outage','updatedAt'=>$base,'incidents'=>[['title'=>'Resolved by ETA','status'=>'investigating','eta'=>'resolved','impact'=>'minor']]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'eta resolved is lifecycle and impact minor is not active');
foreach (['completed','postmortem','resolved','operational','ok','none'] as $status) { set_snapshot([['id'=>$status,'name'=>$status,'stateCode'=>'degraded','tile_kind'=>'outage','updatedAt'=>$base,'incidents'=>[['title'=>$status,'status'=>$status,'impact'=>'minor']]]]); assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], "$status incidents hidden"); }
set_snapshot([['id'=>'old','name'=>'Old','stateCode'=>'degraded','tile_kind'=>'outage','updatedAt'=>$base,'incidents'=>[['title'=>'Resolved','status'=>'investigating','resolved_at'=>'2026-07-19T09:00:00Z'],['title'=>'Resolved camel','status'=>'investigating','resolvedAt'=>'2026-07-19T09:00:00Z']]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'resolved_at/resolvedAt incidents suppressed and not resurrected');
set_snapshot([['id'=>'slow','name'=>'Slow','stateCode'=>'degraded','tile_kind'=>'signal','updatedAt'=>$base,'summary'=>'Latency verified','incidents'=>[]]]);
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5); assert_true(count($rows)===1 && $rows[0]['severity']==='degraded', 'signal tile becomes degraded row');
set_snapshot([['id'=>'unk','name'=>'Unknown','stateCode'=>'degraded','tile_kind'=>'unknown','updatedAt'=>$base,'incidents'=>[]],['id'=>'man','name'=>'Manual','stateCode'=>'degraded','tile_kind'=>'manual','updatedAt'=>$base,'incidents'=>[]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'unknown/manual tiles not active');

set_snapshot([['id'=>'aws','name'=>'AWS','stateCode'=>'degraded','tile_kind'=>'outage','updatedAt'=>$base,'incidents'=>[['display_title'=>'Multiple AWS services disrupted in UAE (ME-CENTRAL-1)','title'=>'Operational issue - Multiple services (UAE)','summary'=>'Recovery is expected to take months in ME-CENTRAL-1.','status'=>'major','impact'=>'major','updatedAt'=>'2026-04-30T12:00:00Z']]]]);
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5);
assert_true(count($rows)===1 && $rows[0]['provider_id']==='aws' && $rows[0]['summary']==='Multiple AWS services disrupted in UAE (ME-CENTRAL-1)', 'explicit long-running AWS remains current with display title');
set_snapshot([['id'=>'aws','name'=>'AWS','stateCode'=>'degraded','tile_kind'=>'outage','updatedAt'=>$base,'incidents'=>[['title'=>'Major outage reported.','summary'=>'Service disruption reported.','status'=>'major','impact'=>'major','updatedAt'=>'2026-04-30T12:00:00Z']]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'old ambiguous AWS entry is not current');
set_snapshot([['id'=>'aws','name'=>'AWS','stateCode'=>'operational','tile_kind'=>'outage','updatedAt'=>$base,'incidents'=>[['title'=>'Operational issue - Multiple services (UAE)','summary'=>'This issue is now resolved.','status'=>'resolved','impact'=>'operational','updatedAt'=>$base],['title'=>'Operational issue - Multiple services (UAE)','summary'=>'Service disruption in ME-CENTRAL-1.','status'=>'major','impact'=>'major','updatedAt'=>'2026-04-30T12:00:00Z']]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'new resolution suppresses older outage in current selector');
$summary = file_get_contents(__DIR__ . '/../plugins/lousy-outages/includes/Summary.php');
assert_true(str_contains($summary, "'eta'") && strpos($summary, "incident['impact'] ?? incident['status']") === false, 'WordPress-loaded plugin tree contains repaired Summary implementation');
$functions = file_get_contents(__DIR__ . '/../functions.php'); $part = file_get_contents(__DIR__ . '/../parts/lousy-outages-teaser.php'); $js = file_get_contents(__DIR__ . '/../assets/js/lousy-outages-teaser.js');
assert_true(str_contains($functions, 'lousy-outages/v1/summary') && str_contains($part, 'lousy-outages/v1/summary'), 'homepage endpoint configuration is summary');
assert_true(!str_contains($functions.$part.$js, 'lousy-outages/v1/status'), 'no homepage code references status endpoint');
set_snapshot([['id'=>'tv','name'=>'TeamViewer','stateCode'=>'degraded','tile_kind'=>'outage','updatedAt'=>$base,'verification_status'=>'stale','is_stale'=>true,'incidents'=>[['id'=>'tv1','title'=>'DEX PLATFORM. Inventory Software pages not loading','status'=>'monitoring','impact'=>'degraded','startedAt'=>'2026-07-20T10:00:00Z','updatedAt'=>'2026-07-20T10:30:00Z']]]]);
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5);
assert_true(count($rows)===1 && $rows[0]['provider_id']==='tv', 'stale failed provider remains visible during grace via canonical snapshot');
set_snapshot([['id'=>'tv','name'=>'TeamViewer','stateCode'=>'unknown','tile_kind'=>'unknown','updatedAt'=>$base,'verification_status'=>'failed','is_stale'=>false,'incidents'=>[]]]);
$current = \SuzyEaston\LousyOutages\Summary::current();
assert_true($current['kind']==='delayed' && stripos($current['title'], 'verification delayed') !== false, 'fetch failure is verification delayed, not all quiet');
echo "OK\n";
}
