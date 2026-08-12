<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
require_once __DIR__ . '/../lousy-outages/includes/Entitlements.php';
require_once __DIR__ . '/../lousy-outages/includes/CommerceStore.php';

use SuzyEaston\LousyOutages\CommerceStore;
use SuzyEaston\LousyOutages\Entitlements;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

// Manual Pro active (no Stripe) grants Pro.
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'pro',
        'subscription_status' => 'none',
        'access_source' => 'paypal',
        'access_expires_at' => null,
    ]) === 'pro',
    'Manual Pro with PayPal source must grant Pro'
);
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'pro',
        'subscription_status' => 'canceled',
        'access_source' => 'buymeacoffee',
        'access_expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
    ]) === 'pro',
    'Manual Pro with future expiry must grant Pro'
);
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'team',
        'subscription_status' => 'none',
        'access_source' => 'github_sponsors',
        'access_expires_at' => '',
    ]) === 'team',
    'Manual Team via GitHub Sponsors must grant Team'
);

// Manual Pro expired falls back to Free.
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'pro',
        'subscription_status' => 'none',
        'access_source' => 'complimentary',
        'access_expires_at' => gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS),
    ]) === 'free',
    'Expired manual Pro must fall back to Free'
);

// Manual revoke shape (cleared source / free plan) is Free.
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'free',
        'subscription_status' => 'none',
        'access_source' => null,
        'access_expires_at' => null,
        'access_note' => null,
    ]) === 'free',
    'Revoked manual access must be Free'
);
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'pro',
        'subscription_status' => 'none',
        'access_source' => null,
        'access_expires_at' => null,
    ]) === 'free',
    'Pro plan without Stripe or manual source must fail closed to Free'
);

// Stripe active/trialing continues to work exactly as before.
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'pro',
        'subscription_status' => 'active',
    ]) === 'pro',
    'Stripe active Pro must still grant Pro'
);
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'team',
        'subscription_status' => 'trialing',
    ]) === 'team',
    'Stripe trialing Team must still grant Team'
);
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'pro',
        'subscription_status' => 'active',
        'access_source' => 'manual',
        'access_expires_at' => gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS),
    ]) === 'pro',
    'Stripe active must win even when a manual expiry is in the past'
);

// Invalid plan fails closed to Free.
$assert(
    CommerceStore::plan_from_customer([
        'plan' => 'garbage',
        'subscription_status' => 'active',
        'access_source' => 'paypal',
    ]) === 'free',
    'Invalid plan must fail closed to Free even with Stripe active'
);
$assert(Entitlements::normalize_plan('enterprise') === 'free', 'normalize_plan must fail closed');
$assert(CommerceStore::normalize_access_source('venmo') === '', 'Unknown access source must be rejected');
$assert(CommerceStore::normalize_access_source('PayPal') === 'paypal', 'Access source must normalize case');

// Expiry helper.
$assert(CommerceStore::normalize_expiry('2026-12-31') === '2026-12-31 23:59:59', 'Date-only expiry should end that UTC day');
$assert(CommerceStore::normalize_expiry('not-a-date') === null, 'Invalid expiry must be rejected');

echo "ok - CommerceStore manual entitlements, Stripe parity, and fail-closed plans\n";
