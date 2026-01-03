# Lousy Outages

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
4. Click **Send Test Email** to verify delivery; the panel shows the status and the latest subject/recipient recorded.

Use the **Poll Now** button in the debug panel to run an immediate poll. The panel also shows the last poll timestamp, each provider’s most recent status, and any fetch errors captured during the run.

## Shortcode

Place `[lousy_outages]` in any page or post to render the status table. A page titled *Lousy Outages* is created automatically on activation.

## Filters & Actions

- `lousy_outages_providers` – filter the provider array before polling.
- `lousy_outages_status` – filter normalized status before storage.

## Development

Polling runs via WP-Cron (`lousy_outages_poll`). A separate background refresh (`lousy_outages_cron_refresh`) updates the snapshot and "Last fetched" timestamp every 15 minutes; on low-traffic sites, point a system cron at `wp-cron.php` to keep it firing. Results are stored in an option and also exposed at `/wp-json/lousy-outages/v1/status`.

## How to subscribe to RSS

- RSS reader: add `https://suzyeaston.ca/lousy-outages/feed/status/` (or `https://suzyeaston.ca/feed/lousy-outages-status/`) to NetNewsWire, Feedly, or your preferred client to receive incident alerts.
- Slack or email: point an automation tool such as IFTTT or Zapier at the same feed (trigger: “New RSS item”) and forward the payload to a Slack webhook, email address, or other notification channel.
