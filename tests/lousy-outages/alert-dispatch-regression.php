<?php
declare(strict_types=1);

/**
 * Realtime alert dispatch regressions. Mail is captured in-process. No subscriber mail is sent.
 */

if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('YEAR_IN_SECONDS')) define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');

$GLOBALS['opts'] = [];
$GLOBALS['mails'] = [];
$GLOBALS['mail_fail'] = [];
$GLOBALS['batch_size'] = null;
$GLOBALS['cron'] = [];
$GLOBALS['uuid'] = 0;

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

function reset_state(): void {
    $GLOBALS['opts'] = [];
    $GLOBALS['mails'] = [];
    $GLOBALS['mail_fail'] = [];
    $GLOBALS['batch_size'] = null;
    $GLOBALS['cron'] = [];
}

if (!function_exists('sanitize_key')) { function sanitize_key($k) { return trim((string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)), '_-'); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e) { return trim(strtolower((string) $e)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t) { return trim(strip_tags((string) $t)); } }
if (!function_exists('sanitize_title_with_dashes')) { function sanitize_title_with_dashes($t) { $t = strtolower((string) $t); $t = preg_replace('/[^a-z0-9\-]+/', '-', $t) ?? ''; return trim($t, '-'); } }
if (!function_exists('is_email')) { function is_email($e) { return is_string($e) && str_contains($e, '@') && !str_contains($e, ' '); } }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $autoload = false) { $GLOBALS['opts'][$k] = $v; return true; } }
if (!function_exists('add_option')) { function add_option($k, $v, $deprecated = '', $autoload = true) { if (array_key_exists($k, $GLOBALS['opts'])) { return false; } $GLOBALS['opts'][$k] = $v; return true; } }
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; } }
if (!function_exists('get_transient')) { function get_transient($k) { return false; } }
if (!function_exists('home_url')) { function home_url($p = '/') { return 'https://example.com' . $p; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url) { return (string) $url; } }
if (!function_exists('current_time')) { function current_time($type = 'mysql', $gmt = false) { return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); } }
if (!function_exists('wp_date')) { function wp_date($format, $timestamp = null, $timezone = null) { $timestamp = $timestamp ?? time(); if ($timezone instanceof DateTimeZone) { return (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone($timezone)->format($format); } return gmdate((string) $format, (int) $timestamp); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v) { return json_encode($v); } }
if (!function_exists('wp_generate_uuid4')) { function wp_generate_uuid4() { $GLOBALS['uuid']++; return sprintf('00000000-0000-4000-8000-%012d', $GLOBALS['uuid']); } }
if (!function_exists('do_action')) { function do_action($h, ...$a): void {} }
if (!function_exists('add_action')) { function add_action($h, $c, $p = 10, $a = 1) {} }
if (!function_exists('remove_action')) { function remove_action($h, $c, $p = 10) {} }
if (!function_exists('add_filter')) { function add_filter($h, $c, $p = 10, $a = 1) {} }
if (!function_exists('apply_filters')) { function apply_filters($tag, $value, ...$extra) { if ($tag === 'lousy_outages_alert_recipient_batch_size' && isset($GLOBALS['batch_size'])) { return (int) $GLOBALS['batch_size']; } return $value; } }
if (!function_exists('esc_html')) { function esc_html($v) { return (string) $v; } }
if (!function_exists('esc_attr')) { function esc_attr($v) { return (string) $v; } }
if (!function_exists('esc_url')) { function esc_url($v) { return (string) $v; } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($v) { return (string) $v; } }
if (!function_exists('get_bloginfo')) { function get_bloginfo($show = '', $filter = 'raw') { return 'Lousy Outages'; } }
if (!function_exists('wp_specialchars_decode')) { function wp_specialchars_decode($v, $quote = ENT_NOQUOTES) { return (string) $v; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($hook, $args = []) { foreach ((array) ($GLOBALS['cron'] ?? []) as $ts => $events) { if (is_array($events) && isset($events[$hook])) { return (int) $ts; } } return false; } }
if (!function_exists('_get_cron_array')) { function _get_cron_array() { return $GLOBALS['cron'] ?? []; } }
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        $fail = !empty($GLOBALS['mail_fail'][(string) $to]);
        $GLOBALS['mails'][] = ['to' => (string) $to, 'subject' => (string) $subject, 'body' => (string) $message, 'ok' => !$fail];
        return !$fail;
    }
}

