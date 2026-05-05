<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\RumourRadarLogger;
use SuzyEaston\LousyOutages\SignalSourceInterface;

class PublicChatterSource implements SignalSourceInterface {
    private const GDELT_MIN_INTERVAL = 6;
    private const GDELT_MAX_QUERIES_PER_RUN = 4;
    private const SOURCE_RUNTIME_BUDGET_MS = 14000;
    private const ISSUE_TERMS = ['outage','down','unavailable','degraded','error','errors','latency','timeout','failed','failing','login','sign in','authentication','sso','mfa','dns','cdn','api','deploy','build','queue','webhook','incident','status page','e-transfer','payment failed','online banking','mobile banking','internet down','network outage','service unavailable'];

    public function id(): string { return 'public_chatter'; }
    public function label(): string { return 'Public Chatter Radar'; }
    public function is_configured(): bool { return !empty(get_option('lousy_outages_public_chatter_enabled', '0')); }

    public function collect(array $options = []): array {
        if (!$this->is_configured()) return [];
        $started = microtime(true);
        $window = max(10, min(180, (int) apply_filters('lo_public_chatter_window_minutes', 30)));
        $thresholds = ['watch'=>3,'trending'=>6,'hot'=>12];
        $groups = $this->query_groups();
        $cursor = (int)get_option('lousy_outages_public_chatter_group_cursor', 0);
        $groupKeys = array_keys($groups);
        $activeGroup = $groupKeys[$cursor % max(1,count($groupKeys))] ?? 'A';
        $queries = (array)($groups[$activeGroup] ?? []);
        update_option('lousy_outages_public_chatter_group_cursor', ($cursor + 1) % max(1, count($groupKeys)), false);
        $signals = [];
        $gdeltQueries = 0;
        foreach ($queries as $queryMeta) {
            if (((microtime(true)-$started)*1000) > self::SOURCE_RUNTIME_BUDGET_MS) { break; }
            $query = (string)$queryMeta['query']; $category=(string)$queryMeta['category'];
            RumourRadarLogger::log('query_attempt',['source'=>'public_chatter_gdelt','query'=>$query,'category'=>$category]);
            if ($gdeltQueries >= self::GDELT_MAX_QUERIES_PER_RUN) break;
            $mentions = $this->search_gdelt($query);
            if (!empty($mentions['rate_limited'])) { RumourRadarLogger::log('rate_limited',['source'=>'public_chatter_gdelt','query'=>$query]); break; }
            if (!empty($mentions['error'])) { RumourRadarLogger::log('query_result',['source'=>'public_chatter_gdelt','query'=>$query,'status'=>'query_error','reason'=>$mentions['error']]); continue; }
            $gdeltQueries++;
            $usable = $this->filter_usable_items((array)($mentions['items'] ?? []), $query);
            RumourRadarLogger::log('query_result',['source'=>'public_chatter_gdelt','query'=>$query,'usable_count'=>count($usable),'raw_source_count'=>(int)($mentions['raw_source_count']??0)]);
            if (count($usable) === 0) { RumourRadarLogger::log('signal_skipped',['source'=>'public_chatter_gdelt','query'=>$query,'reason'=>'no_evidence']); continue; }
            $detected = $this->detect_provider_or_category($query.' '.implode(' ', array_column($usable,'snippet')));
            $severity = $this->severity_for_count(count($usable), $thresholds);
            $signal = $this->build_signal('public_chatter_gdelt', $detected['provider_id'], $detected['provider_name'], $detected['category'], $severity, count($usable), $window, ['items'=>$usable,'raw_source_count'=>(int)($mentions['raw_source_count']??0)], [$query], $detected);
            if ($this->is_empty_evidence((array)$signal['metadata'])) { RumourRadarLogger::log('signal_skipped',['source'=>'public_chatter_gdelt','query'=>$query,'reason'=>'empty_metadata']); continue; }
            $signals[] = $signal;
            RumourRadarLogger::log('signal_created',['source'=>'public_chatter_gdelt','query'=>$query,'provider'=>$detected['provider_name'],'category'=>$detected['category']]);
        }
        return $signals;
    }

