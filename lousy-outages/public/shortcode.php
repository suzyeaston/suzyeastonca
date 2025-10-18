<?php
declare(strict_types=1);

namespace LousyOutages;

add_shortcode( 'lousy_outages', __NAMESPACE__ . '\render_shortcode' );

function render_shortcode(): string {
    $base_path  = plugin_dir_path( __DIR__ ) . 'assets/';
    $theme_path = get_template_directory() . '/lousy-outages/assets/';
    if ( file_exists( $theme_path ) ) {
        $base_path = $theme_path;
        $base_url  = get_template_directory_uri() . '/lousy-outages/assets/';
    } else {
        $base_url = plugin_dir_url( __DIR__ ) . 'assets/';
    }

    wp_enqueue_style(
        'lousy-outages',
        $base_url . 'lousy-outages.css',
        [],
        file_exists( $base_path . 'lousy-outages.css' ) ? filemtime( $base_path . 'lousy-outages.css' ) : null
    );

    wp_enqueue_script(
        'lousy-outages',
        $base_url . 'lousy-outages.js',
        [],
        file_exists( $base_path . 'lousy-outages.js' ) ? filemtime( $base_path . 'lousy-outages.js' ) : null,
        true
    );

    $store      = new Store();
    $providers  = Providers::enabled();
    $states     = $store->get_all();
    $fetched_at = get_option( 'lousy_outages_last_poll' );

    if ( ! $fetched_at ) {
        foreach ( $states as $state ) {
            if ( empty( $state['updated_at'] ) ) {
                continue;
            }
            $candidate = strtotime( (string) $state['updated_at'] );
            if ( ! $candidate ) {
                continue;
            }
            if ( ! $fetched_at || $candidate > strtotime( (string) $fetched_at ) ) {
                $fetched_at = gmdate( 'c', $candidate );
            }
        }
    }

    $fetched_at         = $fetched_at ?: gmdate( 'c' );
    $provider_payloads  = [];
    foreach ( $providers as $id => $provider ) {
        $state    = $states[ $id ] ?? [];
        $payload  = lousy_outages_build_provider_payload( $id, $state, $fetched_at );
        if ( empty( $payload['url'] ) && ! empty( $provider['status_url'] ) ) {
            $payload['url'] = $provider['status_url'];
        }
        $provider_payloads[] = $payload;
    }

    $config = [
        'endpoint'     => esc_url_raw( rest_url( 'lousy-outages/v1/status' ) ),
        'pollInterval' => lousy_outages_get_poll_interval(),
        'initial'      => [
            'providers' => $provider_payloads,
            'meta'      => [ 'fetchedAt' => $fetched_at ],
        ],
    ];

    wp_localize_script( 'lousy-outages', 'LousyOutagesConfig', $config );

    $format_datetime = static function ( ?string $iso ): string {
        if ( empty( $iso ) ) {
            return '—';
        }
        $timestamp = strtotime( $iso );
        if ( ! $timestamp ) {
            return '—';
        }
        $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        return wp_date( $format, $timestamp );
    };

    $raw_feed_url  = home_url( '/outages/feed' );
    $feed_url      = esc_url( $raw_feed_url );
    $fallback_feed = esc_url( add_query_arg( 'lousy_outages_feed', '1', home_url( '/' ) ) );

    ob_start();
    ?>
    <div class="lousy-outages" data-lo-endpoint="<?php echo esc_url( $config['endpoint'] ); ?>">
        <div class="lo-header">
            <div class="lo-actions">
                <span class="lo-meta" aria-live="polite">
                    Fetched: <strong id="lo-fetched-at" data-lo-fetched><?php echo esc_html( $format_datetime( $fetched_at ) ); ?></strong>
                    <span id="lo-countdown" data-lo-countdown>Calculating refresh…</span>
                </span>
                <button type="button" class="lo-link" id="lo-refresh-btn" data-lo-refresh>Refresh now</button>
                <a class="lo-link" href="<?php echo $feed_url; ?>" target="_blank" rel="noopener">
                    <svg class="lo-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M6 17a2 2 0 11.001 3.999A2 2 0 016 17zm-2-7v3a8 8 0 018 8h3c0-6.075-4.925-11-11-11zm0-5v3c9.389 0 17 7.611 17 17h3C24 13.85 10.15 0 4 0z" />
                    </svg>
                    Subscribe (RSS)
                </a>
            </div>
            <noscript>
                <p><a class="lo-link" href="<?php echo $fallback_feed; ?>">RSS (fallback)</a></p>
            </noscript>
        </div>
        <div class="lo-grid" data-lo-grid>
            <?php foreach ( $provider_payloads as $provider ) :
                $state_code = $provider['stateCode'] ?? 'unknown';
                $incidents  = $provider['incidents'] ?? [];
                $prealert   = ( isset( $provider['prealert'] ) && is_array( $provider['prealert'] ) ) ? $provider['prealert'] : [];
                $risk       = isset( $prealert['risk'] ) ? (int) $prealert['risk'] : ( isset( $provider['risk'] ) ? (int) $provider['risk'] : 0 );
                $prealert_summary = isset( $prealert['summary'] ) ? (string) $prealert['summary'] : '';
                $prealert_measures = isset( $prealert['measures'] ) && is_array( $prealert['measures'] ) ? $prealert['measures'] : [];
                ?>
                <article class="lo-card" data-provider-id="<?php echo esc_attr( $provider['id'] ); ?>">
                    <div class="lo-head">
                        <h3 class="lo-title"><?php echo esc_html( $provider['name'] ); ?></h3>
                        <span class="lo-pill <?php echo esc_attr( $state_code ); ?>">
                            <?php echo esc_html( $provider['state'] ); ?>
                        </span>
                        <?php if ( $risk >= 20 ) : ?>
                            <span class="lo-pill risk">
                                RISK: <?php echo esc_html( $risk ); ?>/100
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $provider['error'] ) ) : ?>
                        <span class="lo-error">Error: <?php echo esc_html( (string) $provider['error'] ); ?></span>
                    <?php endif; ?>
                    <p class="lo-summary"><?php echo esc_html( $provider['summary'] ); ?></p>
                    <?php if ( $risk > 0 ) :
                        $measure_bits = [];
                        if ( isset( $prealert_measures['latency_ms'] ) && '' !== (string) $prealert_measures['latency_ms'] ) {
                            $measure_bits[] = 'Latency ' . (int) $prealert_measures['latency_ms'] . ' ms';
                        }
                        if ( isset( $prealert_measures['baseline_ms'] ) && '' !== (string) $prealert_measures['baseline_ms'] ) {
                            $measure_bits[] = 'Baseline ' . (int) $prealert_measures['baseline_ms'] . ' ms';
                        }
                        ?>
                        <div class="lo-prealert">
                            <strong>Pre-alerts</strong>
                            <p class="lo-prealert-summary"><?php echo esc_html( $prealert_summary ?: 'Early warning signals detected.' ); ?></p>
                            <?php if ( ! empty( $measure_bits ) ) : ?>
                                <p class="lo-prealert-metrics"><?php echo esc_html( implode( ' • ', $measure_bits ) ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $provider['snark'] ) ) : ?>
                        <p class="lo-snark"><?php echo esc_html( (string) $provider['snark'] ); ?></p>
                    <?php endif; ?>
                    <div class="lo-inc">
                        <strong>Incidents</strong>
                        <?php if ( empty( $incidents ) ) : ?>
                            <p class="lo-empty">No active incidents</p>
                        <?php else : ?>
                            <ul class="lo-inc-list">
                                <?php foreach ( $incidents as $incident ) :
                                    $impact       = ucfirst( (string) ( $incident['impact'] ?? 'unknown' ) );
                                    $updated      = $format_datetime( $incident['updatedAt'] ?? $incident['startedAt'] ?? '' );
                                    $incident_url = $incident['url'] ?? '';
                                    $meta_bits    = array_filter(
                                        [
                                            $impact,
                                            '—' !== $updated ? $updated : '',
                                        ],
                                        static fn ( $bit ) => '' !== (string) $bit
                                    );
                                    $meta_text = implode( ' • ', $meta_bits );
                                    ?>
                                    <li class="lo-inc-item">
                                        <p class="lo-inc-title"><?php echo esc_html( $incident['title'] ?? 'Incident' ); ?></p>
                                        <?php if ( $meta_text ) : ?>
                                            <p class="lo-inc-meta"><?php echo esc_html( $meta_text ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( $incident_url ) : ?>
                                            <a class="lo-status-link" href="<?php echo esc_url( $incident_url ); ?>" target="_blank" rel="noopener noreferrer">Open incident</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $provider['url'] ) ) : ?>
                        <a class="lo-status-link" href="<?php echo esc_url( $provider['url'] ); ?>" target="_blank" rel="noopener noreferrer">View status →</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
