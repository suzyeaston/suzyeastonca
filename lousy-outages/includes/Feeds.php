<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Storage\IncidentStore;

class Feeds {
    private const FEED_NAME = 'lousy_outages_status';
    private const INCIDENT_WINDOW_DAYS = 30;
    private const INCIDENT_LIMIT = 25;

    public static function bootstrap(): void {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void {
        add_feed(self::FEED_NAME, [self::class, 'render_status_feed']);
        add_feed('lousy-outages-status', [self::class, 'render_status_feed']);

        add_rewrite_rule(
            '^feed/lousy-outages-status/?$',
            'index.php?feed=' . self::FEED_NAME,
            'top'
        );

        add_rewrite_rule(
            '^lousy-outages/feed/status/?$',
            'index.php?feed=' . self::FEED_NAME,
            'top'
        );
    }

    public static function render_status_feed(): void {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        $charset = (string) get_option('blog_charset', 'UTF-8');
        header('Content-Type: application/rss+xml; charset=' . $charset, true);

        [$items, $last_updated] = self::collect_incident_items();

        echo '<?xml version="1.0" encoding="' . esc_attr($charset ?: 'UTF-8') . '"?>';
        ?>
<rss version="2.0">
  <channel>
    <title><?php echo esc_html('Suzy Easton – Lousy Outages Status Feed'); ?></title>
    <link><?php echo esc_url(home_url('/lousy-outages/')); ?></link>
    <description><?php echo esc_html('Major outage shenanigans curated by Suzy Easton. Serious incidents, delightfully unserious commentary.'); ?></description>
    <lastBuildDate><?php echo esc_html(self::format_rss_date($last_updated)); ?></lastBuildDate>
<?php foreach ($items as $item) : ?>
    <item>
      <title><?php echo esc_html($item['title']); ?></title>
      <link><?php echo esc_url($item['link']); ?></link>
      <guid isPermaLink="false"><?php echo esc_html($item['guid']); ?></guid>
      <pubDate><?php echo esc_html($item['pubDate']); ?></pubDate>
      <description><?php echo esc_html($item['description']); ?></description>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
        <?php
        exit;
    }

    private static function collect_incident_items(): array {
        $items          = [];
        $timestamps     = [];
        $fallback_time  = gmdate('c');
        $cutoff         = time() - (self::INCIDENT_WINDOW_DAYS * DAY_IN_SECONDS);
        $store          = new IncidentStore();
        $providers      = Providers::list();
        $events         = $store->getStoredIncidents();
        $seenGuids      = [];

        if (is_array($events)) {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $event = $store->normalizeEvent($event);

                $severity  = strtolower((string) ($event['severity'] ?? ''));
                $important = isset($event['important']) ? (bool) $event['important'] : false;

                if (!$important || !in_array($severity, ['outage', 'degraded'], true)) {
                    continue;
                }

                $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
                $lastSeen  = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
                $timestamp = $firstSeen ?: $lastSeen;

                if ($timestamp && $timestamp < $cutoff) {
                    continue;
                }

                $provider_id   = sanitize_key((string) ($event['provider'] ?? ''));
                $provider_data = $providers[$provider_id] ?? [];
                $provider_name = (string) ($event['provider_label'] ?? ($provider_data['name'] ?? ($provider_id ? ucfirst($provider_id) : 'Provider')));
                $status_url    = isset($provider_data['status_url']) ? (string) $provider_data['status_url'] : (string) ($provider_data['url'] ?? '');

                $title_text = trim((string) ($event['title'] ?? $event['description'] ?? 'Incident'));
                $status     = (string) ($event['status'] ?? $event['status_normal'] ?? '');
                $guid       = (string) ($event['guid'] ?? '');

                if ('' === $guid) {
                    $guid = self::build_guid($provider_id, '', $title_text, $firstSeen ? gmdate('c', (int) $firstSeen) : $fallback_time);
                }

                if ($guid && isset($seenGuids[$guid])) {
                    continue;
                }

                $seenGuids[$guid] = true;

                $incidentLink = (string) ($event['url'] ?? '');
                if ('' === $incidentLink) {
                    $incidentLink = self::provider_link($provider_id, $status_url);
                }

                $itemTimestamp = $timestamp ?: self::parse_time($fallback_time);

                $items[] = [
                    'title'       => sprintf('[%s] %s – %s', strtoupper($severity ?: 'incident'), $provider_name, $title_text ?: 'Incident'),
                    'link'        => $incidentLink,
                    'guid'        => $guid,
                    'pubDate'     => self::format_rss_date($timestamp ? gmdate('c', (int) $timestamp) : $fallback_time),
                    'description' => self::build_funny_summary([
                        'provider_label' => $provider_name,
                        'severity'       => $severity,
                        'status'         => $status,
                        'title'          => $title_text,
                        'impact_summary' => (string) ($event['impact_summary'] ?? ''),
                        'summary'        => (string) ($event['description'] ?? ''),
                    ]),
                    'timestamp'   => $itemTimestamp,
                ];

                $timestamps[] = $itemTimestamp;
            }
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
            }
        );