require_once __DIR__ . '/../../lousy-outages/includes/Model/Incident.php';
require_once __DIR__ . '/../../lousy-outages/includes/Storage/EpisodeStore.php';
require_once __DIR__ . '/../../lousy-outages/includes/Storage/IncidentStore.php';
require_once __DIR__ . '/../../lousy-outages/includes/Email/Composer.php';
require_once __DIR__ . '/../../lousy-outages/includes/MailTransport.php';
require_once __DIR__ . '/../../lousy-outages/includes/Mailer.php';
require_once __DIR__ . '/../../lousy-outages/includes/ProviderRegistry.php';
require_once __DIR__ . '/../../lousy-outages/includes/Providers.php';
require_once __DIR__ . '/../../lousy-outages/includes/IncidentAlerts.php';
require_once __DIR__ . '/../../lousy-outages/includes/email-templates.php';
require_once __DIR__ . '/../../lousy-outages/includes/Cron/CanonicalPipeline.php';

use SuzyEaston\LousyOutages\Cron\CanonicalPipeline;
use SuzyEaston\LousyOutages\IncidentAlerts;
use SuzyEaston\LousyOutages\Model\Incident;
use SuzyEaston\LousyOutages\Storage\EpisodeStore;

function subscribers(array $emails): void {
    update_option('lo_subscribers', $emails, false);
    update_option('lousy_outages_email', '', false);
    update_option('admin_email', '', false);
}

function provider_state(string $id, string $title, string $incidentId, string $startedAt, string $status = 'major_outage'): array {
    return [
        'id' => $id,
        'name' => $id,
        'status' => $status,
        'summary' => $title,
        'url' => 'https://status.example/' . $id,
        'source_type' => 'statuspage',
        'updated_at' => $startedAt,
        'incidents' => [[
            'id' => $incidentId,
            'title' => $title,
            'name' => $title,
            'summary' => $title,
            'impact' => 'critical',
            'status' => $status,
            'started_at' => $startedAt,
            'url' => 'https://status.example/' . $id . '/' . $incidentId,
        ]],
    ];
}

function snapshot_from_states(array $states): array {
    $providers = [];
    foreach ($states as $id => $state) {
        $ref = new ReflectionClass(CanonicalPipeline::class);
        $method = $ref->getMethod('providerPayload');
        $method->setAccessible(true);
        $providers[] = $method->invoke(null, (string) $id, $state, gmdate('c'));
    }
    return ['providers' => $providers, 'fetched_at' => gmdate('c')];
}

function mail_bodies(): string {
    return implode("\n", array_map(static fn(array $mail): string => $mail['subject'] . "\n" . $mail['body'], $GLOBALS['mails']));
}

function assert_no_address(string $blob, string $address, string $label): void {
    ok(!str_contains($blob, $address), $label . ' contains subscriber address');
}

$vancouver = new DateTimeZone('America/Vancouver');
$detectedLabel = lo_format_alert_timestamp((new DateTimeImmutable('2026-09-25 16:09:00', $vancouver))->getTimestamp());
ok($detectedLabel === 'Sep 25, 2026 4:09 PM PDT', 'named timezone render got ' . $detectedLabel);

reset_state();
subscribers(['solo@subscribers.test']);
$before = count($GLOBALS['mails']);
$openai = CanonicalPipeline::publishProviderDetection('openai', provider_state('openai', 'Issues with Codex', 'codex-1', '2026-09-25T23:09:00Z'), 'cycle-openai');
ok(count($GLOBALS['mails']) === $before + 1, 'single OpenAI incident mailed without another provider');
ok(str_contains($GLOBALS['mails'][0]['subject'], 'OpenAI'), 'single alert names OpenAI');
ok(str_contains($GLOBALS['mails'][0]['subject'], 'Issues with Codex'), 'single alert names the incident');
ok(!str_contains(strtolower($GLOBALS['mails'][0]['subject']), 'zscaler'), 'OpenAI alert did not wait for Zscaler');
ok((int) ($openai['emails_sent'] ?? 0) === 1, 'dispatch result records the send');
$again = count($GLOBALS['mails']);
CanonicalPipeline::publishProviderDetection('openai', provider_state('openai', 'Issues with Codex', 'codex-1', '2026-09-25T23:09:00Z'), 'cycle-openai');
ok(count($GLOBALS['mails']) === $again, 'repeated OpenAI snapshot does not send a second email');
$healthy = count($GLOBALS['mails']);
$skipped = CanonicalPipeline::publishProviderDetection('github', ['status' => 'operational', 'incidents' => []], 'cycle-openai');
ok(count($GLOBALS['mails']) === $healthy, 'operational provider does not send');
ok(!empty($skipped['skipped']), 'operational provider is not alertable');

