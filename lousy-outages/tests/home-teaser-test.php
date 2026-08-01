<?php
declare(strict_types=1);

namespace {
    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }
    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp');
    }

    function home_url(string $path = ''): string
    {
        return 'https://example.test' . $path;
    }

    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }

    function lousy_outages_get_current_state(): array
    {
        return $GLOBALS['lo_home_teaser_state'] ?? [];
    }
}

namespace SuzyEaston\LousyOutages\Board {
    function timestamp_from($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        $ts = strtotime((string) $value);
        return $ts ? $ts : 0;
    }

    function relative_time(int $timestamp): string
    {
        $delta = time() - $timestamp;
        if ($delta < 3600) {
            return max(1, (int) floor($delta / 60)) . 'm ago';
        }
        return max(1, (int) floor($delta / 3600)) . 'h ago';
    }

    function tidy(string $text, int $limit = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, $limit - 1), " \t.,;:-") . '…';
    }

    function first_timestamp(array $row, array $fields): int
    {
        foreach ($fields as $field) {
            if (!empty($row[$field])) {
                $ts = timestamp_from($row[$field]);
                if ($ts > 0) {
                    return $ts;
                }
            }
        }
        return 0;
    }

    function state_key(array $provider): string
    {
        $lane = strtolower((string) ($provider['lane'] ?? ''));
        if ($lane === 'long_running') {
            return 'advisory';
        }
        if ($lane === 'signals') {
            return 'degraded';
        }
        $code = strtolower((string) ($provider['stateCode'] ?? $provider['state'] ?? 'operational'));
        if (in_array($code, ['outage', 'major', 'critical'], true)) {
            return 'outage';
        }
        if (in_array($code, ['degraded', 'partial', 'maintenance'], true)) {
            return 'degraded';
        }
        if ($code === 'advisory') {
            return 'advisory';
        }
        if ($code === 'unknown') {
            return 'unknown';
        }
        return 'operational';
    }

    function severity_key(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['major', 'critical', 'outage'], true)) {
            return 'outage';
        }
        if (in_array($value, ['degraded', 'minor', 'partial'], true)) {
            return 'degraded';
        }
        return $value ?: 'degraded';
    }

    function incident_view(array $incident): array
    {
        $title = tidy((string) ($incident['title'] ?? $incident['display_title'] ?? ''), 130);
        $summary = tidy((string) ($incident['summary'] ?? ''), 320);
        return [
            'id' => (string) ($incident['id'] ?? ''),
            'provider_id' => sanitize_key((string) ($incident['provider_id'] ?? '')),
            'provider' => (string) ($incident['provider'] ?? $incident['provider_name'] ?? ''),
            'title' => $title ?: 'Incident reported',
            'summary' => $summary,
            'severity' => severity_key((string) ($incident['status'] ?? '')),
            'lifecycle' => (string) ($incident['status'] ?? ''),
            'scope' => (string) ($incident['region_name'] ?? ''),
            'updated_ts' => timestamp_from($incident['updated_at'] ?? ''),
            'started_ts' => timestamp_from($incident['started_at'] ?? ''),
            'checked_ts' => timestamp_from($incident['checked_at'] ?? ''),
            'url' => (string) ($incident['url'] ?? ''),
            'long_running' => !empty($incident['is_long_running']),
        ];
    }

    function provider_rows(array $state): array
    {
        $rows = [];
        foreach ((array) ($state['providers'] ?? []) as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $id = sanitize_key((string) ($provider['id'] ?? $provider['provider_id'] ?? ''));
            if ('' === $id) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => (string) ($provider['name'] ?? $id),
                'state' => state_key($provider),
                'summary' => tidy((string) ($provider['summary'] ?? ''), 220),
                'category' => (string) ($provider['category'] ?? 'cloud'),
                'source' => (string) ($provider['source_type'] ?? 'statuspage'),
                'checked_ts' => first_timestamp($provider, ['checked_at']),
                'observed_ts' => first_timestamp($provider, ['updated_at']),
                'url' => (string) ($provider['url'] ?? ''),
                'error' => '',
            ];
        }
        return $rows;
    }

    function verdict(array $state): array
    {
        $meta = is_array($state['meta'] ?? null) ? $state['meta'] : [];
        $active = (int) ($meta['active_outage_count'] ?? count((array) ($state['outages'] ?? [])));
        $signals = (int) ($meta['signal_count'] ?? 0);
        if ($active > 0) {
            return ['tone' => 'down', 'line' => 'PROVIDERS ARE DOWN', 'sub' => 'Active incidents.'];
        }
        if ($signals > 0) {
            return ['tone' => 'warn', 'line' => 'DEGRADED', 'sub' => 'Yellow lights without incidents.'];
        }
        return ['tone' => 'ok', 'line' => 'ALL CLEAR', 'sub' => 'Nothing on fire.'];
    }
}

