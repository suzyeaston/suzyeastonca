<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Feed {
    public static function bootstrap(): void {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void {
        add_feed('lousy-outages', [self::class, 'render']);
        add_feed('lousy-outages-incidents', [self::class, 'render']);
    }

    public static function render(): void {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        header('Content-Type: application/rss+xml; charset=UTF-8');

        if ('lousy-outages-incidents' === get_query_var('feed')) {
            self::render_incidents_feed();
            return;
        }

        $store  = new Store();
        $states = $store->get_all();
        if (empty($states)) {
            $states = lousy_outages_collect_statuses(false);
        }

        $fetched_at = get_option('lousy_outages_last_poll');
        if (!$fetched_at) {
            $fetched_at = gmdate('c');
        }

        $providers = [];
        foreach ($states as $id => $state) {
            $providers[] = lousy_outages_build_provider_payload($id, $state, $fetched_at);
        }

        $providers = \lousy_outages_sort_providers($providers);

        $items = self::build_items($providers, $fetched_at);

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<rss version="2.0">
  <channel>
    <title><?php echo esc_html(get_bloginfo('name') . ' – Lousy Outages Alerts'); ?></title>
    <link><?php echo esc_url(home_url('/lousy-outages/')); ?></link>
    <description><?php echo esc_html('Live incident alerts from the Lousy Outages dashboard.'); ?></description>
    <language>en-US</language>
    <lastBuildDate><?php echo esc_html(self::format_rss_date(gmdate('c'))); ?></lastBuildDate>
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

    // LO: custom incidents RSS with Suzy-voice descriptions.
    private static function render_incidents_feed(): void {
        $store  = new Store();
        $states = $store->get_all();
        if (empty($states)) {
            $states = lousy_outages_collect_statuses(false);
        }

        $fetched_at = get_option('lousy_outages_last_poll');
        if (!$fetched_at) {
            $fetched_at = gmdate('c');
        }

        $providers = [];
        foreach ($states as $id => $state) {
            $providers[] = lousy_outages_build_provider_payload($id, $state, $fetched_at);
        }

        $items = self::build_incident_items($providers);

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<rss version="2.0">
  <channel>
    <title><?php echo esc_html('Lousy Outages — Arcade Ops Feed'); ?></title>
    <link><?php echo esc_url(home_url('/lousy-outages/')); ?></link>
    <description><?php echo esc_html('Fast, loud, and slightly neon. Outage pings from Suzanne (Suzy) Easton’s console.'); ?></description>
    <language>en-US</language>
    <lastBuildDate><?php echo esc_html(self::format_rss_date(gmdate('c'))); ?></lastBuildDate>
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

    private static function build_incident_items(array $providers): array {
        $cutoff = time() - (2 * DAY_IN_SECONDS);
        $items  = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $providerId   = (string) ($provider['id'] ?? '');
            $providerName = (string) ($provider['name'] ?? $providerId ?: 'Provider');
            $providerLink = (string) ($provider['url'] ?? self::provider_link($providerId));
            $incidents    = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];

            foreach ($incidents as $incident) {
                if (!is_array($incident)) {
                    continue;
                }

                $updatedIso = (string) ($incident['updatedAt'] ?? $incident['updated_at'] ?? '');
                $startedIso = (string) ($incident['startedAt'] ?? $incident['started_at'] ?? '');
                $updatedTs  = $updatedIso ? strtotime($updatedIso) : 0;
                $startedTs  = $startedIso ? strtotime($startedIso) : 0;
                $timestamp  = $updatedTs ?: $startedTs;
                if ($timestamp && $timestamp < $cutoff) {
                    continue;
                }

                $impactCode = strtolower((string) ($incident['impact'] ?? $incident['status'] ?? 'unknown'));
                $statusLabel = Fetcher::status_label($impactCode ?: 'unknown');
                $emoji = '⚠️';
                if ('maintenance' === $impactCode) {
                    $emoji = '🛠️';
                } elseif ('operational' === $impactCode) {
                    $emoji = 'ℹ️';
                } elseif ('outage' === $impactCode) {
                    $emoji = '🚨';
                }

                $summary = trim((string) ($incident['summary'] ?? 'Details unavailable'));
                $timeText = $timestamp ? gmdate('M d, h:i A', $timestamp) : gmdate('M d, h:i A');
                $detailsLine = ('maintenance' === $impactCode)
                    ? sprintf('%s scheduled maintenance: "%s"', $providerName, $summary)
                    : sprintf('%s looks cranky: "%s"', $providerName, $summary);
                $description = $emoji . ' ' . $statusLabel . ' • ' . $timeText . "\n"
                    . $detailsLine . "\n"
                    . 'Tap for details. We’ll stash the gritty bits in tonight’s digest.';

                $link = (string) ($incident['url'] ?? $providerLink);
                if ('' === $link) {
                    $link = self::provider_link($providerId);
                }

                $guid = isset($incident['id']) ? (string) $incident['id'] : '';
                if ('' !== $guid) {
                    $guid = $providerId . ':' . $guid;
                } else {
                    $guid = sha1($providerId . '|' . $summary . '|' . ($updatedIso ?: $startedIso ?: '')); 
                }

                $items[] = [
                    'title'       => sprintf('%s: %s', $providerName, $statusLabel),
                    'link'        => $link,
                    'guid'        => $guid,
                    'pubDate'     => self::format_rss_date($updatedIso ?: ($startedIso ?: gmdate('c'))),
                    'description' => $description,
                    'timestamp'   => $timestamp ?: time(),
                ];
            }
        }

        usort($items, static function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        if (empty($items)) {
            $items[] = [
                'title'       => 'All clear: no incidents in the last 48 hours',
                'link'        => home_url('/lousy-outages/'),
                'guid'        => 'lousy-outages-incidents-empty-' . gmdate('Ymd'),
                'pubDate'     => self::format_rss_date(gmdate('c')),
                'description' => 'Enjoy the silence. Suzanne will holler if something breaks.',
                'timestamp'   => time(),
            ];
        }

        return array_map(static function ($item) {
            unset($item['timestamp']);
            return $item;
        }, array_slice($items, 0, 25));
    }

    private static function build_items(array $providers, string $fallback_time): array {
        $items      = [];
        $threshold  = (int) get_option('lousy_outages_prealert_threshold', 60);
        $threshold  = max(0, min(100, $threshold));

        foreach ($providers as $provider) {
            $incidents = isset($provider['incidents']) && is_array($provider['incidents']) ? $provider['incidents'] : [];
            if (!empty($incidents)) {
                $incident  = $incidents[0];
                $updated   = $incident['updatedAt'] ?? $incident['startedAt'] ?? $fallback_time;
                $impact    = $incident['impact'] ?? 'incident';
                $items[]   = self::make_item(
                    sprintf('%s: %s (%s)', $provider['name'], $incident['title'], ucfirst((string) $impact)),
                    $provider['id'],
                    $updated,
                    $incident['summary'] ?: $provider['summary'],
                    $provider['id'] . '-' . $incident['id']
                );
                continue;
            }

            $state = strtolower((string) ($provider['stateCode'] ?? 'unknown'));
            if ('operational' !== $state) {
                $items[] = self::make_item(
                    sprintf('%s: %s', $provider['name'], $provider['state']),
                    $provider['id'],
                    $provider['updatedAt'] ?? $fallback_time,
                    $provider['summary'],
                    $provider['id'] . '-' . md5($provider['stateCode'] . $provider['summary'])
                );
                continue;
            }

            $prealert = isset($provider['prealert']) && is_array($provider['prealert']) ? $provider['prealert'] : [];
            $risk     = isset($prealert['risk']) ? (int) $prealert['risk'] : 0;
            if ($risk >= $threshold && $risk > 0) {
                $signals     = isset($prealert['signals']) && is_array($prealert['signals']) ? implode(', ', $prealert['signals']) : 'Early warning';
                $summaryBits = [];
                if (!empty($prealert['summary'])) {
                    $summaryBits[] = (string) $prealert['summary'];
                }
                if (isset($prealert['measures']) && is_array($prealert['measures'])) {
                    $measures = [];
                    if (!empty($prealert['measures']['latency_ms'])) {
                        $measures[] = 'Latency ' . (int) $prealert['measures']['latency_ms'] . ' ms';
                    }
                    if (!empty($prealert['measures']['baseline_ms'])) {
                        $measures[] = 'Baseline ' . (int) $prealert['measures']['baseline_ms'] . ' ms';
                    }
                    if ($measures) {
                        $summaryBits[] = implode(' • ', $measures);
                    }
                }
                $description = $summaryBits ? implode(' — ', $summaryBits) : $provider['summary'];
                $items[]     = self::make_item(
                    sprintf('[EARLY WARNING] %s: %s (Risk %d)', $provider['name'], $signals, $risk),
                    $provider['id'],
                    $prealert['updated_at'] ?? $provider['updatedAt'] ?? $fallback_time,
                    $description,
                    $provider['id'] . '-early-' . md5($signals . $risk . ($prealert['updated_at'] ?? ''))
                );
            }
        }

        usort($items, static function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        $items = array_slice($items, 0, 25);

        if (empty($items)) {
            $items[] = self::make_item(
                'All systems operational',
                'lousy-outages',
                $fallback_time,
                'No active incidents detected across monitored providers.',
                'lousy-outages-' . gmdate('Ymd')
            );
        }

        return array_map(static function ($item) {
            unset($item['timestamp']);
            return $item;
        }, $items);
    }

    private static function make_item(string $title, string $provider_id, string $time, string $description, string $guid): array {
        $timestamp = self::parse_time($time);
        return [
            'title'     => $title,
            'link'      => self::provider_link($provider_id),
            'guid'      => $guid,
            'pubDate'   => self::format_rss_date($time),
            'description' => $description,
            'timestamp' => $timestamp,
        ];
    }

    private static function provider_link(string $provider_id): string {
        $anchor = sanitize_title($provider_id);
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
        return $timestamp;
    }

    private static function format_rss_date(string $time): string {
        $timestamp = strtotime($time);
        if (!$timestamp) {
            $timestamp = time();
        }
        return gmdate('D, d M Y H:i:s +0000', $timestamp);
    }
}
