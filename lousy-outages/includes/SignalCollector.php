<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Sources\CloudflareRadarSource;
use SuzyEaston\LousyOutages\Sources\CommunityReportIntelSource;
use SuzyEaston\LousyOutages\Sources\HackerNewsChatterSource;
use SuzyEaston\LousyOutages\Sources\ProviderFeedSource;
use SuzyEaston\LousyOutages\Sources\PublicChatterSource;
use SuzyEaston\LousyOutages\Sources\StatuspageIntelSource;
use SuzyEaston\LousyOutages\Sources\SyntheticCanarySource;

class SignalCollector {
    public static function sources(): array { return [new StatuspageIntelSource(), new ProviderFeedSource(), new HackerNewsChatterSource(), new CommunityReportIntelSource(), new SyntheticCanarySource(), new PublicChatterSource(), new CloudflareRadarSource()]; }
    public static function collect(array $options=[]): array {
        $sources=self::sources();
        $result=['started_at'=>gmdate('c'),'finished_at'=>'','sources'=>[],'total_collected'=>0,'total_stored'=>0,'diagnostics'=>[],'errors'=>[]];
        foreach($sources as $source){ $r=self::collect_source($source->id(),$options); $result['sources'][]=$r; $result['total_collected']+=(int)$r['collected_count']; $result['total_stored']+=(int)$r['stored_count']; $result['diagnostics'][]=['source'=>$source->id(),'status'=>$r['attempted']?'attempted':'skipped','reason'=>(string)($r['reason']??''),'detail'=>$r['diagnostics']??[]]; }
        $result['finished_at']=gmdate('c'); self::mark_last_collection_result($result); return $result;
    }
    public static function collect_source(string $sourceId, array $options=[]): array {
        foreach(self::sources() as $source){ if($source->id()!==$sourceId) continue; $configured=$source->is_configured(); if(!$configured) return ['source'=>$source->id(),'configured'=>false,'attempted'=>false,'reason'=>'not_configured','collected_count'=>0,'stored_count'=>0,'diagnostics'=>['configured'=>false,'attempted'=>false,'skipped_reasons'=>['not_configured']],'errors'=>[]];
            $signals=$source->collect($options); $stored=ExternalSignals::record_many($signals); $diag=get_option('lo_diag_'.$source->id(),[]); if(!is_array($diag)) $diag=[];
            return ['source'=>$source->id(),'configured'=>true,'attempted'=>true,'collected_count'=>count($signals),'stored_count'=>(int)($stored['inserted']??0),'diagnostics'=>$diag,'errors'=>[]]; }
        return ['source'=>$sourceId,'configured'=>false,'attempted'=>false,'collected_count'=>0,'stored_count'=>0,'diagnostics'=>['configured'=>false,'attempted'=>false],'errors'=>['unknown source']];
    }
    public static function get_last_collection_result(): array { $r=get_option('lousy_outages_last_external_collection',[]); return is_array($r)?$r:[]; }
    public static function mark_last_collection_result(array $result): void { update_option('lousy_outages_last_external_collection',$result,false); }
}
