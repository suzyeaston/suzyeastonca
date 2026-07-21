# Lousy Outages runtime audit for 0.3.2

This audit was written before the 0.3.2 runtime repair. The current plugin contains several overlapping paths that can fetch, normalize, cache, schedule, and alert on provider data.

## Existing network and persistence paths

| Path | Fetches provider status/incidents? | Writes provider state? | Writes canonical snapshot? | Persists history? | Sends alerts? | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `lousy_outages_poll` -> `lousy_outages_run_poll()` | Yes, through `lousy_outages_collect_statuses(true)` and `Fetcher::fetch()` for all enabled providers. | Yes, `Store::update()` for each provider. | Yes, `lousy_outages_refresh_snapshot()`. | Indirectly through detector/notification side effects, not HistoryStore v2 canonical lifecycle. | Yes, via `Detector`, `SMS`, and `Email`. | Duplicate full provider-fetch pipeline. |
| `lousy_outages_cron_refresh` -> `lousy_outages_refresh_data()` | Yes, through `lousy_outages_collect_statuses()`. | Yes, `Store::update()` for each provider. | Yes, `lousy_outages_refresh_snapshot()`. | Not reliably for current official incidents before this repair. | No direct alert sending. | Best existing basis for canonical official provider refresh because it already has a lock and last-known-good merge. |
| `lousy_outages_refresh` -> `IncidentAlerts::run()` | Yes before 0.3.2: `collect_incidents()` included registered sources and legacy feeds in addition to snapshot. | No provider-state write. | No. | Yes, `IncidentStore::persistIncidents()` then HistoryStore migration. | Yes. | Alert path independently refetched sources; must become snapshot/history consumer only. |
| `lo_check_statuses` -> `IncidentAlerts::run()` | Same as `lousy_outages_refresh`. | No provider-state write. | No. | Yes. | Yes. | Duplicate alert schedule and duplicate provider-fetch risk. |
| `lo_refresh_snapshot` -> `lo_run_snapshot_refresh()` -> `lo_snapshot_refresh(true)` | Yes via legacy snapshot runtime when available. | Legacy snapshot store only. | Writes `lo_snapshot_payload_v1`, not the canonical `lousy_outages_snapshot`. | No. | No. | Duplicate snapshot refresh schedule. |
| `lo_send_daily_digest` -> `IncidentAlerts::send_daily_digest()` | No provider fetch required; consumes persisted incidents. | No. | No. | Prunes stale stored incident records. | Sends digest email. | May remain scheduled separately. |
| Manual admin poll (`admin_post_lousy_outages_poll_now`) | Queues/executes `lousy_outages_poll`. | Yes via poll hook. | Yes. | Legacy side effects. | Potentially yes. | Must be replaced by authenticated POST refresh using canonical lock. |
| REST refresh (`/lousy-outages/v1/refresh`) | Yes via `lousy_outages_refresh_data(true)`. | Yes. | Yes. | No before repair. | No. | Previously allowed anonymous GET/POST. Must be authenticated nonce-protected POST only. |
| Homepage teaser / shortcode render | Should consume snapshot, but `lousy_outages_get_snapshot(true)` fallback could fetch on page render when no snapshot existed. | Possible through forced snapshot refresh. | Possible through forced snapshot refresh. | No. | No. | Page views must not initiate provider collection. |
| Dashboard / summary REST / history REST | Summary reads snapshot but could force refresh; history consumes HistoryStore v2. | Possible via forced summary fallback. | Possible via forced summary fallback. | History migration is read-time and idempotent. | No. | Summary must expose the normalized current-state lanes. |

## Hooks traced

- `lousy_outages_poll`: independently performs provider network requests and writes provider state/snapshot.
- `lousy_outages_cron_refresh`: independently performs provider network requests and writes provider state/snapshot.
- `lousy_outages_refresh`: before repair, independently fetched incidents through alert collectors; after repair, must consume saved snapshot only.
- `lo_check_statuses`: before repair, same duplicate alert collector as above; after repair, obsolete for scheduling.
- `lo_refresh_snapshot`: independently refreshes a legacy snapshot and can perform provider collection through the old snapshot runtime.
- `lo_send_daily_digest`: consumes persisted incident/digest data and does not need provider network requests.

## Proposed canonical provider-refresh pipeline

Use exactly one scheduled provider-fetch hook: `lousy_outages_refresh_official_providers`.

The hook calls `lousy_outages_refresh_official_providers()`, an alias around the canonical refresh service. That service fetches all enabled official providers, merges bounded last-known-good data, normalizes the current-state schema (`outages`, `signals`, `unverified`, `operational`, `meta`), writes `lousy_outages_snapshot`, updates provider state, appends current official incident lifecycle records to HistoryStore v2 through `IncidentStore`, and returns a structured result. Alerting, homepage, dashboard, RSS, summary REST, and history REST must consume the saved snapshot/history rather than refetching providers.

Upgrade to 0.3.2 must unschedule `lousy_outages_poll`, `lousy_outages_cron_refresh`, `lousy_outages_refresh`, `lo_check_statuses`, and `lo_refresh_snapshot`; preserve `lo_send_daily_digest` and subscriber cleanup; and schedule `lousy_outages_refresh_official_providers` exactly once.
