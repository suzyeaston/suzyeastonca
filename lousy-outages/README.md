# Lousy Outages

## 0.5.3

- Replaces the monolithic provider refresh with a bounded, option-checkpointed cycle and recoverable owner lease.
- Publishes alerts and RSS through independent, idempotent cron phases with cycle and fingerprint diagnostics.
- Unifies the canonical 15-minute cadence, repairs only missing/overdue schedules, and adds safe expired-cycle recovery.

## 0.5.2

- Loads the shared source pack before concrete intelligence sources and includes admin diagnostics in release archives.

## 0.5.1

- Coordinates RSS and realtime email around a bounded, persistent logical incident-episode ledger. Mutable provider prose and polling timestamps no longer republish an ongoing incident; confirmed recovery closes it and permits a later recurrence.
- Repairs missing or stale canonical refresh scheduling without doing provider I/O during page rendering, records refresh timing/lock/result diagnostics, and clearly identifies installations that require an external cron runner.
- Tracks successful and failed email recipients per episode so partial batches retry only immediate failures, and removes the provider-wide 90-minute throttle that suppressed distinct incidents.

## 0.5.0

- Adds Free, Pro, and Team entitlements with server-side gates for watchlists, alert destinations, shared/private-board scaffolding, and API tokens.
- Adds Stripe-hosted recurring Checkout, Customer Portal sessions, signed idempotent webhooks, and low-friction magic-link accounts without bundling a payment SDK.
- Adds pricing/account pages, upgrade prompts, product analytics hooks, additive commerce storage, admin rollout settings, and migration/runbook documentation while leaving the public dashboard, history, RSS, and basic email path intact.

## 0.4.9

- Publishes the RSS cache inside every canonical refresh, including refreshes started directly by the dashboard, REST API, or admin tools.
- Keeps realtime email processing running when optional incident-history persistence fails, and records the storage failure in alert diagnostics.

## 0.4.8

- Isolates monitored-service diagnostics and footer prose from broad theme
  background and pixel-font rules.
- Makes attention cards fill their grid tracks consistently while retaining the
  248px ultra-narrow layout and browser diagnostics introduced in 0.4.7.

## 0.4.7

- Adds an ultra-narrow layout layer that keeps the dashboard readable at the
  required 248px viewport instead of dividing content into slivers.
- Adds real-browser viewport diagnostics, screenshots, and a standalone
  representative fixture for environments where WordPress is unavailable.

## 0.4.6

Mobile dashboard layout repair and file-versioned early asset loading.

The public CSS and JavaScript are enqueued in `wp_head` on the canonical page
with a version composed from the plugin version and asset file modification
time. After deploying the release ZIP, purge the LiteSpeed/full-page cache once
so cached HTML is regenerated with the new `?ver=0.4.6-<mtime>` asset URL;
browsers then update normally without manual query-string changes.

## 0.4.5

- Settings page now treats public chatter sources as optional and shows an admin diagnostic when `PublicChatterSource` is unavailable.
- Realtime alert recipients are selected from the subscriptions table first, with legacy `lo_subscribers` compatibility and duplicate removal.
- Email alert batches attempt each recipient independently and store admin-only diagnostics for attempted, accepted-for-sending, immediate failures, transport, masked recipient results, and last error.
- Mail transport no longer forces local sendmail or unauthenticated localhost SMTP by default; WordPress, the host, or SMTP plugins remain in control unless `LOUSY_OUTAGES_MAIL_TRANSPORT`, `lousy_outages_mail_transport_mode`, or the matching filter opts into `local_mail` or `local_sendmail`.


Monitor third‑party service status and get SMS and email alerts when things break.

## Providers

| ID | Name | Endpoint |
|----|------|----------|
| github | GitHub | https://www.githubstatus.com/api/v2/summary.json |
| cloudflare | Cloudflare | https://www.cloudflarestatus.com/api/v2/summary.json |
| openai | OpenAI | https://status.openai.com/api/v2/summary.json |
| atlassian | Atlassian | https://status.atlassian.com/api/v2/summary.json |
| digitalocean | DigitalOcean | https://status.digitalocean.com/api/v2/summary.json |
| netlify | Netlify | https://www.netlifystatus.com/api/v2/summary.json |
| vercel | Vercel | https://www.vercel-status.com/api/v2/summary.json |
| zoom | Zoom | https://status.zoom.us/api/v2/summary.json |
| zscaler | Zscaler | https://trust.zscaler.com/rss-feed |
| slack | Slack | https://slack-status.com/feed/rss |
| teamviewer | TeamViewer | https://status.teamviewer.com/api/v2/summary.json |
| linear | Linear | https://status.linear.app/api/v2/summary.json |
| sentry | Sentry | https://status.sentry.io/api/v2/summary.json |
| aws | AWS | https://status.aws.amazon.com/rss/all.rss |
| azure | Azure | https://azurestatuscdn.azureedge.net/en-us/status/feed/ |
| gcp | Google Cloud | https://www.google.com/appsstatus/dashboard/en-CA/feed.atom |

Zscaler is queried from `https://trust.zscaler.com` to dodge intermittent DNS failures. New default providers include TeamViewer, Linear, and Sentry—toggle any of them from **Settings → Lousy Outages**.


To add or remove a provider, edit `includes/Providers.php` or use the checkboxes under **Settings → Lousy Outages** in wp-admin.

## Notifications

