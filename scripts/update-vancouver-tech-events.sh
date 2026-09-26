#!/bin/bash
# Suzy's Vancouver tech calendar update — researched September 26, 2026.
# Run: bash ~/Downloads/update-vancouver-tech-events.sh
# Preview only: bash ~/Downloads/update-vancouver-tech-events.sh --check
# Requires git, gh, and python3. PHP and Node checks run when available.
# Updates suzyeaston/suzyeastonca main; its existing workflow deploys WordPress.
set -euo pipefail
mode="${1:-apply}"
case "$mode" in
  apply|--check) ;;
  *) echo "Usage: bash $0 [--check]" >&2; exit 2 ;;
esac
if [ "$#" -gt 1 ]; then echo "Too many arguments." >&2; exit 2; fi
for tool in git gh python3; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "Missing $tool. Install it, then run this script again." >&2
    exit 1
  fi
done
if ! gh auth status >/dev/null 2>&1; then
  echo "Please run: gh auth login. Then run this script again." >&2
  exit 1
fi
script_path="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
updates_dir="$HOME/suzy-site-updates"
mkdir -p "$updates_dir"
update_dir="$(mktemp -d "$updates_dir/vancouver-events.XXXXXX")"
trap 'echo "Update stopped. Your separate checkout and patch are in: $update_dir" >&2' ERR
repo_name="suzyeaston/suzyeastonca"
echo "Preparing Vancouver tech event fixes in: $update_dir"
echo "This script pushes to main and triggers your existing WordPress deployment."
gh repo clone "https://github.com/$repo_name.git" "$update_dir/repo" -- --branch main --single-branch
cd "$update_dir/repo"
base_sha="$(git rev-parse HEAD)"
cat > "$update_dir/calendar.patch" <<'SUZY_CALENDAR_PATCH_20260926'
diff --git a/__tests__/homepage-vancouver-tech-events.test.js b/__tests__/homepage-vancouver-tech-events.test.js
index 1aae82a..258fb3c 100644
--- a/__tests__/homepage-vancouver-tech-events.test.js
+++ b/__tests__/homepage-vancouver-tech-events.test.js
@@ -83,7 +83,7 @@ test("vancouver tech events spotlights Futureproof without membership bias", ()
   const css = fs.readFileSync(path.join(ROOT, "style.css"), "utf8");
   assert.match(php, /Futureproof Festival/);
   assert.match(php, /vte-spotlight/);
-  assert.match(php, /Oct 29–30 at the Space Centre\./);
+  assert.match(php, /Oct 28–30 at the Space Centre\./);
   assert.match(css, /\.vte-event--spotlight/);
   assert.match(css, /\.vte-spotlight-badge/);
   assert.doesNotMatch(php, /No ranking/);
@@ -217,7 +217,7 @@ function dedupeVancouverTechEvents(events) {
   return deduped;
 }
 
-test("Futureproof helpers and cache v5 land in the aggregator", () => {
+test("Futureproof helpers and cache v6 land in the aggregator", () => {
   const php = fs.readFileSync(
     path.join(ROOT, "inc", "vancouver-tech-events.php"),
     "utf8"
@@ -226,7 +226,7 @@ test("Futureproof helpers and cache v5 land in the aggregator", () => {
   assert.match(php, /function suzy_vte_event_identity_key\s*\(/);
   assert.match(php, /function suzy_vte_merge_event_records\s*\(/);
   assert.match(php, /futureproof-festival-2026/);
-  assert.match(php, /suzy_vancouver_tech_events_cache_v5/);
+  assert.match(php, /suzy_vancouver_tech_events_cache_v6/);
   assert.doesNotMatch(php, /suzy_vancouver_tech_events_cache_v4/);
   assert.match(php, /'curated'\s*=>\s*true/);
   assert.match(
diff --git a/docs/vancouver-tech-events-coverage.md b/docs/vancouver-tech-events-coverage.md
new file mode 100644
index 0000000..3b4dff9
--- /dev/null
+++ b/docs/vancouver-tech-events-coverage.md
@@ -0,0 +1,82 @@
+# Vancouver tech event coverage update
+
+Researched 2026-09-26. Base inspected: `27488d5a7099569ff3e5e13e579ee182526b46cc`.
+
+The original aggregator had eight sources: three Meetup ICS feeds, Luma's general
+Vancouver discovery page, BC + AI, Vancouver Tech Journal, T-Net, and Meetup search.
+General city discovery does not guarantee coverage of an organizer's calendar.
+
+## Additional automatic calendar sources
+
+| Community | Organizer calendar | Coverage |
+| --- | --- | --- |
+| Vancouver Crypto Events | https://luma.com/yvr | DCTRL/EthVan and other Vancouver crypto/builders events |
+| DC604 | https://luma.com/dc604 | DEFCON Vancouver and security meetups |
+| VanCitySec | https://luma.com/vancitysec | Security community socials |
+| Vancouver.dev | https://luma.com/vancouver.dev | Developer community events |
+| VanTUG | https://luma.com/Vantug | Enterprise IT, cloud, Microsoft and security |
+| Code Together Vancouver | https://luma.com/code-together-vancouver | Collaborative coding |
+| White-Hat Security Community | https://luma.com/WhiteHatSecurityCommunityVancouver | Cybersecurity coffee chats |
+| Vancouver Impact | https://luma.com/vancouverimpact | Climate tech, startups and innovation |
+
+The VanTUG website links its calendar: https://www.vantug.com/events.
+Vancouver Crypto Events currently lists Builders Night at DCTRL:
+https://luma.com/builders-night-vancouver-sept-30.
+These are source registrations, not promises that every source always has future events.
+
+## Direct community links
+
+DCTRL's own hosted-event profile is also linked:
+https://luma.com/user/usr-XQ1OMFlMqL7Ajax. This catches open houses visitors may not
+find on the regional crypto calendar. It is a profile link, not an automatic feed.
+
+Vancouver Hack Space: https://vanhack.ca/events-calendar. Its official site advertises
+Tuesday public nights and links a Google calendar. We link that calendar rather than
+invent dated occurrences: the existing ICS parser does not implement RRULE,
+EXDATE, or moved/cancelled recurrence instances. VHS dates are not auto-imported.
+
+## Code changes
+
+- Preserve case and punctuation in Luma slugs (notably `vancouver.dev`).
+- Combine featured and regular structured event lists; deduplicate their overlap.
+- Normalize Luma/Meetup event URLs for deduplication while retaining each occurrence's timestamp.
+- Fill missing event fields from duplicate records.
+- Respect a published external registration URL where supplied by Luma.
+- Render dates and times using `America/Vancouver`, independently of WordPress settings.
+- Refilter cached events; keep multiday events until their published end.
+- Bump aggregate and affected parser caches so the patch takes effect on the next uncached page request.
+- Correct Futureproof's displayed date range to October 28–30, consistent with its existing timestamp.
+- Show organizer links even if the automatic list is empty. Admin `?vte_debug=1` reports HTTP failures.
+
+## Validation and limitations
+
+Run `php tests/VancouverTechEventsTest.php` and
+`node --test __tests__/homepage-vancouver-tech-events.test.js`.
+The behavior tests execute the actual PHP parser/rendering functions with stubbed
+WordPress HTTP and cache calls, covering combined lists, malformed payloads,
+slug preservation, duplicate URLs, recurrence identities, timezone boundaries,
+HTML escaping, cache expiry filtering and fallback links.
+
+The existing integration uses Luma public-page data and an undocumented public
+endpoint, so upstream changes or bot protection can still interrupt imports.
+Organizer pages were verified through web search, but live HTTP requests to Luma
+and DCTRL from the development environment returned 403. This patch is not a claim
+of successful production ingestion. After deployment, an administrator should
+check `/vancouver-tech-events/?vte_debug=1` and confirm per-source results.
+
+The existing 80-event display cap and general discovery feeds remain. The page is
+broader, not an exhaustive inventory of all Vancouver events. No fictional recurring
+events or old one-off events were seeded into the feed. Page/CDN caches may need
+clearing if the old rendered page persists after a successful deploy.
+
+## Deployment
+
+Run the downloadable `update-vancouver-tech-events.sh` with Bash. It clones the
+latest `main` into a separate directory, checks and applies the embedded patch,
+commits the changes (including a copy of the script), and pushes normally to `main`.
+The repository's existing `Deploy to Production` workflow then handles WordPress.
+No credentials are embedded and no force push is used. Conflicts stop before push.
+`--check` prepares the changes without committing or pushing.
+
+If rollback is needed, revert the printed commit SHA in a clean checkout and push
+that revert. Do not reset or force-push shared history.
diff --git a/inc/vancouver-tech-events.php b/inc/vancouver-tech-events.php
index a4db1ff..f501ddf 100644
--- a/inc/vancouver-tech-events.php
+++ b/inc/vancouver-tech-events.php
@@ -35,7 +35,71 @@ function suzy_get_vancouver_tech_event_sources(): array {
             'source' => 'Meetup Vancouver AWS',
             'format' => 'ics',
         ],
-        // Add more Vancouver Meetup ICS feeds here using format "ics".
+        // Organizer calendars verified 2026-09-26; city discovery is not exhaustive.
+        [
+            'id'     => 'luma_yvr_crypto',
+            'label'  => 'Vancouver Crypto Events / DCTRL & EthVan',
+            'url'    => 'https://luma.com/yvr',
+            'slug'   => 'yvr',
+            'source' => 'Vancouver Crypto Events / DCTRL & EthVan',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_dc604',
+            'label'  => 'DC604 DEFCON Vancouver',
+            'url'    => 'https://luma.com/dc604',
+            'slug'   => 'dc604',
+            'source' => 'DC604 DEFCON Vancouver',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_vancitysec',
+            'label'  => 'VanCitySec',
+            'url'    => 'https://luma.com/vancitysec',
+            'slug'   => 'vancitysec',
+            'source' => 'VanCitySec',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_vancouver_dev',
+            'label'  => 'Vancouver.dev',
+            'url'    => 'https://luma.com/vancouver.dev',
+            'slug'   => 'vancouver.dev',
+            'source' => 'Vancouver.dev',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_vantug',
+            'label'  => 'VanTUG',
+            'url'    => 'https://luma.com/Vantug',
+            'slug'   => 'Vantug',
+            'source' => 'VanTUG',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_code_together',
+            'label'  => 'Code Together Vancouver',
+            'url'    => 'https://luma.com/code-together-vancouver',
+            'slug'   => 'code-together-vancouver',
+            'source' => 'Code Together Vancouver',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_whitehat',
+            'label'  => 'White-Hat Security Community',
+            'url'    => 'https://luma.com/WhiteHatSecurityCommunityVancouver',
+            'slug'   => 'WhiteHatSecurityCommunityVancouver',
+            'source' => 'White-Hat Security Community',
+            'format' => 'luma_calendar',
+        ],
+        [
+            'id'     => 'luma_vancouver_impact',
+            'label'  => 'Vancouver Impact',
+            'url'    => 'https://luma.com/vancouverimpact',
+            'slug'   => 'vancouverimpact',
+            'source' => 'Vancouver Impact',
+            'format' => 'luma_calendar',
+        ],
         [
             'id'     => 'luma_vancouver',
             'label'  => 'Luma Vancouver',
@@ -136,6 +200,9 @@ function suzy_fetch_vancouver_tech_events_raw( bool $debug = false, array &$debu
             $debug_entry['status']  = 'error';
             $debug_entry['message'] = $result->get_error_message();
             $source_events          = [];
+        } elseif ( empty( $source_events ) && (int) ( $meta['http_status'] ?? 0 ) >= 400 ) {
+            $debug_entry['status']  = 'error';
+            $debug_entry['message'] = 'Source request failed; use the organizer calendar link.';
         } elseif ( empty( $source_events ) ) {
             $debug_entry['status']  = 'empty';
             $debug_entry['message'] = $debug_entry['message'] ?: 'No events returned.';
@@ -374,7 +441,7 @@ function suzy_fetch_vancouver_tech_events_from_html_luma( array $source, bool $d
         return [];
     }
 
-    $transient_key = 'suzy_vte_luma_' . md5( $source['url'] );
+    $transient_key = 'suzy_vte_luma_v2_' . md5( $source['url'] );
 
     if ( ! $debug ) {
         $cached = get_transient( $transient_key );
@@ -518,10 +585,10 @@ function suzy_fetch_vancouver_tech_events_from_html_luma( array $source, bool $d
  * @return array<string, mixed>|WP_Error
  */
 function suzy_fetch_vancouver_tech_events_from_luma_calendar( array $source, bool $debug = false ) {
-    $slug = isset( $source['slug'] ) ? sanitize_title( (string) $source['slug'] ) : '';
+    $slug = isset( $source['slug'] ) ? trim( (string) $source['slug'], '/' ) : '';
     if ( '' === $slug && ! empty( $source['url'] ) ) {
         $path = wp_parse_url( (string) $source['url'], PHP_URL_PATH );
-        $slug = sanitize_title( trim( (string) $path, '/' ) );
+        $slug = trim( (string) $path, '/' );
     }
 
     if ( '' === $slug && empty( $source['calendar_api_id'] ) && empty( $source['url'] ) ) {
@@ -529,7 +596,7 @@ function suzy_fetch_vancouver_tech_events_from_luma_calendar( array $source, boo
     }
 
     $cache_key     = $slug !== '' ? $slug : md5( (string) ( $source['url'] ?? $source['calendar_api_id'] ?? '' ) );
-    $transient_key = 'suzy_vte_luma_cal_v3_' . md5( $cache_key );
+    $transient_key = 'suzy_vte_luma_cal_v4_' . md5( $cache_key );
 
     if ( ! $debug ) {
         $cached = get_transient( $transient_key );
@@ -688,7 +755,7 @@ function suzy_vte_is_futureproof_event( array $event ): bool {
  * Stable identity key for cross-source event dedupe.
  *
  * Futureproof records share one canonical key so curated + live copies collapse.
- * All other events keep title|start|location equality.
+ * Known event URLs identify an occurrence; other records use title|start|location.
  *
  * @param array<string, mixed> $event Event.
  */
@@ -705,6 +772,17 @@ function suzy_vte_event_identity_key( array $event ): string {
         return '';
     }
 
+    $url = wp_parse_url( (string) ( $event['url'] ?? '' ) );
+    if ( is_array( $url ) && ! empty( $url['host'] ) && ! empty( $url['path'] ) ) {
+        $host = strtolower( $url['host'] );
+        $host = in_array( $host, [ 'lu.ma', 'www.lu.ma', 'www.luma.com' ], true ) ? 'luma.com' : $host;
+        // Only strip tracking queries for known event hosts; other hosts may use ?id=.
+        if ( in_array( $host, [ 'luma.com', 'www.meetup.com', 'meetup.com' ], true ) ) {
+            $host = 'meetup.com' === $host ? 'www.meetup.com' : $host;
+            return $host . rtrim( $url['path'], '/' ) . '|' . $start;
+        }
+    }
+
     return $title . '|' . $start . '|' . $location;
 }
 
@@ -867,22 +945,25 @@ function suzy_vte_parse_luma_next_data_events( string $html, string $source ): a
         return [];
     }
 
+    // A featured list can coexist with the regular event list.
     $items = [];
-    if ( ! empty( $data['featured_items'] ) && is_array( $data['featured_items'] ) ) {
-        $items = $data['featured_items'];
-    } elseif ( ! empty( $data['events'] ) && is_array( $data['events'] ) ) {
-        $items = $data['events'];
+    foreach ( [ 'featured_items', 'events', 'entries' ] as $collection ) {
+        if ( ! empty( $data[ $collection ] ) && is_array( $data[ $collection ] ) ) {
+            $items = array_merge( $items, $data[ $collection ] );
+        }
     }
 
     $events = [];
     foreach ( $items as $item ) {
         $normalized = suzy_vte_normalize_luma_entry( $item, $source );
         if ( $normalized ) {
-            $events[] = $normalized;
+            $key = suzy_vte_event_identity_key( $normalized );
+            $events[ $key ] = isset( $events[ $key ] )
+                ? suzy_vte_merge_event_records( $events[ $key ], $normalized ) : $normalized;
         }
     }
 
-    return $events;
+    return array_values( $events );
 }
 
 /**
@@ -920,7 +1001,7 @@ function suzy_vte_normalize_luma_entry( $entry, string $source ): ?array {
 
     $end = suzy_vte_parse_iso_datetime( isset( $event['end_at'] ) ? (string) $event['end_at'] : null, $tz );
 
-    $url_slug = trim( (string) ( $event['url'] ?? '' ), '/' );
+    $url_slug = trim( (string) ( $event['external_url'] ?? $event['url'] ?? '' ), '/' );
     $url      = '';
     if ( '' !== $url_slug ) {
         if ( str_starts_with( $url_slug, 'http://' ) || str_starts_with( $url_slug, 'https://' ) ) {
@@ -1616,17 +1697,30 @@ function suzy_get_vancouver_tech_spotlight_events(): array {
  *
  * @return array<int, array<string, mixed>>
  */
+/** Keep ongoing events, and discard finished events on every cache read. */
+function suzy_vte_upcoming_events( array $events, ?int $now = null ): array {
+    $now = $now ?? time();
+    return array_values( array_filter( $events, static function ( $event ) use ( $now ) {
+        if ( empty( $event['start'] ) ) {
+            return false;
+        }
+        $start = (int) $event['start'];
+        $end = ! empty( $event['end'] ) ? (int) $event['end'] : $start;
+        return $end > $start ? $end >= $now : $start >= $now - 6 * HOUR_IN_SECONDS;
+    } ) );
+}
+
 function suzy_get_vancouver_tech_events(): array {
     // Only show debug output when explicitly requested AND user is an admin.
     $debug          = ( isset( $_GET['vte_debug'] ) && '1' === $_GET['vte_debug'] && current_user_can( 'manage_options' ) );
-    $transient_key  = 'suzy_vancouver_tech_events_cache_v5';
+    $transient_key  = 'suzy_vancouver_tech_events_cache_v6';
     $cached         = $debug ? false : get_transient( $transient_key );
     $debug_report   = [];
     $cache_bypassed = $debug;
 
     if ( false !== $cached && is_array( $cached ) ) {
         return [
-            'events'         => $cached,
+            'events'         => suzy_vte_upcoming_events( $cached ),
             'debug'          => $debug ? $debug_report : null,
             'cache_bypassed' => $cache_bypassed,
         ];
@@ -1646,19 +1740,7 @@ function suzy_get_vancouver_tech_events(): array {
     }
     unset( $event );
 
-    $now = time();
-
-    // Filter out events that are clearly in the past (older than 6 hours ago).
-    $events = array_filter(
-        $events,
-        static function ( $event ) use ( $now ) {
-            if ( ! isset( $event['start'] ) ) {
-                return false;
-            }
-
-            return (int) $event['start'] >= ( $now - ( 6 * HOUR_IN_SECONDS ) );
-        }
-    );
+    $events = suzy_vte_upcoming_events( $events );
 
     // Dedupe across sources. Futureproof uses a canonical identity so curated +
     // live copies (different titles) collapse into one spotlight record.
@@ -1687,8 +1769,8 @@ function suzy_get_vancouver_tech_events(): array {
                 } else {
                     $deduped[ $existing_index ] = suzy_vte_merge_event_records( $existing, $event );
                 }
-            } elseif ( ! empty( $event['spotlight'] ) ) {
-                $deduped[ $existing_index ]['spotlight'] = true;
+            } else {
+                $deduped[ $existing_index ] = suzy_vte_merge_event_records( $existing, $event );
             }
             continue;
         }
@@ -1760,7 +1842,7 @@ function suzy_render_vancouver_tech_events_html( ?array $events = null ): string
     <section class="vancouver-tech-events">
         <p class="vancouver-tech-events__kicker pixel-font">yvr calendar</p>
         <h1>Vancouver Tech Events</h1>
-        <p>Meetup ICS, Luma, BC + AI, Vancouver Tech Journal, BC Tech / T-Net. One list.</p>
+        <p>Vancouver tech, maker, security and developer communities. One list. Times are Vancouver local time.</p>
 
         <?php if ( empty( $events ) ) : ?>
             <p>Nothing upcoming right now. Sources still get checked on the next pass.</p>
@@ -1783,7 +1865,7 @@ function suzy_render_vancouver_tech_events_html( ?array $events = null ): string
                     continue;
                 }
                 $start    = isset( $event['start'] ) ? (int) $event['start'] : time();
-                $date_key = wp_date( 'Y-m-d', $start );
+                $date_key = wp_date( 'Y-m-d', $start, new DateTimeZone( 'America/Vancouver' ) );
                 if ( ! isset( $events_by_date[ $date_key ] ) ) {
                     $events_by_date[ $date_key ] = [];
                 }
@@ -1796,7 +1878,7 @@ function suzy_render_vancouver_tech_events_html( ?array $events = null ): string
                 <section class="vte-spotlight" aria-labelledby="vte-spotlight-title">
                     <p class="vte-spotlight__kicker pixel-font">on the board</p>
                     <h2 id="vte-spotlight-title">Futureproof Festival</h2>
-                    <p class="vte-spotlight__intro">Oct 29–30 at the Space Centre.</p>
+                    <p class="vte-spotlight__intro">Oct 28–30 at the Space Centre.</p>
                     <ul class="vte-event-list vte-event-list--spotlight">
                         <?php foreach ( $spotlight_events as $event ) : ?>
                             <?php echo suzy_vte_render_event_list_item( $event, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
@@ -1808,11 +1890,11 @@ function suzy_render_vancouver_tech_events_html( ?array $events = null ): string
             <?php foreach ( $events_by_date as $date_key => $date_events ) : ?>
                 <h2 class="vte-date">
                     <?php
-                    $timezone = wp_timezone();
+                    $timezone = new DateTimeZone( 'America/Vancouver' );
                     $date_dt  = DateTime::createFromFormat( 'Y-m-d H:i:s', $date_key . ' 12:00:00', $timezone );
                     $date_ts  = $date_dt ? $date_dt->getTimestamp() : (int) ( $date_events[0]['start'] ?? time() );
                     $format = 'l, ' . get_option( 'date_format' );
-                    echo esc_html( wp_date( $format, $date_ts ) );
+                    echo esc_html( wp_date( $format, $date_ts, $timezone ) );
                     ?>
                 </h2>
                 <ul class="vte-event-list">
@@ -1822,6 +1904,18 @@ function suzy_render_vancouver_tech_events_html( ?array $events = null ): string
                 </ul>
             <?php endforeach; ?>
         <?php endif; ?>
+        <details class="vte-community-links">
+            <summary>More community calendars &amp; open nights</summary>
+            <p>Check the organizer for the latest schedule, cancellations and RSVP requirements. This list is not exhaustive.</p>
+            <ul>
+                <li><a href="https://luma.com/user/usr-XQ1OMFlMqL7Ajax" target="_blank" rel="noopener noreferrer">DCTRL hosted events &amp; open houses</a> · <a href="https://www.dctrl.wtf/" target="_blank" rel="noopener noreferrer">Visit DCTRL</a></li>
+                <li><a href="https://vanhack.ca/events-calendar" target="_blank" rel="noopener noreferrer">Vancouver Hack Space calendar</a> · Tuesday public nights, workshops, electronics and maker projects. Check their calendar before heading over; these dates are not imported into the list above.</li>
+                <?php foreach ( suzy_get_vancouver_tech_event_sources() as $source ) : ?>
+                    <?php if ( 'luma_calendar' !== ( $source['format'] ?? '' ) ) { continue; } ?>
+                    <li><a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $source['label'] ); ?></a></li>
+                <?php endforeach; ?>
+            </ul>
+        </details>
         <?php if ( ! empty( $debug_data ) ) : ?>
             <div class="vte-debug" style="margin-top:2rem; padding:1rem; border:1px dashed #888; background:#111; color:#ddd;">
                 <strong>DEBUG</strong>
@@ -1884,8 +1978,8 @@ function suzy_vte_render_event_list_item( array $event, bool $spotlight = false
                 <span class="vte-time">
                     <?php
                     $time_label = $spotlight
-                        ? wp_date( 'D, M j · ' . get_option( 'time_format' ), (int) $event['start'] )
-                        : wp_date( get_option( 'time_format' ), (int) $event['start'] );
+                        ? wp_date( 'D, M j · ' . get_option( 'time_format' ), (int) $event['start'], new DateTimeZone( 'America/Vancouver' ) )
+                        : wp_date( get_option( 'time_format' ), (int) $event['start'], new DateTimeZone( 'America/Vancouver' ) );
                     echo esc_html( $time_label );
                     ?>
                 </span>
diff --git a/parts/home-vancouver-tech-events.php b/parts/home-vancouver-tech-events.php
index 308150c..0013d0d 100644
--- a/parts/home-vancouver-tech-events.php
+++ b/parts/home-vancouver-tech-events.php
@@ -60,7 +60,7 @@ $events_url = home_url( '/vancouver-tech-events/' );
 						<p class="vancouver-tech-home__badge pixel-font"><?php echo esc_html( 'spotlight' ); ?></p>
 					<?php endif; ?>
 					<p class="vancouver-tech-home__time">
-						<?php echo esc_html( $event_start > 0 ? wp_date( 'D, M j • g:i A T', $event_start ) : 'Date/time TBD' ); ?>
+						<?php echo esc_html( $event_start > 0 ? wp_date( 'D, M j • g:i A T', $event_start, new DateTimeZone( 'America/Vancouver' ) ) : 'Date/time TBD' ); ?>
 					</p>
 					<a href="<?php echo esc_url( $event_url ); ?>" target="_blank" rel="noopener noreferrer" class="vancouver-tech-home__title">
 						<?php echo esc_html( $event['title'] ?? 'Upcoming event' ); ?>
diff --git a/tests/VancouverTechEventsTest.php b/tests/VancouverTechEventsTest.php
new file mode 100644
index 0000000..4a70cab
--- /dev/null
+++ b/tests/VancouverTechEventsTest.php
@@ -0,0 +1,76 @@
+<?php
+// Run: php tests/VancouverTechEventsTest.php (no WordPress or network required).
+define('ABSPATH', __DIR__);
+define('HOUR_IN_SECONDS', 3600);
+define('MINUTE_IN_SECONDS', 60);
+define('DAY_IN_SECONDS', 86400);
+function add_shortcode(...$args) {}
+function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
+function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
+function esc_attr($s) { return esc_html($s); }
+function esc_url($s) { return esc_html($s); }
+function get_option($key) { return $key === 'date_format' ? 'F j, Y' : 'g:i a'; }
+function wp_date($format, $timestamp, $tz = null) {
+    return (new DateTimeImmutable('@'.$timestamp))->setTimezone($tz ?? new DateTimeZone('UTC'))->format($format);
+}
+function current_user_can($capability) { return false; }
+function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
+function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
+function add_query_arg($args, $url) { return $url.'?'.http_build_query($args); }
+function is_wp_error($value) { return $value instanceof WP_Error; }
+class WP_Error { public function __construct(...$args) {} }
+function wp_remote_get($url, $args) {
+    $GLOBALS['requests'][] = $url;
+    if (str_contains($url, '/url?')) { return ['body' => '{"calendar":{"api_id":"cal-test"}}']; }
+    if (str_contains($url, '/calendar/get-items')) { return ['body' => '{"entries":[],"has_more":false}']; }
+    return ['body' => '<html></html>'];
+}
+function wp_remote_retrieve_body($r) { return $r['body']; }
+function wp_remote_retrieve_response_code($r) { return 200; }
+function wp_remote_retrieve_header($r, $key) { return 'text/html'; }
+require __DIR__.'/../inc/vancouver-tech-events.php';
+$checks = 0;
+function check($ok, $label) {
+    global $checks;
+    if (!$ok) { fwrite(STDERR, 'FAIL: '.$label."\n"); exit(1); }
+    $checks++;
+}
+function fixture($title, $url, $date = '2026-10-01T01:00:00Z') {
+    return ['event' => ['name' => $title, 'url' => $url, 'start_at' => $date, 'timezone' => 'America/Vancouver']];
+}
+$data = ['featured_items' => [fixture('Builders night', 'builders-night')],
+         'events' => [fixture('Builders night', 'builders-night'), fixture('Workshop', 'workshop')],
+         'entries' => [fixture('Security social', 'security-social')]];
+$html = '<script id="__NEXT_DATA__" type="application/json">'.json_encode(['props'=>['pageProps'=>['initialData'=>['data'=>$data]]]]).'</script>';
+$parsed = suzy_vte_parse_luma_next_data_events($html, 'Test');
+check(count($parsed) === 3, 'featured and ordinary entries survive, duplicate removed');
+check(suzy_vte_parse_luma_next_data_events('<html>blocked</html>', 'Test') === [], 'blocked page produces no invented events');
+check(suzy_vte_parse_luma_next_data_events('<script id="__NEXT_DATA__">invalid</script>', 'Test') === [], 'invalid JSON is harmless');
+$one = ['title'=>'One', 'start'=>100, 'url'=>'https://lu.ma/AbC123?utm_source=x', 'location'=>'DCTRL'];
+$two = ['title'=>'One', 'start'=>100, 'url'=>'https://luma.com/AbC123', 'location'=>'328 W Hastings'];
+check(suzy_vte_event_identity_key($one) === suzy_vte_event_identity_key($two), 'cross-domain Luma copies dedupe despite venue spelling');
+$two['start'] = 200;
+check(suzy_vte_event_identity_key($one) !== suzy_vte_event_identity_key($two), 'recurring occurrences stay separate');
+$two['start'] = 100; $two['url'] = 'https://luma.com/abc123';
+check(suzy_vte_event_identity_key($one) !== suzy_vte_event_identity_key($two), 'case-sensitive event path preserved');
+foreach (['vancouver.dev', 'Vantug', 'WhiteHatSecurityCommunityVancouver'] as $slug) {
+    $GLOBALS['requests'] = [];
+    suzy_fetch_vancouver_tech_events_from_luma_calendar(['slug'=>$slug, 'url'=>'https://luma.com/'.$slug], true);
+    check(in_array('https://api.lu.ma/url?url='.urlencode($slug), $GLOBALS['requests'], true), 'slug preserved: '.$slug);
+}
+$now = time();
+$events = [['title'=>'finished','start'=>$now-5000,'end'=>$now-1], ['title'=>'ongoing','start'=>$now-86400,'end'=>$now+86400], ['title'=>'future','start'=>$now+5000]];
+check(count(suzy_vte_upcoming_events($events, $now)) === 2, 'finished removed, ongoing multiday preserved');
+$GLOBALS['cache']['suzy_vancouver_tech_events_cache_v6'] = $events;
+check(count(suzy_get_vancouver_tech_events()['events']) === 2, 'cached records are filtered again at read time');
+$display = suzy_render_vancouver_tech_events_html([['title'=>'Safe <script>', 'start'=>strtotime('2026-10-01T01:00:00Z'), 'url'=>'https://luma.com/test', 'source'=>'DCTRL']]);
+check(str_contains($display, 'Wednesday, September 30, 2026'), 'date bucket is Vancouver even if WordPress timezone is UTC');
+check(str_contains($display, '6:00 pm'), 'summer time is Pacific daylight time');
+check(str_contains($display, 'Safe &lt;script&gt;'), 'title escaped');
+$winter = suzy_vte_render_event_list_item(['title'=>'Winter','start'=>strtotime('2025-12-01T02:00:00Z')]);
+check(str_contains($winter, '6:00 pm'), 'historical winter date follows Vancouver timezone rules');
+check(str_contains($display, 'https://vanhack.ca/events-calendar'), 'maker calendar remains discoverable');
+check(str_contains($display, 'usr-XQ1OMFlMqL7Ajax'), 'DCTRL hosted events linked directly');
+check(str_contains(suzy_render_vancouver_tech_events_html([]), 'Vancouver Hack Space'), 'community links remain when feed empty');
+check(count(suzy_get_vancouver_tech_event_sources()) === 16, 'eight direct calendars added');
+echo "Passed $checks Vancouver event behavior checks.\n";
SUZY_CALENDAR_PATCH_20260926
if git apply --reverse --check "$update_dir/calendar.patch" >/dev/null 2>&1; then
  echo "This update is already present. Nothing to push."
  exit 0
fi
if ! git apply --check "$update_dir/calendar.patch"; then
  echo "The relevant code changed since this patch was prepared. Nothing was pushed." >&2
  echo "Keep this folder and share the error so the patch can be refreshed: $update_dir" >&2
  exit 1
fi
git apply "$update_dir/calendar.patch"
mkdir -p scripts
cp "$script_path" scripts/update-vancouver-tech-events.sh
chmod +x scripts/update-vancouver-tech-events.sh
git diff --check
python3 scripts/theme_deploy_manifest.py validate
if command -v php >/dev/null 2>&1; then
  php -l inc/vancouver-tech-events.php
  php -l parts/home-vancouver-tech-events.php
  php tests/VancouverTechEventsTest.php
else
  echo "PHP is not installed locally; skipping local PHP checks (the packaged patch was tested)."
fi
if command -v node >/dev/null 2>&1; then
  node --test __tests__/homepage-vancouver-tech-events.test.js
else
  echo "Node is not installed locally; skipping the existing Node regression checks."
fi
git add -- inc/vancouver-tech-events.php parts/home-vancouver-tech-events.php   __tests__/homepage-vancouver-tech-events.test.js tests/VancouverTechEventsTest.php   docs/vancouver-tech-events-coverage.md scripts/update-vancouver-tech-events.sh
git diff --cached --stat
if [ "$mode" = "--check" ]; then
  echo "Preview ready. No commit or push was made. Review with:"
  printf 'git -C "%s" diff --cached\n' "$update_dir/repo"
  echo "Run the original script without --check when ready to publish."
  exit 0
fi
commit_name="$(git config user.name || true)"
commit_email="$(git config user.email || true)"
if [ -z "$commit_name" ] || [ -z "$commit_email" ]; then
  account_login="$(gh api user --jq .login)"
  account_id="$(gh api user --jq .id)"
  commit_name="${commit_name:-$account_login}"
  commit_email="${commit_email:-$account_id+$account_login@users.noreply.github.com}"
fi
git -c user.name="$commit_name" -c user.email="$commit_email" commit   -m "Expand Vancouver community event sources and fix calendar parsing"
commit_sha="$(git rev-parse HEAD)"
# An ordinary push rejects concurrent changes; never reset or force-push.
if ! git -c credential.helper= -c 'credential.https://github.com.helper=!gh auth git-credential' push origin HEAD:main; then
  echo "Push did not complete. Your commit remains in: $update_dir/repo" >&2
  echo "No force push was attempted. Share the error before retrying." >&2
  exit 1
fi
printf '\nPushed commit: %s\nPrevious commit: %s\n' "$commit_sha" "$base_sha"
echo "WordPress deployment is handled by your existing GitHub Actions workflow:"
echo "https://github.com/$repo_name/actions/workflows/deploy-production.yml"
echo "Once that workflow succeeds, check https://www.suzyeaston.ca/vancouver-tech-events/"
echo "If the old page persists, clear the WordPress/page cache."
echo "Admin feed diagnostics: https://www.suzyeaston.ca/vancouver-tech-events/?vte_debug=1"
echo "The research notes are in docs/vancouver-tech-events-coverage.md."
echo "Local checkout: $update_dir/repo"
echo "Rollback if needed: git revert $commit_sha, then push the revert."
