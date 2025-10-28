<?php
declare(strict_types=1);

namespace LousyOutages;

// Changelog:
// - 2024-06-05: Improve HTTP resilience, add history fallbacks, enhance error reporting.

use function Lousy\http_get;
use function Lousy\Adapters\from_rss_atom;
use function Lousy\Adapters\from_slack_current;
use function Lousy\Adapters\from_statuspage_status;
use function Lousy\Adapters\from_statuspage_summary;
use function Lousy\Adapters\Statuspage\detect_state_from_error;

class Fetcher {
    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'outage'      => 'Outage',
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
        ];

        if (! $endpoint) {
            $defaults['summary'] = 'No public status endpoint available';
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
        $statusFallback  = false;

        if (! $response['ok'] && in_array($status, [401, 403, 404], true)) {
            $statuspageStatus = $this->attempt_statuspage_status($provider, $endpoint);
            if ($statuspageStatus) {
                $response       = $statuspageStatus['response'];
                $adapterType    = $statuspageStatus['adapter'];
                $status         = (int) ($response['status'] ?? 0);
                $statusFallback = true;
            }
        }

        if (! $response['ok'] && in_array($status, [401, 403, 404], true)) {
            $fallback = $this->attempt_statuspage_history($provider, $endpoint);
            if ($fallback) {
                $response        = $fallback['response'];
                $adapterType     = $fallback['adapter'];
                $fallbackSummary = null;
                $status          = (int) ($response['status'] ?? 0);
            }
        }

        if (! $response['ok']) {
            $message      = (string) ($response['message'] ?? '');
            $networkKind  = $response['retry_kind'] ?? $this->classify_network_error($message);
            $errorCode    = $status > 0
                ? 'http_error:' . $status
                : 'network_error:' . ($networkKind ?: ((string) ($response['error'] ?? 'request_failed')));
            if (! $fallbackSummary) {
                $fallbackSummary = $this->failure_summary($status, $networkKind);
            }
            $statusResult = 'unknown';

            if ('statuspage' === $type && $status >= 500 && $status < 600) {
                $detected = detect_state_from_error((string) ($response['body'] ?? ''));
                if ($detected) {
                    $statusResult = $detected;
                    if (! $fallbackSummary) {
                        $summary = ('outage' === $detected)
                            ? 'Status API error indicates major outage'
                            : 'Status API error indicates active incident';
                    }
                }
            }

            if ($optional) {
                return $this->optional_unavailable($defaults, $message ?: $summary);
            }

            return $this->failed_defaults($defaults, $errorCode, $summary, $statusResult);
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

        if ($statusFallback) {
            $result['summary'] = $this->status_only_summary($result['summary'], $result['status_label']);
            $result['message'] = $result['summary'];
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
        $incidents = $this->normalize_incidents($normalized['incidents'] ?? [], $provider, $status);
        $summary   = $this->summarize($normalized, $incidents, $status);

        $result = $defaults;
        $result['status']       = $status;
        $result['status_label'] = self::status_label($status);
        $result['summary']      = $summary;
        $result['message']      = $summary;
        $result['incidents']    = $incidents;
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
        if (isset($normalized['summary'])) {
            $summary = $this->sanitize((string) $normalized['summary']);
        }
        if (! $summary && ! empty($incidents)) {
            $summary = $incidents[0]['title'] ?? ($incidents[0]['summary'] ?? '');
        }
        if (! $summary) {
            switch ($status) {
                case 'operational':
                    $summary = 'All systems operational';
                    break;
                case 'degraded':
                    $summary = 'Service degradation reported';
                    break;
                case 'outage':
                    $summary = 'Major outage reported';
                    break;
                case 'maintenance':
                    $summary = 'Maintenance in progress';
                    break;
                default:
                    $summary = 'Status unavailable';
                    break;
            }
        }

        return $this->sanitize($summary);
    }

    private function status_only_summary(string $summary, string $label): string {
        $summary = $this->sanitize($summary);
        if ('' === $summary) {
            $summary = $label ? sprintf('Overall status: %s', $label) : 'Overall status reported';
        }

        if (false === stripos($summary, 'incident')) {
            $summary .= ' (incidents unavailable)';
        }

        return $this->sanitize($summary);
    }

    private function normalize_incidents(array $incidents, array $provider, string $status): array {
        $out = [];
        foreach ($incidents as $incident) {
            if (! is_array($incident)) {
                continue;
            }

            $rawStatus = strtolower((string) ($incident['status'] ?? ''));
            if (in_array($rawStatus, ['resolved', 'completed', 'postmortem'], true)) {
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

            $impactSource = $incident['impact'] ?? $rawStatus ?: $status;
            $impact       = $this->map_impact($impactSource);

            $url = '';
            if (! empty($incident['url']) && is_string($incident['url'])) {
                $url = $incident['url'];
            } elseif (! empty($incident['shortlink']) && is_string($incident['shortlink'])) {
                $url = $incident['shortlink'];
            } elseif (! empty($provider['status_url']) && is_string($provider['status_url'])) {
                $url = $provider['status_url'];
            }

            $out[] = [
                'id'         => (string) ($incident['id'] ?? md5(wp_json_encode($incident))),
                'title'      => $title ?: 'Incident',
                'summary'    => $summary,
                'started_at' => $started,
                'updated_at' => $updated,
                'impact'     => $impact,
                'eta'        => $this->sanitize($incident['eta'] ?? ''),
                'url'        => $url,
            ];
        }

        return array_slice($out, 0, 10);
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

    private function failed_defaults(array $defaults, string $code, string $summary, string $status = 'unknown'): array {
        $summary = $summary ?: 'Status fetch failed';
        $defaults['status']       = $status ?: 'unknown';
        $defaults['status_label'] = self::status_label($defaults['status']);
        $defaults['summary']      = $summary;
        $defaults['message']      = $summary;
        $defaults['error']        = $code;
        return $defaults;
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
    private const TRANSIENT_PREFIX       = 'lousy_outages_provider_';
    private const CACHE_TTL              = 90;
    private const REQUEST_TIMEOUT        = 8;
    private const USER_AGENT             = 'LousyOutages/1.1 (+https://suzyeaston.ca)';
    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'major'       => 'Major Outage',
        'unknown'     => 'UNKNOWN',
    ];

    private const STATUS_CLASSES = [
        'operational' => 'status--operational',
        'degraded'    => 'status--degraded',
        'major'       => 'status--outage',
        'unknown'     => 'status--unknown',
    ];

    private static $PROVIDERS = [
        // Statuspage-backed vendors.
        'cloudflare' => [
            'name' => 'Cloudflare',
            'kind' => 'statuspage',
            'base' => 'https://www.cloudflarestatus.com',
        ],
        'okta' => [
            'name' => 'Okta',
            'kind' => 'statuspage',
            'base' => 'https://status.okta.com',
        ],
        'gitlab' => [
            'name' => 'GitLab',
            'kind' => 'statuspage',
            'base' => 'https://status.gitlab.com',
        ],
        'stripe' => [
            'name' => 'Stripe',
            'kind' => 'statuspage',
            'base' => 'https://status.stripe.com',
        ],
        'pagerduty' => [
            'name' => 'PagerDuty',
            'kind' => 'statuspage',
            'base' => 'https://status.pagerduty.com',
        ],
        'fastly' => [
            'name' => 'Fastly',
            'kind' => 'statuspage',
            'base' => 'https://www.fastlystatus.com',
        ],
        'datadog' => [
            'name' => 'Datadog',
            'kind' => 'statuspage',
            'base' => 'https://status.datadoghq.com',
        ],
        'zoom' => [
            'name' => 'Zoom',
            'kind' => 'statuspage',
            'base' => 'https://status.zoom.us',
        ],
        'notion' => [
            'name' => 'Notion',
            'kind' => 'statuspage',
            'base' => 'https://www.notionstatus.com',
        ],

        // Clouds.
        'aws' => [
            'name' => 'Amazon Web Services',
            'kind' => 'aws-rss',
            'rss'  => 'https://health.aws.amazon.com/health/status',
            'link' => 'https://health.aws.amazon.com/health/status',
        ],
        'azure' => [
            'name' => 'Microsoft Azure',
            'kind' => 'azure-rss',
            'rss'  => 'https://azure.status.microsoft/en-us/status/feed',
            'link' => 'https://status.azure.com/en-us/status',
        ],
        'gcp' => [
            'name' => 'Google Cloud',
            'kind' => 'gcp-json',
            'json' => 'https://status.cloud.google.com/incidents.json',
            'link' => 'https://status.cloud.google.com',
        ],

        // Trust-only tiles.
        'zscaler' => [
            'name' => 'Zscaler',
            'kind' => 'link-only',
            'link' => 'https://trust.zscaler.com/cloud-status',
        ],
        'crowdstrike' => [
            'name' => 'CrowdStrike',
            'kind' => 'link-only',
            'link' => 'https://trust.crowdstrike.com',
        ],
    ];

    public function get_provider_map(): array {
        $map = [];

        foreach (self::$PROVIDERS as $slug => $provider) {
            $slug = (string) $slug;
            $name = $provider['name'] ?? ucfirst($slug);
            $link = $provider['link'] ?? ($provider['base'] ?? ($provider['url'] ?? ''));

            $map[$slug] = [
                'slug'       => $slug,
                'name'       => $name,
                'kind'       => $provider['kind'] ?? 'link-only',
                'base'       => $provider['base'] ?? '',
                'rss'        => $provider['rss'] ?? '',
                'json'       => $provider['json'] ?? '',
                'status_url' => $link,
                'link'       => $link,
            ];
        }

        return $map;
    }

    public function get_all(?array $filters = null): array {
        $map = $this->get_provider_map();
        $slugs = [];

        if ($filters) {
            foreach ($filters as $filter) {
                if (!is_string($filter) || '' === trim($filter)) {
                    continue;
                }
                $slugs[] = strtolower(trim($filter));
            }
            $slugs = array_values(array_unique($slugs));
        }

        if (!$slugs) {
            $slugs = array_keys($map);
        }

        $providers = [];
        $errors    = [];
        $latest    = null;

        foreach ($slugs as $slug) {
            $info  = $map[$slug] ?? [
                'slug'       => $slug,
                'name'       => ucfirst($slug),
                'kind'       => 'link-only',
                'status_url' => '',
                'link'       => '',
            ];
            $cache = $this->get_cache($slug);
            $tile  = $this->fetch_provider($slug, $info, $cache);

            $providers[$slug] = $tile;

            if (!empty($tile['error'])) {
                $errors[] = [
                    'id'       => $slug,
                    'provider' => $tile['name'] ?? $info['name'],
                    'message'  => (string) $tile['error'],
                ];
            }

            if (!empty($tile['fetched_at'])) {
                $timestamp = strtotime((string) $tile['fetched_at']);
                if ($timestamp && (null === $latest || $timestamp > $latest)) {
                    $latest = $timestamp;
                }
            }
        }

        $fetched_at = $latest ? gmdate('c', $latest) : gmdate('c');
        $trending   = (new Trending())->evaluate($providers);

        return [
            'providers'  => $providers,
            'fetched_at' => $fetched_at,
            'errors'     => $errors,
            'trending'   => $trending,
        ];
    }

    private function fetch_provider(string $slug, array $info, ?array $cache): array {
        $kind = $info['kind'] ?? 'link-only';

        switch ($kind) {
            case 'statuspage':
                return $this->fetch_statuspage($info, $cache);
            case 'gcp-json':
                return $this->fetch_gcp($info, $cache);
            case 'azure-rss':
                return $this->fetch_rss($info, $cache, 'azure');
            case 'aws-rss':
                return $this->fetch_rss($info, $cache, 'aws');
            default:
                return $this->build_link_only_tile($info, $cache);
        }
    }

    private function fetch_statuspage(array $info, ?array $cache): array {
        $base   = rtrim((string) ($info['base'] ?? $info['status_url'] ?? ''), '/');
        $slug   = $info['slug'] ?? 'provider';
        $link   = $info['link'] ?? $info['status_url'] ?? $base;
        $summaryUrl   = $base ? $base . '/api/v2/summary.json' : '';
        $fallbackUrl  = $base ? $base . '/api/v2/incidents/unresolved.json' : '';

        if ('' === $summaryUrl) {
            return $this->build_link_only_tile($info, $cache);
        }

        $request = $this->perform_request($summaryUrl, $cache, 'application/json');

        if (!empty($request['error'])) {
            return $this->build_error_tile($info, $cache, (string) $request['error'], 0);
        }

        $status = (int) $request['status'];

        if (304 === $status && $cache) {
            return $this->use_cached($slug, $cache, 304);
        }

        if ($status >= 200 && $status < 300) {
            $body = (string) ($request['body'] ?? '');
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return $this->build_error_tile($info, $cache, 'Invalid JSON response.', $status);
            }

            $indicator = isset($data['status']['indicator']) ? (string) $data['status']['indicator'] : '';
            $statusCode = $this->map_indicator($indicator);
            $description = isset($data['status']['description']) ? trim((string) $data['status']['description']) : '';
            $message = $description ?: ('operational' === $statusCode ? 'All systems operational.' : 'Active incidents detected.');
            $incidents = $this->normalize_statuspage_incidents(isset($data['incidents']) ? $data['incidents'] : []);

            $tile = $this->build_tile($info, $statusCode, $message, $incidents, $status, [
                'indicator'  => $indicator ?: $statusCode,
                'fetched_at' => gmdate('c'),
                'link'       => $link,
            ]);

            $this->store_cache($slug, $tile, $request['response'], $summaryUrl, $status, $body);

            return $tile;
        }

        if (in_array($status, [401, 403, 404], true) && '' !== $fallbackUrl) {
            $fallbackCache = $cache && !empty($cache['endpoint']) && $cache['endpoint'] === $fallbackUrl ? $cache : null;
            $fallback      = $this->perform_request($fallbackUrl, $fallbackCache, 'application/json');

            if (!empty($fallback['error'])) {
                return $this->build_status_unavailable_tile($info, $cache, $status);
            }

            $fallbackStatus = (int) $fallback['status'];

            if (304 === $fallbackStatus && $fallbackCache) {
                return $this->use_cached($slug, $fallbackCache, 304);
            }

            if ($fallbackStatus >= 200 && $fallbackStatus < 300) {
                $body = (string) ($fallback['body'] ?? '');
                $data = json_decode($body, true);
                if (!is_array($data)) {
                    return $this->build_error_tile($info, $cache, 'Invalid JSON response.', $fallbackStatus);
                }

                $incidents = $this->normalize_statuspage_incidents($data);
                $severity  = $incidents ? $this->severity_from_incidents($incidents) : 'operational';
                $indicator = $incidents ? ('major' === $severity ? 'major' : 'minor') : 'none';
                $message   = $incidents ? ($incidents[0]['name'] ?? 'Active incident') : 'No active incidents.';

                $tile = $this->build_tile($info, $severity, $message, $incidents, $fallbackStatus, [
                    'indicator'  => $indicator,
                    'fetched_at' => gmdate('c'),
                    'link'       => $link,
                ]);

                $this->store_cache($slug, $tile, $fallback['response'], $fallbackUrl, $fallbackStatus, $body);

                return $tile;
            }

            return $this->build_status_unavailable_tile($info, $cache, $status);
        }

        return $this->build_status_unavailable_tile($info, $cache, $status);
    }

    private function fetch_gcp(array $info, ?array $cache): array {
        $url  = $info['json'] ?? '';
        $slug = $info['slug'] ?? 'gcp';

        if ('' === $url) {
            return $this->build_link_only_tile($info, $cache);
        }

        $request = $this->perform_request($url, $cache, 'application/json');

        if (!empty($request['error'])) {
            return $this->build_error_tile($info, $cache, (string) $request['error'], 0);
        }

        $status = (int) $request['status'];

        if (304 === $status && $cache) {
            return $this->use_cached($slug, $cache, 304);
        }

        if ($status < 200 || $status >= 300) {
            return $this->build_status_unavailable_tile($info, $cache, $status);
        }

        $body = (string) ($request['body'] ?? '');
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return $this->build_error_tile($info, $cache, 'Invalid JSON response.', $status);
        }

        $incidents = $this->normalize_gcp_incidents($data);
        $severity  = $incidents ? $this->severity_from_incidents($incidents) : 'operational';
        if ('operational' !== $severity && 'major' !== $severity) {
            $severity = 'degraded';
        }
        $message = $incidents ? ($incidents[0]['name'] ?? 'Active incident') : 'All systems operational.';

        $tile = $this->build_tile($info, $severity, $message, $incidents, $status, [
            'indicator'  => $incidents ? $severity : 'none',
            'fetched_at' => gmdate('c'),
            'link'       => $info['link'] ?? $info['status_url'] ?? '',
        ]);

        $this->store_cache($slug, $tile, $request['response'], $url, $status, $body);

        return $tile;
    }

    private function fetch_rss(array $info, ?array $cache, string $type): array {
        $url  = $info['rss'] ?? '';
        $slug = $info['slug'] ?? $type;

        if ('' === $url) {
            return $this->build_link_only_tile($info, $cache);
        }

        $request = $this->perform_request($url, $cache, 'application/atom+xml, application/rss+xml;q=0.9, */*;q=0.5');

        if (!empty($request['error'])) {
            return $this->build_error_tile($info, $cache, (string) $request['error'], 0);
        }

        $status = (int) $request['status'];

        if (304 === $status && $cache) {
            return $this->use_cached($slug, $cache, 304);
        }

        if ($status < 200 || $status >= 300) {
            return $this->build_status_unavailable_tile($info, $cache, $status);
        }

        $body  = (string) ($request['body'] ?? '');
        if ('aws' === $type) {
            // AWS recommends EventBridge for programmatic consumption, but the RSS feed works for this dashboard tile.
        }
        $items = $this->parse_feed_items($body);
        $recent = $this->recent_feed_incidents($items, 24 * HOUR_IN_SECONDS);

        $hasRecent = !empty($recent);
        $message   = $hasRecent ? ($recent[0]['name'] ?? 'Recent incident') : 'No active incidents.';
        $severity  = $hasRecent ? 'degraded' : 'operational';
        if ('aws' === $slug && $hasRecent) {
            $severity = 'degraded';
        }

        $tile = $this->build_tile($info, $severity, $message, $recent, $status, [
            'indicator'  => $hasRecent ? 'minor' : 'none',
            'fetched_at' => gmdate('c'),
            'link'       => $info['link'] ?? $info['status_url'] ?? '',
        ]);

        $this->store_cache($slug, $tile, $request['response'], $url, $status, $body);

        return $tile;
    }

    private function build_link_only_tile(array $info, ?array $cache): array {
        $message = 'Status unknown — view status page for updates.';
        $incidents = [];
        if ($cache && isset($cache['tile']['incidents']) && is_array($cache['tile']['incidents'])) {
            $incidents = $cache['tile']['incidents'];
        }

        return $this->build_tile($info, 'unknown', $message, $incidents, 0, [
            'indicator'  => null,
            'fetched_at' => $cache['tile']['fetched_at'] ?? gmdate('c'),
            'link'       => $info['link'] ?? $info['status_url'] ?? '',
            'error'      => null,
        ]);
    }

    private function build_status_unavailable_tile(array $info, ?array $cache, int $httpCode): array {
        $message = 'Status temporarily unavailable (HTTP ' . $httpCode . ')';
        $incidents = [];
        if ($cache && isset($cache['tile']['incidents']) && is_array($cache['tile']['incidents'])) {
            $incidents = $cache['tile']['incidents'];
        }

        $meta = [
            'indicator'  => null,
            'fetched_at' => $cache['tile']['fetched_at'] ?? gmdate('c'),
            'link'       => $info['link'] ?? $info['status_url'] ?? '',
            'error'      => $message,
        ];

        if ($cache && isset($cache['tile']['fetched_at'])) {
            $meta['stale'] = true;
            $meta['last_success'] = $cache['tile']['fetched_at'];
        }

        return $this->build_tile($info, 'unknown', $message, $incidents, $httpCode, $meta);
    }

    private function build_error_tile(array $info, ?array $cache, string $message, int $httpCode): array {
        $incidents = [];
        if ($cache && isset($cache['tile']['incidents']) && is_array($cache['tile']['incidents'])) {
            $incidents = $cache['tile']['incidents'];
        }

        $meta = [
            'indicator'  => null,
            'fetched_at' => $cache['tile']['fetched_at'] ?? gmdate('c'),
            'link'       => $info['link'] ?? $info['status_url'] ?? '',
            'error'      => $message,
            'stale'      => isset($cache['tile']['fetched_at']),
        ];

        if ($cache && isset($cache['tile']['fetched_at'])) {
            $meta['last_success'] = $cache['tile']['fetched_at'];
        }

        return $this->build_tile($info, 'unknown', $message, $incidents, $httpCode, $meta);
    }

    private function build_tile(array $info, string $status, string $message, array $incidents, int $httpCode, array $meta = []): array {
        $slug = $info['slug'] ?? sanitize_title($info['name'] ?? 'provider');
        $name = $info['name'] ?? ucfirst($slug);
        $status = strtolower($status ?: 'unknown');
        if (!isset(self::STATUS_LABELS[$status])) {
            $status = 'unknown';
        }

        $label = self::STATUS_LABELS[$status];
        $class = self::STATUS_CLASSES[$status] ?? self::STATUS_CLASSES['unknown'];
        $fetchedAt = $meta['fetched_at'] ?? gmdate('c');
        $link = $meta['link'] ?? ($info['link'] ?? $info['status_url'] ?? '');

        $normalizedIncidents = [];
        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                continue;
            }
            $normalizedIncidents[] = [
                'name'       => isset($incident['name']) ? (string) $incident['name'] : '',
                'status'     => isset($incident['status']) ? (string) $incident['status'] : '',
                'impact'     => isset($incident['impact']) ? (string) $incident['impact'] : '',
                'summary'    => isset($incident['summary']) ? (string) $incident['summary'] : '',
                'started_at' => $incident['started_at'] ?? ($incident['startedAt'] ?? ''),
                'updated_at' => $incident['updated_at'] ?? ($incident['updatedAt'] ?? ''),
                'url'        => isset($incident['url']) ? (string) $incident['url'] : '',
            ];
        }

        $tile = [
            'id'           => $slug,
            'provider'     => $slug,
            'name'         => $name,
            'kind'         => $info['kind'] ?? 'link-only',
            'overall'      => $status,
            'status'       => $status,
            'status_label' => $label,
            'status_class' => $class,
            'summary'      => wp_strip_all_tags($message ?: ''),
            'message'      => wp_strip_all_tags($message ?: ''),
            'incidents'    => $normalizedIncidents,
            'components'   => [],
            'fetched_at'   => $fetchedAt,
            'http_code'    => $httpCode,
            'indicator'    => $meta['indicator'] ?? null,
            'link'         => $link,
            'url'          => $link,
            'error'        => $meta['error'] ?? null,
            'stale'        => !empty($meta['stale']),
        ];

        if (isset($meta['last_success'])) {
            $tile['last_success'] = $meta['last_success'];
        }

        return $tile;
    }

    private function perform_request(string $url, ?array $cache, string $accept): array {
        $headers = [
            'User-Agent'      => self::USER_AGENT,
            'Accept'          => $accept,
            'Accept-Language' => 'en',
        ];

        if ($cache && isset($cache['endpoint']) && $cache['endpoint'] === $url) {
            if (!empty($cache['etag'])) {
                $headers['If-None-Match'] = $cache['etag'];
            }
            if (!empty($cache['last_modified'])) {
                $headers['If-Modified-Since'] = $cache['last_modified'];
            }
        }

        $response = wp_remote_get($url, [
            'timeout'     => self::REQUEST_TIMEOUT,
            'headers'     => $headers,
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            return [
                'status'   => 0,
                'body'     => null,
                'response' => $response,
                'error'    => $response->get_error_message(),
            ];
        }

        return [
            'status'   => (int) wp_remote_retrieve_response_code($response),
            'body'     => wp_remote_retrieve_body($response),
            'response' => $response,
        ];
    }

    private function get_cache(string $slug): ?array {
        $cached = get_transient(self::TRANSIENT_PREFIX . $slug);
        return is_array($cached) ? $cached : null;
    }

    private function use_cached(string $slug, array $cache, int $httpCode): array {
        $tile = isset($cache['tile']) && is_array($cache['tile']) ? $cache['tile'] : null;
        if (!$tile) {
            return $this->build_error_tile(['slug' => $slug, 'name' => ucfirst($slug), 'kind' => 'link-only'], null, 'Cached data unavailable.', $httpCode);
        }

        $tile['http_code'] = $httpCode;
        $tile['stale']     = !empty($tile['stale']);

        set_transient(self::TRANSIENT_PREFIX . $slug, $cache, self::CACHE_TTL);

        return $tile;
    }

    private function store_cache(string $slug, array $tile, $response, string $endpoint, int $status, string $body): void {
        $etag         = is_array($response) ? wp_remote_retrieve_header($response, 'etag') : '';
        $lastModified = is_array($response) ? wp_remote_retrieve_header($response, 'last-modified') : '';

        $payload = [
            'tile'          => $tile,
            'etag'          => is_string($etag) ? $etag : '',
            'last_modified' => is_string($lastModified) ? $lastModified : '',
            'endpoint'      => $endpoint,
            'http_code'     => $status,
            'body'          => $body,
        ];

        set_transient(self::TRANSIENT_PREFIX . $slug, $payload, self::CACHE_TTL);
    }

    private function normalize_statuspage_incidents($value): array {
        if (!is_array($value)) {
            return [];
        }

        $incidents = [];
        foreach ($value as $incident) {
            if (!is_array($incident)) {
                continue;
            }

            $updates = isset($incident['incident_updates']) && is_array($incident['incident_updates'])
                ? $incident['incident_updates']
                : [];
            $latestUpdate = $this->latest_statuspage_update($updates);

            $incidents[] = [
                'name'       => isset($incident['name']) ? (string) $incident['name'] : 'Incident',
                'status'     => isset($incident['status']) ? (string) $incident['status'] : '',
                'impact'     => isset($incident['impact']) ? (string) $incident['impact'] : '',
                'summary'    => $latestUpdate['body'] ?? '',
                'started_at' => $this->extract_time($incident, ['started_at', 'created_at', 'start_time']),
                'updated_at' => $this->extract_time($incident, ['updated_at', 'resolved_at', 'end_time']),
                'url'        => isset($incident['shortlink']) ? (string) $incident['shortlink'] : ($incident['url'] ?? ''),
            ];
        }

        return $incidents;
    }

    private function latest_statuspage_update(array $updates): array {
        $latest = [];
        $latestTs = 0;

        foreach ($updates as $update) {
            if (!is_array($update)) {
                continue;
            }
            $timestamp = isset($update['updated_at']) ? strtotime((string) $update['updated_at']) : 0;
            if ($timestamp && $timestamp > $latestTs) {
                $latest   = $update;
                $latestTs = $timestamp;
            }
        }

        return $latest;
    }

    private function extract_time(array $source, array $keys): string {
        foreach ($keys as $key) {
            if (empty($source[$key])) {
                continue;
            }
            $time = strtotime((string) $source[$key]);
            if ($time) {
                return gmdate('c', $time);
            }
        }

        return '';
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
            $impact = strtolower((string) ($incident['impact'] ?? ''));
            $status = strtolower((string) ($incident['status'] ?? ''));

            if (in_array($impact, ['critical', 'major'], true) || in_array($status, ['critical', 'major_outage', 'major'], true)) {
                return 'major';
            }
        }

        return $incidents ? 'degraded' : 'operational';
    }

    private function normalize_gcp_incidents(array $incidents): array {
        $normalized = [];
        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                continue;
            }

            $status = strtolower((string) ($incident['status'] ?? ''));
            if ('resolved' === $status) {
                continue;
            }

            $updates = isset($incident['most_recent_update']) && is_array($incident['most_recent_update'])
                ? $incident['most_recent_update']
                : [];
            $updateStatus = strtolower((string) ($updates['status'] ?? ''));
            if ('resolved' === $updateStatus) {
                continue;
            }

            $impact = strtolower((string) ($incident['impact'] ?? ''));
            $severity = 'degraded';
            if (in_array($impact, ['service outage', 'service disruption - high'], true)) {
                $severity = 'major';
            }

            $link = 'https://status.cloud.google.com';
            if (!empty($incident['uri']) && is_string($incident['uri'])) {
                $link = (string) $incident['uri'];
            }

            $normalized[] = [
                'name'       => isset($incident['name']) ? (string) $incident['name'] : 'Incident',
                'status'     => $severity,
                'impact'     => $impact,
                'summary'    => isset($updates['text']) ? (string) $updates['text'] : '',
                'started_at' => $this->extract_time($incident, ['begin', 'start_time', 'creation_time']),
                'updated_at' => $this->extract_time($incident, ['end', 'end_time', 'update_time']),
                'url'        => $link,
            ];
        }

        return $normalized;
    }

    private function parse_feed_items(string $body): array {
        if ('' === trim($body)) {
            return [];
        }

        $items = [];
        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            return [];
        }

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = [
                    'name' => isset($item->title) ? (string) $item->title : 'Incident',
                    'url'  => isset($item->link) ? (string) $item->link : '',
                    'date' => isset($item->pubDate) ? strtotime((string) $item->pubDate) : 0,
                ];
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = [
                    'name' => isset($entry->title) ? (string) $entry->title : 'Incident',
                    'url'  => isset($entry->link['href']) ? (string) $entry->link['href'] : '',
                    'date' => isset($entry->updated) ? strtotime((string) $entry->updated) : 0,
                ];
            }
        }

        return $items;
    }

    private function recent_feed_incidents(array $items, int $window): array {
        $threshold = time() - $window;
        $recent = [];

        foreach ($items as $item) {
            $timestamp = isset($item['date']) ? (int) $item['date'] : 0;
            if ($timestamp && $timestamp >= $threshold) {
                $recent[] = [
                    'name'       => $item['name'] ?? 'Incident',
                    'status'     => 'degraded',
                    'impact'     => 'recent',
                    'summary'    => '',
                    'started_at' => gmdate('c', $timestamp),
                    'updated_at' => gmdate('c', $timestamp),
                    'url'        => $item['url'] ?? '',
                ];
            }
        }

        return $recent;
    }
}
