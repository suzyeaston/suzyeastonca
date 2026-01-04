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
            '^feed/' . self::FEED_NAME . '/?$',
            'index.php?feed=' . self::FEED_NAME,
            'top'
        );

        add_rewrite_rule(
            '^lousy-outages/feed/status/?$',
            'index.php?feed=' . self::FEED_NAME,
            'top'
        );

        add_rewrite_rule(
            '^' . self::FEED_NAME . '/feed/?$',
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
    <description><?php echo esc_html('Aggregated incident and performance updates for third-party providers monitored by the Lousy Outages dashboard.'); ?></description>
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
        $itemsByKey     = [];
        $timestamps     = [];
        $fallback_time  = gmdate('c');
        $cutoff         = time() - (self::INCIDENT_WINDOW_DAYS * DAY_IN_SECONDS);
        $store          = new IncidentStore();
        $providers      = Providers::list();
        $events         = $store->getStoredIncidents();

        if (is_array($events)) {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $event = $store->normalizeEvent($event);

                $severity       = strtolower((string) ($event['severity'] ?? ''));
                $isUserReport   = 'user_report' === $severity || 'user_report' === strtolower((string) ($event['source'] ?? ''));
                $severityKnown  = $isUserReport || in_array($severity, ['outage', 'degraded', 'maintenance', 'info'], true);
                $importantField = $event['important'] ?? null;
                $important      = null === $importantField ? null : (bool) $importantField;

                if (false === $important) {
                    continue;
                }

                if (!$severityKnown) {
                    continue;
                }

                $firstSeen     = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
                $lastSeen      = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
                $publishedTime = isset($event['published']) && !is_numeric($event['published'])
                    ? self::parse_time((string) $event['published'])
                    : (int) ($event['published'] ?? 0);
                $timestamp     = $firstSeen ?: ($lastSeen ?: $publishedTime);

                if ($timestamp && $timestamp < $cutoff) {
                    continue;
                }

                $provider_id   = sanitize_key((string) ($event['provider'] ?? ''));
                $provider_data = $providers[$provider_id] ?? [];
                $provider_name = (string) ($event['provider_label'] ?? ($provider_data['name'] ?? ($provider_id ? ucfirst($provider_id) : 'Provider')));
                $status_url    = isset($provider_data['status_url']) ? (string) $provider_data['status_url'] : (string) ($provider_data['url'] ?? '');

                $title_text = trim((string) ($event['title'] ?? $event['description'] ?? 'Incident'));
                $status     = (string) ($event['status'] ?? $event['status_normal'] ?? '');
                $eventTime  = $timestamp ? gmdate('c', (int) $timestamp) : $fallback_time;
                $incidentId = sanitize_key((string) ($event['incident_id'] ?? ($event['id'] ?? '')));

                $incidentKey = self::build_incident_key($provider_id, $incidentId, $title_text, $eventTime);
                $guid        = self::build_guid($provider_id, $incidentId, $title_text, $eventTime);

                $incidentLink = (string) ($event['url'] ?? '');
                if ('' === $incidentLink) {
                    $incidentLink = self::provider_link($provider_id, $status_url);
                }

                $itemTimestamp = $timestamp ?: self::parse_time($fallback_time);

                $itemTitle      = sprintf('[%s] %s – %s', strtoupper($severity ?: 'incident'), $provider_name, $title_text ?: 'Incident');
                $descriptionArgs = [
                    'provider_label' => $provider_name,
                    'severity'       => $severity,
                    'status'         => $status,
                    'title'          => $title_text,
                    'impact_summary' => (string) ($event['impact_summary'] ?? ''),
                    'summary'        => (string) ($event['description'] ?? ''),
                ];

                if ($isUserReport) {
                    $title_text                 = $title_text ?: 'Possible issue reported by a user';
                    $descriptionArgs['severity'] = 'user_report';
                    $itemTitle                  = sprintf('[COMMUNITY REPORT] %s – %s', $provider_name, $title_text);
                }

                $item = [
                    'title'       => $itemTitle,
                    'link'        => $incidentLink,
                    'guid'        => $guid,
                    'pubDate'     => self::format_rss_date($eventTime),
                    'description' => self::build_funny_summary($descriptionArgs),
                    'timestamp'   => $itemTimestamp,
                ];

                if ('' !== $incidentKey) {
                    $existing = $itemsByKey[$incidentKey] ?? null;
                    if (! $existing || ($existing['timestamp'] ?? 0) < $itemTimestamp) {
                        $itemsByKey[$incidentKey] = $item;
                    }
                } else {
                    $itemsByKey[] = $item;
                }
            }
        }

        if (0 === count($itemsByKey)) {
            $now     = time();
            $nowIso  = gmdate('c', $now);
            $itemsByKey[] = [
                'title'       => 'No recent major incidents detected',
                'link'        => home_url('/lousy-outages/'),
                'guid'        => self::build_guid('lousy-outages-status', 'all-clear', 'No recent major incidents detected', $nowIso),
                'pubDate'     => self::format_rss_date($nowIso),
                'description' => 'No major outages or degraded incidents have been detected in the last 30 days. Check the dashboard for current status details.',
                'timestamp'   => $now,
            ];
        }

        $items = array_values($itemsByKey);
        $timestamps = array_values(
            array_filter(
                array_map(
                    static function (array $item): int {
                        return (int) ($item['timestamp'] ?? 0);
                    },
                    $items
                )
            )
        );

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
        return self::build_incident_key($provider_id, $incident_id, $summary, $time);
    }

    private static function build_incident_key(string $provider_id, string $incident_id, string $summary, string $time): string {
        $provider  = sanitize_key($provider_id);
        $incident  = sanitize_key($incident_id);
        $normalizedSummary = '';

        if ('' !== $incident) {
            return $provider . ':' . $incident;
        }

        if (function_exists('mb_strtolower')) {
            $normalizedSummary = mb_strtolower(trim((string) $summary), 'UTF-8');
        } else {
            $normalizedSummary = strtolower(trim((string) $summary));
        }

        $timestamp = self::parse_time($time);
        $dateKey   = $timestamp ? gmdate('Y-m-d', $timestamp) : trim((string) $time);

        return sha1($provider . '|' . $normalizedSummary . '|' . $dateKey);
    }

    private static function provider_link(string $provider_id, string $status_url = ''): string {
        if ('' !== $status_url) {
            return $status_url;
        }
        $anchor = sanitize_title($provider_id ?: 'provider');
        return home_url('/lousy-outages/#provider-' . $anchor);
    }

    /**
     * Build a concise, professional summary for the feed description.
     *
     * @param array<string, mixed> $incident
     */
    private static function build_funny_summary(array $incident): string {
        $provider      = trim((string) ($incident['provider_label'] ?? 'The provider')) ?: 'The provider';
        $severity      = strtolower((string) ($incident['severity'] ?? ''));
        $status        = trim((string) ($incident['status'] ?? ''));
        $impactSummary = trim((string) ($incident['impact_summary'] ?? ''));
        $fallback      = trim((string) ($incident['summary'] ?? ''));
        $details       = '' !== $impactSummary ? $impactSummary : $fallback;

        if ('user_report' === $severity) {
            $summary = sprintf('Community report for %s.', $provider);
            if ('' !== $details) {
                $summary .= ' A user reported a possible issue: ' . self::truncate_text($details, 200) . '.';
            }
            $summary .= ' This report has not yet been independently verified.';

            return trim($summary);
        }

        $statusText = $status ?: '';

        switch ($severity) {
            case 'outage':
                $body = sprintf(
                    'Service outage reported for %s. Status: %s.',
                    $provider,
                    $statusText ?: 'see provider status page for details'
                );
                break;
            case 'degraded':
                $body = sprintf(
                    'Performance issues detected for %s. Status: %s.',
                    $provider,
                    $statusText ?: 'see provider status page for details'
                );
                break;
            case 'maintenance':
            case 'info':
                $body = sprintf(
                    'Scheduled maintenance or informational update for %s. Status: %s.',
                    $provider,
                    $statusText ?: 'see provider status page for details'
                );
                break;
            default:
                $body = sprintf(
                    'Incident update for %s. Status: %s.',
                    $provider,
                    $statusText ?: 'see incident details'
                );
        }

        if ('' !== $details) {
            $body .= ' ' . self::truncate_text($details, 200);
        }

        return trim($body);
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
