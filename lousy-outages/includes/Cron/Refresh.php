<?php
declare(strict_types=1);

namespace LousyOutages\Cron;

use LousyOutages\IncidentAlerts;

class Refresh
{
    public static function bootstrap(): void
    {
        add_filter('cron_schedules', [self::class, 'registerSchedule']);
        add_action('init', [self::class, 'ensureScheduled']);
        add_action('lousy_outages_refresh', [IncidentAlerts::class, 'run']);
        add_action('lo_send_daily_digest', [IncidentAlerts::class, 'send_daily_digest']);
    }

    public static function registerSchedule(array $schedules): array
    {
        if (! isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every 5 Minutes', 'lousy-outages'),
            ];
        }

        if (! isset($schedules['lo_daily'])) {
            $schedules['lo_daily'] = [
                'interval' => DAY_IN_SECONDS,
                'display'  => __('Every 24 Hours', 'lousy-outages'),
            ];
        }

        return $schedules;
    }

    public static function ensureScheduled(): void
    {
        if (! wp_next_scheduled('lousy_outages_refresh')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'five_minutes', 'lousy_outages_refresh');
        }

        if (! wp_next_scheduled('lo_send_daily_digest')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'lo_daily', 'lo_send_daily_digest');
        }
    }
}
