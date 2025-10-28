<?php
declare(strict_types=1);

namespace {
    function trailingslashit( $value ) {
        $value = (string) $value;
        return '' === $value ? '/' : rtrim( $value, "/\\" ) . '/';
    }

    function wp_strip_all_tags( $text ) {
        return trim( strip_tags( (string) $text ) );
    }

    function wp_json_encode( $value ) {
        return json_encode( $value );
    }

    function wp_parse_url( $url ) {
        return parse_url( $url );
    }

    function home_url( $path = '/' ) {
        return 'https://example.com' . (string) $path;
    }
}

namespace Lousy {
    class HttpMock {
        /** @var array<int, mixed> */
        public static array $queue = [];

        public static function queue( array $responses ): void {
            self::$queue = $responses;
        }

        public static function pop(string $url): array {
            if ( empty( self::$queue ) ) {
                return [
                    'ok'      => false,
                    'status'  => 0,
                    'error'   => 'request_failed',
                    'message' => 'No mock response queued for ' . $url,
                    'body'    => null,
                ];
            }

            $current = array_shift( self::$queue );
            if ( is_callable( $current ) ) {
                return (array) $current( $url );
            }

            return (array) $current;
        }
    }

    function http_get( string $url, array $args = [] ): array {
        return HttpMock::pop( $url );
    }
}

namespace LousyOutages\Tests {
    use Lousy\HttpMock;
    use LousyOutages\Fetcher;

    require_once __DIR__ . '/../lousy-outages/includes/Adapters.php';
    require_once __DIR__ . '/../lousy-outages/includes/Adapters/Statuspage.php';
    require_once __DIR__ . '/../lousy-outages/includes/Fetcher.php';

    $tests = [];

    $tests['statuspage_status_fallback_after_403'] = static function (): void {
        $status = wp_json_encode([
            'page'   => ['updated_at' => gmdate('c')],
            'status' => ['indicator' => 'none', 'description' => 'All Systems Operational'],
        ]);

        HttpMock::queue([
            [ 'ok' => false, 'status' => 403, 'error' => 'http_error', 'message' => 'HTTP 403 response', 'body' => '' ],
            [ 'ok' => true, 'status' => 200, 'error' => null, 'message' => null, 'body' => $status ],
        ]);

        $fetcher = new Fetcher( 4 );
        $result  = $fetcher->fetch([
            'id'              => 'status-only',
            'name'            => 'Status Only',
            'type'            => 'statuspage',
            'url'             => 'https://status.example.com/api/v2/summary.json',
            'status_endpoint' => 'https://status.example.com/api/v2/status.json',
        ]);

        if ( 'operational' !== $result['status'] ) {
            throw new \RuntimeException( 'Expected status fallback to mark provider operational.' );
        }
        if ( null !== $result['error'] ) {
            throw new \RuntimeException( 'Expected error to be cleared after status fallback.' );
        }
        if ( false === strpos( (string) $result['summary'], 'incidents unavailable' ) ) {
            throw new \RuntimeException( 'Expected summary to mention missing incidents.' );
        }
    };

    $tests['statuspage_rss_fallback_after_404'] = static function (): void {
        $rss = sprintf(
            '<?xml version="1.0"?><rss><channel><item><title>Outage</title><pubDate>%s</pubDate></item></channel></rss>',
            gmdate( 'D, d M Y H:i:s \G\M\T' )
        );

        HttpMock::queue([
            [ 'ok' => false, 'status' => 404, 'error' => 'http_error', 'message' => 'HTTP 404 response', 'body' => '' ],
            [ 'ok' => false, 'status' => 404, 'error' => 'http_error', 'message' => 'HTTP 404 response', 'body' => '' ],
            [ 'ok' => false, 'status' => 404, 'error' => 'http_error', 'message' => 'HTTP 404 response', 'body' => '' ],
            [ 'ok' => true, 'status' => 200, 'error' => null, 'message' => null, 'body' => $rss ],
        ]);

        $fetcher  = new Fetcher( 4 );
        $result   = $fetcher->fetch([
            'id'         => 'example',
            'name'       => 'Example',
            'type'       => 'statuspage',
            'url'        => 'https://status.example.com/api/v2/summary.json',
            'status_url' => 'https://status.example.com/',
        ]);

        if ( ! in_array( $result['status'], [ 'operational', 'degraded', 'outage' ], true ) ) {
            throw new \RuntimeException( 'Expected RSS fallback to produce a known status.' );
        }
        if ( null !== $result['error'] ) {
            throw new \RuntimeException( 'Expected error to be cleared after fallback.' );
        }
    };

    $tests['statuspage_atom_fallback_after_401'] = static function (): void {
        $atom = sprintf(
            '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Degraded</title><updated>%s</updated></entry></feed>',
            gmdate( 'c' )
        );

        HttpMock::queue([
            [ 'ok' => false, 'status' => 401, 'error' => 'http_error', 'message' => 'HTTP 401 response', 'body' => '' ],
            [ 'ok' => false, 'status' => 404, 'error' => 'http_error', 'message' => 'HTTP 404 response', 'body' => '' ],
            [ 'ok' => false, 'status' => 404, 'error' => 'http_error', 'message' => 'HTTP 404 response', 'body' => '' ],
            [ 'ok' => false, 'status' => 404, 'error' => 'http_error', 'message' => 'HTTP 404 response', 'body' => '' ],
            [ 'ok' => true, 'status' => 200, 'error' => null, 'message' => null, 'body' => $atom ],
        ]);

        $fetcher = new Fetcher( 4 );
        $result  = $fetcher->fetch([
            'id'   => 'atom-example',
            'name' => 'Atom Example',
            'type' => 'statuspage',
            'url'  => 'https://status.example.net/api/v2/summary.json',
        ]);

        if ( ! in_array( $result['status'], [ 'operational', 'degraded', 'outage' ], true ) ) {
            throw new \RuntimeException( 'Expected Atom fallback to produce a known status.' );
        }
        if ( null !== $result['error'] ) {
            throw new \RuntimeException( 'Expected no error after Atom fallback.' );
        }
    };

    $tests['optional_empty_body'] = static function (): void {
        HttpMock::queue([
            [ 'ok' => true, 'status' => 200, 'error' => null, 'message' => null, 'body' => '   ' ],
        ]);

        $fetcher = new Fetcher( 4 );
        $result  = $fetcher->fetch([
            'id'       => 'optional',
            'name'     => 'Optional',
            'type'     => 'rss-optional',
            'url'      => 'https://status.optional.example/history.rss',
            'optional' => true,
        ]);

        if ( false === strpos( (string) $result['error'], 'optional_unavailable' ) ) {
            throw new \RuntimeException( 'Expected optional_unavailable error string.' );
        }
    };

    $failed = false;

    foreach ( $tests as $name => $callback ) {
        try {
            $callback();
            echo "ok - {$name}\n";
        } catch ( \Throwable $throwable ) {
            $failed = true;
            echo "not ok - {$name}: " . $throwable->getMessage() . "\n";
        }
    }

    if ( $failed ) {
        exit( 1 );
    }

    echo "All tests passed\n";
}