reset_state();
subscribers(['zoom@subscribers.test']);
$zoom = [
    'zoom' => [
        'status' => 'major_outage',
        'name' => 'Zoom',
        'summary' => 'Zoom',
        'url' => 'https://status.zoom.us',
        'source_type' => 'statuspage',
        'incidents' => [
            ['id' => 'zoom-a', 'title' => 'Meetings unavailable', 'impact' => 'critical', 'status' => 'major_outage', 'started_at' => '2026-09-25T19:16:00Z', 'url' => 'https://status.zoom.us/a'],
            ['id' => 'zoom-b', 'title' => 'Phone unavailable', 'impact' => 'critical', 'status' => 'major_outage', 'started_at' => '2026-09-25T19:20:00Z', 'url' => 'https://status.zoom.us/b'],
        ],
    ],
];
IncidentAlerts::process_snapshot(snapshot_from_states($zoom), ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === 1, 'two Zoom incidents produce one email');
$zoomMail = $GLOBALS['mails'][0];
foreach (['subject', 'body'] as $part) {
    ok(str_contains($zoomMail[$part], '2 incidents affecting 1 provider'), 'Zoom ' . $part . ' counts incidents and providers separately: ' . $zoomMail['subject']);
}
ok(!str_contains(strtolower($zoomMail['subject'] . $zoomMail['body']), '2 providers'), 'Zoom email does not say 2 providers');
ok(!str_contains(strtolower($zoomMail['subject'] . $zoomMail['body']), 'at once'), 'Zoom email does not claim simultaneity');
ok(!str_contains(strtolower($zoomMail['subject'] . $zoomMail['body']), 'same time'), 'Zoom email does not say same time');
ok(!str_contains(strtolower($zoomMail['subject'] . $zoomMail['body']), 'simultaneously'), 'Zoom email does not say simultaneously');
$zoomAgain = count($GLOBALS['mails']);
IncidentAlerts::process_snapshot(snapshot_from_states($zoom), ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === $zoomAgain, 'repeated Zoom snapshot does not duplicate the email');

reset_state();
subscribers(['multi@subscribers.test']);
$pair = [
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-2', '2026-09-25T23:09:00Z'),
    'zscaler' => provider_state('zscaler', 'Portal disruption', 'zs-1', '2026-09-25T23:09:00Z'),
];
IncidentAlerts::process_snapshot(snapshot_from_states($pair), ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === 1, 'incidents already pending together are one email');
ok(str_contains($GLOBALS['mails'][0]['subject'], '2 incidents affecting 2 providers'), 'multi-provider subject counts both sides');
ok(str_contains($GLOBALS['mails'][0]['body'], '2 incidents affecting 2 providers'), 'multi-provider body matches the subject');

reset_state();
$people = [];
for ($i = 1; $i <= 26; $i++) {
    $people[] = sprintf('u%02d@subscribers.test', $i);
}
subscribers($people);
$GLOBALS['batch_size'] = 25;
foreach (array_slice($people, 0, 25) as $email) {
    $GLOBALS['mail_fail'][$email] = true;
}
IncidentAlerts::process_snapshot(snapshot_from_states([
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-batch', '2026-09-25T23:09:00Z'),
]), ['mode' => 'canonical_refresh']);
$sentTo = array_column($GLOBALS['mails'], 'to');
ok(count($GLOBALS['mails']) === 26, 'recipient batch larger than 25 is drained in the same run, got ' . count($GLOBALS['mails']));
ok(in_array('u26@subscribers.test', $sentTo, true), 'recipient 26 is not starved by the failing prefix');
ok(!empty(array_filter($GLOBALS['mails'], static fn(array $mail): bool => $mail['to'] === 'u26@subscribers.test' && $mail['ok'])), 'recipient 26 was accepted');
$pendingDiag = (array) get_option('lousy_outages_last_alert_processing_diagnostics', []);
ok((int) ($pendingDiag['result']['pending'] ?? 0) === 25, 'failed recipients stay pending');
assert_no_address((string) json_encode($pendingDiag), 'u01@subscribers.test', 'processing diagnostics');
$failMails = count($GLOBALS['mails']);
$GLOBALS['mail_fail'] = [];
IncidentAlerts::process_snapshot(snapshot_from_states([
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-batch', '2026-09-25T23:09:00Z'),
]), ['mode' => 'canonical_refresh']);
$retried = array_slice($GLOBALS['mails'], $failMails);
ok(count($retried) === 25, 'retry sends only the previous failures, got ' . count($retried));
ok(!in_array('u26@subscribers.test', array_column($retried, 'to'), true), 'successful recipient is not mailed again');

