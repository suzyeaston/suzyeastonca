<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Sources\PublicChatterSource;

/**
 * Bounded public-signal scan, NOT an official Microsoft 365 status adapter.
 * Tenant service health requires Microsoft Graph authorization.
 */
final class Microsoft365Signals
{
    public const HOOK = 'lousy_outages_microsoft365_signal_scan';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'schedule']);
        add_action(self::HOOK, [self::class, 'scan']);
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 4 * MINUTE_IN_SECONDS, 'lousy_outages_15min', self::HOOK);
        }
    }

    public static function scan(): void
    {
        if (!class_exists(PublicChatterSource::class)) {
            update_option('lo_microsoft365_signal_scan_result', ['status'=>'source_unavailable','at'=>gmdate('c')], false);
            return;
        }
        $source = new PublicChatterSource();
        if (!$source->is_configured()) {
            update_option('lo_microsoft365_signal_scan_result', ['status'=>'disabled','at'=>gmdate('c')], false);
            return;
        }

        try {
            // One rotating query for each enabled lane; at most two public requests.
            // Keep this job separate from the canonical official-provider sweep.
            $signals = $source->collect([
                'microsoft365_only'=>true,
                'collection_trigger'=>'microsoft365_cron',
                'window_minutes'=>60,
            ]);
            $stored = ['inserted'=>0,'failed'=>0,'skipped'=>0];
            if ($signals) {
                $stored = ExternalSignals::record_many($signals);
                Feeds::refresh_status_feed_cache('microsoft365-public-signals');
            }
            update_option('lo_microsoft365_signal_scan_result', [
                'status'=>'completed',
                'at'=>gmdate('c'),
                'signals'=>count($signals),
                'stored'=>(int)($stored['inserted']??0),
                'failed'=>(int)($stored['failed']??0),
            ], false);
        } catch (\Throwable $e) {
            // Never let optional public chatter interrupt provider collection.
            update_option('lo_microsoft365_signal_scan_result', [
                'status'=>'failed',
                'at'=>gmdate('c'),
                'error'=>substr($e->getMessage(),0,180),
            ], false);
            error_log('[lousy_outages] Microsoft 365 public signal scan failed: '.$e->getMessage());
        }
    }
}
