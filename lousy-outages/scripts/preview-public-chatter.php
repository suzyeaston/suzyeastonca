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
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    fwrite(
        STDERR,
        sprintf(
            "Fatal error: %s in %s:%d\n",
            (string) ($error['message'] ?? 'unknown error'),
            (string) ($error['file'] ?? 'unknown file'),
            (int) ($error['line'] ?? 0)
        )
    );
});

define('LOUSY_OUTAGES_NO_EMAIL', true);
require_once $wpLoad;

if (class_exists(\SuzyEaston\LousyOutages\MailTransport::class)
    && method_exists(\SuzyEaston\LousyOutages\MailTransport::class, 'set_enabled')) {
    \SuzyEaston\LousyOutages\MailTransport::set_enabled(false);
}
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
    $reason = (string) ($p['reject_reason'] ?? '');
    $reasonMeta = \SuzyEaston\LousyOutages\Sources\ChatterRejectionReasons::get($reason);
    echo "[REJECT] reason=" . $reason .
        " label=\"" . ($reasonMeta['short_label'] ?? '') . "\"" .
        " provider=" . ($p['provider'] ?? '') .
        " category=" . ($p['category'] ?? '') .
        " quote=\"" . substr((string)($p['quote'] ?? ''), 0, 160) . "\"" .
        " source_url=" . ($p['source_url'] ?? '') .
        " observed_at=" . ($p['observed_at'] ?? '') . "\n";
}
