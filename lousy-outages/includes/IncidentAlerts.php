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
    ];

    private const OPTION_SUBSCRIBERS      = 'lo_subscribers';
    private const OPTION_UNSUB_TOKENS     = 'lo_unsub_tokens';
    private const OPTION_SEEN             = 'lo_seen_incidents';
    private const OPTION_LAST_CHECK       = 'lo_last_status_check';
    private const TRANSIENT_PREFIX   = 'lo_cooldown_';
    private static $altBody = '';

    public static function bootstrap(): void {
        add_action('init', [self::class, 'ensure_schedule']);
        add_action('admin_notices', [self::class, 'render_admin_notice']);
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function ensure_schedule(): void {
        if (!wp_next_scheduled('lo_check_statuses')) {
            wp_schedule_event(time() + 60, 'lo_five_minutes', 'lo_check_statuses');
        }
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

        foreach (self::RSS_PROVIDERS as $provider => $url) {
            $result = self::fetch_rss($provider, $url);
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
    private static function fetch_rss(string $provider, string $url): array {
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
        if (!is_string($body) || '' === trim($body)) {
            self::log_error($provider, 'empty RSS body');
            return [];
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            self::log_error($provider, 'unable to parse RSS');
            return [];
        }

        $items = [];
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->entry)) {
            $items = $xml->entry;
        }

        $incidents = [];
        $keywords  = ['major outage', 'critical', 'service disruption'];

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

            $guid       = isset($item->guid) ? (string) $item->guid : (isset($item->id) ? (string) $item->id : (string) $item->link);
            $link       = isset($item->link) ? (string) $item->link : '';
            if (is_object($item->link) && isset($item->link['href'])) {
                $link = (string) $item->link['href'];
            }

            $started = '';
            if (isset($item->pubDate)) {
                $started = (string) $item->pubDate;
            } elseif (isset($item->updated)) {
                $started = (string) $item->updated;
            }

            $incidents[] = [
                'id'         => $provider . ':' . hash('sha256', $guid ?: $link ?: (string) $item->title),
                'provider'   => $provider,
                'name'       => (string) ($item->title ?? 'Incident'),
                'impact'     => 'major',
                'components' => [],
                'started_at' => $started,
                'url'        => $link,
            ];
        }

        return $incidents;
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
        $subject  = sprintf('major incident — %s: %s', $provider, (string) ($incident['name'] ?? 'unknown'));

        $impact      = strtoupper((string) ($incident['impact'] ?? 'major'));
        $components  = isset($incident['components']) && is_array($incident['components']) ? $incident['components'] : [];
        $componentLine = $components ? implode(' • ', $components) : 'All tracked components';
        $started_at  = (string) ($incident['started_at'] ?? '');
        $url         = (string) ($incident['url'] ?? '');

        $headers = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
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

            $html_body = self::build_html_email(
                $provider,
                $incident['name'] ?? '',
                $impact,
                $componentLine,
                $started_at,
                $url,
                $unsubscribe
            );
            $text_body = self::build_text_email(
                $provider,
                $incident['name'] ?? '',
                $impact,
                $components,
                $started_at,
                $url,
                $unsubscribe
            );

            $headersWithList = $headers;
            $headersWithList[] = 'List-Unsubscribe: <' . esc_url_raw($unsubscribe) . '>';
            $headersWithList[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';

            add_filter('wp_mail_content_type', [self::class, 'force_html_content']);
            self::$altBody = $text_body;
            add_action('phpmailer_init', [self::class, 'inject_alt_body']);

            $sent = wp_mail($email, $subject, $html_body, $headersWithList);

            remove_filter('wp_mail_content_type', [self::class, 'force_html_content']);
            remove_action('phpmailer_init', [self::class, 'inject_alt_body']);
            self::$altBody = '';

            if (!$sent) {
                self::log_error((string) ($incident['provider'] ?? 'incident'), 'mail send failed for ' . $email);
                return false;
            }
        }

        return true;
    }

    public static function force_html_content(): string {
        return 'text/html';
    }

    public static function inject_alt_body($phpmailer): void {
        if ($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer && '' !== self::$altBody) {
            $phpmailer->AltBody = self::$altBody;
        }
    }

    private static function build_html_email(string $provider, string $name, string $impact, string $components, string $started_at, string $url, string $unsubscribe): string {
        $title = esc_html($name ?: 'Major incident');
        $impactLabel = esc_html($impact);
        $componentsLabel = esc_html($components ?: 'components unknown');
        $startedLabel = esc_html($started_at ?: 'time TBD');
        $cta = $url ? sprintf('<a href="%s" style="color:#f04e23;">open incident log</a>', esc_url($url)) : '';
        $unsubscribeLink = esc_url($unsubscribe);

        return <<<HTML
<!doctype html>
<meta charset="utf-8">
<body style="margin:0;background:#050505;color:#ffe9c4;font-family:'Space Grotesk',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
  <div style="max-width:640px;margin:0 auto;padding:32px 24px;">
    <div style="background:linear-gradient(140deg,#160800 0%,#2d1300 100%);border:2px solid #ffb81c;box-shadow:0 0 28px rgba(240,78,35,0.4);border-radius:18px;padding:28px;">
      <header style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;">
        <span style="font-size:18px;letter-spacing:0.08em;text-transform:uppercase;color:#ffb81c;">lousy outages</span>
        <span style="font-size:14px;color:rgba(255,233,196,0.85);">old-school outage intel</span>
      </header>
      <h1 style="margin:0 0 12px;font-size:26px;color:#ffe9c4;">{$impactLabel} — {$provider}</h1>
      <p style="margin:0 0 18px;font-size:20px;color:#ffb81c;">{$title}</p>
      <div style="display:grid;gap:12px;margin:20px 0;">
        <div style="background:rgba(20,10,0,0.65);border-radius:14px;padding:14px 16px;color:#ffe3b5;font-size:15px;">
          <strong style="display:block;font-size:12px;letter-spacing:0.1em;color:#ffb81c;margin-bottom:4px;">components</strong>
          {$componentsLabel}
        </div>
        <div style="background:rgba(20,10,0,0.65);border-radius:14px;padding:14px 16px;color:#ffe3b5;font-size:15px;">
          <strong style="display:block;font-size:12px;letter-spacing:0.1em;color:#ffb81c;margin-bottom:4px;">started</strong>
          {$startedLabel}
        </div>
      </div>
      <p style="font-size:15px;color:rgba(255,233,196,0.85);line-height:1.6;">massive systems turbulence detected. stay frosty, reroute workloads if you can, and keep the synthwave looping.</p>
      <p style="margin:20px 0;">{$cta}</p>
      <footer style="margin-top:24px;border-top:1px dashed rgba(255,184,28,0.4);padding-top:16px;color:rgba(255,233,196,0.75);font-size:12px;">
        broadcasting for <em>console cowboys</em> &amp; dream logic engineers.<br>
        <a href="{$unsubscribeLink}" style="color:#ffb81c;">unsubscribe instantly</a> • keep hacking the planet.
      </footer>
    </div>
  </div>
</body>
HTML;
    }

    private static function build_text_email(string $provider, string $name, string $impact, array $components, string $started_at, string $url, string $unsubscribe): string {
        $componentsLine = $components ? implode(', ', $components) : 'All tracked components';
        $unsubscribeText = esc_url_raw($unsubscribe);
        $lines = [
            sprintf('Major incident — %s', $provider),
            $name ?: 'Unnamed incident',
            'Impact: ' . $impact,
            'Components: ' . $componentsLine,
        ];

        if ('' !== $started_at) {
            $lines[] = 'Started: ' . $started_at;
        }

        if ('' !== $url) {
            $lines[] = 'Details: ' . $url;
        }

        $lines[] = '';
        $lines[] = 'Unsubscribe: ' . $unsubscribeText;
        $lines[] = '';
        $lines[] = 'Stay sharp. – Lousy Outages';

        return implode("\n", $lines);
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

        $last = get_option(self::OPTION_LAST_CHECK);
        if ($last) {
            $message = sprintf('Last provider check ran at %s (UTC)', $last);
        } else {
            $message = 'Lousy Outages incident checker has not run yet.';
        }

        echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
    }
}

add_action('lo_check_statuses', [IncidentAlerts::class, 'run']);

