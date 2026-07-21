<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Storage\IncidentStore;

class Summary {
    public static function current(): array {
        $providers = self::providers();
        $latestIncident = self::latest_incident($providers);
        $latestSignal = self::latest_signal_provider($providers);
        $outageCount = self::count_outages($providers);
        $signalCount = self::count_signals($providers);

        if ($latestIncident) {
            $slug = sanitize_title($latestIncident['provider_id'] ?: $latestIncident['provider_name']);

            return [
                'kind'        => 'outage',
                'hasIncident' => true,
                'hasSignal'   => false,
                'providerId'  => $latestIncident['provider_id'],
                'provider'    => $latestIncident['provider_name'],
                'title'       => $latestIncident['title'],
                'status'      => $latestIncident['status'],
                'relative'    => self::relative_time_from_timestamp(
                    (int) ($latestIncident['started_timestamp'] ?? $latestIncident['sort_timestamp'])
                ),
                'started_at'  => $latestIncident['started_at'],
                'href'        => $slug ? home_url('/lousy-outages/#provider-' . $slug) : home_url('/lousy-outages/'),
                'outageCount' => $outageCount,
                'signalCount' => $signalCount,
            ];
        }

        // Degraded indicators without unresolved incidents are treated as signals.
        if ($latestSignal) {
            $slug = sanitize_title($latestSignal['provider_id'] ?: $latestSignal['provider_name']);

            return [
                'kind'        => 'signal',
                'hasIncident' => false,
                'hasSignal'   => true,
                'providerId'  => $latestSignal['provider_id'],
                'provider'    => $latestSignal['provider_name'],
                'title'       => $latestSignal['title'],
                'status'      => $latestSignal['status'],
                'relative'    => self::relative_time_from_timestamp(
                    (int) ($latestSignal['started_timestamp'] ?? $latestSignal['sort_timestamp'])
                ),
                'started_at'  => $latestSignal['started_at'],
                'href'        => $slug ? home_url('/lousy-outages/#provider-' . $slug) : home_url('/lousy-outages/'),
                'outageCount' => $outageCount,
                'signalCount' => $signalCount,
            ];
        }

        if (self::has_verification_delay($providers)) {
            return [
                'kind'        => 'delayed',
                'hasIncident' => false,
                'hasSignal'   => false,
                'providerId'  => '',
                'provider'    => '',
                'title'       => 'Verification delayed; latest provider checks are unavailable.',
                'status'      => 'Verification delayed',
                'relative'    => '',
                'started_at'  => '',
                'href'        => home_url('/lousy-outages/'),
                'outageCount' => $outageCount,
                'signalCount' => $signalCount,
            ];
        }

        return [
            'kind'        => 'clear',
            'hasIncident' => false,
            'hasSignal'   => false,
            'providerId'  => '',
            'provider'    => '',
            'title'       => 'All systems operational.',
            'status'      => 'Operational',
            'relative'    => '',
            'started_at'  => '',
            'href'        => home_url('/lousy-outages/'),
            'outageCount' => $outageCount,
            'signalCount' => $signalCount,
        ];
    }



    public static function current_provider_tiles(array $providers = null): array
    {
        $providers = null === $providers ? self::providers() : $providers;
        $out = [];
        foreach (self::sort_status_page_providers($providers) as $provider) {
            if (!is_array($provider)) { continue; }
            $current = self::current_incidents_for_provider($provider);
            if (empty($current)) {
                $tileKind = strtolower((string) ($provider['tile_kind'] ?? $provider['tileKind'] ?? ''));
                if (!in_array($tileKind, ['outage', 'signal'], true) || !empty($provider['incidents'])) { continue; }
            }
            $copy = $provider;
            $copy['incidents'] = $current;
            $out[] = $copy;
        }
        return $out;
    }

    public static function current_incidents_for_provider(array $provider): array
    {
        $incidents = isset($provider['incidents']) && is_array($provider['incidents']) ? array_values(array_filter($provider['incidents'], 'is_array')) : [];
        $current = array_values(array_filter($incidents, static function (array $incident): bool {
            return self::is_current_official_incident($incident);
        }));
        usort($current, static function (array $left, array $right): int { return self::incident_timestamp($right) <=> self::incident_timestamp($left); });
        return $current;
    }

