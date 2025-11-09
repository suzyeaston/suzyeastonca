<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Summary {
    public static function current(): array {
        $store  = new Store();
        $states = $store->get_all();
        $latest = null;
        $fallback = null;

        foreach ($states as $id => $state) {
            if (!is_array($state)) {
                continue;
            }
            $status = strtolower((string) ($state['status'] ?? 'unknown'));
            $provider_name = (string) ($state['name'] ?? $state['provider'] ?? $id);
            $incidents = isset($state['incidents']) && is_array($state['incidents']) ? $state['incidents'] : [];

            foreach ($incidents as $incident) {
                if (!is_array($incident)) {
                    continue;
                }
                $updated = self::parse_time($incident['updated_at'] ?? $incident['updatedAt'] ?? $incident['started_at'] ?? $incident['startedAt'] ?? null);
                if (null === $latest || $updated > $latest['timestamp']) {
                    $latest = [
                        'provider_id'   => $id,
                        'provider_name' => $provider_name,
                        'title'         => (string) ($incident['title'] ?? 'Incident'),
                        'summary'       => (string) ($incident['summary'] ?? ''),
                        'status'        => ucfirst((string) ($incident['impact'] ?? 'incident')),
                        'started_at'    => (string) ($incident['started_at'] ?? $incident['startedAt'] ?? ''),
                        'updated_at'    => (string) ($incident['updated_at'] ?? $incident['updatedAt'] ?? ''),
                        'timestamp'     => $updated,
                    ];
                }
            }

            if ('operational' !== $status && null === $latest) {
                $updated = self::parse_time($state['updated_at'] ?? $state['updatedAt'] ?? null);
                if (null === $fallback || $updated > $fallback['timestamp']) {
                    $fallback = [
                        'provider_id'   => $id,
                        'provider_name' => $provider_name,
                        'title'         => (string) ($state['summary'] ?? Fetcher::status_label($status)),
                        'status'        => Fetcher::status_label($status),
                        'started_at'    => (string) ($state['updated_at'] ?? $state['updatedAt'] ?? ''),
                        'timestamp'     => $updated,
                    ];
                }
            }
        }

        if ($latest) {
            return [
                'hasIncident' => true,
                'providerId'  => $latest['provider_id'],
                'provider'    => $latest['provider_name'],
                'title'       => $latest['title'],
                'status'      => $latest['status'],
                'relative'    => self::relative_time($latest['started_at'] ?: $latest['updated_at']),
                'started_at'  => $latest['started_at'],
            ];
        }

        if ($fallback) {
            return [
                'hasIncident' => true,
                'providerId'  => $fallback['provider_id'],
                'provider'    => $fallback['provider_name'],
                'title'       => $fallback['title'],
                'status'      => $fallback['status'],
                'relative'    => self::relative_time($fallback['started_at']),
                'started_at'  => $fallback['started_at'],
            ];
        }

        return [
            'hasIncident' => false,
            'providerId'  => '',
            'provider'    => '',
            'title'       => 'All systems operational',
            'status'      => 'Operational',
            'relative'    => '',
            'started_at'  => '',
        ];
    }

    private static function parse_time($value): ?int {
        if (empty($value)) {
            return null;
        }
        $timestamp = strtotime((string) $value);
        if (false === $timestamp) {
            return null;
        }
        return $timestamp;
    }

    private static function relative_time(?string $iso): string {
        if (empty($iso)) {
            return '';
        }
        $timestamp = strtotime($iso);
        if (false === $timestamp) {
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
