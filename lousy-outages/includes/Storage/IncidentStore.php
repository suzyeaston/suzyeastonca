<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Storage;

use SuzyEaston\LousyOutages\Model\Incident;

class IncidentStore
{
    private const OPTION_EVENTS = 'lo_event_log';
    private const OPTION_EVENTS_COMPACTED = 'lo_event_log_compacted_v1';
    private const OPTION_LAST_DIGEST = 'lousy_outages_daily_digest_last_sent';
    private const ALERT_COOLDOWN = 90; // Minutes.
    // Keep events long enough for multi-year comparisons.
    private const EVENT_RETENTION = 2 * YEAR_IN_SECONDS; // (~24 months)
    private const IMPORTANT_EVENT_RETENTION = 4 * YEAR_IN_SECONDS;
    private const IMPORTANT_EVENT_CAP = 1000;

    /**
     * Determine whether an alert email should fire for the incident.
     */
    public function shouldSend(Incident $incident): bool
    {
        $providerKey = $this->providerKey($incident);
        $guid        = $this->incidentGuid($incident);
        $status      = $this->normalizeStatus($incident->status);

        $this->updateLastStatus($providerKey, $status);

        if ('operational' === $status) {
            update_option($this->lastGuidOption($providerKey), '', false);
            return false;
        }

        if ('maintenance' === $status) {
            return false;
        }

        if (! in_array($status, ['incident', 'degraded', 'partial', 'major', 'critical'], true)) {
            return false;
        }

        $classification = $this->classifyIncident($providerKey, $incident->title, $status);
        if (! $classification['important']) {
            return false;
        }

        $lastGuid = (string) get_option($this->lastGuidOption($providerKey), '');
        if ('' !== $lastGuid && '' !== $guid && hash_equals($lastGuid, $guid)) {
            return false;
        }

        $lastAlertAt = (int) get_option($this->lastAlertOption($providerKey), 0);
        $now         = time();
        if ($lastAlertAt && ($now - $lastAlertAt) < (self::ALERT_COOLDOWN * MINUTE_IN_SECONDS)) {
            return false;
        }

        update_option($this->lastGuidOption($providerKey), $guid, false);
        update_option($this->lastAlertOption($providerKey), $now, false);
        update_option($this->lastStatusOption($providerKey), $status, false);

        return true;
    }

