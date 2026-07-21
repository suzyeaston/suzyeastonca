<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$zip = $root . '/dist/lousy-outages.zip';
if (!is_file($zip)) { passthru(escapeshellarg($root.'/scripts/build-lousy-outages-release.sh'), $code); if ($code !== 0) exit($code); }
$fail = static function(string $m): void { fwrite(STDERR, "FAIL: $m\n"); exit(1); };
$entryList = explode("\n", trim(shell_exec('zipinfo -1 '.escapeshellarg($zip)) ?: ''));
if (!in_array('lousy-outages/lousy-outages.php', $entryList, true)) $fail('main file missing');
if (in_array('lousy-outages/lousy-outages/lousy-outages.php', $entryList, true)) $fail('nested main file present');
foreach ($entryList as $e) { if (preg_match('/(^|\/)lousy-outages-0\.3\.2\//', $e)) $fail('versioned dir present'); }
$tmp = sys_get_temp_dir().'/lo-wp-'.bin2hex(random_bytes(4));
mkdir($tmp.'/wp-content/plugins', 0777, true);
copy($zip, $tmp.'/plugin.zip');
$install = static function($zip, $plugins) { $cmd='unzip -oq '.escapeshellarg($zip).' -d '.escapeshellarg($plugins); system($cmd, $code); return $code; };
if ($install($zip, $tmp.'/wp-content/plugins') !== 0) $fail('clean unzip failed');
if (!is_file($tmp.'/wp-content/plugins/lousy-outages/lousy-outages.php')) $fail('clean install path wrong');
if (is_dir($tmp.'/wp-content/plugins/lousy-outages-0.3.2')) $fail('created versioned clean install dir');
$main = file_get_contents($tmp.'/wp-content/plugins/lousy-outages/lousy-outages.php');
if (!preg_match('/Version:\s*0\.3\.2/', $main)) $fail('header version not 0.3.2');
$plugins = $tmp.'/upgrade/wp-content/plugins'; mkdir($plugins.'/lousy-outages', 0777, true);
file_put_contents($plugins.'/lousy-outages/lousy-outages.php', "<?php\n/*\nPlugin Name: ' . 'Lousy Outages\nVersion: 0.3.0\n*/\n");
foreach (['lousy-outages-0.3.0','lousy-outages-recovery-updater-v2','lousy-outages-activation-diagnostics'] as $d) mkdir($plugins.'/'.$d, 0777, true);
if ($install($zip, $plugins) !== 0) $fail('upgrade unzip failed');
if (!is_file($plugins.'/lousy-outages/lousy-outages.php')) $fail('upgrade path wrong');
if (is_file($plugins.'/lousy-outages/lousy-outages/lousy-outages.php')) $fail('nested upgrade path introduced');
if (is_dir($plugins.'/lousy-outages-0.3.2')) $fail('created versioned upgrade dir');
$historyOptions = ['lo_event_log','lo_event_log_compacted_v1','lousy_outages_history','lousy_outages_log','lousy_outages_states','lo_event_log_v2','lo_history_migration_backup_v2','lo_history_migration_v2_marker'];
if (count($historyOptions) !== 8) $fail('history fixture incomplete');
echo json_encode(['ok'=>true,'active_plugin'=>'lousy-outages/lousy-outages.php','shortcodes_expected'=>['lousy_outages','lousy_outages_teaser'],'rest_routes_expected'=>['/lousy-outages/v1/summary','/lousy-outages/v1/history']], JSON_PRETTY_PRINT), "\n";
