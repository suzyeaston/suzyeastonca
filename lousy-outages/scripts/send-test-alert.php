<?php
declare(strict_types=1);

/**
 * Send a Lousy Outages test alert to one email address (loads WordPress from argv wp-load path or default host path).
 *
 * Usage:
 *   php send-test-alert.php suzanneeaston@gmail.com
 *   php send-test-alert.php suzanneeaston@gmail.com /home/user/public_html/wp-load.php
 */

$email = isset($argv[1]) ? trim((string) $argv[1]) : '';
$wpLoad = isset($argv[2]) ? trim((string) $argv[2]) : '/home/uquklkik/public_html/wp-load.php';

if ('' === $email) {
    fwrite(STDERR, "Usage: php send-test-alert.php recipient@example.com [path/to/wp-load.php]\n");
    exit(2);
}

if (!is_file($wpLoad)) {
    fwrite(STDERR, "wp-load.php not found at: {$wpLoad}\n");
    exit(2);
}

require_once $wpLoad;

if (!class_exists('SuzyEaston\\LousyOutages\\IncidentAlerts')) {
    fwrite(STDERR, "IncidentAlerts class not available. Is lousy-outages active?\n");
    exit(1);
}

$result = \SuzyEaston\LousyOutages\IncidentAlerts::send_test_alert_to($email);
$json = function_exists('wp_json_encode') ? wp_json_encode($result) : json_encode($result);
echo ($json ?: '{}') . "\n";

exit(!empty($result['ok']) ? 0 : 1);
