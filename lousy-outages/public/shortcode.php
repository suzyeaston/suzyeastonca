<?php
declare(strict_types=1);

namespace LousyOutages;

const LO_SHOW_WIDESPREAD = false;

add_shortcode('lousy_outages', __NAMESPACE__ . '\render_shortcode');
add_shortcode('lousy_outages_subscribe', __NAMESPACE__ . '\render_subscribe_shortcode');

function render_shortcode(): string {
    $base_path  = plugin_dir_path(__DIR__) . 'assets/';
    $theme_path = get_template_directory() . '/lousy-outages/assets/';
    if (file_exists($theme_path)) {
        $base_path = $theme_path;
        $base_url  = get_template_directory_uri() . '/lousy-outages/assets/';
    } else {
        $base_url = plugin_dir_url(__DIR__) . 'assets/';
    }

    wp_enqueue_style(
        'lousy-outages',
        $base_url . 'lousy-outages.css',
        [],
        file_exists($base_path . 'lousy-outages.css') ? filemtime($base_path . 'lousy-outages.css') : null
    );

    wp_enqueue_script(
        'lousy-outages',
        $base_url . 'lousy-outages.js',
        [],
        file_exists($base_path . 'lousy-outages.js') ? filemtime($base_path . 'lousy-outages.js') : null,
        true
    );

    $cache_key = is_user_logged_in() ? null : 'lousy_outages_fragment_public';
    $cached    = ($cache_key) ? get_transient($cache_key) : null;

    $fetcher      = new Lousy_Outages_Fetcher();
    $provider_map = $fetcher->get_provider_map();

    $providers_config = Providers::enabled();
    if (is_array($provider_map) && $provider_map) {
        $providers_config = array_intersect_key($providers_config, $provider_map);
        if (empty($providers_config)) {
            foreach ($provider_map as $slug => $provider_info) {
                $providers_config[$slug] = [
                    'id'         => $slug,
                    'name'       => $provider_info['name'],
                    'status_url' => $provider_info['status_url'],
                ];
            }
        }
    }

    $fetched_at = gmdate('c');
    $tiles      = [];
    $config     = null;
    $initial_trending = [
        'trending'     => false,
        'signals'      => [],
        'generated_at' => gmdate('c'),
    ];

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
    }

    if (!$config) {
        $result = $fetcher->get_all(array_keys($provider_map));
        $tiles = isset($result['providers']) && is_array($result['providers']) ? $result['providers'] : [];
        if (isset($result['fetched_at'])) {
            $fetched_at = (string) $result['fetched_at'];
        }
        if (isset($result['trending']) && is_array($result['trending'])) {
            $initial_trending = $result['trending'];
        }
        $config = [
            'endpoint'          => esc_url_raw(rest_url('lousy-outages/v1/summary')),
            'pollInterval'      => 60000,
            'refreshEndpoint'   => esc_url_raw(rest_url('lousy/v1/refresh')),
            'refreshNonce'      => wp_create_nonce('wp_rest'),
            'subscribeEndpoint' => esc_url_raw(rest_url('lousy-outages/v1/subscribe')),
            'initial'           => [
                'providers'  => $tiles,
                'fetched_at' => $fetched_at,
                'trending'   => $initial_trending,
            ],
        ];
    } else {
        if (!isset($config['initial']['trending']) || !is_array($config['initial']['trending'])) {
            $config['initial']['trending'] = $initial_trending;
        } else {
            $initial_trending = $config['initial']['trending'];
        }
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

    foreach ($providers_config as $slug => $provider_config) {
        if (isset($tiles_by_slug[$slug])) {
            continue;
        }
        $ordered_tiles[] = [
            'id'           => $slug,
            'provider'     => $slug,
            'name'         => $provider_config['name'] ?? ucfirst($slug),
            'status'       => 'unknown',
            'status_label' => 'UNKNOWN',
            'status_class' => 'status--unknown',
            'overall'      => 'unknown',
            'message'      => 'Status unavailable',
            'summary'      => 'Status unavailable',
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
            $ordered_tiles[] = [
                'provider'     => $slug,
                'name'         => $provider_info['name'],
                'status'       => 'unknown',
                'status_label' => 'UNKNOWN',
                'status_class' => 'status--unknown',
                'overall'      => 'unknown',
                'message'      => 'Status unavailable',
                'summary'      => 'Status unavailable',
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

    $rss_url = esc_url(home_url('/lousy-outages/feed/'));

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

    ob_start();
    ?>
    <?php
    $trending_active = !empty($initial_trending['trending']);
    $trending_signals = isset($initial_trending['signals']) && is_array($initial_trending['signals'])
        ? array_filter(array_map('strval', $initial_trending['signals']))
        : [];
    $trending_generated = isset($initial_trending['generated_at']) ? (string) $initial_trending['generated_at'] : '';
    ?>
    <div class="lousy-outages" data-lo-endpoint="<?php echo esc_url($config['endpoint']); ?>">
        <div class="lo-header">
            <div class="lo-actions">
                <span class="lo-meta" aria-live="polite">
                    <span>Fetched:</span>
                    <strong data-lo-fetched><?php echo esc_html($format_datetime($fetched_at)); ?></strong>
                    <span data-lo-countdown>Auto-refresh ready</span>
                </span>
                <span class="lo-pill lo-pill--degraded" data-lo-degraded hidden>Auto-refresh degraded</span>
                <button type="button" class="lo-link" data-lo-refresh>Refresh now</button>
                <a class="lo-link" href="<?php echo esc_url($rss_url); ?>" target="_blank" rel="noopener">Subscribe (RSS)</a>
            </div>
        </div>
        <?php if (LO_SHOW_WIDESPREAD) : ?>
        <div class="lo-trending" data-lo-trending<?php echo $trending_active ? '' : ' hidden'; ?> data-lo-trending-generated="<?php echo esc_attr($trending_generated); ?>" aria-live="assertive">
            <span class="lo-trending__icon" aria-hidden="true">⚡</span>
            <div class="lo-trending__body">
                <strong data-lo-trending-text>Potential widespread issues detected — check affected providers</strong>
                <span class="lo-trending__reasons" data-lo-trending-reasons<?php echo $trending_signals ? '' : ' hidden'; ?>><?php echo esc_html($trending_signals ? 'Signals: ' . implode(', ', array_slice($trending_signals, 0, 6)) : ''); ?></span>
            </div>
            <a class="lo-link" href="https://downdetector.com/" target="_blank" rel="noopener">Downdetector →</a>
        </div>
        <?php endif; ?>
        <?php echo render_subscribe_shortcode(); ?>
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
                ?>
                <article class="lo-card" data-provider-id="<?php echo esc_attr($slug ?: 'provider'); ?>">
                    <div class="lo-head">
                        <h3 class="lo-title"><?php echo esc_html($tile['name'] ?? ucfirst($slug)); ?></h3>
                        <span class="lo-pill <?php echo esc_attr($status_class); ?>" data-lo-badge><?php echo esc_html($label); ?></span>
                    </div>
                    <p class="lo-error" data-lo-error<?php echo empty($tile['error']) ? ' hidden' : ''; ?>><?php echo esc_html((string) ($tile['error'] ?? '')); ?></p>
                    <p class="lo-summary" data-lo-summary><?php echo esc_html($tile['summary'] ?? 'Status unavailable'); ?></p>
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
                    <div class="lo-inc" data-lo-incidents>
                        <?php if (empty($incidents)) : ?>
                            <p class="lo-empty">No active incidents.</p>
                        <?php else : ?>
                            <ul class="lo-inc-list">
                                <?php foreach ($incidents as $incident) :
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
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tile['url'])) : ?>
                        <a class="lo-status-link" data-lo-status-url href="<?php echo esc_url($tile['url']); ?>" target="_blank" rel="noopener">View status →</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
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
    $rss_url  = esc_url(home_url('/lousy-outages/feed/'));
    $challenge = \lo_build_subscribe_challenge();
    if (!is_array($challenge) || empty($challenge['prompt']) || empty($challenge['hash'])) {
        $challenge = [
            'prompt' => 'Type the word outage (all lowercase) to unlock alerts.',
            'hash'   => \lo_hash_subscribe_answer('outage'),
        ];
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
            <label class="lo-subscribe__label lo-subscribe__challenge" for="<?php echo esc_attr($challenge_id); ?>">
                <span><?php echo esc_html($challenge['prompt']); ?></span>
                <input
                    id="<?php echo esc_attr($challenge_id); ?>"
                    class="lo-subscribe__input"
                    type="text"
                    name="challenge_response"
                    placeholder="Type it here"
                    autocomplete="off"
                    aria-describedby="<?php echo esc_attr($challenge_hint_id); ?>"
                    required
                />
                <p class="lo-subscribe__note" id="<?php echo esc_attr($challenge_hint_id); ?>">Humans only: answer the secret word challenge so we know you’re real.</p>
            </label>
            <input type="text" name="website" class="lo-hp" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px" />
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>" />
            <input type="hidden" name="challenge_expected" value="<?php echo esc_attr($challenge['hash']); ?>" />
            <button type="submit" class="lo-subscribe__button">Subscribe</button>
            <p class="lo-subscribe__status" data-lo-subscribe-status aria-live="polite"></p>
        </form>
        <p class="lo-subscribe__help">Watch for the confirmation email (check spam if it’s missing). Every briefing ships with a one-click unsubscribe link, and we use a secret word challenge to stop spam bots.</p>
    </div>
    <?php
    return (string) ob_get_clean();
}