namespace SuzyEaston\LousyOutages {
    class Providers
    {
        public static function enabled(): array
        {
            return [];
        }
    }
}

namespace {
require __DIR__ . '/../includes/HomeTeaser.php';

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$GLOBALS['lo_home_teaser_state'] = [
    'fetched_at' => gmdate('c', time() - 600),
    'providers' => [
        ['id' => 'cloudflare', 'name' => 'Cloudflare', 'lane' => 'signals', 'stateCode' => 'degraded', 'summary' => '46 components not operational.', 'checked_at' => gmdate('c'), 'url' => 'https://cloudflarestatus.com'],
        ['id' => 'aws', 'name' => 'AWS', 'lane' => 'long_running', 'stateCode' => 'advisory', 'checked_at' => gmdate('c'), 'url' => 'https://health.aws.amazon.com'],
        ['id' => 'github', 'name' => 'GitHub', 'lane' => 'operational', 'stateCode' => 'operational', 'checked_at' => gmdate('c')],
    ],
    'outages' => [],
    'signals' => [
        ['id' => 'cloudflare', 'provider_id' => 'cloudflare', 'name' => 'Cloudflare', 'summary' => '46 components not operational.', 'url' => 'https://cloudflarestatus.com'],
    ],
    'long_running' => [
        ['provider_id' => 'aws', 'provider' => 'AWS', 'title' => 'Conflict-related infrastructure damage affecting AWS Bahrain', 'summary' => 'Bahrain region notice.', 'status' => 'outage', 'is_long_running' => true, 'updated_at' => gmdate('c', time() - 90 * DAY_IN_SECONDS)],
    ],
    'meta' => ['active_outage_count' => 0, 'signal_count' => 1, 'long_running_count' => 1, 'operational_count' => 1],
];

$teaser = \SuzyEaston\LousyOutages\HomeTeaser::build();
ok($teaser['counts']['down'] === 0, 'no active down count when outages lane is empty');
ok($teaser['counts']['degraded'] === 1, 'degraded provider counted');
ok($teaser['counts']['advisory'] === 1, 'advisory provider counted');
ok($teaser['tone'] === 'warn', 'verdict tone is warn when degraded');
ok(($teaser['lead']['kind'] ?? '') === 'warn', 'lead story is degraded signal');
ok(str_contains((string) $teaser['lead']['title'], 'Cloudflare'), 'lead mentions cloudflare');
ok(count($teaser['also']) === 1, 'also watching includes aws advisory');

$GLOBALS['lo_home_teaser_state']['signals'] = [];
$GLOBALS['lo_home_teaser_state']['providers'][0]['lane'] = 'operational';
$GLOBALS['lo_home_teaser_state']['providers'][0]['stateCode'] = 'operational';
$GLOBALS['lo_home_teaser_state']['meta']['signal_count'] = 0;
$teaser2 = \SuzyEaston\LousyOutages\HomeTeaser::build();
ok(($teaser2['lead']['kind'] ?? '') === 'advisory', 'lead falls back to advisory when no degraded');
ok(str_contains((string) $teaser2['lead']['title'], 'Bahrain'), 'advisory lead uses incident title');

echo "home teaser tests passed\n";
}
