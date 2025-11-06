<?php
declare(strict_types=1);

namespace LousyOutages\Sources;

use LousyOutages\Model\Incident;

class StatuspageSource
{
    private string $provider;
    private string $summaryUrl;
    private string $statusUrl;

    public function __construct(string $provider, string $summaryUrl, string $statusUrl)
    {
        $this->provider  = $provider;
        $this->summaryUrl = $summaryUrl;
        $this->statusUrl = rtrim($statusUrl, '/');
    }

    /**
     * @return array{status: string, incidents: Incident[], updated: int}
     */
    public function fetch(): array
    {
        $response = wp_remote_get($this->summaryUrl, [
            'timeout' => 12,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'LousyOutages/2.0 (+https://suzyeaston.ca)',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'status'    => 'unknown',
                'incidents' => [],
                'updated'   => time(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode((string) $body, true);
        if (! is_array($data)) {
            return [
                'status'    => 'unknown',
                'incidents' => [],
                'updated'   => time(),
            ];
        }

        $indicator = strtolower((string) ($data['status']['indicator'] ?? 'none'));
        $status    = $this->mapIndicator($indicator);
        $updated   = isset($data['page']['updated_at']) ? strtotime((string) $data['page']['updated_at']) : time();
        if (! $updated) {
            $updated = time();
        }

        $incidents = [];
        foreach ($data['incidents'] ?? [] as $incident) {
            if (! is_array($incident)) {
                continue;
            }
            $incidents[] = $this->transformIncident($incident);
        }

        return [
            'status'    => $status,
            'incidents' => array_filter($incidents),
            'updated'   => $updated,
        ];
    }

    private function transformIncident(array $incident): ?Incident
    {
        $rawStatus = strtolower((string) ($incident['status'] ?? '')); 
        $impact    = strtolower((string) ($incident['impact'] ?? 'none'));
        $id        = (string) ($incident['id'] ?? md5(wp_json_encode($incident)));
        $title     = $this->resolveTitle($incident);
        $url       = (string) ($incident['shortlink'] ?? ($this->statusUrl ? $this->statusUrl . '/incidents/' . $id : ''));
        $component = null;
        if (! empty($incident['components']) && is_array($incident['components'])) {
            $names = array_map(static function ($component) {
                if (is_array($component) && ! empty($component['name'])) {
                    return (string) $component['name'];
                }
                return null;
            }, $incident['components']);
            $names = array_filter($names);
            if ($names) {
                $component = implode(', ', $names);
            }
        }

        $detectedAt = $this->timestamp($incident['started_at'] ?? $incident['created_at'] ?? $incident['scheduled_for'] ?? null);
        if (! $detectedAt) {
            $detectedAt = time();
        }
        $resolvedAt = null;
        $status     = $this->mapIncidentStatus($rawStatus, $impact);
        if ('resolved' === $status) {
            $resolvedAt = $this->timestamp($incident['resolved_at'] ?? $incident['updated_at'] ?? null);
            if (! $resolvedAt) {
                $resolvedAt = time();
            }
        }

        return new Incident(
            $this->provider,
            $id,
            $title,
            $status,
            $url,
            $component,
            $impact ?: null,
            $detectedAt,
            $resolvedAt
        );
    }

    private function resolveTitle(array $incident): string
    {
        $title = isset($incident['name']) ? (string) $incident['name'] : '';
        if ('' !== trim($title)) {
            return $title;
        }

        $updates = isset($incident['incident_updates']) && is_array($incident['incident_updates'])
            ? $incident['incident_updates']
            : [];
        if ($updates) {
            usort($updates, static function ($a, $b) {
                $tsA = isset($a['updated_at']) ? strtotime((string) $a['updated_at']) : 0;
                $tsB = isset($b['updated_at']) ? strtotime((string) $b['updated_at']) : 0;
                return $tsB <=> $tsA;
            });
            foreach ($updates as $update) {
                if (! is_array($update)) {
                    continue;
                }
                $body = isset($update['body']) ? trim((string) $update['body']) : '';
                if ('' !== $body) {
                    return $body;
                }
            }
        }

        return 'Incident';
    }

    private function mapIndicator(string $indicator): string
    {
        switch ($indicator) {
            case 'none':
                return 'operational';
            case 'maintenance':
                return 'maintenance';
            case 'minor':
                return 'degraded';
            case 'major':
                return 'partial_outage';
            case 'critical':
                return 'major_outage';
            default:
                return 'degraded';
        }
    }

    private function mapIncidentStatus(string $status, string $impact): string
    {
        $status = strtolower($status);
        if (in_array($status, ['resolved', 'completed', 'postmortem'], true)) {
            return 'resolved';
        }

        $impact = strtolower($impact);
        switch ($impact) {
            case 'minor':
                return 'degraded';
            case 'major':
                return 'partial_outage';
            case 'critical':
                return 'major_outage';
            case 'maintenance':
                return 'maintenance';
            default:
                return 'degraded';
        }
    }

    private function timestamp($value): ?int
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        $string = is_numeric($value) ? (string) $value : (string) $value;
        if ('' === trim($string)) {
            return null;
        }
        if (preg_match('/^\d+$/', $string)) {
            return (int) $string;
        }
        $time = strtotime($string);
        if (false === $time) {
            return null;
        }
        return $time;
    }
}
