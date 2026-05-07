<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }
$wpLoad = $argv[1] ?? '';
if ($wpLoad === '' || !is_file($wpLoad)) { fwrite(STDERR, "Usage: php preview-public-chatter.php /path/to/wp-load.php [--window-minutes=1440] [--limit=30] [--json]\n"); exit(1); }
$window=1440; $limit=30; $json=false; $sourceCoverage=false; $activeOnly=false;
foreach (array_slice($argv,2) as $arg) {
    if (str_starts_with($arg,'--window-minutes=')) { $window=max(5,(int)substr($arg,17)); }
    if (str_starts_with($arg,'--limit=')) { $limit=max(1,(int)substr($arg,8)); }
    if ($arg === '--json') { $json=true; }
    if ($arg === '--source-coverage') { $sourceCoverage=true; }
    if ($arg === '--active-only') { $activeOnly=true; }
}
$_SERVER['REQUEST_METHOD'] = 'GET';

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!is_array($error)) { return; }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) { return; }
    fwrite(STDERR, sprintf("Fatal error: %s in %s:%d\n", (string) ($error['message'] ?? 'unknown error'), (string) ($error['file'] ?? 'unknown file'), (int) ($error['line'] ?? 0)));
});

define('LOUSY_OUTAGES_NO_EMAIL', true);
require_once $wpLoad;

if (class_exists(\SuzyEaston\LousyOutages\MailTransport::class)
    && method_exists(\SuzyEaston\LousyOutages\MailTransport::class, 'set_enabled')) {
    \SuzyEaston\LousyOutages\MailTransport::set_enabled(false);
}

$publicSource = new \SuzyEaston\LousyOutages\Sources\PublicChatterSource();
$signals = $publicSource->collect(['dry_run'=>true,'diagnostic_mode'=>true,'window_minutes'=>$window,'suppress_notifications'=>true,'no_email'=>true,'collection_trigger'=>'preview']);
$publicDiag = (array) get_option('lo_diag_public_chatter', []);

$hnSource = new \SuzyEaston\LousyOutages\Sources\HackerNewsChatterSource();
$hnRows = $hnSource->collect(['dry_run'=>true,'diagnostic_mode'=>true,'window_minutes'=>$window,'suppress_notifications'=>true,'no_email'=>true,'collection_trigger'=>'preview']);
$hnDiag = (array) get_option('lo_diag_hacker_news_chatter', []);
$reasonCounts = (array)($hnDiag['chatter_rejected_by_reason'] ?? []);

$sourceStatuses = (array)($publicDiag['source_statuses'] ?? []);
$enabledSources = [];
$cooldownSources = [];
$budgetSkipped = [];
foreach ((array)($publicDiag['enabled_sources'] ?? []) as $source => $enabled) {
    if ($enabled) { $enabledSources[] = (string)$source; }
}
foreach ($sourceStatuses as $source => $row) {
    $status = (string)(((array)$row)['status'] ?? 'disabled');
    if (in_array($status, ['cooldown','rate_limited'], true)) { $cooldownSources[] = (string)$source . ' (' . $status . ')'; }
}
foreach ((array)($publicDiag['source_request_details'] ?? []) as $source => $details) {
    if ((int)(((array)$details)['queries_skipped_due_to_budget'] ?? 0) > 0) {
        $budgetSkipped[] = (string)$source . ' (' . (int)(((array)$details)['queries_skipped_due_to_budget'] ?? 0) . ')';
    }
}

$summary = [
    'master_enabled' => !empty($publicDiag['configured']),
    'direct_gate_enabled' => !empty($publicDiag['direct_sources_enabled']),
    'enabled_sources' => $enabledSources,
    'rate_limited_or_cooldown_sources' => $cooldownSources,
    'sources_skipped_by_budget' => $budgetSkipped,
    'active_official_providers_scanned' => array_values(array_map(static fn($p) => (string)($p['provider_name'] ?? $p['provider_id'] ?? ''), (array)($publicDiag['active_incident_seed_providers'] ?? []))),
    'canadian_infrastructure_categories_armed' => array_values(array_map(static fn($p) => (string)($p['label'] ?? $p['category'] ?? ''), (array)($publicDiag['canadian_infrastructure_watchlist'] ?? []))),
    'watch_candidates' => (int)($publicDiag['watch_candidate_count'] ?? 0),
    'promoted_signals' => ['direct_public' => count($signals), 'hn' => count($hnRows)],
    'rejected_reason_summary' => $reasonCounts,
    'source_lanes_available' => (array)(\SuzyEaston\LousyOutages\Sources\SourceRegistry::by_lane()),
    'sources_configured' => (array)($publicDiag['sources_configured'] ?? []),
    'sources_not_configured' => (array)($publicDiag['sources_not_configured'] ?? []),
    'provider_coverage' => (array)($publicDiag['provider_coverage'] ?? []),
    'active_incident_coverage' => (array)($publicDiag['active_incident_coverage'] ?? $publicDiag['official_incident_corroboration'] ?? []),
    'source_request_details' => (array)($publicDiag['source_request_details'] ?? []),
    'query_budget_plan' => (array)($publicDiag['source_budgets'] ?? []),
    'gdelt' => [
        'enabled' => !empty($publicDiag['gdelt_enabled']),
        'attempted' => !empty($publicDiag['gdelt_attempted']),
        'rate_limited' => !empty($publicDiag['gdelt_rate_limited']),
        'cooldown_until' => (string)($publicDiag['gdelt_cooldown_until'] ?? ''),
        'last_response_code' => (int)($publicDiag['gdelt_last_response_code'] ?? 0),
        'queries_skipped_due_to_budget' => (int)($publicDiag['gdelt_queries_skipped_due_to_budget'] ?? 0),
        'rows_seen' => (int)($publicDiag['gdelt_rows_seen'] ?? 0),
        'watch_candidates' => (int)($publicDiag['gdelt_watch_candidates'] ?? 0),
    ],
];

