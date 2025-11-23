<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Storage\IncidentStore;
use WP_REST_Request;
use WP_REST_Response;

class Api {
    public static function bootstrap(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(
            'lousy/v1',
            '/refresh',
            [
                'methods'             => 'POST',
                'permission_callback' => [self::class, 'verify_nonce'],
                'callback'            => [self::class, 'handle_refresh'],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/refresh',
            [
                'methods'             => ['POST', 'GET'],
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_refresh'],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/summary',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_summary'],
                'args'                => [
                    'provider' => [
                        'description'       => 'Optional provider filter (comma-separated)',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/history',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_history'],
                'args'                => [
                    'provider' => [
                        'description'       => 'Optional provider filter (comma-separated)',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'days' => [
                        'description'       => 'How many days of history to return (default 30)',
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

    }

    public static function verify_nonce(): bool {
        $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Trigger a manual refresh of provider statuses.
     */
    public static function handle_refresh(WP_REST_Request $request): WP_REST_Response {
        $timestamp = gmdate('c');
        $store     = new Store();
        $states    = lousy_outages_collect_statuses(true);
        $errors    = [];

        foreach ($states as $id => $state) {
            $store->update($id, $state);
            if (!empty($state['error'])) {
                $errors[] = [
                    'id'      => $id,
                    'provider'=> isset($state['name']) ? (string) $state['name'] : (isset($state['provider']) ? (string) $state['provider'] : $id),
                    'message' => (string) $state['error'],
                ];
            }
        }

        update_option('lousy_outages_last_poll', $timestamp, false);
        do_action('lousy_outages_log', 'manual_refresh', [
            'count' => count($states),
            'ts'    => $timestamp,
        ]);

        $snapshot  = \lousy_outages_refresh_snapshot($states, $timestamp, 'live');
        $providers = isset($snapshot['providers']) && is_array($snapshot['providers']) ? $snapshot['providers'] : [];

        if (empty($errors) && isset($snapshot['errors']) && is_array($snapshot['errors'])) {
            $errors = $snapshot['errors'];
        }

        $epoch = time();
        $response = [
            'ok'            => true,
            'refreshed_at'  => $epoch,
            'refreshedAt'   => $timestamp,
            'providerCount' => count($providers),
            'errors'        => $errors,
            'providers'     => $providers,
            'trending'      => $snapshot['trending'] ?? ['trending' => false, 'signals' => []],
            'source'        => 'live',
        ];

        return rest_ensure_response($response);
    }

    public static function handle_summary(WP_REST_Request $request) {
        $providerParam = $request->get_param('provider');
        $filters       = self::sanitize_provider_list(is_string($providerParam) ? $providerParam : null);

        $snapshot = \lousy_outages_get_snapshot(false);
        if (empty($snapshot['providers'])) {
            $snapshot = \lousy_outages_get_snapshot(true);
        }

        $providers = isset($snapshot['providers']) && is_array($snapshot['providers']) ? array_values($snapshot['providers']) : [];
        if (!empty($filters)) {
            $providers = array_values(array_filter($providers, static function ($provider) use ($filters) {
                if (!is_array($provider)) {
                    return false;
                }
                $slug = strtolower((string) ($provider['provider'] ?? $provider['id'] ?? ''));
                return $slug && in_array($slug, $filters, true);
            }));
        }

        $trending = isset($snapshot['trending']) && is_array($snapshot['trending'])
            ? $snapshot['trending']
            : ['trending' => false, 'signals' => [], 'generated_at' => gmdate('c')];
        $source = isset($snapshot['source']) ? (string) $snapshot['source'] : 'snapshot';
        $fetched_at = isset($snapshot['fetched_at']) ? (string) $snapshot['fetched_at'] : gmdate('c');
        $errors = isset($snapshot['errors']) && is_array($snapshot['errors']) ? $snapshot['errors'] : [];

        $isLite = false;
        $liteParam = $request->get_param('lite');
        if (null !== $liteParam) {
            $value = strtolower((string) $liteParam);
            $isLite = in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        if ($isLite) {
            $payload = [
                'trending' => !empty($trending['trending']),
            ];
        } else {
            $payload = [
                'providers'  => $providers,
                'fetched_at' => $fetched_at,
                'trending'   => $trending,
                'source'     => $source,
            ];
            if (!empty($errors)) {
                $payload['errors'] = $errors;
            }
        }

        $json = wp_json_encode($payload);
        if (!is_string($json)) {
            $json = '{}';
        }
        $etag = '"' . sha1($json) . '"';
        $incoming = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $matched = false;
        if ('' !== $incoming) {
            $candidates = array_map('trim', explode(',', $incoming));
            foreach ($candidates as $candidate) {
                if ($candidate === $etag || $candidate === 'W/' . $etag) {
                    $matched = true;
                    break;
                }
            }
        }

        if ($matched) {
            status_header(304);
            header('ETag: ' . $etag);
            header('Cache-Control: no-cache, must-revalidate');
            exit;
        }

        status_header(200);
        header('ETag: ' . $etag);
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        exit;
    }

    /**
     * Return a compact history document for each provider.
     *
     * Schema (per provider):
     * {
     *   id: "github",
     *   history: [ { date: "2024-06-01", incidents: 2, last_status: "degraded" }, ... ]
     * }
     */
    public static function handle_history(WP_REST_Request $request): WP_REST_Response {
        $providerParam = $request->get_param('provider');
        $filters       = self::sanitize_provider_list(is_string($providerParam) ? $providerParam : null);

        $daysParam = $request->get_param('days');
        $days = (int) (is_numeric($daysParam) ? $daysParam : 30);
        if ($days <= 0) {
            $days = 30;
        }
        if ($days > 90) {
            $days = 90;
        }

        $cutoff        = time() - ($days * DAY_IN_SECONDS);
        $store         = new Store();
        $incidentStore = new IncidentStore();
        $log           = $store->get_history_log();
        $events        = $incidentStore->getStoredIncidents();

        $providers = [];

        $ensureProvider = static function (string $slug, string $label) use (&$providers): void {
            if (!isset($providers[$slug])) {
                $providers[$slug] = [
                    'id'        => $slug,
                    'label'     => $label,
                    'history'   => [],
                    'incidents' => [],
                ];
            }
        };

        // Sanity check: calling /wp-json/lousy-outages/v1/history?provider=github&days=30
        // should include any non-operational incidents persisted within that window.
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $slug = sanitize_key((string) ($event['provider'] ?? ''));
            if ('' === $slug) {
                continue;
            }
            if (!empty($filters) && !in_array($slug, $filters, true)) {
                continue;
            }

            $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $lastSeen  = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;

            if ($lastSeen && $lastSeen < $cutoff) {
                continue;
            }

            $status = strtolower((string) ($event['status'] ?? 'unknown'));
            $label  = $event['provider_label'] ?? ucfirst($slug);
            $date   = gmdate('Y-m-d', $firstSeen ?: $lastSeen ?: time());
            $isIncident = !in_array($status, ['operational', 'none', 'ok'], true);

            $ensureProvider($slug, (string) $label);

            if (!isset($providers[$slug]['history'][$date])) {
                $providers[$slug]['history'][$date] = [
                    'date'        => $date,
                    'incidents'   => 0,
                    'last_status' => $status,
                ];
            }

            if ($isIncident) {
                $providers[$slug]['history'][$date]['incidents'] += 1;
            }
            $providers[$slug]['history'][$date]['last_status'] = $status;

            $providers[$slug]['incidents'][] = [
                'id'         => (string) ($event['guid'] ?? sha1($slug . '|' . ($event['title'] ?? '') . '|' . ($firstSeen ?: $lastSeen))),
                'provider'   => (string) $label,
                'status'     => $status,
                'summary'    => (string) ($event['title'] ?? $event['description'] ?? ''),
                'first_seen' => $firstSeen ? gmdate('c', $firstSeen) : null,
                'last_seen'  => $lastSeen ? gmdate('c', $lastSeen) : null,
                'url'        => isset($event['url']) ? (string) $event['url'] : '',
            ];
        }

        // Fallback for environments without persisted incidents yet: use legacy status log.
        if (empty($providers)) {
            foreach ($log as $entry) {
                if (!is_array($entry) || empty($entry['id']) || !isset($entry['time'])) {
                    continue;
                }
                $timestamp = (int) $entry['time'];
                if ($timestamp < $cutoff) {
                    continue;
                }
                $slug = sanitize_key((string) $entry['id']);
                if ('' === $slug) {
                    continue;
                }
                if (!empty($filters) && !in_array($slug, $filters, true)) {
                    continue;
                }
                $date   = gmdate('Y-m-d', $timestamp);
                $status = strtolower((string) ($entry['status'] ?? 'unknown'));
                $isIncident = !in_array($status, ['operational', 'none', 'ok'], true);

                $ensureProvider($slug, ucfirst($slug));

                if (!isset($providers[$slug]['history'][$date])) {
                    $providers[$slug]['history'][$date] = [
                        'date'        => $date,
                        'incidents'   => 0,
                        'last_status' => $status,
                    ];
                }

                if ($isIncident) {
                    $providers[$slug]['history'][$date]['incidents'] += 1;
                }
                $providers[$slug]['history'][$date]['last_status'] = $status;
            }
        }

        $response = [
            'generated_at' => gmdate('c'),
            'providers'    => [],
        ];

        foreach ($providers as $provider) {
            $history = $provider['history'];
            ksort($history);
            $response['providers'][] = [
                'id'        => $provider['id'],
                'label'     => $provider['label'],
                'history'   => array_values($history),
                'incidents' => array_values($provider['incidents']),
            ];
        }

        return rest_ensure_response($response);
    }


    private static function sanitize_provider_list(?string $raw): array {
        if (!$raw) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $parts = array_map(static function ($part) {
            return strtolower((string) $part);
        }, $parts);

        return array_values(array_unique($parts));
    }

}
