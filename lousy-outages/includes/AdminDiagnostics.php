<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class AdminDiagnostics {
    public static function bootstrap(): void { add_action('admin_menu', [self::class, 'menu']); add_action('admin_post_lousy_outages_abandon_expired_cycle',[self::class,'abandonExpiredCycle']); }
    public static function abandonExpiredCycle(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !current_user_can('manage_options')) wp_die('Forbidden',403);
        check_admin_referer('lousy_outages_abandon_expired_cycle');
        $lease=(array)get_option(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::LEASE_OPTION,[]);
        $cycle=(array)get_option(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::CYCLE_OPTION,[]);
        $heartbeat=strtotime((string)($cycle['heartbeat_at']??''))?:0;
        if ((!$lease || (int)($lease['expires_at']??0)<=time()) && $heartbeat && time()-$heartbeat>2*\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::cadence()) {
            delete_option(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::LEASE_OPTION);$cycle['final_status']='abandoned';$cycle['abandoned_at']=gmdate('c');update_option(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::CYCLE_OPTION,$cycle,false);
            if(!wp_next_scheduled(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::RECOVERY_HOOK))wp_schedule_single_event(time()+5,\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::RECOVERY_HOOK);
        }
        wp_safe_redirect(admin_url('tools.php?page=lousy-outages-diagnostics'));exit;
    }
    public static function menu(): void {
        add_management_page('Lousy Outages Diagnostics', 'Lousy Outages Diagnostics', 'manage_options', 'lousy-outages-diagnostics', [self::class, 'render']);
    }
    public static function render(): void {
        if (!current_user_can('manage_options')) { return; }
        $state = function_exists('lousy_outages_get_current_state') ? \lousy_outages_get_current_state() : [];
        [$asset_path, $asset_url] = function_exists(__NAMESPACE__.'\\locate_assets_base') ? locate_assets_base() : [LOUSY_OUTAGES_PATH.'assets/', LOUSY_OUTAGES_URL.'assets/'];
        $theme_old = false;
        foreach (['get_stylesheet_directory','get_template_directory'] as $fn) { if (function_exists($fn) && is_dir(rtrim($fn(), '/\\').'/lousy-outages/assets')) { $theme_old = true; } }
        $hooks = function_exists('_get_cron_array') ? _get_cron_array() : [];
        $cron = [];
        foreach ((array)$hooks as $ts => $events) { foreach ((array)$events as $hook => $items) { if (false !== strpos((string)$hook, 'lousy_outages') || false !== strpos((string)$hook, 'lo_')) { $cron[] = $hook.' @ '.gmdate('c', (int)$ts); } } }
        $attempted = (array) get_option('lousy_outages_last_refresh_attempt', []);
        $completed = (array) get_option('lousy_outages_last_refresh_complete', []);
        $provider_health = (array) get_option('lousy_outages_provider_health', []);
        $next = function_exists('wp_next_scheduled') ? wp_next_scheduled('lousy_outages_refresh_official_providers') : false;
        $alert_health = IncidentAlerts::alert_health();
        $cycle=(array)get_option(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::CYCLE_OPTION,[]);$lease=(array)get_option(\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::LEASE_OPTION,[]);if($lease&&(int)($lease['expires_at']??0)<=time())$lease=[];
        $counts=['recurring'=>0,'continuation'=>0,'recovery'=>0];foreach((array)$hooks as $events)foreach((array)$events as $hook=>$items){if($hook===\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::RECURRING_HOOK)$counts['recurring']+=count($items);if($hook===\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::CONTINUATION_HOOK)$counts['continuation']+=count($items);if($hook===\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::RECOVERY_HOOK)$counts['recovery']+=count($items);}
        $healthy = 0; $failed = 0; $delayed = 0;
        foreach (ProviderRegistry::enabled() as $provider) {
            $id = (string)($provider['id'] ?? '');
            $health = isset($provider_health[$id]) && is_array($provider_health[$id]) ? $provider_health[$id] : [];
            if (!empty($health['last_success'])) { $healthy++; }
            if (!empty($health['last_error'])) { $failed++; }
            $last = !empty($health['last_success']) ? strtotime((string)$health['last_success']) : 0;
            if (!$last || (time() - $last) > (int)($provider['freshness_threshold'] ?? ProviderRegistry::DEFAULT_FRESHNESS_THRESHOLD)) { $delayed++; }
        }
        $legacy = [];
        foreach (['lo_event_log','lo_event_log_compacted_v1','lousy_outages_history','lousy_outages_log','lousy_outages_states','lo_event_log_v2','lo_history_migration_backup_v2','lo_history_migration_v2_marker'] as $opt) { $v = get_option($opt, null); $legacy[$opt] = is_array($v) ? count($v) : (null === $v ? 0 : 1); }
        echo '<div class="wrap"><h1>Lousy Outages Diagnostics</h1><table class="widefat striped"><tbody>';
        $rows = [
            'Plugin version'=>LOUSY_OUTAGES_VERSION, 'Plugin path'=>LOUSY_OUTAGES_PATH, 'Asset URL base'=>$asset_url,
            'Snapshot schema'=>(string)LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION, 'Snapshot fetched_at'=>(string)($state['fetched_at'] ?? ''),
            'Canonical cron hook'=>'lousy_outages_refresh_official_providers',
            'Canonical recurring interval'=>(string)\SuzyEaston\LousyOutages\Cron\CanonicalPipeline::cadence(),
            'Recurring / continuation / recovery event counts'=>wp_json_encode($counts),
            'Next scheduled run'=>$next ? gmdate('c', (int)$next) : 'not scheduled',
            'Active cycle ID'=>(string)($cycle['cycle_id']??''),'Current phase'=>(string)($cycle['phase']??''),
            'Cycle age seconds'=>!empty($cycle['started_at'])?time()-(strtotime((string)$cycle['started_at'])?:time()):'',
            'Lease owner suffix'=>$lease?substr((string)($lease['owner_token']??''),-8):'none',
            'Lease age seconds'=>$lease?time()-(int)($lease['acquired_at']??time()):'',
            'Heartbeat age seconds'=>!empty($cycle['heartbeat_at'])?time()-(strtotime((string)$cycle['heartbeat_at'])?:time()):'',
            'Provider progress'=>(int)($cycle['completed_provider_count']??0).' / '.count((array)($cycle['provider_ids']??[])),
            'Last completed provider'=>(string)($cycle['last_completed_provider']??''),
            'Last successful snapshot commit'=>wp_json_encode(get_option('lousy_outages_last_snapshot_commit',[])),
            'Alert publication status'=>(string)($cycle['publication']['alerts']??''),'Alert recipients pending'=>wp_json_encode($cycle['publication_results']['alerts']['result']['pending']??[]),
            'RSS publication status'=>(string)($cycle['publication']['rss']??''),'RSS source cycle'=>(string)(Feeds::get_status_feed_diagnostics()['source_cycle_id']??''),
            'Last fatal or interrupted invocation'=>wp_json_encode(get_option('lousy_outages_last_interrupted_invocation',[])),
            'Last completely successful end-to-end cycle'=>wp_json_encode(get_option('lousy_outages_last_end_to_end_cycle',[])),
            'Alert / publication health'=>wp_json_encode($alert_health, JSON_PRETTY_PRINT),
            'Last attempted refresh'=>wp_json_encode($attempted),
            'Last successful complete refresh'=>wp_json_encode($completed),
            'Overall refresh health'=>($failed > 0 || $delayed > 0) ? 'attention_needed' : 'healthy',
            'Providers attempted'=>count(ProviderRegistry::enabled()),
            'Providers with successful checks'=>$healthy,
            'Providers failed'=>$failed,
            'Verification delayed providers'=>$delayed,
            'Provider-specific health'=>wp_json_encode($provider_health),
            'Lane counts'=>wp_json_encode($state['meta'] ?? []), 'Summary internal test'=>empty($state) ? 'failed' : 'ok',
            'History internal test'=>class_exists('SuzyEaston\\LousyOutages\\Storage\\HistoryStore') ? 'ok' : 'failed',
            'Canonical cron'=>implode("\n", $cron), 'Legacy history option sizes'=>wp_json_encode($legacy),
            'Old theme assets exist'=>$theme_old ? 'yes' : 'no', 'Cached old plugin HTML'=>'manual page-cache inspection required',
        ];
        foreach ($rows as $k=>$v) { echo '<tr><th>'.esc_html((string)$k).'</th><td><pre>'.esc_html((string)$v).'</pre></td></tr>'; }
        echo '</tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="lousy_outages_abandon_expired_cycle">';wp_nonce_field('lousy_outages_abandon_expired_cycle');submit_button('Abandon expired cycle and queue recovery','secondary');echo '</form></div>';
    }
}
