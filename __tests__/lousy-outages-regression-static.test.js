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
