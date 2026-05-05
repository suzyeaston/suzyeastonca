<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class HackerNewsChatterSource implements SignalSourceInterface {
    public function id(): string { return 'hacker_news_chatter'; }
    public function label(): string { return 'Hacker News Chatter'; }
    public function is_configured(): bool { return SourcePack::enabled() && (bool) apply_filters('lo_hn_chatter_enabled', get_option('lo_hn_chatter_enabled', '1') === '1'); }
    private function issue(string $t): bool {
        return (bool) preg_match('/\b(outage|is down|down\b|incident|degrad(?:ed|ing)?|latency(?: spike)?|errors?|failing|failed|not working|unavailable|disruption|returning 5\d\d|stuck)\b/i', $t);
    }
    private function provider_aliases(): array {
        return [
            'github' => ['name'=>'GitHub','category'=>'developer_tooling','aliases'=>['github','github actions']],
            'npm' => ['name'=>'npm','category'=>'package_registry','aliases'=>['npm','npmjs']],
            'docker' => ['name'=>'Docker','category'=>'package_registry','aliases'=>['docker','docker hub','docker pull']],
            'cloudflare' => ['name'=>'Cloudflare','category'=>'dns_cdn','aliases'=>['cloudflare','cloudflare workers','workers']],
            'openai' => ['name'=>'OpenAI','category'=>'ai_api','aliases'=>['openai','chatgpt','openai api']],
            'vercel' => ['name'=>'Vercel','category'=>'developer_tooling','aliases'=>['vercel']],
            'netlify' => ['name'=>'Netlify','category'=>'developer_tooling','aliases'=>['netlify']],
            'aws' => ['name'=>'AWS','category'=>'cloud_api','aliases'=>['aws','amazon web services']],
            'azure' => ['name'=>'Azure','category'=>'cloud_identity','aliases'=>['azure','entra']],
            'google_cloud' => ['name'=>'Google Cloud','category'=>'cloud_api','aliases'=>['google cloud','gcp']],
            'interac' => ['name'=>'Interac','category'=>'canadian_payments','aliases'=>['interac','e-transfer','etransfer']],
            'canadian_telecom' => ['name'=>'Canadian Telecom','category'=>'canadian_telecom','aliases'=>['rogers','shaw','telus','bell','freedom']],
        ];
    }
    private function detect_provider_from_evidence(string $evidenceText): array {
        foreach ($this->provider_aliases() as $id => $meta) {
            foreach ((array)($meta['aliases'] ?? []) as $alias) {
                if ((bool) preg_match('/\b' . preg_quote((string) $alias, '/') . '\b/i', $evidenceText)) {
                    return ['provider_id'=>$id,'provider_name'=>(string)$meta['name'],'category'=>(string)$meta['category'],'aliases'=>(array)$meta['aliases']];
                }
            }
        }
        return ['provider_id'=>'','provider_name'=>'','category'=>'public_chatter','aliases'=>[]];
    }
    private function has_outage_phrase_near_provider(string $evidenceText, array $providerAliases): bool {
        if ($providerAliases === []) { return false; }
        $issuePattern = '(?:outage|incident|degraded|degrading|latency(?: spike)?|errors?|failing|failed|not working|unavailable|stuck|returning 5\d\d|is down|down)';
        foreach ($providerAliases as $alias) {
            $aliasPattern = preg_quote((string)$alias, '/');
            if ((bool) preg_match('/\b' . $aliasPattern . '\b(?:.{0,50})\b' . $issuePattern . '\b/iu', $evidenceText)) { return true; }
            if ((bool) preg_match('/\b' . $issuePattern . '\b(?:.{0,50})\b' . $aliasPattern . '\b/iu', $evidenceText)) { return true; }
        }
        return false;
    }
    private function is_generic_noise(string $evidenceText): bool {
        return (bool) preg_match('/\b(down\s+\d+%|traffic is down|killing your vibe|port(ed|ing).+rust|job postings|rebuilt my blog|cache|welcome to gas city|pricing)\b/i', $evidenceText);
    }
    private function clean_quote(string $text): string {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = wp_strip_all_tags($decoded);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $stripped));
        return sanitize_text_field(mb_substr($normalized, 0, 180));
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
        $lookbackHours = max(1, min(72, (int)($options['chatter_lookback_hours'] ?? get_option('lo_chatter_lookback_hours', 24))));
        $minTs = time() - ($windowMinutes * 60);
        $lookbackMinTs = time() - ($lookbackHours * HOUR_IN_SECONDS);
        $skipBudgetMutation = $this->should_skip_budget_mutation($options);
        $diag=['configured'=>true,'attempted'=>false,'queries_available'=>count($queries),'queries_attempted'=>0,'queries_skipped_budget'=>0,'raw_results_seen'=>0,'usable_results'=>0,'rows_stored'=>0,'rows_attempted'=>0,'results_old_skipped'=>0,'results_missing_date'=>0,'rows_inserted'=>null,'skipped_reasons'=>[],'cooldown_active'=>false,'chatter_queries_attempted'=>0,'chatter_raw_results_seen'=>0,'chatter_recent_results'=>0,'chatter_old_skipped'=>0,'chatter_rows_attempted'=>0,'chatter_rows_output'=>0,'chatter_rows_skipped'=>0,'chatter_rows_skipped_no_issue_language'=>0,'chatter_rows_skipped_empty_quote'=>0,'chatter_rows_skipped_old'=>0,'chatter_rows_skipped_no_provider_match'=>0,'chatter_rows_skipped_weak_issue_language'=>0,'chatter_rows_skipped_generic_noise'=>0,'chatter_sources_enabled'=>['hacker_news'],'chatter_sources_disabled'=>[],'first_chatter_error'=>''];
        $out=[];
        foreach($take as $q){
            $budget=['ok'=>true];
            if (!$skipBudgetMutation) { $budget=SourceBudgetManager::can_attempt($this->id(),'hn.algolia.com',20); }
            if(empty($budget['ok'])){ $diag['queries_skipped_budget']++; $diag['cooldown_active']=true; continue; }
            $diag['queries_attempted']++; $diag['attempted']=true;
            $diag['chatter_queries_attempted']++;
            $url=add_query_arg(['query'=>$q,'tags'=>'(story,comment)','hitsPerPage'=>10],'https://hn.algolia.com/api/v1/search_by_date');
            $cache='lo_hn_'.md5($url); $hits=get_transient($cache);
            if(!is_array($hits)){
                $r=wp_remote_get($url,['timeout'=>7]); if(!$skipBudgetMutation){ SourceBudgetManager::mark_attempt($this->id(),'hn.algolia.com',10); }
                if(is_wp_error($r)) { if(!$skipBudgetMutation){ SourceBudgetManager::mark_result($this->id(),false,0); } $diag['skipped_reasons'][]='http_error'; if($diag['first_chatter_error']===''){ $diag['first_chatter_error']='http_error'; } continue; }
                $code=(int)wp_remote_retrieve_response_code($r); if($code===429){if(!$skipBudgetMutation){SourceBudgetManager::mark_result($this->id(),false,429);} $diag['cooldown_active']=true; continue;}
                if($code<200||$code>=300){if(!$skipBudgetMutation){SourceBudgetManager::mark_result($this->id(),false,$code);} continue;}
                if(!$skipBudgetMutation){ SourceBudgetManager::mark_result($this->id(),true,$code); }
                $json=json_decode((string)wp_remote_retrieve_body($r),true); $hits=(array)($json['hits']??[]); set_transient($cache,$hits,10*MINUTE_IN_SECONDS);
            }
            $diag['raw_results_seen']+=count($hits);
            $diag['chatter_raw_results_seen']+=count($hits);
            foreach(array_slice($hits,0,10) as $hit){
                $diag['chatter_rows_attempted']=($diag['chatter_rows_attempted']??0)+1;
                $title=$this->clean_quote((string)($hit['title']??$hit['story_title']??''));
                $txt=$this->clean_quote((string)($hit['comment_text']??''));
                $quote = $this->clean_quote(trim($txt !== '' ? $txt : $title));
                if($quote==='') { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_empty_quote']=($diag['chatter_rows_skipped_empty_quote']??0)+1; continue; }
                $evidenceText = trim($title . ' ' . $txt);
                if($this->is_generic_noise($evidenceText)) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_generic_noise']=($diag['chatter_rows_skipped_generic_noise']??0)+1; $this->maybe_log_rejection($options, $quote, 'generic_noise'); continue; }
                if(!$this->issue($evidenceText)) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_no_issue_language']=($diag['chatter_rows_skipped_no_issue_language']??0)+1; continue; }
                $provider=$this->detect_provider_from_evidence($evidenceText);
                if(empty($provider['provider_id'])) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_no_provider_match']=($diag['chatter_rows_skipped_no_provider_match']??0)+1; $this->maybe_log_rejection($options, $quote, 'no_provider_match'); continue; }
                if(!$this->has_outage_phrase_near_provider($evidenceText, (array)($provider['aliases'] ?? []))) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_weak_issue_language']=($diag['chatter_rows_skipped_weak_issue_language']??0)+1; $this->maybe_log_rejection($options, $quote, 'weak_issue_language'); continue; }
                $parsed = $this->parse_observed_at($hit);
                if (empty($parsed['ok'])) { $diag['results_missing_date']++; continue; }
                if ((int)$parsed['ts'] < $lookbackMinTs) { $diag['results_old_skipped']++; $diag['chatter_old_skipped']++; $diag['chatter_rows_skipped_old']=($diag['chatter_rows_skipped_old']??0)+1; continue; }
                $diag['usable_results']++;
                $diag['chatter_recent_results']++;
                $storyUrl=esc_url_raw((string)($hit['url']??$hit['story_url']??'')); $hnUrl='https://news.ycombinator.com/item?id='.(int)($hit['objectID']??0);
                $urls=array_values(array_filter([$hnUrl,$storyUrl]));
                $isRecentCurrent = ((int)$parsed['ts'] >= $minTs);
                $message = $isRecentCurrent ? 'Public chatter reports possible user impact.' : 'Recent chatter mentions outage symptoms. Needs corroboration from official/synthetic signals.';
                $out[]=['source'=>'hacker_news_chatter','source_type'=>'public_chatter','adapter_id'=>'hacker_news_chatter','source_id'=>sanitize_text_field((string)($hit['objectID']??md5($title.$hnUrl))),'provider_id'=>$provider['provider_id'],'provider_name'=>$provider['provider_name'],'category'=>$isRecentCurrent ? 'public_chatter' : 'rumour_radar','region'=>'global','signal_type'=>'public_chatter','severity'=>'watch','confidence'=>$storyUrl?40:25,'title'=>'HN chatter: '.$title,'message'=>$message,'url'=>$hnUrl,'observed_at'=>(string)$parsed['value'],'snippets'=>[$quote],'source_urls'=>$urls,'domains'=>array_values(array_unique(array_filter([wp_parse_url($storyUrl,PHP_URL_HOST),'news.ycombinator.com']))),'confidence_reason'=>'Developer chatter with issue-language match; requires corroboration.','evidence_quality'=>$storyUrl?'moderate':'weak','official_confirmed'=>false,'unconfirmed_note'=>'UNCONFIRMED / RECENT CHATTER','signal_lane'=>'chatter','evidence_platform'=>'Hacker News','evidence_source_label'=>'Hacker News','evidence_quote'=>$quote,'evidence_url'=>$hnUrl,'evidence_urls'=>$urls,'evidence_observed_at'=>(string)$parsed['value']];
                $diag['rows_stored']++;
                $diag['rows_attempted']++;
                $diag['chatter_rows_output']=($diag['chatter_rows_output']??0)+1;
            }
        }
        update_option('lo_diag_'.$this->id(),$diag,false);
        return $out;
    }
    private function maybe_log_rejection(array $options, string $quote, string $reason): void {
        if (empty($options['dry_run']) || empty($options['diagnostic_mode'])) { return; }
        error_log(sprintf('[HN chatter dry-run] skipped=%s quote="%s"', $reason, $quote));
    }
}
