<?php
declare(strict_types=1);

if ($argc < 2) { fwrite(STDERR, "Usage: php scripts/smoke-production-preflight.php /path/to/wp-load.php\n"); exit(2);} 
$wpLoad=$argv[1]; if(!is_file($wpLoad)){ fwrite(STDERR,"wp-load.php not found at: {$wpLoad}\n"); exit(2);} require_once $wpLoad;

use SuzyEaston\LousyOutages\SignalCollector;
use SuzyEaston\LousyOutages\SignalSourceInterface;
use SuzyEaston\LousyOutages\Sources\SourcePack;

$errors=[]; $sources=SignalCollector::sources();
foreach($sources as $i=>$source){
 if(!$source instanceof SignalSourceInterface){$errors[]="Source $i is not SignalSourceInterface"; continue;}
 if(trim((string)$source->id())==='') $errors[] = get_class($source).' empty id';
 if(trim((string)$source->label())==='') $errors[] = get_class($source).' empty label';
}
if(count(SourcePack::statuspage_base_urls())===0) $errors[]='SourcePack statuspage list empty';
if(count(SourcePack::provider_feed_urls())===0) $errors[]='SourcePack feed list empty';
if(count(SourcePack::early_warning_queries())===0) $errors[]='SourcePack queries empty';

$result = SignalCollector::collect(['dry_run'=>true,'suppress_notifications'=>true]);
if (!is_array($result) || !isset($result['sources'])) $errors[]='dry-run collect did not return expected diagnostics';
if($errors){ fwrite(STDERR, "Preflight FAILED:\n- ".implode("\n- ",$errors)."\n"); exit(1);} echo "Preflight passed with ".count($sources)." sources.\n";
