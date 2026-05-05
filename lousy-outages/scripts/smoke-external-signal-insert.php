<?php
declare(strict_types=1);

if ($argc < 2) { fwrite(STDERR, "Usage: php scripts/smoke-external-signal-insert.php /path/to/wp-load.php [--keep]\n"); exit(2); }
$wpLoad = $argv[1]; $keep = in_array('--keep', $argv, true);
if (!is_file($wpLoad)) { fwrite(STDERR, "wp-load.php not found at: {$wpLoad}\n"); exit(2); }
require_once $wpLoad;

use SuzyEaston\LousyOutages\ExternalSignals;

global $wpdb;
$rawHash = 'smoke_external_signal_v1';
$signal = [
    'source' => 'smoke_external_signal',
    'source_type' => 'synthetic_canary',
    'adapter_id' => 'smoke_script',
    'source_id' => 'smoke-script-1',
    'provider_id' => 'smoke',
    'provider_name' => 'Smoke Test',
    'category' => 'test',
    'region' => 'global',
    'signal_type' => 'test_signal',
    'severity' => 'unknown',
    'confidence' => 1,
    'title' => 'Smoke external insert',
    'message' => 'Deterministic smoke test record.',
    'observed_at' => gmdate('Y-m-d H:i:s'),
    'raw_hash' => $rawHash,
];

$result = ExternalSignals::record_many([$signal]);
$table = ExternalSignals::table_name();
$rowId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE raw_hash = %s LIMIT 1", $rawHash));
$ok = $rowId > 0;
if (!$keep && $rowId > 0) { $wpdb->delete($table, ['id' => $rowId], ['%d']); }

echo ($ok ? "ok" : "failure") . "\n";
echo 'inserted=' . (int)($result['inserted'] ?? 0) . "\n";
echo 'skipped=' . (int)($result['skipped'] ?? 0) . "\n";
echo 'failed=' . (int)($result['failed'] ?? 0) . "\n";
echo 'insert_id=' . $rowId . "\n";
echo 'wpdb_last_error=' . sanitize_text_field((string)$wpdb->last_error) . "\n";

exit($ok ? 0 : 1);
