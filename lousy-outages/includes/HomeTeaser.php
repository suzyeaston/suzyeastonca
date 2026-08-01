<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Board as Board;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Homepage widget payload — same lanes and verdict as the public board.
 */
final class HomeTeaser
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?array $state = null): array
    {
        if ($state === null) {
            $state = function_exists('lousy_outages_get_current_state') ? \lousy_outages_get_current_state() : [];
        }
        if (!is_array($state)) {
            $state = [];
        }

        $dashboard = home_url('/lousy-outages/');
        $providers = Board\provider_rows($state);
        $verdict = Board\verdict($state);

        $counts = [
            'down'      => count(array_filter($providers, static fn(array $row): bool => 'outage' === $row['state'])),
            'degraded'  => count(array_filter($providers, static fn(array $row): bool => in_array($row['state'], ['degraded', 'maintenance'], true))),
            'advisory'  => count(array_filter($providers, static fn(array $row): bool => 'advisory' === $row['state'])),
            'unknown'   => count(array_filter($providers, static fn(array $row): bool => 'unknown' === $row['state'])),
            'up'        => count(array_filter($providers, static fn(array $row): bool => 'operational' === $row['state'])),
            'tracked'   => count($providers),
        ];

        $lead = self::pick_lead($state, $dashboard);
        $also = self::also_watching($state, $lead, $dashboard);

        $fetched_raw = (string) ($state['fetched_at'] ?? '');
        $fetched_ts = Board\timestamp_from($fetched_raw);

        return [
            'dashboard_url'   => $dashboard,
            'verdict_line'    => (string) ($verdict['line'] ?? 'ALL CLEAR'),
            'verdict_sub'     => (string) ($verdict['sub'] ?? ''),
            'tone'            => (string) ($verdict['tone'] ?? 'ok'),
            'counts'          => $counts,
            'lead'            => $lead,
            'also'            => $also,
            'fetched_at'      => $fetched_raw,
            'fetched_label'   => $fetched_ts > 0 ? Board\relative_time($fetched_ts) : '',
            'urls'            => [
                'active'     => $dashboard . '#active',
                'degraded'   => $dashboard . '#degraded',
                'advisories' => $dashboard . '#advisories',
                'matrix'     => $dashboard . '#providers',
                'full'       => $dashboard,
            ],
            'delayed'         => !empty($state['errors']) && $counts['down'] === 0 && $counts['degraded'] === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pick_lead(array $state, string $dashboard): array
    {
        $outages = array_values(array_filter((array) ($state['outages'] ?? []), 'is_array'));
        if ($outages) {
            return self::lead_from_incident(Board\incident_view($outages[0]), 'down', $dashboard, '#active');
        }

        $signals = array_values(array_filter((array) ($state['signals'] ?? []), 'is_array'));
        if ($signals) {
            return self::lead_from_signal($signals[0], $dashboard);
        }

        $advisories = array_values(array_filter((array) ($state['long_running'] ?? []), 'is_array'));
        if ($advisories) {
            return self::lead_from_incident(Board\incident_view($advisories[0]), 'advisory', $dashboard, '#advisories');
        }

        $unverified = array_values(array_filter((array) ($state['unverified'] ?? []), 'is_array'));
        if ($unverified) {
            return self::lead_from_unverified($unverified[0], $dashboard);
        }

        return [
            'kind'         => 'clear',
            'title'        => 'Nothing on fire.',
            'summary'      => (string) (Board\verdict($state)['sub'] ?? 'All monitored providers answered up.'),
            'provider'     => '',
            'provider_id'  => '',
            'label'        => 'ALL CLEAR',
            'url'          => $dashboard,
            'section_url'  => $dashboard,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function also_watching(array $state, array $lead, string $dashboard): array
    {
        $items = [];
        $lead_id = sanitize_key((string) ($lead['provider_id'] ?? ''));

        foreach (array_values(array_filter((array) ($state['long_running'] ?? []), 'is_array')) as $incident) {
            $view = Board\incident_view($incident);
            $pid = sanitize_key((string) ($view['provider_id'] ?? ''));
            if ($pid === $lead_id) {
                continue;
            }
            $items[] = [
                'label' => (string) ($view['provider'] ?: $pid),
                'title' => (string) $view['title'],
                'tone'  => 'advisory',
                'url'   => $dashboard . '#advisories',
            ];
        }

        foreach (array_values(array_filter((array) ($state['signals'] ?? []), 'is_array')) as $provider) {
            $pid = sanitize_key((string) ($provider['provider_id'] ?? $provider['id'] ?? ''));
            if ($pid === $lead_id) {
                continue;
            }
            $name = (string) ($provider['name'] ?? $provider['provider'] ?? $pid);
            $items[] = [
                'label' => $name,
                'title' => Board\tidy((string) ($provider['summary'] ?? ''), 100),
                'tone'  => 'warn',
                'url'   => $dashboard . '#provider-' . $pid,
            ];
        }

        return array_slice($items, 0, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private static function lead_from_incident(array $view, string $tone, string $dashboard, string $section_hash): array
    {
        $pid = sanitize_key((string) ($view['provider_id'] ?? ''));
        $provider = (string) ($view['provider'] ?? $pid);
        $url = '' !== (string) ($view['url'] ?? '') ? (string) $view['url'] : $dashboard . '#provider-' . $pid;

        return [
            'kind'         => $tone,
            'title'        => (string) $view['title'],
            'summary'      => (string) ($view['summary'] ?: $view['lifecycle']),
            'provider'     => $provider,
            'provider_id'  => $pid,
            'label'        => strtoupper($tone === 'down' ? 'DOWN' : 'ADVISORY'),
            'scope'        => (string) ($view['scope'] ?? ''),
            'url'          => $url,
            'section_url'  => $dashboard . $section_hash,
            'updated_label' => $view['updated_ts'] > 0 ? Board\relative_time((int) $view['updated_ts']) : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lead_from_signal(array $provider, string $dashboard): array
    {
        $pid = sanitize_key((string) ($provider['provider_id'] ?? $provider['id'] ?? ''));
        $name = (string) ($provider['name'] ?? $provider['provider'] ?? $pid);
        $summary = Board\tidy((string) ($provider['summary'] ?? $provider['message'] ?? ''), 220);

        return [
            'kind'         => 'warn',
            'title'        => $name . ' — yellow status, no incident filed',
            'summary'      => $summary ?: 'Status page says something is off. Nobody wrote it up.',
            'provider'     => $name,
            'provider_id'  => $pid,
            'label'        => 'DEGRADED',
            'scope'        => '',
            'url'          => (string) ($provider['url'] ?? $dashboard . '#provider-' . $pid),
            'section_url'  => $dashboard . '#degraded',
            'updated_label' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lead_from_unverified(array $provider, string $dashboard): array
    {
        $pid = sanitize_key((string) ($provider['provider_id'] ?? $provider['id'] ?? ''));
        $name = (string) ($provider['name'] ?? $provider['provider'] ?? $pid);

        return [
            'kind'         => 'unknown',
            'title'        => $name . ' — no answer',
            'summary'      => Board\tidy((string) ($provider['fetch_error'] ?? $provider['stale_label'] ?? ''), 200),
            'provider'     => $name,
            'provider_id'  => $pid,
            'label'        => 'NO ANSWER',
            'scope'        => '',
            'url'          => $dashboard . '#providers',
            'section_url'  => $dashboard . '#providers',
            'updated_label' => '',
        ];
    }
}
