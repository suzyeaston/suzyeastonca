<?php
declare(strict_types=1);

namespace LousyOutages;

use LousyOutages\Email\Composer;
use LousyOutages\Model\Incident;
use LousyOutages\Mailer;
use LousyOutages\Providers;
use LousyOutages\Sources\Sources;
use LousyOutages\Sources\StatuspageSource;
use LousyOutages\Storage\IncidentStore;
use WP_REST_Request;

class IncidentAlerts {
    private const SUMMARY_PROVIDERS = [
        'github'    => 'https://www.githubstatus.com/api/v2/summary.json',
        'atlassian' => 'https://status.atlassian.com/api/v2/summary.json',
        'vercel'    => 'https://www.vercel-status.com/api/v2/summary.json',
        'digitalocean' => 'https://status.digitalocean.com/api/v2/summary.json',
    ];

    private const RSS_PROVIDERS = [
        'cloudflare' => 'https://www.cloudflarestatus.com/history.rss',
        'aws'        => 'https://status.aws.amazon.com/rss/all.rss',
        'azure'      => [
            'https://rssfeed.azure.status.microsoft/en-us/status/feed/',
            'https://azurestatuscdn.azureedge.net/en-us/status/feed/',
        ],
    ];

    private const RSS_HEADERS = [
        'User-Agent'    => 'LousyOutages/1.2 (+https://suzyeaston.ca)',
        'Accept'        => 'application/rss+xml, application/atom+xml;q=0.9,*/*;q=0.8',
        'Cache-Control' => 'no-cache',
    ];

    private const ALERTABLE_STATES = ['degraded', 'partial_outage', 'major_outage', 'maintenance', 'major', 'outage'];

    private const OPTION_SUBSCRIBERS      = 'lo_subscribers';
    private const OPTION_UNSUB_TOKENS     = 'lo_unsub_tokens';
    private const OPTION_SEEN             = 'lo_seen_incidents';
    private const OPTION_LAST_CHECK       = 'lo_last_status_check';

