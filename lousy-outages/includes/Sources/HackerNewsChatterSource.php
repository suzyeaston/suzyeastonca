<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class HackerNewsChatterSource implements SignalSourceInterface {
    public function id(): string { return 'hacker_news_chatter'; }
    public function label(): string { return 'Hacker News Chatter'; }
    public function is_configured(): bool { return SourcePack::enabled() && (bool) apply_filters('lo_hn_chatter_enabled', get_option('lo_hn_chatter_enabled', '1') === '1'); }
    private function issue(string $t): bool {
        return (bool) preg_match('/\b(is down|down for me|outage|degrad(?:ed|ing)?|unavailable|not working|unable to login|login not working|mobile banking down|online banking down|e-transfer failing|debit unavailable|atm down|card payments failing|mobile data unavailable|no cell service|landline down|911 unavailable|compass card down|service outage|failing|failures|returning 500|returning 502|5xx|errors?|timeouts?|latency spike|stuck|cannot connect|connection refused|api error|deploys failing|actions failing|npm install failing)\b/i', $t);
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
            'rogers' => ['name'=>'Rogers','category'=>'canadian_telecom','aliases'=>['rogers','rogers wireless','rogers internet']],
            'shaw' => ['name'=>'Shaw','category'=>'canadian_telecom','aliases'=>['shaw','shaw internet']],
            'bell' => ['name'=>'Bell','category'=>'canadian_telecom','aliases'=>['bell','bell canada','bell mobility']],
            'telus' => ['name'=>'TELUS','category'=>'canadian_telecom','aliases'=>['telus']],
            'freedom_mobile' => ['name'=>'Freedom Mobile','category'=>'canadian_telecom','aliases'=>['freedom mobile','freedom']],
            'videotron' => ['name'=>'Videotron','category'=>'canadian_telecom','aliases'=>['videotron']],
            'fizz' => ['name'=>'Fizz','category'=>'canadian_telecom','aliases'=>['fizz']],
            'koodo' => ['name'=>'Koodo','category'=>'canadian_telecom','aliases'=>['koodo']],
            'virgin_plus' => ['name'=>'Virgin Plus','category'=>'canadian_telecom','aliases'=>['virgin plus','virgin mobile']],
            'public_mobile' => ['name'=>'Public Mobile','category'=>'canadian_telecom','aliases'=>['public mobile']],
            'moneris' => ['name'=>'Moneris','category'=>'canadian_payments','aliases'=>['moneris']],
            'global_payments' => ['name'=>'Global Payments','category'=>'canadian_payments','aliases'=>['global payments']],
            'stripe_canada' => ['name'=>'Stripe Canada','category'=>'canadian_payments','aliases'=>['stripe canada']],
            'square' => ['name'=>'Square','category'=>'canadian_payments','aliases'=>['square']],
            'rbc' => ['name'=>'RBC','category'=>'canadian_bank','aliases'=>['rbc','royal bank']],
            'td' => ['name'=>'TD','category'=>'canadian_bank','aliases'=>['td','td canada trust','td easyweb']],
            'scotiabank' => ['name'=>'Scotiabank','category'=>'canadian_bank','aliases'=>['scotiabank']],
            'bmo' => ['name'=>'BMO','category'=>'canadian_bank','aliases'=>['bmo','bank of montreal']],
            'cibc' => ['name'=>'CIBC','category'=>'canadian_bank','aliases'=>['cibc']],
            'national_bank' => ['name'=>'National Bank','category'=>'canadian_bank','aliases'=>['national bank']],
            'tangerine' => ['name'=>'Tangerine','category'=>'canadian_bank','aliases'=>['tangerine']],
            'simplii' => ['name'=>'Simplii','category'=>'canadian_bank','aliases'=>['simplii']],
            'eq_bank' => ['name'=>'EQ Bank','category'=>'canadian_bank','aliases'=>['eq bank']],
            'vancity' => ['name'=>'Vancity','category'=>'canadian_bank','aliases'=>['vancity']],
            'coast_capital' => ['name'=>'Coast Capital','category'=>'canadian_bank','aliases'=>['coast capital']],
            'desjardins' => ['name'=>'Desjardins','category'=>'canadian_bank','aliases'=>['desjardins']],
            'bc_services_card' => ['name'=>'BC Services Card','category'=>'canadian_government','aliases'=>['bc services card']],
            'cra_my_account' => ['name'=>'CRA My Account','category'=>'canadian_government','aliases'=>['cra my account']],
            'service_canada' => ['name'=>'Service Canada','category'=>'canadian_government','aliases'=>['service canada']],
            'translink_compass' => ['name'=>'TransLink Compass','category'=>'canadian_transit','aliases'=>['translink compass','compass card']],
            'ecomm_911' => ['name'=>'E-Comm 911','category'=>'emergency_services','aliases'=>['911','e-comm 911','ecomm 911']],
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
    private function contains_pattern(string $text, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if ((bool) preg_match($pattern, $text)) { return true; }
        }
        return false;
    }
    private function sentence_chunks(string $text): array {
        $chunks = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        return array_values(array_filter(array_map('trim', $chunks), static fn($c) => $c !== ''));
    }
    private function has_provider_failure_match_in_quote(string $quote, array $providerAliases): bool {
        if ($quote === '' || $providerAliases === []) { return false; }
        if ($this->contains_pattern($quote, ['/\\b(no outage|not down|not seeing an outage|have not seen any outage|haven\\\'t seen any outage|not broken|works for me|no issues|not failing)\\b/i'])) { return false; }
        $issuePattern = '(?:is down|down for me|outage|degraded|unavailable|not working|failing|failures|returning\s+500|returning\s+502|5xx|errors?|timeouts?|latency spike|stuck|cannot connect|connection refused|api error|deploys failing|actions failing|npm install failing)';
        foreach ($this->sentence_chunks($quote) as $sentence) {
            foreach ($providerAliases as $alias) {
                $aliasPattern = preg_quote((string)$alias, '/');
                if ((bool) preg_match('/\b' . $aliasPattern . '\b(?:.{0,80})\b' . $issuePattern . '\b/iu', $sentence)) { return true; }
                if ((bool) preg_match('/\b' . $issuePattern . '\b(?:.{0,80})\b' . $aliasPattern . '\b/iu', $sentence)) { return true; }
            }
        }
        return false;
    }
    private function is_generic_noise(string $evidenceText): bool {
        return (bool) preg_match('/\b(natural language processing|voice ai at scale|interesting project|\bmimic\b|pricing|job postings?|blog cache|traffic is down|down\s+60%|\bvibe\b|\brust\b|\bzig\b|\bbun\b|general llm|ai discussion|stock is down|shares are down|earnings down|prices down|rates down|branch closed|planned maintenance|historical outage)\b/i', $evidenceText);
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
        $queries=SourcePack::early_warning_queries();
        $queriesPerRun = max(1, min(12, (int) apply_filters('lo_hn_chatter_queries_per_run', (int) get_option('lo_hn_chatter_queries_per_run', 6))));
        $hitsPerQuery = max(5, min(50, (int) apply_filters('lo_hn_hits_per_query', (int) get_option('lo_hn_hits_per_query', 20))));
        $cursor=(int)get_option('lo_hn_query_cursor',0);
        $rotating = array_merge($queries,$queries);
        $take=array_slice($rotating,$cursor,$queriesPerRun);
        if (count($take) < $queriesPerRun && !empty($queries)) { $take = array_slice(array_merge($take, $queries), 0, $queriesPerRun); }
        $windowMinutes = max(5, min(1440, (int)($options['window_minutes'] ?? $options['windowMinutes'] ?? 60)));
        $lookbackHours = max(1, min(72, (int)($options['chatter_lookback_hours'] ?? get_option('lo_chatter_lookback_hours', 24))));
        $minTs = time() - ($windowMinutes * 60);
        $lookbackMinTs = time() - ($lookbackHours * HOUR_IN_SECONDS);
        $skipBudgetMutation = $this->should_skip_budget_mutation($options);
        if (!$skipBudgetMutation) { update_option('lo_hn_query_cursor',($cursor+$queriesPerRun)%max(1,count($queries)),false); }
        $diag=['configured'=>true,'attempted'=>false,'queries_available'=>count($queries),'queries_attempted'=>0,'queries_skipped_budget'=>0,'raw_results_seen'=>0,'usable_results'=>0,'rows_stored'=>0,'rows_attempted'=>0,'results_old_skipped'=>0,'results_missing_date'=>0,'rows_inserted'=>null,'skipped_reasons'=>[],'cooldown_active'=>false,'chatter_queries_attempted'=>0,'chatter_raw_results_seen'=>0,'chatter_recent_results'=>0,'chatter_old_skipped'=>0,'chatter_rows_attempted'=>0,'chatter_rows_output'=>0,'chatter_rows_skipped'=>0,'chatter_rows_skipped_no_issue_language'=>0,'chatter_rows_skipped_empty_quote'=>0,'chatter_rows_skipped_old'=>0,'chatter_rows_skipped_no_provider_match'=>0,'chatter_rows_skipped_weak_issue_language'=>0,'chatter_rows_skipped_generic_noise'=>0,'chatter_rows_skipped_negated_issue'=>0,'chatter_rows_skipped_resolved_or_historical'=>0,'chatter_rows_skipped_quote_missing_provider'=>0,'chatter_rows_skipped_quote_missing_failure'=>0,'chatter_rows_accepted_provider_failure_match'=>0,'chatter_candidates_preview_sample'=>[],'chatter_sources_enabled'=>['hacker_news'],'chatter_sources_disabled'=>[],'first_chatter_error'=>''];
        $out=[];
        foreach($take as $q){
            $budget=['ok'=>true];
            if (!$skipBudgetMutation) { $budget=SourceBudgetManager::can_attempt($this->id(),'hn.algolia.com',20); }
            if(empty($budget['ok'])){ $diag['queries_skipped_budget']++; $diag['cooldown_active']=true; continue; }
            $diag['queries_attempted']++; $diag['attempted']=true;
            $diag['chatter_queries_attempted']++;
            $url=add_query_arg(['query'=>$q,'tags'=>'(story,comment)','hitsPerPage'=>$hitsPerQuery],'https://hn.algolia.com/api/v1/search_by_date');
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
            foreach(array_slice($hits,0,$hitsPerQuery) as $hit){
                $diag['chatter_rows_attempted']=($diag['chatter_rows_attempted']??0)+1;
                $title=$this->clean_quote((string)($hit['title']??$hit['story_title']??''));
                $txt=$this->clean_quote((string)($hit['comment_text']??''));
                $quote = $this->clean_quote(trim($txt !== '' ? $txt : $title));
                if($quote==='') { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_empty_quote']=($diag['chatter_rows_skipped_empty_quote']??0)+1; continue; }
                $evidenceText = trim($title . ' ' . $txt);
                if ($this->contains_pattern($evidenceText, ['/\\b(no outage|not down|not seeing an outage|have not seen any outage|haven\\\'t seen any outage|not broken|works for me|no issues|not failing)\\b/i'])) {
                    $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_negated_issue']++;
                    $diag['chatter_candidates_preview_sample'][] = ['title'=>$title,'quote'=>$quote,'source'=>'hacker_news','reject_reason'=>'negated_issue'];
                    continue;
                }
                if ($this->contains_pattern(strtolower($title.' '.$evidenceText), ['/\\b(resolved|postmortem|incident report|rca|from\\s+[a-z]+|from\\s+20(22|25)|last year|last week|yesterday\\\'s incident|was down|previous outage|historical outage)\\b/i'])) {
                    $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_resolved_or_historical']++;
                    $diag['chatter_candidates_preview_sample'][] = ['title'=>$title,'quote'=>$quote,'source'=>'hacker_news','reject_reason'=>'resolved_or_historical'];
                    continue;
                }
                if ($this->contains_pattern($evidenceText, ['/\\b(lawsuit|regulation|business news)\\b/i']) && !$this->issue($evidenceText)) {
                    $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_generic_noise']=($diag['chatter_rows_skipped_generic_noise']??0)+1;
                    $diag['chatter_candidates_preview_sample'][] = ['title'=>$title,'quote'=>$quote,'source'=>'hacker_news','reject_reason'=>'business_or_regulatory_chatter'];
                    continue;
                }
                if($this->is_generic_noise($evidenceText)) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_generic_noise']=($diag['chatter_rows_skipped_generic_noise']??0)+1; $this->maybe_log_rejection($options, $quote, 'generic_noise'); continue; }
                if(!$this->issue($evidenceText)) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_no_issue_language']=($diag['chatter_rows_skipped_no_issue_language']??0)+1; continue; }
                $provider=$this->detect_provider_from_evidence($evidenceText);
                if(empty($provider['provider_id'])) { $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_no_provider_match']=($diag['chatter_rows_skipped_no_provider_match']??0)+1; $this->maybe_log_rejection($options, $quote, 'no_provider_match'); continue; }
                if(!$this->has_provider_failure_match_in_quote($quote, (array)($provider['aliases'] ?? []))) {
                    if (!(bool) preg_match('/\b(?:' . implode('|', array_map(static fn($a) => preg_quote((string)$a, '/'), (array)($provider['aliases'] ?? []))) . ')\b/i', $quote)) {
                        $diag['chatter_rows_skipped_quote_missing_provider']++;
                    } else {
                        $diag['chatter_rows_skipped_quote_missing_failure']++;
                    }
                    $diag['chatter_rows_skipped']++; $diag['chatter_rows_skipped_weak_issue_language']=($diag['chatter_rows_skipped_weak_issue_language']??0)+1;
                    $diag['chatter_candidates_preview_sample'][] = ['title'=>$title,'quote'=>$quote,'source'=>'hacker_news','reject_reason'=>'quote_missing_provider_or_failure','provider'=>(string)($provider['provider_name'] ?? ''),'category'=>(string)($provider['category'] ?? ''),'source_url'=>(string)('https://news.ycombinator.com/item?id='.(int)($hit['objectID']??0))];
                    continue;
                }
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
                $diag['chatter_rows_accepted_provider_failure_match']++;
                $diag['chatter_candidates_preview_sample'][] = ['title'=>$title,'quote'=>$quote,'source'=>'hacker_news','reject_reason'=>'accepted','provider'=>(string)$provider['provider_name'],'category'=>(string)$provider['category'],'source_url'=>$hnUrl,'observed_at'=>(string)$parsed['value']];
            }
        }
        $diag['chatter_candidates_preview_sample'] = array_slice($diag['chatter_candidates_preview_sample'], 0, 30);
        $out = $this->limit_output($out);
        update_option('lo_diag_'.$this->id(),$diag,false);
        return $out;
    }
    private function limit_output(array $rows): array {
        $deduped = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = strtolower((string)($row['source_id'] ?? '') . '|' . (string)($row['url'] ?? '') . '|' . md5((string) wp_json_encode($row)));
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $deduped[] = $row;
        }
        $providerCounts = [];
        $limited = [];
        foreach ($deduped as $row) {
            $providerId = (string)($row['provider_id'] ?? '');
            $providerCounts[$providerId] = (int)($providerCounts[$providerId] ?? 0);
            if ($providerId !== '' && $providerCounts[$providerId] >= 2) { continue; }
            $providerCounts[$providerId]++;
            $limited[] = $row;
            if (count($limited) >= 5) { break; }
        }
        return $limited;
    }
    private function maybe_log_rejection(array $options, string $quote, string $reason): void {
        if (empty($options['dry_run']) || empty($options['diagnostic_mode'])) { return; }
        error_log(sprintf('[HN chatter dry-run] skipped=%s quote="%s"', $reason, $quote));
    }
}
