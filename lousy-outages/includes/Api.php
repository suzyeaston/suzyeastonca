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
                    'severity' => [
                        'description'       => 'Severity filter (e.g., important)',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'min_severity' => [
                        'description'       => 'Minimum severity to include (outage|degraded|maintenance|info)',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'limit' => [
                        'description'       => 'Maximum number of incidents to return (after dedupe)',
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            'lousy-outages/v1',
            '/cron-status',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_cron_status'],
            ]
        );

        register_rest_route(
            'lousy/v1',
            '/report',
            [
                'methods'             => 'POST',
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'handle_report'],
                'args'                => [
                    'provider_id' => [
                        'description'       => 'Provider identifier',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => true,
                    ],
                    'summary' => [
                        'description'       => 'Summary of the reported issue',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'required'          => true,
                    ],
                    'contact' => [
                        'description'       => 'Optional contact information for follow-up',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => false,
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
        $result = \lousy_outages_refresh_data(true);
        $skipped = !empty($result['skipped']);
        $ok      = !empty($result['ok']);

        if ($skipped && ! $ok) {
            $status = 200;
        } else {
            $status = $ok ? 200 : 503;
        }

        $providers = isset($result['providers']) && is_array($result['providers']) ? $result['providers'] : [];
        $errors    = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];
        $trending  = isset($result['trending']) && is_array($result['trending']) ? $result['trending'] : ['trending' => false, 'signals' => []];
        $refreshed_at = isset($result['refreshedAt']) ? (string) $result['refreshedAt'] : gmdate('c');

        $response = [
            'ok'            => $ok,
            'skipped'       => $skipped,
            'refreshed_at'  => isset($result['refreshed_at']) ? (int) $result['refreshed_at'] : time(),
            'refreshedAt'   => $refreshed_at,
            'providerCount' => count($providers),
            'errors'        => $errors,
            'providers'     => $providers,
            'trending'      => $trending,
            'source'        => 'live',
        ];

        if (!empty($result['message']) && is_string($result['message'])) {
            $response['message'] = $result['message'];
        }

        return new WP_REST_Response($response, $status);
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

    public static function handle_cron_status(WP_REST_Request $request): WP_REST_Response {
        $last_refresh_raw   = get_option('lousy_outages_last_fetched', 0);
        $last_refresh_epoch = is_numeric($last_refresh_raw) ? (int) $last_refresh_raw : 0;

        $last_refresh_iso_option = get_option('lousy_outages_last_fetched_iso');
        $last_refresh_gmt        = is_string($last_refresh_iso_option) && '' !== trim($last_refresh_iso_option)
            ? (string) $last_refresh_iso_option
            : ($last_refresh_epoch > 0 ? gmdate('c', $last_refresh_epoch) : null);

        $last_refresh_local = $last_refresh_epoch > 0 ? wp_date('c', $last_refresh_epoch) : null;

        $next_refresh      = wp_next_scheduled('lousy_outages_cron_refresh');
        $next_alert_run    = wp_next_scheduled('lousy_outages_refresh');
        $next_daily_digest = wp_next_scheduled('lo_send_daily_digest');

        $payload = [
            'ok'                              => true,
            'last_refresh_epoch'              => $last_refresh_epoch,
            'last_refresh_gmt'                => $last_refresh_gmt,
            'last_refresh_local'              => $last_refresh_local,
            'next_scheduled_refresh'          => $next_refresh ?: null,
            'next_scheduled_refresh_gmt'      => $next_refresh ? gmdate('c', (int) $next_refresh) : null,
            'next_scheduled_alert_run'        => $next_alert_run ?: null,
            'next_scheduled_alert_run_gmt'    => $next_alert_run ? gmdate('c', (int) $next_alert_run) : null,
            'next_scheduled_daily_digest'     => $next_daily_digest ?: null,
            'next_scheduled_daily_digest_gmt' => $next_daily_digest ? gmdate('c', (int) $next_daily_digest) : null,
            'wp_cron_disabled'                => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternate_wp_cron'               => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
        ];

        return new WP_REST_Response($payload, 200);
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
        if ($days > 365) {
            $days = 365;
        }

        $cutoff        = time() - ($days * DAY_IN_SECONDS);
        $store         = new Store();
        $incidentStore = new IncidentStore();
        $log           = $store->get_history_log();
        $events        = $incidentStore->getStoredIncidents();

        $normalizeStatus = static function (string $status): string {
            $status = strtolower(trim($status));
            switch ($status) {
                case 'ok':
                case 'none':
                case 'operational':
                case 'resolved':
                    return 'operational';
                case 'maintenance':
                case 'maintenance_window':
                    return 'maintenance';
                case 'minor':
                case 'minor_outage':
                case 'degraded':
                case 'degraded_performance':
                case 'partial':
                case 'partial_outage':
                case 'incident':
                case 'investigating':
                case 'identified':
                case 'monitoring':
                    return 'degraded';
                case 'major_outage':
                case 'outage':
                case 'major':
                case 'critical':
                    return 'major';
            }

            return $status ?: 'incident';
        };

        $severityParam = $request->get_param('severity');
        $importantOnly = true;
        if (null !== $severityParam) {
            $value         = strtolower((string) $severityParam);
            $importantOnly = ! in_array($value, ['all', 'any', 'everything'], true);
        }

        $minSeverityParam = $request->get_param('min_severity');
        $minSeverity      = in_array(strtolower((string) $minSeverityParam), ['outage', 'degraded', 'maintenance', 'info'], true)
            ? strtolower((string) $minSeverityParam)
            : '';

        $limitParam = $request->get_param('limit');
        $limit      = (int) (is_numeric($limitParam) ? $limitParam : 80);
        if ($limit <= 0) {
            $limit = 80;
        }
        if ($limit > 150) {
            $limit = 150;
        }

        $providers      = [];
        $providerLabels = [];
        foreach (Providers::enabled() as $id => $provider) {
            $providerLabels[$id] = isset($provider['name']) ? (string) $provider['name'] : ucfirst((string) $id);
        }
        $allowedProviders = array_fill_keys(array_keys($providerLabels), true);

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

        $severityOrder = [
            'info'        => 0,
            'maintenance' => 1,
            'degraded'    => 2,
            'outage'      => 3,
        ];

        $prepared = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $event = $incidentStore->normalizeEvent($event);

            $slug = sanitize_key((string) ($event['provider'] ?? ''));
            if ('' === $slug || !isset($allowedProviders[$slug])) {
                continue;
            }
            if (!empty($filters) && !in_array($slug, $filters, true)) {
                continue;
            }

            $firstSeen     = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $lastSeen      = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
            // Use incident start time to gate the history window (ignore refreshed old posts).
            $incidentStart = $firstSeen ?: $lastSeen;

            if ($incidentStart && $incidentStart < $cutoff) {
                continue;
            }

            $status    = $normalizeStatus((string) ($event['status'] ?? 'unknown'));
            $label     = $providerLabels[$slug] ?? ($event['provider_label'] ?? ucfirst($slug));
            $severity  = isset($event['severity']) ? strtolower((string) $event['severity']) : 'degraded';
            $important = isset($event['important']) ? (bool) $event['important'] : true;

            if (in_array($status, ['operational', 'ok', 'none'], true)) {
                continue;
            }

            if ($importantOnly) {
                if (! $important) {
                    continue;
                }
                if (in_array($severity, ['maintenance', 'info'], true)) {
                    continue;
                }
            }

            if ($minSeverity && isset($severityOrder[$severity]) && isset($severityOrder[$minSeverity])) {
                if ($severityOrder[$severity] < $severityOrder[$minSeverity]) {
                    continue;
                }
            }

            $prepared[] = [
                'id'         => (string) ($event['guid'] ?? sha1($slug . '|' . ($event['title'] ?? '') . '|' . ($firstSeen ?: $lastSeen))),
                'provider'   => $slug,
                'provider_label' => (string) $label,
                'status'     => $status,
                'severity'   => $severity,
                'important'  => (bool) $important,
                'summary'    => (string) ($event['title'] ?? $event['description'] ?? ''),
                'first_seen' => $firstSeen,
                'last_seen'  => $lastSeen,
                'url'        => isset($event['url']) ? (string) $event['url'] : '',
            ];
        }

        usort($prepared, static function ($left, $right): int {
            $leftTs  = isset($left['last_seen']) ? (int) $left['last_seen'] : (int) ($left['first_seen'] ?? 0);
            $rightTs = isset($right['last_seen']) ? (int) $right['last_seen'] : (int) ($right['first_seen'] ?? 0);
            return $rightTs <=> $leftTs;
        });

        $deduped         = [];
        $latestPerSource = [];
        $dedupeWindow    = 30 * MINUTE_IN_SECONDS;

        foreach ($prepared as $entry) {
            $slug      = $entry['provider'];
            $reference = $entry['last_seen'] ?: $entry['first_seen'];

            if (isset($latestPerSource[$slug])) {
                $lastIndex = $latestPerSource[$slug];
                $recent    = $deduped[$lastIndex];
                $sameTitle = isset($recent['summary'], $entry['summary']) && $recent['summary'] === $entry['summary'];
                $sameSeverity = isset($recent['severity']) && $recent['severity'] === ($entry['severity'] ?? '');
                $recentTs     = $recent['last_seen'] ?: $recent['first_seen'];

                if ($sameTitle && $sameSeverity && abs($recentTs - $reference) < $dedupeWindow) {
                    $deduped[$lastIndex]['first_seen'] = min(
                        (int) ($deduped[$lastIndex]['first_seen'] ?? $reference),
                        (int) ($entry['first_seen'] ?? $reference)
                    );
                    $deduped[$lastIndex]['last_seen'] = max(
                        (int) ($deduped[$lastIndex]['last_seen'] ?? $reference),
                        (int) ($entry['last_seen'] ?? $reference)
                    );
                    continue;
                }
            }

            $deduped[]              = $entry;
            $latestPerSource[$slug] = count($deduped) - 1;
        }

        $chartEvents = $deduped;
        if (count($deduped) > $limit) {
            $deduped = array_slice($deduped, 0, $limit);
        }

        $windowStart = $cutoff;
        $windowEnd   = time();

        foreach ($chartEvents as $event) {
            $firstSeen = isset($event['first_seen']) ? (int) $event['first_seen'] : 0;
            $lastSeen  = isset($event['last_seen']) ? (int) $event['last_seen'] : $firstSeen;
            $startTs   = $firstSeen ?: $lastSeen;
            $endTs     = $lastSeen ?: $firstSeen;

            if ($startTs && $startTs < $windowStart) {
                $windowStart = $startTs;
            }
            if ($endTs && $endTs > $windowEnd) {
                $windowEnd = $endTs;
            }
        }

        if ($windowStart <= 0) {
            $windowStart = $cutoff;
        }
        if ($windowEnd <= 0) {
            $windowEnd = time();
        }

        foreach ($deduped as $event) {
            $slug  = $event['provider'];
            $label = $event['provider_label'] ?? ucfirst($slug);
            $date  = gmdate('Y-m-d', $event['first_seen'] ?: ($event['last_seen'] ?: time()));

            $ensureProvider($slug, (string) $label);

            if (!isset($providers[$slug]['history'][$date])) {
                $providers[$slug]['history'][$date] = [
                    'date'        => $date,
                    'incidents'   => 0,
                    'last_status' => $event['status'],
                ];
            }

            $providers[$slug]['history'][$date]['incidents'] += 1;
            $providers[$slug]['history'][$date]['last_status'] = $event['status'];

            $providers[$slug]['incidents'][] = [
                'id'         => $event['id'],
                'provider'   => $event['provider_label'],
                'status'     => $event['status'],
                'severity'   => $event['severity'],
                'important'  => $event['important'],
                'summary'    => $event['summary'],
                'first_seen' => $event['first_seen'] ? gmdate('c', (int) $event['first_seen']) : null,
                'last_seen'  => $event['last_seen'] ? gmdate('c', (int) $event['last_seen']) : null,
                'url'        => $event['url'],
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
                if ('' === $slug || !isset($allowedProviders[$slug])) {
                    continue;
                }
                if (!empty($filters) && !in_array($slug, $filters, true)) {
                    continue;
                }
                $date   = gmdate('Y-m-d', $timestamp);
                $status = $normalizeStatus((string) ($entry['status'] ?? 'unknown'));
                $isIncident = !in_array($status, ['operational', 'none', 'ok'], true);

                if (!$isIncident) {
                    continue;
                }

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

                $providers[$slug]['incidents'][] = [
                    'id'         => sha1($slug . '|' . $timestamp . '|' . $status),
                    'provider'   => ucfirst($slug),
                    'status'     => $status,
                    'severity'   => 'degraded',
                    'important'  => true,
                    'summary'    => sprintf('Status reported: %s', ucfirst($status)),
                    'first_seen' => gmdate('c', $timestamp),
                    'last_seen'  => null,
                    'url'        => '',
                ];
            }
        }

        $providerCounts = [];
        $dailyCounts    = [];
        foreach ($chartEvents as $event) {
            $label = $event['provider_label'] ?? ($providerLabels[$event['provider']] ?? $event['provider']);
            $date  = gmdate('Y-m-d', $event['first_seen'] ?: ($event['last_seen'] ?: time()));
            $providerCounts[$label] = ($providerCounts[$label] ?? 0) + 1;
            $dailyCounts[$date]     = ($dailyCounts[$date] ?? 0) + 1;
        }

        $response = [
            'generated_at' => gmdate('c'),
            'meta'         => [
                'fetchedAt'        => gmdate('c'),
                'provider_counts'  => $providerCounts,
                'daily_counts'     => $dailyCounts,
                'important_only'   => $importantOnly,
                'window_days'      => $days,
                'window_start'     => gmdate('Y-m-d', $windowStart),
                'window_end'       => gmdate('Y-m-d', $windowEnd),
                'deduped_incidents' => count($deduped),
            ],
            'providers'    => [],
        ];

        $yearOverYear = $incidentStore->getYearOverYearWindow($days, $importantOnly);
        $response['meta']['year_over_year'] = [
            'current'  => [
                'start'        => gmdate('Y-m-d', $yearOverYear['current']['start'] ?? $windowStart),
                'end'          => gmdate('Y-m-d', $yearOverYear['current']['end'] ?? $windowEnd),
                'total'        => $yearOverYear['current']['total'] ?? 0,
                'daily_counts' => $yearOverYear['current']['daily_counts'] ?? [],
            ],
            'previous' => [
                'start'        => gmdate('Y-m-d', $yearOverYear['previous']['start'] ?? ($windowStart - YEAR_IN_SECONDS)),
                'end'          => gmdate('Y-m-d', $yearOverYear['previous']['end'] ?? ($windowEnd - YEAR_IN_SECONDS)),
                'total'        => $yearOverYear['previous']['total'] ?? 0,
                'daily_counts' => $yearOverYear['previous']['daily_counts'] ?? [],
            ],
            'delta'    => ($yearOverYear['current']['total'] ?? 0) - ($yearOverYear['previous']['total'] ?? 0),
        ];

        foreach ($providers as $provider) {
            $history = $provider['history'];
            ksort($history);
            $incidents = $provider['incidents'];
            usort($incidents, static function ($left, $right): int {
                $leftTs  = isset($left['last_seen']) ? strtotime((string) $left['last_seen']) : 0;
                $rightTs = isset($right['last_seen']) ? strtotime((string) $right['last_seen']) : 0;
                if (!$leftTs) {
                    $leftTs = isset($left['first_seen']) ? strtotime((string) $left['first_seen']) : 0;
                }
                if (!$rightTs) {
                    $rightTs = isset($right['first_seen']) ? strtotime((string) $right['first_seen']) : 0;
                }

                return $rightTs <=> $leftTs;
            });

            $response['providers'][] = [
                'id'        => $provider['id'],
                'label'     => $provider['label'],
                'history'   => array_values($history),
                'incidents' => array_values($incidents),
            ];
        }

        return rest_ensure_response($response);
    }

    public static function handle_report(WP_REST_Request $request): WP_REST_Response {
        $providerId = sanitize_text_field((string) $request->get_param('provider_id'));
        $summary    = sanitize_textarea_field((string) $request->get_param('summary'));
        $contact    = sanitize_text_field((string) $request->get_param('contact'));

        if ('' === trim($providerId) || '' === trim($summary)) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'provider_id and summary are required.',
            ], 400);
        }

        $providers     = Providers::list();
        $providerLabel = $providers[$providerId]['name'] ?? ($providerId ? ucfirst($providerId) : 'provider');

        $store = new IncidentStore();
        $store->addUserReport($providerId, $summary, $contact, $providerLabel);

        return new WP_REST_Response([
            'ok'       => true,
            'provider' => $providerId,
            'message'  => sprintf(
                'Thanks – we have logged your report for %s. It will appear in the status history as a community report.',
                $providerLabel
            ),
        ], 200);
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
