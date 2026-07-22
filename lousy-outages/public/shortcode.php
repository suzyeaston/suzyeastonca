<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

const LO_SHOW_WIDESPREAD = false;

/**
 * Determine the filesystem path and URL for standalone plugin assets.
 *
 * Public assets always come from LOUSY_OUTAGES_URL . 'assets/' so obsolete
 * theme copies cannot create version skew between SSR markup and JS/CSS.
 *
 * @return array{string, string}
 */
function locate_assets_base(): array
{
    $plugin_path = defined('LOUSY_OUTAGES_PATH') ? rtrim(LOUSY_OUTAGES_PATH, '/\\') . '/assets/' : rtrim(plugin_dir_path(__DIR__), '/\\') . '/assets/';
    $plugin_url = defined('LOUSY_OUTAGES_URL') ? rtrim(LOUSY_OUTAGES_URL, '/\\') . '/assets/' : rtrim(plugin_dir_url(__DIR__), '/\\') . '/assets/';
    return [$plugin_path, $plugin_url];
}

function asset_version(string $base_path, string $asset): string
{
    $version = defined('LOUSY_OUTAGES_VERSION') ? (string) LOUSY_OUTAGES_VERSION : '0.0.0';
    $path = rtrim($base_path, '/\\') . '/' . ltrim($asset, '/\\');
    return $version . '-' . (file_exists($path) ? (string) filemtime($path) : '0');
}


function provider_identity(array $tile): string
{
    foreach (['provider_id', 'id', 'provider'] as $field) {
        if (!isset($tile[$field])) {
            continue;
        }
        $identity = sanitize_key((string) $tile[$field]);
        if ('' !== $identity) {
            return $identity;
        }
    }
    return '';
}

add_shortcode('lousy_outages', __NAMESPACE__ . '\render_shortcode');
add_shortcode('lousy_outages_subscribe', __NAMESPACE__ . '\render_subscribe_shortcode');
add_shortcode('lousy_outages_report', __NAMESPACE__ . '\render_report_shortcode');

