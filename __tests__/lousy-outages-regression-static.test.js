const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const read = (file) => fs.readFileSync(file, 'utf8');

test('homepage teaser uses canonical structured counts and deep links', () => {
  const fn = read('functions.php');
  const tpl = read('parts/lousy-outages-teaser.php');
  assert.match(fn, /lousy_outages_get_current_state\(\)/);
  assert.match(fn, /outage_event_count/);
  assert.match(fn, /official_notice_count/);
  assert.match(fn, /affected_provider_count/);
  assert.doesNotMatch(tpl, /preg_replace\(\s*['"]\/\\D\+/);
  assert.match(tpl, /#active-incidents/);
  assert.match(tpl, /#monitored-services/);
  assert.match(tpl, /lead_url/);
});

test('canonical refresh owns alert processing without legacy alert cron', () => {
  const plugin = read('lousy-outages/lousy-outages.php');
  const alerts = read('lousy-outages/includes/IncidentAlerts.php');
  assert.match(plugin, /IncidentAlerts::process_snapshot\(\s*\$snapshot/);
  assert.match(alerts, /public static function collect_from_snapshot\(\?array \$snapshot = null\)/);
  assert.match(alerts, /OPTION_LAST_ALERT_PROCESSING_DIAGNOSTICS/);
  assert.match(alerts, /next_canonical_refresh/);
  assert.match(alerts, /recipient_count/);
  assert.doesNotMatch(plugin, /wp_schedule_event\([^;]+lo_check_statuses/s);
});

test('full page labels distinguish events, providers, and notices with stable targets', () => {
  const php = read('lousy-outages/public/shortcode.php');
  const js = read('lousy-outages/assets/lousy-outages.js');
  assert.match(php, /Outage events/);
  assert.match(php, /Affected providers/);
  assert.match(php, /Official notices/);
  assert.match(php, /id="active-incidents"/);
  assert.match(php, /id="monitored-services"/);
  assert.match(php, /id="provider-/);
  assert.match(php, /id="incident-/);
  assert.match(js, /Outage events \('/);
  assert.doesNotMatch(js, /counts\.active_outage_count = meta\.official_incident_count/);
});

test('0.4.4 repairs literal feed newlines and duplicate stylesheet enqueue', () => {
  const fn = read('functions.php');
  assert.match(fn, /printf\(\s*"\\n<link rel=\\"alternate/);
  assert.doesNotMatch(fn, /echo '\\n<link rel="alternate"/);
  assert.equal((fn.match(/wp_enqueue_style\(\s*['"]main-styles['"]/g) || []).length, 1);
  assert.equal((fn.match(/wp_enqueue_style\(\s*['"]suzy-style['"]/g) || []).length, 0);
  assert.match(fn, /'lousy-outages-teaser',[\s\S]+array\( 'main-styles' \)/);
});

test('homepage teaser keeps canonical DOM on refresh failure and has no max-height clipping', () => {
  const css = read('assets/css/lousy-outages-teaser.css') + '\n' + read('style.css');
  const tpl = read('parts/lousy-outages-teaser.php');
  const js = read('assets/js/lousy-outages-teaser.js');
  assert.match(css, /#lousy-outages-teaser\.lo-home-teaser[^}]*height:auto[^}]*max-height:none[^}]*min-height:0[^}]*overflow:visible/s);
  assert.doesNotMatch(css, /#lousy-outages-teaser\.lo-home-teaser[^}]*max-height:\s*260px/s);
  assert.match(tpl, /data-lo-stat="outages"/);
  assert.match(tpl, /data-lo-stat="providers"/);
  assert.match(tpl, /data-lo-stat="notices"/);
  assert.doesNotMatch(js, /while\s*\([^)]*firstChild[^)]*\)\s*[^;{]*removeChild/s);
  assert.doesNotMatch(js, /lo-home-live-band|lo-home-alert-list|lo-home-alert/);
  assert.match(js, /markDelayed[\s\S]+append\(p\)/);
});

test('full-page JS keeps incident cards and service rows in separate DOM components', () => {
  const php = read('lousy-outages/public/shortcode.php');
  const js = read('lousy-outages/assets/lousy-outages.js');
  assert.match(php, /data-lo-incident-card=/);
  assert.match(php, /data-lo-provider-row=/);
  assert.match(php, /data-label="Provider"/);
  assert.match(php, /data-label="State"/);
  assert.match(php, /data-label="Category \/ source"/);
  assert.match(php, /data-label="Last checked"/);
  assert.match(php, /data-label="Status page"/);
  assert.doesNotMatch(js, /querySelector\('\[data-provider-id=/);
  assert.doesNotMatch(js, /appendChild\(card\)[\s\S]{0,80}\[data-lo-grid\]/);
  assert.match(js, /hasUnresolved[\s\S]+return 'incident'[\s\S]+if \(tileKind\)/);
});

test('admin alert health, canonical manual refresh, cron self-heal, and replay safeguards exist', () => {
  const plugin = read('lousy-outages/lousy-outages.php');
  const alerts = read('lousy-outages/includes/IncidentAlerts.php');
  assert.match(plugin, /Alert Health/);
  assert.match(alerts, /public static function alert_health\(\)/);
  assert.match(plugin, /lousy_outages_ensure_canonical_cron_scheduled/);
  assert.match(plugin, /wp_next_scheduled\( 'lousy_outages_refresh_official_providers' \)/);
  assert.match(plugin, /\$hook = 'lousy_outages_refresh_official_providers'/);
  assert.doesNotMatch(plugin, /\$hook = 'lousy_outages_poll'/);
  assert.match(plugin, /lousy_outages_refresh_official_providers\( true \)/);
  assert.match(alerts, /replay_latest_real_incident_to_notification_inbox/);
  assert.match(plugin, /current_user_can\( 'manage_options' \)[\s\S]+check_admin_referer\( 'lousy_outages_replay_latest_real_incident' \)/);
  assert.match(alerts, /'notification_only'=>true/);
  assert.match(alerts, /mark_sent_called'=>false/);
  assert.doesNotMatch(alerts, /manual_replay[\s\S]{0,500}markSent\(/);
});