    public static function bootstrap(): void {
        add_action('init', [self::class, 'ensure_schedule']);
        add_action('init', [self::class, 'maybe_trigger_fallback'], 20);
        add_action('admin_notices', [self::class, 'render_admin_notice']);
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function ensure_schedule(): void {
        if (!wp_next_scheduled('lo_check_statuses')) {
            wp_schedule_event(time() + 60, 'lo_five_minutes', 'lo_check_statuses');
            do_action('lousy_outages_log', 'cron_scheduled', ['hook' => 'lo_check_statuses']);
        }
    }

    public static function maybe_trigger_fallback(): void {
        if (wp_doing_cron()) {
            return;
        }

        $next = wp_next_scheduled('lo_check_statuses');
        if ($next) {
            return;
        }

        $last = get_option(self::OPTION_LAST_CHECK);
        $last_ts = $last ? strtotime((string) $last) : 0;
        if ($last_ts && (time() - $last_ts) < 15 * MINUTE_IN_SECONDS) {
            return;
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            do_action('lousy_outages_log', 'cron_disabled', ['hook' => 'lo_check_statuses']);
            return;
        }

        wp_schedule_single_event(time() + 60, 'lo_check_statuses');
        do_action('lousy_outages_log', 'cron_fallback_scheduled', ['hook' => 'lo_check_statuses']);
    }

    public static function register_routes(): void {
        register_rest_route(
            'lousy-outages/v1',
            '/unsubscribe-email',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_unsubscribe'],
                'args'                => [
                    'email' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => 'is_email',
                    ],
                    'token' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    public static function handle_unsubscribe(WP_REST_Request $request) {
        $rawEmail = $request->get_param('email');
        $emailRaw = is_string($rawEmail) ? rawurldecode($rawEmail) : '';
        $email    = is_string($emailRaw) ? sanitize_email($emailRaw) : '';
        $token    = sanitize_text_field((string) $request->get_param('token'));

        if (!$email || !is_email($email) || '' === $token) {
            return self::redirect_with_status('invalid');
        }

        $saved = self::get_saved_unsubscribe_token($email);
        if ('' === $saved || !hash_equals($saved, $token)) {
            return self::redirect_with_status('invalid');
        }

        self::remove_subscriber($email);
        Subscriptions::mark_unsubscribed_by_email($email);

        return self::redirect_with_status('unsubscribed');
    }

    private static function redirect_with_status(string $status) {
        $status = sanitize_key($status);
        $target = add_query_arg('sub', rawurlencode($status), home_url('/lousy-outages/'));
        status_header(302);
        wp_safe_redirect($target);
        exit;
    }

    public static function run(): void {
        do_action('lousy_outages_log', 'lo_check_statuses_run', ['ts' => gmdate('c')]);

        $store     = new IncidentStore();
        $incidents = self::collect_incidents();
        $store->persistIncidents($incidents);
        update_option(self::OPTION_LAST_CHECK, gmdate('c'), false);

        if (empty($incidents)) {
            return;
        }

        $eligible = [];
        foreach ($incidents as $incident) {
            if (! $incident instanceof Incident) {
                continue;
            }

            if ($store->shouldSend($incident)) {
                $store->pushDigestCandidate($incident);
                $eligible[] = $incident;
            }
        }

        if (empty($eligible)) {
            return;
        }

        if (count($eligible) > 3) {
            if (self::email_digest($eligible)) {
                $store->recordDigest($eligible);
                $keys = array_map(function (Incident $incident) use ($store): string {
                    return $store->keyFor($incident);
                }, $eligible);
                $store->clearDigestEntries($keys);
            }
            return;
        }

        foreach ($eligible as $incident) {
            self::email_incident($incident);
        }
    }

    /**
     * @return Incident[]
     */
    public static function collect_incidents(): array {
        $combined = [];

        foreach (self::collect_from_registered_sources() as $incident) {
            $normalized = self::normalize_incident($incident);
            if ($normalized instanceof Incident) {
                $combined[$normalized->provider . '|' . $normalized->id] = $normalized;
            }
        }

        foreach (self::collect_from_snapshot() as $incident) {
            $normalized = self::normalize_incident($incident);
            if ($normalized instanceof Incident) {
                $combined[$normalized->provider . '|' . $normalized->id] = $normalized;
            }
        }

        foreach (self::collect_from_legacy_feeds() as $incident) {
            $normalized = self::normalize_incident($incident);
            if ($normalized instanceof Incident) {
                $combined[$normalized->provider . '|' . $normalized->id] = $normalized;
            }
        }

        return array_values($combined);
    }

    /**
     * @return array<int, Incident>
     */
    private static function collect_from_registered_sources(): array {
        $incidents = [];

        foreach (Sources::all() as $slug => $source) {
            if (! $source instanceof StatuspageSource) {
                continue;
            }

            $payload = $source->fetch();
            $status  = isset($payload['status']) ? (string) $payload['status'] : 'operational';
            $updated = isset($payload['updated']) ? (int) $payload['updated'] : time();

            foreach ($payload['incidents'] as $incident) {
                if (! $incident instanceof Incident) {
                    continue;
                }

                $incidents[] = new Incident(
                    self::provider_label($slug, $incident->provider),
                    $slug . ':' . $incident->id,
                    $incident->title,
                    $incident->status,
                    $incident->url ?: self::provider_url($slug),
                    $incident->component,
                    $incident->impact,
                    $incident->detected_at,
                    $incident->resolved_at
                );
            }

            if (empty($payload['incidents']) && in_array($status, ['degraded', 'partial_outage', 'major_outage', 'maintenance'], true)) {
                $label = self::provider_label($slug, '');
                $incidents[] = new Incident(
                    $label,
                    $slug . ':status:' . md5($status . '|' . $updated),
                    sprintf('%s status: %s', $label, ucfirst(str_replace('_', ' ', $status))),
                    $status,
                    self::provider_url($slug),
                    null,
                    self::impact_from_status($status),
                    $updated,
                    null
                );
            }
        }

        return $incidents;
    }

    private static function normalize_incident($incident): ?Incident {
        if ($incident instanceof Incident) {
            return $incident;
        }

        if (! is_array($incident)) {
            return null;
        }

        return self::incident_from_array($incident);
    }

    private static function incident_from_array(array $data): ?Incident {
        $provider = '';
        if (isset($data['provider'])) {
            $provider = (string) $data['provider'];
        } elseif (isset($data['service'])) {
            $provider = sanitize_key((string) $data['service']);
        }

        if ('' === $provider && isset($data['id']) && is_string($data['id'])) {
            $parts = explode(':', $data['id']);
            if (count($parts) > 1) {
                $provider = (string) array_shift($parts);
            }
        }

        $provider = sanitize_key($provider);
        if ('' === $provider) {
            return null;
        }

        $id = isset($data['id']) ? (string) $data['id'] : md5($provider . '|' . wp_json_encode($data));

        $title = '';
        foreach (['title', 'name', 'summary', 'body'] as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                $title = trim((string) $data[$field]);
                if ('' !== $title) {
                    break;
                }
            }
        }
        if ('' === $title && isset($data['status']) && is_string($data['status'])) {
            $title = trim((string) $data['status']);
        }
        if ('' === $title) {
            $title = 'Incident';
        }

        $statusRaw = '';
        foreach (['status', 'state', 'status_code'] as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                $statusRaw = (string) $data[$field];
                break;
            }
        }
        $impactRaw = isset($data['impact']) ? (string) $data['impact'] : '';

        $status = self::normalize_status_code($statusRaw, $impactRaw);
        $impact = self::normalize_impact($impactRaw ?: self::impact_from_status($status));

        $url = '';
        foreach (['url', 'shortlink', 'link'] as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                $url = (string) $data[$field];
                break;
            }
        }
        if ('' === $url) {
            $url = self::provider_url($provider);
        }

        $components = self::components_to_string($data['components'] ?? null);

        $detected = self::to_epoch($data['detected_at'] ?? $data['started_at'] ?? $data['startedAt'] ?? $data['timestamp'] ?? $data['updated_at'] ?? null) ?? time();
        $resolved = null;
        if ('resolved' === $status) {
            $resolved = self::to_epoch($data['resolved_at'] ?? $data['updated_at'] ?? null) ?? $detected;
        }

        return new Incident(
            self::provider_label($provider, isset($data['service']) ? (string) $data['service'] : ''),
            $id,
            $title,
            $status,
            $url,
            $components,
            $impact,
            $detected,
            $resolved
        );
    }