function render_shortcode(): string {
    [$base_path, $base_url] = locate_assets_base();

    wp_enqueue_style(
        'lousy-outages',
        $base_url . 'lousy-outages.css',
        [],
        asset_version($base_path, 'lousy-outages.css')
    );

    wp_enqueue_style(
        'lousy-outages-hud',
        $base_url . 'hud.css',
        ['lousy-outages'],
        asset_version($base_path, 'hud.css')
    );

    wp_enqueue_script(
        'lousy-outages',
        $base_url . 'lousy-outages.js',
        [],
        asset_version($base_path, 'lousy-outages.js'),
        true
    );

    wp_enqueue_script(
        'lousy-outages-hud',
        $base_url . 'hud.js',
        ['lousy-outages'],
        asset_version($base_path, 'hud.js'),
        true
    );

    if (file_exists($base_path . 'js/outages.js')) {
        wp_enqueue_script(
            'lousy-outages-auto-refresh',
            $base_url . 'js/outages.js',
            [],
            asset_version($base_path, 'js/outages.js'),
            true
        );
    }

    // Dynamic provider data is hydrated from the canonical summary; ignore legacy unversioned fragments.
    $cache_key = null;
    $cached    = ($cache_key) ? get_transient($cache_key) : null;
    $snapshot_endpoint = esc_url_raw(rest_url('lousy/v1/snapshot'));

    $fetcher      = new Lousy_Outages_Fetcher();
    $provider_map = $fetcher->get_provider_map();

    $providers_config = Providers::enabled();
    if (is_array($provider_map) && $provider_map) {
        foreach ($provider_map as $slug => $info) {
            if (!isset($providers_config[$slug]) && is_array($info)) {
                $providers_config[$slug] = [
                    'id'         => $slug,
                    'name'       => isset($info['name']) ? (string) $info['name'] : ucfirst((string) $slug),
                    'status_url' => isset($info['status_url']) ? (string) $info['status_url'] : '',
                ];
            }
        }
    }

    $fetched_at = '';
    $last_fetched_iso = \function_exists('lousy_outages_get_last_fetched_iso')
        ? \lousy_outages_get_last_fetched_iso()
        : '';
    $tiles      = [];
    $config     = null;
    $refresh_interval = 60000;
    $initial_trending = [
        'trending'     => false,
        'signals'      => [],
        'generated_at' => gmdate('c'),
    ];
    $source = 'live';
    $snapshot_errors = [];

    if (is_array($cached) && isset($cached['config'])) {
        $config = $cached['config'];
        if (isset($config['initial']['providers']) && is_array($config['initial']['providers'])) {
            foreach ($config['initial']['providers'] as $tile) {
                if (!is_array($tile)) {
                    continue;
                }
                $slug = provider_identity($tile);
                if ('' === $slug) {
                    continue;
                }
                $tiles[] = $tile;
            }
        }
        $fetched_at = $config['initial']['fetched_at'] ?? $config['initial']['fetchedAt'] ?? $fetched_at;
        if (isset($config['initial']['trending']) && is_array($config['initial']['trending'])) {
            $initial_trending = $config['initial']['trending'];
        }
        if (isset($config['initial']['source'])) {
            $source = (string) $config['initial']['source'];
        }
        if (isset($config['initial']['errors']) && is_array($config['initial']['errors'])) {
            $snapshot_errors = $config['initial']['errors'];
        }
    }

    if (!$config) {
        $snapshot = \lousy_outages_get_snapshot(false);
        if (!isset($snapshot['providers']) || !is_array($snapshot['providers']) || !$snapshot['providers']) {
            $stored_snapshot = get_option('lousy_outages_snapshot', []);
            if (isset($stored_snapshot['providers']) && is_array($stored_snapshot['providers']) && $stored_snapshot['providers']) {
                $snapshot = $stored_snapshot;
            }
        }

        if (isset($snapshot['providers']) && is_array($snapshot['providers']) && $snapshot['providers']) {
            $tiles = array_values($snapshot['providers']);
            if (isset($snapshot['fetched_at'])) {
                $fetched_at = (string) $snapshot['fetched_at'];
            }
            if (isset($snapshot['trending']) && is_array($snapshot['trending'])) {
                $initial_trending = $snapshot['trending'];
            }
            if (!empty($snapshot['errors']) && is_array($snapshot['errors'])) {
                $snapshot_errors = $snapshot['errors'];
            }
            $source = isset($snapshot['source']) ? (string) $snapshot['source'] : 'snapshot';
        } else {
            $normalized_snapshot = \lo_snapshot_get_cached();
            if (!empty($normalized_snapshot['services'])) {
                foreach ($normalized_snapshot['services'] as $service) {
                    if (!is_array($service)) {
                        continue;
                    }
                    $service_id = (string) ($service['id'] ?? '');
                    if ('' === $service_id) {
                        continue;
                    }
                    $tiles[] = [
                        'id'          => $service_id,
                        'provider'    => $service_id,
                        'name'        => $service['name'] ?? $service_id,
                        'status'      => $service['status'] ?? 'unknown',
                        'status_label'=> $service['status_text'] ?? ucfirst((string) ($service['status'] ?? 'unknown')),
                        'state'       => $service['status_text'] ?? '',
                        'stateCode'   => $service['status'] ?? 'unknown',
                        'summary'     => $service['summary'] ?? '',
                        'url'         => $service['url'] ?? '',
                        'updated_at'  => $service['updated_at'] ?? $normalized_snapshot['updated_at'],
                        'incidents'   => [],
                    ];
                }
                $fetched_at = $normalized_snapshot['updated_at'];
                $snapshot_errors = [];
                $source = $normalized_snapshot['stale'] ? 'stale' : 'snapshot';
            }
        }

        $config = [
            'endpoint'          => esc_url_raw(rest_url('lousy-outages/v1/summary')),
            'pollInterval'      => $refresh_interval,
            'refreshEndpoint'   => current_user_can('manage_options') ? esc_url_raw(rest_url('lousy-outages/v1/refresh')) : '',
            'refreshNonce'      => current_user_can('manage_options') ? wp_create_nonce('wp_rest') : '',
            'subscribeEndpoint' => esc_url_raw(rest_url('lousy-outages/v1/subscribe')),
            'snapshotEndpoint'  => $snapshot_endpoint,
            'historyEndpoint'   => esc_url_raw(rest_url('lousy-outages/v1/history')),
            'initial'           => [
                'providers'  => $tiles,
                'fetched_at' => $fetched_at,
                'trending'   => $initial_trending,
                'source'     => $source,
                'errors'     => $snapshot_errors,
            ],
        ];
    } else {
        if (!isset($config['initial']['trending']) || !is_array($config['initial']['trending'])) {
            $config['initial']['trending'] = $initial_trending;
        } else {
            $initial_trending = $config['initial']['trending'];
        }
        if (isset($config['initial']['source'])) {
            $source = (string) $config['initial']['source'];
        } else {
            $config['initial']['source'] = $source;
        }
        if (isset($config['initial']['errors']) && is_array($config['initial']['errors'])) {
            $snapshot_errors = $config['initial']['errors'];
        } else {
            $config['initial']['errors'] = $snapshot_errors;
        }

        if (!isset($config['snapshotEndpoint'])) {
            $config['snapshotEndpoint'] = $snapshot_endpoint;
        }
        if (!isset($config['historyEndpoint'])) {
            $config['historyEndpoint'] = esc_url_raw(rest_url('lousy-outages/v1/history'));
        }
    }

    if ($last_fetched_iso) {
        if ('' === $fetched_at) {
            $fetched_at = $last_fetched_iso;
        }
        if (isset($config['initial']['fetched_at'])) {
            $config['initial']['fetched_at'] = $last_fetched_iso;
        }
    }

    if ('' === $fetched_at) {
        $fetched_at = $last_fetched_iso ?: gmdate('c');
    }

    $current_state = function_exists('lousy_outages_get_current_state') ? \lousy_outages_get_current_state() : [];
    if (isset($current_state['providers']) && is_array($current_state['providers']) && $current_state['providers']) {
        $canonical_tiles = [];
        foreach ($current_state['providers'] as $provider_tile) {
            if (!is_array($provider_tile)) {
                continue;
            }
            $provider_id = provider_identity($provider_tile);
            if ('' === $provider_id) {
                continue;
            }
            $provider_tile['provider_id'] = $provider_id;
            $provider_tile['id'] = $provider_id;
            if (!isset($provider_tile['name']) || '' === trim((string) $provider_tile['name'])) {
                $provider_tile['name'] = (string) ($provider_tile['provider'] ?? $provider_id);
            }
            $canonical_tiles[] = $provider_tile;
        }
        if ($canonical_tiles) {
            $tiles = $canonical_tiles;
            $source = (string) ($current_state['source'] ?? $source);
            if (!empty($current_state['fetched_at'])) {
                $fetched_at = (string) $current_state['fetched_at'];
            }
        }
    }

    $tiles_by_slug = [];
    foreach ($tiles as $tile) {
        if (!is_array($tile)) {
            continue;
        }
        $slug = provider_identity($tile);
        if ('' === $slug) {
            continue;
        }
        $tiles_by_slug[$slug] = $tile;
    }

    $ordered_tiles = [];
    foreach ($tiles as $tile) {
        if (!is_array($tile)) {
            continue;
        }
        $slug = provider_identity($tile);
        if ('' === $slug) {
            continue;
        }
        if (!empty($providers_config) && !isset($providers_config[$slug])) {
            continue;
        }
        $ordered_tiles[] = $tile;
    }

    $operational_placeholders = apply_filters('lo_operational_placeholder_providers', ['openai', 'twilio']);
    if (!is_array($operational_placeholders)) {
        $operational_placeholders = ['openai', 'twilio'];
    }
    $operational_placeholders = array_map('sanitize_key', $operational_placeholders);

    $placeholder_label = apply_filters('lo_operational_placeholder_label', 'OPERATIONAL');
    if (!is_string($placeholder_label) || '' === trim($placeholder_label)) {
        $placeholder_label = 'OPERATIONAL';
    }

    $placeholder_summary = apply_filters('lo_operational_placeholder_summary', 'Assuming operational — awaiting the next live sync.');
    if (!is_string($placeholder_summary) || '' === trim($placeholder_summary)) {
        $placeholder_summary = 'Assuming operational — awaiting the next live sync.';
    }

    $placeholder_message = apply_filters('lo_operational_placeholder_message', 'Live status feed quiet; placeholder set to operational.');
    if (!is_string($placeholder_message) || '' === trim($placeholder_message)) {
        $placeholder_message = 'Live status feed quiet; placeholder set to operational.';
    }

    foreach ($providers_config as $slug => $provider_config) {
        if (isset($tiles_by_slug[$slug])) {
            continue;
        }
        $provider_key = sanitize_key($slug);
        $is_operational_placeholder = in_array($provider_key, $operational_placeholders, true);
        $status_slug = $is_operational_placeholder ? 'operational' : 'unknown';
        $tile_kind = $is_operational_placeholder ? 'operational' : 'unknown';
        $sort_key = $is_operational_placeholder ? 70 : 90;
        $status_label = $is_operational_placeholder ? $placeholder_label : 'UNKNOWN';
        $status_class = $is_operational_placeholder ? 'status--operational' : 'status--unknown';
        $summary_text = $is_operational_placeholder ? '' : 'Can’t verify status right now.';
        $message_text = $is_operational_placeholder ? 'All systems operational.' : 'Can’t verify status right now.';

        $ordered_tiles[] = [
            'id'           => $slug,
            'provider'     => $slug,
            'name'         => $provider_config['name'] ?? ucfirst($slug),
            'status'       => $status_slug,
            'status_label' => $status_label,
            'status_class' => $status_class,
            'overall'      => $status_slug,
            'message'      => $message_text,
            'summary'      => $summary_text,
            'components'   => [],
            'incidents'    => [],
            'fetched_at'   => $fetched_at,
            'http_code'    => 0,
            'indicator'    => null,
            'link'         => $provider_config['status_url'] ?? '',
            'url'          => $provider_config['status_url'] ?? '',
            'error'        => null,
            'tile_kind'    => $tile_kind,
            'sort_key'     => $sort_key,
        ];
    }

    if (!$ordered_tiles && $provider_map) {
        foreach ($provider_map as $slug => $provider_info) {
            $provider_key = sanitize_key($slug);
            $is_operational_placeholder = in_array($provider_key, $operational_placeholders, true);
            $status_slug = $is_operational_placeholder ? 'operational' : 'unknown';
            $tile_kind = $is_operational_placeholder ? 'operational' : 'unknown';
            $sort_key = $is_operational_placeholder ? 70 : 90;
            $status_label = $is_operational_placeholder ? $placeholder_label : 'UNKNOWN';
            $status_class = $is_operational_placeholder ? 'status--operational' : 'status--unknown';
            $summary_text = $is_operational_placeholder ? '' : 'Can’t verify status right now.';
            $message_text = $is_operational_placeholder ? 'All systems operational.' : 'Can’t verify status right now.';

            $ordered_tiles[] = [
                'provider'     => $slug,
                'name'         => $provider_info['name'],
                'status'       => $status_slug,
                'status_label' => $status_label,
                'status_class' => $status_class,
                'overall'      => $status_slug,
                'message'      => $message_text,
                'summary'      => $summary_text,
                'components'   => [],
                'incidents'    => [],
                'fetched_at'   => $fetched_at,
                'http_code'    => 0,
                'indicator'    => null,
                'link'         => $provider_info['status_url'],
                'url'          => $provider_info['status_url'],
                'error'        => null,
                'tile_kind'    => $tile_kind,
                'sort_key'     => $sort_key,
            ];
        }
    }

    usort($ordered_tiles, static function (array $left, array $right): int {
        $rank = static function (array $tile): array {
            $incidents = \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider($tile);
            $raw_status = strtolower((string) ($tile['status'] ?? $tile['stateCode'] ?? $tile['overall'] ?? 'unknown'));
            $tile_kind = strtolower((string) ($tile['tile_kind'] ?? $tile['tileKind'] ?? ''));
            $has_error = !empty($tile['error']) || in_array((string) ($tile['verification_status'] ?? ''), ['failed', 'delayed', 'unknown'], true);
            if (!empty($incidents)) { $priority = 10; }
            elseif ('signal' === $tile_kind || in_array($raw_status, ['degraded', 'major', 'outage', 'maintenance', 'partial_outage', 'degraded_performance'], true)) { $priority = 20; }
            elseif ($has_error) { $priority = 30; }
            elseif ('manual' === $tile_kind || in_array($raw_status, ['unknown', ''], true)) { $priority = 40; }
            else { $priority = 50; }
            return [$priority, strtolower((string) ($tile['name'] ?? $tile['provider'] ?? $tile['id'] ?? ''))];
        };
        return $rank($left) <=> $rank($right);
    });

    $config['initial']['providers'] = array_map(static function ($provider) use ($providers_config) {
        if (!is_array($provider)) { return $provider; }
        $provider_id = provider_identity($provider);
        if ('' !== $provider_id) {
            $provider['provider_id'] = $provider_id;
            $provider['id'] = $provider_id;
        }
        if (isset($providers_config[$provider_id]) && is_array($providers_config[$provider_id])) {
            $provider['category'] = (string)($providers_config[$provider_id]['category'] ?? 'other');
            $provider['source_type'] = (string)($providers_config[$provider_id]['source_type'] ?? $providers_config[$provider_id]['type'] ?? '');
            $provider['freshness_threshold'] = (int)($providers_config[$provider_id]['freshness_threshold'] ?? 2700);
        }
        $provider['incidents'] = \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider($provider);
        return $provider;
    }, array_values($ordered_tiles));
    $config['initial']['fetched_at'] = $fetched_at;
    $config['initial']['source']    = $source;
    $config['initial']['errors']    = $snapshot_errors;

    if (!$current_state && function_exists('lousy_outages_current_state_from_snapshot')) {
        $current_state = \lousy_outages_current_state_from_snapshot(['providers' => $config['initial']['providers']]);
    }
    $meta_counts = isset($current_state['meta']) && is_array($current_state['meta']) ? $current_state['meta'] : ['active_outage_count'=>0,'affected_provider_count'=>0,'signal_count'=>0,'unverified_count'=>0,'generated_at'=>gmdate('c')];
    $active_incident_records = array_values(array_filter((array) ($current_state['outages'] ?? []), 'is_array'));
    $affected_provider_ids = array_values(array_unique(array_filter(array_map(static function ($incident): string {
        return is_array($incident) ? sanitize_key((string) ($incident['provider_id'] ?? $incident['provider'] ?? '')) : '';
    }, $active_incident_records))));
    $meta_counts['active_outage_count'] = count($active_incident_records);
    $official_notice_count = 0;
    $affected_services = [];
    foreach ($active_incident_records as $incident) {
        if (!is_array($incident)) { continue; }
        $official_notice_count += max(1, (int)($incident['official_notice_count'] ?? count((array)($incident['official_notices'] ?? []))));
        foreach ((array)($incident['affected_services'] ?? []) as $service) { if (is_string($service) && '' !== trim($service)) { $affected_services[] = trim($service); } }
    }
    $meta_counts['official_incident_count'] = $official_notice_count;
    $meta_counts['affected_service_count'] = count(array_unique($affected_services));
    $meta_counts['affected_provider_count'] = count($affected_provider_ids);
    $meta_counts['current_official_provider_ids'] = $affected_provider_ids;
    $config['initial']['current_state'] = $current_state;
    $config['meta'] = $meta_counts;
    $config['initial']['meta'] = $meta_counts;

    $rss_url = home_url('/?feed=lousy_outages_status'); // Pretty /feed/lousy_outages_status/ works after a permalink flush, but the query form is safer.

    wp_localize_script('lousy-outages', 'LousyOutagesConfig', $config);
    wp_localize_script(
        'lousy-outages',
        'LOUSY_OUTAGES',
        [
            'restUrl' => esc_url_raw(rest_url('lousy/v1/refresh')),
            'nonce'   => $config['refreshNonce'] ?? wp_create_nonce('wp_rest'),
        ]
    );

    $format_datetime = static function (?string $iso): string {
        if (empty($iso)) {
            return '—';
        }
        $timestamp = strtotime($iso);
        if (!$timestamp) {
            return '—';
        }
        $format = get_option('date_format') . ' ' . get_option('time_format');
        return wp_date($format, $timestamp);
    };
    $is_no_incidents_message = static function (string $text): bool {
        $needle = strtolower(trim($text));
        if ('' === $needle) {
            return false;
        }
        $phrases = [
            'no active incidents',
            'no incidents',
            'no current incidents',
            'no known incidents',
            'no reported incidents',
            'all systems operational',
        ];
        foreach ($phrases as $phrase) {
            if (false !== strpos($needle, $phrase)) {
                return true;
            }
        }
        return false;
    };
    $is_generic_degraded_message = static function (string $text): bool {
        $needle = strtolower(trim($text));
        if ('' === $needle) {
            return true;
        }
        $phrases = [
            'service degradation reported.',
            'major outage reported.',
            'maintenance in progress.',
            'status temporarily unavailable.',
            'can’t verify status right now.',
        ];
        return in_array($needle, $phrases, true);
    };


    $normalize_card_text = static function (string $text, int $limit = 180): string {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\*\*\s*(Summary|Description|Symptoms?|Workaround|Impact|Status|Update|Updates?)\s*\*\*\s*:?/i', '', $text) ?? $text;
        $text = preg_replace('/\b(Summary|Description|Symptoms?|Workaround|Impact|Status|Update|Updates?)\s*:\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*[-–—]\s*(Summary|Description|Symptoms?|Workaround|Impact|Status|Update|Updates?)\s*:\s*/i', ' — ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B-*:_");
        if ('' === $text || strlen($text) <= $limit) {
            return $text;
        }
        $slice = substr($text, 0, max(0, $limit - 1));
        $space = strrpos($slice, ' ');
        if (false !== $space && $space > ($limit * 0.65)) {
            $slice = substr($slice, 0, $space);
        }
        return rtrim($slice, " \t\n\r\0\x0B.,;:-") . '…';
    };

    $incident_timestamp = static function (array $incident): int {
        foreach (['updated_at', 'updatedAt', 'started_at', 'startedAt', 'last_seen', 'first_seen'] as $field) {
            if (empty($incident[$field])) {
                continue;
            }
            $timestamp = strtotime((string) $incident[$field]);
            if ($timestamp) {
                return $timestamp;
            }
        }
        return 0;
    };

    $pick_lead_incident = static function (array $incidents) use ($incident_timestamp): ?array {
        if (!$incidents) {
            return null;
        }
        usort($incidents, static function ($left, $right) use ($incident_timestamp): int {
            $left_time  = is_array($left) ? $incident_timestamp($left) : 0;
            $right_time = is_array($right) ? $incident_timestamp($right) : 0;
            return $right_time <=> $left_time;
        });
        foreach ($incidents as $incident) {
            if (is_array($incident)) {
                return $incident;
            }
        }
        return null;
    };

    $build_incident_display = static function (array $incident, string $provider_name, string $fallback_status) use ($normalize_card_text, $format_datetime): array {
        $title = (string) ($incident['name'] ?? $incident['title'] ?? '');
        $summary = (string) ($incident['summary'] ?? $incident['body'] ?? $incident['message'] ?? $incident['description'] ?? '');
        if ('' === trim($title) && '' !== trim($summary)) {
            $title = $summary;
            $summary = '';
        }
        $title = $normalize_card_text($title, 118);
        $summary = $normalize_card_text($summary, 210);
        if ('' !== $summary && 0 === strcasecmp($summary, $title)) {
            $summary = '';
        }
        if ('' === $summary) {
            $summary = 'Latest official update is available from the provider status page.';
        }

        $status = (string) ($incident['impact'] ?? $incident['status'] ?? $fallback_status ?: 'Incident');
        $status = ucwords(str_replace(['_', '-'], ' ', $status));

        return [
            'provider' => $provider_name,
            'status'   => $status ?: 'Incident',
            'title'    => $title ?: 'Active incident',
            'summary'  => $summary,
            'updated'  => $format_datetime((string) ($incident['updated_at'] ?? $incident['updatedAt'] ?? $incident['started_at'] ?? $incident['startedAt'] ?? '')),
            'url'      => (string) ($incident['url'] ?? ''),
            'region'   => implode(', ', array_filter(array_map('strval', (array) ($incident['affected_services'] ?? [$incident['region_name'] ?? $incident['region'] ?? ''])))),
            'why'      => $normalize_card_text((string) ($incident['impact_description'] ?? $incident['impact'] ?? ''), 140),
        ];
    };

    $fetched_label = ('snapshot' === strtolower((string) $source))
        ? 'Provider status last refreshed:'
        : 'Provider status fetched:';
    ob_start();
    ?>
    <?php
    $trending_active = !empty($initial_trending['trending']);
    $trending_signals = isset($initial_trending['signals']) && is_array($initial_trending['signals'])
        ? array_filter(array_map('strval', $initial_trending['signals']))
        : [];
    $trending_generated = isset($initial_trending['generated_at']) ? (string) $initial_trending['generated_at'] : '';
    ?>
    <div
        class="lousy-outages"
        data-lo-endpoint="<?php echo esc_url($config['endpoint']); ?>"
        data-lo-source="<?php echo esc_attr($source); ?>"
        data-lo-snapshot="<?php echo esc_url($config['snapshotEndpoint'] ?? $snapshot_endpoint); ?>"
        data-lo-refresh-interval="<?php echo esc_attr((string) $refresh_interval); ?>"
    >
        <div class="lo-header">
            <div class="lo-actions lo-print-hide">
                <span class="lo-meta" aria-live="polite">
                    <span data-lo-fetched-label><?php echo esc_html($fetched_label); ?></span>
                    <strong data-lo-fetched data-lo-last-fetched><?php echo esc_html($format_datetime($fetched_at)); ?></strong>
                    <span data-lo-countdown>Auto-refresh ready</span>
                </span>
                <span class="lo-pill lo-pill--degraded" data-lo-degraded hidden>Auto-refresh degraded</span>
                <button type="button" class="lo-link" data-lo-refresh>Refresh status</button>
                <button type="button" class="lo-link lo-link--secondary" data-lo-export-csv>Export CSV</button>
                <button type="button" class="lo-link lo-link--secondary" data-lo-export-pdf>Save PDF</button>
                <a class="lo-link" href="<?php echo esc_url($rss_url); ?>" target="_blank" rel="noopener">Subscribe (RSS)</a>
            </div>
        </div>
        <div class="lo-mode-toggle lo-print-hide" data-lo-mode-toggle>
            <button type="button" class="lo-mode-toggle__button is-active" data-lo-mode="incidents" aria-pressed="true">Outage events (<?php echo esc_html((string) ($meta_counts['active_outage_count'] ?? 0)); ?>)</button>
            <button type="button" class="lo-mode-toggle__button" data-lo-mode="all" aria-pressed="false">Affected providers (<?php echo esc_html((string) ($meta_counts['affected_provider_count'] ?? 0)); ?>)</button><span class="lo-mode-toggle__note">Official notices (<?php echo esc_html((string) ($meta_counts['official_incident_count'] ?? 0)); ?>)</span>
        </div>
        <?php
        $fused_public = SignalEngine::summarize_fused_signals(120);
        $fused_public = is_array($fused_public) ? array_values(array_filter($fused_public, static function($r){ $c = strtolower((string)($r['classification'] ?? 'quiet')); return in_array($c,['watch','trending','hot'], true); })) : [];
        // Official fused signals are intentionally not rendered here; Active incidents is the public, non-duplicative official incident surface.
        // Community reports must stay quote-backed human/public reports only. Feed/synthetic/watch telemetry lanes render in dedicated sections later.
        $public_chatter = array_values(array_filter($fused_public, static fn($s) => (($s['signal_lane'] ?? '') === 'chatter')));
        $hnDiag = (array) get_option('lo_diag_hacker_news_chatter', []);
        $previewSample = isset($hnDiag['chatter_candidates_preview_sample']) && is_array($hnDiag['chatter_candidates_preview_sample']) ? $hnDiag['chatter_candidates_preview_sample'] : [];
        $rejectedCounts = (array) ($hnDiag['chatter_rejected_by_reason'] ?? []);
        if ($rejectedCounts === [] && $previewSample) {
            foreach ($previewSample as $sample) {
                $reasonCode = (string) ($sample['reject_reason'] ?? '');
                if ($reasonCode === '' || $reasonCode === 'accepted') {
                    continue;
                }
                $rejectedCounts[$reasonCode] = (int) ($rejectedCounts[$reasonCode] ?? 0) + 1;
            }
        }
        $rejectSummary = \SuzyEaston\LousyOutages\Sources\ChatterRejectionReasons::summarize_counts($rejectedCounts);
        $rejectedTotal = array_sum(array_map(static fn($row): int => (int) ($row['count'] ?? 0), $rejectSummary));
        $promotedTotal = count($public_chatter);
        $checkedTotal = (int) ($hnDiag['chatter_rows_attempted'] ?? 0);
        if ($checkedTotal <= 0 && $previewSample) {
            $checkedTotal = count($previewSample);
        }
        if ($checkedTotal <= 0) {
            $checkedTotal = $promotedTotal + $rejectedTotal;
        }
        $publicDiag = (array) get_option('lo_diag_public_chatter', []);
        $cfDiag = (array) get_option('lo_diag_cloudflare_radar', []);
        $watchCandidates = isset($publicDiag['watch_candidates']) && is_array($publicDiag['watch_candidates']) ? $publicDiag['watch_candidates'] : [];
        $watchCandidateCount = (int) ($publicDiag['watch_candidate_count'] ?? count($watchCandidates));
        $sourceStatuses = isset($publicDiag['source_statuses']) && is_array($publicDiag['source_statuses']) ? $publicDiag['source_statuses'] : [];
        $sourceStatusRows = $sourceStatuses ?: [
            'hacker_news_chatter' => ['label' => 'HN chatter', 'status' => !empty(get_option('lo_hn_chatter_enabled', '1')) ? 'enabled' : 'disabled', 'lane'=>'public_chatter'],
            'public_chatter_bluesky' => ['label' => 'Bluesky', 'status' => !empty(get_option('lousy_outages_public_chatter_bluesky_enabled', '1')) ? (!empty($publicDiag['direct_sources_enabled']) ? 'enabled' : 'blocked_by_safe_default') : 'disabled', 'lane'=>'public_chatter'],
            'public_chatter_mastodon' => ['label' => 'Mastodon', 'status' => !empty(get_option('lousy_outages_public_chatter_mastodon_enabled', '1')) ? (!empty($publicDiag['direct_sources_enabled']) ? 'enabled' : 'blocked_by_safe_default') : 'disabled', 'lane'=>'public_chatter'],
            'public_chatter_gdelt' => ['label' => 'GDELT open web', 'status' => !empty(get_option('lousy_outages_public_chatter_gdelt_enabled', '1')) ? (!empty($publicDiag['direct_sources_enabled']) ? 'enabled' : 'blocked_by_safe_default') : 'disabled', 'lane'=>'open_web'],
            'cloudflare_radar' => ['label' => 'Cloudflare Radar', 'status' => !empty($cfDiag['configured']) ? 'configured' : 'not_configured', 'lane'=>'internet_health'],
        ];
        $sourcesEnabledCount = 0;
        $sourcesBlockedCount = 0;
        foreach ($sourceStatusRows as $sourceStatusRow) {
            $statusValue = (string) ($sourceStatusRow['status'] ?? 'disabled');
            if (in_array($statusValue, ['enabled', 'configured'], true)) { $sourcesEnabledCount++; }
            if (in_array($statusValue, ['disabled', 'blocked_by_safe_default', 'not_configured', 'cooldown', 'rate_limited', 'budget_skipped'], true)) { $sourcesBlockedCount++; }
        }
        $officialCorroborationRows = isset($publicDiag['official_incident_corroboration']) && is_array($publicDiag['official_incident_corroboration']) ? $publicDiag['official_incident_corroboration'] : [];
        $canonicalIncidentProviders = [];
        foreach ((array) $tiles as $tile) {
            if (!is_array($tile)) { continue; }
            $pid = provider_identity($tile);
            $active = class_exists('\SuzyEaston\LousyOutages\Summary') && method_exists('\SuzyEaston\LousyOutages\Summary', 'current_incidents_for_provider')
                ? \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider($tile)
                : ((isset($tile['incidents']) && is_array($tile['incidents'])) ? $tile['incidents'] : []);
            if ($pid !== '' && !empty($active)) { $canonicalIncidentProviders[$pid] = true; }
        }
        $officialCorroborationRows = array_values(array_filter($officialCorroborationRows, static function ($row) use ($canonicalIncidentProviders): bool {
            if (!is_array($row)) { return false; }
            $pid = sanitize_key((string) ($row['provider_id'] ?? $row['provider'] ?? ''));
            return $pid !== '' && isset($canonicalIncidentProviders[$pid]);
        }));
        $canadianWatchRows = isset($publicDiag['canadian_infrastructure_watchlist']) && is_array($publicDiag['canadian_infrastructure_watchlist']) ? $publicDiag['canadian_infrastructure_watchlist'] : [];
        $statusLabel = static function($status): string {
            $status = (string) $status;
            if ($status === 'blocked_by_safe_default') { return 'blocked'; }
            if ($status === 'budget_skipped') { return 'budget skipped'; }
            if ($status === 'not_configured') { return 'not configured'; }
            if ($status === 'rate_limited') { return 'rate limited'; }
            return str_replace('_', ' ', $status);
        };
        $sourceReason = static function(array $row): string {
            $status = (string) ($row['status'] ?? 'disabled');
            $reasons = array_values(array_unique(array_filter(array_map('strval', (array) ($row['reasons'] ?? [])))));
            if ($status === 'enabled' || $status === 'configured') { return 'Budgeted · available'; }
            if ($status === 'cooldown') { return (($row['lane'] ?? '') === 'open_web') ? 'Open web cooling down' : 'Temporarily paused'; }
            if ($status === 'rate_limited') { return (($row['lane'] ?? '') === 'open_web') ? 'Open web cooling down' : 'Provider asked slow-down'; }
            if ($status === 'budget_skipped') { return 'Per-run budget spent'; }
            if ($status === 'blocked_by_safe_default') { return 'Direct source gate disabled'; }
            if ($status === 'not_configured') { return 'External telemetry'; }
            if ($status === 'disabled') { return 'Disabled by admin'; }
            if ($reasons) { return ucfirst(str_replace('_', ' ', (string) $reasons[0])); }
            return 'No extra diagnostics';
        };
        $statusBadge = 'Official radar only';
        $radarBrief = 'Official radar only. Public sources were checked, but no credible field reports were promoted.';
        if ($promotedTotal > 0) {
            $statusBadge = 'Public corroboration';
            $radarBrief = 'Public corroboration active. Promoted field reports are still conservative and unverified.';
        } elseif ($watchCandidateCount > 0) {
            $statusBadge = 'Public watch';
            $radarBrief = 'Public watch active. Mentions exist, but they remain below promotion threshold.';
        } elseif ($sourcesBlockedCount > 0 && $sourcesEnabledCount === 0) {
            $statusBadge = 'Sources limited';
            $radarBrief = 'Official radar primary. Public source checks are currently limited by configuration or budgets.';
        }
        $summaryMetrics = [
            ['Checked', $checkedTotal, $checkedTotal > 0 ? 'items scanned' : 'pending'],
            ['Promoted', $promotedTotal, $promotedTotal > 0 ? 'field reports' : 'none'],
            ['Watch', $watchCandidateCount, $watchCandidateCount > 0 ? 'below threshold' : 'clear'],
            ['Rejected', $rejectedTotal, $rejectedTotal > 0 ? 'filtered' : 'none'],
            ['Sources active', $sourcesEnabledCount, 'ready'],
            ['Limited', $sourcesBlockedCount, $sourcesBlockedCount > 0 ? 'needs attention' : 'none'],
        ];
        ?>
        <div class="lo-incidents" data-lo-incidents>
            <section class="lo-section lo-section--incidents" id="active-incidents" data-lo-section="incidents">
                <div class="lo-section__head">
                    <h3 class="lo-block-title">Active incidents</h3>
                </div>
                <div class="lo-grid lo-grid--section" data-lo-section-grid="incidents">
                    <?php $rendered_incident_providers = 0; ?>
                    <?php foreach ($ordered_tiles as $tile) :
                        if (!is_array($tile)) { continue; }
                        $slug = provider_identity($tile);
                        $incidents = is_array($tile['incidents'] ?? null) ? \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider($tile) : [];
                        if ('' === $slug || empty($incidents)) { continue; }
                        $rendered_incident_providers++;
                        $provider_name = (string) ($tile['name'] ?? $tile['provider'] ?? ucfirst($slug));
                        $lead_active_incident = $pick_lead_incident($incidents);
                        $lead_incident_display = $lead_active_incident ? $build_incident_display($lead_active_incident, $provider_name, (string) ($tile['status_label'] ?? 'Incident')) : null;
                        $last_checked = $format_datetime($tile['checked_at'] ?? $tile['fetched_at'] ?? $tile['updated_at'] ?? null);
                        $last_official = $format_datetime($tile['last_official_update'] ?? ($lead_active_incident['last_official_update'] ?? $lead_active_incident['updated_at'] ?? $lead_active_incident['updatedAt'] ?? null));
                        $status_url = (string) ($tile['url'] ?? $tile['link'] ?? '');
                    $category = (string) ($tile['category'] ?? ($providers_config[$slug]['category'] ?? 'other'));
                    $source_type = (string) ($tile['source_type'] ?? ($providers_config[$slug]['source_type'] ?? 'unknown'));
                    ?>
                        <article id="provider-<?php echo esc_attr($slug); ?>" class="lo-card lo-card--incident" data-provider-id="<?php echo esc_attr($slug); ?>">
                            <div class="lo-head">
                                <h3 class="lo-title"><?php echo esc_html($provider_name); ?></h3>
                                <span class="lo-pill status--degraded" data-lo-badge><?php echo esc_html((string) ($tile['status_label'] ?? 'Incident')); ?></span>
                            </div>
                            <p class="lo-card-kicker"><?php echo esc_html(count($incidents) . ' active ' . (count($incidents) === 1 ? 'incident' : 'incidents')); ?></p>
                            <h4 id="incident-<?php echo esc_attr(sanitize_title((string)($lead_active_incident['id'] ?? $slug))); ?>" class="lo-incident-title" data-lo-message><?php echo esc_html((string) ($lead_incident_display['title'] ?? 'Active incident')); ?></h4>
                            <p class="lo-message" data-lo-summary><?php echo esc_html((string) ($lead_incident_display['summary'] ?? 'Latest official update is available from the provider status page.')); ?></p>
                            <dl class="lo-incident-facts">
                                <div><dt>Affected service / region</dt><dd><?php echo esc_html((string) ($lead_incident_display['region'] ?: 'Provider status page did not specify.')); ?></dd></div>
                                <div><dt>Lifecycle</dt><dd><?php echo esc_html((string) ($lead_incident_display['status'] ?? ($tile['status_label'] ?? 'Incident'))); ?></dd></div>
                                <div><dt>Latest official update</dt><dd><?php echo esc_html($last_official); ?></dd></div>
                                <div><dt>Checked</dt><dd><?php echo esc_html($last_checked); ?></dd></div>
                                <div><dt>Source</dt><dd><?php echo esc_html(ucfirst($source_type)); ?></dd></div>
                            </dl>
                            <?php if (!empty($lead_incident_display['why']) && !\SuzyEaston\LousyOutages\PublicCopy::should_suppress('minor', ['severity'=>$lead_incident_display['status'], 'message'=>$lead_incident_display['why']])) : ?><p class="lo-why"><strong>Why this matters:</strong> <?php echo esc_html((string) $lead_incident_display['why']); ?></p><?php endif; ?>
                            <?php if (count($incidents) > 1) : ?>
                                <details class="lo-inc-details"><summary><?php echo esc_html('View ' . count($incidents) . ' incidents'); ?></summary>
                                    <ul class="lo-inc-list">
                                        <?php foreach ($incidents as $incident) : $incident_display = $build_incident_display((array) $incident, $provider_name, (string) ($tile['status_label'] ?? 'Incident')); ?>
                                            <li class="lo-inc-item"><p class="lo-inc-title"><?php echo esc_html((string) $incident_display['title']); ?></p><p class="lo-inc-meta"><?php echo esc_html('Last official update: ' . (string) $incident_display['updated']); ?></p><?php if (!empty($incident_display['url'])) : ?><a class="lo-status-link" href="<?php echo esc_url((string) $incident_display['url']); ?>" target="_blank" rel="noopener">View incident</a><?php endif; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                            <?php if ('' !== $status_url) : ?><a class="lo-status-link" data-lo-status-url href="<?php echo esc_url($status_url); ?>" target="_blank" rel="noopener">Official status page</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (($meta_counts['active_outage_count'] ?? 0) > 0 && 0 === $rendered_incident_providers) : ?>
                        <p class="lo-history__error">Active incidents are present in the saved snapshot, but no affected provider cards could be rendered.</p>
                    <?php elseif (0 === (int) ($meta_counts['active_outage_count'] ?? 0)) : ?>
                        <p class="lo-history__empty">No active provider incidents in the saved snapshot.</p>
                    <?php endif; ?>
                </div>
            </section>
            <section class="lo-section lo-section--collapsible" data-lo-section="signals">
                <div class="lo-section__head">
                    <button type="button" class="lo-section__toggle" data-lo-section-toggle="signals" aria-expanded="false">Signals (<?php echo esc_html((string) ($meta_counts['signal_count'] ?? 0)); ?>)</button>
                </div>
                <div class="lo-grid lo-grid--section" data-lo-section-grid="signals" hidden></div>
            </section>
            <section class="lo-section lo-section--collapsible" data-lo-section="unverified">
                <div class="lo-section__head">
                    <button type="button" class="lo-section__toggle" data-lo-section-toggle="unverified" aria-expanded="false">Can’t verify (<?php echo esc_html((string) ($meta_counts['unverified_count'] ?? 0)); ?>)</button>
                </div>
                <div class="lo-grid lo-grid--section" data-lo-section-grid="unverified" hidden></div>
            </section>
        </div>
        <?php if (false) : ?><section class="lo-corroboration-radar lo-chatter-scanner" data-lo-chatter-scanner>
            <div class="lo-corroboration-radar__head">
                <div>
                    <h3 class="lo-block-title">CORROBORATION RADAR</h3>
                    <p class="lo-corroboration-radar__muted">Official feeds confirm provider-declared incidents. Public/social and open-web sources are unconfirmed corroboration only. Telemetry can indicate network-level disruption but does not prove application outages.</p>
                </div>
                <span class="lo-corroboration-radar__badge"><?php echo esc_html($statusBadge); ?></span>
            </div>

            <p class="lo-corroboration-radar__brief"><?php echo esc_html($radarBrief); ?></p>

            <div class="lo-corroboration-radar__summary" aria-label="Community reports scan summary">
                <?php foreach ($summaryMetrics as $metric) : ?>
                    <div class="lo-corroboration-radar__metric"><span><?php echo esc_html((string) $metric[0]); ?></span><strong><?php echo esc_html((string) $metric[1]); ?></strong><small><?php echo esc_html((string) $metric[2]); ?></small></div>
                <?php endforeach; ?>
            </div>

            <div class="lo-corroboration-radar__sources" aria-label="Source lane status">
                <?php foreach ($sourceStatusRows as $sourceStatusRow) : $sourceStatus = (string) ($sourceStatusRow['status'] ?? 'disabled'); $lastCode = (int) ($sourceStatusRow['last_response_code'] ?? 0); $cooldownUntil = (string) ($sourceStatusRow['cooldown_until'] ?? ''); ?>
                    <article class="lo-corroboration-radar__source lo-corroboration-radar__source--<?php echo esc_attr(sanitize_html_class($sourceStatus)); ?>">
                        <div class="lo-corroboration-radar__source-head"><strong><?php echo esc_html((string) ($sourceStatusRow['label'] ?? 'Source')); ?></strong><span class="lo-corroboration-radar__status-badge"><?php echo esc_html($statusLabel($sourceStatus)); ?></span></div>
                        <p><strong><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($sourceStatusRow['lane'] ?? 'source')))); ?>:</strong> <?php echo esc_html($sourceReason((array) $sourceStatusRow)); ?></p>
                        <?php if ($lastCode > 0 || $cooldownUntil !== '') : ?>
                            <small><?php echo $lastCode > 0 ? esc_html('HTTP ' . $lastCode) : ''; ?><?php echo ($lastCode > 0 && $cooldownUntil !== '') ? esc_html(' · ') : ''; ?><?php echo $cooldownUntil !== '' ? esc_html('Cooldown until ' . $format_datetime($cooldownUntil)) : ''; ?></small>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="lo-corroboration-radar__incidents" aria-label="Official incident public corroboration">
                <div class="lo-corroboration-radar__section-title"><h4>Official Incident Corroboration</h4><span>Active official incidents checked against available public/open-web/telemetry lanes</span></div>
                <?php if (!empty($officialCorroborationRows)) : ?>
                    <div class="lo-corroboration-radar__incident-grid">
                        <?php foreach (array_slice($officialCorroborationRows, 0, 8) as $row) : $sourcesChecked = array_values(array_filter(array_map('strval', (array) ($row['sources_checked'] ?? [])))); ?>
                            <article class="lo-corroboration-radar__incident">
                                <h5><?php echo esc_html((string) ($row['provider_name'] ?? $row['provider_id'] ?? 'Provider')); ?></h5>
                                <div class="lo-corroboration-radar__incident-badges">
                                    <span class="lo-corroboration-radar__status-badge lo-corroboration-radar__status-badge--official">Official: <?php echo esc_html((string) ($row['official_status'] ?? 'active')); ?></span>
                                    <span class="lo-corroboration-radar__status-badge lo-corroboration-radar__status-badge--public">Result: <?php echo esc_html((string) ($row['result_label'] ?? 'official only')); ?></span>
                                </div>
                                <p><span>Watch</span><strong><?php echo esc_html((string) ($row['watch_candidates'] ?? 0)); ?></strong></p>
                                <div class="lo-corroboration-radar__chips" aria-label="Sources checked">
                                    <?php foreach ($sourcesChecked ?: ['None'] as $sourceChecked) : ?><span><?php echo esc_html($sourceChecked); ?></span><?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="lo-corroboration-radar__muted">No active official incidents were available to seed public corroboration in the last scan.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($canadianWatchRows)) : ?>
                <div class="lo-corroboration-radar__canada-watch">
                    <div class="lo-corroboration-radar__section-title"><h4>Canadian Infrastructure Watch</h4><span>Watchlist armed</span></div>
                    <div class="lo-corroboration-radar__watch-grid">
                        <?php foreach ($canadianWatchRows as $watchRow) : $providersShown = array_values(array_map('strval', (array) ($watchRow['providers'] ?? []))); $more = max(0, (int) ($watchRow['count'] ?? count($providersShown)) - 3); ?>
                            <article>
                                <strong><?php echo esc_html((string) ($watchRow['label'] ?? $watchRow['category'] ?? 'Watchlist')); ?></strong>
                                <span><?php echo esc_html((string) ($watchRow['count'] ?? 0)); ?> watched</span>
                                <small><?php echo esc_html(implode(', ', array_slice($providersShown, 0, 3)) . ($more > 0 ? ' +' . $more . ' more' : '')); ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($watchCandidates)) : ?>
                <div class="lo-corroboration-radar__watch-candidates">
                    <h4>Watch candidates</h4>
                    <p class="lo-corroboration-radar__muted">Unconfirmed volume/context hints below promotion thresholds.</p>
                    <div>
                        <?php foreach (array_slice($watchCandidates, 0, 6) as $candidate) : ?>
                            <span><?php echo esc_html((string) ($candidate['provider_name'] ?? $candidate['provider_id'] ?? 'Provider')); ?> · <?php echo esc_html((string) ($candidate['count'] ?? 0)); ?> <?php echo esc_html((string) ($candidate['source_label'] ?? $candidate['source'] ?? 'source')); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($public_chatter) : ?>
                <div class="lo-chatter-scanner__cards">
                    <?php foreach (array_slice($public_chatter, 0, 6) as $sig) : $sourceLabel = trim((string)($sig['evidence_source_label'] ?? $sig['source'] ?? '')); $sourceUrl = trim((string)($sig['evidence_url'] ?? $sig['url'] ?? '')); ?>
                        <article class="lo-card">
                            <div class="lo-head"><h4 class="lo-title"><?php echo esc_html((string)($sig['provider_name'] ?? $sig['provider_id'] ?? 'Provider')); ?></h4><span class="lo-pill">RUMOUR RADAR</span></div>
                            <p class="lo-summary">Unconfirmed public report. Needs corroboration.</p>
                            <p class="lo-message"><?php echo esc_html((string)($sig['message'] ?? 'Needs corroboration from official/synthetic signals.')); ?></p>
                            <p class="lo-card-meta">Source: <?php echo esc_html($sourceLabel ?: 'Community reports'); ?><?php if ($sourceUrl !== '') : ?> · <a class="lo-link" href="<?php echo esc_url($sourceUrl); ?>" target="_blank" rel="noopener">View source</a><?php endif; ?> · Observed: <?php echo esc_html($format_datetime($sig['observed_at'] ?? null)); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="lo-corroboration-radar__empty">
                    <h4><?php echo ($checkedTotal > 0 || !empty($rejectSummary) || !empty($previewSample)) ? esc_html('Community reports scan complete') : esc_html('Community reports scanner online'); ?></h4>
                    <p><?php echo ($checkedTotal > 0 || !empty($rejectSummary) || !empty($previewSample)) ? esc_html('No credible field reports promoted. Official radar only.') : esc_html('No rumour radar hits or diagnostics available yet.'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($rejectSummary)) : ?>
                <div class="lo-corroboration-radar__diagnostics" aria-label="Rejected community reports categories">
                    <h4>Filtered chatter</h4>
                    <p class="lo-corroboration-radar__diagnostic-summary">Filtered chatter: mostly historical/resolved and not actionable.</p>
                    <div class="lo-corroboration-radar__diagnostic-grid">
                        <?php foreach (array_slice($rejectSummary, 0, 2) as $reasonRow) : ?>
                            <div class="lo-corroboration-radar__reason"><span><?php echo esc_html((string) ($reasonRow['label'] ?? 'Filtered chatter')); ?></span><strong><?php echo esc_html((string) ($reasonRow['count'] ?? 0)); ?></strong><small><?php echo esc_html((string) ($reasonRow['description'] ?? 'Filtered chatter that did not meet current-signal quality checks.')); ?></small></div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($rejectSummary) > 2) : ?>
                        <details><summary>Show filter diagnostics</summary>
                            <div class="lo-corroboration-radar__diagnostic-grid">
                                <?php foreach (array_slice($rejectSummary, 2) as $reasonRow) : ?>
                                    <div class="lo-corroboration-radar__reason"><span><?php echo esc_html((string) ($reasonRow['label'] ?? 'Filtered chatter')); ?></span><strong><?php echo esc_html((string) ($reasonRow['count'] ?? 0)); ?></strong><small><?php echo esc_html((string) ($reasonRow['description'] ?? 'Filtered chatter that did not meet current-signal quality checks.')); ?></small></div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <footer class="lo-corroboration-radar__footer">Scanner idle. Official radar remains primary.</footer>
        </section>
        <?php endif; ?>
        <?php if (LO_SHOW_WIDESPREAD) : ?>
        <div class="lo-trending" data-lo-trending<?php echo $trending_active ? '' : ' hidden'; ?> data-lo-trending-generated="<?php echo esc_attr($trending_generated); ?>" aria-live="assertive">
            <span class="lo-trending__icon" aria-hidden="true">⚡</span>
            <div class="lo-trending__body">
                <strong data-lo-trending-text>Potential widespread issues detected — check affected providers</strong>
                <span class="lo-trending__reasons" data-lo-trending-reasons<?php echo $trending_signals ? '' : ' hidden'; ?>><?php echo esc_html($trending_signals ? 'Signals: ' . implode(', ', array_slice($trending_signals, 0, 6)) : ''); ?></span>
            </div>
        </div>
        <?php endif; ?>
        <div class="lo-hero" data-lo-hero hidden>
            <article class="lo-card lo-card--hero">
                <div class="lo-head">
                    <h3 class="lo-title">No confirmed current incidents</h3>
                </div>
                <p class="lo-summary">Official provider feeds are checked by the scheduled refresh; emerging signals and verification delays appear above when present.</p>
                <p class="lo-card-meta">Last checked: <span data-lo-hero-time>—</span></p>
            </article>
        </div>
        <section class="lo-history" data-lo-history>
            <div class="lo-history__heading">
                <div>
                    <h3 class="lo-block-title">Recent incident history</h3>
                    <p class="lo-history__meta">Official provider incidents plus separately labelled community reports when available.</p>
                </div>
                <div class="lo-history__controls lo-print-hide">
                    <label>
                        <input type="checkbox" data-lo-history-important checked>
                        <span>Show only major incidents</span>
                    </label>
                    <label>
                        <span class="sr-only">History window</span>
                        <select data-lo-history-window>
                            <option value="1">24h</option>
                            <option value="7">7d</option>
                            <option value="30" selected>30d</option>
                            <option value="90">90d</option>
                            <option value="365">1y</option>
                            <option value="0">All retained</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="lo-history__body">
                <p class="lo-history__meta">History is loaded in small pages from retained official incidents; community reports remain labelled unconfirmed when present.</p>
                <div class="lo-history__charts" data-lo-history-charts hidden></div>
                <ol class="lo-history__list" data-lo-history-list>
                    <li class="lo-history__item lo-history__item--placeholder">Loading incidents…</li>
                </ol>
                <p class="lo-history__empty" data-lo-history-empty hidden>No service incidents in the past 30 days.</p>
                <p class="lo-history__error" data-lo-history-error hidden>Incident history could not be loaded. Current status is still available. <button type="button" class="lo-history__retry" data-lo-history-retry>Retry</button></p>
            </div>
        </section>
        <?php
        $lo_priority_service_tiles = [];
        $lo_operational_service_tiles = [];
        foreach ($ordered_tiles as $lo_service_tile) {
            if (!is_array($lo_service_tile)) { continue; }
            $lo_service_incidents = \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider($lo_service_tile);
            $lo_raw_status = strtolower((string) ($lo_service_tile['status'] ?? $lo_service_tile['stateCode'] ?? 'unknown'));
            $lo_tile_kind = strtolower((string) ($lo_service_tile['tile_kind'] ?? $lo_service_tile['tileKind'] ?? ''));
            $lo_has_error = !empty($lo_service_tile['error']) || in_array((string) ($lo_service_tile['verification_status'] ?? ''), ['failed','delayed','unknown'], true);
            $lo_is_operational = empty($lo_service_incidents) && !$lo_has_error && !in_array($lo_raw_status, ['degraded','major','outage','maintenance'], true) && 'signal' !== $lo_tile_kind && 'unknown' !== $lo_raw_status && 'manual' !== $lo_tile_kind;
            if ($lo_is_operational) { $lo_operational_service_tiles[] = $lo_service_tile; } else { $lo_priority_service_tiles[] = $lo_service_tile; }
        }
        $lo_render_service_row = static function (array $tile) use ($format_datetime, $providers_config): void {
            $slug = provider_identity($tile);
            if ('' === $slug) { return; }
            $incidents = \SuzyEaston\LousyOutages\Summary::current_incidents_for_provider($tile);
            $raw_status = strtolower((string) ($tile['status'] ?? $tile['stateCode'] ?? 'unknown'));
            $tile_kind = strtolower((string) ($tile['tile_kind'] ?? $tile['tileKind'] ?? ''));
            $has_error = !empty($tile['error']) || in_array((string) ($tile['verification_status'] ?? ''), ['failed','delayed','unknown'], true);
            if (!empty($incidents)) { $state_label = 'Incident'; }
            elseif ('signal' === $tile_kind || in_array($raw_status, ['degraded','major','outage','maintenance','partial_outage','degraded_performance'], true)) { $state_label = 'Degraded'; }
            elseif ($has_error) { $state_label = 'Verification delayed'; }
            elseif ('manual' === $tile_kind || 'unknown' === $raw_status || '' === $raw_status) { $state_label = 'Status unavailable'; }
            else { $state_label = 'Operational'; }
            $state_class = sanitize_html_class(strtolower(str_replace(' ', '-', $state_label)));
            $last_checked = $format_datetime($tile['checked_at'] ?? $tile['fetched_at'] ?? $tile['updated_at'] ?? null);
            $status_url = (string) ($tile['url'] ?? $tile['link'] ?? '');
            $category = (string) ($tile['category'] ?? ($providers_config[$slug]['category'] ?? 'other'));
            $source_type = (string) ($tile['source_type'] ?? ($providers_config[$slug]['source_type'] ?? 'unknown'));
            ?>
            <div class="lo-services__row lo-services__row--<?php echo esc_attr($state_class); ?>" data-lo-provider-row="<?php echo esc_attr($slug); ?>" data-lo-provider-name="<?php echo esc_attr(strtolower((string) ($tile['name'] ?? $slug))); ?>" data-lo-provider-category="<?php echo esc_attr($category); ?>" data-lo-provider-state="<?php echo esc_attr($state_class); ?>" role="row"><span role="cell"><strong><?php echo esc_html((string) ($tile['name'] ?? ucfirst($slug))); ?></strong></span><span role="cell"><span class="lo-pill status--<?php echo esc_attr($state_class); ?>"><?php echo esc_html($state_label); ?></span></span><span role="cell"><?php echo esc_html(ucfirst($category) . ' / ' . $source_type); ?></span><span role="cell"><?php echo esc_html($last_checked); ?></span><span role="cell"><?php if ('' !== $status_url) : ?><a class="lo-status-link" href="<?php echo esc_url($status_url); ?>" target="_blank" rel="noopener">Open</a><?php else : ?>—<?php endif; ?></span></div>
            <?php
        };
        ?>
        <section id="monitored-services" class="lo-services" data-lo-services>
            <div class="lo-section__head"><h3 class="lo-block-title">Monitored services</h3><p class="lo-history__meta">Incident, degraded and verification-delayed providers appear first. Operational services are tucked away until you need them.</p></div>
            <div class="lo-history__controls lo-print-hide" aria-label="Provider filters">
                <label><span class="sr-only">Search providers</span><input type="search" data-lo-provider-search placeholder="Search services"></label>
                <label><span class="sr-only">Category</span><select data-lo-provider-category><option value="">All categories</option><option value="ai">AI</option><option value="cloud">Cloud</option><option value="development">Development</option><option value="communications">Communications</option><option value="creative">Creative</option></select></label>
                <label><span class="sr-only">State</span><select data-lo-provider-state><option value="">All states</option><option value="operational">Operational</option><option value="incident">Incident</option><option value="degraded">Degraded</option><option value="verification-delayed">Verification delayed</option></select></label>
            </div>
            <details class="lo-settings lo-print-hide" data-lo-settings>
            <summary class="lo-block-title">Choose monitored providers</summary>
            <p class="lo-settings__hint">Pick which providers appear below. Preferences stay on this browser only.</p>
            <div class="lo-settings__actions">
                <button type="button" class="lo-settings__button" data-lo-provider-select="all">Select all</button>
                <button type="button" class="lo-settings__button lo-settings__button--ghost" data-lo-provider-select="none">Select none</button>
            </div>
            <div class="lo-settings__options">
                <?php foreach ($ordered_tiles as $tile) :
                    $slug = provider_identity($tile);
                    if ('' === $slug) {
                        continue;
                    }
                    $provider_label = $tile['name'] ?? ucfirst($slug);
                    ?>
                    <label class="lo-checkbox">
                        <input type="checkbox" data-lo-provider-toggle value="<?php echo esc_attr($slug); ?>" checked>
                        <span><?php echo esc_html($provider_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            </details>
        </section>
        <div class="lo-loading lo-print-hide" data-lo-loading<?php echo $ordered_tiles ? ' hidden' : ''; ?> role="status">
            <span class="lo-loading__spinner" aria-hidden="true"></span>
            <span class="lo-loading__text">Dialing interstellar relays…</span>
        </div>
        <div class="lo-all" data-lo-all>
            <div class="lo-services__table" data-lo-grid role="table" aria-label="Monitored provider states needing attention">
                <div class="lo-services__row lo-services__row--head" role="row"><span>Provider</span><span>State</span><span>Category / source</span><span>Last checked</span><span>Status page</span></div>
                <?php foreach ($lo_priority_service_tiles as $tile) { $lo_render_service_row($tile); } ?>
            </div>
            <details class="lo-operational-disclosure" data-lo-operational-services>
                <summary><?php echo esc_html('Show ' . count($lo_operational_service_tiles) . ' operational services'); ?></summary>
                <div class="lo-services__table lo-services__table--operational" role="table" aria-label="Operational monitored provider states">
                    <div class="lo-services__row lo-services__row--head" role="row"><span>Provider</span><span>State</span><span>Category / source</span><span>Last checked</span><span>Status page</span></div>
                    <?php foreach ($lo_operational_service_tiles as $tile) { $lo_render_service_row($tile); } ?>
                </div>
            </details>
        </div>
        <?php echo render_subscribe_shortcode(); ?>
    </div>
    <?php
    $output = (string) ob_get_clean();

    if ($cache_key) {
        set_transient($cache_key, [
            'html'   => $output,
            'config' => $config,
        ], 90);
    }

    return $output;
}




