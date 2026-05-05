<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;
use SuzyEaston\LousyOutages\SignalSourceInterface;

class ProviderFeedSource implements SignalSourceInterface {
    public function id(): string { return 'provider_feed'; }
    public function label(): string { return 'Provider Feed Intel'; }
    public function is_configured(): bool { return SourcePack::enabled() && count(SourcePack::provider_feed_urls()) > 0; }
    public function collect(array $options = []): array {
        $feeds=SourcePack::provider_feed_urls(); $out=[]; $counts=['feeds_checked'=>0,'items_seen'=>0,'items_matched'=>0,'items_stored'=>0];
        foreach(array_slice($feeds,0,8) as $feed){ $counts['feeds_checked']++; $cache='lo_feed_'.md5($feed); $items=get_transient($cache); if(!is_array($items)){ $r=wp_remote_get($feed,['timeout'=>8]); if(is_wp_error($r)) continue; $xml=simplexml_load_string((string)wp_remote_retrieve_body($r)); $items=[]; if($xml&&isset($xml->entry)){ foreach($xml->entry as $e){$items[]=['title'=>sanitize_text_field((string)$e->title),'url'=>esc_url_raw((string)$e->link['href'])];}} set_transient($cache,$items,10*MINUTE_IN_SECONDS);} foreach(array_slice($items,0,5) as $it){ $counts['items_seen']++; $title=strtolower((string)($it['title']??'')); if(strpos($title,'outage')===false && strpos($title,'degrad')===false && strpos($title,'incident')===false) continue; $counts['items_matched']++; $out[]=['source'=>'provider_feed','provider_id'=>'','provider_name'=>'Provider Feed','category'=>'official_status','region'=>'global','signal_type'=>'official_feed','severity'=>'trending','confidence'=>65,'title'=>(string)$it['title'],'message'=>'Provider status feed indicates service issue.','url'=>(string)($it['url']??$feed),'observed_at'=>gmdate('Y-m-d H:i:s'),'metadata'=>['evidence_quality'=>'moderate','source_url'=>$feed]]; $counts['items_stored']++; }} update_option('lo_last_provider_feed_counts',$counts,false); return $out;
    }
}
