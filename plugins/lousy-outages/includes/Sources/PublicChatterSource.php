<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\SignalSourceInterface;

class PublicChatterSource implements SignalSourceInterface {
    public function id(): string { return 'public_chatter'; }
    public function label(): string { return 'Public Chatter Radar'; }
    public function is_configured(): bool { return !empty(get_option('lousy_outages_public_chatter_enabled', '0')); }

    public function collect(array $options = []): array {
        if (!$this->is_configured()) return [];
        $window = max(10, min(180, (int) apply_filters('lo_public_chatter_window_minutes', 30)));
        $thresholds = [
            'watch' => (int) apply_filters('lo_public_chatter_watch_threshold', 3),
            'trending' => (int) apply_filters('lo_public_chatter_trending_threshold', 6),
            'hot' => (int) apply_filters('lo_public_chatter_hot_threshold', 12),
        ];
        $providers = Providers::list();
        $queries = (array) apply_filters('lo_public_chatter_queries', $this->default_queries($providers), $providers);
        $signals = [];
        $sources = [
            'public_chatter_bluesky' => !empty(get_option('lousy_outages_public_chatter_bluesky_enabled', true)),
            'public_chatter_mastodon' => !empty(get_option('lousy_outages_public_chatter_mastodon_enabled', false)),
            'public_chatter_gdelt' => !empty(get_option('lousy_outages_public_chatter_gdelt_enabled', true)),
        ];
        foreach ($queries as $providerId => $providerQueries) {
            $provider = (array)($providers[$providerId] ?? ['name' => ucfirst((string)$providerId), 'category'=>'general']);
            foreach ($sources as $sourceId => $enabled) {
                if (!$enabled) continue;
                $mentions = $this->collect_mentions($sourceId, (array)$providerQueries, $window);
                $count = (int)($mentions['count'] ?? 0);
                $severity = $this->severity_for_count($count, $thresholds);
                if ($severity === '') continue;
                $signals[] = $this->build_signal($sourceId, (string)$providerId, (string)($provider['name'] ?? $providerId), (string)($provider['category'] ?? 'general'), $severity, $count, $window, $mentions, (array)$providerQueries);
            }
        }
        return $signals;
    }

