<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

/** The single server-side source of truth for product access. */
final class Entitlements {
    public const PLANS = ['free', 'pro', 'team'];

    private const MATRIX = [
        'free' => ['public_dashboard', 'recent_history', 'rss', 'email_subscription'],
        'pro'  => ['public_dashboard', 'recent_history', 'rss', 'email_subscription', 'watchlists', 'advanced_filters', 'digest_preferences', 'alert_destination'],
        'team' => ['public_dashboard', 'recent_history', 'rss', 'email_subscription', 'watchlists', 'advanced_filters', 'digest_preferences', 'alert_destination', 'shared_boards', 'multiple_destinations', 'api_tokens', 'private_board'],
    ];

    public static function normalize_plan(string $plan): string {
        $plan = strtolower(trim($plan));
        return in_array($plan, self::PLANS, true) ? $plan : 'free';
    }

    public static function for_plan(string $plan): array {
        return self::MATRIX[self::normalize_plan($plan)];
    }

    public static function allows(string $plan, string $feature): bool {
        return in_array($feature, self::for_plan($plan), true);
    }

    public static function destination_limit(string $plan): int {
        return ['free' => 0, 'pro' => 1, 'team' => 20][self::normalize_plan($plan)];
    }
}
