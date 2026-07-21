<?php
$root = dirname(__DIR__, 2);
$files = [
  'main' => file_get_contents($root.'/lousy-outages/lousy-outages.php'),
  'history' => file_get_contents($root.'/lousy-outages/includes/Storage/HistoryStore.php'),
  'api' => file_get_contents($root.'/lousy-outages/includes/Api.php'),
  'js' => file_get_contents($root.'/lousy-outages/assets/lousy-outages.js'),
  'shortcode' => file_get_contents($root.'/lousy-outages/public/shortcode.php'),
];
$fail=function($m){fwrite(STDERR,"FAIL: $m\n"); exit(1);};
if (!str_contains($files['main'], 'Version: 0.3.6') || !str_contains($files['main'], "LOUSY_OUTAGES_VERSION', '0.3.6'")) $fail('version not bumped');
if (preg_match('/lousy_outages_activate\(\).*?HistoryStore\(\).*?->migrate/s', $files['main'])) $fail('activation runs migration');
if (!str_contains($files['main'], 'wp_clear_scheduled_hook( \'lousy_outages_refresh_official_providers\' )')) $fail('orphan cron not cleared');
if (!str_contains($files['main'], "'hourly'")) $fail('cron fallback missing');
if (!str_contains($files['main'], 'has_action( \'lousy_outages_refresh_official_providers\'')) $fail('double callback guard missing');
if (preg_match('/function loadCanonical\(\): array\s*\{[^}]*array_values/s', $files['history'])) $fail('loadCanonical copies with array_values');
if (preg_match('/if \(!\$force[^}]*validated[^}]*get_option\(self::OPTION_CANONICAL/s', $files['history'])) $fail('validated migration reads canonical events');
if (str_contains($files['api'], "'migration'=>array_diff_key")) $fail('api migration metadata may include events');
if (!str_contains($files['api'], "'page'=>") || !str_contains($files['api'], "'per_page'=>") || !str_contains($files['api'], "'has_more'=>")) $fail('pagination metadata missing');
if (!str_contains($files['js'], "'page', '1'") || !str_contains($files['js'], "'per_page'")) $fail('dashboard does not request first page');
if (str_contains($files['shortcode'], 'External signals (unconfirmed)') || str_contains($files['shortcode'], 'Status feeds + community') || str_contains($files['shortcode'], 'Twitter/X search') || str_contains($files['shortcode'], 'Reddit search')) $fail('stale public copy remains');
if (str_contains($files['shortcode'], 'get_stylesheet_directory()') || str_contains($files['shortcode'], 'get_template_directory()')) $fail('theme-first asset discovery remains');
if (!str_contains($files['shortcode'], "LOUSY_OUTAGES_URL") || !str_contains($files['shortcode'], "/assets/")) $fail('plugin asset URL base missing');
echo "hotfix-035-regression-ok\n";
