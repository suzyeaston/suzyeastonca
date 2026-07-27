<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Storage\IncidentStore;

/** Canonical, shared RSS status feed. Feed.php is intentionally not bootstrapped. */
class Feeds {
    private const FEED_NAME = 'lousy_outages_status';
    private const INCIDENT_WINDOW_DAYS = 7;
    private const INCIDENT_LIMIT = 15;
    private const FEED_CACHE_TTL = 600;
    /** The source signal window is 120 minutes; a gap that long starts a new episode. */
    private const SIGNAL_RESET_SECONDS = 120 * 60;
    private const IDENTITY_RETENTION_SECONDS = 30 * DAY_IN_SECONDS;
    private const OPTION_STATUS_FEED_LAST_BUILD = 'lousy_outages_status_feed_last_build';
    private const OPTION_STATUS_FEED_DIAGNOSTICS = 'lousy_outages_status_feed_diagnostics';
    private const OPTION_STATUS_FEED_CACHE = 'lousy_outages_status_feed_cache_v3';
    private const OPTION_IDENTITIES = 'lousy_outages_status_feed_identities_v1';

    public static function bootstrap(): void {
        add_action('init', [self::class, 'register']);
    }
    public static function register(): void { add_feed(self::FEED_NAME, [self::class, 'render_status_feed']); add_feed('lousy-outages-status', [self::class, 'render_status_feed']); }

    public static function render_status_feed(): void {
        if (function_exists('nocache_headers')) nocache_headers();
        $charset=(string)get_option('blog_charset','UTF-8'); header('Content-Type: application/rss+xml; charset='.$charset,true);
        $noCache=self::is_admin_nocache_request(); $payload=$noCache?null:self::valid_cached_payload(); $cacheUsed=is_array($payload);
        if (!$cacheUsed) $payload=self::rebuild_cache(!$noCache, $noCache?'admin_nocache':'cache_miss');
        update_option(self::OPTION_STATUS_FEED_DIAGNOSTICS,self::build_diagnostics($payload['build'],$payload['last_updated'],$cacheUsed,self::OPTION_STATUS_FEED_CACHE),false);
        $feedLink=function_exists('get_self_link')?get_self_link():home_url(add_query_arg(null,null));
        echo '<?xml version="1.0" encoding="'.esc_attr($charset?:'UTF-8').'"?>'."\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>
<title><?php echo esc_html('Suzy Easton – Lousy Outages Status Feed'); ?></title><link><?php echo esc_url(home_url('/lousy-outages/')); ?></link>
<description><?php echo esc_html('Provider status, public reports, and early warning signals. Unconfirmed unless marked official.'); ?></description>
<atom:link href="<?php echo esc_url($feedLink); ?>" rel="self" type="application/rss+xml" /><lastBuildDate><?php echo esc_html(self::format_rss_date((string)$payload['last_updated'])); ?></lastBuildDate>
<?php foreach (($payload['items']??[]) as $item): ?><item><title><?php echo esc_html($item['title']); ?></title><link><?php echo esc_url($item['link']); ?></link><guid isPermaLink="false"><?php echo esc_html($item['guid']); ?></guid><pubDate><?php echo esc_html($item['pubDate']); ?></pubDate><description><?php echo esc_html($item['description']); ?></description><?php foreach (($item['categories']??[]) as $category): ?><category><?php echo esc_html((string)$category); ?></category><?php endforeach; ?></item><?php endforeach; ?></channel></rss>
<?php exit; }

