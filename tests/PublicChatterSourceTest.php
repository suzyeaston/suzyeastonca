<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../lousy-outages/includes/SignalSourceInterface.php';
require_once __DIR__.'/../lousy-outages/includes/Providers.php';
require_once __DIR__.'/../lousy-outages/includes/Sources/PublicChatterSource.php';

use SuzyEaston\LousyOutages\Sources\PublicChatterSource;

$ref = new ReflectionClass(PublicChatterSource::class);
$method = $ref->getMethod('severity_for_count');
$method->setAccessible(true);
$src = $ref->newInstanceWithoutConstructor();
$thresholds=['watch'=>3,'trending'=>6,'hot'=>12];
$tests=[
    'none'=>fn()=> $method->invoke($src,0,$thresholds)==='',
    'watch'=>fn()=> $method->invoke($src,3,$thresholds)==='watch',
    'trending'=>fn()=> $method->invoke($src,6,$thresholds)==='trending',
    'hot'=>fn()=> $method->invoke($src,12,$thresholds)==='hot',
];
$f=false; foreach($tests as $n=>$cb){ try{ if(!$cb()) throw new RuntimeException('failed'); echo "ok - $n\n"; }catch(Throwable $e){$f=true; echo "not ok - $n\n"; } }
if($f) exit(1); echo "All tests passed\n";
