<?php
declare(strict_types=1);

function lo_test_classify(array $state): string {
    $status = strtolower((string)($state['status'] ?? 'unknown'));
    $error = trim((string)($state['error'] ?? ''));
    if (in_array($status, ['operational','ok'], true)) return 'operational';
    if (in_array($status, ['degraded','outage','partial','maintenance'], true)) return 'status';
    if ($error !== '') return 'fetch_error';
    return 'unknown';
}

$cases = [
    [['status'=>'operational','error'=>'All systems operational'], 'operational'],
    [['status'=>'degraded','message'=>'Minor outage'], 'status'],
    [['status'=>'unknown','error'=>'timeout'], 'fetch_error'],
];
foreach ($cases as [$input, $expected]) {
    if (lo_test_classify($input) !== $expected) { echo "FAIL\n"; exit(1); }
}

echo "OK\n";
