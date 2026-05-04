<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\SignalSourceInterface;

class PublicChatterSource implements SignalSourceInterface {
    public function id(): string { return 'public_chatter'; }
    public function label(): string { return 'Public Chatter Radar'; }
    public function is_configured(): bool { return (bool) get_option('lousy_outages_public_chatter_enabled', false); }

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
                $count = count($mentions);
                $severity = $this->severity_for_count($count, $thresholds);
                if ($severity === '') continue;
                $signals[] = $this->build_signal($sourceId, (string)$providerId, (string)($provider['name'] ?? $providerId), (string)($provider['category'] ?? 'general'), $severity, $count, $window, $mentions);
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
        $out=[]; foreach(array_slice($queries,0,4) as $q){
            if ($sourceId==='public_chatter_bluesky') $out=array_merge($out,$this->search_bluesky($q,$window));
            elseif ($sourceId==='public_chatter_mastodon') $out=array_merge($out,$this->search_mastodon($q,$window));
            elseif ($sourceId==='public_chatter_gdelt') $out=array_merge($out,$this->search_gdelt($q));
        }
        return array_values(array_unique($out));
    }
    private function search_bluesky(string $q,int $window): array {
        $url = add_query_arg(['q'=>$q,'limit'=>25,'sort'=>'latest'],'https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts');
        $res = wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res) || (int)wp_remote_retrieve_response_code($res)>=400) return [];
        $body = json_decode((string)wp_remote_retrieve_body($res), true); $posts=(array)($body['posts']??[]); $min=time()-$window*60; $hashes=[];
        foreach($posts as $p){ $created = strtotime((string)($p['record']['createdAt'] ?? $p['indexedAt'] ?? '')); if($created && $created < $min) continue; $hashes[] = hash('sha256', (string)($p['uri'] ?? '').'|'.substr((string)($p['record']['text'] ?? ''),0,60)); }
        return $hashes;
    }
    private function search_mastodon(string $q,int $window): array {
        $instances=(array)apply_filters('lo_public_chatter_mastodon_instances',['https://mastodon.social']); $hashes=[]; $min=time()-$window*60;
        foreach(array_slice($instances,0,2) as $instance){ $url = trailingslashit((string)$instance).'api/v2/search?q='.rawurlencode($q).'&type=statuses&limit=20'; $res=wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res)||(int)wp_remote_retrieve_response_code($res)>=400) continue; $body=json_decode((string)wp_remote_retrieve_body($res),true); foreach((array)($body['statuses']??[]) as $s){ $created=strtotime((string)($s['created_at']??'')); if($created && $created<$min) continue; $hashes[] = hash('sha256',(string)($s['url']??'').'|'.substr(wp_strip_all_tags((string)($s['content']??'')),0,60)); }}
        return $hashes;
    }
    private function search_gdelt(string $q): array {
        $url=add_query_arg(['query'=>$q,'mode'=>'ArtList','format'=>'json','timespan'=>'60m','sort'=>'datedesc'],'https://api.gdeltproject.org/api/v2/doc/doc');
        $res=wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res)||(int)wp_remote_retrieve_response_code($res)>=400) return [];
        $body=json_decode((string)wp_remote_retrieve_body($res),true); $arts=(array)($body['articles']??[]); $hashes=[]; foreach(array_slice($arts,0,20) as $a){ $hashes[] = hash('sha256',(string)($a['url']??'').'|'.(string)($a['title']??'')); } return $hashes;
    }
    private function severity_for_count(int $count, array $t): string { if($count >= $t['hot']) return 'hot'; if($count >= $t['trending']) return 'trending'; if($count >= $t['watch']) return 'watch'; return ''; }
    private function build_signal(string $source,string $providerId,string $providerName,string $category,string $severity,int $count,int $window,array $mentions): array {
        $confMap=['watch'=>25,'trending'=>45,'hot'=>65]; if($source==='public_chatter_gdelt') $confMap=['watch'=>35,'trending'=>55,'hot'=>70];
        $msg = $source==='public_chatter_gdelt' ? "Recent public web/news mentions suggest a possible {$providerName} issue. Official status may still be unconfirmed." : "Public posts mentioning possible {$providerName} issues increased recently. This is unconfirmed.";
        return ['source'=>$source,'provider_id'=>$providerId,'provider_name'=>$providerName,'category'=>$source==='public_chatter_gdelt'?'open_web':$category,'region'=>'global','signal_type'=>'public_chatter','severity'=>$severity,'confidence'=>min(85,max(0,(int)$confMap[$severity])),'title'=>$source==='public_chatter_gdelt'?"Open web mentions increasing for {$providerName}":"Public chatter mentions increasing for {$providerName}",'message'=>$msg,'url'=>$this->safe_url_for_source($source,$providerName),'observed_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$window*60),'raw_hash'=>hash('sha256',$source.'|'.$providerId.'|'.$severity.'|'.count($mentions).'|'.implode('|',$mentions))];
    }
    private function safe_url_for_source(string $source, string $provider): string { if($source==='public_chatter_bluesky') return 'https://bsky.app/search?q='.rawurlencode($provider.' outage'); if($source==='public_chatter_mastodon') return 'https://mastodon.social'; if($source==='public_chatter_gdelt') return 'https://api.gdeltproject.org/api/v2/doc/doc'; return ''; }
}
