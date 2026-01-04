<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

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
