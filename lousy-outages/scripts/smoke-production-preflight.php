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
if (empty($schemaDiag['table_exists'])) {
    $errors[]='ExternalSignals table missing: '.(string)($schemaDiag['table'] ?? '');
}
if(!empty($schemaDiag['missing_columns'])) {
    $errors[]='ExternalSignals missing columns: '.implode(',', (array)$schemaDiag['missing_columns']);
}

$budgetBefore = get_option('lo_source_budget_state', []);
$result = SignalCollector::collect(['dry_run'=>true,'preflight'=>true,'suppress_notifications'=>true,'no_email'=>true]);
$budgetAfter = get_option('lo_source_budget_state', []);
if ($budgetBefore !== $budgetAfter) {
    $errors[]='Dry-run/preflight mutated lo_source_budget_state';
}
if (!is_array($result) || !isset($result['sources']) || !isset($result['diagnostics'])) $errors[]='dry-run collect did not return expected diagnostics';
$statusDiag=(array)get_option('lo_diag_statuspage_intel',[]);
if(!empty($statusDiag)){
    $attempted=(int)($statusDiag['endpoints_attempted']??0);
    $cooldown=!empty($statusDiag['cooldown_active']);
    if($attempted<=0 && !$cooldown){ $errors[]='Statuspage diagnostics: endpoints_attempted=0 without cooldown'; }
}
$chatterDiag=(array)get_option('lo_diag_public_chatter',[]);
if(!empty($chatterDiag) && !empty($chatterDiag['direct_sources_enabled'])){ $errors[]='Public chatter direct sources enabled (expected safe-default disabled)'; }
if(!empty($chatterDiag)){
    foreach(['configured','attempted','direct_sources_enabled','direct_sources_disabled_by_safe_default','enabled_sources','skipped_sources','providers_scanned','queries_attempted','mentions_seen_by_source','mentions_seen_by_provider','signals_built_by_provider','thresholds','scan_window_minutes','ran_at','watch_candidates','official_incident_corroboration','canadian_infrastructure_watchlist'] as $key){
        if(!array_key_exists($key,$chatterDiag)){ $errors[]='Public chatter diagnostics missing '.$key; }
    }
    if(empty($chatterDiag['direct_sources_enabled']) && empty($chatterDiag['direct_sources_disabled_by_safe_default'])){ $errors[]='Public chatter safe-default gate not visible in diagnostics'; }
    if(!array_key_exists('public_chatter_bluesky',(array)($chatterDiag['enabled_sources']??[])) || !array_key_exists('public_chatter_mastodon',(array)($chatterDiag['enabled_sources']??[])) || !array_key_exists('public_chatter_gdelt',(array)($chatterDiag['enabled_sources']??[]))){ $errors[]='Public chatter source checkbox diagnostics incomplete'; }
    foreach((array)($chatterDiag['watch_candidates']??[]) as $candidate){
        $pid=(string)($candidate['provider_id']??'');
        if($pid!=='' && !empty($chatterDiag['signals_built_by_provider'][$pid])){ $errors[]='Watch candidate also promoted for provider '.$pid; break; }
    }
    $seeded=false; foreach((array)($chatterDiag['providers_scanned']??[]) as $provider){ if(in_array('active_incident',(array)($provider['seed_types']??[]),true)){ $seeded=true; break; } }
    if(!empty($chatterDiag['active_incident_seed_providers']) && !$seeded){ $errors[]='Active incident seed providers missing from generated query providers'; }
    $hasCanada=false; foreach((array)($chatterDiag['providers_scanned']??[]) as $provider){ if(in_array('canadian_infrastructure',(array)($provider['seed_types']??[]),true)){ $hasCanada=true; break; } }
    if(!$hasCanada){ $errors[]='Canadian infrastructure watchlist queries not generated'; }
}
$hnDiag=(array)get_option('lo_diag_hacker_news_chatter',[]);
if(!empty($hnDiag) && !array_key_exists('queries_attempted',$hnDiag)){ $errors[]='HN diagnostics missing queries_attempted'; }
if($errors){ fwrite(STDERR, "Preflight FAILED:\n- ".implode("\n- ",$errors)."\n"); exit(1);} echo "Preflight passed with ".count($sources)." sources.\n";
