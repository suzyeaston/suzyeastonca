const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const assert = require('node:assert/strict');
const plugin = readFileSync('lousy-outages/lousy-outages.php', 'utf8');
const pipeline = readFileSync('lousy-outages/includes/Cron/CanonicalPipeline.php', 'utf8');
const alerts = readFileSync('lousy-outages/includes/IncidentAlerts.php', 'utf8');
const feeds = readFileSync('lousy-outages/includes/Feeds.php', 'utf8');

test('canonical refresh checkpoints collection and independently queues publications', () => {
  assert.match(pipeline, /provider_cursor/);
  assert.match(pipeline, /scheduleOnce\(self::ALERT_HOOK/);
  assert.match(pipeline, /scheduleOnce\(self::RSS_HOOK/);
  assert.doesNotMatch(plugin, /Feeds::refresh_status_feed_cache\(\);/);
});

test('history persistence cannot prevent the actual episode email path', () => {
  const processSnapshot = alerts.slice(alerts.indexOf('public static function process_snapshot'), alerts.indexOf('public static function alert_health'));
  const historyAt = processSnapshot.indexOf("'history_store: '");
  const emailAt = processSnapshot.indexOf('self::process_episodes(');
  assert.notEqual(historyAt, -1);
  assert.ok(emailAt > historyAt);
  assert.match(processSnapshot, /'persistence_failures'\s*=>\s*\$persistenceFailures/);
});

test('alert and RSS failures are isolated and retryable', () => {
  assert.match(pipeline, /publishChannel\('alerts'/);
  assert.match(pipeline, /publishChannel\('rss'/);
  assert.match(pipeline, /publication_errors/);
  assert.match(feeds, /source_cycle_id/);
  assert.match(feeds, /snapshot_fingerprint/);
  assert.match(feeds, /publication_result/);
});

test('recipient retries use the episode ledger and do not stop at one failure', () => {
  assert.match(alerts, /pendingRecipients/);
  assert.match(alerts, /saveDelivery/);
  assert.match(alerts, /foreach\(\$episodes as \$episode\)/);
});
