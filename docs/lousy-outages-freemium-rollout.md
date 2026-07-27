# Lousy Outages freemium rollout

## Assumptions and decisions

- WordPress users are the account identity. Email magic links create a subscriber-level user only after a valid email request; the response never reveals whether the address exists.
- Stripe-hosted Checkout and Customer Portal own payment details. The plugin uses WordPress HTTP rather than shipping the Stripe PHP SDK.
- Pro and Team prices are recurring Stripe Price IDs configured per environment. Taxes, coupons, trials, refunds, and seat metering stay in Stripe for the first release.
- Existing dashboard, history, RSS, reporting, and basic double-opt-in email paths are unchanged. Paid storage is additive.
- A Team private board is an access-controlled entitlement and storage scaffold in this release, not a claim that the full board UI is finished.

## Implementation plan and file map

1. `Entitlements.php` defines the fail-closed plan matrix and destination limits. `CommerceStore.php` owns additive tables and customer-plan lookup.
2. `StripeBilling.php` creates hosted Checkout/Portal sessions and consumes signed, replay-resistant, idempotent Stripe webhooks without a vendor SDK.
3. `Product.php` provides magic-link accounts, pricing/account shortcodes, watchlist/destination/token endpoints, server-side gates, and analytics hooks.
4. `CommerceAdmin.php` adds Stripe keys, webhook secret, recurring Price IDs, and rollout flags below the existing Lousy Outages admin menu.
5. The pricing/account templates and status/home CTAs keep the current theme vocabulary. The existing dashboard remains the primary free experience.
6. PHP tests cover the complete entitlement boundary and webhook signature/replay boundary. Existing public-dashboard regression tests remain release gates.

## Database and option schema

All tables use the active WordPress prefix. Activation or an admin request runs `dbDelta`; no existing table is altered.

- `lo_customers`: WordPress user to Stripe customer/subscription, effective plan, status, period end, optional team.
- `lo_watchlists`: owner/team, provider JSON, filter JSON, digest JSON, and sharing flag.
- `lo_alert_destinations`: owner/team, Slack or webhook type, encrypted endpoint, label and enabled state.
- `lo_api_tokens`: prefix plus one-way password hash; the plaintext is returned only at creation.
- `lo_stripe_events`: Stripe event IDs for idempotent webhook processing.
- `lousy_outages_commerce_schema_version`: migration checkpoint.
- `lousy_outages_stripe_publishable_key`, `lousy_outages_stripe_secret_key`, `lousy_outages_stripe_webhook_secret`, `lousy_outages_stripe_price_pro`, `lousy_outages_stripe_price_team`: environment billing configuration.
- `lousy_outages_feature_commerce`, `lousy_outages_feature_webhooks`, `lousy_outages_feature_private_boards`: staged rollout switches.

## Tracking contract

Server integrations attach to `lousy_outages_product_event`. Browser analytics listen for `lousy-outages:event` or consume `dataLayer` events prefixed with `lo_`. Event names are `plan_page_view`, `checkout_start`, `checkout_complete`, `upgrade_click`, `subscription_start`, `subscription_confirm`, `watchlist_saved`, `export_csv`, and `export_pdf`. Payloads must not include raw email addresses, webhook URLs, API tokens, or Stripe secrets.

## Migration notes

1. Back up the database and plugin/theme directories.
2. Deploy the plugin and theme together. Activation creates the additive tables and the pricing/account child pages. On an already-active install, visit wp-admin once to run the versioned table migration; create the two pages with the supplied templates if activation is not cycled.
3. Create recurring Pro and Team products/prices in Stripe. Save live/test keys and Price IDs in the matching environment.
4. Register the displayed webhook URL for `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`, and `customer.subscription.deleted`.
5. Existing subscribers are not converted. Every existing user resolves to Free until an active/trialing Stripe subscription event is stored.

## Rollout checklist

- [ ] Take database and file backups; verify the rollback artifact.
- [ ] Deploy to staging with Stripe test keys and commerce disabled.
- [ ] Run PHP, JavaScript, syntax, public-dashboard, RSS, and email subscription regression suites.
- [ ] Confirm tables and nested `/lousy-outages/pricing/` and `/account/` pages exist; flush permalinks once if required.
- [ ] Send signed Stripe test events twice; confirm the second is an idempotent no-op.
- [ ] Complete Pro and Team test checkouts, open the portal, cancel, and verify access falls back to Free.
- [ ] Verify Free dashboard/history/RSS/email while logged out and while signed into a Free account.
- [ ] Verify Pro destination limit, Team token creation, and unknown-plan fail-closed behavior.
- [ ] Connect analytics to the documented action/browser event and verify it contains no secrets or email.
- [ ] Add production keys and webhook endpoint, enable commerce for a small cohort, then monitor Stripe event failures and WordPress logs.

## Rollback checklist

- [ ] Disable `lousy_outages_feature_commerce`; do not delete Stripe subscriptions.
- [ ] Restore the previous plugin/theme artifact and purge page/object caches.
- [ ] Leave additive `lo_*` commerce tables in place so customer state is recoverable; they are ignored by the previous release.
- [ ] Disable the Stripe webhook endpoint only after the old code is live. Export missed Stripe events before disabling it.
- [ ] Confirm the public dashboard, history, RSS, basic email confirmation, cron refresh, and admin dashboard.
- [ ] If rolling forward again, redeploy and replay unprocessed Stripe events from Stripe before re-enabling checkout.
