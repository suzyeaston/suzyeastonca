<?php
declare(strict_types=1);

if ($argc < 2) { fwrite(STDERR, "Usage: php scripts/smoke-production-preflight.php /path/to/wp-load.php\n"); exit(2);} 
$wpLoad=$argv[1]; if(!is_file($wpLoad)){ fwrite(STDERR,"wp-load.php not found at: {$wpLoad}\n"); exit(2);} require_once $wpLoad;

use SuzyEaston\LousyOutages\ExternalSignals;
use SuzyEaston\LousyOutages\SignalCollector;
use SuzyEaston\LousyOutages\SignalSourceInterface;
use SuzyEaston\LousyOutages\Sources\SourcePack;

$errors=[]; $sources=SignalCollector::sources(); $ids=[];
foreach($sources as $i=>$source){
 if(!$source instanceof SignalSourceInterface){$errors[]="Source $i is not SignalSourceInterface"; continue;}
 $id=trim((string)$source->id()); $label=trim((string)$source->label());
 if($id==='') $errors[] = get_class($source).' empty id';
 if($label==='') $errors[] = get_class($source).' empty label';
 if(isset($ids[$id])) $errors[] = 'Duplicate source id: '.$id; else $ids[$id]=true;
}
if(count(SourcePack::statuspage_base_urls()) < 5) $errors[]='SourcePack statuspage list <5';
if(count(SourcePack::provider_feed_urls()) < 5) $errors[]='SourcePack feed list <5';
if(count(SourcePack::early_warning_queries()) < 20) $errors[]='SourcePack queries <20';
$schemaDiag=ExternalSignals::schema_diagnostics();
if(!empty($schemaDiag['missing_columns']) && !is_callable([ExternalSignals::class,'install'])) $errors[]='ExternalSignals expected columns missing and install not callable';

$result = SignalCollector::collect(['dry_run'=>true,'suppress_notifications'=>true,'no_email'=>true]);
if (!is_array($result) || !isset($result['sources']) || !isset($result['diagnostics'])) $errors[]='dry-run collect did not return expected diagnostics';
$statusDiag=(array)get_option('lo_diag_statuspage_intel',[]);
if(!empty($statusDiag)){
    $attempted=(int)($statusDiag['endpoints_attempted']??0);
    $cooldown=!empty($statusDiag['cooldown_active']);
    if($attempted<=0 && !$cooldown){ $errors[]='Statuspage diagnostics: endpoints_attempted=0 without cooldown'; }
}
$chatterDiag=(array)get_option('lo_diag_public_chatter',[]);
if(!empty($chatterDiag) && !empty($chatterDiag['direct_sources_enabled'])){ $errors[]='Public chatter direct sources enabled (expected safe-default disabled)'; }
$hnDiag=(array)get_option('lo_diag_hacker_news_chatter',[]);
if(!empty($hnDiag) && !array_key_exists('queries_attempted',$hnDiag)){ $errors[]='HN diagnostics missing queries_attempted'; }
if($errors){ fwrite(STDERR, "Preflight FAILED:\n- ".implode("\n- ",$errors)."\n"); exit(1);} echo "Preflight passed with ".count($sources)." sources.\n";
