# Lousy Outages Commercial Cutover Checklist

1. Build ZIP: `bash scripts/build-lousy-outages-plugin.sh`.
2. Upload ZIP in WP Admin → Plugins → Add New → Upload Plugin.
3. Activate **Lousy Outages** under Plugins.
4. Confirm top-level **Lousy Outages** menu appears.
5. Confirm Settings alias is gone (no primary `options-general.php?page=lousy-outages` UI).
6. Confirm public `/lousy-outages` page still works.
7. Confirm REST routes:
   - `/wp-json/lousy-outages/v1/signals`
   - `/wp-json/lousy-outages/v1/report`
8. Confirm no PHP critical errors.
9. Confirm theme fallback loader is skipped when plugin is active.
10. Remove theme fallback in a later release after verification.

## SSH diagnostics (no WP-CLI)

- Check `metadata_json` column:
  - `mysql -e "SHOW COLUMNS FROM wp_lo_external_signals LIKE 'metadata_json';"`
- Rumour Radar log lines:
  - `grep -n "\\[lousy_outages\\]\\[rumour_radar\\]" wp-content/debug.log | tail -n 120`
  - `grep -n "query_attempt\\|query_result\\|signal_created\\|signal_skipped\\|rate_limited\\|collection_error" wp-content/debug.log | tail -n 160`
- Inspect latest public chatter rows:
  - `php -r 'require "wp-load.php"; global $wpdb; print_r($wpdb->get_results("SELECT source,provider_id,severity,metadata_json,observed_at FROM {$wpdb->prefix}lo_external_signals WHERE signal_type=\"public_chatter\" ORDER BY id DESC LIMIT 10", ARRAY_A));'`
- Inspect collector snapshot:
  - `php -r 'require "wp-load.php"; print_r(get_option("lousy_outages_last_external_collection", []));'`
- REST signals:
  - `curl -sS https://YOUR_SITE/wp-json/lousy-outages/v1/signals`

GDELT may rate-limit requests to one every ~5 seconds; plugin enforces a 6-second minimum interval.
