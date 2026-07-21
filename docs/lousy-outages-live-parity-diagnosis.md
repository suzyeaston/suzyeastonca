# Lousy Outages live parity diagnosis

## Disposable environment

Repository inspection started from `2106215e700a9378436121929431777aa455dc32` on branch `fix/lousy-outages-live-parity-and-dashboard`. The local disposable test harness stubs WordPress functions for contract tests; no production deployment, tag, release, or ZIP artifact was created.

## Reproduction findings

Production evidence reported a 0.3.4 split-brain state: the homepage rendered **5 Current Issues** while the full dashboard rendered zero incidents/signals/unverified providers, displayed **Auto-refresh degraded**, failed to display history, and still exposed an obsolete public-chatter panel. That divergence is reproducible from repository behavior before this repair because public surfaces did not all consume one read-only canonical current-state accessor.

## Recorded diagnostics checklist

- Plugin version: production evidence `0.3.4`; repository baseline `0.3.5`; target `0.3.6`.
- Plugin filesystem path: expected `wp-content/plugins/lousy-outages/lousy-outages.php`.
- Snapshot schema version: baseline `4`; target `5`.
- Scheduled cron hooks: canonical `lousy_outages_refresh_official_providers`; legacy hooks are cleared.
- Dashboard assets before repair: could resolve from `/wp-content/themes/.../lousy-outages/assets/` because shortcode asset discovery checked theme paths first.
- Dashboard assets after repair: always `/wp-content/plugins/lousy-outages/assets/` via `LOUSY_OUTAGES_URL . 'assets/'`.
- Homepage rendered issue count: production evidence `5`.
- REST summary counts: must equal `current_state.meta` and the lengths of `outages`, `signals`, and `unverified`.
- Dashboard rendered lane counts: must equal the same `current_state` arrays.
- Saved snapshot counts: authoritative after validation.
- Store/provider-state counts: no longer used by public reads to invent a fallback current state.
- Browser console errors / failed REST requests: production evidence indicates history REST failure and degraded hydration badge.
- History response status: required HTTP 200 for `GET /wp-json/lousy-outages/v1/history?days=30&page=1&per_page=20`.
- LiteSpeed/page-cache evidence: summary/history routes now send no-store/no-cache headers plus a scoped LiteSpeed no-cache header.

## Endpoint contract to inspect

### `GET /wp-json/lousy-outages/v1/summary`

Expected after repair:

- HTTP status: `200`.
- Content type: JSON from WordPress REST.
- Response size: bounded to canonical providers/current lanes; no public-chatter diagnostics.
- `fetched_at`: copied from saved canonical snapshot.
- `source`: copied from saved canonical snapshot or explicit delayed source.
- `meta`: counts exactly match lane array lengths.
- `current_state`: includes `outages`, `signals`, `unverified`, `operational`, `providers`, `meta`, `fetched_at`, `source`, `errors`, `plugin_version`, `snapshot_schema_version`.
- Errors: explicit if the saved snapshot is missing/invalid.

### `GET /wp-json/lousy-outages/v1/history?days=30&page=1&per_page=20`

Expected after repair:

- HTTP status: `200`.
- Content type: JSON from WordPress REST.
- Response size: at most 20 returned records on page 1.
- `fetched_at`: endpoint `generated_at` and `meta.fetchedAt`.
- Source: dedicated retained history table when available.
- Counts: `meta.returned_count <= per_page`; `meta.total_matching` from bounded table count.
- Errors: endpoint should not fatal from loading a giant option first.

## Root cause summary

Before the repair, the homepage, dashboard SSR, REST summary, and JavaScript hydration could combine different inputs: saved snapshot, transient snapshot, Store/provider state fallback, history-derived incidents, and stale theme-copied assets. The dashboard could therefore hydrate with JavaScript that did not match the plugin PHP, while public REST reads could rebuild or fallback rather than only reading the canonical saved snapshot.

## Safe production replacement sequence

1. Back up the database and current `wp-content/plugins/lousy-outages` directory.
2. Replace only the standalone plugin directory with the 0.3.6 build; do not deploy a theme copy.
3. In WP Admin, confirm Lousy Outages Diagnostics reports plugin path under `wp-content/plugins/lousy-outages/`, version `0.3.6`, schema `5`, and plugin asset URLs.
4. Run the canonical provider refresh once from an authenticated/admin path or WP-Cron.
5. Purge only LiteSpeed entries for the Lousy Outages page and REST route URLs: `/lousy-outages/`, `/wp-json/lousy-outages/v1/summary`, and `/wp-json/lousy-outages/v1/history*`.
6. Do not purge unrelated site caches automatically.
7. Run production smoke tests for summary, history, homepage/dashboard parity, console errors, and plugin asset URL paths.
