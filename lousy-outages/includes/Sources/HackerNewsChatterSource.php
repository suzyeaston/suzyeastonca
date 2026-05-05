<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class HackerNewsChatterSource implements SignalSourceInterface {
    public function id(): string { return 'hacker_news_chatter'; }
    public function label(): string { return 'Hacker News Chatter'; }
    public function is_configured(): bool { return SourcePack::enabled() && (bool) apply_filters('lo_hn_chatter_enabled', get_option('lo_hn_chatter_enabled', '1') === '1'); }
    private function issue(string $t): bool { return (bool) preg_match('/\b(outage|down|incident|degrad|latency|error|unavailable|disruption)\b/i',$t); }
    public function collect(array $options = []): array {
        if(!$this->is_configured()) return [];
        $queries=SourcePack::early_warning_queries(); $cursor=(int)get_option('lo_hn_query_cursor',0); $take=array_slice(array_merge($queries,$queries),$cursor,2); update_option('lo_hn_query_cursor',($cursor+2)%max(1,count($queries)),false);
        $diag=['configured'=>true,'attempted'=>false,'queries_available'=>count($queries),'queries_attempted'=>0,'queries_skipped_budget'=>0,'raw_results_seen'=>0,'usable_results'=>0,'rows_stored'=>0,'skipped_reasons'=>[],'cooldown_active'=>false];
        $out=[];
        foreach($take as $q){
            $budget=SourceBudgetManager::can_attempt($this->id(),'hn.algolia.com',20); if(empty($budget['ok'])){ $diag['queries_skipped_budget']++; $diag['cooldown_active']=true; continue; }
            $diag['queries_attempted']++; $diag['attempted']=true;
            $url=add_query_arg(['query'=>$q,'tags'=>'story','hitsPerPage'=>5],'https://hn.algolia.com/api/v1/search_by_date');
            $cache='lo_hn_'.md5($url); $hits=get_transient($cache);
            if(!is_array($hits)){
                $r=wp_remote_get($url,['timeout'=>7]); SourceBudgetManager::mark_attempt($this->id(),'hn.algolia.com',10);
                if(is_wp_error($r)) { SourceBudgetManager::mark_result($this->id(),false,0); $diag['skipped_reasons'][]='http_error'; continue; }
                $code=(int)wp_remote_retrieve_response_code($r); if($code===429){SourceBudgetManager::mark_result($this->id(),false,429); $diag['cooldown_active']=true; continue;}
                if($code<200||$code>=300){SourceBudgetManager::mark_result($this->id(),false,$code); continue;}
                SourceBudgetManager::mark_result($this->id(),true,$code);
                $json=json_decode((string)wp_remote_retrieve_body($r),true); $hits=(array)($json['hits']??[]); set_transient($cache,$hits,10*MINUTE_IN_SECONDS);
            }
            $diag['raw_results_seen']+=count($hits);
            foreach(array_slice($hits,0,5) as $hit){
                $title=sanitize_text_field((string)($hit['title']??$hit['story_title']??'')); $txt=sanitize_textarea_field((string)($hit['comment_text']??''));
                if($title==='' || !$this->issue($title.' '.$txt.' '.$q)) continue;
                $diag['usable_results']++;
                $storyUrl=esc_url_raw((string)($hit['url']??$hit['story_url']??'')); $hnUrl='https://news.ycombinator.com/item?id='.(int)($hit['objectID']??0);
                $urls=array_values(array_filter([$hnUrl,$storyUrl]));
                $out[]=['source'=>'hacker_news_chatter','source_type'=>'public_chatter','adapter_id'=>'hacker_news_chatter','source_id'=>sanitize_text_field((string)($hit['objectID']??md5($title.$hnUrl))),'provider_id'=>'','provider_name'=>'Tech Pulse','category'=>'public_chatter','region'=>'global','signal_type'=>'public_chatter','severity'=>'watch','confidence'=>$storyUrl?40:25,'title'=>'HN chatter: '.$title,'message'=>'Unconfirmed developer chatter from Hacker News search results.','url'=>$hnUrl,'observed_at'=>gmdate('Y-m-d H:i:s'),'snippets'=>[$title],'source_urls'=>$urls,'domains'=>array_values(array_unique(array_filter([wp_parse_url($storyUrl,PHP_URL_HOST),'news.ycombinator.com']))),'confidence_reason'=>'Developer chatter with issue-language match; requires corroboration.','evidence_quality'=>$storyUrl?'moderate':'weak','official_confirmed'=>false,'unconfirmed_note'=>'Unconfirmed developer chatter.'];
                $diag['rows_stored']++;
            }
        }
        update_option('lo_diag_'.$this->id(),$diag,false);
        return $out;
    }
}