    public static function is_current_official_incident(array $incident, ?int $now = null): bool
    {
        $now = $now ?? time();
        if (!self::is_unresolved_incident($incident)) { return false; }
        $status = strtolower((string) ($incident['status'] ?? $incident['impact'] ?? ''));
        $text = strtolower((string)($incident['title'] ?? '') . ' ' . (string)($incident['name'] ?? '') . ' ' . (string)($incident['summary'] ?? '') . ' ' . (string)($incident['eta'] ?? ''));
        $ongoing = in_array($status, ['investigating','identified','monitoring','ongoing'], true)
            || preg_match('/\b(investigating|identified|monitoring|ongoing|continues?|continuing|currently|still experiencing|working to resolve|recovery is expected|unresolved)\b/i', $text);
        $updated = self::incident_timestamp($incident);
        $ageWindow = 7 * DAY_IN_SECONDS;
        if ($updated && ($now - $updated) <= $ageWindow) { return true; }
        return (bool) $ongoing;
    }

    /**
     * Return the ordered active incident collection used by the public status page.
     *
     * The /lousy-outages/ renderer receives provider tiles from the current snapshot and
     * treats non-empty provider `incidents` arrays as active official incidents. The
     * homepage teaser must read that same live snapshot before falling back to stored
     * history, otherwise unresolved active incidents can be missed until/unless they are
     * also present in the persisted event log.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ordered_current_incidents(int $limit = 0): array
    {
        $state = function_exists('lousy_outages_get_current_state') ? \lousy_outages_get_current_state() : ['outages'=>[]];
        $records = [];
        foreach ((array)($state['outages'] ?? []) as $incident) {
            if (!is_array($incident)) { continue; }
            $providerId = sanitize_key((string)($incident['provider_id'] ?? $incident['provider'] ?? ''));
            $providerName = trim((string)($incident['provider'] ?? $incident['provider_name'] ?? $providerId));
            $started = (string)($incident['startedAt'] ?? $incident['started_at'] ?? $incident['created_at'] ?? '');
            $updated = (string)($incident['updatedAt'] ?? $incident['updated_at'] ?? $incident['last_official_update'] ?? $started);
            $status = strtolower((string)($incident['status'] ?? $incident['impact'] ?? 'incident'));
            $records[] = ['id'=>(string)($incident['id'] ?? sha1($providerId.'|'.($incident['title'] ?? '').'|'.$started.'|'.$updated)),'provider_id'=>$providerId,'provider'=>$providerName ?: 'Unknown provider','status'=>$status ?: 'incident','status_label'=>Fetcher::status_label($status ?: 'incident'),'severity'=>strtolower((string)($incident['impact'] ?? $status ?: 'degraded')),'important'=>true,'summary'=>(string)($incident['display_title'] ?? $incident['displayTitle'] ?? $incident['title'] ?? $incident['name'] ?? $incident['summary'] ?? 'Incident reported'),'started_at'=>self::format_incident_time($started),'updated_at'=>self::format_incident_time($updated),'first_seen'=>self::parse_time($started) ?? 0,'last_seen'=>self::parse_time($updated) ?? (self::parse_time($started) ?? 0),'url'=>(string)($incident['url'] ?? $incident['provider_url'] ?? '')];
        }
        usort($records, static fn(array $a, array $b): int => ((int)($b['last_seen'] ?? 0)) <=> ((int)($a['last_seen'] ?? 0)));
        $records = self::dedupe_ordered_incidents($records);
        return $limit > 0 ? array_slice($records, 0, $limit) : $records;
    }

    /**
     * Return the same normalized incident records and ordering used by the public history panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ordered_recent_incidents(int $days = 30, bool $importantOnly = true, int $limit = 0): array
    {
        $days = max(1, min(365, $days));
        $cutoff = time() - ($days * DAY_IN_SECONDS);
        $providerLabels = [];
        foreach (Providers::enabled() as $id => $provider) {
            $providerLabels[$id] = isset($provider['name']) ? (string) $provider['name'] : ucfirst((string) $id);
        }
        $allowedProviders = array_fill_keys(array_keys($providerLabels), true);
        $incidentStore = new IncidentStore();
        $events = $incidentStore->getStoredIncidents();
        $prepared = [];
        $normalizeStatus = static function (string $status): string {
            $status = strtolower(trim($status));
            switch ($status) {
                case 'ok':
                case 'none':
                case 'operational':
                case 'resolved':
                    return 'operational';
                case 'maintenance':
                case 'maintenance_window':
                    return 'maintenance';
                case 'minor':
                case 'minor_outage':
                case 'degraded':
                case 'degraded_performance':
                case 'partial':
                case 'partial_outage':
                case 'incident':
                case 'investigating':
                case 'identified':
                case 'monitoring':
                    return 'degraded';
                case 'major_outage':
                case 'outage':
                case 'major':
                case 'critical':
                    return 'major';
            }

            return $status ?: 'incident';
        };

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event = $incidentStore->normalizeEvent($event);
            $slug = sanitize_key((string) ($event['provider'] ?? ''));
            $source = strtolower((string) ($event['source'] ?? ''));
            $severity = strtolower((string) ($event['severity'] ?? ''));
            $isUserReport = ('user_report' === $source || 'user_report' === $severity);
            $allowOther = ('other' === $slug && $isUserReport);

            if ('' === $slug || (!isset($allowedProviders[$slug]) && !$allowOther)) {
                continue;
            }
            if ($allowOther && !isset($providerLabels[$slug])) {
                $providerLabels[$slug] = (string) ($event['provider_label'] ?? 'Other');
            }

            $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $lastSeen = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
            $incidentStart = $firstSeen ?: $lastSeen;
            if ($incidentStart && $incidentStart < $cutoff) {
                continue;
            }

            $status = $normalizeStatus((string) ($event['status'] ?? 'unknown'));
            if (in_array($status, ['operational', 'ok', 'none'], true)) {
                continue;
            }

            $important = isset($event['important']) ? (bool) $event['important'] : true;
            if ($importantOnly && (!$important || in_array($severity, ['maintenance', 'info'], true))) {
                continue;
            }

            $providerLabel = $providerLabels[$slug] ?? ($event['provider_label'] ?? ucfirst($slug));
            $prepared[] = [
                'id' => (string) ($event['guid'] ?? sha1($slug . '|' . ($event['title'] ?? '') . '|' . ($firstSeen ?: $lastSeen))),
                'provider_id' => $slug,
                'provider' => (string) $providerLabel,
                'status' => $status,
                'status_label' => Fetcher::status_label($status),
                'severity' => $severity ?: 'degraded',
                'important' => (bool) $important,
                'summary' => (string) ($event['title'] ?? $event['description'] ?? ''),
                'started_at' => $firstSeen ? gmdate('c', $firstSeen) : null,
                'updated_at' => $lastSeen ? gmdate('c', $lastSeen) : null,
                'first_seen' => $firstSeen,
                'last_seen' => $lastSeen,
                'url' => isset($event['url']) ? (string) $event['url'] : '',
            ];
        }

        usort($prepared, static function (array $left, array $right): int {
            $leftTs = (int) ($left['last_seen'] ?: ($left['first_seen'] ?? 0));
            $rightTs = (int) ($right['last_seen'] ?: ($right['first_seen'] ?? 0));
            return $rightTs <=> $leftTs;
        });

        $deduped = self::dedupe_ordered_incidents($prepared);
        return $limit > 0 ? array_slice($deduped, 0, $limit) : $deduped;
    }

    private static function dedupe_ordered_incidents(array $incidents): array
    {
        $seen = [];
        $deduped = [];
        foreach ($incidents as $incident) {
            $title = strtolower((string) ($incident['summary'] ?? ''));
            $title = preg_replace('/\bdetails\b/', '', $title) ?: '';
            $title = preg_replace('/([!?.:,;])\1+/', '$1', $title) ?: '';
            $title = trim(preg_replace('/\s+/', ' ', $title) ?: '');
            $started = self::parse_time($incident['started_at'] ?? null) ?? 0;
            $key = strtolower((string) ($incident['provider_id'] ?? $incident['provider'] ?? '')) . '|' . $title . '|' . (string) floor($started / 60);
            $updated = self::parse_time($incident['updated_at'] ?? null) ?? 0;
            if (!isset($seen[$key]) || ((self::parse_time($seen[$key]['updated_at'] ?? null) ?? 0) < $updated)) {
                $seen[$key] = $incident;
            }
        }
        return array_values($seen);
    }

    private static function severity_rank(string $value): int
    {
        $rank = ['critical' => 4, 'major' => 4, 'major_outage' => 4, 'outage' => 4, 'partial' => 3, 'partial_outage' => 3, 'degraded_performance' => 3, 'incident' => 3, 'degraded' => 3, 'minor' => 3, 'maintenance' => 2, 'info' => 1];
        $key = strtolower($value);
        return $rank[$key] ?? 1;
    }

    private static function sort_status_page_providers(array $providers): array
    {
        $tilePriority = ['outage' => 0, 'signal' => 1, 'unknown' => 2, 'manual' => 3, 'operational' => 4];
        usort($providers, static function (array $a, array $b) use ($tilePriority): int {
            $aSort = is_numeric($a['sort_key'] ?? null) ? (int) $a['sort_key'] : self::legacy_provider_sort_key($a);
            $bSort = is_numeric($b['sort_key'] ?? null) ? (int) $b['sort_key'] : self::legacy_provider_sort_key($b);
            if ($aSort !== $bSort) {
                return $aSort <=> $bSort;
            }
            $aKind = strtolower((string) ($a['tile_kind'] ?? $a['tileKind'] ?? 'unknown'));
            $bKind = strtolower((string) ($b['tile_kind'] ?? $b['tileKind'] ?? 'unknown'));
            $aRank = $tilePriority[$aKind] ?? $tilePriority['unknown'];
            $bRank = $tilePriority[$bKind] ?? $tilePriority['unknown'];
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            return strtolower((string) ($a['name'] ?? $a['id'] ?? $a['provider'] ?? '')) <=> strtolower((string) ($b['name'] ?? $b['id'] ?? $b['provider'] ?? ''));
        });
        return $providers;
    }

    private static function legacy_provider_sort_key(array $provider): int
    {
        $statePriority = ['outage' => 0, 'major' => 0, 'critical' => 0, 'degraded' => 1, 'partial' => 1, 'maintenance' => 2, 'unknown' => 3, 'operational' => 4, 'ok' => 4];
        if (!empty($provider['incidents']) && is_array($provider['incidents'])) {
            return 0;
        }
        $status = strtolower((string) ($provider['stateCode'] ?? $provider['status'] ?? 'unknown'));
        return $statePriority[$status] ?? $statePriority['unknown'];
    }

    private static function format_incident_time($value): ?string
    {
        $timestamp = self::parse_time($value);
        return $timestamp ? gmdate('c', $timestamp) : null;
    }

    public static function providers(): array
    {
        if (function_exists('lousy_outages_get_current_state')) {
            $state = \lousy_outages_get_current_state();
            if (!empty($state['providers']) && is_array($state['providers'])) {
                return array_values($state['providers']);
            }
        }
        $snapshot = get_option('lousy_outages_snapshot', []);
        return isset($snapshot['providers']) && is_array($snapshot['providers']) ? array_values($snapshot['providers']) : [];
    }

    private static function has_verification_delay(array $providers): bool
    {
        foreach ($providers as $provider) {
            if (!is_array($provider)) { continue; }
            $verification = strtolower((string) ($provider['verification_status'] ?? ''));
            $status = strtolower((string) ($provider['stateCode'] ?? $provider['status'] ?? ''));
            if (!empty($provider['is_stale']) || in_array($verification, ['failed', 'stale', 'unknown'], true) || 'unknown' === $status) {
                return true;
            }
        }
        return false;
    }

    private static function latest_incident(array $providers): ?array
    {
        $latest = null;
        $recent_cutoff = time() - (2 * DAY_IN_SECONDS);

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $providerId = (string) ($provider['id'] ?? $provider['provider'] ?? '');
            $providerName = (string) ($provider['name'] ?? $provider['provider'] ?? $providerId);
            $incidents = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];

            foreach ($incidents as $incident) {
                if (!is_array($incident)) {
                    continue;
                }

                if (!self::is_current_official_incident($incident)) {
                    continue;
                }

                $sortTimestamp = self::incident_timestamp($incident);
                $startedTimestamp = self::incident_started_timestamp($incident);

                if (null === $sortTimestamp || $sortTimestamp < $recent_cutoff) {
                    continue;
                }

                if (null === $latest || $sortTimestamp > $latest['sort_timestamp']) {
                    $startedIso = self::incident_start_iso($incident, $sortTimestamp);
                    $statusCode = strtolower((string) ($incident['impact'] ?? $incident['status'] ?? ''));

                    $latest = [
                        'provider_id'        => $providerId,
                        'provider_name'      => $providerName ?: $providerId,
                        'title'              => (string) ($incident['title'] ?? $incident['name'] ?? 'Incident'),
                        'status'             => Fetcher::status_label($statusCode ?: 'incident'),
                        'started_at'         => $startedIso,
                        'sort_timestamp'     => $sortTimestamp,
                        'started_timestamp'  => $startedTimestamp ?? $sortTimestamp,
                    ];
                }
            }
        }

        return $latest;
    }

    private static function latest_signal_provider(array $providers): ?array
    {
        $fallback = null;
        $recent_cutoff = time() - (4 * HOUR_IN_SECONDS);
        $eligibleFallbackStates = ['degraded', 'outage', 'major', 'partial', 'monitoring', 'investigating'];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $tileKind = strtolower((string) ($provider['tile_kind'] ?? $provider['tileKind'] ?? ''));
            $status = strtolower((string) ($provider['stateCode'] ?? $provider['status'] ?? 'unknown'));
            $isSignal = $tileKind === 'signal' || in_array($status, $eligibleFallbackStates, true);

            if (!$isSignal) {
                continue;
            }

            $providerId = (string) ($provider['id'] ?? $provider['provider'] ?? '');
            $providerName = (string) ($provider['name'] ?? $provider['provider'] ?? $providerId);
            $incidents = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];

            // Only treat degraded indicators as signals when there are no unresolved incidents.
            if (self::has_unresolved_incident($incidents)) {
                continue;
            }

            $updated = self::parse_time($provider['updatedAt'] ?? $provider['updated_at'] ?? null);
            $sortTimestamp = $updated ?? time();
            if ($updated && $updated < $recent_cutoff) {
                continue;
            }

            if (null === $fallback || $sortTimestamp > $fallback['sort_timestamp']) {
                $fallback = [
                    'provider_id'        => $providerId,
                    'provider_name'      => $providerName,
                    'title'              => (string) ($provider['summary'] ?? Fetcher::status_label($status)),
                    'status'             => Fetcher::status_label($status),
                    'started_at'         => (string) ($provider['updatedAt'] ?? $provider['updated_at'] ?? ''),
                    'sort_timestamp'     => $sortTimestamp,
                    'started_timestamp'  => $updated ?? $sortTimestamp,
                ];
            }
        }

        return $fallback;
    }

    private static function count_outages(array $providers): int
    {
        $recent_cutoff = time() - (2 * DAY_IN_SECONDS);
        $count = 0;

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $incidents = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];

            foreach ($incidents as $incident) {
                if (!is_array($incident)) {
                    continue;
                }

                if (!self::is_current_official_incident($incident)) {
                    continue;
                }

                $sortTimestamp = self::incident_timestamp($incident);
                if (null === $sortTimestamp || $sortTimestamp < $recent_cutoff) {
                    continue;
                }

                $count++;
            }
        }

        return $count;
    }

    private static function count_signals(array $providers): int
    {
        $recent_cutoff = time() - (4 * HOUR_IN_SECONDS);
        $eligibleFallbackStates = ['degraded', 'outage', 'major', 'partial', 'monitoring', 'investigating'];
        $count = 0;

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $tileKind = strtolower((string) ($provider['tile_kind'] ?? $provider['tileKind'] ?? ''));
            $status = strtolower((string) ($provider['stateCode'] ?? $provider['status'] ?? 'unknown'));
            $isSignal = $tileKind === 'signal' || in_array($status, $eligibleFallbackStates, true);
            if (!$isSignal) {
                continue;
            }

            $incidents = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];
            if (self::has_unresolved_incident($incidents)) {
                continue;
            }

            $updated = self::parse_time($provider['updatedAt'] ?? $provider['updated_at'] ?? null);
            if ($updated && $updated < $recent_cutoff) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private static function has_unresolved_incident(array $incidents): bool
    {
        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                continue;
            }

            if (self::is_unresolved_incident($incident)) {
                return true;
            }
        }

        return false;
    }

    private static function is_unresolved_incident(array $incident): bool
    {
        if (!empty($incident['resolved_at']) || !empty($incident['resolvedAt'])) {
            return false;
        }

        $resolvedValues = ['resolved', 'completed', 'postmortem', 'operational', 'ok', 'none'];
        foreach (['status', 'eta'] as $field) {
            $value = strtolower(trim((string) ($incident[$field] ?? '')));
            if ('' !== $value && in_array($value, $resolvedValues, true)) {
                return false;
            }
        }

        return true;
    }

    private static function incident_timestamp(array $incident): ?int
    {
        // Effective time for sorting incidents (generally the last update time).
        $candidates = [
            $incident['timestamp'] ?? null,
            $incident['updated_at'] ?? null,
            $incident['updatedAt'] ?? null,
            $incident['detected_at'] ?? null,
            $incident['detectedAt'] ?? null,
            $incident['started_at'] ?? null,
            $incident['startedAt'] ?? null,
        ];

        foreach ($candidates as $value) {
            $parsed = self::parse_time($value);
            if (null !== $parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private static function incident_started_timestamp(array $incident): ?int
    {
        // Prefer the earliest plausible "start" field.
        $candidates = [
            $incident['detected_at'] ?? null,
            $incident['detectedAt'] ?? null,
            $incident['started_at'] ?? null,
            $incident['startedAt'] ?? null,
            $incident['timestamp'] ?? null,
            $incident['updated_at'] ?? null,
            $incident['updatedAt'] ?? null,
        ];

        foreach ($candidates as $value) {
            $parsed = self::parse_time($value);
            if (null !== $parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private static function incident_start_iso(array $incident, int $timestamp): string
    {
        $fields = [
            $incident['detected_at'] ?? null,
            $incident['detectedAt'] ?? null,
            $incident['started_at'] ?? null,
            $incident['startedAt'] ?? null,
            $incident['updated_at'] ?? null,
            $incident['updatedAt'] ?? null,
        ];

        foreach ($fields as $field) {
            if (is_string($field) && '' !== trim($field)) {
                return (string) $field;
            }

            if (is_numeric($field)) {
                $ts = self::parse_time($field);
                if ($ts) {
                    return gmdate('c', $ts);
                }
            }
        }

        return gmdate('c', $timestamp);
    }

    private static function parse_time($value): ?int {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;
            return $int > 0 ? $int : null;
        }

        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if (false === $timestamp) {
            return null;
        }
        return $timestamp;
    }

    private static function relative_time_from_timestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        $now = current_time('timestamp', true);
        if (!is_int($now)) {
            $now = time();
        }

        if ($timestamp > $now) {
            $timestamp = $now;
        }

        $diff = human_time_diff($timestamp, $now);
        return $diff ? sprintf('%s ago', $diff) : '';
    }
}
