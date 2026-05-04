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

    $state = ('ok' === $overall && empty($incidents)) ? 'operational' : 'degraded';

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
    $resolve_status = static function (string $title, string $summary): string {
        $text = strtolower($title . ' ' . $summary);
        if (preg_match('/\bresolved\b|this issue is now resolved|issue is now resolved|issue has been resolved|ended at/i', $text)) {
            return 'resolved';
        }
        if (preg_match('/\b(scheduled|planned).{0,40}maintenance\b/i', $text)) {
            return 'maintenance';
        }
        if (preg_match('/\b(outage|major|critical)\b/i', $text)) {
            return 'major';
        }
        if (preg_match('/\b(degraded|partial|service disruption)\b/i', $text)) {
            return 'degraded';
        }
        if (preg_match('/\b(investigating|identified|monitoring)\b/i', $text)) {
            return 'investigating';
        }
        return 'unknown';
    };
    if (isset($feed->channel)) {
        foreach ($feed->channel->item as $item) {
            $title = (string) ($item->title ?? 'Incident');
            $summary = (string) ($item->description ?? '');
            $items[] = [
                'name'       => $title ?: 'Incident',
                'status'     => $resolve_status($title, $summary),
                'started_at' => (string) ($item->pubDate ?? ''),
                'updated_at' => (string) ($item->pubDate ?? ''),
                'shortlink'  => (string) ($item->link ?? ''),
            ];
        }
    } else {
        foreach ($feed->entry as $item) {
            $title = (string) ($item->title ?? 'Incident');
            $summary = (string) ($item->summary ?? $item->content ?? '');
            $items[] = [
                'name'       => $title ?: 'Incident',
                'status'     => $resolve_status($title, $summary),
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

    $resolved_states = ['resolved', 'completed', 'postmortem', 'operational', 'ok'];
    $hasMajor = false;
    $hasDegraded = false;
    $hasMaintenance = false;

    foreach ($items as $item) {
        $status = strtolower((string) ($item['status'] ?? ''));
        if (in_array($status, $resolved_states, true)) {
            continue;
        }
        if (in_array($status, ['major', 'outage', 'critical'], true)) {
            $hasMajor = true;
            continue;
        }
        if (in_array($status, ['degraded', 'partial', 'investigating', 'identified', 'monitoring'], true)) {
            $hasDegraded = true;
            continue;
        }
        if (in_array($status, ['maintenance', 'scheduled'], true)) {
            $hasMaintenance = true;
        }
    }

    $state = 'operational';
    if ($hasMajor) {
        $state = 'major';
    } elseif ($hasDegraded) {
        $state = 'degraded';
    } elseif ($hasMaintenance) {
        $state = 'maintenance';
    }

    return [
        'state'      => $state,
        'incidents'  => $items,
        'updated_at' => $items[0]['updated_at'] ?? null,
        'raw'        => $xml,
    ];
}

function from_gcp_incidents_json(string $json): array {
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['state' => 'unknown', 'incidents' => [], 'raw' => null];
    }

    $incidentsRaw = $data['incidents'] ?? null;
    if (!is_array($incidentsRaw)) {
        return ['state' => 'unknown', 'incidents' => [], 'raw' => $data];
    }

    $activeIncidents = [];
    $hasDisruption = false;
    foreach ($incidentsRaw as $incident) {
        if (!is_array($incident)) {
            continue;
        }
        $end = $incident['end'] ?? null;
        if (!empty($end)) {
            continue;
        }

        $updates = isset($incident['updates']) && is_array($incident['updates']) ? $incident['updates'] : [];
        $mostRecent = isset($incident['most_recent_update']) && is_array($incident['most_recent_update'])
            ? $incident['most_recent_update']
            : null;
        if (!$mostRecent && !empty($updates)) {
            $mostRecent = end($updates);
            if (!is_array($mostRecent)) {
                $mostRecent = null;
            }
        }

        $updateStatus = strtoupper((string) ($mostRecent['status'] ?? ''));
        if ('SERVICE_DISRUPTION' === $updateStatus) {
            $hasDisruption = true;
        }

        $incidentStatus = 'investigating';
        if ('SERVICE_DISRUPTION' === $updateStatus) {
            $incidentStatus = 'identified';
        } elseif ('SERVICE_INFORMATION' === $updateStatus) {
            $incidentStatus = 'monitoring';
        } elseif ('AVAILABLE' === $updateStatus) {
            $incidentStatus = 'monitoring';
        }

        $name = (string) ($incident['external_desc'] ?? $incident['service_name'] ?? 'Google Cloud incident');
        $activeIncidents[] = [
            'id'         => (string) ($incident['id'] ?? ''),
            'name'       => $name ?: 'Google Cloud incident',
            'status'     => $incidentStatus,
            'started_at' => $incident['begin'] ?? null,
            'updated_at' => $mostRecent['when'] ?? ($incident['modified'] ?? null),
            'shortlink'  => $incident['status_url'] ?? null,
        ];
    }

    $state = 'operational';
    if (!empty($activeIncidents)) {
        $state = $hasDisruption ? 'major' : 'degraded';
    }

    $updatedAt = null;
    if (!empty($activeIncidents)) {
        $updatedAt = $activeIncidents[0]['updated_at'] ?? null;
    }

    return [
        'state'      => $state,
        'incidents'  => $activeIncidents,
        'updated_at' => $updatedAt,
        'raw'        => $data,
    ];
}
