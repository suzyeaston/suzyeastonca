<?php
declare(strict_types=1);

namespace LousyOutages\Storage;

use LousyOutages\Model\Incident;

class IncidentStore
{
    private const OPTION_ALERTS = 'lousy_outages_alerts_sent';
    private const OPTION_INCIDENTS = 'lousy_outages_incidents';
    private const OPTION_PROVIDER_CAP_PREFIX = 'lousy_outages_cap_';
    private const DIGEST_TRACKER = 'lousy_outages_digest_window';
    private const DIGEST_THRESHOLD = 3;
    private const DIGEST_WINDOW = 900; // 15 minutes
    private const COOLDOWN_SECONDS = 90 * MINUTE_IN_SECONDS;
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

        if (isset($alerts[$key])) {
            $last = (int) ($alerts[$key]['ts'] ?? 0);
            $lastStatus = (string) ($alerts[$key]['status'] ?? '');
            $elapsed = $now - $last;
            if ($incident->isResolved() || ($lastStatus && $lastStatus !== $incident->status)) {
                // Allow resolution or severity change.
            } elseif ($elapsed < self::COOLDOWN_SECONDS) {
                return false;
            }
        }

        $alerts[$key] = [
            'ts'     => $now,
            'status' => $incident->status,
        ];
        update_option(self::OPTION_ALERTS, $alerts, false);
        update_option($capKey, $capCount + 1, false);

        return true;
    }

    public function recordDigest(array $incidents): void
    {
        $alerts = $this->getAlerts();
        $now    = time();
        foreach ($incidents as $incident) {
            if (! $incident instanceof Incident) {
                continue;
            }
            $alerts[$this->buildKey($incident)] = [
                'ts'     => $now,
                'status' => $incident->status,
            ];
        }
        update_option(self::OPTION_ALERTS, $alerts, false);
    }

    /**
     * Persist the most recent incident snapshot.
     *
     * @param Incident[] $incidents
     */
    public function persistIncidents(array $incidents): void
    {
        $payload = array_map(static function (Incident $incident) {
            return [
                'provider'    => $incident->provider,
                'id'          => $incident->id,
                'title'       => $incident->title,
                'status'      => $incident->status,
                'url'         => $incident->url,
                'component'   => $incident->component,
                'impact'      => $incident->impact,
                'detected_at' => $incident->detected_at,
                'resolved_at' => $incident->resolved_at,
            ];
        }, array_values(array_filter($incidents, static fn($item) => $item instanceof Incident)));

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

    /**
     * Push an incident into the digest consideration queue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pushDigestCandidate(Incident $incident): array
    {
        $window = get_option(self::DIGEST_TRACKER, []);
        if (! is_array($window)) {
            $window = [];
        }
        $now = time();
        $window[] = [
            'id'       => $this->buildKey($incident),
            'provider' => $incident->provider,
            'title'    => $incident->title,
            'status'   => $incident->status,
            'url'      => $incident->url,
            'ts'       => $now,
        ];
        $window = array_values(array_filter($window, static function (array $entry) use ($now): bool {
            return ($now - (int) ($entry['ts'] ?? 0)) <= self::DIGEST_WINDOW;
        }));
        update_option(self::DIGEST_TRACKER, $window, false);

        return $window;
    }

    public function clearDigestEntries(array $ids): void
    {
        $window = get_option(self::DIGEST_TRACKER, []);
        if (! is_array($window)) {
            return;
        }
        $ids = array_fill_keys($ids, true);
        $window = array_values(array_filter($window, static function (array $entry) use ($ids): bool {
            $id = isset($entry['id']) ? (string) $entry['id'] : '';
            return '' !== $id && ! isset($ids[$id]);
        }));
        update_option(self::DIGEST_TRACKER, $window, false);
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
}
