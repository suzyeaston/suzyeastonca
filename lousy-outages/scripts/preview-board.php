<?php
/**
 * Render the public board to a standalone HTML file for design review.
 *
 * Usage: php scripts/preview-board.php <summary-json-path> <output-html-path>
 *
 * The JSON is a saved response from /wp-json/lousy-outages/v1/summary so the preview
 * shows the same data the live board would.
 */
declare(strict_types=1);

const ABSPATH = __DIR__;
const HOUR_IN_SECONDS = 3600;
const DAY_IN_SECONDS = 86400;
const MINUTE_IN_SECONDS = 60;

define('LOUSY_OUTAGES_VERSION', '0.5.6');

function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($value) { return (string) $value; }
function sanitize_key($value) { return trim(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)), '-_'); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-'); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function apply_filters($hook, $value) { return $value; }
function update_option($key, $value, $autoload = null) { return true; }
function get_option($key, $default = false) {
    if ('date_format' === $key) { return 'M j, Y'; }
    if ('time_format' === $key) { return 'H:i'; }
    return $default;
}
function wp_date($format, $timestamp) { return gmdate($format, (int) $timestamp); }
function home_url($path = '/') { return 'https://www.suzyeaston.ca' . $path; }
function trailingslashit($value) { return rtrim((string) $value, '/\\') . '/'; }
function wp_parse_url($url, $component = -1) { return parse_url((string) $url, $component); }

require __DIR__ . '/../includes/ProviderRegistry.php';
require __DIR__ . '/../includes/Providers.php';
require __DIR__ . '/../includes/Storage/HistoryStore.php';
require __DIR__ . '/../public/board.php';

use function SuzyEaston\LousyOutages\Board\render;

$source = $argv[1] ?? __DIR__ . '/../../dist/summary.json';
$target = $argv[2] ?? '/tmp/lousy-outages-preview.html';

$payload = json_decode((string) file_get_contents($source), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Could not read summary JSON from {$source}\n");
    exit(1);
}
$state = is_array($payload['current_state'] ?? null) ? $payload['current_state'] : $payload;

$board = render($state, [
    'summaryEndpoint' => 'https://www.suzyeaston.ca/wp-json/lousy-outages/v1/summary',
    'historyEndpoint' => 'https://www.suzyeaston.ca/wp-json/lousy-outages/v1/history',
    'refreshEndpoint' => '',
    'refreshNonce' => '',
    'rssUrl' => 'https://www.suzyeaston.ca/?feed=lousy_outages_status',
    'pollInterval' => 60000,
    'version' => LOUSY_OUTAGES_VERSION,
]);

$css = file_get_contents(__DIR__ . '/../assets/board.css');
$pageCss = @file_get_contents(__DIR__ . '/../../assets/css/lousy-outages-page.css') ?: '';

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lousy Outages board preview</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
body { margin: 0; background: #04070a; color: #cfe7d8; }
{$pageCss}
{$css}
</style>
</head>
<body>
<main class="lousy-outages-page">
  <div class="lox-page">
    <header class="lox-page__intro">
      <h1 class="lox-page__title">LOUSY OUTAGES</h1>
      <p class="lox-page__tagline">Official status pages, read for you every 15 minutes. No spin, no vibes.</p>
    </header>
    {$board}
  </div>
</main>
</body>
</html>
HTML;

file_put_contents($target, $html);
echo "Wrote {$target}\n";