    private function query_groups(): array { return [
        'A'=>[['query'=>'cloud outage','category'=>'cloud_api'],['query'=>'API outage','category'=>'cloud_api'],['query'=>'authentication outage','category'=>'identity_sso']],
        'B'=>[['query'=>'OpenAI outage','category'=>'known_provider'],['query'=>'AWS API errors','category'=>'known_provider'],['query'=>'Cloudflare Workers issue','category'=>'known_provider']],
        'C'=>[['query'=>'GitHub Actions failing','category'=>'ci_cd'],['query'=>'package registry outage','category'=>'package_registries'],['query'=>'Docker pull errors','category'=>'package_registries']],
        'F'=>[['query'=>'Canadian bank outage','category'=>'canadian_banking'],['query'=>'Interac e-Transfer outage','category'=>'canadian_payments'],['query'=>'TD login error','category'=>'canadian_banking']],
        'G'=>[['query'=>'Rogers outage','category'=>'canadian_telecom'],['query'=>'Telus internet outage','category'=>'canadian_telecom'],['query'=>'Shaw internet outage','category'=>'canadian_telecom']],
        'H'=>[['query'=>'BC Services Card login issue','category'=>'bc_local_services'],['query'=>'TransLink Compass outage','category'=>'bc_local_services'],['query'=>'BC Hydro outage','category'=>'bc_local_services']],
    ]; }