1. (Optional) Sign up for Twilio and obtain your **Account SID**, **Auth Token**, and a verified **From** number to enable SMS alerts.
2. In wp-admin go to **Settings → Lousy Outages** and enter the SID, token, from number, your destination phone number, and a notification email address.
3. Choose which providers to monitor and set the polling interval (default 5 minutes).
4. Click **Send Legacy Test Email** to verify the older basic email path.
5. Click **Send Synthetic Incident Alert** to verify the modern realtime IncidentAlerts path end-to-end.
6. Keep **Test configured notification inbox only** enabled by default to avoid notifying public subscribers during QA.

Use the **Poll Now** button in the debug panel to run an immediate poll. The panel also shows the last poll timestamp, each provider’s most recent status, and any fetch errors captured during the run.

## QA and alert health

- Legacy mail test (old path): use **Send Legacy Test Email**.
- Modern realtime test: use **Send Synthetic Incident Alert**.
- WP-CLI synthetic test:
  - `wp lousy:alert-test`
  - `wp lousy:alert-test --recipient=me@example.com`
  - `wp lousy:alert-test --dry-run=true`
  - `wp lousy:alert-test --fixed-id=demo-incident-001`
- WP-CLI health snapshot:
  - `wp lousy:alert-health`
- If delivery fails, inspect:
  - `lousy_outages_last_alert_delivery_result`
  - `lousy_outages_last_alert_failure`
  - `lousy_outages_alert_delivery_failure`
  - wp-admin Debug panel
  - `WP_DEBUG` / `error_log`
  - `lo-mail.log` (if enabled in your environment)

## Shortcode

Place `[lousy_outages]` in any page or post to render the status table. A page titled *Lousy Outages* is created automatically on activation.


## Deliverability checklist

- DMARC passes when either DKIM passes with alignment **or** SPF passes with an envelope-from (Return-Path / MAIL FROM) aligned to the visible From domain.
- Lousy Outages mail now sets PHPMailer `Sender` and sendmail `-f` to align envelope-from with the `suzyeaston.ca` From domain.
- In Gmail, open an alert email, choose **Show original**, and verify `dmarc=pass` in Authentication-Results.
- DNS still matters: SPF, DKIM, and DMARC records must be correctly configured for full deliverability.

## Filters & Actions

- `lousy_outages_providers` – filter the provider array before polling.
- `lousy_outages_status` – filter normalized status before storage.

## Development

Official-provider refresh runs via WP-Cron (`lousy_outages_refresh_official_providers`) and updates the saved snapshot and "Last fetched" timestamp at the configured interval. Missing or refresh-stale schedules receive one guarded immediate recovery event. When `DISABLE_WP_CRON` is true, an external runner must invoke `wp-cron.php` (or run `wp cron event run lousy_outages_refresh_official_providers`) at least as often as the configured interval. Results are stored in an option and also exposed at `/wp-json/lousy-outages/v1/status`.

## How to subscribe to RSS

- RSS reader: add `https://suzyeaston.ca/lousy-outages/feed/status/` (or `https://suzyeaston.ca/feed/lousy-outages-status/`) to NetNewsWire, Feedly, or your preferred client to receive incident alerts.
- Slack or email: point an automation tool such as IFTTT or Zapier at the same feed (trigger: “New RSS item”) and forward the payload to a Slack webhook, email address, or other notification channel.

## Intel Conduit SSH Diagnostics (no WP-CLI)

Use these shell snippets on hosts where WP-CLI is unavailable:

- Enable debug logging with MU plugin:
  - `cat > wp-content/mu-plugins/lo-debug.php <<'PHP'`
  - `<?php add_filter('lo_hn_chatter_enabled', '__return_true'); define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);`
  - `PHP`
- Grep Intel Conduit logs:
  - `grep -E "lousy_outages|Intel Conduit|statuspage|provider_feed|hn_chatter" wp-content/debug.log | tail -n 200`
- Inspect latest external signals rows:
  - `mysql -e "SELECT observed_at,source,provider_id,source_type,evidence_quality,official_confirmed,title FROM wp_lo_external_signals ORDER BY id DESC LIMIT 30;"`
- Inspect REST output:
  - `curl -s https://YOUR_HOST/wp-json/lousy-outages/v1/status | jq '.'`
- Check schema columns:
  - `mysql -e "SHOW COLUMNS FROM wp_lo_external_signals;"`
- Check adapter options and diagnostics payloads:
  - `mysql -e "SELECT option_name,LENGTH(option_value) FROM wp_options WHERE option_name LIKE 'lousy_outages_%external%' OR option_name LIKE 'lousy_outages_%signal%';"`


## Deployment preflight (required)

`php -l` only validates syntax. It does **not** verify runtime interface compatibility or class instantiation during WordPress bootstrap.

Before replacing the production plugin, run:

- `php -l wp-content/plugins/lousy-outages/lousy-outages.php`
- `php wp-content/plugins/lousy-outages/lousy-outages.php`

The smoke script bootstraps WordPress (`require wp-load.php`), calls `\SuzyEaston\LousyOutages\SignalCollector::sources()`, and validates for each source:

- instance of `SignalSourceInterface`
- non-empty `id()` string
- non-empty `label()` string
- boolean return from `is_configured()`

If this check fails, do not deploy.


## Canonical deployment path

- **Deploy from the canonical repository source `lousy-outages/` only.**
- Build releases with `./scripts/build-lousy-outages-release.sh`; do not use mirrored source trees or manual ZIP layouts.
- Swap plugin folder with rollback copy ready; do not deploy if smoke scripts fail.
