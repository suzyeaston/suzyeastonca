<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Feeds {
    private const FEED_NAME = 'lousy_outages_status';

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

        $snapshot  = function_exists('lousy_outages_get_snapshot') ? lousy_outages_get_snapshot(false) : [];
        $providers = is_array($snapshot['providers'] ?? null) ? $snapshot['providers'] : [];
        $fetched   = (string) ($snapshot['fetched_at'] ?? $snapshot['updated_at'] ?? gmdate('c'));

        if (empty($providers)) {
            $store  = new Store();
            $states = $store->get_all();
            if (empty($states)) {
                $states = lousy_outages_collect_statuses(false);
            }

            $fetched = get_option('lousy_outages_last_poll') ?: gmdate('c');
            foreach ($states as $id => $state) {
                $providers[] = lousy_outages_build_provider_payload((string) $id, $state, (string) $fetched);
            }
        }

        if (function_exists('lousy_outages_sort_providers')) {
            $providers = lousy_outages_sort_providers($providers);
        }

        [$items, $last_updated] = self::build_incident_items($providers, (string) $fetched);

        echo '<?xml version="1.0" encoding="' . esc_attr($charset ?: 'UTF-8') . '"?>';
        ?>
<rss version="2.0">
  <channel>
    <title><?php bloginfo_rss('name'); ?> – Lousy Outages Status Feed</title>
    <link><?php echo esc_url(home_url('/lousy-outages/')); ?></link>
    <description><?php echo esc_html('Latest incident updates from the Lousy Outages dashboard.'); ?></description>
    <lastBuildDate><?php echo esc_html(self::format_rss_date($last_updated ?: (string) $fetched)); ?></lastBuildDate>
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

    private static function build_incident_items(array $providers, string $fallback_time): array {
        $items      = [];
        $timestamps = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $provider_id   = (string) ($provider['id'] ?? '');
            $provider_name = (string) ($provider['name'] ?? ($provider['provider'] ?? 'Provider'));
            $provider_link = (string) ($provider['url'] ?? self::provider_link($provider_id));
            $incidents     = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];

            foreach ($incidents as $incident) {
                if (!is_array($incident)) {
                    continue;
                }

                $updated_iso = (string) ($incident['updatedAt'] ?? $incident['updated_at'] ?? '');
                $started_iso = (string) ($incident['startedAt'] ?? $incident['started_at'] ?? '');
                $timestamp   = self::parse_time($updated_iso ?: $started_iso ?: $fallback_time);
                $impact      = strtolower((string) ($incident['impact'] ?? $incident['status'] ?? 'unknown'));
                $status      = Fetcher::status_label($impact ?: 'unknown');
                $title_text  = (string) ($incident['title'] ?? $incident['summary'] ?? 'Incident');
                $summary     = (string) ($incident['summary'] ?? $title_text);

                $items[]      = [
                    'title'       => sprintf('%s – %s (%s)', $provider_name, $title_text, $status),
                    'link'        => !empty($incident['url']) ? (string) $incident['url'] : $provider_link,
                    'guid'        => self::build_guid($provider_id, (string) ($incident['id'] ?? ''), $summary, $updated_iso ?: $started_iso),
                    'pubDate'     => self::format_rss_date($updated_iso ?: ($started_iso ?: $fallback_time)),
                    'description' => sprintf('%s: %s', $status, $summary ?: 'Details unavailable'),
                    'timestamp'   => $timestamp,
                ];

                $timestamps[] = $timestamp;
            }
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
            }
        );

        $items = array_slice($items, 0, 50);

        if (empty($items)) {
            $items[]      = [
                'title'       => 'All systems operational',
                'link'        => home_url('/lousy-outages/'),
                'guid'        => 'lousy-outages-status-' . gmdate('Ymd'),
                'pubDate'     => self::format_rss_date($fallback_time),
                'description' => 'No active incidents reported across monitored providers.',
                'timestamp'   => self::parse_time($fallback_time),
            ];
            $timestamps[] = self::parse_time($fallback_time);
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

    private static function provider_link(string $provider_id): string {
        $anchor = sanitize_title($provider_id ?: 'provider');
        return home_url('/lousy-outages/#provider-' . $anchor);
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