    private static function normalize_status_code(string $status, string $impact): string {
        $status = self::slugify_status($status);
        $impact = self::slugify_status($impact);

        if ('resolved' === $status || 'postmortem' === $status) {
            return 'resolved';
        }

        if ('operational' === $status || 'none' === $status) {
            return 'operational';
        }

        if ('maintenance' === $status) {
            return 'maintenance';
        }

        switch ($status) {
            case 'minor':
            case 'degraded':
            case 'investigating':
            case 'identified':
            case 'monitoring':
            case 'in_progress':
            case 'warning':
                return 'degraded';
            case 'partial':
            case 'partial_outage':
            case 'major':
                return 'partial_outage';
            case 'major_outage':
            case 'outage':
            case 'critical':
                return 'major_outage';
        }

        switch ($impact) {
            case 'critical':
                return 'major_outage';
            case 'major':
                return 'partial_outage';
            case 'maintenance':
                return 'maintenance';
            case 'minor':
                return 'degraded';
        }

        return 'degraded';
    }

    private static function normalize_impact(?string $impact): ?string {
        $impact = self::slugify_status((string) $impact);
        if ('' === $impact) {
            return null;
        }

        if (in_array($impact, ['minor', 'major', 'critical', 'none'], true)) {
            return $impact;
        }

        switch ($impact) {
            case 'maintenance':
                return 'none';
            case 'partial':
            case 'partial_outage':
            case 'major_outage':
            case 'outage':
                return 'critical';
            case 'degraded':
            case 'investigating':
            case 'identified':
            case 'monitoring':
            case 'warning':
                return 'minor';
            default:
                return 'minor';
        }
    }

    private static function impact_from_status(string $status): string {
        switch ($status) {
            case 'major_outage':
                return 'critical';
            case 'partial_outage':
                return 'major';
            case 'maintenance':
            case 'resolved':
            case 'operational':
                return 'none';
            default:
                return 'minor';
        }
    }