function render_subscribe_shortcode(): string {
    $endpoint = esc_url_raw(rest_url('lousy-outages/v1/subscribe'));
    $nonce    = wp_create_nonce('lousy_outages_subscribe');
    $rss_url  = esc_url(home_url('/?feed=lousy_outages_status'));
    $lyric_lines = [];
    foreach (\lo_lyric_fragment_bank() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $fragment = isset($entry['fragment']) ? trim((string) $entry['fragment']) : '';
        $answer   = isset($entry['answer']) ? trim((string) $entry['answer']) : '';
        if ('' === $fragment && '' === $answer) {
            continue;
        }
        $lyric_lines[] = [
            'fragment' => $fragment,
            'answer'   => $answer,
        ];
    }

    $lyric_lines = apply_filters('lo_subscribe_lyric_lines', $lyric_lines);
    if (!is_array($lyric_lines)) {
        $lyric_lines = [];
    }

    $challenge_phrase = '';
    $challenge_choices = [];
    foreach ($lyric_lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $answer = isset($line['answer']) ? trim((string) $line['answer']) : '';
        if ('' === $answer) {
            continue;
        }
        $challenge_choices[] = $answer;
    }

    if (!empty($challenge_choices)) {
        $random_key = array_rand($challenge_choices);
        if (isset($challenge_choices[$random_key])) {
            $challenge_phrase = $challenge_choices[$random_key];
        }
    }
    $form_uid  = uniqid('lo-subscribe-');
    $email_id  = $form_uid . '-email';
    $challenge_id = $form_uid . '-challenge';
    $challenge_hint_id = $challenge_id . '-hint';
    $providers = Providers::enabled();
    if (empty($providers)) {
        $providers = Providers::list();
    }

    ob_start();
    ?>
    <div class="lo-subscribe" data-lo-subscribe>
        <h2 class="lo-subscribe__title">Get outage alerts</h2>
        <p class="lo-subscribe__intro">Choose the providers you rely on and get the alert before your group chat becomes an incident-response channel. Free public current status, recent history and basic <a href="<?php echo $rss_url; ?>" target="_blank" rel="noopener">RSS access</a> remain available.</p>
        <details class="lo-subscribe__details"><summary class="lo-subscribe__summary">Choose alert preferences</summary><form class="lo-subscribe__form" method="post" action="<?php echo esc_url($endpoint); ?>" data-lo-subscribe-form>
            <label class="lo-subscribe__label" for="<?php echo esc_attr($email_id); ?>">
                <span>Email</span>
                <input
                    id="<?php echo esc_attr($email_id); ?>"
                    class="lo-subscribe__input"
                    type="email"
                    name="email"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                />
            </label>
            <fieldset class="lo-subscribe__fieldset">
                <legend class="lo-subscribe__legend">Alert preferences</legend>
                <p class="lo-subscribe__note">Pick selected providers now; billing or supporter status can be added later without changing these consent preferences.</p>
                <div class="lo-subscribe__provider-grid">
                    <?php foreach ($providers as $provider) : $provider_id = sanitize_key((string) ($provider['id'] ?? '')); if ('' === $provider_id) { continue; } ?>
                        <label class="lo-subscribe__checkbox">
                            <input type="checkbox" name="providers[]" value="<?php echo esc_attr($provider_id); ?>" checked />
                            <span><?php echo esc_html((string) ($provider['name'] ?? $provider_id)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="lo-subscribe__fieldset lo-subscribe__prefs">
                <legend class="lo-subscribe__legend">Delivery and quietness</legend>
                <label class="lo-subscribe__checkbox"><input type="checkbox" name="realtime_alerts" value="1" checked /> <span>Urgent incident alerts</span></label>
                <label class="lo-subscribe__checkbox"><input type="checkbox" name="daily_digest" value="1" /> <span>Optional daily digest</span></label>
                <label class="lo-subscribe__checkbox"><input type="checkbox" name="newsletter" value="1" /> <span>Product updates / supporter news</span></label>
                <p class="lo-subscribe__note">We’ll only use this to send the outage updates you request. You can unsubscribe anytime.</p>
            </fieldset>
            <div class="lo-subscribe__label lo-subscribe__challenge">
                <p class="lo-subscribe__prompt">Type the Steve Jobs line shown below to prove you&rsquo;re not a bot.</p>
                <?php if ($challenge_phrase) : ?>
                    <p class="lo-subscribe__challenge-quote" aria-live="polite"><?php echo esc_html($challenge_phrase); ?></p>
                <?php endif; ?>
                <label for="<?php echo esc_attr($challenge_id); ?>" class="lo-subscribe__challenge-label">
                    <span class="screen-reader-text">Type the sentence</span>
                    <input
                        id="<?php echo esc_attr($challenge_id); ?>"
                        class="lo-subscribe__input"
                        type="text"
                        name="challenge_response"
                        placeholder="Copy the sentence above"
                        autocomplete="off"
                        aria-describedby="<?php echo esc_attr($challenge_hint_id); ?>"
                        required
                        data-lo-captcha-input
                    />
                </label>
                <p class="lo-subscribe__note" id="<?php echo esc_attr($challenge_hint_id); ?>">Case doesn&rsquo;t matter, punctuation optional.</p>
                  <noscript>
                      <p class="lo-subscribe__noscript">No JavaScript? Type the word <strong>jobs</strong> above.</p>
                    <input type="hidden" name="lo_noscript_challenge" value="1" />
                </noscript>
            </div>
            <input type="text" name="website" class="lo-hp" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px" />
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>" />
            <button type="submit" class="lo-subscribe__button">Subscribe</button>
            <p class="lo-subscribe__status" data-lo-subscribe-status aria-live="polite"></p>
        </form></details>
        <p class="lo-subscribe__help">Watch for the confirmation email (check spam if it’s missing). Every briefing ships with a one-click unsubscribe link, and we rotate a Steve Jobs quote to stop spam bots.</p>
    </div>
    <?php
    return (string) ob_get_clean();
}


function render_report_shortcode(): string {
    [$base_path, $base_url] = locate_assets_base();

    wp_enqueue_style(
        'lousy-outages',
        $base_url . 'lousy-outages.css',
        [],
        asset_version($base_path, 'lousy-outages.css')
    );

    wp_enqueue_script(
        'lousy-outages',
        $base_url . 'lousy-outages.js',
        [],
        asset_version($base_path, 'lousy-outages.js'),
        true
    );

    $providers = Providers::enabled();
    if (empty($providers)) {
        $providers = Providers::list();
    }

    $report_endpoint = rest_url('lousy-outages/v1/report');
    $signals_endpoint = rest_url('lousy-outages/v1/signals');
    $signals = class_exists('\\SuzyEaston\\LousyOutages\\SignalEngine') ? SignalEngine::summarize_recent_signals(60) : [];

    ob_start();
    ?>
    <section class="lo-report" data-lo-report>
        <h3>Seeing an issue?</h3>
        <p>Report what you’re seeing. We’ll treat it as an unconfirmed community signal unless the provider confirms it.</p>
        <form class="lo-report__form" method="post" action="<?php echo esc_url($report_endpoint); ?>" data-lo-report-form>
            <label class="lo-report__field">Provider
                <select name="provider_id" data-lo-report-provider required>
                    <option value="">Select a provider…</option>
                    <?php foreach ($providers as $provider) : $provider_id = sanitize_key((string) ($provider['id'] ?? '')); if (!$provider_id) continue; ?>
                    <option value="<?php echo esc_attr($provider_id); ?>"><?php echo esc_html((string) ($provider['name'] ?? $provider_id)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="lo-report__field">Symptom
                <select name="symptom" data-lo-report-summary required><option value="login">Login</option><option value="checkout">Checkout</option><option value="payments">Payments</option><option value="api">API</option><option value="dashboard">Dashboard</option><option value="dns">DNS</option><option value="email">Email</option><option value="slow">Slow</option><option value="full_outage">Full outage</option><option value="other">Other</option></select>
            </label>
            <label class="lo-report__field">Severity
                <select name="severity"><option value="unknown">Unknown</option><option value="minor">Minor</option><option value="degraded">Degraded</option><option value="major">Major</option></select>
            </label>
            <label class="lo-report__field">Region <input type="text" name="region" maxlength="80" placeholder="Canada / BC / Vancouver"></label>
            <label class="lo-report__field">Details (optional)<textarea name="details" maxlength="500"></textarea></label>
            <label class="lo-report__field">Email (optional)<input type="email" name="email" data-lo-report-contact></label>
            <button type="submit" class="lo-subscribe__button" data-lo-report-submit>Report issue</button>
            <p class="lo-report__status" data-lo-report-status aria-live="polite"></p>
        </form>
        <div class="lo-signals" data-lo-signals data-lo-signals-endpoint="<?php echo esc_url($signals_endpoint); ?>">
            <h4>Community signals</h4>
            <div data-lo-signals-list>
            <?php foreach (array_slice($signals, 0, 5) as $signal) : $class = strtolower((string) ($signal['classification'] ?? 'quiet')); if (!in_array($class, ['watch','trending','hot'], true)) { continue; } ?>
                <div class="lo-signal lo-signal--<?php echo esc_attr($class); ?>">
                    <span class="lo-signal__badge"><?php echo esc_html(ucfirst($class)); ?></span>
                    <strong><?php echo esc_html((string) ($signal['provider_name'] ?? $signal['provider_id'] ?? 'Provider')); ?></strong>
                    <span><?php echo esc_html((string) ($signal['message'] ?? 'Unconfirmed community signal.')); ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <p data-lo-signals-empty<?php echo !empty($signals) ? ' hidden' : ''; ?>>No unusual community reports.</p>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}
