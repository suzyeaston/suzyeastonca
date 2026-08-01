<?php
declare(strict_types=1);

const DAY_IN_SECONDS = 86400;
const HOUR_IN_SECONDS = 3600;
const MINUTE_IN_SECONDS = 60;

function apply_filters($hook, $value) { return $value; }
function sanitize_key($v) { return trim(preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)), '_-'); }
function sanitize_title($v) { return sanitize_key($v); }
function get_option($k, $d = false) { return $d; }
function home_url($path = '/') { return 'https://example.test' . $path; }
function human_time_diff($a, $b) { return '1 hour'; }
function current_time($type, $gmt = 0) { return time(); }

require __DIR__ . '/../includes/Summary.php';
require __DIR__ . '/../includes/Adapters.php';

use SuzyEaston\LousyOutages\Summary;

use function SuzyEaston\LousyOutages\Adapters\clean_feed_title;
use function SuzyEaston\LousyOutages\Adapters\describe_affected_components;
use function SuzyEaston\LousyOutages\Adapters\from_statuspage_summary;

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$now = time();

$fresh = ['title' => 'API errors', 'status' => 'investigating', 'updated_at' => gmdate('c', $now - HOUR_IN_SECONDS)];
ok(Summary::classify_incident($fresh, $now) === 'active', 'incident updated an hour ago is active');

$stale = ['title' => 'Regional damage', 'status' => 'outage', 'summary' => 'The region continues to be unavailable.', 'updated_at' => gmdate('c', $now - (90 * DAY_IN_SECONDS))];
ok(Summary::classify_incident($stale, $now) === 'long_running', 'unresolved incident with a months-old update is long running, not active');

$ancient = ['title' => 'Ancient event', 'status' => 'outage', 'summary' => 'still experiencing issues', 'updated_at' => gmdate('c', $now - (400 * DAY_IN_SECONDS))];
ok(Summary::classify_incident($ancient, $now) === 'closed', 'incidents past the advisory window drop off the board');

$resolved = ['title' => 'Fixed', 'status' => 'resolved', 'updated_at' => gmdate('c', $now - 600)];
ok(Summary::classify_incident($resolved, $now) === 'closed', 'resolved incidents are closed');

$quiet = ['title' => 'Quiet advisory', 'status' => 'scheduled', 'summary' => 'nothing to see', 'updated_at' => gmdate('c', $now - (10 * DAY_IN_SECONDS))];
ok(Summary::classify_incident($quiet, $now) === 'closed', 'stale incidents without ongoing wording are closed');

$provider = ['id' => 'aws', 'incidents' => [$fresh, $stale, $resolved]];
ok(count(Summary::current_incidents_for_provider($provider)) === 1, 'only the fresh incident counts as current');
ok(count(Summary::long_running_incidents_for_provider($provider)) === 1, 'the stale-but-open incident lands in the long running lane');
ok(Summary::long_running_incidents_for_provider($provider)[0]['is_long_running'] === true, 'long running incidents are flagged');

$googleTitle = "RESOLVED: **Title**\nInbound emails are failing.\n**Description**\nWe are experiencing an issue.\n**Symptoms**\nEmails bounce.";
ok(clean_feed_title($googleTitle) === 'Resolved: Inbound emails are failing.', 'markdown feed titles collapse to one readable line, got: ' . clean_feed_title($googleTitle));
ok(clean_feed_title('Increased error rates') === 'Increased error rates', 'plain titles pass through unchanged');

$described = describe_affected_components([
    ['name' => 'Arica, Chile', 'status' => 're_routed'],
    ['name' => 'Baghdad, Iraq', 'status' => 're_routed'],
    ['name' => 'Guam', 'status' => 'degraded_performance'],
    ['name' => 'Harare', 'status' => 're_routed'],
], 'Minor Service Outage');
ok(str_contains($described, '4 of the provider’s components'), 'component summary counts affected components');
ok(str_contains($described, '3 re-routed'), 'component summary breaks down by status');
ok(str_contains($described, '+1 more'), 'component summary truncates the example list');
ok(describe_affected_components([], 'Minor Service Outage') === 'Minor Service Outage', 'empty component lists fall back to the provider description');

$summaryPayload = json_encode([
    'page' => ['updated_at' => gmdate('c', $now)],
    'status' => ['indicator' => 'minor', 'description' => 'Minor Service Outage'],
    'components' => [
        ['name' => 'Europe', 'status' => 're_routed', 'group' => true],
        ['name' => 'London', 'status' => 're_routed'],
        ['name' => 'Toronto', 'status' => 'operational'],
    ],
    'incidents' => [],
]);
$adapted = from_statuspage_summary((string) $summaryPayload);
ok($adapted['state'] === 'degraded', 'minor indicator maps to degraded');
ok(count($adapted['components_affected']) === 1, 'group rows are excluded from the affected component count');
ok(str_contains((string) $adapted['summary'], 'London'), 'degraded providers without incidents explain which components are affected');

echo "incident freshness and adapter copy tests passed\n";
