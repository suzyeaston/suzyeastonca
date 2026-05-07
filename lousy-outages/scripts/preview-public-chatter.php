<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }
$wpLoad = $argv[1] ?? '';
if ($wpLoad === '' || !is_file($wpLoad)) { fwrite(STDERR, "Usage: php preview-public-chatter.php /path/to/wp-load.php [--window-minutes=1440] [--limit=30]\n"); exit(1); }
$window=1440; $limit=30;
foreach (array_slice($argv,2) as $arg) {
    if (str_starts_with($arg,'--window-minutes=')) { $window=max(5,(int)substr($arg,17)); }
    if (str_starts_with($arg,'--limit=')) { $limit=max(1,(int)substr($arg,8)); }
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
$signals = $publicSource->collect(['dry_run'=>true,'diagnostic_mode'=>true,'window_minutes'=>$window,'suppress_notifications'=>true,'no_email'=>true]);
$publicDiag = (array) get_option('lo_diag_public_chatter', []);

$hnSource = new \SuzyEaston\LousyOutages\Sources\HackerNewsChatterSource();
$hnRows = $hnSource->collect(['dry_run'=>true,'diagnostic_mode'=>true,'window_minutes'=>$window,'suppress_notifications'=>true,'no_email'=>true]);
$hnDiag = (array) get_option('lo_diag_hacker_news_chatter', []);
$reasonCounts = (array)($hnDiag['chatter_rejected_by_reason'] ?? []);

$printList = static function (string $label, array $values, int $limit = 30): void {
    echo $label . ': ';
    $values = array_slice(array_values(array_filter(array_map('strval', $values))), 0, $limit);
    echo $values ? implode(', ', $values) : 'none';
    echo "\n";
};

echo "Public Chatter Corroboration Preview (diagnostics-only; no email, no inserts requested)\n";
echo "Master enabled: " . (!empty($publicDiag['configured']) ? 'yes' : 'no') . "\n";
echo "Direct source gate: " . (!empty($publicDiag['direct_sources_enabled']) ? 'enabled' : 'disabled') . "\n";

echo "Enabled source checkboxes:\n";
foreach ((array)($publicDiag['enabled_sources'] ?? []) as $source => $enabled) {
    echo "- {$source}: " . ($enabled ? 'checked' : 'unchecked') . "\n";
}

echo "Disabled/blocked sources:\n";
foreach ((array)($publicDiag['skipped_sources'] ?? []) as $source => $reasons) {
    $reasons = array_values(array_unique(array_filter(array_map('strval', (array)$reasons))));
    if (!$reasons) { continue; }
    echo "- {$source}: " . implode(', ', $reasons) . "\n";
}

$printList('Providers scanned', array_map(static fn($p) => (string)($p['provider_name'] ?? $p['provider_id'] ?? ''), (array)($publicDiag['providers_scanned'] ?? [])), $limit);
$printList('Active incident seed providers', array_map(static fn($p) => (string)($p['provider_name'] ?? $p['provider_id'] ?? ''), (array)($publicDiag['active_incident_seed_providers'] ?? [])), $limit);
$printList('Canadian infra providers scanned', array_map(static fn($p) => (string)($p['provider_name'] ?? $p['provider_id'] ?? ''), (array)($publicDiag['canadian_infrastructure_providers_scanned'] ?? [])), $limit);

echo "Mentions seen by source:\n";
foreach ((array)($publicDiag['mentions_seen_by_source'] ?? []) as $source => $count) { echo "- {$source}: " . (int)$count . "\n"; }
echo "Mentions seen by provider:\n";
foreach (array_slice((array)($publicDiag['mentions_seen_by_provider'] ?? []), 0, $limit, true) as $provider => $count) { echo "- {$provider}: " . (int)$count . "\n"; }

echo "Watch candidates: " . (int)($publicDiag['watch_candidate_count'] ?? 0) . "\n";
foreach (array_slice((array)($publicDiag['watch_candidates'] ?? []), 0, $limit) as $candidate) {
    echo "[WATCH] provider=" . ($candidate['provider_name'] ?? $candidate['provider_id'] ?? '') . " source=" . ($candidate['source_label'] ?? $candidate['source'] ?? '') . " count=" . (int)($candidate['count'] ?? 0) . " reason=" . ($candidate['reason'] ?? '') . "\n";
}

echo "Promoted signals: " . count($signals) . " direct public, " . count($hnRows) . " HN\n";
foreach (array_slice($signals, 0, $limit) as $signal) {
    echo "[PROMOTED] provider=" . ($signal['provider_name'] ?? $signal['provider_id'] ?? '') . " source=" . ($signal['source'] ?? '') . " severity=" . ($signal['severity'] ?? '') . "\n";
}

echo "Rejected reasons:\n";
foreach ($reasonCounts as $reason => $count) {
    $reasonMeta = \SuzyEaston\LousyOutages\Sources\ChatterRejectionReasons::get((string)$reason);
    echo "- {$reason}: " . (int)$count . " (" . (string)($reasonMeta['short_label'] ?? '') . ")\n";
}