    /**
     * Build feed items. Source arrays, `now`, and `persist_identities` may be injected by tests.
     * Identity state is shared, bounded state -- never a per-reader "already served" list.
     */
    public static function get_status_feed_items(array $options=[]): array {
        $now=(int)($options['now']??time()); $persist=!array_key_exists('persist_identities',$options)||$options['persist_identities'];
        $build=['sources_included'=>[],'excluded'=>['quiet_signals'=>0,'duplicate_items'=>0,'expired_signals'=>0,'unknown_excluded'=>0,'operational_excluded'=>0,'old_incidents_excluded'=>0,'provider_states_with_official_incident'=>0,'cross_source_duplicates'=>0],'current_provider_items'=>0,'official_incident_items'=>0,'unconfirmed_signal_items'=>0,'stable_identities_reused'=>0,'new_logical_items'=>0];
        $incidentDays=(int)apply_filters('lo_status_feed_official_incident_days',self::INCIDENT_WINDOW_DAYS); $maxItems=(int)apply_filters('lo_status_feed_max_items',self::INCIDENT_LIMIT); $includeUnknown=(bool)apply_filters('lo_status_feed_include_unknown',false); $cutoff=$now-(max(1,$incidentDays)*DAY_IN_SECONDS);
        $providers=$options['providers']??Providers::list(); $states=$options['states']??(new Store())->get_all(); $lastPoll=(string)($options['last_poll']??get_option('lousy_outages_last_poll',gmdate('c',$now)));
        $events=$options['incidents']??(new IncidentStore())->getStoredIncidents(self::INCIDENT_LIMIT*3);
        $fused=$options['fused_signals']??SignalEngine::summarize_fused_signals(120); $external=$options['external_signals']??ExternalSignals::get_recent_signals(['windowMinutes'=>120,'limit'=>20]);
        $identity=is_array($options['identity_map']??null)?$options['identity_map']:(array)get_option(self::OPTION_IDENTITIES,[]); $identity+=['official'=>[],'providers'=>[],'signals'=>[]]; $items=[]; $officialProviders=[];

        foreach ((array)$events as $event) {
            if(!is_array($event))continue; $event=(new IncidentStore())->normalizeEvent($event); $published=self::feed_publication_timestamp($event); if($published&&$published<$cutoff){$build['excluded']['old_incidents_excluded']++;continue;}
            $pid=sanitize_key((string)($event['provider']??'')); $title=trim((string)($event['title']??$event['description']??'Incident')); $severity=strtolower((string)($event['severity']??'info'));
            $logical=self::official_incident_identity($event,$pid,$title); $entry=$identity['official'][$logical]??null;
            if(is_array($entry)){$guid=(string)$entry['guid'];$published=(int)($entry['started_at']??$published);$build['stable_identities_reused']++;}else{$legacy=self::legacy_cached_identity($pid,$title);$guid=(string)($legacy['guid']??sha1('official|'.$logical));$published=(int)($legacy['started_at']??$published);$build['new_logical_items']++;}
            $identity['official'][$logical]=['guid'=>$guid,'started_at'=>$published?:$now,'touched_at'=>$now];$officialProviders[$pid]=true;
            self::put_item($items,['title'=>'['.strtoupper($severity).'] '.($event['provider_label']??ucfirst($pid)).' – '.$title,'link'=>(string)($event['incident_url']??$event['url']??home_url('/lousy-outages/')),'guid'=>$guid,'pubDate'=>self::format_rss_date(gmdate('c',$published?:$now)),'description'=>self::truncate_text((string)($event['description']??$title),200),'timestamp'=>$published?:$now,'categories'=>['official_incident',$severity]],$build);
        }
        $build['sources_included'][]='official_incidents';

        foreach ((array)$states as $pid=>$state) {
            if(!is_array($state))continue; $pid=sanitize_key((string)$pid); $status=strtolower((string)($state['status']??'')); $healthy=$status==='operational';
            if($healthy){if(isset($identity['providers'][$pid]))$identity['providers'][$pid]['active']=false;$build['excluded']['operational_excluded']++;continue;}
            if($status==='unknown'&&!$includeUnknown){$build['excluded']['unknown_excluded']++;continue;} if(!in_array($status,['degraded','outage','major_outage','partial','maintenance','unknown'],true))continue;
            if(isset($officialProviders[$pid])){$build['excluded']['provider_states_with_official_incident']++;continue;}
            $candidate=self::parse_time((string)($state['episode_started_at']??$state['started_at']??''))?:self::parse_time($lastPoll)?:$now;
            $entry=$identity['providers'][$pid]??null; if(!is_array($entry)||empty($entry['active'])){$entry=['guid'=>sha1('provider-episode|'.$pid.'|'.$candidate),'started_at'=>$candidate,'active'=>true,'touched_at'=>$now];$build['new_logical_items']++;}else{$entry['touched_at']=$now;$build['stable_identities_reused']++;} $identity['providers'][$pid]=$entry;
            $provider=$providers[$pid]??[]; $name=(string)($provider['name']??ucfirst($pid)); self::put_item($items,['title'=>self::status_prefix($status).' '.$name.' currently '.$status,'link'=>(string)($provider['status_url']??home_url('/lousy-outages/')),'guid'=>(string)$entry['guid'],'pubDate'=>self::format_rss_date(gmdate('c',(int)$entry['started_at'])),'description'=>self::provider_status_description($name,$status),'timestamp'=>(int)$entry['started_at'],'categories'=>['current-provider-state',$status]],$build);
        }
        $build['sources_included'][]='current_provider_states';

        $signalRows=[]; $fusedProviders=[];
        foreach((array)$fused as $row){if(!is_array($row))continue;$c=strtolower((string)($row['classification']??'quiet'));if(!in_array($c,['watch','trending','hot'],true)){if($c==='quiet')$build['excluded']['quiet_signals']++;continue;}$pid=sanitize_key((string)($row['provider_id']??''));$fusedProviders[$pid]=true;$row['_kind']='fused';$signalRows[]=$row;}
        foreach((array)$external as $row){if(!is_array($row))continue;$pid=sanitize_key((string)($row['provider_id']??''));if(isset($fusedProviders[$pid])){$build['excluded']['cross_source_duplicates']++;continue;}$sev=strtolower((string)($row['severity']??'watch'));if($sev==='quiet'){$build['excluded']['quiet_signals']++;continue;}$row['_kind']='external';$signalRows[]=$row;}
        $seenSignals=[];
        foreach($signalRows as $row){$pid=sanitize_key((string)($row['provider_id']??''));$key=self::signal_logical_key($row,$pid);if(isset($seenSignals[$key])){$build['excluded']['cross_source_duplicates']++;continue;}$seenSignals[$key]=true;$observed=self::parse_time((string)($row['last_seen_at']??$row['observed_at']??''))?:$now;$first=self::parse_time((string)($row['first_seen_at']??$row['started_at']??''))?:$observed;$entry=$identity['signals'][$key]??null;
            if(!is_array($entry)||$first-(int)($entry['last_seen']??0)>=self::SIGNAL_RESET_SECONDS){$entry=['guid'=>sha1('signal-episode|'.$key.'|'.$first),'started_at'=>$first,'last_seen'=>$observed,'touched_at'=>$now];$build['new_logical_items']++;}else{$entry['last_seen']=max((int)$entry['last_seen'],$observed);$entry['touched_at']=$now;$build['stable_identities_reused']++;}$identity['signals'][$key]=$entry;$p=(string)($row['provider_name']??$pid?:'Provider');$kind=(string)$row['_kind'];self::put_item($items,['title'=>'[UNCONFIRMED] '.$p.' signal','link'=>home_url('/lousy-outages/'),'guid'=>(string)$entry['guid'],'pubDate'=>self::format_rss_date(gmdate('c',(int)$entry['started_at'])),'description'=>'This is an unconfirmed signal. Official incident not confirmed.','timestamp'=>(int)$entry['started_at'],'categories'=>['unconfirmed','signal',$kind.'-signal']],$build);}
        $build['sources_included'][]='fused_signals';$build['sources_included'][]='external_signals';

        foreach(['official','providers','signals'] as $type){foreach((array)$identity[$type] as $key=>$entry){if((int)($entry['touched_at']??0)<$now-self::IDENTITY_RETENTION_SECONDS)unset($identity[$type][$key]);}}
        if($persist)update_option(self::OPTION_IDENTITIES,$identity,false); $build['identity_map_size']=count($identity['official'])+count($identity['providers'])+count($identity['signals']);
        $values=array_values($items);usort($values,static fn($a,$b)=>(int)$b['timestamp']<=>(int)$a['timestamp']);$values=array_slice($values,0,max(1,$maxItems));
        foreach($values as $item){if(in_array('official_incident',$item['categories'],true))$build['official_incident_items']++;if(in_array('current-provider-state',$item['categories'],true))$build['current_provider_items']++;if(in_array('unconfirmed',$item['categories'],true))$build['unconfirmed_signal_items']++;}
        $build['max_items_applied']=max(1,$maxItems);$build['item_count']=count($values);$newest=$values?(int)$values[0]['timestamp']:$now;$lastUpdated=gmdate('c',$newest);$build['newest_item_date']=$lastUpdated;
        $public=array_map(static function($item){unset($item['timestamp']);return $item;},$values);$build['feed_content_fingerprint']=self::feed_content_fingerprint($public);update_option(self::OPTION_STATUS_FEED_LAST_BUILD,$lastUpdated,false);return[$public,$lastUpdated,$build];
    }