    /**
     * Persist incident snapshots for digest and history.
     *
     * @param Incident[] $incidents
     */
    public function persistIncidents(array $incidents): void
    {
        $events = $this->loadEvents();
        $now    = time();

        foreach ($incidents as $incident) {
            if (! $incident instanceof Incident) {
                continue;
            }

            $providerKey = $this->providerKey($incident);
            $eventKey    = $this->eventKey($providerKey, $incident);
            $status      = $this->normalizeStatus($incident->status);

            $components = [];
            if ($incident->component) {
                $components[] = $incident->component;
            }

            $publishedTs = $incident->detected_at ?: $now;

            $entry = [
                'provider'       => $providerKey,
                'provider_label' => $incident->provider,
                'guid'           => $this->incidentGuid($incident),
                'title'          => $incident->title,
                'description'    => $incident->title,
                'status'         => $status,
                'components'     => $components,
                'published'      => gmdate('Y-m-d H:i:s T', $publishedTs),
                'first_seen'     => $publishedTs,
                'last_seen'      => $now,
                'url'            => $incident->url,
                'raw'            => null,
            ];

            $classification          = $this->classifyIncident($providerKey, $incident->title, $status);
            $entry['severity']       = $classification['severity'];
            $entry['important']      = $classification['important'];
            $entry['status_normal']  = $status;
            $entry['impact_summary'] = $classification['summary'];

            if (isset($events[$eventKey]['first_seen'])) {
                $entry['first_seen'] = (int) $events[$eventKey]['first_seen'];
            }

            $events[$eventKey] = $entry;
        }

        $events = $this->pruneEvents($events, $now);
        update_option(self::OPTION_EVENTS, $events, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function addUserReport(string $providerId, string $summary, string $contact = '', string $providerLabel = ''): array
    {
        $events = $this->loadEvents();
        $now    = time();

        $providerKey   = sanitize_key($providerId) ?: 'provider';
        $providerLabel = '' !== trim($providerLabel) ? trim($providerLabel) : ucfirst($providerKey);
        $cleanSummary  = trim($summary);
        $cleanContact  = trim($contact);

        $eventKey = 'user_report|' . $providerKey . '|' . sha1($cleanSummary . '|' . microtime(true));

        $event = [
            'provider'       => $providerKey,
            'provider_label' => $providerLabel,
            'guid'           => $eventKey,
            'title'          => 'User report',
            'description'    => $cleanSummary,
            'status'         => 'User-reported issue',
            'status_normal'  => 'User-reported issue',
            'severity'       => 'user_report',
            'important'      => true,
            'impact_summary' => $cleanSummary,
            'source'         => 'user_report',
            'published'      => gmdate('Y-m-d H:i:s T', $now),
            'first_seen'     => $now,
            'last_seen'      => $now,
        ];

        if ('' !== $cleanContact) {
            $event['user_contact'] = $cleanContact;
        }

        $events[$eventKey] = $event;

        $events = $this->pruneEvents($events, $now);
        update_option(self::OPTION_EVENTS, $events, false);

        return $event;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStoredIncidents(): array
    {
        $stored = $this->loadEvents();
        return array_values($stored);
    }

    /**
     * Return incidents between two timestamps, filtered to outage-like severities by default.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIncidentsInRange(int $start, int $end, bool $importantOnly = true): array
    {
        $events   = $this->loadEvents();
        $filtered = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event = $this->normalizeEvent($event);

            $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $lastSeen  = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
            $timestamp = $lastSeen ?: $firstSeen;

            if ($timestamp <= 0) {
                continue;
            }

            if ($timestamp < $start || $timestamp > $end) {
                continue;
            }

            $severity  = isset($event['severity']) ? strtolower((string) $event['severity']) : 'incident';
            $important = isset($event['important']) ? (bool) $event['important'] : true;

            if ($importantOnly && (! $important || in_array($severity, ['maintenance', 'info'], true))) {
                continue;
            }

            if (in_array($severity, ['operational', 'ok', 'none'], true)) {
                continue;
            }

            $filtered[] = $event;
        }

        return $filtered;
    }

    public function getYearOverYearWindow(int $days, bool $importantOnly = true): array
    {
        $now          = time();
        $currentStart = $now - ($days * DAY_IN_SECONDS);
        $prevStart    = $currentStart - YEAR_IN_SECONDS;
        $prevEnd      = $now - YEAR_IN_SECONDS;

        return [
            'current'  => $this->summarizeWindow($currentStart, $now, $importantOnly),
            'previous' => $this->summarizeWindow($prevStart, $prevEnd, $importantOnly),
        ];
    }

    /**
     * @return array{start: int, end: int, total: int, daily_counts: array<string, int>, events: array<int, array<string, mixed>>}
     */
    private function summarizeWindow(int $start, int $end, bool $importantOnly = true): array
    {
        $events       = $this->getIncidentsInRange($start, $end, $importantOnly);
        $dailyCounts  = [];

        foreach ($events as $event) {
            $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $lastSeen  = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
            $timestamp = $lastSeen ?: $firstSeen;
            if (!$timestamp) {
                continue;
            }

            $date = gmdate('Y-m-d', $timestamp);
            $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
        }

        return [
            'start'        => $start,
            'end'          => $end,
            'total'        => count($events),
            'daily_counts' => $dailyCounts,
            'events'       => $events,
        ];
    }

    /**
     * Ensure historical events carry the latest classification fields.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function normalizeEvent(array $event): array
    {
        $status     = $this->normalizeStatus((string) ($event['status'] ?? ''));
        $title      = isset($event['title']) ? (string) $event['title'] : '';
        $providerId = isset($event['provider']) ? (string) $event['provider'] : '';
        $severity   = isset($event['severity']) ? strtolower((string) $event['severity']) : '';
        $source     = isset($event['source']) ? (string) $event['source'] : '';

        $isUserReport  = ('user_report' === $source || 'user_report' === $severity);
        $isOperational = in_array($severity, ['operational', 'ok', 'none'], true);

        $classification = $this->classifyIncident($providerId, $title, $status);

        if (! $isUserReport && ! $isOperational) {
            $event['severity']       = $classification['severity'];
            $event['important']      = $classification['important'];
            $event['impact_summary'] = $classification['summary'];
            $event['status_normal']  = $status;

            if ('maintenance' === $classification['severity'] && 'degraded' === $status) {
                $event['status_normal'] = 'maintenance';
            }
        } else {
            $event['status_normal'] = $event['status_normal'] ?? $status;
        }

        return $event;
    }

    public function getLastDigestSent(): string
    {
        $value = get_option(self::OPTION_LAST_DIGEST, '');
        return is_string($value) ? $value : '';
    }

    public function updateLastDigestSent(string $iso): void
    {
        update_option(self::OPTION_LAST_DIGEST, $iso, false);
    }

    private function providerKey(Incident $incident): string
    {
        $slug = '';
        if (false !== strpos($incident->id, ':')) {
            $slug = substr($incident->id, 0, (int) strpos($incident->id, ':')) ?: '';
        }

        if ('' === $slug) {
            $slug = $incident->provider;
        }

        $slug = sanitize_key((string) $slug);

        return $slug ?: 'provider';
    }

    private function incidentGuid(Incident $incident): string
    {
        $guid = trim((string) $incident->id);
        if ('' !== $guid) {
            return $guid;
        }

        return sha1($incident->provider . '|' . $incident->title . '|' . $incident->detected_at);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        switch ($status) {
            case 'resolved':
            case 'operational':
            case 'none':
                return 'operational';
            case 'maintenance':
            case 'maintenance_window':
                return 'maintenance';
            case 'degraded':
            case 'minor':
            case 'investigating':
            case 'identified':
            case 'monitoring':
                return 'degraded';
            case 'partial':
            case 'partial_outage':
                return 'partial';
            case 'major_outage':
            case 'major':
            case 'outage':
                return 'major';
            case 'critical':
                return 'critical';
        }

        return $status ? 'incident' : 'incident';
    }

    /**
     * @return array{severity: string, important: bool, summary: string}
     */
    public function classifyIncident(string $providerKey, string $title, string $status): array
    {
        $normalizedStatus = $this->normalizeStatus($status);
        $severity         = $this->mapSeverity($normalizedStatus);
        $titleLower       = strtolower($title);
        $providerLower    = strtolower($providerKey);
        $important        = ! in_array($severity, ['maintenance', 'info'], true);
        $summary          = '';

        // Zscaler maintenance / expansion advisories are very noisy.
        if ('zscaler' === $providerLower) {
            $noisePatterns = [
                'reminder',
                'upcoming',
                'url filtering',
                'url filtering rules',
                'pagination',
                'search functionality',
                'admin',
                'administrator',
                'portal',
                'dashboard',
                'analytics',
                'audit log',
                'policy',
                'rules',
                'allowlist',
                'allow list',
                'update to',
                'scheduled update',
                'security update',
                'advisory',
                'react2shell',
                'data center expansions',
                'data centre expansions',
                'new dcs',
                'additions to hub ip address ranges',
                'aggregate & hub ip',
                'aggregate and hub ip',
                'hub ip address ranges',
                'addition to hub services',
                'additions to hub services',
                'hub services',
                'datacenter network maintenance',
                'datacentre network maintenance',
                'critical datacenter network maintenance',
                'critical datacentre network maintenance',
                'upcoming new dcs and expansions',
                'upcoming data center expansions',
                'allowlist',
                'allow list',
                'action required',
                'expansion',
            ];
            $impactWords = [
                'traffic forwarding',
                'not loading',
                'issue',
                'degradation',
                'customers may experience',
                'impact',
                'outage',
                'disruption',
                'failure',
            ];

            $filteredNoise = apply_filters('lo_zscaler_noise_patterns', $noisePatterns);
            if (! is_array($filteredNoise)) {
                $filteredNoise = $noisePatterns;
            }
            $noisePatterns = $filteredNoise;

            $filteredImpact = apply_filters('lo_zscaler_impact_terms', $impactWords);
            if (! is_array($filteredImpact)) {
                $filteredImpact = $impactWords;
            }
            $impactWords = $filteredImpact;

            $hasImpact = false;
            foreach ($impactWords as $signal) {
                if (false !== strpos($titleLower, (string) $signal)) {
                    $hasImpact = true;
                    break;
                }
            }

            $hasNoise = false;
            foreach ($noisePatterns as $pattern) {
                if (false !== strpos($titleLower, (string) $pattern)) {
                    $hasNoise = true;
                    break;
                }
            }

            if ($hasNoise && ! $hasImpact) {
                $severity  = 'maintenance';
                $important = false;
                $summary   = 'Filtered Zscaler advisory';
            }
        }

        if ('cloudflare' === $providerLower) {
            if (false !== strpos($titleLower, 'provider reports minor impact')) {
                $important = false;
                $severity  = 'info';
                $summary   = 'Minor Cloudflare signal suppressed';
            }

            // Suppress status-only Cloudflare indicator events (digest-only).
            if (preg_match('/^cloudflare status:\s*(degraded|partial(?: outage)?|major(?: outage)?|critical)$/', $titleLower)) {
                $important = false;
                $severity  = 'info';
                $summary   = 'Cloudflare status-only indicator suppressed (digest-only)';
            }
        }

        if ('maintenance' === $severity && '' === $summary) {
            $maintenanceNoise = ['upgrade', 'expansion', 'expanding', 'migration', 'deploy', 'capacity'];
            foreach ($maintenanceNoise as $word) {
                if (false !== strpos($titleLower, $word)) {
                    $important = false;
                    $summary   = 'Maintenance advisory';
                    break;
                }
            }
        }

        if (! $summary) {
            $summary = $important ? 'Significant incident' : 'Informational incident';
        }

        return [
            'severity'  => $severity,
            'important' => $important,
            'summary'   => $summary,
        ];
    }

    private function mapSeverity(string $status): string
    {
        switch ($status) {
            case 'major':
            case 'critical':
            case 'major_outage':
            case 'outage':
                return 'outage';
            case 'partial':
            case 'partial_outage':
            case 'degraded':
                return 'degraded';
            case 'maintenance':
                return 'maintenance';
            case 'operational':
                return 'info';
        }

        return 'degraded';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadEvents(): array
    {
        $stored = get_option(self::OPTION_EVENTS, []);
        if (! is_array($stored)) {
            return [];
        }

        $compacted = get_option(self::OPTION_EVENTS_COMPACTED, false);
        if (! $compacted && count($stored) > 1000) {
            $stored = $this->compactNoisyEvents($stored);
            update_option(self::OPTION_EVENTS, $stored, false);
            update_option(self::OPTION_EVENTS_COMPACTED, 1, false);
        }

        return $stored;
    }

    /**
     * @param array<string, array<string, mixed>> $events
     * @return array<string, array<string, mixed>>
     */
    private function pruneEvents(array $events, int $now): array
    {
        $standardCutoff  = $now - self::EVENT_RETENTION;
        $importantCutoff = $now - self::IMPORTANT_EVENT_RETENTION;
        $importantEvents = [];

        foreach ($events as $key => $event) {
            $lastSeen = isset($event['last_seen']) ? (int) $event['last_seen'] : 0;
            $severity = isset($event['severity']) ? strtolower((string) $event['severity']) : '';
            $important = !empty($event['important']);
            $isImportantIncident = $important && in_array($severity, ['outage', 'degraded'], true);
            $cutoff = $isImportantIncident ? $importantCutoff : $standardCutoff;

            if ($cutoff > 0 && $lastSeen && $lastSeen < $cutoff) {
                unset($events[$key]);
                continue;
            }

            if ($isImportantIncident && $lastSeen) {
                $importantEvents[$key] = $lastSeen;
            }
        }

        if (count($importantEvents) > self::IMPORTANT_EVENT_CAP) {
            asort($importantEvents, SORT_NUMERIC);
            $excess = count($importantEvents) - self::IMPORTANT_EVENT_CAP;
            foreach (array_keys($importantEvents) as $key) {
                if ($excess <= 0) {
                    break;
                }
                unset($events[$key]);
                $excess--;
            }
        }

        return $events;
    }

    /**
     * Remove noisy duplicate synthetic indicator events.
     *
     * @param array<string, array<string, mixed>> $events
     * @return array<string, array<string, mixed>>
     */
    private function compactNoisyEvents(array $events): array
    {
        $keepLatest = [];
        $now = time();
        $cloudflareCutoff = $now - (7 * DAY_IN_SECONDS);
        $allowedIndicators = ['minor', 'major', 'critical', 'maintenance'];

        foreach ($events as $key => $event) {
            if (! is_array($event)) {
                continue;
            }

            $provider = isset($event['provider']) ? sanitize_key((string) $event['provider']) : '';
            $title    = isset($event['title']) ? strtolower((string) $event['title']) : '';
            $guid     = isset($event['guid']) ? strtolower((string) $event['guid']) : '';

            $indicator = '';

            if (false !== strpos($guid, ':indicator:')) {
                $parts = explode(':indicator:', $guid);
                $indicator = isset($parts[1]) ? strtolower((string) $parts[1]) : '';
            } elseif (0 === strpos($title, 'provider reports ')) {
                if (preg_match('/^provider reports\s+([a-z]+)\s+impact/', $title, $matches)) {
                    $indicator = strtolower($matches[1]);
                }
            }

            if (! in_array($indicator, $allowedIndicators, true)) {
                continue;
            }

            $lastSeen = isset($event['last_seen']) ? (int) $event['last_seen'] : 0;
            $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $timestamp = $lastSeen ?: $firstSeen;

            if ('cloudflare' === $provider && $timestamp && $timestamp < $cloudflareCutoff) {
                unset($events[$key]);
                continue;
            }

            $dedupeKey = $provider . '|' . $indicator;
            if (! isset($keepLatest[$dedupeKey])) {
                $keepLatest[$dedupeKey] = ['key' => $key, 'timestamp' => $timestamp];
                continue;
            }

            $existing = $keepLatest[$dedupeKey];
            if ($timestamp >= $existing['timestamp']) {
                unset($events[$existing['key']]);
                $keepLatest[$dedupeKey] = ['key' => $key, 'timestamp' => $timestamp];
            } else {
                unset($events[$key]);
            }
        }

        return $events;
    }

    private function eventKey(string $providerKey, Incident $incident): string
    {
        return $providerKey . '|' . $this->incidentGuid($incident);
    }

    private function lastGuidOption(string $providerKey): string
    {
        return 'lo_last_guid_' . $providerKey;
    }

    private function lastStatusOption(string $providerKey): string
    {
        return 'lo_last_status_' . $providerKey;
    }

    private function lastAlertOption(string $providerKey): string
    {
        return 'lo_last_alert_at_' . $providerKey;
    }

    private function updateLastStatus(string $providerKey, string $status): void
    {
        update_option($this->lastStatusOption($providerKey), $status, false);
    }
}
