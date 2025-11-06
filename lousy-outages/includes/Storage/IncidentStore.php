<?php
declare(strict_types=1);

namespace LousyOutages\Storage;

use LousyOutages\Model\Incident;

class IncidentStore
{
    private const OPTION_ALERTS = 'lousy_outages_alerts_sent';
    private const OPTION_INCIDENTS = 'lousy_outages_incidents';
    private const OPTION_PROVIDER_CAP_PREFIX = 'lousy_outages_cap_';
    private const OPTION_LAST_DIGEST = 'lousy_outages_daily_digest_last_sent';
    private const DAILY_CAP = 5;

    /**
     * Determine whether an email for the given incident should be sent.
     */
    public function shouldSend(Incident $incident): bool
    {
        $alerts = $this->getAlerts();
        $key    = $this->buildKey($incident);
        $now    = time();

        $capKey = $this->providerCapKey($incident->provider);
        $capCount = (int) get_option($capKey, 0);
        if ($capCount >= self::DAILY_CAP && ! $incident->isResolved()) {
            return false;
        }

        $allow = false;

        if (! isset($alerts[$key])) {
            $allow = true;
        } else {
            $lastStatus = isset($alerts[$key]['status']) ? (string) $alerts[$key]['status'] : '';
            if ($incident->isResolved()) {
                $allow = true;
            } elseif ('' !== $lastStatus && $this->isSeverityUpgrade($lastStatus, $incident->status)) {
                $allow = true;
            } else {
                return false;
            }
        }

        if ($allow) {
            $alerts[$key] = [
                'ts'     => $now,
                'status' => $incident->status,
            ];
            update_option(self::OPTION_ALERTS, $alerts, false);
            update_option($capKey, $capCount + 1, false);
        }

        return $allow;
    }

    /**
     * Persist the most recent incident snapshot.
     *
     * @param Incident[] $incidents
     */
    public function persistIncidents(array $incidents): void
    {
        $payload = [];

        foreach ($incidents as $incident) {
            if (! $incident instanceof Incident) {
                continue;
            }

            $payload[] = [
                'provider'    => $incident->provider,
                'id'          => $incident->id,
                'title'       => $incident->title,
                'status'      => $incident->status,
                'url'         => $incident->url,
                'component'   => $incident->component,
                'impact'      => $incident->impact,
                'detected_at' => $incident->detected_at,
                'resolved_at' => $incident->resolved_at,
                'updated_at'  => gmdate('c'),
            ];
        }

        update_option(self::OPTION_INCIDENTS, $payload, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStoredIncidents(): array
    {
        $stored = get_option(self::OPTION_INCIDENTS, []);
        if (! is_array($stored)) {
            return [];
        }
        return $stored;
    }

    private function providerCapKey(string $provider): string
    {
        return self::OPTION_PROVIDER_CAP_PREFIX . sanitize_key($provider) . '_' . gmdate('Ymd');
    }

    private function buildKey(Incident $incident): string
    {
        return $incident->provider . '|' . $incident->id;
    }

    public function keyFor(Incident $incident): string
    {
        return $this->buildKey($incident);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getAlerts(): array
    {
        $alerts = get_option(self::OPTION_ALERTS, []);
        if (! is_array($alerts)) {
            return [];
        }
        return $alerts;
    }

    public function getLastDigestSent(): string
    {
        $value = get_option(self::OPTION_LAST_DIGEST, '');
        return is_string($value) ? (string) $value : '';
    }

    public function updateLastDigestSent(string $iso): void
    {
        update_option(self::OPTION_LAST_DIGEST, $iso, false);
    }

    private function isSeverityUpgrade(string $previous, string $current): bool
    {
        $previousRank = $this->severityRank($previous);
        $currentRank  = $this->severityRank($current);

        return $currentRank > $previousRank;
    }

    private function severityRank(string $status): int
    {
        $map = [
            'operational'    => 0,
            'none'           => 0,
            'informational'  => 0,
            'maintenance'    => 1,
            'minor'          => 1,
            'degraded'       => 1,
            'partial_outage' => 2,
            'major_outage'   => 3,
            'outage'         => 3,
            'critical'       => 4,
            'resolved'       => 0,
        ];

        $key = strtolower(trim($status));

        if (isset($map[$key])) {
            return (int) $map[$key];
        }

        return 0;
    }
}