        if (count($items) > self::INCIDENT_LIMIT) {
            $items = array_slice($items, 0, self::INCIDENT_LIMIT);
        }

        return [
            array_map(
                static function (array $item): array {
                    unset($item['timestamp']);
                    return $item;
                },
                $items
            ),
            $timestamps ? gmdate('c', max($timestamps)) : $fallback_time,
        ];
    }

    private static function build_guid(string $provider_id, string $incident_id, string $summary, string $time): string {
        if ('' !== $incident_id) {
            return $provider_id . ':' . $incident_id;
        }

        return sha1($provider_id . '|' . $summary . '|' . $time);
    }

    private static function provider_link(string $provider_id, string $status_url = ''): string {
        if ('' !== $status_url) {
            return $status_url;
        }
        $anchor = sanitize_title($provider_id ?: 'provider');
        return home_url('/lousy-outages/#provider-' . $anchor);
    }

    /**
     * Build a theatrical but informative summary for the feed description.
     *
     * @param array<string, mixed> $incident
     */
    private static function build_funny_summary(array $incident): string {
        $provider = trim((string) ($incident['provider_label'] ?? 'The provider'));
        $severity = strtolower((string) ($incident['severity'] ?? ''));
        $status   = trim((string) ($incident['status'] ?? ''));
        $title    = trim((string) ($incident['title'] ?? ''));
        $notes    = trim((string) ($incident['impact_summary'] ?? ($incident['summary'] ?? '')));

        $statusText = $status ? 'Status: ' . $status . '. ' : '';

        if ('outage' === $severity) {
            $lead = sprintf(
                '%1$s just unplugged reality. %2$sThis is the part of the keynote where everything goes dark and we pretend it’s “planned.”',
                $provider,
                $statusText
            );
        } else {
            $lead = sprintf(
                '%1$s is in “reality distortion field” mode: technically running, spiritually napping. %2$sExpect dramatic limping worthy of a method actor.',
                $provider,
                $statusText
            );
        }

        if ('' !== $title) {
            $lead .= ' Headline: ' . self::truncate_text($title, 180);
        }

        if ('' !== $notes && $notes !== $title) {
            $lead .= ' Vendor notes: ' . self::truncate_text($notes, 200);
        }

        return $lead;
    }

    private static function truncate_text(string $text, int $limit = 180): string {
        $text = trim($text);
        if ('' === $text || $limit <= 0) {
            return '';
        }

        if (function_exists('mb_strlen')) {
            if (mb_strlen($text, 'UTF-8') > $limit) {
                return rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '…';
            }

            return $text;
        }

        if (strlen($text) > $limit) {
            return rtrim(substr($text, 0, $limit - 1)) . '…';
        }

        return $text;
    }

    private static function parse_time(?string $time): int {
        if (!$time) {
            return time();
        }

        $timestamp = strtotime($time);
        if (!$timestamp) {
            return time();
        }

        return (int) $timestamp;
    }

    private static function format_rss_date(string $time): string {
        $timestamp = self::parse_time($time);
        return gmdate('D, d M Y H:i:s +0000', $timestamp);
    }
}