    private function default_queries(array $providers): array {
        $map = [
            'cloudflare' => ['cloudflare down','cloudflare outage','cloudflare warp down'],
            'aws' => ['aws down','aws outage','aws us-east-1'],
            'azure' => ['azure down','azure outage','microsoft 365 down'],
            'google_cloud' => ['google cloud outage','gcp outage'],
            'openai' => ['chatgpt down','openai outage'],
            'slack' => ['slack down','slack outage'],
        ];
        return array_intersect_key($map, $providers);
    }
    private function collect_mentions(string $sourceId, array $queries, int $window): array {
        $items=[]; foreach(array_slice($queries,0,4) as $q){
            if ($sourceId==='public_chatter_bluesky') $items=array_merge($items,$this->search_bluesky($q,$window));
            elseif ($sourceId==='public_chatter_mastodon') $items=array_merge($items,$this->search_mastodon($q,$window));
            elseif ($sourceId==='public_chatter_gdelt') $items=array_merge($items,$this->search_gdelt($q));
        }
        $dedupe=[]; $unique=[];
        foreach ($items as $item) { $k=(string)($item['dedupe_key'] ?? ''); if($k===''||isset($dedupe[$k])){continue;} $dedupe[$k]=true; $unique[]=$item; }
        return ['count'=>count($unique),'items'=>array_slice($unique,0,5),'raw_source_count'=>count($items)];
    }
    private function search_bluesky(string $q,int $window): array {
        $url = add_query_arg(['q'=>$q,'limit'=>25,'sort'=>'latest'],'https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts');
        $res = wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res) || (int)wp_remote_retrieve_response_code($res)>=400) return [];
        $body = json_decode((string)wp_remote_retrieve_body($res), true); $posts=(array)($body['posts']??[]); $min=time()-$window*60; $hashes=[];
        foreach($posts as $p){ $created = strtotime((string)($p['record']['createdAt'] ?? $p['indexedAt'] ?? '')); if($created && $created < $min) continue; $text=substr(sanitize_text_field((string)($p['record']['text'] ?? '')),0,120); $hashes[] = ['dedupe_key'=>hash('sha256',strtolower(preg_replace('/\s+/',' ',(string)$text))),'snippet'=>$text,'url'=>(string)($p['uri'] ?? '')]; }
        return $hashes;
    }
    private function search_mastodon(string $q,int $window): array {
        $instances=(array)apply_filters('lo_public_chatter_mastodon_instances',['https://mastodon.social']); $hashes=[]; $min=time()-$window*60;
        foreach(array_slice($instances,0,2) as $instance){ $url = trailingslashit((string)$instance).'api/v2/search?q='.rawurlencode($q).'&type=statuses&limit=20'; $res=wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res)||(int)wp_remote_retrieve_response_code($res)>=400) continue; $body=json_decode((string)wp_remote_retrieve_body($res),true); foreach((array)($body['statuses']??[]) as $s){ $created=strtotime((string)($s['created_at']??'')); if($created && $created<$min) continue; $text=substr(sanitize_text_field(wp_strip_all_tags((string)($s['content']??''))),0,120); $hashes[] = ['dedupe_key'=>hash('sha256',strtolower(preg_replace('/\s+/',' ',(string)$text))),'snippet'=>$text,'url'=>(string)($s['url']??'')]; }}
        return $hashes;
    }
    private function search_gdelt(string $q): array {
        $url=add_query_arg(['query'=>$q,'mode'=>'ArtList','format'=>'json','timespan'=>'60m','sort'=>'datedesc'],'https://api.gdeltproject.org/api/v2/doc/doc');
        $res=wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res)||(int)wp_remote_retrieve_response_code($res)>=400) return [];
        $body=json_decode((string)wp_remote_retrieve_body($res),true); $arts=(array)($body['articles']??[]); $hashes=[]; foreach(array_slice($arts,0,20) as $a){ $title=substr(sanitize_text_field((string)($a['title']??'')),0,120); $domain=(string)wp_parse_url((string)($a['url']??''),PHP_URL_HOST); $hashes[] = ['dedupe_key'=>hash('sha256',strtolower($title)),'snippet'=>$title,'domain'=>sanitize_text_field($domain),'url'=>esc_url_raw((string)($a['url']??''))]; } return $hashes;
    }
    private function severity_for_count(int $count, array $t): string { if($count >= $t['hot']) return 'hot'; if($count >= $t['trending']) return 'trending'; if($count >= $t['watch']) return 'watch'; return ''; }
    private function build_signal(string $source,string $providerId,string $providerName,string $category,string $severity,int $count,int $window,array $mentions,array $queries): array {
        $confMap=['watch'=>25,'trending'=>45,'hot'=>65]; if($source==='public_chatter_gdelt') $confMap=['watch'=>35,'trending'=>55,'hot'=>70];
        $snippets=array_values(array_filter(array_map(static function($m){return (string)($m['snippet']??'');},(array)($mentions['items']??[]))));
        $domains=array_values(array_unique(array_filter(array_map(static function($m){return (string)($m['domain']??'');},(array)($mentions['items']??[])))));
        $themes=$this->extract_themes($snippets);
        $msg = sprintf('%s public chatter suggests unconfirmed issues around %s. Official status not confirmed.', $providerName, implode(', ', array_slice($themes,0,3)) ?: 'service disruptions');
        return ['source'=>$source,'provider_id'=>$providerId,'provider_name'=>$providerName,'category'=>$source==='public_chatter_gdelt'?'open_web':$category,'region'=>'global','signal_type'=>'public_chatter','severity'=>$severity,'confidence'=>min(85,max(0,(int)$confMap[$severity])),'title'=>$source==='public_chatter_gdelt'?"Unconfirmed open-web chatter for {$providerName}":"Unconfirmed social chatter for {$providerName}",'message'=>$msg,'url'=>$this->safe_url_for_source($source,$providerName),'observed_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$window*60),'raw_hash'=>hash('sha256',$source.'|'.$providerId.'|'.$severity.'|'.$count.'|'.implode('|',$snippets)),'metadata'=>['summary'=>$msg,'themes'=>$themes,'snippets'=>array_slice($snippets,0,5),'domains'=>array_slice($domains,0,5),'query'=>implode(' OR ',array_slice($queries,0,3)),'queries'=>array_slice($queries,0,4),'mention_count'=>$count,'window_minutes'=>$window,'raw_source_count'=>(int)($mentions['raw_source_count']??$count),'classification'=>$severity,'unconfirmed_note'=>'Unconfirmed public chatter. Official status not confirmed.']];
    }
    private function extract_themes(array $snippets): array {
        $keywords=['outage','down','degraded','latency','api error','login','dashboard','deploy','incident','status page','us-east','timeout'];
        $themes=[]; $text=strtolower(implode(' ', $snippets));
        foreach($keywords as $k){ if(strpos($text,$k)!==false){$themes[]=$k;} }
        return array_slice($themes,0,6);
    }
    private function safe_url_for_source(string $source, string $provider): string { if($source==='public_chatter_bluesky') return 'https://bsky.app/search?q='.rawurlencode($provider.' outage'); if($source==='public_chatter_mastodon') return 'https://mastodon.social'; if($source==='public_chatter_gdelt') return 'https://api.gdeltproject.org/api/v2/doc/doc'; return ''; }
}
