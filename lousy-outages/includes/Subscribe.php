<?php
declare(strict_types=1);

use LousyOutages\Subscriptions;

class Lousy_Outages_Subscribe {
    public static function bootstrap(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('lousy-outages/v1', '/confirm', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'confirm'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('lousy-outages/v1', '/unsubscribe', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'unsubscribe'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function confirm(\WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token) {
            return self::redirect_with_status('invalid');
        }

        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return self::redirect_with_status('invalid');
        }

        $status = strtolower((string) ($record['status'] ?? ''));
        if (Subscriptions::STATUS_SUBSCRIBED === $status) {
            return self::redirect_with_status('confirmed');
        }

        if (!self::mark_confirmed($token)) {
            return self::redirect_with_status('invalid');
        }

        $email = isset($record['email']) ? sanitize_email((string) $record['email']) : '';
        if ($email && is_email($email)) {
            $unsubscribe_link = home_url('/wp-json/lousy-outages/v1/unsubscribe?token=' . rawurlencode($token));
            $subject = 'Lousy Outages: subscription confirmed';
            $body    = "You'll now receive outage alerts. Unsubscribe anytime: {$unsubscribe_link}";
            wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
        }

        return self::redirect_with_status('confirmed');
    }

    public static function unsubscribe(\WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token || !self::mark_unsubscribed($token)) {
            return self::redirect_with_status('invalid');
        }

        return self::redirect_with_status('unsubscribed');
    }

    private static function mark_confirmed(string $token): bool {
        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return false;
        }

        $status  = strtolower((string) ($record['status'] ?? ''));
        $created = isset($record['created_at']) ? strtotime((string) $record['created_at']) : false;
        $cutoff  = time() - 14 * DAY_IN_SECONDS;

        if (Subscriptions::STATUS_PENDING === $status) {
            if (!$created || $created < $cutoff) {
                Subscriptions::update_status_by_token($token, Subscriptions::STATUS_UNSUBSCRIBED);
                return false;
            }

            return Subscriptions::update_status_by_token($token, Subscriptions::STATUS_SUBSCRIBED);
        }

        if (Subscriptions::STATUS_SUBSCRIBED === $status) {
            return true;
        }

        return false;
    }

    private static function mark_unsubscribed(string $token): bool {
        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return false;
        }

        if (Subscriptions::STATUS_UNSUBSCRIBED === strtolower((string) ($record['status'] ?? ''))) {
            return true;
        }

        return Subscriptions::update_status_by_token($token, Subscriptions::STATUS_UNSUBSCRIBED);
    }

    private static function redirect_with_status(string $status) {
        $status = sanitize_key($status);
        if ($status) {
            self::store_flash_cookie($status);
        }
        return self::redirect('/lousy-outages/?sub=' . rawurlencode($status ?: '')); 
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

    private static function redirect(string $path) {
        status_header(302);
        header('Location: ' . home_url($path));
        exit;
    }
}

Lousy_Outages_Subscribe::bootstrap();
