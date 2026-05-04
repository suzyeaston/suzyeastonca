<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../lousy-outages/includes/Providers.php';
require_once __DIR__.'/../lousy-outages/includes/UserReports.php';
use SuzyEaston\LousyOutages\UserReports;
$r=UserReports::normalize_input(['provider_id'=>'notreal']); if(!empty($r['ok'])){ echo "not ok - invalid provider\n"; exit(1);} 
$ok=UserReports::normalize_input(['provider_id'=>'cloudflare','symptom'=>'nope','details'=>str_repeat('a',600),'ip'=>'1.2.3.4']);
if($ok['symptom']!=='other'){ echo "not ok - symptom normalize\n"; exit(1);} if(strlen($ok['details'])!==500){ echo "not ok - details len\n"; exit(1);} if(($ok['ip_hash']??'')==='1.2.3.4'){ echo "not ok - raw ip stored\n"; exit(1);} 
echo "ok - user report normalization\nAll tests passed\n";