reset_state();
subscribers(['a@subscribers.test', 'b@subscribers.test', 'c@subscribers.test', 'd@subscribers.test']);
$checkpoint = snapshot_from_states([
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-defer', '2026-09-25T23:09:00Z'),
]);
$deferred = IncidentAlerts::process_snapshot($checkpoint, ['mode' => 'canonical_refresh', 'skip_delivery' => true]);
ok($GLOBALS['mails'] === [], 'snapshot publication with skip_delivery does not send');
ok(!empty($deferred['skip_delivery']), 'checkpoint is marked skip_delivery');
$episodes = (new EpisodeStore())->all();
ok(count($episodes) === 1, 'checkpoint still opens the episode');
$guid = (string) array_key_first($episodes);
ok((int) ($episodes[$guid]['first_detected'] ?? 0) > 0, 'first_detected is recorded at observation');
(new EpisodeStore())->saveDelivery($guid, ['a@subscribers.test', 'b@subscribers.test'], [], ['a@subscribers.test', 'b@subscribers.test', 'c@subscribers.test', 'd@subscribers.test']);
IncidentAlerts::drain_deferred_alerts(['snapshot' => $checkpoint, 'cycle_id' => 'deferred']);
$drainedTo = array_column($GLOBALS['mails'], 'to');
sort($drainedTo);
ok($drainedTo === ['c@subscribers.test', 'd@subscribers.test'], 'interrupted delivery retries only the recipients not yet accepted: ' . implode(',', $drainedTo));
$afterDrain = count($GLOBALS['mails']);
IncidentAlerts::drain_deferred_alerts(['snapshot' => $checkpoint, 'cycle_id' => 'deferred']);
ok(count($GLOBALS['mails']) === $afterDrain, 'a second drain does not resend');

reset_state();
subscribers(['locked@subscribers.test']);
update_option('lousy_outages_alert_delivery_lock', ['token' => 'other-worker', 'expires_at' => time() + 120], false);
$locked = IncidentAlerts::process_snapshot(snapshot_from_states([
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-lock', '2026-09-25T23:09:00Z'),
]), ['mode' => 'canonical_refresh']);
ok(!empty($locked['delivery_locked']), 'overlapping worker does not enter delivery');
ok($GLOBALS['mails'] === [], 'overlapping worker does not send');
update_option('lousy_outages_alert_delivery_lock', ['token' => 'stale-worker', 'expires_at' => time() - 5], false);
IncidentAlerts::process_snapshot(snapshot_from_states([
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-lock', '2026-09-25T23:09:00Z'),
]), ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === 1, 'expired delivery lock can be taken by the next worker');

