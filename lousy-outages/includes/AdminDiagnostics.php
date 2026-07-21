<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class AdminDiagnostics {
    public static function bootstrap(): void { add_action('admin_menu', [self::class, 'menu']); }
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
        $legacy = [];
        foreach (['lo_event_log','lo_event_log_compacted_v1','lousy_outages_history','lousy_outages_log','lousy_outages_states','lo_event_log_v2','lo_history_migration_backup_v2','lo_history_migration_v2_marker'] as $opt) { $v = get_option($opt, null); $legacy[$opt] = is_array($v) ? count($v) : (null === $v ? 0 : 1); }
        echo '<div class="wrap"><h1>Lousy Outages Diagnostics</h1><table class="widefat striped"><tbody>';
        $rows = [
            'Plugin version'=>LOUSY_OUTAGES_VERSION, 'Plugin path'=>LOUSY_OUTAGES_PATH, 'Asset URL base'=>$asset_url,
            'Snapshot schema'=>(string)LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION, 'Snapshot fetched_at'=>(string)($state['fetched_at'] ?? ''),
            'Lane counts'=>wp_json_encode($state['meta'] ?? []), 'Summary internal test'=>empty($state) ? 'failed' : 'ok',
            'History internal test'=>class_exists('SuzyEaston\\LousyOutages\\Storage\\HistoryStore') ? 'ok' : 'failed',
            'Canonical cron'=>implode("\n", $cron), 'Legacy history option sizes'=>wp_json_encode($legacy),
            'Old theme assets exist'=>$theme_old ? 'yes' : 'no', 'Cached old plugin HTML'=>'manual page-cache inspection required',
        ];
        foreach ($rows as $k=>$v) { echo '<tr><th>'.esc_html((string)$k).'</th><td><pre>'.esc_html((string)$v).'</pre></td></tr>'; }
        echo '</tbody></table></div>';
    }
}