    private function search_gdelt(string $q): array {
        $last = (int)get_transient('lousy_outages_gdelt_last_request_ts');
        $wait = self::GDELT_MIN_INTERVAL - (time() - $last);
        if ($wait > 0) { sleep($wait); }
        set_transient('lousy_outages_gdelt_last_request_ts', time(), 60);
        $url=add_query_arg(['query'=>$q,'mode'=>'ArtList','format'=>'json','timespan'=>'60m','sort'=>'datedesc'],'https://api.gdeltproject.org/api/v2/doc/doc');
        $res=wp_remote_get($url,['timeout'=>7]);
        if (is_wp_error($res)) return ['error'=>'http_error'];
        $code = (int)wp_remote_retrieve_response_code($res);
        $rawBody = (string)wp_remote_retrieve_body($res);
        if ($code === 429 || stripos($rawBody, 'limit requests to one every') !== false) return ['rate_limited'=>true];
        if ($rawBody === '') return ['error'=>'empty_body'];
        $body = json_decode($rawBody, true);
        if (!is_array($body) || !isset($body['articles'])) return ['error'=>'non_json_or_parser_error'];
        $arts=(array)$body['articles']; $items=[];
        foreach(array_slice($arts,0,20) as $a){ $title=substr(sanitize_text_field((string)($a['title']??'')),0,140); if($title==='') continue; $domain=(string)wp_parse_url((string)($a['url']??''),PHP_URL_HOST); $items[]=['dedupe_key'=>hash('sha256',strtolower($title)),'snippet'=>$title,'domain'=>sanitize_text_field($domain),'url'=>esc_url_raw((string)($a['url']??''))]; }
        return ['items'=>$items,'raw_source_count'=>count($items)];
    }
    private function filter_usable_items(array $items, string $query): array { $usable=[]; foreach($items as $item){ $txt=strtolower((string)($item['snippet']??'')); $hit=false; foreach(self::ISSUE_TERMS as $term){ if(strpos($txt,$term)!==false){$hit=true; break;} } if(!$hit && !$this->contains_issue_language($query)){ continue; } $usable[]=$item; if(count($usable)>=5) break; } return $usable; }
    private function contains_issue_language(string $text): bool { $text=strtolower($text); foreach(self::ISSUE_TERMS as $term){ if(strpos($text,$term)!==false){ return true; } } return false; }
    private function detect_provider_or_category(string $text): array {
        $txt=strtolower($text);
        $map=['openai'=>['openai','chatgpt'],'aws'=>['aws','us-east','cloudfront'],'github'=>['github actions','github'],'interac'=>['interac','e-transfer'],'rogers'=>['rogers','shaw'],'td'=>['td ','td canada trust'],'bc_services_card'=>['bc services card'],'translink'=>['translink','compass card']];
        foreach($map as $id=>$aliases){ foreach($aliases as $alias){ if(strpos($txt,$alias)!==false){ return ['provider_id'=>$id,'provider_name'=>ucwords(str_replace('_',' ',$id)),'category'=>$this->category_for_provider($id),'confidence_reason'=>'alias_detected: '.$alias]; } } }
        return ['provider_id'=>'','provider_name'=>'Tech Pulse','category'=>$this->category_from_text($txt),'confidence_reason'=>'category_level_unresolved'];
    }
    private function category_for_provider(string $id): string { if(in_array($id,['interac'],true)) return 'canadian_payments'; if(in_array($id,['rogers'],true)) return 'canadian_telecom'; if(in_array($id,['td'],true)) return 'canadian_banking'; if(in_array($id,['bc_services_card','translink'],true)) return 'bc_local_services'; if(in_array($id,['github'],true)) return 'ci_cd'; return 'cloud_api'; }
    private function category_from_text(string $txt): string { if(strpos($txt,'interac')!==false||strpos($txt,'payment')!==false) return 'canadian_payments'; if(strpos($txt,'bank')!==false||strpos($txt,'td ')!==false) return 'canadian_banking'; if(strpos($txt,'rogers')!==false||strpos($txt,'telus')!==false||strpos($txt,'shaw')!==false) return 'canadian_telecom'; if(strpos($txt,'bc ')!==false||strpos($txt,'vancouver')!==false||strpos($txt,'translink')!==false) return 'bc_local_services'; if(strpos($txt,'dns')!==false||strpos($txt,'cdn')!==false) return 'dns_cdn'; if(strpos($txt,'sso')!==false||strpos($txt,'authentication')!==false) return 'identity_sso'; if(strpos($txt,'package')!==false||strpos($txt,'npm')!==false||strpos($txt,'docker')!==false) return 'package_registries'; if(strpos($txt,'build')!==false||strpos($txt,'deploy')!==false||strpos($txt,'actions')!==false) return 'ci_cd'; return 'cloud_api'; }
    private function severity_for_count(int $count, array $t): string { if($count >= $t['hot']) return 'hot'; if($count >= $t['trending']) return 'trending'; return 'watch'; }
    private function build_signal(string $source,string $providerId,string $providerName,string $category,string $severity,int $count,int $window,array $mentions,array $queries,array $detected): array {
        $snippets=array_values(array_filter(array_map(static function($m){return (string)($m['snippet']??'');},(array)($mentions['items']??[]))));
        $domains=array_values(array_unique(array_filter(array_map(static function($m){return (string)($m['domain']??'');},(array)($mentions['items']??[])))));
        $themes=$this->extract_themes($snippets);
        $msg = sprintf('Unconfirmed %s chatter is rising. Recent open-web results mention %s. Official confirmation not found.', str_replace('_',' ', $category), implode(', ', array_slice($themes,0,3)) ?: 'service issues');
        return ['source'=>$source,'provider_id'=>$providerId,'provider_name'=>$providerName,'category'=>$category,'region'=>'global','signal_type'=>'public_chatter','severity'=>$severity,'confidence'=>$severity==='hot'?70:($severity==='trending'?55:35),'title'=>$providerId!==''?"Unconfirmed chatter for {$providerName}":'Unconfirmed category-level tech pulse chatter','message'=>$msg,'url'=>'https://api.gdeltproject.org/api/v2/doc/doc','observed_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$window*60),'raw_hash'=>hash('sha256',$source.'|'.$providerId.'|'.$severity.'|'.$count.'|'.implode('|',$snippets)),'metadata'=>['summary'=>mb_substr($msg,0,300),'themes'=>array_slice($themes,0,5),'snippets'=>array_slice($snippets,0,5),'domains'=>array_slice($domains,0,5),'source_urls'=>array_slice(array_values(array_filter(array_map(static fn($i)=>(string)($i['url']??''),(array)($mentions['items']??[])))),0,3),'query'=>implode(', ',array_slice($queries,0,1)),'queries'=>array_slice($queries,0,3),'mention_count'=>$count,'window_minutes'=>$window,'raw_source_count'=>(int)($mentions['raw_source_count']??$count),'source_labels'=>['Unconfirmed open-web chatter'],'detected_provider'=>$providerName,'detected_category'=>$category,'confidence_reason'=>(string)($detected['confidence_reason']??'')]];
    }
    private function is_empty_evidence(array $metadata): bool { return (int)($metadata['mention_count']??0)===0 && (int)($metadata['raw_source_count']??0)===0 && empty($metadata['snippets']) && empty($metadata['themes']) && empty($metadata['domains']); }
    private function extract_themes(array $snippets): array { $themes=[]; $txt=strtolower(implode(' ', $snippets)); foreach(self::ISSUE_TERMS as $k){ if(strpos($txt,$k)!==false){$themes[]=$k;} } return array_slice(array_values(array_unique($themes)),0,6); }
}
