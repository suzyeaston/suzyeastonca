<?php
declare(strict_types=1);
namespace {
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
function sanitize_key($key){ return preg_replace('/[^a-z0-9_\-]/','', strtolower((string)$key)); }
function sanitize_title($title){ return trim(preg_replace('/[^a-z0-9]+/','-', strtolower((string)$title)),'-'); }
function home_url($path=''){ return 'https://example.test'.$path; }
$GLOBALS['lo_test_snapshot'] = ['providers'=>[]];
function lousy_outages_get_snapshot(bool $force_refresh=false): array { return $GLOBALS['lo_test_snapshot']; }
}
namespace SuzyEaston\LousyOutages {
class Fetcher { public static function status_label($s){ return ucwords(str_replace('_',' ', (string)$s)); } }
class Store { public function get_all(){ return []; } }
}
namespace {
require_once __DIR__ . '/../lousy-outages/includes/Summary.php';
function assert_true($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: $msg\n"); exit(1);} }
function set_snapshot(array $providers): void { $GLOBALS['lo_test_snapshot'] = ['providers'=>$providers]; }
$base = '2026-07-19T10:00:00Z';
set_snapshot([
 ['id'=>'github','name'=>'GitHub','stateCode'=>'degraded','tile_kind'=>'outage','sort_key'=>0,'updatedAt'=>$base,'incidents'=>[['id'=>'i1','title'=>'Actions down','status'=>'investigating','startedAt'=>'2026-07-19T09:00:00Z','updatedAt'=>'2026-07-19T09:30:00Z','url'=>'https://status.example/i1']]],
]);
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5);
assert_true(count($rows)===1 && $rows[0]['provider']==='GitHub' && $rows[0]['summary']==='Actions down', 'unresolved incident appears');
set_snapshot(array_map(fn($i)=>['id'=>'p'.$i,'name'=>'P'.$i,'stateCode'=>'degraded','tile_kind'=>'outage','sort_key'=>$i,'updatedAt'=>$base,'incidents'=>[['title'=>'I'.$i,'status'=>'investigating','startedAt'=>'2026-07-19T0'.$i.':00:00Z','updatedAt'=>'2026-07-19T0'.$i.':30:00Z']]], range(1,6)));
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5);
assert_true(count($rows)===5 && $rows[0]['provider']==='P1' && $rows[4]['provider']==='P5', 'ordered and limited to five');
set_snapshot([['id'=>'ok','name'=>'OK','stateCode'=>'operational','tile_kind'=>'operational','updatedAt'=>$base,'incidents'=>[]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'operational clear');
set_snapshot([['id'=>'slow','name'=>'Slow','stateCode'=>'degraded','tile_kind'=>'signal','updatedAt'=>$base,'summary'=>'Latency verified','incidents'=>[]]]);
$rows = \SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5);
assert_true(count($rows)===1 && $rows[0]['severity']==='degraded', 'degraded provider is signal');
set_snapshot([['id'=>'unk','name'=>'Unknown','stateCode'=>'unknown','tile_kind'=>'unknown','updatedAt'=>$base,'incidents'=>[],'error'=>'fetch failed']]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'unknown does not become outage');
set_snapshot([['id'=>'old','name'=>'Old','stateCode'=>'operational','tile_kind'=>'operational','updatedAt'=>$base,'incidents'=>[['title'=>'Resolved','status'=>'resolved','startedAt'=>'2026-07-18T09:00:00Z','updatedAt'=>'2026-07-18T10:00:00Z']]]]);
assert_true(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5)===[], 'resolved incidents hidden');
set_snapshot([['id'=>'dup','name'=>'Dup','stateCode'=>'degraded','tile_kind'=>'outage','sort_key'=>0,'updatedAt'=>$base,'incidents'=>[
 ['title'=>'Same outage details','status'=>'investigating','startedAt'=>'2026-07-19T09:00:00Z','updatedAt'=>'2026-07-19T09:10:00Z'],
 ['title'=>'Same outage','status'=>'identified','startedAt'=>'2026-07-19T09:00:10Z','updatedAt'=>'2026-07-19T09:20:00Z']
]]]);
assert_true(count(\SuzyEaston\LousyOutages\Summary::ordered_current_incidents(5))===1, 'duplicates deduped');
echo "OK\n";
}
