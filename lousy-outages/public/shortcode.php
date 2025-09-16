<?php
namespace LousyOutages;

add_shortcode( 'lousy_outages', __NAMESPACE__ . '\\render_shortcode' );

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
        'endpoint'        => esc_url_raw( rest_url( 'lousy-outages/v1/status' ) ),
        'pollInterval'    => lousy_outages_get_poll_interval(),
        'fetchTimeout'    => 8000,
        'locale'          => $locale,
        'strings'         => $strings,
        'fallbackStrings' => $fallback_strings,
        'providers'       => array_values(
            array_map(
                static fn( $prov ) => [
                    'id'   => $prov['id'],
                    'name' => $prov['name'],
                ],
                $providers
            )
        ),
        'initialTimestamp' => $initial_iso,
        'debug'            => function_exists( 'wp_get_environment_type' ) ? 'production' !== wp_get_environment_type() : ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
    ];

    wp_localize_script( 'lousy-outages', 'LousyOutagesConfig', $config );

    ob_start();
    ?>
    <div class="lo-arcade">
        <div id="lousy-outages" class="lousy-outages-list" aria-live="polite">
            <p class="last-updated"><?php echo esc_html( $strings['updatedLabel'] ); ?> <span data-initial="<?php echo esc_attr( $initial_iso ); ?>"></span></p>
            <table>
                <thead>
                    <tr>
                        <th><?php echo esc_html( $strings['providerHeader'] ); ?></th>
                        <th><?php echo esc_html( $strings['statusHeader'] ); ?></th>
                        <th><?php echo esc_html( $strings['messageHeader'] ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $providers as $id => $prov ) :
                    $state        = $states[ $id ] ?? [];
                    $status_code  = $state['status'] ?? 'unknown';
                    $status_label = $state['status_label'] ?? Fetcher::status_label( $status_code );
                    $message      = $state['message'] ?? '';
                    ?>
                    <tr data-id="<?php echo esc_attr( $id ); ?>" data-name="<?php echo esc_attr( $prov['name'] ); ?>">
                        <td><?php echo esc_html( $prov['name'] ); ?></td>
                        <td class="status status--<?php echo esc_attr( $status_code ); ?>" data-status="<?php echo esc_attr( $status_code ); ?>"><?php echo esc_html( $status_label ); ?></td>
                        <td class="msg"><?php echo esc_html( $message ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="ticker" aria-live="polite"></div>
            <button type="button" class="coin-btn" data-loading-label="<?php echo esc_attr( $strings['buttonLoading'] ); ?>">
                <span class="label"><?php echo esc_html( $strings['buttonShortLabel'] ); ?></span>
                <span class="loader" aria-hidden="true"></span>
            </button>
            <p class="microcopy"><?php echo esc_html( $strings['buttonLabel'] ); ?></p>
            <p class="weather"><?php echo esc_html( $strings['microcopy'] ); ?></p>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
