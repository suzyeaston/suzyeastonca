<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lousy-outages/includes/SignalSourceInterface.php';
require_once __DIR__ . '/../lousy-outages/includes/Sources/SyntheticCanarySource.php';
use SuzyEaston\LousyOutages\Sources\SyntheticCanarySource;
$src=new SyntheticCanarySource();
$signals=$src->collect();
if(!is_array($signals)) throw new RuntimeException('collect not array');
echo "PASS: synthetic collect shape\n";
