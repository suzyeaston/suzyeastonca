<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

const LO_SHOW_WIDESPREAD = false;

/**
 * Determine the filesystem path and URL for the plugin assets.
 *
 * When the plugin is bundled with the theme (as it is on suzyeaston.ca),
 * plugin_dir_url() incorrectly points to wp-content/plugins/, which results in
 * 404s for front-end assets. This helper checks for copies that live alongside
 * the active theme before falling back to the plugin directory.
 *
 * @return array{string, string} An array containing the absolute path and the
 *                               public URL for the assets directory.
 */
function locate_assets_base(): array
{
    $candidates = [];

    if (function_exists('get_stylesheet_directory') && function_exists('get_stylesheet_directory_uri')) {
        $stylesheet_path = rtrim(get_stylesheet_directory(), '/\\') . '/lousy-outages/assets/';
        $stylesheet_url  = rtrim(get_stylesheet_directory_uri(), '/\\') . '/lousy-outages/assets/';
        $candidates[] = [$stylesheet_path, $stylesheet_url];
    }

    if (function_exists('get_template_directory') && function_exists('get_template_directory_uri')) {
        $template_path = rtrim(get_template_directory(), '/\\') . '/lousy-outages/assets/';
        $template_url  = rtrim(get_template_directory_uri(), '/\\') . '/lousy-outages/assets/';
        $candidates[] = [$template_path, $template_url];
    }

    $plugin_path = rtrim(plugin_dir_path(__DIR__), '/\\') . '/assets/';
    $plugin_url  = rtrim(plugin_dir_url(__DIR__), '/\\') . '/assets/';
    $candidates[] = [$plugin_path, $plugin_url];

    foreach ($candidates as $candidate) {
        [$path, $url] = $candidate;
        if ($path && file_exists($path)) {
            return [$path, $url];
        }
    }

    return [$plugin_path, $plugin_url];
}

add_shortcode('lousy_outages', __NAMESPACE__ . '\render_shortcode');
add_shortcode('lousy_outages_subscribe', __NAMESPACE__ . '\render_subscribe_shortcode');