reset_state();
subscribers(['tz@subscribers.test']);
$detectedAt = time() - 90;
$providerAt = (new DateTimeImmutable('2020-01-15 08:05:00', $vancouver))->getTimestamp();
$detectedLabelInMail = lo_format_alert_timestamp($detectedAt);
$providerLabelInMail = lo_format_alert_timestamp($providerAt);
(new EpisodeStore())->observe([
    new Incident('OpenAI', 'openai:codex-tz', 'Issues with Codex', 'major_outage', 'https://status.example/openai/codex-tz', null, 'critical', $providerAt, null),
], ['openai' => 'major_outage'], $detectedAt);
$tzSnapshot = snapshot_from_states([
    'openai' => provider_state('openai', 'Issues with Codex', 'codex-tz', '2026-09-25T19:00:00Z'),
]);
IncidentAlerts::process_snapshot($tzSnapshot, ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === 1, 'timezone fixture sent');
ok(str_contains($GLOBALS['mails'][0]['body'], $detectedLabelInMail), 'email renders local detection in America/Vancouver: ' . $detectedLabelInMail);
ok(!str_contains($GLOBALS['mails'][0]['body'], $providerLabelInMail), 'email does not render the provider occurrence time as detection');
ok(!str_contains($GLOBALS['mails'][0]['body'], 'GMT-0800'), 'email does not use a fixed GMT-0800 offset');
ok(str_contains($detectedLabelInMail, 'PDT') || str_contains($detectedLabelInMail, 'PST'), 'rendered zone is Pacific, got ' . $detectedLabelInMail);
$stored = (new EpisodeStore())->all();
$tzEpisode = $stored[array_key_first($stored)];
ok((int) $tzEpisode['first_detected'] === $detectedAt, 'first_detected stays the local observation epoch');
ok((int) $tzEpisode['provider_occurred_at'] === $providerAt, 'provider occurrence time is stored separately');
ok((int) $tzEpisode['first_detected'] !== (int) $tzEpisode['provider_occurred_at'], 'detection and provider time are not the same instant');

reset_state();
$GLOBALS['cron'] = [
    time() - 180 => [
        'lousy_outages_refresh_official_providers' => [[]],
        'lousy_outages_publish_alerts' => [[]],
        'lousy_outages_refresh_continue' => [[]],
    ],
];
$health = IncidentAlerts::alert_health();
ok((int) $health['schedule_overdue_seconds'] >= 180, 'schedule lag is reported');
ok((int) $health['alert_hook_overdue_seconds'] >= 180, 'alert hook lag is reported');
ok((int) $health['continuation_hook_overdue_seconds'] >= 180, 'continuation lag is reported');
ok((int) $health['recurring_schedule_seconds'] === 900, 'canonical recurring schedule is 15 minutes');
ok((int) $health['interval_option_seconds'] === 300, 'settings interval stays visible and distinct');
ok(array_key_exists('pending_recipient_slots', $health), 'pending recipient slots are counted');
ok(array_key_exists('last_detection_to_send_seconds', $health), 'detection-to-send delay is surfaced');
assert_no_address((string) json_encode($health), 'tz@subscribers.test', 'alert health');

reset_state();
subscribers(['retry@subscribers.test']);
$GLOBALS['mail_fail']['retry@subscribers.test'] = true;
$retryState = provider_state('openai', 'Issues with Codex', 'codex-retry', '2026-09-25T23:09:00Z');
$retryCycle = [
    'cycle_id' => 'cycle-retry',
    'provider_states' => ['openai' => $retryState],
    'alerted_provider_ids' => [],
    'errors' => [],
];
$remember = new ReflectionMethod(CanonicalPipeline::class, 'rememberProviderAlert');
$remember->setAccessible(true);
$rememberCaller = function (array &$cycle) use ($remember, $retryState): void {
    $remember->invokeArgs(null, [&$cycle, 'openai', $retryState]);
};
$rememberCaller($retryCycle);
ok(!in_array('openai', (array) $retryCycle['alerted_provider_ids'], true), 'failed outage send stays unmarked for the next pass');
ok($GLOBALS['mails'] !== [] && $GLOBALS['mails'][0]['ok'] === false, 'failed attempt was actually handed to the mailer');
$pendingEpisode = (new EpisodeStore())->all();
ok(count($pendingEpisode) === 1, 'failed send still opens the episode');
$pendingGuid = (string) array_key_first($pendingEpisode);
ok(!empty($pendingEpisode[$pendingGuid]['email_pending_recipients']), 'failed recipient stays pending');
$closedWhileOwed = (new EpisodeStore())->observe([], ['openai' => 'operational'], time());
ok($closedWhileOwed['closed'] === [], 'a healthy stale snapshot does not close an episode that still owes mail');
ok(!empty((new EpisodeStore())->all()[$pendingGuid]['active']), 'owed episode stays active');

