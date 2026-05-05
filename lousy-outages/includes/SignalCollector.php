<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Sources\CloudflareRadarSource;
use SuzyEaston\LousyOutages\Sources\SyntheticCanarySource;
use SuzyEaston\LousyOutages\Sources\PublicChatterSource;
use SuzyEaston\LousyOutages\Sources\StatuspageIntelSource;
use SuzyEaston\LousyOutages\Sources\ProviderFeedSource;
use SuzyEaston\LousyOutages\Sources\HackerNewsChatterSource;
use SuzyEaston\LousyOutages\Sources\CommunityReportIntelSource;

class SignalCollector {
    public static function sources(): array { return [new StatuspageIntelSource(), new ProviderFeedSource(), new HackerNewsChatterSource(), new CommunityReportIntelSource(), new SyntheticCanarySource(), new PublicChatterSource(), new CloudflareRadarSource()]; }
    public static function collect(array $options=[]): array {
        $result=['started_at'=>gmdate('c'),'finished_at'=>'','sources'=>[],'total_collected'=>0,'total_stored'=>0,'providers_checked'=>0,'queries_attempted'=>0,'errors'=>[]];
        foreach(self::sources() as $source){ $r=self::collect_source($source->id(),$options); $result['sources'][]=$r; $result['total_collected']+=(int)$r['collected_count']; $result['total_stored']+=(int)$r['stored_count']; $result['providers_checked']+=(int)($r['providers_checked']??0); $result['queries_attempted']+=(int)($r['queries_attempted']??0); $result['errors']=array_merge($result['errors'], (array)($r['errors']??[])); }
        $result['finished_at']=gmdate('c'); self::mark_last_collection_result($result); return $result;
    }
    public static function collect_source(string $sourceId, array $options=[]): array {
        foreach(self::sources() as $source){ if($source->id()!==$sourceId) continue; $configured=$source->is_configured(); if(!$configured) return ['source'=>$source->id(),'configured'=>false,'attempted'=>false,'collected_count'=>0,'stored_count'=>0,'errors'=>[]]; $signals=$source->collect($options); $stored=ExternalSignals::record_many($signals); return ['source'=>$source->id(),'configured'=>true,'attempted'=>true,'collected_count'=>count($signals),'stored_count'=>(int)($stored['inserted']??0),'providers_checked'=>(int)($options['providers_checked']??0),'queries_attempted'=>(int)($options['queries_attempted']??0),'errors'=>[]]; }
        return ['source'=>$sourceId,'configured'=>false,'attempted'=>false,'collected_count'=>0,'stored_count'=>0,'errors'=>['unknown source']];
    }
    public static function get_last_collection_result(): array { $r=get_option('lousy_outages_last_external_collection',[]); return is_array($r)?$r:[]; }
    public static function mark_last_collection_result(array $result): void { update_option('lousy_outages_last_external_collection',$result,false); }
}
