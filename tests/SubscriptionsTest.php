<?php
declare(strict_types=1);

namespace {
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    require_once __DIR__ . '/../lousy-outages/includes/Subscriptions.php';

    class FakeWpdb {
        public string $prefix = 'wp_';
        /** @var array<int, array<string, mixed>> */
        public array $rows = [];

        public function get_charset_collate(): string {
            return 'CHARSET=utf8mb4';
        }

        /**
         * @param string $query
         * @param mixed  ...$args
         * @return array<string, mixed>
         */
        public function prepare($query, ...$args): array { // phpcs:ignore
            $lower = strtolower((string) $query);
            if (strpos($lower, 'where email') !== false) {
                return ['type' => 'email', 'value' => (string) ($args[0] ?? '')];
            }
            if (strpos($lower, 'where token') !== false) {
                return ['type' => 'token', 'value' => (string) ($args[0] ?? '')];
            }

            return ['type' => 'raw', 'query' => $query, 'args' => $args];
        }

        /**
         * @param array<string, mixed> $prepared
         * @param string               $output
         * @return array<string, mixed>|object|null
         */
        public function get_row($prepared, $output = OBJECT) { // phpcs:ignore
            if (!is_array($prepared) || !isset($prepared['type'])) {
                return null;
            }

            if ('email' === $prepared['type']) {
                foreach ($this->rows as $row) {
                    if ($row['email'] === $prepared['value']) {
                        $result = [
                            'id'     => $row['id'],
                            'status' => $row['status'],
                        ];
                        return $output === ARRAY_A ? $result : (object) $result;
                    }
                }
                return null;
            }

            if ('token' === $prepared['type']) {
                foreach ($this->rows as $row) {
                    if ($row['token'] === $prepared['value']) {
                        return $output === ARRAY_A ? $row : (object) $row;
                    }
                }
                return null;
            }

            return null;
        }

        /**
         * @param string               $table
         * @param array<string, mixed> $data
         * @param array<string, mixed> $where
         * @return int
         */
        public function update($table, $data, $where, $format = null, $where_format = null): int { // phpcs:ignore
            foreach ($this->rows as $index => $row) {
                $match = true;
                foreach ($where as $key => $value) {
                    if (!array_key_exists($key, $row) || $row[$key] != $value) { // phpcs:ignore
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    foreach ($data as $key => $value) {
                        $this->rows[$index][$key] = $value;
                    }
                    return 1;
                }
            }

            return 0;
        }

        /**
         * @param string               $table
         * @param array<string, mixed> $data
         * @return int
         */
        public function insert($table, $data, $format = null): int { // phpcs:ignore
            $data['id'] = count($this->rows) + 1;
            $this->rows[] = $data;
            return 1;
        }

        public function query($query) { // phpcs:ignore
            return 0;
        }
    }
}

namespace LousyOutages\Tests {
    use LousyOutages\Subscriptions;

    /** @var array<string, callable> $tests */
    $tests = [];

    $tests['save_pending_resets_created_at_when_reconfirming'] = static function (): void {
        global $wpdb;
        $wpdb = new \FakeWpdb();

        $oldTimestamp = gmdate('Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS);

        $wpdb->rows[] = [
            'id'             => 1,
            'email'          => 'lousy@example.com',
            'status'         => Subscriptions::STATUS_UNSUBSCRIBED,
            'token'          => 'old-token',
            'created_at'     => $oldTimestamp,
            'updated_at'     => $oldTimestamp,
            'ip_hash'        => 'hash',
            'consent_source' => 'form',
        ];

        $newToken = 'new-token-123';
        Subscriptions::save_pending('lousy@example.com', $newToken, 'hash', 'form');

        $row = $wpdb->rows[0];
        if ($row['token'] !== $newToken) {
            throw new \RuntimeException('Expected token to be replaced during reconfirmation.');
        }

        if ($row['created_at'] === $oldTimestamp) {
            throw new \RuntimeException('Expected created_at to be refreshed for new confirmation token.');
        }

        $record = Subscriptions::find_by_token($newToken);
        if (!$record) {
            throw new \RuntimeException('Expected reconfirmed token to be queryable.');
        }

        if ($record['created_at'] !== $row['created_at']) {
            throw new \RuntimeException('Expected find_by_token to return updated timestamps.');
        }
    };

    $failed = false;
    foreach ($tests as $name => $callback) {
        try {
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
