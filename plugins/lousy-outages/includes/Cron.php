<?php
/**
 * Cron helpers for refreshing the cached snapshot.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('lo_cron_bootstrap')) {
    function lo_cron_bootstrap(): void
    {
        add_filter('cron_schedules', 'lo_register_snapshot_schedule');
        add_action('init', 'lo_ensure_snapshot_schedule');
        add_action('lo_refresh_snapshot', 'lo_run_snapshot_refresh');
    }
}

if (! function_exists('lo_cron_activate')) {
    function lo_cron_activate(): void
    {
        lo_ensure_snapshot_schedule();
    }
}

if (! function_exists('lo_cron_deactivate')) {
    function lo_cron_deactivate(): void
    {
        wp_clear_scheduled_hook('lo_refresh_snapshot');
    }
}

if (! function_exists('lo_register_snapshot_schedule')) {
    function lo_register_snapshot_schedule(array $schedules): array
    {
        $interval = lo_snapshot_interval_seconds();
        $schedules['lo_snapshot_interval'] = [
            'interval' => $interval,
            'display'  => __('Lousy Outages Snapshot Interval', 'lousy-outages'),
        ];

        return $schedules;
    }
}

if (! function_exists('lo_snapshot_interval_seconds')) {
    function lo_snapshot_interval_seconds(): int
    {
        $minutes = (int) get_option('lo_snapshot_interval_minutes', 3);
        $minutes = (int) apply_filters('lo_snapshot_interval_minutes', $minutes);
        if ($minutes < 2) {
            $minutes = 2;
        }
        if ($minutes > 5) {
            $minutes = 5;
        }

        return max(120, $minutes * MINUTE_IN_SECONDS);
    }
}

if (! function_exists('lo_ensure_snapshot_schedule')) {
    function lo_ensure_snapshot_schedule(): void
    {
        if (! wp_next_scheduled('lo_refresh_snapshot')) {
            wp_schedule_event(time() + 30, 'lo_snapshot_interval', 'lo_refresh_snapshot');
        }
    }
}

if (! function_exists('lo_run_snapshot_refresh')) {
    function lo_run_snapshot_refresh(): void
    {
        if (! function_exists('lo_snapshot_refresh')) {
            return;
        }

        $snapshot = lo_snapshot_refresh(true);
        do_action('lousy_outages_log', 'snapshot_refresh', [
            'services' => is_array($snapshot['services'] ?? null) ? count($snapshot['services']) : 0,
            'stale'    => ! empty($snapshot['stale']),
            'updated'  => $snapshot['updated_at'] ?? gmdate('c'),
        ]);
    }
}
