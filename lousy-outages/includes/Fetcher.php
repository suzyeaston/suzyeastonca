<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

// Changelog:
// - 2024-06-05: Improve HTTP resilience, add history fallbacks, enhance error reporting.

require_once __DIR__ . '/Fetch.php';

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 24 * 60 * 60);
}

use function SuzyEaston\LousyOutages\http_get;
use function SuzyEaston\LousyOutages\Adapters\from_rss_atom;
use function SuzyEaston\LousyOutages\Adapters\from_slack_current;
use function SuzyEaston\LousyOutages\Adapters\from_statuspage_status;
use function SuzyEaston\LousyOutages\Adapters\from_statuspage_summary;
use function SuzyEaston\LousyOutages\Adapters\Statuspage\detect_state_from_error;

class Fetcher {
    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'outage'      => 'Outage',
        'major'       => 'Major Outage',
        'maintenance' => 'Maintenance',
        'unknown'     => 'Unknown',
    ];

    private $timeout;

    public function __construct(int $timeout = 8) {
        $this->timeout = max(2, $timeout);
    }

    public static function status_label(string $code): string {
        $code = strtolower($code);
        return self::STATUS_LABELS[$code] ?? self::STATUS_LABELS['unknown'];
    }

    public function fetch(array $provider): array {
        $id        = (string) ($provider['id'] ?? '');
        $name      = (string) ($provider['name'] ?? $id);
        $type      = strtolower((string) ($provider['type'] ?? 'statuspage'));
        $endpoint  = $this->resolve_endpoint($provider, $type);
        $statusUrl = isset($provider['status_url']) ? $provider['status_url'] : ($provider['url'] ?? '');

        $defaults = [
            'id'           => $id,
            'name'         => $name,
            'provider'     => $provider['provider'] ?? $name,
            'status'       => 'unknown',
            'status_label' => self::status_label('unknown'),
            'summary'      => 'Waiting for status…',
            'message'      => 'Waiting for status…',
            'updated_at'   => gmdate('c'),
            'url'          => is_string($statusUrl) ? $statusUrl : '',
            'incidents'    => [],
            'error'        => null,
            'source_type'  => $type,
        ];

        if (! $endpoint) {
            if ('manual' === $type) {
                // LO: manual providers have human-updated messaging only.
                $defaults['summary'] = 'No public feed available. We’ll update status when the provider posts an advisory.';
            } else {
                $defaults['summary'] = 'No public status endpoint available';
            }
            $defaults['message'] = $defaults['summary'];
            return $defaults;
        }

        $optional = ! empty($provider['optional']) || ('rss-optional' === $type);
        $headers  = $this->build_headers($type);
        $response = http_get($endpoint, [
            'timeout'     => $this->timeout,
            'headers'     => $headers,
            'redirection' => 3,
        ]);
        $response    = $this->maybe_retry_ipv4($response, $endpoint, $headers);
        $adapterType = $type;

        $status          = (int) ($response['status'] ?? 0);
        $fallbackSummary = null;
        $historyFallback = false;

        if (! $response['ok'] && in_array($status, [401, 403, 404], true)) {
            $statuspageStatus = $this->attempt_statuspage_status($provider, $endpoint);
            if ($statuspageStatus) {
                $response    = $statuspageStatus['response'];
                $adapterType = $statuspageStatus['adapter'];
                $status      = (int) ($response['status'] ?? 0);
            }
        }

        if (! $response['ok'] && in_array($status, [401, 403, 404], true)) {
            $fallback = $this->attempt_statuspage_history($provider, $endpoint);
            if ($fallback) {
                $response        = $fallback['response'];
                $adapterType     = $fallback['adapter'];
                $fallbackSummary = null;
                $status          = (int) ($response['status'] ?? 0);
                $historyFallback = true;
            }
        }

        if (! $response['ok']) {
            $message      = (string) ($response['message'] ?? '');
            $networkKind  = $response['retry_kind'] ?? $this->classify_network_error($message);
            $errorCode    = $status > 0
                ? 'http_error:' . $status
                : 'network_error:' . ($networkKind ?: ((string) ($response['error'] ?? 'request_failed')));
            $summaryText = $fallbackSummary ?: $this->failure_summary($status, $networkKind);
            $statusResult = 'unknown';

            if ('statuspage' === $type && $status >= 500 && $status < 600) {
                $detected = detect_state_from_error((string) ($response['body'] ?? ''));
                if ($detected) {
                    $statusResult = $detected;
                    if (!$fallbackSummary) {
                        $summaryText = ('outage' === $detected)
                            ? 'Status API error indicates major outage'
                            : 'Status API error indicates active incident';
                    }
                }
            }

            if ($optional) {
                return $this->optional_unavailable($defaults, $message ?: $summaryText);
            }

            return $this->failed_defaults($defaults, $errorCode, $summaryText, $statusResult, $status, $networkKind, $message);
        }

        $body = (string) ($response['body'] ?? '');
        if ('' === trim($body)) {
            if ($optional) {
                return $this->optional_unavailable($defaults, 'Empty body');
            }
            return $this->failed_defaults($defaults, 'data_error:empty_body', 'Empty response from status endpoint');
        }

        $normalized = $this->adapt_response($adapterType, $body);
        $result     = $this->assemble_result($defaults, $normalized, $provider);

        if ($historyFallback) {
            $result = $this->failed_defaults(
                $defaults,
                'status_history_fallback',
                'Status temporarily unavailable.',
                'unknown',
                0,
                null,
                'Status history fallback'
            );
        }

        return $result;
    }

    private function build_headers(string $type): array {
        $ua      = 'LousyOutages/1.0 (+https://suzyeaston.ca)';
        $accept  = 'application/json, application/xml, text/xml;q=0.9,*/*;q=0.8';
        if (in_array($type, ['rss', 'atom'], true)) {
            $accept = 'application/rss+xml, application/atom+xml, application/xml;q=0.9,*/*;q=0.8';
        }

        return [
            'User-Agent'    => $ua,
            'Accept'        => $accept,
            'Accept-Language' => 'en',
            'Cache-Control' => 'no-cache',
        ];
    }

    private function resolve_endpoint(array $provider, string $type): ?string {
        $keys = ['url', 'summary', 'rss', 'atom', 'current', 'endpoint'];

        foreach ($keys as $key) {
            if (empty($provider[$key]) || ! is_string($provider[$key])) {
                continue;
            }
            return $provider[$key];
        }

        return null;
    }

    private function attempt_statuspage_history(array $provider, ?string $endpoint = null): ?array {
        $urls = $this->statuspage_history_urls($provider, $endpoint);
        if (!$urls) {
            return null;
        }

        foreach ($urls as $entry) {
            [$url, $adapter] = $entry;
            $headers  = $this->build_headers($adapter);
            $response = http_get($url, [
                'timeout'     => $this->timeout,
                'headers'     => $headers,
                'redirection' => 3,
            ]);
            $response = $this->maybe_retry_ipv4($response, $url, $headers);

            if ($response['ok'] && '' !== trim((string) ($response['body'] ?? ''))) {
                return [
                    'response' => $response,
                    'adapter'  => $adapter,
                ];
            }
        }

        return null;
    }

    private function statuspage_history_urls(array $provider, ?string $endpoint = null): array {
        $base = $this->derive_statuspage_base($provider, $endpoint);
        if (! $base) {
            return [];
        }

        $base = trailingslashit($base);

        return [
            [$base . 'history.rss', 'rss'],
            [$base . 'history.atom', 'atom'],
        ];
    }

    private function attempt_statuspage_status(array $provider, ?string $endpoint = null): ?array {
        $candidates = [];

        if (! empty($provider['status_endpoint']) && is_string($provider['status_endpoint'])) {
            $candidates[] = $provider['status_endpoint'];
        }

        $base = $this->derive_statuspage_base($provider, $endpoint);
        if ($base) {
            $candidates[] = $base . 'api/v2/status.json';
            $candidates[] = $base . 'status.json';
        }

        foreach ($candidates as $candidate) {
            $headers  = $this->build_headers('status');
            $response = http_get($candidate, [
                'timeout'     => $this->timeout,
                'headers'     => $headers,
                'redirection' => 3,
            ]);
            $response = $this->maybe_retry_ipv4($response, $candidate, $headers);

            if ($response['ok'] && '' !== trim((string) ($response['body'] ?? ''))) {
                return [
                    'response' => $response,
                    'adapter'  => 'status',
                ];
            }
        }

        return null;
    }

    private function derive_statuspage_base(array $provider, ?string $endpoint = null): string {
        $candidates = [];

        if (! empty($provider['status_url']) && is_string($provider['status_url'])) {
            $candidates[] = $provider['status_url'];
        }

        if ($endpoint) {
            $candidates[] = $endpoint;
        }

        $resolved = $this->resolve_endpoint($provider, 'statuspage');
        if ($resolved) {
            $candidates[] = $resolved;
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize_statuspage_base((string) $candidate);
            if ($normalized) {
                return $normalized;
            }
        }

        return '';
    }

    private function normalize_statuspage_base(string $candidate): string {
        $candidate = trim($candidate);
        if ('' === $candidate) {
            return '';
        }

        $candidate = preg_replace('#/api/v\d+(?:\.\d+)*?/(summary\.json|status\.json|current)$#', '/', $candidate);
        $candidate = preg_replace('#/api/v\d+(?:\.\d+)*?/history\.(?:rss|atom)$#', '/', $candidate);
        $candidate = preg_replace('#/history\.(?:rss|atom)$#', '/', $candidate);

        $parts = wp_parse_url($candidate);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $base = $parts['scheme'] . '://' . $parts['host'];

        $path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';
        if ('' !== $path) {
            $segments = array_filter(explode('/', $path), static fn($segment) => '' !== $segment);
            $cleaned  = [];
            foreach ($segments as $segment) {
                $lower = strtolower($segment);
                if (preg_match('#^(summary\.json|status\.json|current|history\.(?:rss|atom))$#', $lower)) {
                    continue;
                }
                if ('api' === $lower) {
                    continue;
                }
                if (preg_match('#^v\d#', $lower)) {
                    continue;
                }
                $cleaned[] = $segment;
            }
            if ($cleaned) {
                $base .= '/' . implode('/', $cleaned);
            }
        }

        return trailingslashit($base);
    }

    private function adapt_response(string $type, string $body): array {
        switch ($type) {
            case 'slack':
            case 'slack_current':
                return from_slack_current($body);
            case 'rss':
            case 'atom':
            case 'rss-optional':
                return from_rss_atom($body);
            case 'status':
                return from_statuspage_status($body);
            default:
                return from_statuspage_summary($body);
        }
    }

    private function assemble_result(array $defaults, array $normalized, array $provider): array {
        $state   = strtolower((string) ($normalized['state'] ?? 'unknown'));
        $status  = $this->normalize_status_code($state);
        $incidentBuckets = $this->normalize_incident_buckets($normalized['incidents'] ?? [], $provider, $status);
        $incidents       = $incidentBuckets['active'];
        $recentIncidents = $incidentBuckets['recent'];
        $summary         = $this->summarize($normalized, $incidents, $status);

        $result = $defaults;
        $result['status']       = $status;
        $result['status_label'] = self::status_label($status);
        $result['summary']      = $summary;
        $result['message']      = $summary;
        $result['incidents']    = $incidents;
        $result['recent_incidents'] = $recentIncidents;
        $result['state']        = $state;
        $result['raw']          = $normalized['raw'] ?? null;

        $updated = $this->iso($normalized['updated_at'] ?? null);
        if (! $updated && ! empty($incidents)) {
            $updated = $incidents[0]['updated_at'] ?? ($incidents[0]['started_at'] ?? null);
        }
        $result['updated_at'] = $updated ?: $defaults['updated_at'];

        return $result;
    }

    private function summarize(array $normalized, array $incidents, string $status): string {
        $summary = '';
        if (! $summary && ! empty($incidents)) {
            $summary = $incidents[0]['title'] ?? ($incidents[0]['summary'] ?? '');
        }
        if (! $summary) {
            if ('operational' === $status) {
                $summary = 'All systems operational.';
            } elseif ('unknown' === $status) {
                $summary = 'Status temporarily unavailable.';
            } elseif (isset($normalized['summary'])) {
                $summary = $this->sanitize((string) $normalized['summary']);
            }
        }
        if (! $summary) {
            switch ($status) {
                case 'degraded':
                    $summary = 'Service degradation reported.';
                    break;
                case 'outage':
                    $summary = 'Major outage reported.';
                    break;
                case 'maintenance':
                    $summary = 'Maintenance in progress.';
                    break;
                default:
                    $summary = 'Status temporarily unavailable.';
                    break;
            }
        }

        return $this->sanitize($summary);
    }

    private function normalize_incident_buckets(array $incidents, array $provider, string $status): array {
        $active         = [];
        $recent         = [];
        $historyCutoff  = time() - (35 * DAY_IN_SECONDS);
        foreach ($incidents as $incident) {
            if (! is_array($incident)) {
                continue;
            }

            $rawStatus = strtolower((string) ($incident['status'] ?? ''));
            $isResolved = in_array($rawStatus, ['resolved', 'completed', 'postmortem'], true);
            if (! $isResolved && in_array($rawStatus, ['unknown', ''], true) && empty($incident['impact'])) {
                continue;
            }

            $title   = $this->sanitize($incident['title'] ?? ($incident['name'] ?? 'Incident'));
            $summary = $this->sanitize($incident['summary'] ?? '');
            if (! $summary && $rawStatus) {
                $summary = ucfirst(str_replace('_', ' ', $rawStatus));
            }
            if (! $summary) {
                $summary = $title ?: 'Incident';
            }

            $started = $this->iso($incident['started_at'] ?? ($incident['startedAt'] ?? null));
            $updated = $this->iso($incident['updated_at'] ?? ($incident['updatedAt'] ?? null)) ?: $started;
            $updatedTs = $updated ? strtotime($updated) : null;
            $startedTs = $started ? strtotime($started) : null;
            $effective = $updatedTs ?? $startedTs ?? 0;
            if ($effective && $effective < $historyCutoff) {
                continue;
            }

            $impactSource = $incident['impact'] ?? $rawStatus ?: $status;
            $impact       = $this->map_impact($impactSource);
            $maint = preg_match(
                '/\b(scheduled|planned).{0,40}maintenance\b/i',
                ($title . ' ' . $summary)
            ) === 1;
            if ($maint) {
                $impact = 'maintenance';
            }
            if ($isResolved) {
                $impact = 'operational';
            }

            $url = '';
            if (! empty($incident['url']) && is_string($incident['url'])) {
                $url = $incident['url'];
            } elseif (! empty($incident['shortlink']) && is_string($incident['shortlink'])) {
                $url = $incident['shortlink'];
            } elseif (! empty($provider['status_url']) && is_string($provider['status_url'])) {
                $url = $provider['status_url'];
            }

            $entry = [
                'id'         => (string) ($incident['id'] ?? md5(wp_json_encode($incident))),
                'title'      => $title ?: 'Incident',
                'summary'    => $summary,
                'started_at' => $started,
                'updated_at' => $updated,
                'status'     => $impact,
                'impact'     => $impact,
                'eta'        => $this->sanitize($incident['eta'] ?? ''),
                'url'        => $url,
            ];

            if ($isResolved) {
                $recent[] = $entry;
                continue;
            }

            $active[] = $entry;
        }

        return [
            'active' => array_slice($active, 0, 10),
            'recent' => array_slice($recent, 0, 10),
        ];
    }

    private function maybe_retry_ipv4(array $response, string $endpoint, array $headers): array {
        if ($response['ok'] ?? false) {
            return $response;
        }

        $status  = (int) ($response['status'] ?? 0);
        $message = (string) ($response['message'] ?? '');

        if (0 !== $status) {
            return $response;
        }

        $kind = $this->classify_network_error($message);
        if (! $kind) {
            return $response;
        }

        $retry = http_get($endpoint, [
            'timeout'     => $this->timeout,
            'headers'     => $headers,
            'redirection' => 3,
            'force_ipv4'  => true,
        ]);

        if (! ($retry['ok'] ?? false) && empty($retry['message']) && $message) {
            $retry['message'] = $message;
        }

        if (! ($retry['ok'] ?? false) && ! isset($retry['retry_kind'])) {
            $retry['retry_kind'] = $kind;
        }

        return $retry;
    }

    private function classify_network_error(string $message): ?string {
        $needle = strtolower($message);
        if ('' === $needle) {
            return null;
        }

        $dns_patterns = [
            'could not resolve host',
            "couldn't resolve host",
            'name or service not known',
            'no address associated',
        ];

        foreach ($dns_patterns as $pattern) {
            if (false !== strpos($needle, $pattern)) {
                return 'dns';
            }
        }

        if (preg_match('/\bdns\b/', $needle)) {
            return 'dns';
        }

        $tls_patterns = [
            'ssl',
            'tls',
            'certificate',
            'handshake',
        ];

        foreach ($tls_patterns as $pattern) {
            if (false !== strpos($needle, $pattern)) {
                return 'tls';
            }
        }

        return null;
    }

    private function failure_summary(int $status, ?string $networkKind): string {
        if (in_array($status, [401, 403], true)) {
            return 'Unauthorized or forbidden at API; trying history feed…';
        }

        if (404 === $status) {
            return 'Summary endpoint missing; trying history feed…';
        }

        if ('dns' === $networkKind) {
            return 'Could not resolve host';
        }

        if ('tls' === $networkKind) {
            return 'TLS handshake failed';
        }

        if ($status >= 500 && $status < 600) {
            return 'Status API error';
        }

        if (0 === $status) {
            return 'Network request failed';
        }

        return 'Status fetch failed';
    }

    private function optional_unavailable(array $defaults, string $error): array {
        $defaults['status']  = 'unknown';
        $defaults['summary'] = 'Optional source unavailable';
        $defaults['message'] = $defaults['summary'];
        $defaults['error']   = $error ? 'optional_unavailable: ' . $error : 'optional_unavailable';
        return $defaults;
    }

    private function failed_defaults(array $defaults, string $code, string $summary, string $status = 'unknown', int $httpStatus = 0, ?string $networkKind = null, string $rawMessage = ''): array {
        $defaults['status']       = $status ?: 'unknown';
        $defaults['status_label'] = self::status_label($defaults['status']);

        $suppress = $this->should_suppress_error($httpStatus, $code, $networkKind, $rawMessage);
        $displaySummary = $suppress ? 'Status temporarily unavailable.' : ($summary ?: 'Status fetch failed');

        $defaults['summary'] = $displaySummary;
        $defaults['message'] = $displaySummary;
        $defaults['error']   = $suppress ? null : $code;

        return $defaults;
    }

    private function should_suppress_error(int $httpStatus, string $code, ?string $networkKind, string $rawMessage): bool {
        if (in_array($httpStatus, [401, 403, 404], true)) {
            return true;
        }

        if (0 === $httpStatus) {
            $codeLower = strtolower($code);
            if (0 === strpos($codeLower, 'network_error:dns')) {
                return true;
            }
            if ('dns' === strtolower((string) $networkKind)) {
                return true;
            }
            if ($rawMessage && $this->is_dns_error($rawMessage)) {
                return true;
            }
        }

        return false;
    }

    private function is_dns_error(string $message): bool {
        if ('' === trim($message)) {
            return false;
        }

        return 'dns' === $this->classify_network_error($message);
    }

    private function sanitize(?string $text): string {
        if (! $text) {
            return '';
        }
        return trim(wp_strip_all_tags($text));
    }

    private function iso($value): ?string {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return gmdate('c', (int) $value->getTimestamp());
        }
        $ts = strtotime((string) $value);
        if (! $ts) {
            return null;
        }
        return gmdate('c', $ts);
    }

    private function map_impact($impact): string {
        $impact = strtolower((string) $impact);
        switch ($impact) {
            case 'critical':
            case 'major':
            case 'major_outage':
            case 'outage':
                return 'outage';
            case 'maintenance':
            case 'scheduled':
                return 'maintenance';
            case 'operational':
            case 'ok':
                return 'operational';
            case 'minor':
            case 'degraded':
            case 'partial':
            case 'partial_outage':
            case 'investigating':
            case 'identified':
            case 'monitoring':
            case 'in_progress':
            case 'reported':
            case 'active':
            case 'warning':
                return 'degraded';
            default:
                return 'minor';
        }
    }

    private function normalize_status_code(string $status): string {
        $status = strtolower($status);
        switch ($status) {
            case 'operational':
            case 'ok':
            case 'none':
                return 'operational';
            case 'degraded':
            case 'partial':
            case 'minor':
            case 'warning':
                return 'degraded';
            case 'outage':
            case 'major':
            case 'major_outage':
            case 'critical':
            case 'down':
                return 'outage';
            case 'maintenance':
            case 'scheduled':
                return 'maintenance';
            default:
                return 'unknown';
        }
    }
}

