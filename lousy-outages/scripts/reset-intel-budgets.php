<?php
declare(strict_types=1);

if ($argc < 2) { fwrite(STDERR, "Usage: php scripts/reset-intel-budgets.php /path/to/wp-load.php [--clear-signals]\n"); exit(2); }
$wpLoad = $argv[1];
$clearSignals = in_array('--clear-signals', $argv, true);
if (!is_file($wpLoad)) { fwrite(STDERR, "wp-load.php not found at: {$wpLoad}\n"); exit(2); }
require_once $wpLoad;

use SuzyEaston\LousyOutages\ExternalSignals;

global $wpdb;
$cleared = [];
if (delete_option('lo_source_budget_state')) { $cleared[] = 'option:lo_source_budget_state'; }

$transients = [
    'transient_timeout_lo_statuspage_',
    'transient_lo_statuspage_',
    'transient_timeout_lo_hn_',
    'transient_lo_hn_',
    'transient_timeout_lo_feed_',
    'transient_lo_feed_',
];
foreach ($transients as $prefix) {
    $like = $wpdb->esc_like('_' . $prefix) . '%';
    $count = (int)$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
    if ($count > 0) { $cleared[] = "options_like:{$prefix}* ({$count})"; }
}

if ($clearSignals) {
    $sources = ['provider_feed','hacker_news_chatter','statuspage_intel','public_chatter','cloudflare_radar','synthetic_canary','community_report_intel'];
    $table = ExternalSignals::table_name();
    $in = implode(',', array_fill(0, count($sources), '%s'));
    $sql = $wpdb->prepare("DELETE FROM {$table} WHERE source IN ($in)", ...$sources);
    $deleted = (int)$wpdb->query($sql);
    $cleared[] = "external_signal_rows:{$deleted}";
}

if (empty($cleared)) {
    echo "Cleared: nothing\n";
} else {
    foreach ($cleared as $line) { echo "Cleared: {$line}\n"; }
}
