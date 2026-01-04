<?php
declare(strict_types=1);

namespace {
    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }
    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (!defined('YEAR_IN_SECONDS')) {
        define('YEAR_IN_SECONDS', 31536000);
    }
    if (!function_exists('sanitize_key')) {
        function sanitize_key($key)
        {
            $key = strtolower((string) $key);
            return preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
        }
    }
    if (!function_exists('get_option')) {
        function get_option($key, $default = null)
        {
            return \LousyOutages\Tests\Options::get((string) $key, $default);
        }
    }
    if (!function_exists('update_option')) {
        function update_option($key, $value, $autoload = false)
        {
            \LousyOutages\Tests\Options::set((string) $key, $value);
            return true;
        }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value)
        {
            return $value;
        }
    }
}

namespace LousyOutages\Tests {
    class Options
    {
        /** @var array<string, mixed> */
        private static array $options = [];

        public static function get(string $key, $default = null)
        {
            return self::$options[$key] ?? $default;
        }

        public static function set(string $key, $value): void
        {
            self::$options[$key] = $value;
        }

        public static function reset(): void
        {
            self::$options = [];
        }
    }

    class Clock
    {
        public static int $time = 1700000000;
    }
}

namespace SuzyEaston\LousyOutages\Storage {
    function time(): int
    {
        return \LousyOutages\Tests\Clock::$time;
    }
}

namespace LousyOutages\Tests {
    require_once __DIR__ . '/../lousy-outages/includes/Model/Incident.php';
    require_once __DIR__ . '/../lousy-outages/includes/Storage/IncidentStore.php';

    use SuzyEaston\LousyOutages\Model\Incident;
    use SuzyEaston\LousyOutages\Storage\IncidentStore;

    $tests = [];

    $tests['cloudflare_status_only_is_digest_only'] = static function (): void {
        Options::reset();
        Clock::$time = 1700000000;

        $store = new IncidentStore();
        $incident = new Incident(
            'Cloudflare',
            'cloudflare:status:abcdef',
            'Cloudflare status: Degraded',
            'degraded',
            'https://www.cloudflarestatus.com',
            null,
            'degraded',
            Clock::$time,
            null
        );

        if ($store->shouldSend($incident)) {
            throw new \RuntimeException('Status-only Cloudflare incidents should not send realtime alerts.');
        }
    };

    $tests['cloudflare_real_incident_is_alertable'] = static function (): void {
        Options::reset();
        Clock::$time = 1700000000;

        $store = new IncidentStore();
        $incident = new Incident(
            'Cloudflare',
            'cloudflare:incident:123',
            'Network degradation in India',
            'degraded',
            'https://www.cloudflarestatus.com',
            null,
            'degraded',
            Clock::$time,
            null
        );

        if (! $store->shouldSend($incident)) {
            throw new \RuntimeException('Real Cloudflare incidents should remain alertable.');
        }
    };

    $failed = false;
    foreach ($tests as $name => $callback) {
        try {
            Options::reset();
            Clock::$time = 1700000000;
            $callback();
            echo "ok - {$name}\n";
        } catch (\Throwable $throwable) {
            $failed = true;
            echo "not ok - {$name}: " . $throwable->getMessage() . "\n";
        }
    }

    if ($failed) {
        exit(1);
    }

    echo "All tests passed\n";
}
