<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Cron;

use SuzyEaston\LousyOutages\Feeds;
use SuzyEaston\LousyOutages\Fetcher;
use SuzyEaston\LousyOutages\IncidentAlerts;
use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\Store;

/** Bounded, durable collection and independently retryable publication. */
final class CanonicalPipeline
{
    public const RECURRING_HOOK = 'lousy_outages_refresh_official_providers';
    public const CONTINUATION_HOOK = 'lousy_outages_refresh_continue';
    public const RECOVERY_HOOK = 'lousy_outages_refresh_recovery';
    public const ALERT_HOOK = 'lousy_outages_publish_alerts';
    public const RSS_HOOK = 'lousy_outages_publish_rss';
    public const CYCLE_OPTION = 'lousy_outages_canonical_cycle_v1';
    public const LEASE_OPTION = 'lousy_outages_canonical_lease_v1';

    private static ?array $invocation = null;

    public static function bootstrap(): void
    {
        add_action(self::RECURRING_HOOK, [self::class, 'run']);
        add_action(self::CONTINUATION_HOOK, [self::class, 'run']);
        add_action(self::RECOVERY_HOOK, [self::class, 'run']);
        add_action(self::ALERT_HOOK, [self::class, 'publishAlerts']);
        add_action(self::RSS_HOOK, [self::class, 'publishRss']);
        add_action(IncidentAlerts::SUBSCRIBER_CATCHUP_HOOK, [IncidentAlerts::class, 'dispatchSubscriberAlerts']);
    }

    public static function cadence(): int
    {
        return max(5 * MINUTE_IN_SECONDS, (int) apply_filters('lousy_outages_canonical_interval', 15 * MINUTE_IN_SECONDS));
    }

    public static function budget(): int
    {
        return max(5, min(45, (int) apply_filters('lousy_outages_invocation_budget_seconds', 25)));
    }

    public static function leaseLifetime(): int
    {
        return max(30, self::budget() + 20);
    }

    public static function run(): array
    {
        $cycle = self::cycle();
        if (!$cycle || in_array((string)($cycle['final_status'] ?? ''), ['completed', 'abandoned'], true)) {
            $cycle = self::newCycle();
        }
        $token = wp_generate_uuid4();
        if (!self::acquireLease($token, (string)$cycle['cycle_id'], 'collection')) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Active canonical lease'];
        }
        self::$invocation = ['token'=>$token, 'cycle_id'=>$cycle['cycle_id'], 'started'=>microtime(true), 'phase'=>'collection', 'last_provider'=>'', 'completed'=>false];
        register_shutdown_function([self::class, 'shutdown']);

