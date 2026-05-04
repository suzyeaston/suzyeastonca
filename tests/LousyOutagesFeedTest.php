<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$feedFile = __DIR__ . '/../plugins/lousy-outages/includes/Feeds.php';
$src = file_get_contents($feedFile);
if (!is_string($src) || $src === '') { echo "FAIL: unreadable feed file\n"; exit(1); }

$checks = [
    'unknown filter option' => "lo_status_feed_include_unknown",
    'max items filter option' => "lo_status_feed_max_items",
    'incident days filter option' => "lo_status_feed_official_incident_days",
    'unknown excluded diagnostics' => "unknown_excluded",
    'operational excluded diagnostics' => "operational_excluded",
    'old incidents excluded diagnostics' => "old_incidents_excluded",
    'current provider count diagnostics' => "current_provider_items",
    'official incident count diagnostics' => "official_incident_items",
    'unconfirmed signal count diagnostics' => "unconfirmed_signal_items",
];
foreach ($checks as $name => $needle) {
    if (strpos($src, $needle) === false) { echo "FAIL: missing {$name}\n"; exit(1); }
}

echo "OK\n";
