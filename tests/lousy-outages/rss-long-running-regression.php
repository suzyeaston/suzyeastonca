<?php
declare(strict_types=1);

if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!function_exists('wp_json_encode')) { function wp_json_encode($v) { return json_encode($v); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v) { return strip_tags((string) $v); } }

require __DIR__ . '/../../lousy-outages/includes/Adapters.php';
require __DIR__ . '/../../lousy-outages/includes/Fetcher.php';

use SuzyEaston\LousyOutages\Fetcher;
use function SuzyEaston\LousyOutages\Adapters\from_rss_atom;

function assert_true($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
function call_private($object, string $method, array $args) { $ref = new ReflectionMethod($object, $method); $ref->setAccessible(true); return $ref->invokeArgs($object, $args); }

$awsXml = '<rss><channel><item><title>Service disruption in UAE (ME-CENTRAL-1)</title><link>https://status.aws.amazon.com/</link><pubDate>Thu, 30 Apr 2026 12:00:00 GMT</pubDate><description><![CDATA[<p>We continue to experience increased error rates and service disruption in the UAE region (ME-CENTRAL-1). Recovery is expected to take months due to physical infrastructure damage.</p>]]></description></item></channel></rss>';
$normalized = from_rss_atom($awsXml);
assert_true($normalized['state'] === 'major', 'AWS-style RSS remains major/outage state');
assert_true(count($normalized['incidents']) === 1, 'RSS yields one incident');
$raw = $normalized['incidents'][0];
assert_true($raw['updated_at'] === 'Thu, 30 Apr 2026 12:00:00 GMT', 'RSS pubDate is the official updated_at');
assert_true($raw['started_at'] === '', 'RSS pubDate is not copied into started_at');
assert_true(strpos($raw['summary'], '<p>') === false && strpos($raw['summary'], 'physical infrastructure damage') !== false, 'RSS description summary is stripped and retained');

$fetcher = new Fetcher();
$provider = ['id' => 'aws', 'name' => 'AWS', 'status_url' => 'https://status.aws.amazon.com/', 'type' => 'rss'];
$buckets = call_private($fetcher, 'normalize_incident_buckets', [$normalized['incidents'], $provider, 'outage']);
assert_true(count($buckets['active']) === 1, '80-day-old unresolved official RSS incident remains active');
assert_true(count($buckets['recent']) === 0, 'Unresolved old incident is not history');
$entry = $buckets['active'][0];
assert_true($entry['scope'] === 'regional', 'Regional scope derived');
assert_true($entry['region_name'] === 'UAE' && $entry['region_code'] === 'ME-CENTRAL-1', 'UAE and ME-CENTRAL-1 metadata derived');
assert_true($entry['is_long_running'] === true, 'Long-running metadata is set');
assert_true(substr($entry['last_official_update'], 0, 10) === '2026-04-30', 'last_official_update remains April 30');

$result = call_private($fetcher, 'assemble_result', [[
  'id'=>'aws','name'=>'AWS','provider'=>'AWS','status'=>'unknown','status_label'=>'Unknown','summary'=>'Waiting','message'=>'Waiting','updated_at'=>'2026-07-19T00:00:00+00:00','checked_at'=>'2026-07-19T10:00:00+00:00','url'=>'https://status.aws.amazon.com/','incidents'=>[],'error'=>null,'source_type'=>'rss'
], $normalized, $provider]);
$result = call_private($fetcher, 'apply_tile_metadata', [$result, $provider, false]);
assert_true($result['tile_kind'] === 'outage', 'Unresolved old incident is not converted to tile_kind=signal');
assert_true($result['checked_at'] === '2026-07-19T10:00:00+00:00', 'checked_at stays separate from official update time');

$resolved = $raw;
$resolved['status'] = 'resolved';
$resolvedBuckets = call_private($fetcher, 'normalize_incident_buckets', [[$resolved], $provider, 'operational']);
assert_true(count($resolvedBuckets['active']) === 0 && count($resolvedBuckets['recent']) === 0, '80-day-old resolved incident is excluded from active and old history');
$recentResolved = $resolved;
$recentResolved['updated_at'] = gmdate('r', time() - 2 * DAY_IN_SECONDS);
$recentBuckets = call_private($fetcher, 'normalize_incident_buckets', [[$recentResolved], $provider, 'operational']);
assert_true(count($recentBuckets['active']) === 0 && count($recentBuckets['recent']) === 1, 'Recent resolved entries remain history only');


$resolvedXml = '<rss><channel><item><title>Operational issue - Multiple services (UAE)</title><link>https://status.aws.amazon.com/</link><pubDate>Fri, 01 May 2026 12:00:00 GMT</pubDate><description>This issue is now resolved for the UAE region (ME-CENTRAL-1).</description></item><item><title>Operational issue - Multiple services (UAE)</title><link>https://status.aws.amazon.com/</link><pubDate>Thu, 30 Apr 2026 12:00:00 GMT</pubDate><description>We are experiencing a service disruption affecting multiple services in the UAE region (ME-CENTRAL-1).</description></item></channel></rss>';
$resolvedNormalized = from_rss_atom($resolvedXml);
assert_true($resolvedNormalized['state'] === 'operational', 'newer AWS resolved lifecycle update wins');
assert_true(count($resolvedNormalized['incidents']) === 1 && $resolvedNormalized['incidents'][0]['status'] === 'resolved', 'lifecycle grouping keeps only latest update');
$ambiguous = $raw;
$ambiguous['summary'] = 'Service disruption reported.';
$ambiguous['status'] = 'major';
$ambiguousBuckets = call_private($fetcher, 'normalize_incident_buckets', [[$ambiguous], $provider, 'outage']);
assert_true(count($ambiguousBuckets['active']) === 0 && count($ambiguousBuckets['recent']) === 1, 'old ambiguous RSS item moves to history');
echo "ok\n";