class Lousy_Outages_Fetcher {
    private const TRANSIENT_PREFIX = 'lousy_outages_provider_';
    private const CACHE_TTL = 90;
    private const REQUEST_TIMEOUT = 8;
    private const USER_AGENT = 'LousyOutages/1.2 (+https://suzyeaston.ca)';
    private const ACCEPT_HEADER = 'application/json, application/rss+xml, application/atom+xml, text/html, */*';
    // RSS/Atom incident recency windows (filterable).
    private const FEED_ACTIVE_WINDOW_HOURS = 6;
    private const FEED_RECENT_INCIDENT_DAYS = 7;

    /**
     * Manual verification checklist:
     * - Simulate HTTP 401/403/404/DNS errors to ensure tiles surface UNKNOWN with helpful copy.
     * - Trigger two sequential fetches to hit the 304 path and confirm the degraded banner clears.
     * - Confirm severity sorting bubbles major/degraded tiles above operational ones.
     * - Exercise the subscribe endpoint with valid/invalid emails and non-JS POST fallback.
     */
    private static $PROVIDERS = [
        // Statuspage JSON (keep as JSON)
        'cloudflare' => ['kind' => 'statuspage', 'base' => 'https://www.cloudflarestatus.com'],
        'zoom'       => ['kind' => 'statuspage', 'base' => 'https://status.zoom.us'],
        'teamviewer' => ['kind' => 'statuspage', 'base' => 'https://status.teamviewer.com'],

        // Switch these to RSS/Atom (per Suzy’s feeds)
        'zscaler'    => ['kind' => 'rss', 'feed' => 'https://trust.zscaler.com/rss-feed', 'link' => 'https://trust.zscaler.com/cloud-status'],
        'slack'      => ['kind' => 'rss', 'feed' => 'https://slack-status.com/feed/rss', 'link' => 'https://status.slack.com'],
        'google_workspace' => [
            'kind' => 'rss',
            'feed' => 'https://www.google.com/appsstatus/rss/en-CA',
            'link' => 'https://www.google.com/appsstatus/dashboard/',
        ],
        'google_cloud' => [
            'kind' => 'rss',
            'feed' => 'https://status.cloud.google.com/rss',
            'link' => 'https://status.cloud.google.com/',
        ],
        'azure'      => [
            'kind' => 'rss',
            'feed' => [
                'https://rssfeed.azure.status.microsoft/en-us/status/feed/',
                'https://azurestatuscdn.azureedge.net/en-us/status/feed/',
            ],
            'link' => 'https://status.azure.com/en-us/status',
        ],
        // Clouds
        'aws' => [
            'kind'  => 'rss-multi',
            'feeds' => [
                'https://status.aws.amazon.com/rss/clouddirectory-us-east-1.rss',
                'https://status.aws.amazon.com/rss/clouddirectory-ca-central-1.rss',
                'https://status.aws.amazon.com/rss/apigateway-us-east-2.rss',
                'https://status.aws.amazon.com/rss/bedrock-us-west-2.rss',
            ],
            'link' => 'https://status.aws.amazon.com/',
        ],

        // CrowdStrike: trust center only
        'crowdstrike' => ['kind' => 'link-only', 'url' => 'https://trust.crowdstrike.com'],
    ];

