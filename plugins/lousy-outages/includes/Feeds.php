<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Storage\IncidentStore;

class Feeds {
    private const FEED_NAME = 'lousy_outages_status';
    private const INCIDENT_WINDOW_DAYS = 7;
    private const INCIDENT_LIMIT = 15;
    private const FEED_CACHE_TTL = 600;
    private const OPTION_STATUS_FEED_LAST_BUILD = 'lousy_outages_status_feed_last_build';
    private const OPTION_STATUS_FEED_DIAGNOSTICS = 'lousy_outages_status_feed_diagnostics';
    private const OPTION_STATUS_FEED_CACHE = 'lousy_outages_status_feed_cache_v2';

    public static function bootstrap(): void { add_action('init', [self::class, 'register']); }
    public static function register(): void { add_feed(self::FEED_NAME, [self::class, 'render_status_feed']); add_feed('lousy-outages-status', [self::class, 'render_status_feed']); }

    public static function render_status_feed(): void {
        if (function_exists('nocache_headers')) { nocache_headers(); }
        $charset = (string) get_option('blog_charset', 'UTF-8');
        header('Content-Type: application/rss+xml; charset=' . $charset, true);

        $isAdminNoCache = self::is_admin_nocache_request();
        $cacheKey = self::OPTION_STATUS_FEED_CACHE;
        $cacheUsed = false;
        $payload = null;
        if (!$isAdminNoCache) {
            $cached = get_option($cacheKey, []);
            if (is_array($cached) && !empty($cached['expires_at']) && (int)$cached['expires_at'] > time()) { $payload = $cached; $cacheUsed = true; }
        }
        if (!is_array($payload)) {
            [$items, $lastUpdated, $build] = self::get_status_feed_items();
            $payload = ['items'=>$items,'last_updated'=>$lastUpdated,'build'=>$build,'expires_at'=>time()+self::FEED_CACHE_TTL];
            if (!$isAdminNoCache) { update_option($cacheKey, $payload, false); }
        }

        $diagnostics = self::build_diagnostics($payload['build'], $payload['last_updated'], $cacheUsed, $cacheKey);
        update_option(self::OPTION_STATUS_FEED_DIAGNOSTICS, $diagnostics, false);

        $feed_link = function_exists('get_self_link') ? get_self_link() : home_url(add_query_arg(null, null));
        echo '<?xml version="1.0" encoding="' . esc_attr($charset ?: 'UTF-8') . '"?>' . "\n";
        ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>
<title><?php echo esc_html('Suzy Easton – Lousy Outages Status Feed'); ?></title>
<link><?php echo esc_url(home_url('/lousy-outages/')); ?></link>
<description><?php echo esc_html('Provider status, public reports, and early warning signals. Unconfirmed unless marked official.'); ?></description>
<atom:link href="<?php echo esc_url($feed_link); ?>" rel="self" type="application/rss+xml" />
<lastBuildDate><?php echo esc_html(self::format_rss_date((string)$payload['last_updated'])); ?></lastBuildDate>
<?php foreach (($payload['items'] ?? []) as $item) : ?><item><title><?php echo esc_html($item['title']); ?></title><link><?php echo esc_url($item['link']); ?></link><guid isPermaLink="false"><?php echo esc_html($item['guid']); ?></guid><pubDate><?php echo esc_html($item['pubDate']); ?></pubDate><description><?php echo esc_html($item['description']); ?></description><?php foreach (($item['categories'] ?? []) as $category) : ?><category><?php echo esc_html((string)$category); ?></category><?php endforeach; ?></item><?php endforeach; ?>
</channel></rss>
<?php exit; }

