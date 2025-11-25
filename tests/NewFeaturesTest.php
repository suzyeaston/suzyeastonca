<?php
declare(strict_types=1);

namespace {
    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }
    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }
    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (!function_exists('sanitize_key')) {
        function sanitize_key($key)
        {
            $key = strtolower((string) $key);
            return preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
        }
    }
    if (!function_exists('sanitize_title')) {
        function sanitize_title($title)
        {
            $title = strtolower((string) $title);
            $title = preg_replace('/[^a-z0-9\-]+/', '-', $title) ?? '';
            return trim($title, '-');
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
    if (!function_exists('add_query_arg')) {
        function add_query_arg($args, $url)
        {
            return (string) $url;
        }
    }
    if (!function_exists('home_url')) {
        function home_url($path = '/')
        {
            return 'https://example.com' . (string) $path;
        }
    }
    if (!function_exists('trailingslashit')) {
        function trailingslashit($value)
        {
            $value = (string) $value;
            return '' === $value ? '/' : rtrim($value, "/\\") . '/';
        }
    }
    if (!function_exists('wp_parse_url')) {
        function wp_parse_url($url)
        {
            return parse_url($url);
        }
    }
    if (!function_exists('esc_url')) {
        function esc_url($url)
        {
            return (string) $url;
        }
    }
    if (!function_exists('esc_url_raw')) {
        function esc_url_raw($url)
        {
            return (string) $url;
        }
    }
    if (!function_exists('esc_html')) {
        function esc_html($text)
        {
            return (string) $text;
        }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__($text)
        {
            return (string) $text;
        }
    }
    if (!function_exists('__')) {
        function __($text)
        {
            return (string) $text;
        }
    }
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($value)
        {
            return json_encode($value);
        }
    }
    if (!function_exists('sanitize_email')) {
        function sanitize_email($email)
        {
            return (string) $email;
        }
    }
    if (!function_exists('is_email')) {
        function is_email($email)
        {
            return false !== strpos((string) $email, '@');
        }
    }
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags($text)
        {
            return strip_tags((string) $text);
        }
    }
    if (!function_exists('wp_remote_get')) {
        function wp_remote_get($url, $args = [])
        {
            return [
                'body'     => \LousyOutages\Tests\StatuspageMock::$body,
                'response' => ['code' => 200],
            ];
        }
    }
    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body($response)
        {
            return $response['body'] ?? '';
        }
    }
    if (!function_exists('wp_remote_retrieve_response_code')) {
        function wp_remote_retrieve_response_code($response)
        {
            return (int) ($response['response']['code'] ?? 0);
        }
    }
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing)
        {
            return false;
        }
    }
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response
        {
            public $data;

            public function __construct($data)
            {
                $this->data = $data;
            }
        }
    }
    if (!function_exists('rest_ensure_response')) {
        function rest_ensure_response($data)
        {
            return new \WP_REST_Response($data);
        }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value)
        {
            return $value;
        }
    }
    if (!function_exists('wp_specialchars_decode')) {
        function wp_specialchars_decode($text, $flags = null)
        {
            return (string) $text;
        }
    }
    if (!function_exists('wp_date')) {
        function wp_date($format, $timestamp = null)
        {
            return date($format, $timestamp ?? time());
        }
    }
    if (!function_exists('esc_html_e')) {
        function esc_html_e($text)
        {
            echo $text;
        }
    }
    if (!function_exists('do_action')) {
        function do_action($hook, ...$args): void
        {
        }
    }
    if (!function_exists('lousy_outages_refresh_data')) {
        function lousy_outages_refresh_data()
        {
            return [
                'ok'           => true,
                'refreshed_at' => gmdate('c'),
            ];
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

    class StatuspageMock
    {
        public static string $body = '';
    }

    class Clock
    {
        public static int $time = 1700000000;
    }
}

namespace LousyOutages {
    class Mailer
    {
        /** @var array<int, array<string, mixed>> */
        public static array $sent = [];

        public static function send(string $email, string $subject, string $text, string $html, array $headers = []): bool
        {
            self::$sent[] = compact('email', 'subject', 'text', 'html', 'headers');
            return true;
        }
    }

    class Store
    {
        /** @var array<string, array<string, mixed>> */
        public array $updated = [];

        public function update(string $id, array $data): void
        {
            $this->updated[$id] = $data;
        }
    }
}

namespace {
    function lousy_outages_collect_statuses($bypass = false): array
    {
        return [
            'openai' => [
                'status' => 'degraded',
                'name'   => 'OpenAI',
                'message'=> 'API latency',
                'url'    => 'https://status.openai.com',
            ],
        ];
    }

    function lousy_outages_refresh_snapshot(array $states, string $timestamp, string $source = 'snapshot'): array
    {
        return ['providers' => [], 'trending' => ['trending' => false, 'signals' => []]];
    }

    function lousy_outages_log($event, $payload): void
    {
    }

    class WP_REST_Request
    {
        public function get_param($key)
        {
            return null;
        }
    }
}

namespace SuzyEaston\LousyOutages\Sources {
    function time(): int
    {
        return \LousyOutages\Tests\Clock::$time;
    }
}

namespace SuzyEaston\LousyOutages\Storage {
    function time(): int
    {
        return \LousyOutages\Tests\Clock::$time;
    }
}

namespace SuzyEaston\LousyOutages {
    function time(): int
    {
        return \LousyOutages\Tests\Clock::$time;
    }

    class Mailer
    {
        /** @var array<int, mixed> */
        public static array $sent = [];
    }
}

namespace LousyOutages\Tests {
    require_once __DIR__ . '/../lousy-outages/includes/Model/Incident.php';
    require_once __DIR__ . '/../lousy-outages/includes/Sources/StatuspageSource.php';
    require_once __DIR__ . '/../lousy-outages/includes/Email/Composer.php';
    require_once __DIR__ . '/../lousy-outages/includes/Storage/IncidentStore.php';
    require_once __DIR__ . '/../lousy-outages/includes/Cron/Refresh.php';
    require_once __DIR__ . '/../lousy-outages/includes/Api.php';
    require_once __DIR__ . '/../lousy-outages/includes/Providers.php';
    require_once __DIR__ . '/../lousy-outages/includes/Feeds.php';

    use SuzyEaston\LousyOutages\Cron\Refresh;
    use SuzyEaston\LousyOutages\Email\Composer;
    use SuzyEaston\LousyOutages\Model\Incident;
    use SuzyEaston\LousyOutages\Sources\StatuspageSource;
    use SuzyEaston\LousyOutages\Storage\IncidentStore;
    use SuzyEaston\LousyOutages\Api;
    use SuzyEaston\LousyOutages\Feeds;

    $tests = [];

    $tests['statuspage_source_parses_incidents'] = static function (): void {
        StatuspageMock::$body = json_encode([
            'status' => ['indicator' => 'major'],
            'page'   => ['updated_at' => '2024-03-01T00:00:00Z'],
            'incidents' => [
                [
                    'id' => 'abc',
                    'name' => 'Database connectivity issues',
                    'impact' => 'critical',
                    'status' => 'identified',
                    'started_at' => '2024-03-01T00:00:00Z',
                    'shortlink' => 'https://status.example/incidents/abc',
                ],
                [
                    'id' => 'def',
                    'name' => 'API latency recovered',
                    'impact' => 'minor',
                    'status' => 'resolved',
                    'started_at' => '2024-02-28T22:00:00Z',
                    'resolved_at' => '2024-03-01T00:10:00Z',
                ],
            ],
        ]);

        $source = new StatuspageSource('OpenAI', 'https://status.example/api/v2/summary.json', 'https://status.example');
        $result = $source->fetch();

        if ('partial_outage' !== $result['status']) {
            throw new \RuntimeException('Expected overall status to map to partial_outage.');
        }

        if (count($result['incidents']) !== 2) {
            throw new \RuntimeException('Expected two incidents.');
        }

        $active = $result['incidents'][0];
        if ('major_outage' !== $active->status) {
            throw new \RuntimeException('Expected critical impact to map to major_outage.');
        }

        $resolved = $result['incidents'][1];
        if ('resolved' !== $resolved->status) {
            throw new \RuntimeException('Resolved incident should map to resolved status.');
        }
        if (null === $resolved->resolved_at) {
            throw new \RuntimeException('Resolved incident should expose resolved_at.');
        }
    };

    $tests['composer_subjects'] = static function (): void {
        $incident = new Incident('Zscaler', 'inc-1', 'ZPA Diagnostic Logs', 'degraded', 'https://status.zscaler.com', null, 'major', Clock::$time, null);
        $subject = Composer::subjectForIncident($incident);
        if ('[Outage Alert] Zscaler: ZPA Diagnostic Logs' !== $subject) {
            throw new \RuntimeException('Unexpected subject for degraded incident: ' . $subject);
        }

        $resolved = new Incident('Twilio', 'inc-2', 'Delivery Delays', 'resolved', 'https://status.twilio.com', null, 'minor', Clock::$time, Clock::$time + 60);
        $resolvedSubject = Composer::subjectForIncident($resolved);
        if ('[Resolved] Twilio: Delivery Delays' !== $resolvedSubject) {
            throw new \RuntimeException('Unexpected subject for resolved incident: ' . $resolvedSubject);
        }
    };

    $tests['incident_store_throttling_rules'] = static function (): void {
        Options::reset();
        Clock::$time = 1700000000;

        $store = new IncidentStore();
        $incident = new Incident('OpenAI', 'sig-1', 'API latency', 'degraded', 'https://status.openai.com', null, 'minor', Clock::$time, null);

        if (! $store->shouldSend($incident)) {
            throw new \RuntimeException('First send should be allowed.');
        }
        if ($store->shouldSend($incident)) {
            throw new \RuntimeException('Duplicate send should be throttled.');
        }

        Clock::$time += 100 * MINUTE_IN_SECONDS;
        $major = new Incident('OpenAI', 'sig-2', 'API latency', 'major_outage', 'https://status.openai.com', null, 'critical', Clock::$time + 60, null);
        if (! $store->shouldSend($major)) {
            throw new \RuntimeException('Severity upgrade should bypass cooldown.');
        }

        Clock::$time += 100 * MINUTE_IN_SECONDS;
        update_option('lo_last_guid_openai', '');
        $resolved = new Incident('OpenAI', 'sig-1', 'API latency', 'resolved', 'https://status.openai.com', null, 'minor', Clock::$time + 120, Clock::$time + 120);
        if ($store->shouldSend($resolved)) {
            throw new \RuntimeException('Resolved incidents should remain suppressed.');
        }

        Options::reset();
        $store = new IncidentStore();
        for ($i = 0; $i < 5; $i++) {
            $next = new Incident('OpenAI', 'daily-' . $i, 'API latency #' . $i, 'degraded', 'https://status.openai.com', null, 'minor', Clock::$time + 400 + $i, null);
            if (! $store->shouldSend($next)) {
                throw new \RuntimeException('Daily cap should allow five alerts before suppression.');
            }
            Clock::$time += 100 * MINUTE_IN_SECONDS;
        }

        $capped = new Incident('OpenAI', 'daily-cap', 'Cap reached', 'degraded', 'https://status.openai.com', null, 'minor', Clock::$time + 800, null);
        $store->shouldSend($capped);
    };

    $tests['refresh_schedule_includes_interval'] = static function (): void {
        $schedules = Refresh::registerSchedule([]);
        if (!isset($schedules['five_minutes'])) {
            throw new \RuntimeException('Expected five_minutes schedule to be registered.');
        }
        if ((int) $schedules['five_minutes']['interval'] !== 300) {
            throw new \RuntimeException('Expected five minute interval to equal 300 seconds.');
        }
    };

    $tests['api_refresh_response_shape'] = static function (): void {
        Options::reset();
        \SuzyEaston\LousyOutages\Mailer::$sent = [];
        $response = Api::handle_refresh(new \WP_REST_Request());
        if (!$response instanceof \WP_REST_Response) {
            throw new \RuntimeException('Expected WP_REST_Response instance.');
        }
        $data = $response->data;
        if (!is_array($data) || empty($data['ok']) || empty($data['refreshed_at'])) {
            throw new \RuntimeException('Response missing ok or refreshed_at keys.');
        }
    };

    $tests['feeds_include_user_reports_with_unique_guids'] = static function (): void {
        Options::reset();
        Clock::$time = 1700000000;

        $store = new IncidentStore();
        Options::set('lo_event_log', [
            [
                'provider'       => 'github',
                'provider_label' => 'GitHub',
                'guid'           => 'gh-outage-001',
                'title'          => 'API outage',
                'description'    => 'GitHub API unavailable',
                'status'         => 'Major outage',
                'severity'       => 'outage',
                'important'      => true,
                'first_seen'     => Clock::$time - 600,
                'last_seen'      => Clock::$time - 300,
            ],
        ]);

        $store->addUserReport('openai', 'Timeouts when calling the API', 'reporter@example.com', 'OpenAI');

        $reflector = new \ReflectionClass(Feeds::class);
        $method    = $reflector->getMethod('collect_incident_items');
        $method->setAccessible(true);

        [$items, $lastUpdated] = $method->invoke(null);

        if (count($items) < 2) {
            throw new \RuntimeException('Expected at least two feed items including user reports.');
        }

        $guids = array_map(
            static function (array $item): string {
                return (string) ($item['guid'] ?? '');
            },
            $items
        );

        $uniqueGuids = array_unique(array_filter($guids));
        if (count($uniqueGuids) !== count($guids)) {
            throw new \RuntimeException('GUIDs should be unique across feed items.');
        }

        $timestamps = array_map(
            static function (array $item): int {
                return strtotime((string) ($item['pubDate'] ?? '')) ?: 0;
            },
            $items
        );

        foreach ($timestamps as $time) {
            if ($time <= 0) {
                throw new \RuntimeException('Each feed item should include a valid pubDate.');
            }
        }

        for ($i = 1, $count = count($timestamps); $i < $count; $i++) {
            if ($timestamps[$i - 1] < $timestamps[$i]) {
                throw new \RuntimeException('Feed items should be sorted by recency.');
            }
        }

        if ('' === (string) $lastUpdated) {
            throw new \RuntimeException('Last updated timestamp should be provided.');
        }
    };

    $failed = false;
    foreach ($tests as $name => $callback) {
        try {
            Options::reset();
            Clock::$time = 1700000000;
            StatuspageMock::$body = '';
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