update_option('lousy_outages_current_state', [
    'providers' => [[
        'id' => 'openai',
        'name' => 'OpenAI',
        'stateCode' => 'operational',
        'state' => 'Operational',
        'summary' => 'Operational',
        'sourceType' => 'statuspage',
        'incidents' => [],
    ]],
    'fetched_at' => gmdate('c'),
], false);
$overlay = new ReflectionMethod(CanonicalPipeline::class, 'snapshotForAlertDrain');
$overlay->setAccessible(true);
$overlaid = $overlay->invoke(null, $retryCycle);
$overlaidCodes = [];
foreach ((array) ($overlaid['providers'] ?? []) as $provider) {
    if (is_array($provider)) {
        $overlaidCodes[(string) ($provider['id'] ?? '')] = (string) ($provider['stateCode'] ?? '');
    }
}
ok(($overlaidCodes['openai'] ?? '') === 'major_outage', 'drain snapshot keeps the fetched outage over the stale operational commit');
$GLOBALS['mail_fail'] = [];
$beforeRetry = count($GLOBALS['mails']);
IncidentAlerts::drain_deferred_alerts(['cycle_id' => 'cycle-retry', 'snapshot' => $overlaid]);
$retriedMails = array_slice($GLOBALS['mails'], $beforeRetry);
ok(count($retriedMails) === 1 && !empty($retriedMails[0]['ok']), 'next drain sends the owed outage email');
ok(!empty((new EpisodeStore())->all()[$pendingGuid]['active']), 'episode stays open while the provider is still in outage');
$rememberCaller($retryCycle);
ok(in_array('openai', (array) $retryCycle['alerted_provider_ids'], true), 'accepted retry marks the provider alerted');
$marked = count($GLOBALS['mails']);
$rememberCaller($retryCycle);
ok(count($GLOBALS['mails']) === $marked, 'marked provider is not mailed again');

reset_state();
subscribers(['owed@subscribers.test']);
$owedStore = new EpisodeStore();
$owedStore->observe([
    new Incident('Zoom', 'zoom:zoom-live', 'Service degradation', 'degraded', 'https://status.zoom.us/live', null, 'degraded', time() - 20 * HOUR_IN_SECONDS, null),
], ['zoom' => 'degraded']);
$owedEpisodes = $owedStore->all();
$owedGuid = (string) array_key_first($owedEpisodes);
ok($owedGuid !== '', 'owed episode exists');
$owedEpisodes[$owedGuid]['first_detected'] = time() - 20 * HOUR_IN_SECONDS;
$owedEpisodes[$owedGuid]['email_successful_recipients'] = [];
$owedEpisodes[$owedGuid]['email_pending_recipients'] = [];
$owedEpisodes[$owedGuid]['active'] = true;
update_option(EpisodeStore::OPTION, $owedEpisodes, false);
$owedBefore = count($GLOBALS['mails']);
IncidentAlerts::process_snapshot(snapshot_from_states([
    'zoom' => provider_state('zoom', 'Service degradation', 'zoom-live', gmdate('c', time() - 20 * HOUR_IN_SECONDS), 'degraded'),
]), ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === $owedBefore + 1, 'active outage older than 12 hours still mails when nothing was accepted');
ok(!empty($GLOBALS['mails'][$owedBefore]['ok']), 'owed outage mail was accepted');
ok(count((new EpisodeStore())->all()) === 1, 'owed outage reuses the original episode instead of opening a fresh one');
$owedAfter = count($GLOBALS['mails']);
IncidentAlerts::process_snapshot(snapshot_from_states([
    'zoom' => provider_state('zoom', 'Service degradation', 'zoom-live', gmdate('c', time() - 20 * HOUR_IN_SECONDS), 'degraded'),
]), ['mode' => 'canonical_refresh']);
ok(count($GLOBALS['mails']) === $owedAfter, 'owed outage is not mailed twice');

$pipeline = (string) file_get_contents(__DIR__ . '/../../lousy-outages/includes/Cron/CanonicalPipeline.php');
$runStart = strpos($pipeline, 'public static function run');
$publishStart = strpos($pipeline, 'public static function publishAlerts');
ok($runStart !== false && $publishStart !== false && $publishStart > $runStart, 'pipeline methods found');
$run = substr($pipeline, $runStart, $publishStart - $runStart);
$dispatchAt = strpos($run, 'rememberProviderAlert');
$continuationAt = strpos($run, 'CONTINUATION_HOOK');
ok($dispatchAt !== false && $continuationAt !== false && $dispatchAt < $continuationAt, 'provider alerts dispatch before the sweep continuation is scheduled');
ok(str_contains($run, 'publishAlerts'), 'full snapshot commit also publishes alerts in-process');

echo "alert dispatch regressions passed\n";
