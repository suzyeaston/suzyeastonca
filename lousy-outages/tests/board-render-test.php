<?php
/**
 * Render the public board against a synthetic snapshot with a minimal WordPress shim.
 *
 * Catches fatals and missing markup before the plugin ships, and asserts the states
 * that used to leak (every empty-state string visible at once, ghost providers).
 */
declare(strict_types=1);

const ABSPATH = __DIR__;
const HOUR_IN_SECONDS = 3600;
const DAY_IN_SECONDS = 86400;
const MINUTE_IN_SECONDS = 60;

define('LOUSY_OUTAGES_VERSION', '0.0.0-test');

function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($value) { return (string) $value; }
function sanitize_key($value) { return trim(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)), '-_'); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-'); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function apply_filters($hook, $value) { return $value; }
function get_option($key, $default = false) {
    if ('date_format' === $key) { return 'M j, Y'; }
    if ('time_format' === $key) { return 'H:i'; }
    return $default;
}
function update_option($key, $value, $autoload = null) { return true; }
function wp_date($format, $timestamp) { return gmdate($format, (int) $timestamp); }
function home_url($path = '/') { return 'https://example.test' . $path; }
function trailingslashit($value) { return rtrim((string) $value, '/\\') . '/'; }
function wp_parse_url($url, $component = -1) { return parse_url((string) $url, $component); }

require __DIR__ . '/../includes/ProviderRegistry.php';
require __DIR__ . '/../includes/Providers.php';
require __DIR__ . '/../includes/Storage/HistoryStore.php';
require __DIR__ . '/../public/board.php';

use function SuzyEaston\LousyOutages\Board\render;

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$now = time();
$iso = static fn(int $offset): string => gmdate('c', $now - $offset);

$providers = [
    [
        'id' => 'github', 'provider_id' => 'github', 'name' => 'GitHub', 'stateCode' => 'outage',
        'lane' => 'outages', 'summary' => 'Actions is down.', 'checked_at' => $iso(120),
        'url' => 'https://www.githubstatus.com/', 'sourceType' => 'statuspage', 'category' => 'development',
        'incidents' => [[
            'id' => 'gh-1', 'title' => 'Actions jobs are queueing', 'status' => 'investigating',
            'impact' => 'major', 'updated_at' => $iso(600), 'checked_at' => $iso(120),
            'url' => 'https://www.githubstatus.com/incidents/gh-1',
        ]],
    ],
    [
        'id' => 'cloudflare', 'provider_id' => 'cloudflare', 'name' => 'Cloudflare', 'stateCode' => 'degraded',
        'lane' => 'signals', 'summary' => '46 of the provider’s components are not operational.',
        'checked_at' => $iso(300), 'data_observed_at' => $iso(900), 'url' => 'https://www.cloudflarestatus.com/',
        'sourceType' => 'statuspage', 'category' => 'cloud', 'incidents' => [],
    ],
    [
        'id' => 'aws', 'provider_id' => 'aws', 'name' => 'AWS', 'stateCode' => 'advisory', 'lane' => 'long_running',
        'summary' => 'Conflict-related infrastructure damage affecting AWS Bahrain', 'checked_at' => $iso(200),
        'url' => 'https://health.aws.amazon.com/health/status', 'sourceType' => 'rss', 'category' => 'cloud',
        'incidents' => [],
    ],
    [
        'id' => 'openai', 'provider_id' => 'openai', 'name' => 'OpenAI', 'stateCode' => 'operational',
        'lane' => 'operational', 'summary' => 'All systems operational.', 'checked_at' => $iso(60),
        'url' => 'https://status.openai.com/', 'sourceType' => 'statuspage', 'category' => 'ai', 'incidents' => [],
    ],
];

$state = [
    'providers' => $providers,
    'outages' => [[
        'id' => 'gh-1', 'provider_id' => 'github', 'provider' => 'GitHub',
        'title' => 'Actions jobs are queueing', 'summary' => 'Runners are not picking up jobs.',
        'status' => 'investigating', 'impact' => 'major', 'updated_at' => $iso(600),
        'checked_at' => $iso(120), 'url' => 'https://www.githubstatus.com/incidents/gh-1',
    ]],
    'long_running' => [[
        'id' => 'aws-bahrain', 'provider_id' => 'aws', 'provider' => 'AWS',
        'title' => 'Conflict-related infrastructure damage affecting AWS Bahrain',
        'summary' => 'The region remains unavailable.', 'status' => 'outage', 'impact' => 'outage',
        'region_name' => 'Bahrain', 'updated_at' => $iso(90 * DAY_IN_SECONDS), 'checked_at' => $iso(200),
        'is_long_running' => true, 'url' => 'https://health.aws.amazon.com/health/status',
    ]],
    'signals' => [$providers[1]],
    'unverified' => [],
    'operational' => [$providers[3]],
    'fetched_at' => $iso(45),
    'meta' => [
        'active_outage_count' => 1, 'long_running_count' => 1, 'signal_count' => 1,
        'unverified_count' => 0, 'operational_count' => 1,
        'current_official_provider_ids' => ['github'],
    ],
];

$config = [
    'summaryEndpoint' => 'https://example.test/wp-json/lousy-outages/v1/summary',
    'historyEndpoint' => 'https://example.test/wp-json/lousy-outages/v1/history',
    'refreshEndpoint' => '',
    'refreshNonce' => '',
    'rssUrl' => 'https://example.test/?feed=lousy_outages_status',
    'pollInterval' => 60000,
    'version' => '0.0.0-test',
];

$html = render($state, $config);

