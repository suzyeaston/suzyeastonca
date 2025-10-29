<?php
/**
 * Snapshot caching and REST exposure for Lousy Outages.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('lo_snapshot_bootstrap')) {
    function lo_snapshot_bootstrap(): void
    {
        add_action('rest_api_init', 'lo_snapshot_register_route');
    }
}

if (! function_exists('lo_snapshot_register_route')) {
    function lo_snapshot_register_route(): void
    {
        register_rest_route(
            'lousy/v1',
            '/snapshot',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => 'lo_snapshot_rest_callback',
            ]
        );
    }
}

if (! function_exists('lo_snapshot_rest_callback')) {
    function lo_snapshot_rest_callback(\WP_REST_Request $request)
    {
        return rest_ensure_response(lo_snapshot_get_cached());
    }
}

if (! function_exists('lo_snapshot_transient_key')) {
    function lo_snapshot_transient_key(): string
    {
        return 'lo_snapshot_payload_v1';
    }
}

if (! function_exists('lo_snapshot_last_good_key')) {
    function lo_snapshot_last_good_key(): string
    {
        return 'lo_snapshot_last_good_v1';
    }
}

if (! function_exists('lo_snapshot_default_ttl')) {
    function lo_snapshot_default_ttl(): int
    {
        $ttl = (int) apply_filters('lo_snapshot_ttl', 300);
        if ($ttl < 120) {
            $ttl = 120;
        }
        if ($ttl > 900) {
            $ttl = 900;
        }

        return $ttl;
    }
}

if (! function_exists('lo_snapshot_refresh')) {
    function lo_snapshot_refresh(bool $force_refresh = true): array
    {
        if (! function_exists('lousy_outages_get_snapshot')) {
            return lo_snapshot_get_cached();
        }

        $raw = lousy_outages_get_snapshot($force_refresh);
        $updated_at = isset($raw['fetched_at']) ? (string) $raw['fetched_at'] : gmdate('c');
        $services = [];
        if (! empty($raw['providers']) && is_array($raw['providers'])) {
            foreach ($raw['providers'] as $provider) {
                if (! is_array($provider)) {
                    continue;
                }
                $services[] = lo_snapshot_normalize_service($provider, $updated_at);
            }
        }

        $payload = [
            'updated_at' => $updated_at,
            'services'   => $services,
        ];

        return lo_snapshot_store($payload);
    }
}

if (! function_exists('lo_snapshot_store')) {
    function lo_snapshot_store(array $payload): array
    {
        $ttl = lo_snapshot_default_ttl();
        $expires_at = time() + $ttl;

        $stored = [
            'updated_at' => (string) ($payload['updated_at'] ?? gmdate('c')),
            'services'   => is_array($payload['services'] ?? null) ? array_values($payload['services']) : [],
            'expires_at' => $expires_at,
        ];

        set_transient(lo_snapshot_transient_key(), $stored, $ttl);
        update_option(lo_snapshot_last_good_key(), $stored, false);

        return lo_snapshot_prepare_response($stored, false);
    }
}

if (! function_exists('lo_snapshot_get_cached')) {
    function lo_snapshot_get_cached(): array
    {
        $cached = get_transient(lo_snapshot_transient_key());
        if (is_array($cached) && ! empty($cached['services'])) {
            $stale = isset($cached['expires_at']) ? (int) $cached['expires_at'] < time() : false;
            return lo_snapshot_prepare_response($cached, $stale);
        }

        $stored = get_option(lo_snapshot_last_good_key(), []);
        if (is_array($stored) && ! empty($stored['services'])) {
            return lo_snapshot_prepare_response($stored, true);
        }

        return [
            'updated_at'  => gmdate('c'),
            'ttl_seconds' => lo_snapshot_default_ttl(),
            'services'    => [],
            'stale'       => true,
        ];
    }
}

if (! function_exists('lo_snapshot_prepare_response')) {
    function lo_snapshot_prepare_response(array $stored, bool $stale): array
    {
        $ttl = lo_snapshot_default_ttl();
        $response = [
            'updated_at'  => isset($stored['updated_at']) ? (string) $stored['updated_at'] : gmdate('c'),
            'ttl_seconds' => $ttl,
            'services'    => [],
            'stale'       => $stale,
        ];

        if (! empty($stored['services']) && is_array($stored['services'])) {
            foreach ($stored['services'] as $service) {
                if (! is_array($service)) {
                    continue;
                }
                $response['services'][] = lo_snapshot_normalize_service($service, $response['updated_at']);
            }
        }

        return $response;
    }
}

if (! function_exists('lo_snapshot_normalize_service')) {
    function lo_snapshot_normalize_service(array $service, string $fetched_at): array
    {
        $status_code = strtolower((string) ($service['stateCode'] ?? $service['status'] ?? 'unknown'));
        $status_label = (string) ($service['state'] ?? $service['status_label'] ?? ucfirst($status_code));
        $updated_at = (string) ($service['updatedAt'] ?? $service['updated_at'] ?? $fetched_at);
        $risk = 0;
        if (isset($service['risk'])) {
            $risk = (int) $service['risk'];
        } elseif (isset($service['prealert']['risk'])) {
            $risk = (int) $service['prealert']['risk'];
        }

        return [
            'id'          => (string) ($service['id'] ?? $service['provider'] ?? ''),
            'name'        => (string) ($service['name'] ?? $service['provider'] ?? ''),
            'status'      => $status_code ?: 'unknown',
            'status_text' => $status_label ?: 'Unknown',
            'summary'     => (string) ($service['summary'] ?? $service['message'] ?? ''),
            'updated_at'  => $updated_at ?: $fetched_at,
            'url'         => (string) ($service['url'] ?? ''),
            'risk'        => max(0, $risk),
        ];
    }
}
