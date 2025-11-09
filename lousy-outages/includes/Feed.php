<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Feed {
    public static function bootstrap(): void {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void {
        add_feed('lousy-outages', [self::class, 'render']);
    }

    public static function render(): void {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        header('Content-Type: application/rss+xml; charset=UTF-8');

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
