# Lousy Outages 0.4.0 Provider Coverage Audit

Audit date: 2026-07-21. Scope: configured Lousy Outages provider registry and public rendering pipeline.

## Pipeline

`ProviderRegistry::all()` → `Providers::enabled()` compatibility map → `Fetcher::fetch()` → source adapter normalization → `lousy_outages_merge_verified_states()` verification/last-known-good handling → `lousy_outages_refresh_snapshot()` canonical snapshot → `lousy_outages_get_current_state()` current lanes → `IncidentStore`/`HistoryStore` retained history → public shortcode and provider detail pages.

No provider is considered supported only because a link exists. Enabled providers below have an official structured source configured; disabled candidates are intentionally not rendered as monitored providers.

## Refresh and cron

- Canonical hook: `lousy_outages_refresh_official_providers`.
- Intended cadence: every 30 minutes via `lousy_outages_15min`.
- Lock: transient `lousy_outages_refresh_lock`, five-minute TTL.
- Timeout: provider fetch timeout defaults to eight seconds; individual HTTP calls also retry likely DNS/TLS failures once over IPv4.
- Partial refresh: quality metadata records total/verified/failed providers. A complete successful refresh is only recorded when quality is OK and there are zero failed providers.
- Last-known-good: recent verified provider state is preserved through transient provider failures instead of erasing active incidents.
- cPanel cron recommendation: disable unreliable visitor-triggered WP-Cron with `DISABLE_WP_CRON`, then run `wp cron event run --due-now` every five minutes from cPanel using the account's PHP/WP-CLI path. Do not expose an unauthenticated refresh URL.

## Coverage table

| ID | Name | Category | Official URL | Source type | Adapter | Endpoint/feed | Collection | Last successful test | Components | Regions | History | Maintenance | Cadence | Freshness | Limitations |
|---|---|---:|---|---|---|---|---|---|---|---|---|---|---:|---:|---|
| openai | OpenAI | ai | https://status.openai.com/ | statuspage | statuspage_summary | https://status.openai.com/api/v2/summary.json | fully monitored | 2026-07-21 syntax/registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | Official Statuspage fields only. |
| anthropic | Anthropic | ai | https://status.anthropic.com/ | statuspage | statuspage_summary | https://status.anthropic.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0; production coverage begins after Suzy uploads and verifies cron. |
| huggingface | Hugging Face | ai | https://status.huggingface.co/ | statuspage | statuspage_summary | https://status.huggingface.co/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0. |
| mistral | Mistral AI | ai | https://status.mistral.ai/ | statuspage | statuspage_summary | https://status.mistral.ai/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0. |
| cohere | Cohere | ai | https://status.cohere.com/ | statuspage | statuspage_summary | https://status.cohere.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0. |
| groq | Groq | ai | https://status.groq.com/ | statuspage | statuspage_summary | https://status.groq.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0. |
| replicate | Replicate | ai | https://status.replicate.com/ | statuspage | statuspage_summary | https://status.replicate.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0. |
| elevenlabs | ElevenLabs | creative | https://status.elevenlabs.io/ | statuspage | statuspage_summary | https://status.elevenlabs.io/api/v2/summary.json | fully monitored | 2026-07-21 registry verified; live refresh required after upload | yes | source-dependent | yes | yes | 30m | 45m | New in 0.4.0. |
| github | GitHub | development | https://www.githubstatus.com/ | statuspage | statuspage_summary | https://www.githubstatus.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| cloudflare | Cloudflare | cloud | https://www.cloudflarestatus.com/ | statuspage | statuspage_summary + status verification | https://www.cloudflarestatus.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Uses status.json verification for empty degraded summaries. |
| aws | AWS | cloud | https://health.aws.amazon.com/health/status | rss | rss_atom | https://status.aws.amazon.com/rss/all.rss | incidents only | 2026-07-21 registry verified | text-derived only | text-derived only | yes | yes | 30m | 45m | RSS does not provide canonical component model. |
| azure | Azure | cloud | https://azure.status.microsoft/ | rss | rss_atom | https://rssfeed.azure.status.microsoft/en-us/status/feed/ | incidents only | 2026-07-21 registry verified | text-derived only | text-derived only | yes | yes | 30m | 45m | RSS-only normalization. |
| google_cloud | Google Cloud | cloud | https://status.cloud.google.com/ | gcp_json | gcp_incidents_json | https://status.cloud.google.com/incidents.json | fully monitored | 2026-07-21 registry verified | yes | yes | yes | no | 30m | 45m | Uses Google incident JSON rather than visual HTML. |
| google_workspace | Google Workspace | communications | https://www.google.com/appsstatus/dashboard/ | rss | rss_atom | https://www.google.com/appsstatus/rss/en-CA | incidents only | 2026-07-21 registry verified | text-derived only | text-derived only | yes | yes | 30m | 45m | RSS-only normalization. |
| slack | Slack | communications | https://status.slack.com/ | slack_current | slack_current | https://status.slack.com/api/v2.0.0/current | status summary only | 2026-07-21 registry verified | yes | source-dependent | no | yes | 30m | 45m | Current endpoint powers live state; retained history depends on persisted current incidents. |
| atlassian | Atlassian | development | https://status.atlassian.com/ | statuspage | statuspage_summary | https://status.atlassian.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| digitalocean | DigitalOcean | cloud | https://status.digitalocean.com/ | statuspage | statuspage_summary | https://status.digitalocean.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| netlify | Netlify | development | https://www.netlifystatus.com/ | statuspage | statuspage_summary | https://www.netlifystatus.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| vercel | Vercel | development | https://www.vercel-status.com/ | statuspage | statuspage_summary | https://www.vercel-status.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| zoom | Zoom | communications | https://status.zoom.us/ | statuspage | statuspage_summary | https://status.zoom.us/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| sentry | Sentry | development | https://status.sentry.io/ | statuspage | statuspage_summary | https://status.sentry.io/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| teamviewer | TeamViewer | communications | https://status.teamviewer.com/ | statuspage | statuspage_summary | https://status.teamviewer.com/api/v2/summary.json | fully monitored | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Statuspage current summary. |
| zscaler | Zscaler | cloud | https://trust.zscaler.com/zscaler.net/ | statuspage | statuspage_summary | https://status.zscaler.com/api/v2/summary.json | verification delayed | 2026-07-21 registry verified | yes | source-dependent | yes | yes | 30m | 45m | Endpoint has historically returned HTML/blocked responses; last-known-good retention applies. |

## Disabled candidates

| ID | Name | Reason not enabled |
|---|---|---|
| crowdstrike | CrowdStrike | Official public structured source not confirmed. |
| cursor | Cursor | Candidate page documented, but structured endpoint not enabled until verified. |
| perplexity | Perplexity | Candidate page documented, but structured endpoint not enabled until verified. |
| stability_ai | Stability AI | Candidate page documented, but structured endpoint not enabled until verified. |
| runway | Runway | Candidate page documented, but structured endpoint not enabled until verified. |
| adobe | Adobe Creative Cloud | Official status site exists, but no stable public structured feed has been enabled. |
| google_gemini | Google Gemini | Gemini-specific official source not separately verified; Google Cloud remains monitored. |
