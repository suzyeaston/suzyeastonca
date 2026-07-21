<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class AdminCleanup {
    private const CANONICAL_PLUGIN = 'lousy-outages/lousy-outages.php';
    private const REMOVABLE_DIRS = [
        'lousy-outages-0.3.0',
        'lousy-outages-recovery-updater-v2',
        'lousy-outages-activation-diagnostics',
    ];
    private const HELPERS = [
        'lousy-outages-recovery-updater/lousy-outages-updater.php',
        'lousy-outages-recovery-updater-v2/lousy-outages-updater.php',
        'lousy-outages-activation-diagnostics/lousy-outages-activation-diagnostics.php',
    ];

    public static function bootstrap(): void {
        add_action('admin_menu', [self::class, 'register_page']);
    }

    public static function register_page(): void {
        add_management_page('Lousy Outages Cleanup', 'Lousy Outages Cleanup', 'manage_options', 'lousy-outages-cleanup', [self::class, 'render_page']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) { wp_die('Insufficient permissions.'); }
        $removed = [];
        $report = self::inspect();
        $message = '';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['lo_cleanup_action'])) {
            check_admin_referer('lo_cleanup_action');
            if (empty($_POST['lo_cleanup_confirm'])) {
                $message = 'Cleanup was not run because the confirmation checkbox was not selected.';
            } else {
                $result = self::cleanup();
                $removed = $result['removed'];
                $message = $result['message'];
                $report = self::inspect();
            }
        }
        echo '<div class="wrap"><h1>Lousy Outages Cleanup</h1>';
        echo '<p>This administrator-only tool defaults to dry-run and only removes approved malformed or temporary Lousy Outages helper directories.</p>';
        if ($message) { echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>'; }
        if ($removed) { echo '<h2>Removed directories</h2><ul>'; foreach ($removed as $dir) echo '<li>'.esc_html($dir).'</li>'; echo '</ul>'; }
        echo '<h2>Dry-run report</h2><table class="widefat striped"><tbody>';
        foreach ($report as $key => $value) {
            echo '<tr><th>'.esc_html($key).'</th><td><pre>'.esc_html(is_scalar($value) ? (string)$value : wp_json_encode($value, JSON_PRETTY_PRINT)).'</pre></td></tr>';
        }
        echo '</tbody></table><form method="post">';
        wp_nonce_field('lo_cleanup_action');
        echo '<p><label><input type="checkbox" name="lo_cleanup_confirm" value="1"> I understand this removes only approved temporary Lousy Outages directories and does not touch history or unrelated plugins.</label></p>';
        submit_button('Remove approved temporary directories', 'delete', 'lo_cleanup_action');
        echo '</form></div>';
    }

    public static function inspect(): array {
        $pluginsDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : dirname((string)(defined('LOUSY_OUTAGES_PATH') ? LOUSY_OUTAGES_PATH : __DIR__), 2);
        $active = (array) get_option('active_plugins', []);
        $routes = function_exists('rest_get_server') ? rest_get_server()->get_routes() : [];
        $history = class_exists('SuzyEaston\\LousyOutages\\Storage\\HistoryStore') ? (new \SuzyEaston\LousyOutages\Storage\HistoryStore())->all(30) : [];
        $dirs = [];
        foreach (glob(rtrim($pluginsDir, '/').'/lousy-outages*', GLOB_ONLYDIR) ?: [] as $dir) { $dirs[] = basename($dir); }
        $helpers = [];
        foreach (self::HELPERS as $helper) { $helpers[$helper] = ['exists'=>is_file(rtrim($pluginsDir, '/').'/'.$helper), 'active'=>in_array($helper, $active, true)]; }
        return [
            'canonical_plugin_path' => self::CANONICAL_PLUGIN,
            'canonical_filesystem_path' => rtrim($pluginsDir, '/').'/'.self::CANONICAL_PLUGIN,
            'canonical_version' => defined('LOUSY_OUTAGES_VERSION') ? LOUSY_OUTAGES_VERSION : 'unknown',
            'canonical_active' => in_array(self::CANONICAL_PLUGIN, $active, true) || (defined('LOUSY_OUTAGES_FILE') && plugin_basename(LOUSY_OUTAGES_FILE) === self::CANONICAL_PLUGIN),
            'duplicate_lousy_outages_directories' => array_values(array_filter($dirs, fn($d) => $d !== 'lousy-outages')),
            'temporary_helper_plugins' => $helpers,
            'summary_endpoint_registered' => isset($routes['/lousy-outages/v1/summary']),
            'history_endpoint_registered' => isset($routes['/lousy-outages/v1/history']),
            'historical_events_visible' => is_array($history),
            'canonical_history_options_exist' => ['lo_event_log_v2'=>false !== get_option('lo_event_log_v2', false), 'lo_history_migration_v2_marker'=>false !== get_option('lo_history_migration_v2_marker', false)],
            'dry_run_only' => true,
        ];
    }

    public static function cleanup(): array {
        $report = self::inspect();
        if (!is_file((string)$report['canonical_filesystem_path'])) { return ['removed'=>[], 'message'=>'Canonical plugin file is missing; cleanup aborted.']; }
        if (!version_compare((string)$report['canonical_version'], '0.3.1', '>=')) { return ['removed'=>[], 'message'=>'Canonical plugin version is below 0.3.1; cleanup aborted.']; }
        if (empty($report['canonical_active']) || empty($report['summary_endpoint_registered']) || empty($report['historical_events_visible'])) { return ['removed'=>[], 'message'=>'Safety checks failed; cleanup aborted.']; }
        if (function_exists('deactivate_plugins')) { deactivate_plugins(self::HELPERS, true); }
        $pluginsDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : dirname((string)LOUSY_OUTAGES_PATH);
        $removed = [];
        foreach (self::REMOVABLE_DIRS as $dir) {
            $path = rtrim($pluginsDir, '/').'/'.$dir;
            if (is_dir($path) && self::remove_dir($path)) { $removed[] = $dir; }
        }
        self::write_report($report, $removed);
        return ['removed'=>$removed, 'message'=>'Cleanup completed.'];
    }

    private static function remove_dir(string $dir): bool {
        $base = basename($dir);
        if (!in_array($base, self::REMOVABLE_DIRS, true)) { return false; }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
        return rmdir($dir);
    }

    private static function write_report(array $report, array $removed): void {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir'] ?? sys_get_temp_dir());
        file_put_contents($dir.'lousy-outages-cleanup-'.gmdate('Ymd-His').'.txt', wp_json_encode(['report'=>$report,'removed'=>$removed,'created_at'=>gmdate('c')], JSON_PRETTY_PRINT));
    }
}
