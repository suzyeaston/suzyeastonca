<?php
declare(strict_types=1);
const HOUR_IN_SECONDS = 3600;
const DAY_IN_SECONDS = 86400;
const YEAR_IN_SECONDS = 31536000;
$GLOBALS['wp_options'] = [];
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['wp_options']) ? $GLOBALS['wp_options'][$k] : $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['wp_options'][$k] = $v; return true; }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$key)); }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
require __DIR__ . '/../includes/Storage/HistoryStore.php';
use SuzyEaston\LousyOutages\Storage\HistoryStore;
function assert_true($condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$now = strtotime('2026-07-20T00:00:00Z');
$GLOBALS['wp_options']['lo_event_log'] = [
  'aws|april' => ['provider'=>'aws','provider_label'=>'AWS','guid'=>'aws-april','title'=>'April AWS API errors','status'=>'degraded','severity'=>'degraded','first_seen'=>strtotime('2026-04-10T12:00:00Z'),'last_seen'=>strtotime('2026-04-10T14:00:00Z'),'important'=>true],
  'cf|recent' => ['provider'=>'cloudflare','provider_label'=>'Cloudflare','guid'=>'cf-recent','title'=>'Workers errors','status'=>'major','severity'=>'outage','first_seen'=>$now - DAY_IN_SECONDS,'last_seen'=>$now - DAY_IN_SECONDS,'important'=>true],
];
$GLOBALS['wp_options']['lousy_outages_history'] = [
  ['id'=>'disabled_provider','status'=>'degraded','time'=>$now - (40 * DAY_IN_SECONDS)],
  ['id'=>'aws','status'=>'degraded','time'=>strtotime('2026-04-10T12:10:00Z')],
];
$GLOBALS['wp_options']['lousy_outages_log'] = [ ['id'=>'github','status'=>'major','time'=>$now - (370 * DAY_IN_SECONDS)] ];
$GLOBALS['wp_options']['lousy_outages_states'] = [ 'removed_provider' => ['status'=>'degraded','updated_at'=>'2026-07-19T00:00:00Z'] ];
$GLOBALS['wp_options']['lo_event_log_compacted_v1'] = 1;
$original = $GLOBALS['wp_options'];
$store = new HistoryStore();
$report = $store->migrate(true);
assert_true($report['before_dedupe'] >= 6, 'rich, legacy log/history, and states are merged before dedupe');
assert_true($report['after_dedupe'] >= 5, 'legacy records remain visible when rich events exist');
$again = $store->migrate(true);
assert_true($again['after_dedupe'] === $report['after_dedupe'], 'migration is idempotent');
foreach (HistoryStore::SOURCE_OPTIONS as $option) { assert_true($GLOBALS['wp_options'][$option] === $original[$option], "original option $option remains unchanged"); }
$providers = $report['provider_counts'];
assert_true(isset($providers['disabled_provider']), 'disabled providers remain visible in history');
assert_true(isset($providers['removed_provider']), 'current provider allowlist does not remove historical events');
$thirty = array_filter($report['events'], fn($e) => (int)$e['first_seen'] >= $now - 30 * DAY_IN_SECONDS);
assert_true(count($thirty) < count($report['events']), '30-day filter hides older records without deleting them');
$one_year = array_filter($report['events'], fn($e) => (int)$e['first_seen'] >= $now - 365 * DAY_IN_SECONDS);
assert_true(count($one_year) >= count($thirty), 'one-year request returns retained older events');
$awsApril = array_values(array_filter($report['events'], fn($e) => $e['provider'] === 'aws' && str_contains($e['title'], 'April')));
assert_true(count($awsApril) === 1, 'AWS April event remains in history after stale current-state cleanup');
unset($GLOBALS['activated']);
assert_true(get_option('lo_event_log_v2', []) === $report['events'], 'reactivation preserves and reuses canonical history');
assert_true(get_option('lo_history_migration_backup_v2', [])['source_options']['lo_event_log'] === $original['lo_event_log'], 'backup stores original history values');
echo json_encode(['ok'=>true,'source_counts'=>$report['source_counts'],'before_dedupe'=>$report['before_dedupe'],'after_dedupe'=>$report['after_dedupe'],'provider_counts'=>$report['provider_counts'],'oldest'=>$report['oldest_timestamp'],'newest'=>$report['newest_timestamp']], JSON_PRETTY_PRINT), "\n";
