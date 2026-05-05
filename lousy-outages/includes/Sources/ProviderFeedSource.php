<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;
use SuzyEaston\LousyOutages\SignalSourceInterface;

class ProviderFeedSource implements SignalSourceInterface {
    public function id(): string { return 'provider_feed'; }
    public function label(): string { return 'Provider Feed Intel'; }
    public function is_configured(): bool { return SourcePack::enabled() && count(SourcePack::provider_feed_urls()) > 0; }
    private function issue(string $t): bool { return (bool) preg_match('/\b(outage|down|incident|degrad|latency|error|unavailable|disruption)\b/i', $t); }
    private function host(string $url): string { return (string) wp_parse_url($url, PHP_URL_HOST); }
    public function collect(array $options = []): array {
        $feeds=SourcePack::provider_feed_urls(); $out=[]; $diag=['configured'=>$this->is_configured(),'attempted'=>false,'feeds_checked'=>0,'items_seen'=>0,'items_matched'=>0,'items_stored'=>0,'skipped_reasons'=>[]];
        foreach(array_slice($feeds,0,12,true) as $key=>$feed){
            $diag['feeds_checked']++; $diag['attempted']=true;
            $cache='lo_feed_'.md5((string)$feed); $items=get_transient($cache);
            if(!is_array($items)){
                $r=wp_remote_get((string)$feed,['timeout'=>8]);
                if(is_wp_error($r)){ $diag['skipped_reasons'][]='http_error:'.$feed; continue; }
                $body=(string)wp_remote_retrieve_body($r);
                $xml=@simplexml_load_string($body); $items=[];
                if($xml && isset($xml->entry)){ foreach($xml->entry as $e){ $items[]=['title'=>(string)$e->title,'url'=>(string)$e->link['href'],'description'=>(string)($e->summary??$e->content??'')]; } }
                if($xml && isset($xml->channel->item)){ foreach($xml->channel->item as $i){ $items[]=['title'=>(string)$i->title,'url'=>(string)$i->link,'description'=>(string)$i->description]; } }
                set_transient($cache,$items,10*MINUTE_IN_SECONDS);
            }
            foreach(array_slice($items,0,8) as $it){
                $diag['items_seen']++;
                $title=sanitize_text_field((string)($it['title']??'')); $desc=sanitize_textarea_field((string)($it['description']??''));
                if(!$this->issue($title.' '.$desc)) continue;
                $diag['items_matched']++;
                $url=esc_url_raw((string)($it['url']??$feed)); if($url==='') $url=(string)$feed;
                $providerHost=$this->host((string)$feed);
                $official=(bool) preg_match('/statuspage\.io$|status\./i',$providerHost);
                $out[]=['source'=>'provider_feed','source_type'=>'provider_rss','adapter_id'=>'provider_feed','source_id'=>md5($url.$title),'provider_id'=>sanitize_key(is_string($key)?$key:$providerHost),'provider_name'=>sanitize_text_field(is_string($key)?$key:$providerHost),'category'=>'service','region'=>'global','signal_type'=>'official_feed','severity'=>'trending','confidence'=>$official?70:55,'title'=>$title,'message'=>mb_substr($desc ?: 'Provider feed indicates service issue.',0,280),'url'=>$url,'observed_at'=>gmdate('Y-m-d H:i:s'),'snippets'=>array_values(array_filter([$title,mb_substr($desc,0,120)])),'domains'=>array_values(array_unique(array_filter([$this->host($url),$providerHost]))),'source_urls'=>array_values(array_unique([$url,(string)$feed])),'confidence_reason'=>$official?'Official provider status feed issue language detected.':'Provider/public feed issue language detected.','evidence_quality'=>$official?'moderate':'weak','official_confirmed'=>$official];
                $diag['items_stored']++;
            }
        }
        update_option('lo_diag_'.$this->id(),$diag,false); return $out;
    }
}
