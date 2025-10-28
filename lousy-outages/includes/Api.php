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

        register_rest_route(
            'lousy-outages/v1',
            '/confirm',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_confirm'],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/unsubscribe',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_unsubscribe'],
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

    public static function handle_summary(WP_REST_Request $request): WP_REST_Response {
        $providerParam = $request->get_param('provider');
        $filters       = self::sanitize_provider_list(is_string($providerParam) ? $providerParam : null);

        $fetcher  = new Lousy_Outages_Fetcher();
        $result   = $fetcher->get_all($filters ?: null);
        $providers = array_values($result['providers']);

        $payload = [
            'providers'  => $providers,
            'fetched_at' => $result['fetched_at'],
        ];
        if (!empty($result['errors'])) {
            $payload['errors'] = $result['errors'];
        }

        $etag       = '"' . md5(wp_json_encode($payload)) . '"';
        $ifNoneMatch = trim((string) $request->get_header('if-none-match'));

        if ('' !== $ifNoneMatch && $ifNoneMatch === $etag) {
            $response = new WP_REST_Response(null, 304);
            $response->header('ETag', $etag);
            $response->header('Cache-Control', 'no-cache, must-revalidate');
            return $response;
        }

        $response = new WP_REST_Response($payload, 200);
        $response->header('ETag', $etag);
        $response->header('Cache-Control', 'no-cache, must-revalidate');

        return $response;
    }

    public static function handle_subscribe(WP_REST_Request $request): WP_REST_Response {
        $params = self::extract_body_params($request);

        $nonce = $params['_wpnonce'] ?? $params['nonce'] ?? $request->get_header('X-WP-Nonce');
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'lousy_outages_subscribe')) {
            return new WP_REST_Response(['message' => 'Invalid security token.'], 403);
        }

        $honeypot = isset($params['website']) ? trim((string) $params['website']) : '';
        if ('' !== $honeypot) {
            return new WP_REST_Response(['message' => 'Something went wrong.'], 400);
        }

        $email = isset($params['email']) ? sanitize_email((string) $params['email']) : '';
        if (!$email || !is_email($email)) {
            return new WP_REST_Response(['message' => 'Please provide a valid email address.'], 400);
        }

        $ip      = self::detect_ip($request);
        $ip_hash = self::hash_ip($ip);
        $rateKey = self::rate_limit_key($ip_hash);
        $count   = (int) get_transient($rateKey);
        if ($count >= 5) {
            return new WP_REST_Response(['message' => 'Too many requests. Try again soon.'], 429);
        }
        set_transient($rateKey, $count + 1, 60);

        try {
            $token = bin2hex(random_bytes(24));
        } catch (\Throwable $e) {
            $token = wp_generate_password(32, false, false);
        }

        Subscriptions::save_pending($email, $token, $ip_hash, 'form');

        $confirm_url     = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/confirm'));
        $unsubscribe_url = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/unsubscribe'));

        $subject = 'Confirm your Lousy Outages subscription';
        $message = "Hi there!\n\nPlease confirm your email to receive Lousy Outages alerts.\n\nConfirm subscription: {$confirm_url}\n\nIf you didn\'t request this, ignore this email or unsubscribe here: {$unsubscribe_url}\n\n— Lousy Outages";

        wp_mail($email, $subject, $message, ['Content-Type: text/plain; charset=UTF-8']);

        $response = new WP_REST_Response([
            'message' => 'Check your email to confirm your subscription.',
            'status'  => 'pending',
        ], 200);
        $response->header('Cache-Control', 'no-store, max-age=0');

        return $response;
    }

    public static function handle_confirm(WP_REST_Request $request): WP_REST_Response {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token) {
            return self::html_response('Missing token', 'This confirmation link is missing a token.', 400);
        }

        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return self::html_response('Link expired', 'This confirmation link is invalid or has expired.', 404);
        }

        $status  = strtolower((string) ($record['status'] ?? ''));
        $created = isset($record['created_at']) ? strtotime((string) $record['created_at']) : false;
        $cutoff  = time() - 14 * DAY_IN_SECONDS;

        if (Subscriptions::STATUS_PENDING === $status) {
            if (!$created || $created < $cutoff) {
                Subscriptions::update_status_by_token($token, Subscriptions::STATUS_UNSUBSCRIBED);
                return self::html_response('Link expired', 'This confirmation link has expired. Please subscribe again.', 410);
            }

            Subscriptions::update_status_by_token($token, Subscriptions::STATUS_SUBSCRIBED);
            return self::html_response('Subscription confirmed', 'Thanks! You will now receive outage alerts by email.');
        }

        if (Subscriptions::STATUS_SUBSCRIBED === $status) {
            return self::html_response('Already confirmed', 'This email address is already confirmed.');
        }

        return self::html_response('Unsubscribed', 'This subscription has been unsubscribed.');
    }

    public static function handle_unsubscribe(WP_REST_Request $request): WP_REST_Response {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token) {
            return self::html_response('Missing token', 'No unsubscribe token was provided.', 400);
        }

        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return self::html_response('Link invalid', 'This unsubscribe link is invalid or has already been used.', 404);
        }

        if (Subscriptions::STATUS_UNSUBSCRIBED !== strtolower((string) $record['status'] ?? '')) {
            Subscriptions::update_status_by_token($token, Subscriptions::STATUS_UNSUBSCRIBED);
        }

        return self::html_response('You have been unsubscribed', 'You will no longer receive outage alerts.');
    }

    private static function sanitize_provider_list(?string $raw): array {
        if (!$raw) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $raw)));
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

    private static function html_response(string $title, string $message, int $status = 200): WP_REST_Response {
        $title   = esc_html($title);
        $message = esc_html($message);
        $body    = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" /><title>' . $title . '</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#101410;color:#f4fff0;padding:48px;display:flex;justify-content:center;}'
            . '.lo-card{max-width:520px;background:#1a261a;border-radius:16px;padding:32px;box-shadow:0 12px 32px rgba(0,0,0,0.35);}'
            . '.lo-card h1{margin-top:0;font-size:1.6rem;} .lo-card p{margin:12px 0 0;font-size:1rem;line-height:1.5;}</style>'
            . '</head><body><main class="lo-card"><h1>' . $title . '</h1><p>' . $message . '</p></main></body></html>';

        $response = new WP_REST_Response($body, $status);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->header('Cache-Control', 'no-store, max-age=0');

        return $response;
    }
}
