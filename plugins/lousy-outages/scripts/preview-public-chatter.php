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
define('LOUSY_OUTAGES_NO_EMAIL', true);
require_once $wpLoad;
\SuzyEaston\LousyOutages\MailTransport::set_enabled(false);
$src = new \SuzyEaston\LousyOutages\Sources\HackerNewsChatterSource();
$rows = $src->collect(['dry_run'=>true,'diagnostic_mode'=>true,'window_minutes'=>$window,'suppress_notifications'=>true,'no_email'=>true]);
$diag = get_option('lo_diag_hacker_news_chatter', []);
$preview = array_slice((array)($diag['chatter_candidates_preview_sample'] ?? []), 0, $limit);

$accepted = array_values(array_filter($preview, static fn($p) => ($p['reject_reason'] ?? '') === 'accepted'));
$rejected = array_values(array_filter($preview, static fn($p) => ($p['reject_reason'] ?? '') !== 'accepted'));

echo "Accepted candidates: " . count($accepted) . "\n";
foreach ($accepted as $p) {
    echo "[ACCEPT] provider=" . ($p['provider'] ?? '') .
        " category=" . ($p['category'] ?? '') .
        " quote=\"" . substr((string)($p['quote'] ?? ''), 0, 160) . "\"" .
        " source_url=" . ($p['source_url'] ?? '') .
        " observed_at=" . ($p['observed_at'] ?? '') . "\n";
}
echo "\nRejected candidates: " . count($rejected) . "\n";
foreach ($rejected as $p) {
    echo "[REJECT] reason=" . ($p['reject_reason'] ?? '') .
        " provider=" . ($p['provider'] ?? '') .
        " category=" . ($p['category'] ?? '') .
        " quote=\"" . substr((string)($p['quote'] ?? ''), 0, 160) . "\"" .
        " source_url=" . ($p['source_url'] ?? '') .
        " observed_at=" . ($p['observed_at'] ?? '') . "\n";
}