    public static function get_status_feed_items(array $options = []): array {
        $build = ['sources_included'=>[],'excluded'=>['quiet_signals'=>0,'duplicate_items'=>0,'expired_signals'=>0,'unknown_excluded'=>0,'operational_excluded'=>0,'old_incidents_excluded'=>0],'current_provider_items'=>0,'official_incident_items'=>0,'unconfirmed_signal_items'=>0];
        $itemsByGuid=[]; $incidentDays=(int)apply_filters('lo_status_feed_official_incident_days', self::INCIDENT_WINDOW_DAYS); $maxItems=(int)apply_filters('lo_status_feed_max_items', self::INCIDENT_LIMIT); $includeUnknown=(bool)apply_filters('lo_status_feed_include_unknown', false); $cutoff = time() - (max(1,$incidentDays) * DAY_IN_SECONDS);
        $providers = Providers::list(); $states=(new Store())->get_all(); $lastPoll=(string)get_option('lousy_outages_last_poll', gmdate('c'));
        foreach ($states as $providerId=>$state) {
            if (!is_array($state)) { continue; }
            $status = strtolower((string)($state['status'] ?? '')); if ('operational'===$status){$build['excluded']['operational_excluded']++;continue;} if ('unknown'===$status && !$includeUnknown){$build['excluded']['unknown_excluded']++;continue;} if (!in_array($status,['degraded','outage','major_outage','partial','maintenance','unknown'],true)) { continue; }
            $provider = $providers[$providerId] ?? []; $name=(string)($provider['name'] ?? ucfirst((string)$providerId)); $updated=(string)($state['updated_at'] ?? $state['checked_at'] ?? $lastPoll);
            $ts = self::parse_time($updated) ?: self::parse_time($lastPoll); $prefix = $status==='outage'?'[OUTAGE]':($status==='major_outage'?'[MAJOR OUTAGE]':($status==='degraded'?'[DEGRADED]':($status==='maintenance'?'[MAINTENANCE]':'[PARTIAL]')));
            $guid=sha1('provider_state|'.$providerId.'|'.$status.'|'.$updated); $itemsByGuid[$guid]=['title'=>$prefix.' '.$name.' currently '.$status,'link'=>(string)($provider['status_url'] ?? home_url('/lousy-outages/')),'guid'=>$guid,'pubDate'=>self::format_rss_date(gmdate('c',$ts?:time())),'description'=>self::provider_status_description($name,$status,$updated),'timestamp'=>$ts?:time(),'categories'=>['current-provider-state',$status]];
        }
        if (!empty($itemsByGuid)) { $build['sources_included'][]='current_provider_states'; $build['current_provider_items']=count($itemsByGuid); }

        $store = new IncidentStore();
        foreach ((array)$store->getStoredIncidents(self::INCIDENT_LIMIT * 3) as $event) {
            if (!is_array($event)) continue; $event=$store->normalizeEvent($event); $ts=(int)($event['last_seen'] ?? $event['first_seen'] ?? 0); if ($ts && $ts<$cutoff){$build['excluded']['expired_signals']++;$build['excluded']['old_incidents_excluded']++;continue;}
            $severity=strtolower((string)($event['severity'] ?? 'info')); $providerId=sanitize_key((string)($event['provider'] ?? '')); $title=trim((string)($event['title'] ?? $event['description'] ?? 'Incident'));
            $guid=sha1('incident|'.$providerId.'|'.$title.'|'.$ts); if(isset($itemsByGuid[$guid])){$build['excluded']['duplicate_items']++;continue;}
            $itemsByGuid[$guid]=['title'=>'['.strtoupper($severity).'] '.($event['provider_label'] ?? ucfirst($providerId)).' – '.$title,'link'=>(string)($event['incident_url'] ?? $event['url'] ?? home_url('/lousy-outages/')),'guid'=>$guid,'pubDate'=>self::format_rss_date(gmdate('c',$ts?:time())),'description'=>self::truncate_text((string)($event['description'] ?? $title),200),'timestamp'=>$ts?:time(),'categories'=>['official_incident',$severity]];
        }
        $build['sources_included'][]='official_incidents';
        foreach ((array)SignalEngine::summarize_fused_signals(120) as $row) {
            $c=strtolower((string)($row['classification'] ?? 'quiet')); if (!in_array($c,['watch','trending','hot'],true)){ if($c==='quiet')$build['excluded']['quiet_signals']++; continue; }
            $pid=sanitize_key((string)($row['provider_id'] ?? '')); $p=(string)($row['provider_name'] ?? $pid ?: 'Provider'); $ts=self::parse_time((string)($row['last_seen_at'] ?? '')) ?: time(); $prefix=$c==='hot'?'[TRENDING]':'[WATCH]';
            $guid=sha1('fused|'.$pid.'|'.$c.'|'.$ts); $itemsByGuid[$guid]=['title'=>$prefix.' '.$p.' signal','link'=>home_url('/lousy-outages/'),'guid'=>$guid,'pubDate'=>self::format_rss_date(gmdate('c',$ts)),'description'=>'This is an unconfirmed signal. Official incident not confirmed.','timestamp'=>$ts,'categories'=>['unconfirmed','signal','community-signal']];
        }
        $build['sources_included'][]='fused_signals';
        foreach ((array)ExternalSignals::get_recent_signals(['windowMinutes'=>120,'limit'=>20]) as $row) {
            $sev=strtolower((string)($row['severity'] ?? 'watch')); if ($sev==='quiet'){ $build['excluded']['quiet_signals']++; continue; }
            $pid=sanitize_key((string)($row['provider_id'] ?? '')); $p=(string)($row['provider_name'] ?? $pid ?: 'Provider'); $ts=self::parse_time((string)($row['observed_at'] ?? '')) ?: time();
            $guid=sha1('external|'.$pid.'|'.$sev.'|'.$ts); if(isset($itemsByGuid[$guid])) continue;
            $itemsByGuid[$guid]=['title'=>'[UNCONFIRMED] '.$p.' external signal','link'=>home_url('/lousy-outages/'),'guid'=>$guid,'pubDate'=>self::format_rss_date(gmdate('c',$ts)),'description'=>'This is an unconfirmed signal. Official incident not confirmed.','timestamp'=>$ts,'categories'=>['unconfirmed','signal','external-signal']];
        }
        $build['sources_included'][]='external_signals';
        $items=array_values($itemsByGuid); usort($items,fn($a,$b)=>(int)$b['timestamp']<=>(int)$a['timestamp']);
        $official=0; $unconfirmed=0; foreach($items as $it){$cats=$it['categories']??[]; if(in_array('official_incident',$cats,true))$official++; if(in_array('unconfirmed',$cats,true))$unconfirmed++;}
        $build['official_incident_items']=$official; $build['unconfirmed_signal_items']=$unconfirmed;
        $items=array_slice($items,0,max(1,$maxItems)); $build['max_items_applied']=max(1,$maxItems);
        $newestTs = 0; foreach($items as $item){$newestTs=max($newestTs,(int)$item['timestamp']);}
        $lastUpdated = $newestTs ? gmdate('c',$newestTs) : gmdate('c'); update_option(self::OPTION_STATUS_FEED_LAST_BUILD, $lastUpdated, false);
        $build['item_count']=count($items); $build['newest_item_date']=$lastUpdated;
        return [array_map(static function($i){unset($i['timestamp']);return $i;},$items), $lastUpdated, $build];
    }

