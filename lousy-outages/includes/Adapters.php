<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Adapters;

function from_statuspage_summary(string $json): array {
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['state' => 'unknown', 'incidents' => [], 'raw' => null];
    }

    $indicator = strtolower((string) ($data['status']['indicator'] ?? 'none'));
    $map       = [
        'none'        => 'operational',
        'minor'       => 'degraded',
        'major'       => 'major',
        'critical'    => 'major',
        'maintenance' => 'maintenance',
    ];
    $state = $map[$indicator] ?? 'unknown';

    $incidents = [];
    foreach ($data['incidents'] ?? [] as $incident) {
        if (!is_array($incident)) {
            continue;
        }
        $status = strtolower((string) ($incident['status'] ?? ''));
        if (in_array($status, ['resolved', 'completed', 'postmortem'], true)) {
            continue;
        }

        $incidents[] = [
            'id'         => (string) ($incident['id'] ?? ''),
            'name'       => (string) ($incident['name'] ?? 'Incident'),
            'status'     => $status ?: 'investigating',
            'started_at' => $incident['started_at'] ?? ($incident['created_at'] ?? null),
            'updated_at' => $incident['updated_at'] ?? null,
            'shortlink'  => $incident['shortlink'] ?? null,
        ];
    }

    return [
        'state'      => $state,
        'incidents'  => $incidents,
        'updated_at' => $data['page']['updated_at'] ?? null,
        'raw'        => $data,
    ];
}

function from_statuspage_status(string $json): array {
    $data = json_decode($json, true);
    if (! is_array($data)) {
        return ['state' => 'unknown', 'incidents' => [], 'raw' => null];
    }

    $indicator = strtolower((string) ($data['status']['indicator'] ?? 'unknown'));
    $map       = [
        'none'        => 'operational',
        'minor'       => 'degraded',
        'minor_outage'=> 'degraded',
        'partial'     => 'degraded',
        'degraded'    => 'degraded',
        'major'       => 'major',
        'critical'    => 'major',
        'maintenance' => 'maintenance',
    ];

    $state = $map[$indicator] ?? 'unknown';

    return [
        'state'      => $state,
        'incidents'  => [],
        'updated_at' => $data['page']['updated_at'] ?? null,
        'summary'    => $data['status']['description'] ?? '',
        'raw'        => $data,
    ];
}

function from_slack_current(string $json): array {
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['state' => 'unknown', 'incidents' => [], 'raw' => null];
    }

    $overall = strtolower((string) ($data['status'] ?? 'unknown'));
    $state   = ('ok' === $overall) ? 'operational' : 'degraded';

    $incidents = [];
    foreach ($data['active_incidents'] ?? [] as $incident) {
        if (!is_array($incident)) {
            continue;
        }
        $status = strtolower((string) ($incident['status'] ?? 'active'));
        if (in_array($status, ['resolved', 'completed'], true)) {
            continue;
        }

        $incidents[] = [
            'id'         => (string) ($incident['id'] ?? ''),
            'name'       => (string) ($incident['title'] ?? 'Incident'),
            'status'     => $status ?: 'active',
            'started_at' => $incident['created'] ?? null,
            'updated_at' => $incident['updated'] ?? null,
            'shortlink'  => $incident['url'] ?? null,
        ];
    }

    return [
        'state'      => $state,
        'incidents'  => $incidents,
        'updated_at' => $data['last_updated'] ?? ($data['date'] ?? null),
        'raw'        => $data,
    ];
}

function from_rss_atom(string $xml): array {
    $previous = libxml_use_internal_errors(true);
    $feed     = simplexml_load_string($xml);
    if (! $feed) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return ['state' => 'operational', 'incidents' => [], 'raw' => null];
    }

    $items = [];
    $resolve_status = static function (string $title): string {
        $upper = strtoupper($title);
        if (false !== strpos($upper, 'RESOLVED')) {
            return 'resolved';
        }
        if (false !== strpos($upper, 'OUTAGE')) {
            return 'major';
        }
        if (false !== strpos($upper, 'SERVICE DISRUPTION')
            || false !== strpos($upper, 'DEGRADED')
            || false !== strpos($upper, 'INCIDENT')) {
            return 'degraded';
        }
        return 'investigating';
    };
    if (isset($feed->channel)) {
        foreach ($feed->channel->item as $item) {
            $title = (string) ($item->title ?? 'Incident');
            $items[] = [
                'name'       => $title ?: 'Incident',
                'status'     => $resolve_status($title),
                'started_at' => (string) ($item->pubDate ?? ''),
                'updated_at' => (string) ($item->pubDate ?? ''),
                'shortlink'  => (string) ($item->link ?? ''),
            ];
        }
    } else {
        foreach ($feed->entry as $item) {
            $title = (string) ($item->title ?? 'Incident');
            $items[] = [
                'name'       => $title ?: 'Incident',
                'status'     => $resolve_status($title),
                'started_at' => (string) ($item->updated ?? ($item->published ?? '')),
                'updated_at' => (string) ($item->updated ?? ($item->published ?? '')),
                'shortlink'  => (string) ($item->link['href'] ?? ($item->link ?? '')),
            ];
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $items = array_map(
        static function (array $item): array {
            $timestamp = 0;
            if (! empty($item['updated_at'])) {
                $timestamp = strtotime((string) $item['updated_at']) ?: 0;
            }
            if (! $timestamp && ! empty($item['started_at'])) {
                $timestamp = strtotime((string) $item['started_at']) ?: 0;
            }
            $item['timestamp'] = $timestamp;
            return $item;
        },
        $items
    );

    usort(
        $items,
        static function (array $a, array $b): int {
            return (int) ($b['timestamp'] ?? 0) <=> (int) ($a['timestamp'] ?? 0);
        }
    );

    $items = array_slice($items, 0, 10);

    $severity = [
        'major'         => 3,
        'outage'        => 3,
        'degraded'      => 2,
        'investigating' => 1,
        'reported'      => 1,
    ];
    $resolved_states = ['resolved', 'completed', 'postmortem'];
    $worst = 0;
    foreach ($items as $item) {
        $status = strtolower((string) ($item['status'] ?? ''));
        if (in_array($status, $resolved_states, true)) {
            continue;
        }
        $worst = max($worst, $severity[$status] ?? 1);
    }

    $state = 'operational';
    if ($worst >= 3) {
        $state = 'major';
    } elseif ($worst >= 1) {
        $state = 'degraded';
    }

    return [
        'state'      => $state,
        'incidents'  => $items,
        'updated_at' => $items[0]['updated_at'] ?? null,
        'raw'        => $xml,
    ];
}