    private static function to_epoch($value): ?int {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed) {
                return null;
            }
            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }
            $time = strtotime($trimmed);
            if (false !== $time) {
                return $time;
            }
        }

        return null;
    }

    private static function components_to_string($components): ?string {
        if (empty($components)) {
            return null;
        }

        $names = [];
        if (is_array($components)) {
            foreach ($components as $component) {
                if (is_array($component) && isset($component['name'])) {
                    $names[] = (string) $component['name'];
                } elseif (is_string($component)) {
                    $names[] = $component;
                }
            }
        } elseif (is_string($components)) {
            $names[] = $components;
        }

        $names = array_values(array_filter(array_map('trim', $names)));
        if (empty($names)) {
            return null;
        }

        return implode(', ', array_unique($names));
    }

    private static function provider_label(string $slug, string $fallback = ''): string {
        static $providers;
        if (null === $providers) {
            $providers = Providers::list();
        }

        if (isset($providers[$slug]['name'])) {
            return (string) $providers[$slug]['name'];
        }

        if ('' !== $fallback) {
            return $fallback;
        }

        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    private static function provider_url(string $slug): string {
        static $providers;
        if (null === $providers) {
            $providers = Providers::list();
        }

        if (isset($providers[$slug]['status_url']) && is_string($providers[$slug]['status_url'])) {
            return (string) $providers[$slug]['status_url'];
        }

        if (isset($providers[$slug]['url']) && is_string($providers[$slug]['url'])) {
            return (string) $providers[$slug]['url'];
        }

        return (string) home_url('/lousy-outages/');
    }

    private static function slugify_status(string $status): string {
        $status = strtolower(trim($status));
        if ('' === $status) {
            return '';
        }

        $slug = preg_replace('/[^a-z0-9]+/', '_', $status);
        if (null === $slug) {
            return $status;
        }

        return trim($slug, '_');
    }

    private static function status_label(string $status): string {
        $status = self::slugify_status($status);
        $map = [
            'degraded'       => 'Degraded',
            'partial_outage' => 'Partial Outage',
            'major_outage'   => 'Major Outage',
            'maintenance'    => 'Maintenance',
            'resolved'       => 'Resolved',
            'operational'    => 'Operational',
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        return ucfirst(str_replace('_', ' ', $status ?: 'Status'));
    }

    private static function collect_from_legacy_feeds(): array {
        $incidents = [];

        foreach (self::SUMMARY_PROVIDERS as $provider => $url) {
            $result = self::fetch_statuspage($provider, $url);
            if (!empty($result)) {
                $incidents = array_merge($incidents, $result);
            }
        }

        foreach (self::RSS_PROVIDERS as $provider => $endpoints) {
            $urls = is_array($endpoints) ? array_filter(array_map('strval', $endpoints)) : [(string) $endpoints];
            $result = self::fetch_rss($provider, $urls);
            if (!empty($result)) {
                $incidents = array_merge($incidents, $result);
            }
        }

        return $incidents;
    }

    private static function collect_from_snapshot(): array {
        $snapshot  = \lousy_outages_get_snapshot(true);
        $providers = [];
        $fetchedAt = gmdate('c');

        if (is_array($snapshot)) {
            if (!empty($snapshot['providers']) && is_array($snapshot['providers'])) {
                $providers = $snapshot['providers'];
            }
            if (!empty($snapshot['fetched_at']) && is_string($snapshot['fetched_at'])) {
                $fetchedAt = (string) $snapshot['fetched_at'];
            }
        }

        if (empty($providers)) {
            $states = \lousy_outages_collect_statuses(true);
            $stored = get_option('lousy_outages_last_poll');
            if (is_string($stored) && '' !== trim($stored)) {
                $fetchedAt = $stored;
            }
            foreach ($states as $id => $state) {
                if (!is_array($state)) {
                    continue;
                }
                $providers[] = \lousy_outages_build_provider_payload((string) $id, $state, $fetchedAt);
            }
        }

        if (empty($providers)) {
            return [];
        }

        $incidents = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $providerId = isset($provider['id']) ? (string) $provider['id'] : '';
            if ('' === $providerId) {
                continue;
            }

            $providerName = (string) ($provider['name'] ?? $providerId);
            $statusCode   = strtolower((string) ($provider['stateCode'] ?? 'unknown'));
            $statusLabel  = (string) ($provider['state'] ?? ($statusCode ? ucfirst($statusCode) : 'Status change'));
            if ('' === $statusLabel) {
                $statusLabel = $statusCode ? ucfirst($statusCode) : 'Status change';
            }
            $summary    = (string) ($provider['summary'] ?? $statusLabel);
            $updatedAt  = isset($provider['updatedAt']) ? (string) $provider['updatedAt'] : $fetchedAt;
            $url        = isset($provider['url']) ? (string) $provider['url'] : '';
            $components = isset($provider['components']) && is_array($provider['components']) ? $provider['components'] : [];
            $incidentList = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];

            if (!empty($incidentList)) {
                foreach ($incidentList as $incident) {
                    if (!is_array($incident)) {
                        continue;
                    }
                    $rawId = isset($incident['id']) ? (string) $incident['id'] : '';
                    if ('' === $rawId) {
                        $rawId = md5($providerId . wp_json_encode($incident));
                    }
                    $incidentId      = $providerId . ':' . $rawId;
                    $incidentSummary = (string) ($incident['summary'] ?? $summary);
                    $impact          = strtolower((string) ($incident['impact'] ?? ($statusCode ?: 'major')));

                    $incidents[$incidentId] = [
                        'id'         => $incidentId,
                        'provider'   => $providerId,
                        'name'       => (string) ($incident['title'] ?? $incidentSummary ?: 'Incident'),
                        'impact'     => $impact,
                        'status'     => $statusLabel,
                        'started_at' => self::normalize_timestamp($incident['startedAt'] ?? $incident['started_at'] ?? $updatedAt, $updatedAt),
                        'url'        => (string) ($incident['url'] ?? $url),
                        'components' => $components,
                        'body'       => $incidentSummary,
                    ];
                }
                continue;
            }

            if (!in_array($statusCode, self::ALERTABLE_STATES, true)) {
                continue;
            }

            $statusId = $providerId . ':status:' . md5($statusCode . '|' . $summary . '|' . $updatedAt);
            $incidents[$statusId] = [
                'id'         => $statusId,
                'provider'   => $providerId,
                'name'       => sprintf('%s status: %s', $providerName, $statusLabel),
                'impact'     => $statusCode,
                'status'     => $statusLabel,
                'started_at' => self::normalize_timestamp($updatedAt, $fetchedAt),
                'url'        => $url,
                'components' => $components,
                'body'       => $summary,
            ];
        }

        return array_values($incidents);
    }

    private static function normalize_timestamp($value, string $fallback): string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_int($value) || is_float($value)) {
            $timestamp = (int) round((float) $value);
            if ($timestamp > 0) {
                return gmdate('c', $timestamp);
            }
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ('' !== $trimmed) {
                return $trimmed;
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetch_statuspage(string $provider, string $url): array {
        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response)) {
            self::log_error($provider, $response->get_error_message());
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            self::log_error($provider, 'HTTP ' . $code);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            self::log_error($provider, 'invalid JSON payload');
            return [];
        }

        $componentNames = [];
        if (!empty($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as $component) {
                if (isset($component['id'], $component['name'])) {
                    $componentNames[(string) $component['id']] = (string) $component['name'];
                }
            }
        }

        $incidents = [];
        $entries   = isset($data['incidents']) && is_array($data['incidents']) ? $data['incidents'] : [];

        foreach ($entries as $incident) {
            $impact = strtolower((string) ($incident['impact'] ?? ''));
            if (!in_array($impact, ['major', 'critical'], true)) {
                continue;
            }

            $components = [];
            if (!empty($incident['components']) && is_array($incident['components'])) {
                foreach ($incident['components'] as $component) {
                    if (is_array($component)) {
                        if (!empty($component['name']) && is_string($component['name'])) {
                            $components[] = trim($component['name']);
                        } elseif (!empty($component['id'])) {
                            $id = (string) $component['id'];
                            if (isset($componentNames[$id])) {
                                $components[] = $componentNames[$id];
                            }
                        }
                    } elseif (is_string($component) && isset($componentNames[$component])) {
                        $components[] = $componentNames[$component];
                    }
                }
            }

            $components = array_values(array_unique(array_filter($components)));

            $incidents[] = [
                'id'         => $provider . ':' . (string) ($incident['id'] ?? wp_hash(wp_json_encode($incident))),
                'provider'   => $provider,
                'name'       => (string) ($incident['name'] ?? 'Unknown incident'),
                'impact'     => $impact,
                'components' => $components,
                'started_at' => (string) ($incident['started_at'] ?? $incident['created_at'] ?? ''),
                'url'        => (string) ($incident['shortlink'] ?? $incident['url'] ?? ''),
            ];
        }

        return $incidents;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetch_rss(string $provider, array $urls): array {
        $errors = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if ('' === $url) {
                continue;
            }

            [$incidents, $error] = self::fetch_rss_from_url($provider, $url);

            if ($incidents) {
                return $incidents;
            }

            if ($error) {
                $errors[] = $error;
            }
        }

        if ($errors) {
            $unique = array_values(array_unique($errors));
            self::log_error($provider, implode(' | ', $unique));
        }

        return [];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private static function fetch_rss_from_url(string $provider, string $url): array {
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => self::RSS_HEADERS,
        ]);

        if (is_wp_error($response)) {
            return [[], $response->get_error_message() . ' (' . $url . ')'];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return [[], 'HTTP ' . $code . ' (' . $url . ')'];
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === trim($body)) {
            return [[], 'empty RSS body (' . $url . ')'];
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            return [[], 'unable to parse RSS (' . $url . ')'];
        }

        $items = [];
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->entry)) {
            $items = $xml->entry;
        }

        $incidents = [];
        $keywords  = ['major outage', 'critical', 'service disruption', 'service issue', 'service interruption'];

        foreach ($items as $item) {
            $title = isset($item->title) ? strtolower((string) $item->title) : '';
            $desc  = isset($item->description) ? strtolower((string) $item->description) : '';
            $match = false;
            foreach ($keywords as $keyword) {
                if (false !== strpos($title, $keyword) || false !== strpos($desc, $keyword)) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                continue;
            }

            $guid = isset($item->guid) ? (string) $item->guid : '';
            if (!$guid && isset($item->id)) {
                $guid = (string) $item->id;
            }

            $link = '';
            if (isset($item->link)) {
                $link = (string) $item->link;
                if (is_object($item->link) && isset($item->link['href'])) {
                    $link = (string) $item->link['href'];
                }
            }

            $started = '';
            if (isset($item->pubDate)) {
                $started = (string) $item->pubDate;
            } elseif (isset($item->updated)) {
                $started = (string) $item->updated;
            }

            $incidents[] = [
                'id'         => $provider . ':' . hash('sha256', $guid ?: $link ?: (string) ($item->title ?? 'incident')),
                'provider'   => $provider,
                'name'       => (string) ($item->title ?? 'Incident'),
                'impact'     => 'major',
                'components' => [],
                'started_at' => $started,
                'url'        => $link,
            ];
        }

        return [$incidents, ''];
    }

    public static function add_subscriber(string $email): void {
        $email = strtolower(sanitize_email($email));
        if (!$email || !is_email($email)) {
            return;
        }

        $subscribers = get_option(self::OPTION_SUBSCRIBERS, []);
        if (!is_array($subscribers)) {
            $subscribers = [];
        }

        if (!in_array($email, $subscribers, true)) {
            $subscribers[] = $email;
            update_option(self::OPTION_SUBSCRIBERS, array_values($subscribers), false);
        }

        self::build_unsubscribe_token($email);
    }

    public static function remove_subscriber(string $email): void {
        $email = strtolower(sanitize_email($email));
        if (!$email) {
            return;
        }

        $subscribers = get_option(self::OPTION_SUBSCRIBERS, []);
        if (!is_array($subscribers) || empty($subscribers)) {
            return;
        }

        $updated = array_values(array_filter($subscribers, static function ($item) use ($email) {
            return strtolower((string) $item) !== $email;
        }));

        if ($updated !== $subscribers) {
            update_option(self::OPTION_SUBSCRIBERS, $updated, false);
        }

        self::delete_unsubscribe_token($email);
    }

    public static function get_subscribers(): array {
        $subscribers = get_option(self::OPTION_SUBSCRIBERS, []);
        if (!is_array($subscribers)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_email', $subscribers)));
    }

    private static function normalize_unsubscribe_email(string $email): string {
        $normalized = strtolower(trim((string) sanitize_email($email)));
        return $normalized;
    }

    private static function get_unsubscribe_tokens(): array {
        $tokens = get_option(self::OPTION_UNSUB_TOKENS, []);
        if (!is_array($tokens)) {
            return [];
        }

        $sanitized = [];
        foreach ($tokens as $rawEmail => $rawToken) {
            if (!is_string($rawToken) || '' === trim($rawToken)) {
                continue;
            }

            $normalized = self::normalize_unsubscribe_email((string) $rawEmail);
            if ('' === $normalized) {
                continue;
            }

            $sanitized[$normalized] = (string) $rawToken;
        }

        if ($sanitized !== $tokens) {
            self::save_unsubscribe_tokens($sanitized);
        }

        return $sanitized;
    }

    private static function save_unsubscribe_tokens(array $tokens): void {
        $sanitized = [];
        foreach ($tokens as $email => $token) {
            if (!is_string($token) || '' === trim($token)) {
                continue;
            }
            $normalized = self::normalize_unsubscribe_email((string) $email);
            if ('' === $normalized) {
                continue;
            }
            $sanitized[$normalized] = (string) $token;
        }

        update_option(self::OPTION_UNSUB_TOKENS, $sanitized, false);
    }

    public static function get_saved_unsubscribe_token(string $email): string {
        $normalized = self::normalize_unsubscribe_email($email);
        if ('' === $normalized) {
            return '';
        }

        $tokens = self::get_unsubscribe_tokens();
        $value  = $tokens[$normalized] ?? '';

        return is_string($value) ? (string) $value : '';
    }

    public static function delete_unsubscribe_token(string $email): void {
        $normalized = self::normalize_unsubscribe_email($email);
        if ('' === $normalized) {
            return;
        }

        $tokens = self::get_unsubscribe_tokens();
        if (!isset($tokens[$normalized])) {
            return;
        }

        unset($tokens[$normalized]);
        self::save_unsubscribe_tokens($tokens);
    }

    public static function build_unsubscribe_token(string $email): string {
        $normalized = self::normalize_unsubscribe_email($email);
        if ('' === $normalized) {
            return '';
        }

        $tokens = self::get_unsubscribe_tokens();
        if (!isset($tokens[$normalized]) || '' === trim((string) $tokens[$normalized])) {
            $tokens[$normalized] = wp_generate_uuid4();
            self::save_unsubscribe_tokens($tokens);
        }

        return (string) $tokens[$normalized];
    }

    private static function email_incident(Incident $incident): bool {
        $subscribers = self::get_subscribers();
        if (empty($subscribers)) {
            return false;
        }

        $provider      = $incident->provider;
        $statusLabel   = self::status_label($incident->status);
        $impact        = $incident->impact ?? self::impact_from_status($incident->status);
        $timestamp     = gmdate('c', $incident->detected_at);
        $components    = $incident->component ? array_map('trim', explode(',', $incident->component)) : [];
        $componentLine = $incident->component ?: 'All monitored components';
        $url           = $incident->url ?: self::provider_url(sanitize_key($provider));
        $summary       = $incident->title;

        foreach ($subscribers as $email) {
            $token = self::build_unsubscribe_token($email);
            $unsubscribe = add_query_arg(
                [
                    'lo_unsub' => 1,
                    'email'    => rawurlencode($email),
                    'token'    => $token,
                ],
                home_url('/lousy-outages/')
            );

            $incident_payload = [
                'service'         => $provider,
                'status'          => $incident->status,
                'impact'          => $impact,
                'summary'         => $summary,
                'notes'           => '',
                'timestamp'       => $timestamp,
                'components'      => $componentLine,
                'components_list' => $components,
                'url'             => $url,
                'unsubscribe_url' => $unsubscribe,
            ];

            $sent = send_lo_outage_alert_email($email, $incident_payload);

            if (! $sent) {
                self::log_error($provider, 'mail send failed for ' . $email);
                return false;
            }
        }

        return true;
    }

    /**
     * @param Incident[] $incidents
     */
    private static function email_digest(array $incidents): bool {
        $subscribers = self::get_subscribers();
        if (empty($subscribers)) {
            return false;
        }

        $items = [];
        foreach ($incidents as $incident) {
            if (! $incident instanceof Incident) {
                continue;
            }

            $items[] = [
                'provider' => $incident->provider,
                'title'    => Composer::shortTitle($incident->title),
                'status'   => self::status_label($incident->status),
                'url'      => $incident->url ?: self::provider_url(sanitize_key($incident->provider)),
            ];
        }

        if (empty($items)) {
            return false;
        }

        $count   = count($items);
        $subject = sprintf('[Outage Digest] %d incidents in the last 15 minutes', $count);

        $textLines = ['Multiple outage signals fired:', ''];
        foreach ($items as $item) {
            $textLines[] = sprintf('- %s: %s (%s) → %s', $item['provider'], $item['title'], $item['status'], $item['url']);
        }
        $textLines[] = '';
        $textLines[] = 'Stay sharp — Lousy Outages';
        $textBody = implode("\n", $textLines);

        $htmlItems = [];
        foreach ($items as $item) {
            $htmlItems[] = sprintf(
                '<li><strong>%s:</strong> %s <em>(%s)</em> — <a href="%s">View status</a></li>',
                esc_html($item['provider']),
                esc_html($item['title']),
                esc_html($item['status']),
                esc_url($item['url'])
            );
        }

        $htmlBody = sprintf(
            '<p>Multiple outage alerts fired within the last fifteen minutes:</p><ul>%s</ul><p>Stay sharp — Lousy Outages</p>',
            implode('', $htmlItems)
        );

        foreach ($subscribers as $email) {
            $token = self::build_unsubscribe_token($email);
            $unsubscribe = add_query_arg(
                [
                    'lo_unsub' => 1,
                    'email'    => rawurlencode($email),
                    'token'    => $token,
                ],
                home_url('/lousy-outages/')
            );

            $text = $textBody . "\n\nUnsubscribe: " . $unsubscribe;
            $html = $htmlBody . sprintf('<p><a href="%s">Unsubscribe</a></p>', esc_url($unsubscribe));

            $headers = [
                'List-Unsubscribe: <' . esc_url_raw($unsubscribe) . '>',
                'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            ];

            if (! Mailer::send($email, $subject, $text, $html, $headers)) {
                self::log_error('digest', 'mail send failed for ' . $email);
                return false;
            }
        }

        return true;
    }

    private static function log_error(string $provider, string $message): void {
        error_log('[lousy_outages] incident_fetch provider=' . $provider . ' ' . $message);
    }

    public static function render_admin_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || ('dashboard' !== $screen->id && 'toplevel_page_lousy-outages' !== $screen->id && 'plugins' !== $screen->id)) {
            return;
        }

        $messages = [];

        $last = get_option(self::OPTION_LAST_CHECK);
        if ($last) {
            $messages[] = sprintf('Last provider check ran at %s (UTC)', $last);
        } else {
            $messages[] = 'Lousy Outages incident checker has not run yet.';
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $messages[] = 'WordPress cron is disabled. Configure a real server cron to hit wp-cron.php or run "wp cron event run lo_check_statuses" regularly.';
        } elseif (!wp_next_scheduled('lo_check_statuses')) {
            $messages[] = 'No upcoming outage scans are scheduled. A fallback run was queued, but setting up a server cron is recommended for reliability.';
        }

        $safe_messages = array_map('esc_html', $messages);
        echo '<div class="notice notice-info"><p>' . implode('</p><p>', $safe_messages) . '</p></div>';
    }
}

add_action('lo_check_statuses', [IncidentAlerts::class, 'run']);

