<?php
declare(strict_types=1);

namespace LousyOutages;

use WP_REST_Request;
use WP_REST_Response;

class Api {
    public static function bootstrap(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(
            'lousy/v1',
            '/refresh',
            [
                'methods'             => 'POST',
                'permission_callback' => [self::class, 'verify_nonce'],
                'callback'            => [self::class, 'handle_refresh'],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/summary',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_summary'],
                'args'                => [
                    'provider' => [
                        'description'       => 'Optional provider filter (comma-separated)',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/subscribe',
            [
                'methods'             => 'POST',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_subscribe'],
            ]
        );

    }

    public static function verify_nonce(): bool {
        $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Trigger a manual refresh of provider statuses.
     */
    public static function handle_refresh(WP_REST_Request $request): WP_REST_Response {
        $timestamp = gmdate('c');
        $store     = new Store();
        $states    = lousy_outages_collect_statuses(true);
        $errors    = [];

        foreach ($states as $id => $state) {
            $store->update($id, $state);
            if (!empty($state['error'])) {
                $errors[] = [
                    'id'      => $id,
                    'provider'=> isset($state['name']) ? (string) $state['name'] : (isset($state['provider']) ? (string) $state['provider'] : $id),
                    'message' => (string) $state['error'],
                ];
            }
        }

        update_option('lousy_outages_last_poll', $timestamp, false);
        do_action('lousy_outages_log', 'manual_refresh', [
            'count' => count($states),
            'ts'    => $timestamp,
        ]);

        $providers = [];
        foreach ($states as $id => $state) {
            $providers[] = lousy_outages_build_provider_payload($id, $state, $timestamp);
        }

        $providers = \lousy_outages_sort_providers($providers);

        $response = [
            'refreshedAt'   => $timestamp,
            'providerCount' => count($providers),
            'errors'        => $errors,
            'providers'     => $providers,
        ];

        return rest_ensure_response($response);
    }

    public static function handle_summary(WP_REST_Request $request) {
        $providerParam = $request->get_param('provider');
        $filters       = self::sanitize_provider_list(is_string($providerParam) ? $providerParam : null);

        $fetcher = new Lousy_Outages_Fetcher();
        $result  = $fetcher->get_all($filters ?: null);
        $providers = isset($result['providers']) && is_array($result['providers']) ? array_values($result['providers']) : [];
        $trending  = isset($result['trending']) && is_array($result['trending'])
            ? $result['trending']
            : ['trending' => false, 'signals' => [], 'generated_at' => gmdate('c')];

        $isLite = false;
        $liteParam = $request->get_param('lite');
        if (null !== $liteParam) {
            $value = strtolower((string) $liteParam);
            $isLite = in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        if ($isLite) {
            $payload = [
                'trending' => !empty($trending['trending']),
            ];
        } else {
            $payload = [
                'providers'  => $providers,
                'fetched_at' => $result['fetched_at'] ?? gmdate('c'),
                'trending'   => $trending,
            ];
            if (!empty($result['errors']) && is_array($result['errors'])) {
                $payload['errors'] = $result['errors'];
            }
        }

        $json = wp_json_encode($payload);
        if (!is_string($json)) {
            $json = '{}';
        }
        $etag = '"' . sha1($json) . '"';
        $incoming = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $matched = false;
        if ('' !== $incoming) {
            $candidates = array_map('trim', explode(',', $incoming));
            foreach ($candidates as $candidate) {
                if ($candidate === $etag || $candidate === 'W/' . $etag) {
                    $matched = true;
                    break;
                }
            }
        }

        if ($matched) {
            status_header(304);
            header('ETag: ' . $etag);
            header('Cache-Control: no-cache, must-revalidate');
            exit;
        }

        status_header(200);
        header('ETag: ' . $etag);
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        exit;
    }


    public static function handle_subscribe(WP_REST_Request $request): WP_REST_Response {
        $params = self::extract_body_params($request);

        $nonce = $params['_wpnonce'] ?? $params['nonce'] ?? $request->get_header('X-WP-Nonce');
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'lousy_outages_subscribe')) {
            return self::respond_with_message(false, 'Invalid security token.', 403, $request);
        }

        $honeypot = isset($params['website']) ? trim((string) $params['website']) : '';
        if ('' !== $honeypot) {
            return self::respond_with_message(false, 'Something went wrong.', 400, $request);
        }

        $email = isset($params['email']) ? sanitize_email((string) $params['email']) : '';
        if (!$email || !is_email($email)) {
            return self::respond_with_message(false, 'Please provide a valid email address.', 400, $request);
        }

        $ip      = self::detect_ip($request);
        $ip_hash = self::hash_ip($ip);
        $rateKey = self::rate_limit_key($ip_hash);
        $count   = (int) get_transient($rateKey);
        if ($count >= 5) {
            return self::respond_with_message(false, 'Too many requests. Try again soon.', 429, $request);
        }
        set_transient($rateKey, $count + 1, 60);

        try {
            $token = bin2hex(random_bytes(24));
        } catch (\Throwable $e) {
            $token = wp_generate_password(32, false, false);
        }

        Subscriptions::save_pending($email, $token, $ip_hash, 'form');

        \Lousy_Outages_Subscribe::send_confirm_email($email, $token);

        return self::respond_with_message(true, 'Check your email to confirm your subscription.', 200, $request);
    }

    private static function respond_with_message(bool $success, string $message, int $status, WP_REST_Request $request): WP_REST_Response {
        if (self::wants_html($request)) {
            $slug = $success ? 'check-email' : 'invalid';
            self::store_flash_cookie($slug);
            status_header(303);
            header('Location: ' . home_url('/lousy-outages/?sub=' . $slug));
            exit;
        }

        return new WP_REST_Response(['message' => $message], $status);
    }

    private static function wants_html(WP_REST_Request $request): bool {
        $accept = $request->get_header('accept');
        if (!is_string($accept)) {
            return false;
        }

        return false !== stripos($accept, 'text/html');
    }

    private static function store_flash_cookie(string $status): void {
        if (headers_sent()) {
            return;
        }

        $value = sanitize_key($status);
        if ('' === $value) {
            return;
        }

        $params = [
            'expires'  => time() + 300,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ];

        setcookie('lo_sub_msg', $value, $params);
    }

    private static function sanitize_provider_list(?string $raw): array {
        if (!$raw) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $parts = array_map(static function ($part) {
            return strtolower((string) $part);
        }, $parts);

        return array_values(array_unique($parts));
    }

    private static function extract_body_params(WP_REST_Request $request): array {
        $json = $request->get_json_params();
        if (!is_array($json)) {
            $json = [];
        }
        $body = $request->get_body_params();
        if (!is_array($body)) {
            $body = [];
        }
        return array_merge($body, $json);
    }

    private static function detect_ip(WP_REST_Request $request): string {
        $headers = $request->get_headers();
        $forwarded = '';
        if (isset($headers['x-forwarded-for'][0])) {
            $forwarded = trim(explode(',', $headers['x-forwarded-for'][0])[0]);
        }
        if ($forwarded) {
            return $forwarded;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private static function hash_ip(string $ip): string {
        return hash('sha256', $ip . wp_salt('nonce'));
    }

    private static function rate_limit_key(string $ip_hash): string {
        return 'lousy_outages_subscribe_' . substr($ip_hash, 0, 20);
    }

}
