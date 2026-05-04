<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lousy-outages/includes/Providers.php';
require_once __DIR__ . '/../lousy-outages/includes/UserReports.php';
require_once __DIR__ . '/../lousy-outages/includes/ExternalSignals.php';
require_once __DIR__ . '/../lousy-outages/includes/SignalEngine.php';
use SuzyEaston\LousyOutages\SignalEngine;
$r=SignalEngine::summarize_fused_signals(60);
if(!is_array($r)) throw new RuntimeException('not array');
echo "PASS: fused signals shape\n";
