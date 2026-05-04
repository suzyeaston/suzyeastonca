<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../plugins/lousy-outages/includes/ExternalSignals.php';

$sql = SuzyEaston\LousyOutages\ExternalSignals::schema_sql('wp_lo_external_signals', '');
if (strpos($sql, '`KEY`') !== false || strpos($sql, '(source,KEY') !== false) { echo "FAIL bad-key-artifact\n"; exit(1); }
foreach (['source_idx','provider_idx','signal_type_idx','observed_at_idx','expires_at_idx','raw_hash_idx'] as $idx) {
    if (strpos($sql, $idx) === false) { echo "FAIL missing-index {$idx}\n"; exit(1); }
}
echo "OK\n";