    private static $NAMES = [
        'cloudflare' => 'Cloudflare',
        'zoom'       => 'Zoom',
        'teamviewer' => 'TeamViewer',
        'zscaler'    => 'Zscaler',
        'slack'      => 'Slack',
        'google_workspace' => 'Google Workspace',
        'google_cloud'     => 'Google Cloud',
        'azure'      => 'Microsoft Azure',
        'aws'        => 'Amazon Web Services',
        'crowdstrike'=> 'CrowdStrike',
    ];

    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'major'       => 'Major Outage',
        'outage'      => 'Outage',
        'maintenance' => 'Maintenance',
        'unknown'     => 'Unknown',
    ];

    private const STATUS_CLASSES = [
        'operational' => 'status--operational',
        'degraded'    => 'status--degraded',
        'major'       => 'status--outage',
        'outage'      => 'status--outage',
        'maintenance' => 'status--maintenance',
        'unknown'     => 'status--unknown',
    ];

    private const STATUS_PRIORITY = [
        'major'       => 4,
        'outage'      => 4,
        'degraded'    => 3,
        'maintenance' => 2,
        'unknown'     => 1,
        'operational' => 0,
    ];

    public function get_provider_map(): array {
        $map = [];
        foreach (self::$PROVIDERS as $slug => $config) {
            $slug = (string) $slug;
            $map[$slug] = [
                'slug'       => $slug,
                'name'       => $this->display_name($slug),
                'kind'       => $config['kind'],
                'status_url' => $this->provider_link($slug, $config),
                'link'       => $this->provider_link($slug, $config),
            ];
        }
        return $map;
    }

    public function get_all(?array $filters = null): array {
        $slugs = $this->resolve_slugs($filters);
        $providers = [];
        $errors = [];
        $latest = 0;

        foreach ($slugs as $slug) {
            $config = self::$PROVIDERS[$slug];
            $cache  = $this->get_cache($slug);
            $tile   = $this->fetch_provider($slug, $config, $cache);
            $providers[$slug] = $tile;

            if (!empty($tile['error'])) {
                $errors[] = [
                    'id'      => $slug,
                    'provider'=> $tile['name'],
                    'message' => (string) $tile['error'],
                ];
            }

            $timestamp = strtotime($tile['fetched_at'] ?? '');
            if ($timestamp) {
                $latest = max($latest, $timestamp);
            }

            $this->save_cache($slug, $cache);
        }

        $sorted = array_values($providers);
        usort($sorted, [self::class, 'compare_tiles']);

        $fetched_at = $latest ? gmdate('c', $latest) : gmdate('c');
        $trending   = (new Trending())->evaluate($sorted);

        return [
            'providers'  => $sorted,
            'fetched_at' => $fetched_at,
            'errors'     => $errors,
            'trending'   => $trending,
        ];
    }

    private function resolve_slugs(?array $filters): array {
        $available = array_keys(self::$PROVIDERS);
        if (!$filters) {
            return $available;
        }
        $selected = [];
        foreach ($filters as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug && in_array($slug, $available, true)) {
                $selected[] = $slug;
            }
        }
        return $selected ? array_values(array_unique($selected)) : $available;
    }

    private static function compare_tiles(array $a, array $b): int {
        $rank = self::STATUS_PRIORITY;
        $statusA = strtolower((string) ($a['status'] ?? 'unknown'));
        $statusB = strtolower((string) ($b['status'] ?? 'unknown'));
        $ra = $rank[$statusA] ?? $rank['unknown'];
        $rb = $rank[$statusB] ?? $rank['unknown'];
        if ($ra !== $rb) {
            return $rb <=> $ra;
        }

        $nameA = strtolower((string) ($a['name'] ?? $a['provider'] ?? ''));
        $nameB = strtolower((string) ($b['name'] ?? $b['provider'] ?? ''));
        return $nameA <=> $nameB;
    }

    private function fetch_provider(string $slug, array $config, array &$cache): array {
        $kind = $config['kind'];
        switch ($kind) {
            case 'statuspage':
                $tile = $this->fetch_statuspage($slug, $config, $cache);
                break;
            case 'rss':
            case 'atom':
                $feeds = [];
                if (isset($config['feed'])) {
                    if (is_array($config['feed'])) {
                        $feeds = array_values(array_filter(array_map('strval', $config['feed'])));
                    } elseif (is_string($config['feed']) && '' !== trim($config['feed'])) {
                        $feeds = [(string) $config['feed']];
                    }
                }
                $tile = $this->fetch_feed($slug, $config, $cache, $feeds, $kind);
                break;
            case 'rss-multi':
                $feeds = isset($config['feeds']) && is_array($config['feeds']) ? array_filter(array_map('strval', $config['feeds'])) : [];
                $tile  = $this->fetch_feed($slug, $config, $cache, $feeds, 'rss');
                break;
            case 'scrape':
                $tile = $this->fetch_scrape($slug, $config, $cache);
                break;
            case 'link-only':
            default:
                $tile = $this->build_tile($slug, $config, 'unknown', 'View status →', '', [], 0, []);
                break;
        }

        $cache['tile'] = $tile;
        return $tile;
    }

    private function fetch_statuspage(string $slug, array $config, array &$cache): array {
        $base = rtrim((string) ($config['base'] ?? ''), '/');
        if ('' === $base) {
            return $this->build_tile($slug, $config, 'unknown', 'Status endpoint unavailable.', '', [], 0, ['error' => 'missing_endpoint']);
        }

        $summaryUrl  = $base . '/api/v2/summary.json';
        $fallbackUrl = $base . '/api/v2/incidents/unresolved.json';

        $request = $this->request_with_cache($slug, $cache, 'summary', $summaryUrl);
        if (!empty($request['error'])) {
            return $this->error_tile_from_request($slug, $config, $request['error'], 0);
        }

        $summaryFetchedAt = isset($cache['segments']['summary']['fetched_at']) ? $cache['segments']['summary']['fetched_at'] : gmdate('c');
        $statusCode = (int) $request['status'];
        $body       = (string) ($request['body'] ?? '');

        if (($statusCode >= 200 && $statusCode < 300) || 304 === $statusCode) {
            $parsed = $this->parse_statuspage_summary($body);
            if ($parsed) {
                $indicator = strtolower((string) $parsed['indicator']);
                $status    = $this->map_indicator($indicator);
                $message   = $parsed['description'] ?: $this->default_message($status);
                $summary   = '';
                $incidents = $parsed['incidents'];
                if ($incidents) {
                    $status  = $this->severity_from_incidents($incidents);
                    $lead    = $incidents[0];
                    $message = $lead['name'] ?? 'Incident';
                    $summary = $this->format_incident_summary($lead);
                } else {
                    if ('operational' === $status || $this->is_no_incidents_message($message)) {
                        $message = 'All systems operational.';
                    } elseif ('unknown' === $status) {
                        $message = 'Status temporarily unavailable.';
                    }
                }

                return $this->build_tile($slug, $config, $status, $message, $summary, $incidents, $statusCode, [
                    'indicator' => $indicator,
                    'link'      => $this->provider_link($slug, $config),
                    'fetched_at' => $summaryFetchedAt,
                ]);
            }
        }

        if (in_array($statusCode, [401, 403, 404], true)) {
            $fallback = $this->request_with_cache($slug, $cache, 'unresolved', $fallbackUrl);
            if (!empty($fallback['error'])) {
                return $this->error_tile_from_request($slug, $config, $fallback['error'], $statusCode);
            }
            $fallbackFetchedAt = isset($cache['segments']['unresolved']['fetched_at']) ? $cache['segments']['unresolved']['fetched_at'] : $summaryFetchedAt;
            $fbStatus = (int) $fallback['status'];
            $fbBody   = (string) ($fallback['body'] ?? '');
            if (($fbStatus >= 200 && $fbStatus < 300) || 304 === $fbStatus) {
                $incidents = $this->parse_statuspage_incidents($fbBody);
                if ($incidents) {
                    $severity = $this->severity_from_incidents($incidents);
                    $lead     = $incidents[0];
                    $message  = $lead['name'] ?? 'Incident';
                    $summary  = $this->format_incident_summary($lead);
                    return $this->build_tile($slug, $config, $severity, $message, $summary, $incidents, $fbStatus, [
                        'indicator' => 'major' === $severity ? 'critical' : 'minor',
                        'link'      => $this->provider_link($slug, $config),
                        'fetched_at' => $fallbackFetchedAt,
                    ]);
                }
            }
        }

        $message = $statusCode ? sprintf('HTTP %d fetching status.', $statusCode) : 'Status temporarily unavailable.';
        $extra = [
            'fetched_at' => $summaryFetchedAt,
        ];

        if ($this->should_suppress_http_error($statusCode ?: 0, $message)) {
            $message = 'Status temporarily unavailable.';
        } else {
            $extra['error'] = $message;
        }

        return $this->build_tile($slug, $config, 'unknown', $message, '', [], $statusCode ?: 0, $extra);
    }

    private function fetch_feed(string $slug, array $config, array &$cache, array $feeds, string $kind): array {
        if (!$feeds) {
            return $this->build_tile($slug, $config, 'unknown', 'Feed URL unavailable.', '', [], 0, ['error' => 'missing_feed']);
        }

        $items = [];
        $httpStatus = 0;
        $errors = [];
        $fetchedTimes = [];

        foreach ($feeds as $index => $feedUrl) {
            $key = 'feed_' . $index;
            $request = $this->request_with_cache($slug, $cache, $key, $feedUrl);
            if (!empty($request['error'])) {
                $errors[] = $request['error'];
                continue;
            }
            $httpStatus = (int) $request['status'];
            if ($httpStatus >= 400 && $httpStatus < 600) {
                $errors[] = 'HTTP ' . $httpStatus . ' fetching feed.';
                continue;
            }
            $body = (string) ($request['body'] ?? '');
            $items = array_merge($items, $this->parse_feed_items($body, $kind));
            if (isset($cache['segments']['feed_' . $index]['fetched_at'])) {
                $fetchedTimes[] = $cache['segments']['feed_' . $index]['fetched_at'];
            }
        }

        if (!$items && $errors) {
            return $this->error_tile_from_request($slug, $config, $errors[0], $httpStatus ?: 0);
        }

        $feedFetchedAt = gmdate('c');
        if ($fetchedTimes) {
            $latestTs = 0;
            foreach ($fetchedTimes as $timeStr) {
                $timeVal = strtotime((string) $timeStr);
                if ($timeVal && $timeVal > $latestTs) {
                    $latestTs = $timeVal;
                }
            }
            if ($latestTs) {
                $feedFetchedAt = gmdate('c', $latestTs);
            }
        }

        if ($items) {
            usort(
                $items,
                static function ($a, $b) {
                    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
                }
            );

            // LO: trim feed noise to last 35 days and dedupe noisy providers.
            $cutoff = time() - (35 * DAY_IN_SECONDS);
            $items  = array_values(
                array_filter(
                    $items,
                    static function ($item) use ($cutoff) {
                        return ($item['timestamp'] ?? 0) >= $cutoff;
                    }
                )
            );

            $seen = [];
            $items = array_values(
                array_filter(
                    $items,
                    static function ($item) use (&$seen) {
                        $key = sha1(
                            ($item['title'] ?? '') . '|' . ($item['iso'] ?? '') . '|' . ($item['link'] ?? '')
                        );
                        if (isset($seen[$key])) {
                            return false;
                        }
                        $seen[$key] = true;
                        return true;
                    }
                )
            );
        }

        if (!$items) {
            return $this->build_tile($slug, $config, 'operational', 'All systems operational.', '', [], $httpStatus ?: 200, ['fetched_at' => $feedFetchedAt]);
        }

        $active = [];
        $incidents = [];
        $activeWindowHours = apply_filters(
            'lo_feed_active_window_hours',
            self::FEED_ACTIVE_WINDOW_HOURS,
            $slug,
            $config
        );
        $recentIncidentDays = apply_filters(
            'lo_feed_recent_incident_days',
            self::FEED_RECENT_INCIDENT_DAYS,
            $slug,
            $config
        );
        $activeWindowHours = is_numeric($activeWindowHours) ? max(1, (int) $activeWindowHours) : self::FEED_ACTIVE_WINDOW_HOURS;
        $recentIncidentDays = is_numeric($recentIncidentDays) ? max(1, (int) $recentIncidentDays) : self::FEED_RECENT_INCIDENT_DAYS;
        $threshold = time() - ($activeWindowHours * HOUR_IN_SECONDS);
        $recentThreshold = time() - ($recentIncidentDays * DAY_IN_SECONDS);
        $severityRank = [
            'outage'      => 0,
            'degraded'    => 1,
            'maintenance' => 2,
            'unknown'     => 3,
        ];
        $activeStatus = 'operational';

        foreach ($items as $item) {
            $timestamp = (int) ($item['timestamp'] ?? 0);
            $text = strtolower($item['summary'] . ' ' . $item['title']);
            $statusCode = $this->infer_incident_status($text);
            $impact     = $this->map_impact($statusCode);
            $isActive   = in_array($impact, ['outage', 'degraded'], true);

            if (!$isActive || $timestamp < $threshold) {
                continue;
            }

            $active[] = $item;
            $rank     = $severityRank[$impact] ?? $severityRank['unknown'];
            $current  = $severityRank[$activeStatus] ?? PHP_INT_MAX;
            if ($rank < $current) {
                $activeStatus = $impact;
            }

            $incidents[] = [
                'name'       => $item['title'],
                'status'     => $statusCode,
                'impact'     => $impact,
                'started_at' => $item['iso'],
                'updated_at' => $item['iso'],
                'url'        => $item['link'],
            ];

            if (count($incidents) >= 10) {
                break;
            }
        }

        if (!$active) {
            $summary = '';
            $lastIncident = null;
            foreach ($items as $item) {
                $timestamp = (int) ($item['timestamp'] ?? 0);
                if ($timestamp < $recentThreshold) {
                    continue;
                }
                $text = strtolower($item['summary'] . ' ' . $item['title']);
                $statusCode = $this->infer_incident_status($text);
                $impact = $this->map_impact($statusCode);
                if (!in_array($impact, ['outage', 'degraded'], true)) {
                    continue;
                }
                $lastIncident = $item;
                break;
            }

            if ($lastIncident) {
                $reported = $this->format_local_time((int) ($lastIncident['timestamp'] ?? 0));
                $reportedText = $reported ? ' (reported ' . $reported . ')' : '';
                $summary = 'Last incident: ' . $lastIncident['title'] . $reportedText;
            }

            return $this->build_tile($slug, $config, 'operational', 'All systems operational.', $summary, [], $httpStatus ?: 200, [
                'fetched_at' => $feedFetchedAt,
            ]);
        }

        $lead = $active[0];
        $summary = '';
        if (!empty($lead['timestamp'])) {
            $summaryTime = $this->format_local_time((int) $lead['timestamp']);
            if ($summaryTime) {
                $summary = 'Updated ' . $summaryTime;
            }
        }

        return $this->build_tile($slug, $config, $activeStatus, $lead['title'], $summary, $incidents, $httpStatus ?: 200, [
            'fetched_at' => $feedFetchedAt,
        ]);
    }

    private function fetch_scrape(string $slug, array $config, array &$cache): array {
        $url = (string) ($config['url'] ?? '');
        if ('' === $url) {
            return $this->build_tile($slug, $config, 'unknown', 'Status page unavailable.', '', [], 0, ['error' => 'missing_url']);
        }
        $request = $this->request_with_cache($slug, $cache, 'scrape', $url);
        if (!empty($request['error'])) {
            return $this->error_tile_from_request($slug, $config, $request['error'], 0);
        }
        $scrapeFetchedAt = isset($cache['segments']['scrape']['fetched_at']) ? $cache['segments']['scrape']['fetched_at'] : gmdate('c');
        $body = (string) ($request['body'] ?? '');
        $headline = $this->extract_headline($body);
        if ('' === $headline) {
            $headline = 'Unable to read status headline.';
        }
        $status = (stripos($headline, 'running smoothly') !== false) ? 'operational' : 'degraded';
        return $this->build_tile($slug, $config, $status, $headline, '', [], (int) $request['status'], [
            'fetched_at' => $scrapeFetchedAt,
        ]);
    }

    private function build_tile(string $slug, array $config, string $status, string $message, string $summary, array $incidents, int $http_code, array $extra): array {
        $status = strtolower($status ?: 'unknown');
        if (!isset(self::STATUS_LABELS[$status])) {
            $status = 'unknown';
        }

        $link = $extra['link'] ?? $this->provider_link($slug, $config);
        $fetchedAt = $extra['fetched_at'] ?? gmdate('c');
        $indicator = $extra['indicator'] ?? null;
        $error = $extra['error'] ?? null;

        $normalizedIncidents = [];
        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                continue;
            }
            $normalizedIncidents[] = [
                'name'       => $this->clean_string($incident['name'] ?? 'Incident'),
                'status'     => strtolower($incident['status'] ?? 'unknown'),
                'started_at' => $incident['started_at'] ?? '',
                'updated_at' => $incident['updated_at'] ?? '',
                'url'        => $incident['url'] ?? '',
            ];
        }

        $summary_text = $summary;
        if (array_key_exists('summary', $extra)) {
            $summary_text = (string) $extra['summary'];
        }

        $tile = [
            'id'           => $slug,
            'provider'     => $slug,
            'name'         => $this->display_name($slug),
            'kind'         => $config['kind'] ?? 'link-only',
            'status'       => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'status_class' => self::STATUS_CLASSES[$status],
            'overall'      => $status,
            'message'      => $this->clean_string($message),
            'summary'      => $this->clean_string($summary_text),
            'incidents'    => $normalizedIncidents,
            'components'   => [],
            'fetched_at'   => $fetchedAt,
            'http_code'    => $http_code,
            'indicator'    => $indicator,
            'link'         => $link,
            'url'          => $link,
            'error'        => $error,
        ];

        if ('unknown' === $status && !$error && $http_code >= 500) {
            $tile['error'] = sprintf('HTTP %d response from provider.', $http_code);
        }

        if (!empty($tile['error'])) {
            $tile['error'] = $this->clean_string($tile['error']);
        }

        return $tile;
    }

    private function error_tile_from_request(string $slug, array $config, string $message, int $http_code): array {
        $http = $http_code ?: 0;
        $display = $message ?: 'Status fetch failed.';
        $suppress = $this->should_suppress_http_error($http, $message);

        $extra = [];
        if (!$suppress && '' !== $display) {
            $extra['error'] = $display;
        }

        if ($suppress) {
            $display = 'Status temporarily unavailable.';
        }

        return $this->build_tile($slug, $config, 'unknown', $display, '', [], $http, $extra);
    }

    private function should_suppress_http_error(int $code, string $message): bool {
        if (in_array($code, [401, 403, 404], true)) {
            return true;
        }

        if (0 === $code && $this->is_dns_error($message)) {
            return true;
        }

        return false;
    }

    private function parse_statuspage_summary(string $body): ?array {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        $indicator = $data['status']['indicator'] ?? 'none';
        $description = isset($data['status']['description']) ? $this->clean_string($data['status']['description']) : '';
        $incidents = $this->normalize_statuspage_incident_array($data['incidents'] ?? []);
        return [
            'indicator'    => $indicator,
            'description'  => $description,
            'incidents'    => $incidents,
        ];
    }

    private function parse_statuspage_incidents(string $body): array {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }
        return $this->normalize_statuspage_incident_array($data);
    }

    private function normalize_statuspage_incident_array($value): array {
        if (!is_array($value)) {
            return [];
        }
        $incidents = [];
        foreach ($value as $incident) {
            if (!is_array($incident)) {
                continue;
            }
            $status = strtolower((string) ($incident['status'] ?? 'unknown'));
            if (in_array($status, ['resolved', 'completed', 'postmortem'], true)) {
                continue;
            }
            $name = $this->clean_string($incident['name'] ?? 'Incident');
            $updates = isset($incident['incident_updates']) && is_array($incident['incident_updates']) ? $incident['incident_updates'] : [];
            $summary = $this->latest_statuspage_summary($updates);
            $started = $this->normalize_time($incident['started_at'] ?? $incident['created_at'] ?? '');
            $updated = $this->normalize_time($incident['updated_at'] ?? $incident['resolved_at'] ?? '');
            $url = isset($incident['shortlink']) ? (string) $incident['shortlink'] : ((isset($incident['url']) && is_string($incident['url'])) ? $incident['url'] : '');
            $incidents[] = [
                'name'       => $name,
                'status'     => $status ?: 'unknown',
                'started_at' => $started,
                'updated_at' => $updated ?: $started,
                'url'        => $url,
            ];
        }
        return $incidents;
    }

    private function latest_statuspage_summary(array $updates): string {
        $latest = '';
        $latestTs = 0;
        foreach ($updates as $update) {
            if (!is_array($update)) {
                continue;
            }
            $ts = strtotime((string) ($update['updated_at'] ?? $update['created_at'] ?? ''));
            if ($ts && $ts > $latestTs) {
                $latestTs = $ts;
                $latest = $this->clean_string($update['body'] ?? '');
            }
        }
        return $latest;
    }

    private function parse_feed_items(string $body, string $kind): array {
        if ('' === trim($body)) {
            return [];
        }
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (false === $xml) {
            return [];
        }
        $items = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $title = $this->clean_string((string) ($item->title ?? ''));
                $link  = (string) ($item->link ?? '');
                $timestamp = strtotime((string) ($item->pubDate ?? $item->date ?? ''));
                $iso = $timestamp ? gmdate('c', $timestamp) : '';
                $summary = $this->clean_string((string) ($item->description ?? ''));
                $items[] = [
                    'title'     => $title ?: 'Incident',
                    'link'      => $link,
                    'summary'   => $summary,
                    'timestamp' => $timestamp ?: 0,
                    'iso'       => $iso,
                ];
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $title = $this->clean_string((string) ($entry->title ?? ''));
                $link  = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $linkNode) {
                        $rel = (string) ($linkNode['rel'] ?? '');
                        if ('' === $rel || 'alternate' === $rel) {
                            $link = (string) ($linkNode['href'] ?? '');
                            break;
                        }
                    }
                }
                $timestamp = strtotime((string) ($entry->updated ?? $entry->published ?? ''));
                $iso = $timestamp ? gmdate('c', $timestamp) : '';
                $summary = $this->clean_string((string) ($entry->summary ?? $entry->content ?? ''));
                $items[] = [
                    'title'     => $title ?: 'Incident',
                    'link'      => $link,
                    'summary'   => $summary,
                    'timestamp' => $timestamp ?: 0,
                    'iso'       => $iso,
                ];
            }
        }
        return $items;
    }

    private function infer_incident_status(string $text): string {
        if (preg_match('/\bresolved\b|this issue is now resolved|issue is now resolved|issue has been resolved|ended at|postmortem/i', $text)) {
            return 'operational';
        }
        if (preg_match('/\b(scheduled|planned).{0,40}maintenance\b/i', $text)) {
            return 'maintenance';
        }
        if (preg_match('/\b(major|critical|outage)\b/i', $text)) {
            return 'major_outage';
        }
        if (preg_match('/\b(degraded|partial|service disruption|connectivity)\b/i', $text)) {
            return 'degraded';
        }
        if (false !== strpos($text, 'investigat')) {
            return 'investigating';
        }
        if (false !== strpos($text, 'identify')) {
            return 'identified';
        }
        if (false !== strpos($text, 'monitor')) {
            return 'monitoring';
        }
        return 'unknown';
    }

    private function extract_headline(string $body): string {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $body, $matches)) {
            return $this->clean_string($matches[1]);
        }
        if (preg_match('/aria-label="([^"]*dashboard[^"]*)"/i', $body, $matches)) {
            return $this->clean_string($matches[1]);
        }
        $text = $this->clean_string(wp_strip_all_tags($body));
        return $text ? mb_substr($text, 0, 140) : '';
    }

    private function map_indicator(string $indicator): string {
        $indicator = strtolower($indicator);
        switch ($indicator) {
            case 'none':
            case 'operational':
                return 'operational';
            case 'minor':
            case 'degraded_performance':
                return 'degraded';
            case 'major':
            case 'critical':
            case 'major_outage':
                return 'major';
            default:
                return 'unknown';
        }
    }

    private function severity_from_incidents(array $incidents): string {
        foreach ($incidents as $incident) {
            $status = strtolower((string) ($incident['status'] ?? ''));
            if (in_array($status, ['critical', 'major', 'major_outage'], true)) {
                return 'major';
            }
        }
        return $incidents ? 'degraded' : 'operational';
    }

    private function request_with_cache(string $slug, array &$cache, string $key, string $url): array {
        if ('' === $url) {
            return ['status' => 0, 'body' => null, 'error' => ''];
        }

        if (!isset($cache['segments']) || !is_array($cache['segments'])) {
            $cache['segments'] = [];
        }
        $segment = isset($cache['segments'][$key]) && is_array($cache['segments'][$key]) ? $cache['segments'][$key] : null;
        if ($segment && !empty($segment['url']) && $segment['url'] !== $url) {
            $segment = null;
        }

        $headers = [
            'User-Agent'      => self::USER_AGENT,
            'Accept'          => self::ACCEPT_HEADER,
            'Cache-Control'   => 'no-cache',
        ];
        if ($segment) {
            if (!empty($segment['etag'])) {
                $headers['If-None-Match'] = $segment['etag'];
            }
            if (!empty($segment['last_modified'])) {
                $headers['If-Modified-Since'] = $segment['last_modified'];
            }
        }

        $response = wp_remote_get($url, [
            'timeout'     => self::REQUEST_TIMEOUT,
            'headers'     => $headers,
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => 0,
                'body'   => null,
                'error'  => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if (304 === $status && $segment && isset($segment['body'])) {
            return [
                'status'     => 304,
                'body'       => $segment['body'],
                'http_code'  => $segment['http_code'] ?? 304,
                'etag'       => $segment['etag'] ?? '',
                'fetched_at' => $segment['fetched_at'] ?? gmdate('c'),
            ];
        }

        $body = (string) wp_remote_retrieve_body($response);
        $etag = wp_remote_retrieve_header($response, 'etag');
        $last = wp_remote_retrieve_header($response, 'last-modified');

        $cache['segments'][$key] = [
            'url'           => $url,
            'body'          => $body,
            'etag'          => is_string($etag) ? $etag : '',
            'last_modified' => is_string($last) ? $last : '',
            'http_code'     => $status,
            'fetched_at'    => gmdate('c'),
        ];

        return [
            'status' => $status,
            'body'   => $body,
        ];
    }

    private function get_cache(string $slug): array {
        $cached = get_transient(self::TRANSIENT_PREFIX . $slug);
        if (!is_array($cached)) {
            return [];
        }
        if (!isset($cached['segments']) || !is_array($cached['segments'])) {
            $cached['segments'] = [];
        }
        return $cached;
    }

    private function save_cache(string $slug, array $cache): void {
        set_transient(self::TRANSIENT_PREFIX . $slug, $cache, self::CACHE_TTL);
    }

    private function provider_link(string $slug, array $config): string {
        if (!empty($config['link'])) {
            return (string) $config['link'];
        }
        if (!empty($config['base'])) {
            return rtrim((string) $config['base'], '/') . '/';
        }
        if (!empty($config['url'])) {
            return (string) $config['url'];
        }
        if (!empty($config['feed'])) {
            return (string) $config['feed'];
        }
        return '';
    }

    private function display_name(string $slug): string {
        if (isset(self::$NAMES[$slug])) {
            return self::$NAMES[$slug];
        }
        $slug = str_replace(['-', '_'], ' ', $slug);
        $slug = ucwords($slug);
        return $slug ?: 'Provider';
    }

    private function normalize_time($value): string {
        if (empty($value)) {
            return '';
        }
        $timestamp = strtotime((string) $value);
        return $timestamp ? gmdate('c', $timestamp) : '';
    }

    private function default_message(string $status): string {
        switch ($status) {
            case 'operational':
                return 'All systems operational.';
            case 'degraded':
                return 'Service degradation detected';
            case 'major':
                return 'Major outage detected';
            default:
                return 'Status temporarily unavailable.';
        }
    }

    private function format_incident_summary(array $incident): string {
        $status = isset($incident['status']) ? (string) $incident['status'] : '';
        $statusLabel = $status ? ucwords(str_replace('_', ' ', $status)) : 'Incident';
        $updated = $this->format_local_time($incident['updated_at'] ?? ($incident['started_at'] ?? ''));
        return trim($statusLabel . ($updated ? ' • Updated ' . $updated : ''));
    }

    private function is_no_incidents_message(string $message): bool {
        $needle = strtolower(trim($message));
        if ('' === $needle) {
            return false;
        }
        $phrases = [
            'no active incidents',
            'no incidents',
            'no current incidents',
            'no known incidents',
            'no reported incidents',
            'all systems operational',
        ];
        foreach ($phrases as $phrase) {
            if (false !== strpos($needle, $phrase)) {
                return true;
            }
        }
        return false;
    }

    private function format_local_time($value): string {
        if (empty($value)) {
            return '';
        }
        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$timestamp) {
            return '';
        }
        $format = get_option('date_format') . ' ' . get_option('time_format');
        return wp_date($format, $timestamp);
    }

    private function clean_string($value): string {
        return trim(wp_strip_all_tags((string) $value));
    }

    private function is_dns_error(string $message): bool {
        $needle = strtolower($message);
        foreach (['could not resolve host', "couldn't resolve host", 'name or service not known', 'no address associated', 'cURL error 6'] as $pattern) {
            if (false !== strpos($needle, strtolower($pattern))) {
                return true;
            }
        }
        return false;
    }
}

if (! class_exists('LousyOutages\\Fetcher')) {
    class_alias(__NAMESPACE__ . '\\Fetcher', 'LousyOutages\\Fetcher');
}

if (! class_exists('LousyOutages\\Lousy_Outages_Fetcher')) {
    class_alias(__NAMESPACE__ . '\\Lousy_Outages_Fetcher', 'LousyOutages\\Lousy_Outages_Fetcher');
}
