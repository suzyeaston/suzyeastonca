<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Cron;

use SuzyEaston\LousyOutages\IncidentAlerts;

class Refresh
{
    public static function bootstrap(): void
    {
        add_filter('cron_schedules', [self::class, 'registerSchedule']);
        add_action('init', [self::class, 'ensureScheduled']);
        add_action('lousy_outages_cron_refresh', '\\lousy_outages_refresh_data');
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

        if (! isset($schedules['lousy_outages_15min'])) {
            $schedules['lousy_outages_15min'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __('Every 15 Minutes', 'lousy-outages'),
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
        if (! wp_next_scheduled('lousy_outages_cron_refresh')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'lousy_outages_15min', 'lousy_outages_cron_refresh');
        }

        if (! wp_next_scheduled('lousy_outages_refresh')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'five_minutes', 'lousy_outages_refresh');
        }

        $target = self::nextDigestTimestamp();
        $scheduled = wp_next_scheduled('lo_send_daily_digest');

        if (! $scheduled || abs($scheduled - $target) > MINUTE_IN_SECONDS) {
            if ($scheduled) {
                wp_unschedule_event($scheduled, 'lo_send_daily_digest');
            }
            wp_schedule_event($target, 'lo_daily', 'lo_send_daily_digest');
        }
    }

    /**
     * Calculate the UTC timestamp for the next nightly digest run.
     */
    private static function nextDigestTimestamp(): int
    {
        $tz     = new \DateTimeZone('America/Vancouver');
        $now    = new \DateTimeImmutable('now', $tz);
        $target = $now->setTime(23, 59, 30);

        if ($target <= $now) {
            $target = $target->modify('+1 day');
        }

        return $target->setTimezone(new \DateTimeZone('UTC'))->getTimestamp();
    }
}