ok(str_contains($html, 'data-lox'), 'board root is present');
ok(str_contains($html, '1 PROVIDER IS DOWN'), 'verdict reports the active outage');
ok(str_contains($html, 'Actions jobs are queueing'), 'active incident card renders');
ok(str_contains($html, 'NO RECENT UPDATE'), 'long-running advisory is labelled');
ok(str_contains($html, 'Conflict-related infrastructure damage'), 'advisory card renders');
ok(str_contains($html, '46 of the provider'), 'degraded provider explains itself');
ok(str_contains($html, 'data-lox-grid="active"'), 'active grid exists for hydration');
ok(str_contains($html, 'data-lox-matrix'), 'provider matrix exists');
ok(substr_count($html, 'data-lox-row') === count($providers), 'every provider gets one matrix row');

// The old board printed every empty state at once. Exactly one should appear when a
// lane has content and its siblings do not.
ok(!str_contains($html, 'Nothing on fire. Enjoy it.'), 'active empty state is absent while an incident is open');
ok(!str_contains($html, 'No unexplained yellow lights.'), 'degraded empty state is absent while a signal is open');
ok(!str_contains($html, 'No stale notices hanging around.'), 'advisory empty state is absent while an advisory is open');
ok(!str_contains($html, 'data-lox-section="unverified"'), 'the no-answer lane is omitted when everything answered');
ok(!str_contains($html, 'CrowdStrike'), 'retired providers do not appear');

// A quiet board should read as clear, with a single empty state per lane.
$quiet = $state;
$quiet['outages'] = [];
$quiet['long_running'] = [];
$quiet['signals'] = [];
$quiet['providers'] = [$providers[3]];
$quiet['meta'] = ['active_outage_count' => 0, 'long_running_count' => 0, 'signal_count' => 0, 'unverified_count' => 0, 'operational_count' => 1];
$quietHtml = render($quiet, $config);
ok(str_contains($quietHtml, 'ALL CLEAR'), 'quiet board reports all clear');
ok(str_contains($quietHtml, 'Nothing on fire. Enjoy it.'), 'quiet board shows the active empty state');
ok(substr_count($quietHtml, 'lox-card--down') === 0, 'quiet board renders no outage cards');

// A correlated Microsoft 365 dependency signal must name its independently
// reporting providers without fabricating official Microsoft confirmation.
$related = [
    'id' => 'microsoft365', 'provider_id' => 'microsoft365', 'name' => 'Microsoft 365',
    'stateCode' => 'degraded', 'lane' => 'signals', 'source_type' => 'cross_provider_corroboration',
    'summary' => '3 independent providers reported Microsoft 365-related incidents. Likely shared disruption.',
    'data_observed_at' => $iso(900), 'url' => 'https://status.cloud.microsoft/m365',
    'correlation_count' => 3,
    'correlation_sources' => [
        ['name' => 'Zoom', 'title' => 'Microsoft 365 integration disruption', 'url' => 'https://status.zoom.us/incidents/zoom-test'],
        ['name' => 'Netlify', 'title' => 'Delayed mail to Outlook', 'url' => 'https://www.netlifystatus.com/incidents/netlify-test'],
        ['name' => 'OpenAI', 'title' => 'Microsoft 365 connector errors', 'url' => 'https://status.openai.com/incidents/openai-test'],
    ],
];
$correlatedState = $quiet;
$correlatedState['signals'] = [$related];
$correlatedState['providers'] = [$related, $providers[3]];
$correlatedState['meta']['signal_count'] = 1;
$correlatedHtml = render($correlatedState, $config);
ok(str_contains($correlatedHtml, 'DEGRADED + CORRELATED SIGNALS'), 'section title does not claim no incident exists');
ok(str_contains($correlatedHtml, 'Likely Microsoft 365-related disruption'), 'correlated signal title is prominent');
ok(str_contains($correlatedHtml, '3 provider reports'), 'corroborating provider count is visible');
ok(str_contains($correlatedHtml, 'aria-label="Corroborating provider notices"'), 'source evidence is accessible');
ok(str_contains($correlatedHtml, 'https://status.zoom.us/incidents/zoom-test'), 'source links appear on the card');
ok(str_contains($correlatedHtml, 'Netlify: Delayed mail to Outlook'), 'source incident titles appear on the card');
ok(!str_contains(strtolower($correlatedHtml), 'no incident filed'), 'board never infers that no Microsoft incident was filed');
ok(!str_contains($correlatedHtml, 'We last checked</dt><dd>never'), 'cross-provider signal does not imply an unauthenticated Microsoft poll');
ok(str_contains($correlatedHtml, 'root cause or incident status'), 'correlation does not falsely establish causation');
// The public must see a weaker related-impact watch when the two first-party
// incidents are still active but did not begin in the same 90-minute window.
$related['correlation_tier'] = 'related_reports';
$related['correlation_count'] = 2;
$related['correlation_sources'] = array_slice($related['correlation_sources'], 0, 2);
$relatedState = $correlatedState;
$relatedState['signals'] = [$related];
$relatedState['providers'] = [$related, $providers[3]];
$relatedHtml = render($relatedState, $config);
ok(str_contains($relatedHtml, '>RELATED</span>'), 'active related issues use a distinct watch badge');
ok(str_contains($relatedHtml, 'Related active Microsoft 365-related disruption'), 'related watch is visibly different from confirmed cluster');
ok(str_contains($relatedHtml, 'incident starts are hours apart'), 'watch explains its weaker timing evidence');
ok(str_contains($relatedHtml, 'Netlify: Delayed mail to Outlook'), 'related watch retains source details');
ok(!str_contains($relatedHtml, 'within a 90-minute window'), 'related watch must not claim simultaneous onsets');

echo "board render tests passed\n";
