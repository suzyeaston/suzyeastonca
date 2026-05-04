<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../lousy-outages/includes/SignalEngine.php';
use SuzyEaston\LousyOutages\SignalEngine;
$tests=[];
$tests['classification']=function(){ if(SignalEngine::classify_score(1)!=='quiet'||SignalEngine::classify_score(2)!=='watch'||SignalEngine::classify_score(3)!=='trending'||SignalEngine::classify_score(5)!=='hot') throw new RuntimeException('thresholds');};
$tests['top_symptom']=function(){ $s=SignalEngine::score_provider([['symptom'=>'api','ip_hash'=>'a'],['symptom'=>'api','ip_hash'=>'b'],['symptom'=>'login','ip_hash'=>'c']]); if($s['top_symptom']!=='api') throw new RuntimeException('top symptom'); if(stripos($s['message'],'community')===false && stripos($s['message'],'unconfirmed')===false) throw new RuntimeException('wording');};
$f=false; foreach($tests as $n=>$cb){ try{$cb(); echo "ok - $n\n";}catch(Throwable $e){$f=true; echo "not ok - $n: {$e->getMessage()}\n";}} if($f) exit(1); echo "All tests passed\n";
