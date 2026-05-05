<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class HackerNewsChatterSource implements SignalSourceInterface {
    public function id(): string { return 'hacker_news_chatter'; }
    public function label(): string { return 'Hacker News Chatter'; }
    public function is_configured(): bool { return SourcePack::enabled() && (bool) apply_filters('lo_hn_chatter_enabled', get_option('lo_hn_chatter_enabled', '1') === '1'); }
    public function collect(array $options = []): array {
        if(!$this->is_configured()) return [];
        $queries=SourcePack::early_warning_queries(); $cursor=(int)get_option('lo_hn_query_cursor',0); $take=array_slice(array_merge($queries,$queries),$cursor,2); update_option('lo_hn_query_cursor',($cursor+2)%max(1,count($queries)),false);
        $out=[]; $attempted=0;
        foreach($take as $q){ $budget=SourceBudgetManager::can_attempt($this->id(),'hn.algolia.com',20); if(empty($budget['ok'])) break; $attempted++; $url=add_query_arg(['query'=>$q,'tags'=>'story','hitsPerPage'=>5],'https://hn.algolia.com/api/v1/search_by_date'); $r=wp_remote_get($url,['timeout'=>7]); SourceBudgetManager::mark_attempt($this->id(),'hn.algolia.com',10); if(is_wp_error($r)) { SourceBudgetManager::mark_result($this->id(),false,0); continue; } $code=(int)wp_remote_retrieve_response_code($r); if($code===429){SourceBudgetManager::mark_result($this->id(),false,429); break;} if($code<200||$code>=300){SourceBudgetManager::mark_result($this->id(),false,$code); continue;} $json=json_decode((string)wp_remote_retrieve_body($r),true); $hits=(array)($json['hits']??[]); if(empty($hits)) continue; SourceBudgetManager::mark_result($this->id(),true,$code); $out[]=['source'=>'hacker_news_chatter','provider_id'=>'','provider_name'=>'Tech Pulse','category'=>'public_chatter','region'=>'global','signal_type'=>'public_chatter','severity'=>'watch','confidence'=>25,'title'=>'HN chatter: '.$q,'message'=>'Unconfirmed chatter from Hacker News search results.','url'=>'https://hn.algolia.com/?q='.rawurlencode((string)$q),'observed_at'=>gmdate('Y-m-d H:i:s'),'metadata'=>['query'=>$q,'mentions'=>min(5,count($hits)),'evidence_quality'=>'weak']]; }
        update_option('lo_last_hn_attempted',$attempted,false);
        return $out;
    }
}
