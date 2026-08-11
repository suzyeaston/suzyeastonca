<?php
declare(strict_types=1);

namespace {
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
function sanitize_html_class($class) {
    return preg_replace('/[^a-z0-9_-]/i', '', (string) $class);
}
require_once __DIR__ . '/../functions.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$clear = se_home_lo_flyover_copy([
    'tone' => 'ok',
    'counts' => ['down' => 0, 'degraded' => 0, 'advisory' => 0],
    'lead' => ['kind' => 'clear', 'provider' => ''],
]);
assert_true($clear['banner'] === 'LOUSY OUTAGES → monitoring provider signals', 'clear banner fallback');
assert_true(str_contains($clear['report'], 'no major active incident summary available right now'), 'clear report fallback');
assert_true($clear['highlight'] === false, 'clear state is not hot');

$hot = se_home_lo_flyover_copy([
    'tone' => 'warn',
    'counts' => ['down' => 1, 'degraded' => 2, 'advisory' => 0],
    'lead' => ['kind' => 'down', 'provider' => 'ExampleCo'],
]);
assert_true($hot['banner'] === 'LOUSY OUTAGES → 1 provider down · 2 degraded · open status', 'hot banner counts');
assert_true($hot['report'] === 'latest report: ExampleCo status page indicates service disruption', 'provider-specific down report');
assert_true($hot['highlight'] === true, 'hot state is highlighted');

$part = file_get_contents(__DIR__ . '/../parts/home-commercial-strip.php');
assert_true(str_contains($part, 'home-lo-flyover'), 'homepage strip replaced with flyover module');
assert_true(str_contains($part, 'data-lo-flyover-banner'), 'flyover banner is data-driven');
assert_true(!str_contains($part, 'home-signal-strip'), 'legacy signal strip markup removed');

echo "OK\n";
}
