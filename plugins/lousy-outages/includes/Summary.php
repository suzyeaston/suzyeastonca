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

    private static function providers(): array
    {
        $providers = [];

        if (function_exists('lousy_outages_get_snapshot')) {
            $snapshot = \lousy_outages_get_snapshot(false);
            if (empty($snapshot['providers'])) {
                $snapshot = \lousy_outages_get_snapshot(true);
            }

            if (!empty($snapshot['providers']) && is_array($snapshot['providers'])) {
                $providers = array_values($snapshot['providers']);
            }
        }

        if ($providers) {
            return $providers;
        }

        $store  = new Store();
        $states = $store->get_all();
        if (!$states) {
            return [];
        }

        $timestamp = get_option('lousy_outages_last_poll');
        if (!$timestamp) {
            $timestamp = gmdate('c');
        }

        foreach ($states as $id => $state) {
            if (!is_array($state)) {
                continue;
            }

            if (function_exists('lousy_outages_build_provider_payload')) {
                $providers[] = \lousy_outages_build_provider_payload((string) $id, $state, (string) $timestamp);
                continue;
            }

            $providers[] = [
                'id'        => (string) $id,
                'provider'  => (string) ($state['provider'] ?? $state['name'] ?? $id),
                'name'      => (string) ($state['name'] ?? $state['provider'] ?? $id),
                'stateCode' => (string) ($state['status'] ?? 'unknown'),
                'updatedAt' => (string) ($state['updated_at'] ?? $state['updatedAt'] ?? $timestamp),
                'incidents' => isset($state['incidents']) && is_array($state['incidents']) ? $state['incidents'] : [],
                'summary'   => (string) ($state['summary'] ?? ''),
            ];
        }

        return $providers;
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

                if (!self::is_unresolved_incident($incident)) {
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

                if (!self::is_unresolved_incident($incident)) {
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
        $statusCode = strtolower((string) ($incident['impact'] ?? $incident['status'] ?? ''));
        if (in_array($statusCode, ['operational', 'resolved', 'maintenance'], true)) {
            return false;
        }

        if (!empty($incident['resolved_at']) || !empty($incident['resolvedAt'])) {
            return false;
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
