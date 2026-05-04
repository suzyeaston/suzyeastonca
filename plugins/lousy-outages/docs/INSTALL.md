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
