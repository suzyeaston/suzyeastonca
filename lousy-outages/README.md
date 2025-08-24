# Lousy Outages

Monitor third‑party service status and get SMS alerts when things break.

## Providers

| ID | Name | Endpoint |
|----|------|----------|
| github | GitHub | https://www.githubstatus.com/api/v2/summary.json |
| slack | Slack | https://status.slack.com/api/v2.0.0/summary.json |
| cloudflare | Cloudflare | https://www.cloudflarestatus.com/api/v2/summary.json |
| openai | OpenAI | https://status.openai.com/api/v2/summary.json |
| aws | AWS | https://status.aws.amazon.com/rss/all.rss |
| azure | Azure | https://azurestatuscdn.azureedge.net/en-us/status/feed/ |
| gcp | Google Cloud | https://status.cloud.google.com/feed.atom |

To add or remove a provider, edit `includes/Providers.php` or use the checkboxes under **Settings → Lousy Outages** in wp-admin.

## Twilio Setup

1. Sign up for Twilio and obtain your **Account SID**, **Auth Token**, and a verified **From** number.
2. In wp-admin go to **Settings → Lousy Outages** and enter the SID, token, from number and your destination phone number.
3. Choose which providers to monitor and set the polling interval (default 5 minutes).

## Shortcode

Place `[lousy_outages]` in any page or post to render the status table. A page titled *Lousy Outages* is created automatically on activation.

## Filters & Actions

- `lousy_outages_providers` – filter the provider array before polling.
- `lousy_outages_status` – filter normalized status before storage.

## Development

Polling runs via WP-Cron (`lousy_outages_poll`). Results are stored in an option and also exposed at `/wp-json/lousy-outages/v1/status`.