function render_shortcode(): string {
    [$base_path, $base_url] = locate_assets_base();

    wp_enqueue_style(
        'lousy-outages',
        $base_url . 'lousy-outages.css',
        [],
        file_exists($base_path . 'lousy-outages.css') ? filemtime($base_path . 'lousy-outages.css') : null
    );

    wp_enqueue_style(
        'lousy-outages-hud',
        $base_url . 'hud.css',
        ['lousy-outages'],
        file_exists($base_path . 'hud.css') ? filemtime($base_path . 'hud.css') : null
    );

    wp_enqueue_script(
        'lousy-outages',
        $base_url . 'lousy-outages.js',
        [],
        file_exists($base_path . 'lousy-outages.js') ? filemtime($base_path . 'lousy-outages.js') : null,
        true
    );

    wp_enqueue_script(
        'lousy-outages-hud',
        $base_url . 'hud.js',
        ['lousy-outages'],
        file_exists($base_path . 'hud.js') ? filemtime($base_path . 'hud.js') : null,
        true
    );

    if (file_exists($base_path . 'js/outages.js')) {
        wp_enqueue_script(
            'lousy-outages-auto-refresh',
            $base_url . 'js/outages.js',
            [],
            filemtime($base_path . 'js/outages.js'),
            true
        );
    }

    $cache_key = is_user_logged_in() ? null : 'lousy_outages_fragment_public';
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
                $slug = (string) ($tile['provider'] ?? '');
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
            'refreshEndpoint'   => esc_url_raw(rest_url('lousy-outages/v1/refresh')),
            'refreshNonce'      => wp_create_nonce('wp_rest'),
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

    $tiles_by_slug = [];
    foreach ($tiles as $tile) {
        if (!is_array($tile)) {
            continue;
        }
        $slug = (string) ($tile['provider'] ?? '');
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
        $slug = (string) ($tile['provider'] ?? '');
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
        $status_label = $is_operational_placeholder ? $placeholder_label : 'UNKNOWN';
        $status_class = $is_operational_placeholder ? 'status--operational' : 'status--unknown';
        $summary_text = $is_operational_placeholder ? '' : 'Status unavailable';
        $message_text = $is_operational_placeholder ? 'All systems operational' : 'Status unavailable';

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
        ];
    }

    if (!$ordered_tiles && $provider_map) {
        foreach ($provider_map as $slug => $provider_info) {
            $provider_key = sanitize_key($slug);
            $is_operational_placeholder = in_array($provider_key, $operational_placeholders, true);
            $status_slug = $is_operational_placeholder ? 'operational' : 'unknown';
            $status_label = $is_operational_placeholder ? $placeholder_label : 'UNKNOWN';
            $status_class = $is_operational_placeholder ? 'status--operational' : 'status--unknown';
            $summary_text = $is_operational_placeholder ? '' : 'Status unavailable';
            $message_text = $is_operational_placeholder ? 'All systems operational' : 'Status unavailable';

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
            ];
        }
    }

    $config['initial']['providers'] = array_values($ordered_tiles);
    $config['initial']['source']    = $source;
    $config['initial']['errors']    = $snapshot_errors;

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

    $fetched_label = ('snapshot' === strtolower((string) $source))
        ? 'Outage info last refreshed:'
        : 'Outage info fetched:';
    $external_query = rawurlencode('service outage');
    $external_links = [
        [
            'label' => 'Twitter/X search',
            'url'   => 'https://x.com/search?q=' . $external_query,
        ],
        [
            'label' => 'Reddit search',
            'url'   => 'https://www.reddit.com/search/?q=' . $external_query,
        ],
        [
            'label' => 'Web search',
            'url'   => 'https://duckduckgo.com/?q=' . $external_query,
        ],
    ];

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
        class="lousy-outages lo-theme-dark"
        data-lo-endpoint="<?php echo esc_url($config['endpoint']); ?>"
        data-lo-source="<?php echo esc_attr($source); ?>"
        data-lo-snapshot="<?php echo esc_url($config['snapshotEndpoint'] ?? $snapshot_endpoint); ?>"
        data-lo-refresh-interval="<?php echo esc_attr((string) $refresh_interval); ?>"
    >
        <div class="lo-header">
            <div class="lo-actions">
                <span class="lo-meta" aria-live="polite">
                    <span data-lo-fetched-label><?php echo esc_html($fetched_label); ?></span>
                    <strong data-lo-fetched><?php echo esc_html($format_datetime($fetched_at)); ?></strong>
                    <span data-lo-countdown>Auto-refresh ready</span>
                </span>
                <span class="lo-pill lo-pill--degraded" data-lo-degraded hidden>Auto-refresh degraded</span>
                <button type="button" class="lo-link" data-lo-refresh>Refresh now</button>
                <button type="button" class="lo-link" data-lo-export-csv>Export CSV</button>
                <button type="button" class="lo-link" data-lo-export-pdf>Save PDF</button>
                <a class="lo-link" href="<?php echo esc_url($rss_url); ?>" target="_blank" rel="noopener">Subscribe (RSS)</a>
            </div>
        </div>
        <section class="lo-history" data-lo-history>
            <div class="lo-history__heading">
                <div>
                    <h3 class="lo-block-title">Recent incidents (Status feeds + community)</h3>
                    <p class="lo-history__meta">Last 30 days of non-operational blips.</p>
                </div>
                <div class="lo-history__controls">
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
                        </select>
                    </label>
                </div>
            </div>
            <div class="lo-history__body">
                <div class="lo-history__charts" data-lo-history-charts hidden></div>
                <ol class="lo-history__list" data-lo-history-list>
                    <li class="lo-history__item lo-history__item--placeholder">Loading incidents…</li>
                </ol>
                <p class="lo-history__empty" data-lo-history-empty hidden>No incidents in the past 30 days.</p>
                <p class="lo-history__error" data-lo-history-error hidden>Unable to load incident history right now.</p>
            </div>
        </section>
        <?php if (LO_SHOW_WIDESPREAD) : ?>
        <div class="lo-trending" data-lo-trending<?php echo $trending_active ? '' : ' hidden'; ?> data-lo-trending-generated="<?php echo esc_attr($trending_generated); ?>" aria-live="assertive">
            <span class="lo-trending__icon" aria-hidden="true">⚡</span>
            <div class="lo-trending__body">
                <strong data-lo-trending-text>Potential widespread issues detected — check affected providers</strong>
                <span class="lo-trending__reasons" data-lo-trending-reasons<?php echo $trending_signals ? '' : ' hidden'; ?>><?php echo esc_html($trending_signals ? 'Signals: ' . implode(', ', array_slice($trending_signals, 0, 6)) : ''); ?></span>
            </div>
        </div>
        <?php endif; ?>
        <?php echo render_subscribe_shortcode(); ?>
        <div class="lo-settings" data-lo-settings>
            <h3 class="lo-block-title">Customize view</h3>
            <p class="lo-settings__hint">Pick which providers appear below. Preferences stay on this browser only.</p>
            <div class="lo-settings__actions">
                <button type="button" class="lo-settings__button" data-lo-provider-select="all">Select all</button>
                <button type="button" class="lo-settings__button lo-settings__button--ghost" data-lo-provider-select="none">Select none</button>
            </div>
            <div class="lo-settings__options">
                <?php foreach ($ordered_tiles as $tile) :
                    $slug = (string) ($tile['provider'] ?? '');
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
        </div>
        <div class="lo-loading" data-lo-loading<?php echo $ordered_tiles ? ' hidden' : ''; ?> role="status">
            <span class="lo-loading__spinner" aria-hidden="true"></span>
            <span class="lo-loading__text">Dialing interstellar relays…</span>
        </div>
        <div class="lo-grid" data-lo-grid>
            <?php foreach ($ordered_tiles as $tile) :
                $slug = (string) ($tile['provider'] ?? '');
                $status = strtolower((string) ($tile['status'] ?? 'unknown'));
                $status_class = $tile['status_class'] ?? ('status--' . (preg_replace('/[^a-z0-9_-]+/i', '-', $status) ?: 'unknown'));
                $label = $tile['status_label'] ?? ucfirst($status);
                $components = array_filter(
                    is_array($tile['components'] ?? null) ? $tile['components'] : [],
                    static fn($component) => is_array($component) && strtolower((string) ($component['status'] ?? '')) !== 'operational'
                );
                $incidents = is_array($tile['incidents'] ?? null) ? $tile['incidents'] : [];
                $resolved_states = ['resolved', 'completed', 'postmortem'];
                $active_incidents = array_values(
                    array_filter(
                        $incidents,
                        static function ($incident) use ($resolved_states): bool {
                            if (!is_array($incident)) {
                                return false;
                            }
                            $status = strtolower((string) ($incident['status'] ?? ''));
                            return !in_array($status, $resolved_states, true);
                        }
                    )
                );
                ?>
                <article class="lo-card" data-provider-id="<?php echo esc_attr($slug ?: 'provider'); ?>">
                    <div class="lo-head">
                        <h3 class="lo-title"><?php echo esc_html($tile['name'] ?? ucfirst($slug)); ?></h3>
                        <span class="lo-pill <?php echo esc_attr($status_class); ?>" data-lo-badge><?php echo esc_html($label); ?></span>
                    </div>
                    <p class="lo-error" data-lo-error<?php echo empty($tile['error']) ? ' hidden' : ''; ?>><?php echo esc_html((string) ($tile['error'] ?? '')); ?></p>
                    <?php
                    $message_text = trim((string) ($tile['message'] ?? ''));
                    $summary_text = trim((string) ($tile['summary'] ?? ''));
                    if (!empty($tile['error'])) {
                        if ('' === $message_text) {
                            $message_text = 'Status temporarily unavailable.';
                        }
                        $summary_text = '';
                    }
                    if ('' === $message_text) {
                        if (!empty($active_incidents)) {
                            $lead_incident = $active_incidents[0]['name'] ?? ($active_incidents[0]['title'] ?? ($active_incidents[0]['summary'] ?? 'Incident'));
                            $message_text = $lead_incident;
                        } elseif ($status === 'operational') {
                            $message_text = 'All systems operational';
                        } elseif ($status === 'unknown') {
                            $message_text = 'Status unknown.';
                        } else {
                            $message_text = $summary_text ?: ($label ?: 'Status update');
                        }
                    }

                    $summary_display = '';
                    if ('' !== $summary_text && strcasecmp($summary_text, $message_text) !== 0) {
                        $summary_display = $summary_text;
                    }
                    ?>
                    <p class="lo-message" data-lo-message><?php echo esc_html($message_text); ?></p>
                    <?php if ('' !== $summary_display) : ?>
                        <p class="lo-summary" data-lo-summary><?php echo esc_html($summary_display); ?></p>
                    <?php endif; ?>
                    <div class="lo-components" data-lo-components>
                        <?php if (!empty($components)) : ?>
                            <h4 class="lo-components__title">Impacted components</h4>
                            <ul class="lo-components__list">
                                <?php foreach ($components as $component) :
                                    $component_status = strtolower((string) ($component['status'] ?? 'unknown'));
                                    $component_label  = $component['status_label'] ?? ucfirst($component_status);
                                    ?>
                                    <li>
                                        <span class="lo-component-name"><?php echo esc_html($component['name'] ?? 'Component'); ?></span>
                                        <span class="lo-component-status"><?php echo esc_html($component_label); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($active_incidents)) : ?>
                        <div class="lo-inc" data-lo-incidents>
                            <ul class="lo-inc-list">
                                <?php foreach ($active_incidents as $incident) :
                                    $impact  = isset($incident['status']) ? ucfirst((string) $incident['status']) : 'Unknown';
                                    $updated = $format_datetime($incident['updated_at'] ?? null);
                                    $summary = isset($incident['summary']) ? (string) $incident['summary'] : '';
                                    $url     = isset($incident['url']) ? (string) $incident['url'] : '';
                                    ?>
                                    <li class="lo-inc-item">
                                        <p class="lo-inc-title"><?php echo esc_html($incident['name'] ?? 'Incident'); ?></p>
                                        <p class="lo-inc-meta"><?php echo esc_html(trim($impact . ($updated ? ' • ' . $updated : ''))); ?></p>
                                        <?php if ($summary) : ?>
                                            <p class="lo-inc-summary"><?php echo esc_html($summary); ?></p>
                                        <?php endif; ?>
                                        <?php if ($url) : ?>
                                            <a class="lo-status-link" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">View incident</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($tile['url'])) : ?>
                        <a class="lo-status-link" data-lo-status-url href="<?php echo esc_url($tile['url']); ?>" target="_blank" rel="noopener">View status →</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <article class="lo-card lo-card--external" data-provider-id="external-signals">
                <div class="lo-head">
                    <h3 class="lo-title">External signals</h3>
                </div>
                <p class="lo-summary">Quick links to community chatter and third-party status hints.</p>
                <ul class="lo-links">
                    <?php foreach ($external_links as $external_link) : ?>
                        <li>
                            <a class="lo-link" href="<?php echo esc_url($external_link['url']); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($external_link['label']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </div>
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

    ob_start();
    ?>
    <div class="lo-subscribe" data-lo-subscribe>
        <h2 class="lo-subscribe__title">Subscribe by email or RSS</h2>
        <p class="lo-subscribe__intro">Get outage alerts in your inbox or follow the <a href="<?php echo $rss_url; ?>" target="_blank" rel="noopener">RSS feed</a>.</p>
        <form class="lo-subscribe__form" method="post" action="<?php echo esc_url($endpoint); ?>" data-lo-subscribe-form>
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
        </form>
        <p class="lo-subscribe__help">Watch for the confirmation email (check spam if it’s missing). Every briefing ships with a one-click unsubscribe link, and we rotate a Steve Jobs quote to stop spam bots.</p>
    </div>
    <?php
    return (string) ob_get_clean();
}
