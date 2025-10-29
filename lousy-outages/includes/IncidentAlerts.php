<?php
declare(strict_types=1);

namespace LousyOutages;

use WP_REST_Request;

class IncidentAlerts {
    private const SUMMARY_PROVIDERS = [
        'github'    => 'https://www.githubstatus.com/api/v2/summary.json',
        'atlassian' => 'https://status.atlassian.com/api/v2/summary.json',
        'openai'    => 'https://status.openai.com/api/v2/summary.json',
        'vercel'    => 'https://www.vercel-status.com/api/v2/summary.json',
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

    private const OPTION_SUBSCRIBERS      = 'lo_subscribers';
    private const OPTION_UNSUB_TOKENS     = 'lo_unsub_tokens';
    private const OPTION_SEEN             = 'lo_seen_incidents';
    private const OPTION_LAST_CHECK       = 'lo_last_status_check';
    private const TRANSIENT_PREFIX   = 'lo_cooldown_';

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

        $incidents = self::collect_incidents();
        update_option(self::OPTION_LAST_CHECK, gmdate('c'), false);

        if (empty($incidents)) {
            return;
        }

        $seen = get_option(self::OPTION_SEEN, []);
        if (!is_array($seen)) {
            $seen = [];
        }

        $changed = false;

        foreach ($incidents as $incident) {
            if (!isset($incident['id'])) {
                continue;
            }
            $id        = (string) $incident['id'];
            $provider  = (string) ($incident['provider'] ?? 'unknown');

            if (isset($seen[$id])) {
                continue;
            }

            if (self::is_on_cooldown($provider)) {
                continue;
            }

            $sent = self::email_incident($incident);
            if ($sent) {
                $seen[$id] = time();
                $changed   = true;
                self::start_cooldown($provider);
            }
        }

        if ($changed) {
            $limit = 200;
            if (count($seen) > $limit) {
                asort($seen);
                $seen = array_slice($seen, -$limit, null, true);
            }
            update_option(self::OPTION_SEEN, $seen, false);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function collect_incidents(): array {
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

    private static function email_incident(array $incident): bool {
        $subscribers = self::get_subscribers();
        if (empty($subscribers)) {
            return false;
        }

        $provider = ucfirst((string) ($incident['provider'] ?? 'Provider'));
        $impact    = (string) ($incident['impact'] ?? 'major incident');
        $status    = isset($incident['status']) ? (string) $incident['status'] : '';
        $statusLabel = $status ? $status : ucwords(strtolower($impact));
        $components  = isset($incident['components']) && is_array($incident['components']) ? $incident['components'] : [];
        $componentLine = $components ? implode(' • ', $components) : 'All tracked components';
        $started_at  = (string) ($incident['started_at'] ?? '');
        $url         = (string) ($incident['url'] ?? '');
        $summary     = isset($incident['name']) ? (string) $incident['name'] : '';
        $notes       = isset($incident['body']) ? (string) $incident['body'] : '';

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
                'status'          => $statusLabel,
                'impact'          => $impact,
                'summary'         => $summary,
                'notes'           => $notes,
                'timestamp'       => $started_at,
                'components'      => $componentLine,
                'components_list' => $components,
                'url'             => $url,
                'unsubscribe_url' => $unsubscribe,
            ];

            $sent = send_lo_outage_alert_email($email, $incident_payload);

            if (!$sent) {
                self::log_error((string) ($incident['provider'] ?? 'incident'), 'mail send failed for ' . $email);
                return false;
            }
        }

        return true;
    }

    private static function is_on_cooldown(string $provider): bool {
        $key = self::TRANSIENT_PREFIX . sanitize_key($provider);
        return false !== get_transient($key);
    }

    private static function start_cooldown(string $provider): void {
        $key = self::TRANSIENT_PREFIX . sanitize_key($provider);
        set_transient($key, 1, 30 * MINUTE_IN_SECONDS);
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

