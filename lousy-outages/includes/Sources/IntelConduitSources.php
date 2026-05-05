<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;
use SuzyEaston\LousyOutages\UserReports;

trait IntelConduitHelpers {
    private function has_issue_language(string $text): bool {
        return (bool) preg_match('/\b(down|outage|incident|degraded|error|failure|unavailable|latency|disruption)\b/i', $text);
    }
    private function host(string $url): string { return (string) wp_parse_url($url, PHP_URL_HOST); }
}

class StatuspageIntelSource implements SignalSourceInterface {
    use IntelConduitHelpers;
    
    public function is_configured(): bool { return true; }
    public function collect(array $options = []): array {
        $bases = (array) apply_filters('lo_statuspage_base_urls', []);
        $out=[];
        foreach($bases as $provider=>$base){ $base=rtrim((string)$base,'/'); if(!$base) continue; $incidentUrl=$base.'/api/v2/incidents/unresolved.json'; $r=wp_remote_get($incidentUrl,['timeout'=>6]); if(is_wp_error($r)) continue; $json=json_decode((string)wp_remote_retrieve_body($r),true); foreach((array)($json['incidents']??[]) as $inc){ $name=sanitize_text_field((string)($inc['name']??'Statuspage incident')); $body=sanitize_text_field((string)($inc['status']??'')); if(!$this->has_issue_language($name.' '.$body)) continue; $url=esc_url_raw((string)($inc['shortlink']??$base)); $out[]=['source'=>'statuspage','source_type'=>'official_status','adapter_id'=>'statuspage_public','source_id'=>sanitize_text_field((string)($inc['id']??md5($url.$name))),'provider_id'=>sanitize_key((string)$provider),'provider_name'=>sanitize_text_field((string)$provider),'category'=>'service','region'=>'global','signal_type'=>'status_incident','severity'=>'major','confidence'=>95,'title'=>$name,'message'=>$body,'url'=>$url,'source_urls'=>[$url,$incidentUrl],'domains'=>[$this->host($base)],'snippets'=>[$name,$body],'confidence_reason'=>'Official provider statuspage incident','evidence_quality'=>'official','official_confirmed'=>true,'observed_at'=>gmdate('Y-m-d H:i:s')]; }
        }
        return $out;
    }
}

class ProviderFeedSource implements SignalSourceInterface { use IntelConduitHelpers;
    public function id(): string { return 'provider_feed'; }
    public function label(): string { return 'Provider Feed'; }
    public function is_configured(): bool { return true; }
    public function collect(array $options = []): array {
        $feeds=(array)apply_filters('lo_provider_feed_urls',[]); $max=max(1,min(20,(int)apply_filters('lo_provider_feed_max_items',8))); $timeout=max(2,min(12,(int)apply_filters('lo_provider_feed_timeout',6))); $out=[];
        include_once ABSPATH . WPINC . '/feed.php';
        foreach($feeds as $provider=>$url){ $cache='lo_feed_'.md5((string)$url); $items=get_transient($cache); if(!is_array($items)){ $rss=fetch_feed((string)$url); if(is_wp_error($rss)) continue; $items=$rss->get_items(0,$max); set_transient($cache,$items,5*MINUTE_IN_SECONDS);} foreach((array)$items as $item){ $title=sanitize_text_field((string)$item->get_title()); $summary=sanitize_textarea_field((string)$item->get_description()); if(!$this->has_issue_language($title.' '.$summary)) continue; $link=esc_url_raw((string)$item->get_link()); $out[]=['source'=>'provider_feed','source_type'=>'provider_rss','adapter_id'=>'provider_rss_atom','source_id'=>sanitize_text_field((string)$item->get_id()),'provider_id'=>sanitize_key((string)$provider),'provider_name'=>sanitize_text_field((string)$provider),'category'=>'service','region'=>'global','signal_type'=>'feed_incident','severity'=>'degraded','confidence'=>65,'title'=>$title,'message'=>mb_substr($summary,0,280),'url'=>$link,'domains'=>[$this->host($link)],'source_urls'=>[$link,(string)$url],'snippets'=>[$title,mb_substr($summary,0,120)],'confidence_reason'=>'Provider feed reported potential issue language','evidence_quality'=>'moderate','official_confirmed'=>false,'unconfirmed_note'=>'Feed item is not a direct official incident confirmation.','metadata_json'=>['published_at'=>(string)$item->get_date('c')],'observed_at'=>gmdate('Y-m-d H:i:s')]; }} return $out;
    }
}