    public static function clear_status_feed_cache(): void { delete_option(self::OPTION_STATUS_FEED_CACHE); update_option(self::OPTION_STATUS_FEED_DIAGNOSTICS, array_merge(self::get_status_feed_diagnostics(), ['last_cache_clear_time'=>gmdate('c')]), false); }
    public static function get_status_feed_diagnostics(): array { $raw=get_option(self::OPTION_STATUS_FEED_DIAGNOSTICS,[]); return is_array($raw)?$raw:[]; }
    private static function is_admin_nocache_request(): bool { return !empty($_GET['lo_nocache']) && is_user_logged_in() && current_user_can('manage_options'); }
    private static function build_diagnostics(array $build, string $lastUpdated, bool $cacheUsed, string $cacheKey): array { return array_merge($build,['renderer_file'=>__FILE__,'renderer_source'=>(false!==strpos(__FILE__,'plugins/lousy-outages/')?'standalone_plugin':'theme_bundle'),'plugin_mode'=>(false!==strpos(__FILE__,'plugins/lousy-outages/')?'standalone_plugin':'theme_bundle'),'render_timestamp'=>gmdate('c'),'active_plugin_loaded'=>defined('LOUSY_OUTAGES_LOADED'),'theme_bundle_loaded'=>file_exists(ABSPATH.'wp-content/themes/'.get_template().'/lousy-outages/lousy-outages.php'),'feed_callback_name'=>__METHOD__,'feed_cache_key_used'=>$cacheKey,'cache_status'=>$cacheUsed?'hit':'miss','last_build'=>$lastUpdated]); }

    private static function provider_status_description(string $name,string $status,string $updated): string {
        if ($status==='degraded') return $name.' is currently reporting degraded service. Last checked '.$updated.'.';
        if ($status==='maintenance') return $name.' is currently in maintenance. Last checked '.$updated.'.';
        if ($status==='outage' || $status==='major_outage') return $name.' is currently reporting an outage. Last checked '.$updated.'.';
        return $name.' is currently reporting partial disruption. Last checked '.$updated.'.';
    }

    private static function parse_time(string $value): int { $value=trim($value); if($value===''){return 0;} $ts=strtotime($value); return $ts===false?0:(int)$ts; }
    private static function format_rss_date(string $iso): string { $ts=self::parse_time($iso); return gmdate('D, d M Y H:i:s +0000', $ts ?: time()); }
    private static function truncate_text(string $text, int $max): string { $t=trim(wp_strip_all_tags($text)); if(strlen($t)<=$max)return $t; return rtrim(substr($t,0,$max-1)).'…'; }
}
