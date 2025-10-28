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
    private const TRANSIENT_PREFIX = 'lousy_outages_summary_';
    private const CACHE_TTL        = 90;

    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'major'       => 'Major Outage',
        'unknown'     => 'Unknown',
    ];

    /**
     * Return the provider → endpoint map.
     */
    public function get_provider_map(): array {
        return [
            'okta'    => [
                'slug'       => 'okta',
                'name'       => 'Okta',
                'endpoint'   => 'https://status.okta.com/api/v2/summary.json',
                'status_url' => 'https://status.okta.com/',
            ],
            'fastly'  => [
                'slug'       => 'fastly',
                'name'       => 'Fastly',
                'endpoint'   => 'https://www.fastlystatus.com/api/v2/summary.json',
                'status_url' => 'https://www.fastlystatus.com/',
            ],
            'gitlab'  => [
                'slug'       => 'gitlab',
                'name'       => 'GitLab',
                'endpoint'   => 'https://status.gitlab.com/api/v2/summary.json',
                'status_url' => 'https://status.gitlab.com/',
            ],
            'stripe'  => [
                'slug'       => 'stripe',
                'name'       => 'Stripe',
                'endpoint'   => 'https://status.stripe.com/api/v2/summary.json',
                'status_url' => 'https://status.stripe.com/',
            ],
            'notion'  => [
                'slug'       => 'notion',
                'name'       => 'Notion',
                'endpoint'   => 'https://www.notionstatus.com/api/v2/summary.json',
                'status_url' => 'https://www.notionstatus.com/',
            ],
            'zscaler' => [
                'slug'       => 'zscaler',
                'name'       => 'Zscaler',
                'endpoint'   => null,
                'status_url' => 'https://trust.zscaler.com/cloud-status',
            ],
        ];
    }

    /**
     * Fetch and normalise a provider summary.
     */
    public function fetch_summary(string $provider): array {
        $slug = strtolower(trim($provider));
        if ('' === $slug) {
            $slug = 'unknown';
        }

        $map = $this->get_provider_map();
        $info = $map[$slug] ?? [
            'slug'       => $slug,
            'name'       => ucfirst($slug),
            'endpoint'   => null,
            'status_url' => '',
        ];

        $cache_key = $this->cache_key($info['slug']);
        $cached    = get_transient($cache_key);
        if (!is_array($cached)) {
            $cached = null;
        }

        if (empty($info['endpoint'])) {
            return $this->build_unknown_result(
                $info,
                $cached,
                'Status unknown — visit the status page for updates.',
                null
            );
        }

        $headers = [
            'User-Agent' => $this->user_agent(),
            'Accept'     => 'application/json',
        ];
        if ($cached) {
            if (!empty($cached['etag'])) {
                $headers['If-None-Match'] = $cached['etag'];
            }
            if (!empty($cached['last_modified'])) {
                $headers['If-Modified-Since'] = $cached['last_modified'];
            }
        }

        $response = wp_remote_get($info['endpoint'], [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return $this->failure_result($info, $cache_key, $cached, $response->get_error_message(), null);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if (304 === $status_code) {
            if ($cached && isset($cached['normalized'])) {
                set_transient($cache_key, $cached, self::CACHE_TTL);
                $normalized = $cached['normalized'];
                if (!isset($normalized['fetched_at']) && !empty($cached['fetched_at'])) {
                    $normalized['fetched_at'] = $cached['fetched_at'];
                }
                return $normalized;
            }

            return $this->failure_result($info, $cache_key, $cached, 'No cached response available for revalidation.', 304);
        }

        if ($status_code < 200 || $status_code >= 300) {
            return $this->failure_result($info, $cache_key, $cached, 'HTTP ' . $status_code, $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === trim($body)) {
            return $this->failure_result($info, $cache_key, $cached, 'Empty response body.', $status_code);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $this->failure_result($info, $cache_key, $cached, 'Invalid JSON response.', $status_code);
        }

        $fetched_at = gmdate('c');
        $normalized = $this->normalize_statuspage($info, $data, $fetched_at);

        $etag         = wp_remote_retrieve_header($response, 'etag');
        $lastModified = wp_remote_retrieve_header($response, 'last-modified');

        $cache_payload = [
            'body'          => $body,
            'normalized'    => $normalized,
            'etag'          => is_string($etag) ? $etag : '',
            'last_modified' => is_string($lastModified) ? $lastModified : '',
            'fetched_at'    => $fetched_at,
        ];

        set_transient($cache_key, $cache_payload, self::CACHE_TTL);

        return $normalized;
    }

    /**
     * Fetch all provider summaries.
     */
    public function get_all(?array $filters = null): array {
        $map        = $this->get_provider_map();
        $providers  = [];
        $errors     = [];
        $latest_ts  = null;

        $slugs = [];
        if ($filters) {
            foreach ($filters as $filter) {
                if (!is_string($filter) || '' === trim($filter)) {
                    continue;
                }
                $slugs[] = strtolower(trim($filter));
            }
            $slugs = array_values(array_unique($slugs));
        } else {
            $slugs = array_keys($map);
        }

        if (!$slugs) {
            $slugs = array_keys($map);
        }

        foreach ($slugs as $slug) {
            $summary = $this->fetch_summary($slug);
            $providers[$slug] = $summary;

            if (!empty($summary['fetched_at'])) {
                $timestamp = strtotime((string) $summary['fetched_at']);
                if ($timestamp && (null === $latest_ts || $timestamp > $latest_ts)) {
                    $latest_ts = $timestamp;
                }
            }

            if (!empty($summary['error'])) {
                $errors[] = [
                    'id'       => $summary['id'] ?? $slug,
                    'provider' => $summary['provider'] ?? $summary['name'] ?? ucfirst($slug),
                    'message'  => (string) $summary['error'],
                ];
            }
        }

        $fetched_at = $latest_ts ? gmdate('c', $latest_ts) : gmdate('c');

        return [
            'providers'  => $providers,
            'fetched_at' => $fetched_at,
            'errors'     => $errors,
        ];
    }

    private function user_agent(): string {
        $home = home_url('/');
        if (!is_string($home) || '' === $home) {
            $home = 'https://example.com/';
        }

        return 'LousyOutages/1.0 (+' . rtrim($home, '/') . ')';
    }

    private function cache_key(string $slug): string {
        $clean = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
        if ('' === $clean) {
            $clean = 'provider';
        }

        return self::TRANSIENT_PREFIX . $clean;
    }

    private function normalize_statuspage(array $info, array $data, string $fetched_at): array {
        $indicator = isset($data['status']['indicator']) ? (string) $data['status']['indicator'] : '';
        $status    = isset($data['status']['status']) ? (string) $data['status']['status'] : '';

        $overall = $this->map_indicator($indicator, $status);
        $summary = '';
        if (!empty($data['status']['description'])) {
            $summary = (string) $data['status']['description'];
        }

        $components = [];
        if (!empty($data['components']) && is_array($data['components'])) {
            $components = $this->impacted_components($data['components']);
        }

        $incidents = [];
        if (!empty($data['incidents']) && is_array($data['incidents'])) {
            $incidents = $this->normalize_incidents($data['incidents']);
        }

        if ('' === $summary) {
            if ($incidents) {
                $summary = $incidents[0]['name'];
            } elseif ('operational' === $overall) {
                $summary = 'All systems operational';
            } else {
                $summary = 'Status updated';
            }
        }

        return [
            'id'           => $info['slug'],
            'provider'     => $info['name'],
            'name'         => $info['name'],
            'overall'      => $overall,
            'status_label' => $this->status_label($overall),
            'status_class' => $this->status_class($overall),
            'summary'      => $summary,
            'components'   => $components,
            'incidents'    => $incidents,
            'fetched_at'   => $fetched_at,
            'url'          => $info['status_url'],
            'error'        => null,
            'stale'        => false,
        ];
    }

    private function map_indicator(?string $indicator, ?string $status_code): string {
        $indicator = strtolower((string) $indicator);
        $status    = strtolower((string) $status_code);

        switch ($indicator) {
            case 'none':
            case 'operational':
                return 'operational';
            case 'minor':
                return 'degraded';
            case 'major':
            case 'critical':
                return 'major';
        }

        switch ($status) {
            case 'operational':
                return 'operational';
            case 'minor_outage':
            case 'partial_outage':
            case 'degraded_performance':
                return 'degraded';
            case 'major_outage':
            case 'critical':
                return 'major';
            default:
                return 'unknown';
        }
    }

    private function status_label(string $overall): string {
        $overall = strtolower($overall);
        return self::STATUS_LABELS[$overall] ?? self::STATUS_LABELS['unknown'];
    }

    private function impacted_components(array $components): array {
        $list = [];

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $status = isset($component['status']) ? strtolower((string) $component['status']) : '';
            if ('' === $status || 'operational' === $status) {
                continue;
            }

            $list[] = [
                'id'           => isset($component['id']) ? (string) $component['id'] : '',
                'name'         => isset($component['name']) ? (string) $component['name'] : 'Component',
                'status'       => $status,
                'status_label' => $this->component_status_label($status),
            ];
        }

        return $list;
    }

    private function component_status_label(string $status): string {
        switch ($status) {
            case 'operational':
                return 'Operational';
            case 'degraded_performance':
                return 'Degraded Performance';
            case 'partial_outage':
                return 'Partial Outage';
            case 'major_outage':
                return 'Major Outage';
            case 'under_maintenance':
                return 'Under Maintenance';
            default:
                $label = str_replace(['_', '-'], ' ', $status);
                return ucwords($label ?: 'Unknown');
        }
    }

    private function normalize_incidents(array $incidents): array {
        $list = [];

        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                continue;
            }

            $summary = $this->incident_summary($incident);
            $list[]  = [
                'id'         => isset($incident['id']) ? (string) $incident['id'] : '',
                'name'       => isset($incident['name']) ? (string) $incident['name'] : 'Incident',
                'status'     => isset($incident['status']) ? strtolower((string) $incident['status']) : '',
                'impact'     => isset($incident['impact']) ? strtolower((string) $incident['impact']) : '',
                'started_at' => isset($incident['started_at']) ? (string) $incident['started_at'] : '',
                'updated_at' => isset($incident['updated_at']) ? (string) $incident['updated_at'] : '',
                'url'        => isset($incident['shortlink']) ? (string) $incident['shortlink'] : (isset($incident['url']) ? (string) $incident['url'] : ''),
                'summary'    => $summary,
            ];
        }

        return $list;
    }

    private function incident_summary(array $incident): string {
        if (empty($incident['incident_updates']) || !is_array($incident['incident_updates'])) {
            return '';
        }

        $latest = null;
        foreach ($incident['incident_updates'] as $update) {
            if (!is_array($update)) {
                continue;
            }

            $timestamp = 0;
            if (!empty($update['updated_at'])) {
                $timestamp = strtotime((string) $update['updated_at']);
            } elseif (!empty($update['created_at'])) {
                $timestamp = strtotime((string) $update['created_at']);
            }

            if (null === $latest || $timestamp > $latest['timestamp']) {
                $latest = [
                    'timestamp' => $timestamp,
                    'body'      => isset($update['body']) ? (string) $update['body'] : '',
                ];
            }
        }

        if (!$latest) {
            return '';
        }

        $body = trim($latest['body']);
        if ('' === $body) {
            return '';
        }

        return wp_strip_all_tags($body);
    }

    private function failure_result(array $info, string $cache_key, ?array $cached, string $message, ?int $status_code): array {
        if ($cached) {
            set_transient($cache_key, $cached, self::CACHE_TTL);
        }

        $summary = 'Status temporarily unavailable';
        if ($status_code) {
            $summary .= ' (HTTP ' . $status_code . ')';
        }

        return $this->build_unknown_result($info, $cached, $summary, $message);
    }

    private function build_unknown_result(array $info, ?array $cached, string $summary, ?string $error): array {
        $result = [
            'id'           => $info['slug'],
            'provider'     => $info['name'],
            'name'         => $info['name'],
            'overall'      => 'unknown',
            'status_label' => $this->status_label('unknown'),
            'status_class' => $this->status_class('unknown'),
            'summary'      => $summary ?: 'Status unavailable',
            'components'   => [],
            'incidents'    => [],
            'fetched_at'   => gmdate('c'),
            'url'          => $info['status_url'],
            'error'        => $error ? $error : null,
            'stale'        => false,
        ];

        if ($cached && isset($cached['normalized']) && is_array($cached['normalized'])) {
            $cached_normalized = $cached['normalized'];
            if (!empty($cached_normalized['components'])) {
                $result['components'] = $cached_normalized['components'];
            }
            if (!empty($cached_normalized['incidents'])) {
                $result['incidents'] = $cached_normalized['incidents'];
            }
            if (!empty($cached['fetched_at'])) {
                $result['fetched_at'] = $cached['fetched_at'];
                $result['last_success'] = $cached['fetched_at'];
            }
            if (!empty($cached_normalized['overall'])) {
                $result['previous_overall'] = $cached_normalized['overall'];
                if (false !== stripos($result['summary'], 'Status temporarily unavailable')) {
                    $result['summary'] = sprintf(
                        'Status temporarily unavailable — last known: %s',
                        $this->status_label((string) $cached_normalized['overall'])
                    );
                }
            }
            $result['stale'] = true;
        }

        return $result;
    }

    private function status_class(string $status): string {
        $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower($status));
        if ('' === $slug) {
            $slug = 'unknown';
        }

        return 'status--' . $slug;
    }
}