class HackerNewsChatterSource implements SignalSourceInterface { use IntelConduitHelpers;
    public function id(): string { return 'hn_chatter'; }
    public function label(): string { return 'Hacker News Chatter'; }
    public function is_configured(): bool { return (bool) apply_filters('lo_hn_chatter_enabled', false); }
    public function collect(array $options = []): array { $queries=['GitHub Actions down','npm outage','Cloudflare outage','AWS outage','OpenAI API down','Vercel outage','Docker Hub outage']; $out=[]; $limit=3; foreach($queries as $q){ $u='https://hn.algolia.com/api/v1/search?tags=story&hitsPerPage='.$limit.'&query='.rawurlencode($q); $cache='lo_hn_'.md5($u); $hits=get_transient($cache); if(!is_array($hits)){ $r=wp_remote_get($u,['timeout'=>5]); if(is_wp_error($r)) continue; $hits=(array)(json_decode((string)wp_remote_retrieve_body($r),true)['hits']??[]); set_transient($cache,$hits,10*MINUTE_IN_SECONDS);} foreach($hits as $h){ $title=sanitize_text_field((string)($h['title']??$h['story_title']??'')); if(!$this->has_issue_language($title.' '.$q)) continue; $url=esc_url_raw((string)($h['url']??$h['story_url']??'https://news.ycombinator.com/item?id='.(int)($h['objectID']??0))); $provider=preg_split('/\s+/',trim((string)$q))[0] ?? 'unknown'; $out[]=['source'=>'hacker_news','source_type'=>'developer_chatter','adapter_id'=>'hn_algolia','source_id'=>sanitize_text_field((string)($h['objectID']??md5($title.$url))),'provider_id'=>sanitize_key((string)$provider),'provider_name'=>sanitize_text_field((string)$provider),'category'=>'developer_chatter','region'=>'global','signal_type'=>'hn_chatter','severity'=>'watch','confidence'=>35,'title'=>$title,'message'=>'Unconfirmed developer chatter from Hacker News.','url'=>$url,'domains'=>[$this->host($url)],'source_urls'=>[$url,$u],'snippets'=>[$title],'confidence_reason'=>'Developer chatter only, requires corroboration.','evidence_quality'=>'weak','official_confirmed'=>false,'unconfirmed_note'=>'HN chatter alone should not create HOT signals.','metadata_json'=>['points'=>(int)($h['points']??0),'comments'=>(int)($h['num_comments']??0),'created_at'=>(string)($h['created_at']??'')],'observed_at'=>gmdate('Y-m-d H:i:s')]; }} return $out; }
}

class CommunityReportIntelSource implements SignalSourceInterface { use IntelConduitHelpers;
    public function id(): string { return 'community_reports_intel'; }
    public function label(): string { return 'Community Reports Intel'; }
    public function is_configured(): bool { return true; }
    public function collect(array $options = []): array { $rows=UserReports::recent(60,100); $out=[]; foreach($rows as $r){ $txt=sanitize_text_field((string)($r['issue_text']??$r['symptom']??'')); if(!$this->has_issue_language($txt)) continue; $out[]=['source'=>'community_reports','source_type'=>'community_report','adapter_id'=>'community_report_normalizer','source_id'=>sanitize_text_field((string)($r['id']??md5(wp_json_encode($r)))),'provider_id'=>sanitize_key((string)($r['provider_id']??'')),'provider_name'=>sanitize_text_field((string)($r['provider_name']??$r['provider_id']??'Unknown')),'category'=>sanitize_key((string)($r['category']??'community')),'region'=>sanitize_text_field((string)($r['region']??'')),'signal_type'=>'user_report','severity'=>'watch','confidence'=>45,'title'=>'Community report: '.sanitize_text_field((string)($r['provider_name']??$r['provider_id']??'provider')),'message'=>mb_substr($txt,0,220),'evidence_quality'=>'weak','official_confirmed'=>false,'unconfirmed_note'=>'Community reports are unconfirmed unless corroborated.','observed_at'=>sanitize_text_field((string)($r['reported_at']??gmdate('Y-m-d H:i:s')))]; } return $out; }
}
