<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class HackerNewsChatterSource implements SignalSourceInterface {
    public function id(): string { return 'hacker_news_chatter'; }
    public function label(): string { return 'Hacker News Chatter'; }
    public function is_configured(): bool { return SourcePack::enabled() && (bool) apply_filters('lo_hn_chatter_enabled', get_option('lo_hn_chatter_enabled', '1') === '1'); }
    private function issue(string $t): bool { return (bool) preg_match('/\b(outage|down|incident|degrad|latency|error|unavailable|disruption)\b/i',$t); }
    private function detect_provider(string $text): array {
        $map=[
            ['/(github actions|github)/i','github','GitHub','developer_tooling'],
            ['/(\bnpm\b)/i','npm','npm','package_registry'],
            ['/(docker hub|docker pull|\bdocker\b)/i','docker','Docker','package_registry'],
            ['/(cloudflare|workers)/i','cloudflare','Cloudflare','dns_cdn'],
            ['/(openai|chatgpt)/i','openai','OpenAI','ai_api'],
            ['/(vercel)/i','vercel','Vercel','developer_tooling'],
            ['/(netlify)/i','netlify','Netlify','developer_tooling'],
            ['/(\baws\b)/i','aws','AWS','cloud_api'],
            ['/(azure|entra)/i','azure','Azure','cloud_identity'],
            ['/(google cloud|\bgcp\b)/i','google_cloud','Google Cloud','cloud_api'],
            ['/(interac|e-?transfer)/i','interac','Interac','canadian_payments'],
            ['/(rogers|shaw|telus|bell|freedom)/i','canadian_telecom','Canadian Telecom','canadian_telecom'],
        ];
        foreach($map as $row){ if(preg_match($row[0],$text)){ return ['provider_id'=>$row[1],'provider_name'=>$row[2],'category'=>$row[3]]; }}
        return ['provider_id'=>'','provider_name'=>'Tech Pulse','category'=>'public_chatter'];
    }
    private function should_skip_budget_mutation(array $options): bool {
        return !empty($options['dry_run']) || !empty($options['preflight']) || !empty($options['suppress_notifications']) || !empty($options['no_email']);
    }
    private function parse_observed_at(array $hit): array {
        if (isset($hit['created_at_i']) && is_numeric($hit['created_at_i'])) {
            $ts = (int)$hit['created_at_i'];
            return ['ok'=>true,'ts'=>$ts,'value'=>gmdate('Y-m-d H:i:s', $ts)];
        }
        $raw = (string)($hit['created_at'] ?? '');
        if ($raw === '') { return ['ok'=>false,'missing'=>true]; }
        $ts = strtotime($raw);
        if ($ts === false) { return ['ok'=>false,'missing'=>false]; }
        return ['ok'=>true,'ts'=>$ts,'value'=>gmdate('Y-m-d H:i:s', $ts)];
    }
    public function collect(array $options = []): array {
        if(!$this->is_configured()) return [];
        $queries=SourcePack::early_warning_queries(); $cursor=(int)get_option('lo_hn_query_cursor',0); $take=array_slice(array_merge($queries,$queries),$cursor,2); update_option('lo_hn_query_cursor',($cursor+2)%max(1,count($queries)),false);
        $windowMinutes = max(5, min(1440, (int)($options['window_minutes'] ?? $options['windowMinutes'] ?? 60)));
        $minTs = time() - ($windowMinutes * 60);
        $skipBudgetMutation = $this->should_skip_budget_mutation($options);
        $diag=['configured'=>true,'attempted'=>false,'queries_available'=>count($queries),'queries_attempted'=>0,'queries_skipped_budget'=>0,'raw_results_seen'=>0,'usable_results'=>0,'rows_stored'=>0,'rows_attempted'=>0,'results_old_skipped'=>0,'results_missing_date'=>0,'rows_inserted'=>null,'skipped_reasons'=>[],'cooldown_active'=>false];
        $out=[];
        foreach($take as $q){
            $budget=['ok'=>true];
            if (!$skipBudgetMutation) { $budget=SourceBudgetManager::can_attempt($this->id(),'hn.algolia.com',20); }
            if(empty($budget['ok'])){ $diag['queries_skipped_budget']++; $diag['cooldown_active']=true; continue; }
            $diag['queries_attempted']++; $diag['attempted']=true;
            $url=add_query_arg(['query'=>$q,'tags'=>'story','hitsPerPage'=>5],'https://hn.algolia.com/api/v1/search_by_date');
            $cache='lo_hn_'.md5($url); $hits=get_transient($cache);
            if(!is_array($hits)){
                $r=wp_remote_get($url,['timeout'=>7]); if(!$skipBudgetMutation){ SourceBudgetManager::mark_attempt($this->id(),'hn.algolia.com',10); }
                if(is_wp_error($r)) { if(!$skipBudgetMutation){ SourceBudgetManager::mark_result($this->id(),false,0); } $diag['skipped_reasons'][]='http_error'; continue; }
                $code=(int)wp_remote_retrieve_response_code($r); if($code===429){if(!$skipBudgetMutation){SourceBudgetManager::mark_result($this->id(),false,429);} $diag['cooldown_active']=true; continue;}
                if($code<200||$code>=300){if(!$skipBudgetMutation){SourceBudgetManager::mark_result($this->id(),false,$code);} continue;}
                if(!$skipBudgetMutation){ SourceBudgetManager::mark_result($this->id(),true,$code); }
                $json=json_decode((string)wp_remote_retrieve_body($r),true); $hits=(array)($json['hits']??[]); set_transient($cache,$hits,10*MINUTE_IN_SECONDS);
            }
            $diag['raw_results_seen']+=count($hits);
            foreach(array_slice($hits,0,5) as $hit){
                $title=sanitize_text_field((string)($hit['title']??$hit['story_title']??'')); $txt=sanitize_textarea_field((string)($hit['comment_text']??''));
                if($title==='' || !$this->issue($title.' '.$txt.' '.$q)) continue;
                $parsed = $this->parse_observed_at($hit);
                if (empty($parsed['ok'])) { $diag['results_missing_date']++; continue; }
                if ((int)$parsed['ts'] < $minTs) { $diag['results_old_skipped']++; continue; }
                $diag['usable_results']++;
                $storyUrl=esc_url_raw((string)($hit['url']??$hit['story_url']??'')); $hnUrl='https://news.ycombinator.com/item?id='.(int)($hit['objectID']??0);
                $urls=array_values(array_filter([$hnUrl,$storyUrl]));
                $provider=$this->detect_provider($title.' '.$q);
                $out[]=['source'=>'hacker_news_chatter','source_type'=>'public_chatter','adapter_id'=>'hacker_news_chatter','source_id'=>sanitize_text_field((string)($hit['objectID']??md5($title.$hnUrl))),'provider_id'=>$provider['provider_id'],'provider_name'=>$provider['provider_name'],'category'=>$provider['category'],'region'=>'global','signal_type'=>'public_chatter','severity'=>'watch','confidence'=>$storyUrl?40:25,'title'=>'HN chatter: '.$title,'message'=>'Unconfirmed developer chatter from Hacker News search results.','url'=>$hnUrl,'observed_at'=>(string)$parsed['value'],'snippets'=>[$title],'source_urls'=>$urls,'domains'=>array_values(array_unique(array_filter([wp_parse_url($storyUrl,PHP_URL_HOST),'news.ycombinator.com']))),'confidence_reason'=>'Developer chatter with issue-language match; requires corroboration.','evidence_quality'=>$storyUrl?'moderate':'weak','official_confirmed'=>false,'unconfirmed_note'=>'Unconfirmed developer chatter.'];
                $diag['rows_stored']++;
                $diag['rows_attempted']++;
            }
        }
        update_option('lo_diag_'.$this->id(),$diag,false);
        return $out;
    }
}
