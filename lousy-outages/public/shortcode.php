<?php
namespace LousyOutages;

add_shortcode( 'lousy_outages', __NAMESPACE__ . '\\render_shortcode' );

function render_shortcode(): string {
    $base_path = plugin_dir_path( __DIR__ ) . 'assets/';
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
    wp_localize_script( 'lousy-outages', 'LousyOutages', [ 'endpoint' => rest_url( 'lousy-outages/v1/status' ) ] );

    $store     = new Store();
    $providers = Providers::list();
    $states    = $store->get_all();

    ob_start();
    ?>
    <div class="lo-arcade">
        <div id="lousy-outages" class="lousy-outages-list" aria-live="polite">
            <p class="last-updated">Updated: <span></span></p>
            <table>
                <thead><tr><th>Provider</th><th>Status</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ( $providers as $id => $prov ) :
                    $state   = $states[ $id ] ?? [ 'status' => 'unknown', 'message' => '' ];
                    $status  = esc_html( $state['status'] );
                    $message = esc_html( $state['message'] );
                    ?>
                    <tr data-id="<?php echo esc_attr( $id ); ?>">
                        <td><?php echo esc_html( $prov['name'] ); ?></td>
                        <td class="status <?php echo $status; ?>"><?php echo $status; ?></td>
                        <td class="msg"><?php echo $message; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="ticker" aria-live="polite"></div>
            <button type="button" class="coin-btn">Coin</button>
            <p class="microcopy">Vancouver weather: cloudy with a chance of outages.</p>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
