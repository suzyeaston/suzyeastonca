=== Lousy Outages ===
Contributors: suzyeaston
Tags: status, incidents, monitoring, alerts
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 0.2.0

WordPress-native outage intelligence, community reporting, and early-warning signals for third-party service dependencies.

== Installation ==
1. Copy `plugins/lousy-outages` to `wp-content/plugins/lousy-outages`.
2. Activate **Lousy Outages** in **WordPress Admin → Plugins**.
3. Open **Lousy Outages** in the admin sidebar.

== Emergency disable ==
Add this to `wp-config.php`:
`define( 'LOUSY_OUTAGES_DISABLE', true );`

== Notes ==
- Cloudflare Radar token is optional.
- SMS/Twilio is optional.
- Synthetic checks are lightweight public URL checks only.
- Community signals are unconfirmed until official provider data confirms them.
