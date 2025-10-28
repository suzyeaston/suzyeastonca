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
    private const USER_AGENT = 'LousyOutages/1.0 (+https://suzyeaston.ca)';
    private const TRANSIENT_PREFIX = 'lousy_outages_summary_';
    private const CACHE_TTL_MIN = 60;
    private const CACHE_TTL_MAX = 120;
    private const CACHE_TTL_DEFAULT = 90;

    /**
     * Fetch a normalized summary for a single provider.
     *
     * @param string|array $provider Provider identifier or configuration array.
     */
    public function fetch_summary($provider): array {
        if (is_string($provider) || is_int($provider)) {
            $provider = $this->provider_from_id((string) $provider);
        }

        if (!is_array($provider) || empty($provider['id'])) {
            return $this->unknown_payload([
                'id'   => is_array($provider) ? ($provider['id'] ?? '') : (string) $provider,
                'name' => is_array($provider) ? ($provider['name'] ?? '') : (string) $provider,
            ], 'Provider not configured');
        }

        $id        = (string) $provider['id'];
        $cache_key = self::TRANSIENT_PREFIX . md5($id);
        $cached    = get_transient($cache_key);
        if (!is_array($cached)) {
            $cached = null;
        }

        if ($cached && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            $payload              = $cached['data'];
            $payload['from_cache'] = true;
            return $payload;
        }

        $summary_url = $this->resolve_summary_url($provider);
        if (!$summary_url) {
            return $this->unknown_payload($provider, 'No status endpoint available');
        }

        $headers = $this->build_headers($cached);
        $args    = [
            'timeout'     => 10,
            'headers'     => $headers,
            'redirection' => 3,
        ];

        $response = wp_remote_get($summary_url, $args);
        if (is_wp_error($response)) {
            return $this->handle_failure($provider, $cached, $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if (304 === $status && $cached) {
            $cached['expires_at'] = time() + $this->determine_ttl($cached['headers'] ?? []);
            set_transient($cache_key, $cached, max(1, $cached['expires_at'] - time()));
            $payload               = $cached['data'];
            $payload['from_cache'] = true;
            return $payload;
        }

        if (200 !== $status) {
            if ('zscaler' === $id) {
                $fallback = $this->fetch_zscaler_fallback($provider, $cached);
                if ($fallback) {
                    return $fallback;
                }
            }

            $message = 'HTTP ' . $status;
            if (in_array($status, [401, 403, 404], true)) {
                $message .= ' from status endpoint';
            }

            return $this->handle_failure($provider, $cached, $message, $status);
        }

        $body = wp_remote_retrieve_body($response);
        if ('' === trim((string) $body)) {
            return $this->handle_failure($provider, $cached, 'Empty response body');
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return $this->handle_failure($provider, $cached, 'Invalid JSON payload');
        }

        $normalized = $this->normalize_statuspage_summary($provider, $decoded);
        $headers    = $this->harvest_headers($response);
        $ttl        = $this->determine_ttl($headers);

        $cache = [
            'data'       => $normalized,
            'etag'       => $headers['etag'] ?? null,
            'last_mod'   => $headers['last-modified'] ?? null,
            'headers'    => $headers,
            'stored_at'  => time(),
            'expires_at' => time() + $ttl,
        ];

        set_transient($cache_key, $cache, $ttl);

        return $normalized;
    }

    /**
     * Fetch summaries for all (or selected) providers.
     *
     * @param array|null $only Specific provider IDs to include.
     */
    public function get_all(?array $only = null): array {
        $providers = Providers::enabled();
        if (null !== $only) {
            $only      = array_map('strval', $only);
            $providers = array_intersect_key($providers, array_flip($only));
        }

        $results   = [];
        $errors    = [];
        $timestamps = [];

        foreach ($providers as $id => $provider) {
            $payload            = $this->fetch_summary($provider);
            $results[$id]       = $payload;
            $timestamps[]       = $payload['fetched_at'] ?? null;
            if (!empty($payload['error'])) {
                $errors[$id] = $payload['error'];
            }
        }

        $timestamps = array_filter($timestamps, static fn($value) => is_string($value) && '' !== $value);
        $fetched_at = $timestamps ? max($timestamps) : gmdate('c');

        return [
            'providers' => $results,
            'fetched_at'=> $fetched_at,
            'errors'    => $errors,
        ];
    }

    private function provider_from_id(string $id): ?array {
        $providers = Providers::enabled();
        if (isset($providers[$id])) {
            return $providers[$id];
        }

        $all = Providers::list();
        return $all[$id] ?? null;
    }

    private function resolve_summary_url(array $provider): ?string {
        $candidates = [];
        foreach (['summary', 'url', 'endpoint'] as $key) {
            if (!empty($provider[$key]) && is_string($provider[$key])) {
                $candidates[] = $provider[$key];
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ('' === $candidate) {
                continue;
            }

            return $candidate;
        }

        if (!empty($provider['status_domain'])) {
            return sprintf('https://%s/api/v2/summary.json', trim((string) $provider['status_domain']));
        }

        if (!empty($provider['id'])) {
            $map = [
                'okta'   => 'status.okta.com',
                'fastly' => 'status.fastly.com',
                'gitlab' => 'status.gitlab.com',
                'stripe' => 'status.stripe.com',
                'notion' => 'www.notionstatus.com',
            ];
            $id = strtolower((string) $provider['id']);
            if (isset($map[$id])) {
                return sprintf('https://%s/api/v2/summary.json', $map[$id]);
            }
        }

        return null;
    }

    private function resolve_status_url(array $provider): string {
        if (!empty($provider['status_url']) && is_string($provider['status_url'])) {
            return (string) $provider['status_url'];
        }
        if (!empty($provider['url']) && is_string($provider['url'])) {
            $url = (string) $provider['url'];
            $url = preg_replace('#/api/v\d+(?:\.\d+)*?/summary\.json$#', '/', $url);
            return $url ?: '';
        }
        if (!empty($provider['status_domain'])) {
            return 'https://' . trim((string) $provider['status_domain']) . '/';
        }
        if (!empty($provider['id'])) {
            $map = [
                'fastly'  => 'https://status.fastly.com/',
                'stripe'  => 'https://status.stripe.com/',
                'gitlab'  => 'https://status.gitlab.com/',
                'notion'  => 'https://www.notionstatus.com/',
                'okta'    => 'https://status.okta.com/',
                'zscaler' => 'https://trust.zscaler.com/',
            ];
            $id = strtolower((string) $provider['id']);
            if (isset($map[$id])) {
                return $map[$id];
            }
        }

        return '';
    }

    private function build_headers(?array $cached): array {
        $headers = [
            'User-Agent'      => self::USER_AGENT,
            'Accept'          => 'application/json',
            'Accept-Language' => 'en',
            'Cache-Control'   => 'no-cache',
        ];

        if ($cached) {
            if (!empty($cached['etag'])) {
                $headers['If-None-Match'] = (string) $cached['etag'];
            }
            if (!empty($cached['last_mod'])) {
                $headers['If-Modified-Since'] = (string) $cached['last_mod'];
            }
        }

        return $headers;
    }

    private function harvest_headers(array $response): array {
        $headers = [];
        $list    = wp_remote_retrieve_headers($response);
        if (is_array($list)) {
            foreach ($list as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $headers[strtolower((string) $key)] = (string) $value;
            }
        } elseif ($list instanceof \Requests_Utility_CaseInsensitiveDictionary) {
            foreach ($list->getAll() as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $headers[strtolower((string) $key)] = (string) $value;
            }
        }

        if (!isset($headers['etag'])) {
            $etag = wp_remote_retrieve_header($response, 'etag');
            if ($etag) {
                $headers['etag'] = (string) $etag;
            }
        }
        if (!isset($headers['last-modified'])) {
            $last = wp_remote_retrieve_header($response, 'last-modified');
            if ($last) {
                $headers['last-modified'] = (string) $last;
            }
        }

        return $headers;
    }

    private function determine_ttl(array $headers): int {
        $ttl = self::CACHE_TTL_DEFAULT;
        if (!empty($headers['cache-control'])) {
            $directives = $this->parse_cache_control($headers['cache-control']);
            if (isset($directives['max-age'])) {
                $ttl = (int) $directives['max-age'];
            }
        }

        if ($ttl < self::CACHE_TTL_MIN) {
            $ttl = self::CACHE_TTL_MIN;
        }
        if ($ttl > self::CACHE_TTL_MAX) {
            $ttl = self::CACHE_TTL_MAX;
        }

        return $ttl;
    }

    private function parse_cache_control(string $header): array {
        $parts = array_map('trim', explode(',', strtolower($header)));
        $directives = [];
        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }
            if (str_contains($part, '=')) {
                [$key, $value] = array_map('trim', explode('=', $part, 2));
                if ('max-age' === $key) {
                    $directives['max-age'] = (int) $value;
                }
            } else {
                $directives[$part] = true;
            }
        }

        return $directives;
    }

    private function normalize_statuspage_summary(array $provider, array $decoded): array {
        $status = strtolower((string) ($decoded['status']['indicator'] ?? 'unknown'));
        $overall = $this->map_indicator($status);
        $description = $decoded['status']['description'] ?? '';
        $summary     = $this->clean_text($description) ?: $this->status_label($overall);

        $components = [];
        if (!empty($decoded['components']) && is_array($decoded['components'])) {
            foreach ($decoded['components'] as $component) {
                if (!is_array($component)) {
                    continue;
                }
                $components[] = [
                    'id'           => (string) ($component['id'] ?? ''),
                    'name'         => $this->clean_text($component['name'] ?? ''),
                    'status'       => $this->map_indicator(strtolower((string) ($component['status'] ?? 'unknown'))),
                    'status_label' => $this->status_label($component['status'] ?? ''),
                ];
            }
        }

        $incidents = [];
        if (!empty($decoded['incidents']) && is_array($decoded['incidents'])) {
            foreach ($decoded['incidents'] as $incident) {
                if (!is_array($incident)) {
                    continue;
                }
                $updates = [];
                if (!empty($incident['incident_updates']) && is_array($incident['incident_updates'])) {
                    $updates = $incident['incident_updates'];
                }
                $latestUpdate = [];
                if ($updates) {
                    $latestUpdate = $updates[0];
                }
                $updateSummary = '';
                if (is_array($latestUpdate)) {
                    $updateSummary = $this->clean_text((string) ($latestUpdate['body'] ?? ''));
                }
                if ('' === $updateSummary && !empty($incident['name'])) {
                    $updateSummary = $this->clean_text((string) $incident['name']);
                }

                $incidents[] = [
                    'id'          => (string) ($incident['id'] ?? ''),
                    'name'        => $this->clean_text($incident['name'] ?? ''),
                    'status'      => $this->map_indicator(strtolower((string) ($incident['status'] ?? 'unknown'))),
                    'impact'      => strtolower((string) ($incident['impact'] ?? 'unknown')),
                    'summary'     => $updateSummary,
                    'updated_at'  => $incident['updated_at'] ?? ($latestUpdate['updated_at'] ?? null),
                    'started_at'  => $incident['started_at'] ?? ($incident['created_at'] ?? null),
                    'url'         => $incident['shortlink'] ?? $incident['url'] ?? $this->resolve_status_url($provider),
                ];
            }
        }

        $fetched = gmdate('c');

        return [
            'provider'        => (string) $provider['id'],
            'name'            => $provider['name'] ?? (string) $provider['id'],
            'overall_status'  => $overall,
            'status_label'    => $this->status_label($overall),
            'summary'         => $summary,
            'components'      => $components,
            'incidents'       => $incidents,
            'fetched_at'      => $fetched,
            'url'             => $this->resolve_status_url($provider),
            'error'           => null,
            'from_cache'      => false,
        ];
    }

    private function fetch_zscaler_fallback(array $provider, ?array $cached): ?array {
        $base = rtrim($this->resolve_status_url($provider), '/') . '/';
        if (!$base) {
            $base = 'https://trust.zscaler.com/';
        }

        $feed_urls = [
            $base . 'history.atom',
            $base . 'history.rss',
        ];

        foreach ($feed_urls as $url) {
            $response = wp_remote_get($url, [
                'timeout'     => 8,
                'redirection' => 3,
                'headers'     => [
                    'User-Agent'      => self::USER_AGENT,
                    'Accept'          => 'application/atom+xml, application/rss+xml, application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en',
                ],
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            if (200 !== $status) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            if (! $body) {
                continue;
            }

            $items = $this->parse_feed_items($body);
            if (null === $items) {
                continue;
            }

            $payload = [
                'provider'       => (string) $provider['id'],
                'name'           => $provider['name'] ?? (string) $provider['id'],
                'overall_status' => 'unknown',
                'status_label'   => $this->status_label('unknown'),
                'summary'        => 'Zscaler status feed (fallback)',
                'components'     => [],
                'incidents'      => $items,
                'fetched_at'     => gmdate('c'),
                'url'            => $this->resolve_status_url($provider),
                'error'          => null,
                'from_cache'     => false,
            ];

            return $payload;
        }

        if ($cached) {
            $payload = $cached['data'];
            $payload['error'] = 'Status API unavailable (fallback to cache)';
            $payload['from_cache'] = true;
            return $payload;
        }

        return $this->unknown_payload($provider, 'Status unavailable');
    }

    private function parse_feed_items(string $xml): ?array {
        if (!class_exists('SimpleXMLElement')) {
            return null;
        }
        try {
            $feed = new \SimpleXMLElement($xml);
        } catch (\Throwable $e) {
            return null;
        }

        $items = [];
        if (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $items[] = [
                    'id'         => (string) ($entry->id ?? ''),
                    'name'       => $this->clean_text((string) ($entry->title ?? 'Incident')),
                    'status'     => 'unknown',
                    'impact'     => 'unknown',
                    'summary'    => $this->clean_text((string) ($entry->summary ?? $entry->content ?? '')),
                    'updated_at' => (string) ($entry->updated ?? ''),
                    'started_at' => (string) ($entry->published ?? ''),
                    'url'        => (string) ($entry->link['href'] ?? ''),
                ];
            }
        } elseif (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = [
                    'id'         => md5((string) ($item->link ?? $item->title ?? '')), 
                    'name'       => $this->clean_text((string) ($item->title ?? 'Incident')),
                    'status'     => 'unknown',
                    'impact'     => 'unknown',
                    'summary'    => $this->clean_text((string) ($item->description ?? '')),
                    'updated_at' => (string) ($item->pubDate ?? ''),
                    'started_at' => (string) ($item->pubDate ?? ''),
                    'url'        => (string) ($item->link ?? ''),
                ];
            }
        }

        return $items;
    }

    private function handle_failure(array $provider, ?array $cached, string $message, int $status = 0): array {
        if ($cached) {
            $payload = $cached['data'];
            $payload['error'] = $message;
            $payload['from_cache'] = true;
            return $payload;
        }

        $payload = $this->unknown_payload($provider, $message);
        if ($status >= 500) {
            $payload['overall_status'] = 'degraded';
            $payload['status_label']   = $this->status_label('degraded');
            $payload['summary']        = 'Provider status endpoint error';
        }

        return $payload;
    }

    private function unknown_payload(array $provider, string $message): array {
        $name = $provider['name'] ?? ($provider['provider'] ?? ($provider['id'] ?? 'Provider'));
        return [
            'provider'       => (string) ($provider['id'] ?? ''),
            'name'           => (string) $name,
            'overall_status' => 'unknown',
            'status_label'   => $this->status_label('unknown'),
            'summary'        => $this->clean_text($message ?: 'Status unknown'),
            'components'     => [],
            'incidents'      => [],
            'fetched_at'     => gmdate('c'),
            'url'            => $this->resolve_status_url($provider),
            'error'          => $this->clean_text($message),
            'from_cache'     => false,
        ];
    }

    private function clean_text(string $value): string {
        $value = wp_strip_all_tags($value, true);
        return trim($value);
    }

    private function map_indicator(string $indicator): string {
        $indicator = strtolower($indicator);
        return match ($indicator) {
            'none', 'operational', 'ok', 'green' => 'operational',
            'maintenance', 'maintainance' => 'maintenance',
            'minor', 'minor_outage', 'degraded', 'partial_outage', 'warning' => 'degraded',
            'major', 'major_outage', 'critical', 'outage' => 'outage',
            default => 'unknown',
        };
    }

    private function status_label(string $status): string {
        return match (strtolower($status)) {
            'operational' => 'Operational',
            'degraded'    => 'Degraded',
            'outage'      => 'Outage',
            'maintenance' => 'Maintenance',
            default       => 'Unknown',
        };
    }
}
