<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lousy-outages/includes/ExternalSignals.php';
use SuzyEaston\LousyOutages\ExternalSignals;
$tests=[];
$tests['normalize_clamps_and_sanitizes']=function(){ $s=ExternalSignals::normalize_signal(['source'=>'Cloud Flare!','confidence'=>999,'title'=>str_repeat('a',500),'provider_id'=>'','category'=>'Internet Health']); if($s['confidence']!==100) throw new RuntimeException('confidence clamp failed'); if(strlen($s['title'])>255) throw new RuntimeException('title limit failed'); if($s['provider_id']!=='') throw new RuntimeException('provider optional failed');};
$tests['seed_clear_demo']=function(){ global $wpdb; if(!method_exists($wpdb,'insert')) return; ExternalSignals::seed_demo_signals(); $cleared=ExternalSignals::clear_demo_signals(); if($cleared<0) throw new RuntimeException('clear failed');};
foreach($tests as $name=>$fn){$fn(); echo "PASS: $name\n";}
