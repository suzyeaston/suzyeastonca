const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const assert = require('node:assert/strict');

const plugin = readFileSync('lousy-outages/lousy-outages.php', 'utf8');
const alerts = readFileSync('lousy-outages/includes/IncidentAlerts.php', 'utf8');
const feeds = readFileSync('lousy-outages/includes/Feeds.php', 'utf8');

test('canonical refresh publishes notifications and RSS in the same cycle', () => {
  const processAt = plugin.indexOf('IncidentAlerts::process_snapshot( $snapshot');
  const feedAt = plugin.indexOf('Feeds::refresh_status_feed_cache();', processAt);

  assert.notEqual(processAt, -1, 'canonical refresh must process incident alerts');
  assert.ok(feedAt > processAt, 'RSS cache must rebuild after incidents are persisted');
  assert.doesNotMatch(
    feeds,
    /add_action\('lousy_outages_refresh_official_providers',[^\n]+refresh_status_feed_cache/,
    'feed publication must not depend on action dispatch after a direct refresh'
  );
});

test('history persistence cannot prevent realtime email processing', () => {
  const processSnapshot = alerts.slice(
    alerts.indexOf('public static function process_snapshot'),
    alerts.indexOf('public static function alert_health')
  );
  const historyAt = processSnapshot.indexOf("'history_store: '");
  const emailAt = processSnapshot.indexOf('self::process_incidents(');

  assert.notEqual(historyAt, -1, 'history failures must be recorded separately');
  assert.ok(emailAt > historyAt, 'email processing must continue after history persistence');
  assert.match(processSnapshot, /'persistence_failures'\s*=>\s*\$persistenceFailures/);
});
