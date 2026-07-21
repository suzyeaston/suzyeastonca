<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Sources\ProviderFeedSource;
use SuzyEaston\LousyOutages\Sources\StatuspageIntelSource;

class SignalCollector {
    public static function sources(): array {
        return [new StatuspageIntelSource(), new ProviderFeedSource()];
    }

    public static function collect(array $options=[]): array {
        $trigger=(string)($options['collection_trigger'] ?? (!empty($options['dry_run']) ? 'preview' : 'manual'));
        $result=['started_at'=>gmdate('c'),'finished_at'=>'','collection_trigger'=>$trigger,'sources'=>[],'total_collected'=>0,'total_stored'=>0,'failed_count'=>0,'skipped_count'=>0,'first_insert_error'=>'','diagnostics'=>[],'errors'=>[]];
        foreach(self::sources() as $source){
            $r=self::collect_source($source->id(),$options);
            $result['sources'][]=$r; $result['total_collected']+=(int)$r['collected_count']; $result['total_stored']+=(int)$r['stored_count']; $result['failed_count']+=(int)($r['failed_count']??0); $result['skipped_count']+=(int)($r['skipped_count']??0);
            if($result['first_insert_error']==='' && !empty($r['first_insert_error'])){$result['first_insert_error']=(string)$r['first_insert_error'];}
        }
        $result['finished_at']=gmdate('c'); self::mark_last_collection_result($result); return $result;
    }

    public static function collect_source(string $sourceId, array $options=[]): array {
        foreach(self::sources() as $source){ if($source->id()!==$sourceId) continue; if(!$source->is_configured()) return ['source'=>$source->id(),'configured'=>false,'attempted'=>false,'reason'=>'not_configured','collected_count'=>0,'stored_count'=>0,'failed_count'=>0,'skipped_count'=>0,'first_insert_error'=>'','diagnostics'=>['configured'=>false,'attempted'=>false,'skipped_reasons'=>['not_configured']],'errors'=>[]];
            $signals=$source->collect($options); $stored=ExternalSignals::record_many($signals); $diag=get_option('lo_diag_'.$source->id(),[]); if(!is_array($diag)) $diag=[];
            return ['source'=>$source->id(),'configured'=>true,'attempted'=>true,'collected_count'=>count($signals),'stored_count'=>(int)($stored['inserted']??0),'failed_count'=>(int)($stored['failed']??0),'skipped_count'=>(int)($stored['skipped']??0),'first_insert_error'=>(string)($stored['first_error']??''),'diagnostics'=>$diag,'errors'=>[]]; }
        return ['source'=>$sourceId,'configured'=>false,'attempted'=>false,'collected_count'=>0,'stored_count'=>0,'failed_count'=>0,'skipped_count'=>0,'first_insert_error'=>'','diagnostics'=>['configured'=>false,'attempted'=>false],'errors'=>['unknown source']];
    }
    public static function get_last_collection_result(): array { $r=get_option('lousy_outages_last_external_collection',[]); return is_array($r)?$r:[]; }
    public static function mark_last_collection_result(array $result): void { update_option('lousy_outages_last_external_collection',$result,false); }
}
