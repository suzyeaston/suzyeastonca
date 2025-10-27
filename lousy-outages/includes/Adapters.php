<?php
declare(strict_types=1);

namespace Lousy\Adapters;

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
    if (isset($feed->channel)) {
        foreach ($feed->channel->item as $item) {
            $items[] = [
                'name'       => (string) ($item->title ?? 'Incident'),
                'status'     => 'reported',
                'started_at' => (string) ($item->pubDate ?? ''),
                'updated_at' => (string) ($item->pubDate ?? ''),
                'shortlink'  => (string) ($item->link ?? ''),
            ];
        }
    } else {
        foreach ($feed->entry as $item) {
            $items[] = [
                'name'       => (string) ($item->title ?? 'Incident'),
                'status'     => 'reported',
                'started_at' => (string) ($item->updated ?? ($item->published ?? '')),
                'updated_at' => (string) ($item->updated ?? ($item->published ?? '')),
                'shortlink'  => (string) ($item->link['href'] ?? ($item->link ?? '')),
            ];
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $items = array_slice($items, 0, 10);

    $recent_threshold = strtotime('-48 hours');
    $state            = 'operational';
    foreach ($items as $item) {
        $ts = strtotime($item['started_at']);
        if ($ts && $ts >= $recent_threshold) {
            $state = 'degraded';
            break;
        }
    }

    return [
        'state'      => $state,
        'incidents'  => $items,
        'updated_at' => $items[0]['updated_at'] ?? null,
        'raw'        => $feed,
    ];
}