        try {
            if (!isset($cycle['errors']) || !is_array($cycle['errors'])) {
                $cycle['errors'] = [];
            }
            self::dispatchUnalertedProviders($cycle);
            try {
                IncidentAlerts::drain_deferred_alerts([
                    'cycle_id' => (string) $cycle['cycle_id'],
                    'snapshot' => self::snapshotForAlertDrain($cycle),
                ]);
            } catch (\Throwable $e) {
                $cycle['errors'][] = ['id' => '', 'message' => 'alert_drain_failed'];
                self::saveCycle($cycle);
            }
            $providers = Providers::enabled();
            $byId = [];
            foreach ($providers as $provider) $byId[(string)$provider['id']] = $provider;
            $fetcher = new Fetcher((int)apply_filters('lousy_outages_provider_timeout', 8));
            $deadline = microtime(true) + self::budget();
            $batchLimit = max(1, (int)apply_filters('lousy_outages_provider_batch_size', 4));
            $done = 0;
            while ((int)$cycle['provider_cursor'] < count($cycle['provider_ids']) && $done < $batchLimit && microtime(true) < $deadline) {
                $id = (string)$cycle['provider_ids'][(int)$cycle['provider_cursor']];
                try {
                    $cycle['provider_states'][$id] = isset($byId[$id]) ? $fetcher->fetch($byId[$id]) : ['id'=>$id, 'status'=>'unknown', 'error'=>'Provider configuration disappeared'];
                } catch (\Throwable $e) {
                    $cycle['provider_states'][$id] = ['id'=>$id, 'status'=>'unknown', 'error'=>'Fetch failed'];
                    $cycle['errors'][] = ['id'=>$id, 'message'=>self::sanitizeMessage($e->getMessage())];
                }
                $cycle['provider_cursor']++;
                $cycle['completed_provider_count'] = $cycle['provider_cursor'];
                $cycle['last_completed_provider'] = $id;
                $cycle['heartbeat_at'] = gmdate('c');
                self::$invocation['last_provider'] = $id;
                self::saveCycle($cycle);
                self::heartbeat($token, 'collection');
                $done++;
                self::rememberProviderAlert($cycle, $id, $cycle['provider_states'][$id]);
            }
            if ((int)$cycle['provider_cursor'] < count($cycle['provider_ids'])) {
                $cycle['continuation_count']++;
                self::saveCycle($cycle);
                self::scheduleOnce(self::CONTINUATION_HOOK, time() + 10);
                return ['ok'=>true, 'partial'=>true, 'cycle_id'=>$cycle['cycle_id'], 'cursor'=>$cycle['provider_cursor']];
            }

            self::$invocation['phase'] = 'snapshot_commit';
            $cycle['phase'] = 'snapshot_commit';
            self::saveCycle($cycle);
            self::heartbeat($token, 'snapshot_commit');
            $snapshot = \lousy_outages_commit_collected_states($cycle['provider_states'], (string)$cycle['cycle_id']);
            $cycle['snapshot_fingerprint'] = hash('sha256', (string)wp_json_encode($snapshot));
            $cycle['snapshot_committed_at'] = gmdate('c');
            $cycle['phase'] = 'publication_pending';
            $cycle['publication'] = ['alerts'=>'pending', 'rss'=>'pending'];
            $cycle['final_status'] = 'collection_completed';
            self::saveCycle($cycle);
            try {
                self::publishAlerts((string) $cycle['cycle_id']);
            } catch (\Throwable $e) {
                $cycle = self::cycle();
                $cycle['publication_errors']['alerts'] = self::sanitizeMessage($e->getMessage());
                self::saveCycle($cycle);
            }
            $cycle = self::cycle();
            if (($cycle['publication']['alerts'] ?? '') !== 'completed') {
                self::scheduleOnce(self::ALERT_HOOK, time() + 10, [(string) $cycle['cycle_id']]);
            }
            self::scheduleOnce(self::RSS_HOOK, time() + 2, [(string) $cycle['cycle_id']]);
            update_option('lousy_outages_last_snapshot_commit', ['cycle_id'=>$cycle['cycle_id'], 'timestamp'=>$cycle['snapshot_committed_at'], 'fingerprint'=>$cycle['snapshot_fingerprint']], false);
            return ['ok'=>true, 'cycle_id'=>$cycle['cycle_id'], 'snapshot'=>$snapshot];
        } finally {
            self::$invocation['completed'] = true;
            self::releaseLease($token);
        }
    }

    public static function publishAlerts(string $cycleId = ''): void
    {
        self::publishChannel('alerts', $cycleId, static function(array $cycle): array {
            $snapshot = (array)get_option('lousy_outages_current_state', []);
            return IncidentAlerts::process_snapshot($snapshot, ['mode'=>'canonical_refresh', 'cycle_id'=>$cycle['cycle_id']]);
        });
    }

    public static function publishRss(string $cycleId = ''): void
    {
        self::publishChannel('rss', $cycleId, static function(array $cycle): array {
            Feeds::refresh_status_feed_cache((string)$cycle['cycle_id'], (string)($cycle['snapshot_fingerprint'] ?? ''));
            return Feeds::get_status_feed_diagnostics();
        });
    }

    private static function publishChannel(string $channel, string $cycleId, callable $callback): void
    {
        $cycle = self::cycle();
        if (!$cycle) {
            return;
        }
        if ($cycleId && $cycleId !== $cycle['cycle_id']) {
            // Stale queued event — skip only if the active cycle already finished this channel.
            if (($cycle['publication'][$channel] ?? '') === 'completed') {
                return;
            }
        }
        if (($cycle['publication'][$channel] ?? '') === 'completed') {
            return;
        }
        $cycle['publication'][$channel] = 'running'; self::saveCycle($cycle);
        try {
            $cycle['publication_results'][$channel] = $callback($cycle);
            $pending=(int)($cycle['publication_results'][$channel]['result']['pending']??0);
            $cycle['publication'][$channel] = $pending>0 ? 'pending' : 'completed';
            if($pending>0) self::scheduleOnce(self::ALERT_HOOK,time()+10,[$cycle['cycle_id']]);
        } catch (\Throwable $e) {
            $cycle['publication'][$channel] = 'failed';
            $cycle['publication_errors'][$channel] = self::sanitizeMessage($e->getMessage());
            self::scheduleOnce($channel === 'alerts' ? self::ALERT_HOOK : self::RSS_HOOK, time() + 60, [$cycle['cycle_id']]);
        }
        if (($cycle['publication']['alerts'] ?? '') === 'completed' && ($cycle['publication']['rss'] ?? '') === 'completed') {
            $cycle['phase']='completed'; $cycle['final_status']='completed'; $cycle['completed_at']=gmdate('c');
            update_option('lousy_outages_last_end_to_end_cycle', ['cycle_id'=>$cycle['cycle_id'], 'timestamp'=>$cycle['completed_at']], false);
        }
        self::saveCycle($cycle);
    }

    public static function acquireLease(string $token, string $cycleId, string $phase): bool
    {
        $now=time(); $current=get_option(self::LEASE_OPTION, null);
        if (is_array($current) && (int)($current['expires_at']??0)>$now) return false;
        if (is_array($current)) delete_option(self::LEASE_OPTION);
        return add_option(self::LEASE_OPTION, ['owner_token'=>$token,'cycle_id'=>$cycleId,'acquired_at'=>$now,'heartbeat'=>$now,'expires_at'=>$now+self::leaseLifetime(),'phase'=>$phase], '', false);
    }
    public static function releaseLease(string $token): bool { $l=get_option(self::LEASE_OPTION,[]); if(!is_array($l)||!hash_equals((string)($l['owner_token']??''),$token))return false; return delete_option(self::LEASE_OPTION); }
    private static function heartbeat(string $token,string $phase): void { $l=get_option(self::LEASE_OPTION,[]);if(!is_array($l)||!hash_equals((string)($l['owner_token']??''),$token))return;$l['heartbeat']=time();$l['expires_at']=time()+self::leaseLifetime();$l['phase']=$phase;update_option(self::LEASE_OPTION,$l,false); }
    public static function cycle(): array { $v=get_option(self::CYCLE_OPTION,[]);return is_array($v)?$v:[]; }
    private static function saveCycle(array $cycle): void { update_option(self::CYCLE_OPTION,$cycle,false); }
    private static function newCycle(): array { $ids=[];foreach(Providers::enabled() as $p)$ids[]=(string)$p['id'];$now=gmdate('c');$cycle=['cycle_id'=>wp_generate_uuid4(),'started_at'=>$now,'heartbeat_at'=>$now,'phase'=>'collection','provider_ids'=>$ids,'provider_cursor'=>0,'completed_provider_count'=>0,'provider_states'=>[],'alerted_provider_ids'=>[],'errors'=>[],'previous_states'=>(new Store())->get_all(),'last_completed_provider'=>'','continuation_count'=>0,'publication'=>['alerts'=>'not_ready','rss'=>'not_ready'],'publication_results'=>[],'publication_errors'=>[],'final_status'=>'running'];self::saveCycle($cycle);return$cycle; }

    /**
     * Mail incidents from one freshly fetched provider before the rest of the sweep.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function publishProviderDetection(string $providerId, array $state, string $cycleId = ''): array
    {
        if (!self::providerStateNeedsAlert($state)) {
            return ['delivery_locked' => false, 'skipped' => true, 'reason' => 'not_alertable', 'sent' => 0];
        }
        $fetchedAt = gmdate('c');
        $payload = function_exists('lousy_outages_build_provider_payload')
            ? lousy_outages_build_provider_payload($providerId, $state, $fetchedAt)
            : self::providerPayload($providerId, $state, $fetchedAt);
        return IncidentAlerts::process_snapshot(
            ['providers' => [$payload], 'fetched_at' => $fetchedAt],
            ['mode' => 'canonical_refresh', 'cycle_id' => $cycleId, 'prompt_dispatch' => true]
        );
    }

    /**
     * @param array<string,mixed> $cycle
     * @param array<string,mixed> $state
     */
    private static function rememberProviderAlert(array &$cycle, string $providerId, array $state): void
    {
        $alerted = array_fill_keys(array_map('strval', (array) ($cycle['alerted_provider_ids'] ?? [])), true);
        if (isset($alerted[$providerId])) {
            return;
        }
        try {
            $diag = self::publishProviderDetection($providerId, $state, (string) ($cycle['cycle_id'] ?? ''));
        } catch (\Throwable $e) {
            $cycle['errors'][] = ['id' => $providerId, 'message' => 'alert_dispatch_failed'];
            self::saveCycle($cycle);
            return;
        }
        if (!self::alertDispatchSettled($diag)) {
            return;
        }
        $cycle['alerted_provider_ids'][] = $providerId;
        self::saveCycle($cycle);
    }

    /**
     * A provider stays eligible for the next pass until mail is accepted or there is nothing to send.
     * A locked worker, a failed transport, or leftover recipients must not count as done.
     *
     * @param array<string,mixed> $diag
     */
    private static function alertDispatchSettled(array $diag): bool
    {
        if (!empty($diag['delivery_locked'])) {
            return false;
        }
        if (!empty($diag['skipped']) && (string) ($diag['reason'] ?? '') === 'not_alertable') {
            return true;
        }
        if ((int) ($diag['result']['pending'] ?? 0) > 0) {
            return false;
        }
        $failures = array_values(array_filter(array_map('strval', (array) ($diag['failures'] ?? [])), static function (string $failure): bool {
            return !str_starts_with($failure, 'incident_store:') && !str_starts_with($failure, 'history_store:');
        }));
        if ($failures) {
            return false;
        }
        if ((int) ($diag['emails_sent'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($diag['pending_recipient_slots'] ?? 0) > 0) {
            return false;
        }
        if (array_key_exists('snapshot_incident_count', $diag) && (int) $diag['snapshot_incident_count'] === 0) {
            return false;
        }
        return true;
    }

    /**
     * Drain against fetched states from this cycle, not the last committed snapshot.
     * Otherwise a provider already seen in outage still looks operational and the retry is closed.
     *
     * @param array<string,mixed> $cycle
     * @return array<string,mixed>
     */
    private static function snapshotForAlertDrain(array $cycle): array
    {
        $stored = get_option('lousy_outages_current_state', []);
        $snapshot = is_array($stored) ? $stored : [];
        $providers = [];
        foreach ((array) ($snapshot['providers'] ?? []) as $provider) {
            if (is_array($provider) && !empty($provider['id'])) {
                $providers[(string) $provider['id']] = $provider;
            }
        }
        $fetchedAt = gmdate('c');
        foreach ((array) ($cycle['provider_states'] ?? []) as $id => $state) {
            if (!is_array($state)) {
                continue;
            }
            $providerId = (string) $id;
            $providers[$providerId] = function_exists('lousy_outages_build_provider_payload')
                ? lousy_outages_build_provider_payload($providerId, $state, $fetchedAt)
                : self::providerPayload($providerId, $state, $fetchedAt);
        }
        $snapshot['providers'] = array_values($providers);
        if (empty($snapshot['fetched_at'])) {
            $snapshot['fetched_at'] = $fetchedAt;
        }
        return $snapshot;
    }

    /**
     * @param array<string,mixed> $cycle
     */
    private static function dispatchUnalertedProviders(array &$cycle): void
    {
        foreach ((array) ($cycle['provider_states'] ?? []) as $id => $state) {
            if (!is_array($state)) {
                continue;
            }
            self::rememberProviderAlert($cycle, (string) $id, $state);
        }
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function providerStateNeedsAlert(array $state): bool
    {
        $status = strtolower(trim((string) ($state['status'] ?? '')));
        if (in_array($status, ['degraded', 'partial_outage', 'major_outage', 'maintenance', 'major', 'outage', 'partial', 'critical', 'minor'], true)) {
            return true;
        }
        foreach ((array) ($state['incidents'] ?? []) as $incident) {
            if (is_array($incident) && $incident) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function providerPayload(string $id, array $state, string $fetchedAt): array
    {
        $incidents = [];
        foreach ((array) ($state['incidents'] ?? []) as $incident) {
            if (!is_array($incident)) {
                continue;
            }
            $incidents[] = [
                'id' => (string) ($incident['id'] ?? ''),
                'title' => (string) ($incident['title'] ?? $incident['name'] ?? 'Incident'),
                'summary' => (string) ($incident['summary'] ?? ''),
                'impact' => (string) ($incident['impact'] ?? $incident['status'] ?? 'major'),
                'status' => (string) ($incident['status'] ?? ''),
                'startedAt' => (string) ($incident['startedAt'] ?? $incident['started_at'] ?? ''),
                'url' => (string) ($incident['url'] ?? $incident['shortlink'] ?? ($state['url'] ?? '')),
            ];
        }
        return [
            'id' => $id,
            'name' => (string) ($state['name'] ?? $id),
            'stateCode' => (string) ($state['status'] ?? 'unknown'),
            'state' => (string) ($state['status_label'] ?? $state['status'] ?? ''),
            'summary' => (string) ($state['summary'] ?? ''),
            'updatedAt' => (string) ($state['updated_at'] ?? $fetchedAt),
            'url' => (string) ($state['url'] ?? ''),
            'sourceType' => (string) ($state['source_type'] ?? 'statuspage'),
            'incidents' => $incidents,
        ];
    }
    private static function scheduleOnce(string $hook,int $at,array $args=[]): void { if(!wp_next_scheduled($hook,$args))wp_schedule_single_event($at,$hook,$args); }
    private static function sanitizeMessage(string $m): string { return substr(preg_replace('/https?:\/\/\S+|[A-Z0-9._%+-]+@[A-Z0-9.-]+/i','[redacted]',$m)??'',0,240); }

    public static function shutdown(): void
    {
        if (!self::$invocation || !empty(self::$invocation['completed'])) return;
        $error=error_get_last(); $fatal=$error && in_array((int)$error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true);
        $record=['cycle_id'=>self::$invocation['cycle_id'],'phase'=>self::$invocation['phase'],'last_provider'=>self::$invocation['last_provider'],'elapsed_ms'=>(int)((microtime(true)-self::$invocation['started'])*1000),'interrupted'=>true,'timestamp'=>gmdate('c')];
        if($fatal){$record['fatal_type']=(int)$error['type'];$record['fatal_message']=self::sanitizeMessage((string)$error['message']);}
        update_option('lousy_outages_last_interrupted_invocation',$record,false);
        self::releaseLease((string)self::$invocation['token']);
        self::scheduleOnce(self::CONTINUATION_HOOK,time()+10);
    }
}
