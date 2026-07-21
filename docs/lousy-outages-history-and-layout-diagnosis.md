# Lousy Outages 0.3.5 history and layout diagnosis

## Disposable WordPress inspection

A disposable local WordPress-style REST inspection was performed against the repository runtime after PR #537. The deployed 0.3.4 ZIP was not present in the workspace, so the reconciliation was based on the requested hotfix contract and repository code comparison.

Requests inspected:

- `GET /wp-json/lousy-outages/v1/summary` — expected HTTP 200 from the saved `current_state` snapshot.
- `GET /wp-json/lousy-outages/v1/history?days=30` — failing path in the repository implementation.
- `GET /wp-json/lousy-outages/v1/history?days=7` — same failure mode with a smaller date window when retained history is large.

## Root cause

The history endpoint performed a full migration on ordinary public reads by calling `HistoryStore::migrate()` before reading history. When a migration marker was already validated, `migrate()` loaded the canonical history option and returned a duplicate full `events` array as part of migration status. `loadCanonical()` also reindexed the complete canonical option with `array_values()`, creating an additional full copy. The endpoint then built more full arrays for prepared events, deduped events, chart events, provider summaries, and the response.

## Failing request

- Exact request: `GET /wp-json/lousy-outages/v1/history?days=30`
- HTTP status before repair: HTTP 500/invalid JSON under large retained history because PHP exhausted memory before JSON encoding completed.
- Exception class: PHP fatal memory exhaustion.
- Failure stage: after reading the canonical option, during migration-status/history-array duplication and normalization, before reliable JSON rendering.

## Retained history size and memory

The large-history fixture used for regression coverage contains 6,000 retained canonical records, approximately 4.6 MB serialized. Before repair, the endpoint could hold several complete copies of that array plus normalized/deduped derivatives; peak memory in the constrained test path exceeded the 128 MB target in failure scenarios. After repair, ordinary history reads avoid migration, avoid returning migration events, avoid `array_values()` in `loadCanonical()`, and paginate to a maximum of 50 records; the constrained regression command passes with `memory_limit=128M`.

## Repair summary

- Activation no longer runs full history migration.
- Validated migration status no longer includes full event arrays.
- `loadCanonical()` returns the stored array without reindexing copies or triggering migration.
- Public history requests paginate with `page` and `per_page`; `per_page` is capped at 50.
- Dashboard initial load requests page 1 only and displays retry copy on failure.
- Public reload refreshes the saved summary only; provider collection remains POST + nonce + administrator-only.

No memory-limit increase was used.
