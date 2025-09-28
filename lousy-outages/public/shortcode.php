<?php
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
        filemtime( $base_path . 'lousy-outages.css' )
    );

    wp_enqueue_script(
        'lousy-outages',
        $base_url . 'lousy-outages.js',
        [],
        filemtime( $base_path . 'lousy-outages.js' ),
        true
    );

    $locale           = I18n::determine_locale();
    $strings          = I18n::strings( $locale );
    $fallback_strings = I18n::strings( 'en-US' );
    $store            = new Store();
    $providers        = Providers::enabled();
    $states           = $store->get_all();
    $initial_iso      = '';

    foreach ( $states as $state ) {
        if ( empty( $state['updated_at'] ) ) {
            continue;
        }
        $timestamp = strtotime( (string) $state['updated_at'] );
        if ( ! $timestamp ) {
            continue;
        }
        if ( ! $initial_iso || $timestamp > strtotime( $initial_iso ) ) {
            $initial_iso = gmdate( 'c', $timestamp );
        }
    }

    $config = [
        'endpoint'         => esc_url_raw( home_url( '/api/outages' ) ),
        'pollInterval'     => lousy_outages_get_poll_interval(),
        'fetchTimeout'     => 10000,
        'locale'           => $locale,
        'strings'          => $strings,
        'fallbackStrings'  => $fallback_strings,
        'providers'        => array_values(
            array_map(
                static fn( $prov ) => [
                    'id'   => $prov['id'],
                    'name' => $prov['name'],
                    'url'  => $prov['url'] ?? '',
                ],
                $providers
            )
        ),
        'initialTimestamp' => $initial_iso,
        'voiceEnabled'     => (bool) apply_filters( 'lousy_outages_voice_enabled', false ),
        'debug'            => function_exists( 'wp_get_environment_type' ) ? 'production' !== wp_get_environment_type() : ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
    ];

    wp_localize_script( 'lousy-outages', 'LousyOutagesConfig', $config );

    ob_start();
    ?>
    <div class="lo-arcade">
        <div
            id="lousy-outages"
            class="lousy-outages-board"
            aria-live="polite"
            data-voice-enabled="<?php echo $config['voiceEnabled'] ? '1' : '0'; ?>"
        >
            <header class="board-header">
                <p class="last-updated"><?php echo esc_html( $strings['updatedLabel'] ); ?> <span data-initial="<?php echo esc_attr( $initial_iso ); ?>"></span></p>
                <button type="button" class="coin-btn" data-loading-label="<?php echo esc_attr( $strings['buttonLoading'] ); ?>">
                    <span class="label"><?php echo esc_html( $strings['buttonShortLabel'] ); ?></span>
                    <span class="loader" aria-hidden="true"></span>
                </button>
            </header>
            <p class="board-subtitle"><?php echo esc_html( $strings['teaserCaption'] ); ?></p>
            <div class="providers-grid" role="list">
                <?php foreach ( $providers as $id => $prov ) :
                    $state        = $states[ $id ] ?? [];
                    $status_code  = $state['status'] ?? 'unknown';
                    $status_label = $state['status_label'] ?? Fetcher::status_label( $status_code );
                    $summary      = $state['summary'] ?? ( $state['message'] ?? $status_label );
                    $details_id   = 'lo-details-' . sanitize_html_class( $id );
                    $provider_url = $state['url'] ?? ( $prov['url'] ?? '' );
                    $incidents    = $state['incidents'] ?? [];
                    $has_incident = ! empty( $incidents ) && is_array( $incidents );
                    $degraded     = ! in_array( $status_code, [ 'operational', 'unknown' ], true );
                    $empty_text   = ( $degraded && ! $has_incident )
                        ? ( $strings['degradedNoIncidents'] ?? $strings['noIncidents'] )
                        : $strings['noIncidents'];
                    ?>
                    <article
                        class="provider-card"
                        role="listitem"
                        data-id="<?php echo esc_attr( $id ); ?>"
                        data-name="<?php echo esc_attr( $prov['name'] ); ?>"
                    >
                        <div class="provider-card__inner">
                            <header class="provider-card__header">
                                <h3 class="provider-card__name"><?php echo esc_html( $prov['name'] ); ?></h3>
                                <span class="status-badge status--<?php echo esc_attr( $status_code ); ?>" data-status="<?php echo esc_attr( $status_code ); ?>">
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                            </header>
                            <p class="provider-card__summary"><?php echo esc_html( $summary ); ?></p>
                            <p class="provider-card__snark" data-snark="true">&nbsp;</p>
                            <button
                                type="button"
                                class="details-toggle"
                                aria-expanded="false"
                                aria-controls="<?php echo esc_attr( $details_id ); ?>"
                            >
                                <span class="toggle-label"><?php echo esc_html( $strings['detailsLabel'] ); ?></span>
                            </button>
                            <section class="provider-details" id="<?php echo esc_attr( $details_id ); ?>" hidden>
                                <div class="incidents" data-empty-text="<?php echo esc_attr( $empty_text ); ?>">
                                    <p class="incident-empty"><?php echo esc_html( $empty_text ); ?></p>
                                </div>
                                <a
                                    class="provider-link"
                                    data-default-url="<?php echo esc_attr( $provider_url ); ?>"
                                    href="<?php echo esc_url( $provider_url ?: '#' ); ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <?php echo esc_html( $strings['viewProvider'] ); ?>
                                </a>
                            </section>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="ticker" aria-live="polite"></div>
            <p class="microcopy"><?php echo esc_html( $strings['buttonLabel'] ); ?></p>
            <p class="weather"><?php echo esc_html( $strings['microcopy'] ); ?></p>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