if ($json) {
    echo wp_json_encode(['summary'=>$summary, 'diagnostics'=>$publicDiag], JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$printList = static function (string $label, array $values, int $limit = 30): void {
    $values = array_slice(array_values(array_filter(array_map('strval', $values))), 0, $limit);
    echo $label . ': ' . ($values ? implode(', ', $values) : 'none') . "\n";
};

echo ($sourceCoverage ? "Source Coverage Preview" : "Public Chatter Corroboration Preview") . " (diagnostics-only; no email)\n";
echo "Master enabled: " . ($summary['master_enabled'] ? 'yes' : 'no') . "\n";
echo "Direct gate enabled: " . ($summary['direct_gate_enabled'] ? 'yes' : 'no') . "\n";
$printList('Source lanes available', array_keys($summary['source_lanes_available']), $limit);
$printList('Sources configured', $summary['sources_configured'], $limit);
$printList('Sources not configured', $summary['sources_not_configured'], $limit);
$printList('Enabled sources', $summary['enabled_sources'], $limit);
$printList('Rate-limited/cooldown sources', $summary['rate_limited_or_cooldown_sources'], $limit);
$printList('Sources skipped by budget', $summary['sources_skipped_by_budget'], $limit);
$printList('Active official providers scanned', $summary['active_official_providers_scanned'], $limit);
$printList('Canadian infrastructure categories armed', $summary['canadian_infrastructure_categories_armed'], $limit);
if ($sourceCoverage) {
    echo "Per-provider coverage:\n";
    foreach (array_slice($summary['provider_coverage'], 0, $limit) as $row) { echo '- ' . (string)($row['provider'] ?? 'provider') . ': official=' . (int)($row['official_sources_checked'] ?? 0) . ', public=' . (int)($row['public_sources_checked'] ?? 0) . ', open_web=' . (int)($row['open_web_sources_checked'] ?? 0) . ', telemetry=' . (int)($row['telemetry_sources_checked'] ?? 0) . ', limited=' . (int)($row['sources_limited'] ?? 0) . "\n"; }
    echo "Per-source attempted/skipped/cooldown:\n";
    foreach ($summary['source_request_details'] as $source=>$details) { echo '- ' . (string)$source . ': attempted=' . (int)($details['queries_attempted'] ?? 0) . ', budget_skipped=' . (int)($details['queries_skipped_due_to_budget'] ?? 0) . "\n"; }
    echo "Query budget plan: " . wp_json_encode($summary['query_budget_plan']) . "\n";
}
echo "Watch candidates: " . $summary['watch_candidates'] . "\n";
echo "Promoted signals: " . $summary['promoted_signals']['direct_public'] . " direct public, " . $summary['promoted_signals']['hn'] . " HN\n";
echo "Open web/GDELT: " . ($summary['gdelt']['enabled'] ? 'enabled' : 'disabled') . ', ' . ($summary['gdelt']['attempted'] ? 'attempted' : 'not attempted') . ', HTTP ' . $summary['gdelt']['last_response_code'] . ($summary['gdelt']['cooldown_until'] !== '' ? ', cooldown until ' . $summary['gdelt']['cooldown_until'] : '') . "\n";
echo "Active incident coverage:\n";
foreach (array_slice($summary['active_incident_coverage'], 0, $limit) as $row) { echo '- ' . (string)($row['provider_name'] ?? $row['provider_id'] ?? 'provider') . ': ' . (string)($row['incident_title'] ?? 'active incident') . ' => ' . (string)($row['result_label'] ?? 'unknown') . "\n"; }
echo "Rejected reason summary:\n";
if (!$reasonCounts) { echo "- none\n"; }
foreach ($reasonCounts as $reason => $count) {
    $reasonMeta = \SuzyEaston\LousyOutages\Sources\ChatterRejectionReasons::get((string)$reason);
    echo "- " . (string)($reasonMeta['short_label'] ?? $reason) . ': ' . (int)$count . "\n";
}
