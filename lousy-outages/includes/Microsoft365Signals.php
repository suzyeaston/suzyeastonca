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

    /**
     * A corroborated observation is not an official Microsoft incident.
     * Require two different provider status sources, with explicit M365/Outlook/
     * Exchange references in their own incident notices in the last 36 hours.
     */
    public static function related_provider_reports(array $events, int $now = 0): ?array
    {
        return DependencyCorrelations::detect_all($events, $now)['microsoft365'] ?? null;
    }

    public static function scan(): void
    {
        $official=class_exists(Microsoft365ServiceHealth::class)
            ? Microsoft365ServiceHealth::refresh()
            : ['status'=>'adapter_not_loaded'];
        // Same official-provider episode evidence used by the dashboard/RSS.
        // This check runs even when optional public chatter is disabled.
        if (class_exists(DependencyCorrelations::class)) {
            DependencyCorrelations::send_alerts();
        }
        $at=gmdate('c');
        $scheduled=wp_next_scheduled(self::HOOK);
        $base=[
            'at'=>$at,
            'next_scheduled_at'=>$scheduled?gmdate('c',(int)$scheduled):null,
            'official_graph'=>$official,
        ];
        if (!class_exists(PublicChatterSource::class)) {
            update_option('lo_microsoft365_signal_scan_result',$base+['status'=>'source_unavailable'],false);
            return;
        }
        $source=new PublicChatterSource();
        if (!$source->is_configured()) {
            // Disabling public chatter must not disable an authorized official Graph feed.
            update_option('lo_microsoft365_signal_scan_result',$base+['status'=>'public_chatter_disabled'],false);
            return;
        }

        try {
            $signals=$source->collect([
                'microsoft365_only'=>true,
                'collection_trigger'=>'microsoft365_cron',
                'window_minutes'=>60,
            ]);
            $stored=['inserted'=>0,'failed'=>0,'skipped'=>0];
            if ($signals) {
                $stored=ExternalSignals::record_many($signals);
                Feeds::refresh_status_feed_cache('microsoft365-public-signals');
            }
            $diag=get_option('lo_diag_public_chatter',[]);
            $diag=is_array($diag)?$diag:[];
            update_option('lo_microsoft365_signal_scan_result',$base+[
                'status'=>'completed',
                'signals'=>count($signals),
                'stored'=>(int)($stored['inserted']??0),
                'failed'=>(int)($stored['failed']??0),
                'queries_attempted'=>(int)($diag['queries_attempted']??0),
                'mentions_seen_by_provider'=>(int)($diag['mentions_seen_by_provider']['microsoft365']??0),
                'skipped_sources'=>(array)($diag['skipped_sources']??[]),
                'direct_sources_enabled'=>!empty($diag['direct_sources_enabled']),
            ],false);
        } catch (\Throwable $e) {
            update_option('lo_microsoft365_signal_scan_result',$base+[
                'status'=>'failed',
                'error'=>substr($e->getMessage(),0,180),
            ],false);
            error_log('[lousy_outages] Microsoft 365 public signal scan failed');
        }
    }
}
