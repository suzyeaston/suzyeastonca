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
        if ('' === $token || !self::mark_confirmed($token)) {
            return self::redirect('/lousy-outages/?sub=invalid');
        }

        return self::redirect('/lousy-outages/?sub=confirmed');
    }

    public static function unsubscribe(\WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token || !self::mark_unsubscribed($token)) {
            return self::redirect('/lousy-outages/?sub=invalid');
        }

        return self::redirect('/lousy-outages/?sub=unsubscribed');
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

    private static function redirect(string $path) {
        status_header(302);
        header('Location: ' . home_url($path));
        exit;
    }
}

Lousy_Outages_Subscribe::bootstrap();