    public static function refresh_status_feed_cache(): void { $old=self::valid_cached_payload(false); $new=self::rebuild_cache(false,'scheduled_refresh'); $changed=!is_array($old)||($old['build']['feed_content_fingerprint']??'')!==($new['build']['feed_content_fingerprint']??''); $new['build']['cache_invalidation_reason']=$changed?'logical_feed_changed':'logical_feed_unchanged'; update_option(self::OPTION_STATUS_FEED_CACHE,$new,false); update_option(self::OPTION_STATUS_FEED_DIAGNOSTICS,self::build_diagnostics($new['build'],$new['last_updated'],false,self::OPTION_STATUS_FEED_CACHE),false); }
    public static function clear_status_feed_cache(): void {delete_option(self::OPTION_STATUS_FEED_CACHE);update_option(self::OPTION_STATUS_FEED_DIAGNOSTICS,array_merge(self::get_status_feed_diagnostics(),['last_cache_clear_time'=>gmdate('c'),'cache_invalidation_reason'=>'manual']),false);}
    public static function get_status_feed_diagnostics(): array {$raw=get_option(self::OPTION_STATUS_FEED_DIAGNOSTICS,[]);return is_array($raw)?$raw:[];}
    public static function feed_content_fingerprint(array $items): string {$stable=[];foreach($items as $item)$stable[]=[(string)($item['guid']??''),(string)($item['pubDate']??''),(string)($item['title']??''),(string)($item['description']??''),(string)($item['link']??'')];return hash('sha256',(string)wp_json_encode($stable));}
    private static function rebuild_cache(bool $save,string $reason): array {[$items,$last,$build]=self::get_status_feed_items();$build['cache_invalidation_reason']=$reason;$payload=['items'=>$items,'last_updated'=>$last,'build'=>$build,'expires_at'=>time()+self::FEED_CACHE_TTL];if($save)update_option(self::OPTION_STATUS_FEED_CACHE,$payload,false);return$payload;}
    private static function valid_cached_payload(bool $checkExpiry=true): ?array {$cached=get_option(self::OPTION_STATUS_FEED_CACHE,[]);if(!is_array($cached)||!isset($cached['items'],$cached['build']))return null;if($checkExpiry&&(int)($cached['expires_at']??0)<=time())return null;return$cached;}
    private static function put_item(array &$items,array $item,array &$build): void {$guid=(string)$item['guid'];if(isset($items[$guid])){$build['excluded']['duplicate_items']++;return;}$items[$guid]=$item;}
    private static function official_incident_identity(array $event,string $pid,string $title): string {foreach(['guid','incident_id','id'] as $key){if(trim((string)($event[$key]??''))!=='')return $pid.'|id|'.trim((string)$event[$key]);}foreach(['incident_url','shortlink','url'] as $key){$url=trim((string)($event[$key]??''));if($url!==''&&preg_match('#^https?://#i',$url))return $pid.'|url|'.strtolower(rtrim($url,'/'));}$start=self::feed_publication_timestamp($event);return $pid.'|fallback|'.self::normalize_title($title).'|'.$start;}
    private static function feed_publication_timestamp(array $event): int {foreach(['first_seen','started_at','startedAt','published','created_at','detected_at'] as $key){$v=$event[$key]??null;$ts=is_numeric($v)?(int)$v:self::parse_time((string)$v);if($ts>0)return$ts;}return 0;}
    private static function signal_logical_key(array $row,string $pid): string {foreach(['episode_id','signal_id','id'] as $key){if(trim((string)($row[$key]??''))!=='')return$pid.'|'.trim((string)$row[$key]);}return$pid.'|'.sanitize_key((string)($row['source_type']??$row['_kind']??'signal'));}
    /** Pin a matching v2 item during rollout so an active official incident is not replayed. */
    private static function legacy_cached_identity(string $pid,string $title): array {$old=get_option('lousy_outages_status_feed_cache_v2',[]);foreach((array)($old['items']??[]) as $item){$itemTitle=strtolower((string)($item['title']??''));if(strpos($itemTitle,strtolower($title))===false||($pid!==''&&strpos($itemTitle,strtolower($pid))===false&&strpos($itemTitle,strtolower(str_replace('_',' ',$pid)))===false))continue;$started=self::parse_time((string)($item['pubDate']??''));if(!empty($item['guid'])&&$started)return['guid'=>(string)$item['guid'],'started_at'=>$started];}return[];}
    private static function normalize_title(string $title): string {$title=strtolower(trim(wp_strip_all_tags($title)));return preg_replace('/\s+/',' ',$title)??$title;}
    private static function status_prefix(string $s): string {return $s==='major_outage'?'[MAJOR OUTAGE]':($s==='outage'?'[OUTAGE]':($s==='degraded'?'[DEGRADED]':($s==='maintenance'?'[MAINTENANCE]':'[PARTIAL]')));}
    private static function provider_status_description(string $name,string $status): string {if($status==='degraded')return$name.' is currently reporting degraded service.';if($status==='maintenance')return$name.' is currently in maintenance.';if($status==='outage'||$status==='major_outage')return$name.' is currently reporting an outage.';return$name.' is currently reporting partial disruption.';}
    private static function is_admin_nocache_request(): bool {return!empty($_GET['lo_nocache'])&&is_user_logged_in()&&current_user_can('manage_options');}
    private static function build_diagnostics(array $build,string $last,bool $hit,string $key): array {return array_merge($build,['renderer_file'=>__FILE__,'renderer_source'=>'standalone_plugin','plugin_mode'=>'standalone_plugin','render_timestamp'=>gmdate('c'),'active_plugin_loaded'=>defined('LOUSY_OUTAGES_LOADED'),'theme_bundle_loaded'=>false,'feed_callback_name'=>__METHOD__,'feed_cache_key_used'=>$key,'cache_status'=>$hit?'hit':'miss','last_build'=>$last]);}
    private static function parse_time(string $value): int {$value=trim($value);if($value==='')return 0;$ts=strtotime($value);return$ts===false?0:(int)$ts;}
    private static function format_rss_date(string $iso): string {$ts=self::parse_time($iso);return gmdate('D, d M Y H:i:s +0000',$ts?:time());}
    private static function truncate_text(string $text,int $max): string {$text=trim(wp_strip_all_tags($text));return strlen($text)<=$max?$text:rtrim(substr($text,0,$max-1)).'…';}
}
