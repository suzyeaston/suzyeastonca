# Lousy Outages 0.3.1 release and production cleanup

The canonical repository source is `lousy-outages/`. The only valid WordPress installation path is `wp-content/plugins/lousy-outages/lousy-outages.php`.

## Release build

The tag workflow builds `dist/lousy-outages.zip`, validates that it contains exactly one top-level `lousy-outages/` directory, publishes the checksum and manifest, and attaches all three files to a GitHub Release. Do not commit the ZIP to a pull request.

```sh
git tag lousy-outages-v0.3.1
git push origin lousy-outages-v0.3.1
```

## Safe production sequence

1. Merge the PR.
2. Create and push tag `lousy-outages-v0.3.1`.
3. Wait for the GitHub Release workflow to complete.
4. Download `lousy-outages.zip` from the GitHub Release.
5. Verify its SHA-256 with the release asset `lousy-outages.zip.sha256`.
6. Back up the WordPress database.
7. Upload the ZIP through WordPress Plugins → Add New → Upload Plugin.
8. Choose **Replace current with uploaded**.
9. Activate **Lousy Outages 0.3.1**.
10. Verify the plugin path is exactly `wp-content/plugins/lousy-outages/lousy-outages.php` and verify the REST endpoints `/wp-json/lousy-outages/v1/summary` and `/wp-json/lousy-outages/v1/history`.
11. Open Tools → Lousy Outages Cleanup.
12. Run dry-run and review the report.
13. Remove only these malformed and temporary Lousy Outages directories when the dry-run safety checks pass: `lousy-outages-0.3.0`, `lousy-outages-recovery-updater-v2`, and `lousy-outages-activation-diagnostics`.
14. Rerun production QA for the homepage teaser, full dashboard, summary endpoint, history endpoint, and plugin activation state.
15. Leave unrelated plugins such as Wordfence, Query Monitor, Akismet, and LiteSpeed Cache unchanged.

The cleanup screen never deletes `wp-content/plugins/lousy-outages`, database options, outage history, subscribers, reports, or unrelated plugins.
