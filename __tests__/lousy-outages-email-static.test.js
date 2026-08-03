const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const alerts = fs.readFileSync('lousy-outages/includes/IncidentAlerts.php','utf8');
const subs = fs.readFileSync('lousy-outages/includes/Subscriptions.php','utf8');
const transport = fs.readFileSync('lousy-outages/includes/MailTransport.php','utf8');
const plugin = fs.readFileSync('lousy-outages/lousy-outages.php','utf8');
const templates = fs.readFileSync('lousy-outages/includes/email-templates.php','utf8');
const adminEmail = fs.readFileSync('lousy-outages/includes/Email.php','utf8');

test('outage email templates declare dark-mode color-scheme support', () => {
  assert.match(templates, /function lo_email_dark_mode_head/);
  assert.match(templates, /name="color-scheme" content="light dark"/);
  assert.match(templates, /@media \(prefers-color-scheme: dark\)/);
  assert.match(templates, /class="lo-text-primary"/);
  assert.match(templates, /function send_lo_burst_alert_email/);
});

test('admin ops email declares dark-mode color-scheme support', () => {
  assert.match(adminEmail, /name="color-scheme" content="light dark"/);
  assert.match(adminEmail, /@media \(prefers-color-scheme: dark\)/);
});

test('canonical episode delivery coalesces burst alerts per recipient', () => {
  assert.match(alerts, /send_lo_burst_alert_email/);
  assert.match(alerts, /stale_episode_backlog/);
  assert.match(alerts, /'coalesced'/);
  assert.match(alerts, /\$recipientEpisodes\[\$recipient\]/);
});

test('settings page renders with missing PublicChatterSource diagnostic', () => {
  assert.match(plugin, /includes\/Sources\/PublicChatterSource\.php', false/);
  assert.match(plugin, /\$lo_public_chatter_source && method_exists/);
  assert.match(plugin, /Optional source class PublicChatterSource is unavailable/);
});

test('subscriber selection is canonical, confirmed, realtime, provider-filtered and deduped', () => {
  assert.match(alerts, /SELECT email,status,providers,realtime_alerts FROM \{\$table\}/);
  assert.match(alerts, /STATUS_SUBSCRIBED, 'confirmed'/);
  assert.match(alerts, /no_realtime_opt_in/);
  assert.match(alerts, /provider_preference_mismatch/);
  assert.match(alerts, /OPTION_SUBSCRIBERS/);
  assert.match(alerts, /array_unique\(\$recipients\)/);
});

test('statistics count confirmed rows for alert/digest totals and preserve confirmation on preference update', () => {
  assert.match(subs, /status IN \('subscribed','confirmed'\) AND COALESCE\(realtime_alerts/);
  assert.match(subs, /status IN \('subscribed','confirmed'\) AND COALESCE\(daily_digest/);
  assert.match(subs, /unset\(\$data\['status'\]\)/);
});

test('mail transport defaults to WordPress and does not force localhost SMTP/sendmail', () => {
  assert.match(transport, /get_option\('lousy_outages_mail_transport_mode', 'wordpress'\)/);
  assert.match(transport, /wordpress_.*\$phpmailer->Mailer/s);
  assert.doesNotMatch(transport, /Host = '127\.0\.0\.1'/);
  assert.doesNotMatch(transport, /Port = 25/);
});

test('batch sending records masked per-recipient accepted-for-sending diagnostics without stopping on failure', () => {
  assert.match(alerts, /foreach \(\$subscribers as \$email\)/);
  assert.match(alerts, /accepted_for_sending/);
  assert.match(alerts, /immediate_failures/);
  assert.match(alerts, /mask_email/);
  assert.doesNotMatch(alerts, /return \['ok'=>false,'recipients'=>\$subscribers,'error'=>'mail send failed/);
});

test('admin test alert is capability and nonce protected', () => {
  assert.match(plugin, /admin_post_lousy_outages_send_test_alert/);
  assert.match(plugin, /current_user_can\( 'manage_options' \)/);
  assert.match(plugin, /check_admin_referer\( 'lousy_outages_send_test_alert' \)/);
  assert.match(plugin, /sanitize_email\( wp_unslash/);
});
