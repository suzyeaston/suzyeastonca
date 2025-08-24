<?php
namespace LousyOutages;

add_shortcode( 'lousy_outages', __NAMESPACE__ . '\\render_shortcode' );

function render_shortcode(): string {
    $base = plugin_dir_path( __DIR__ ) . 'assets/';
    wp_enqueue_style(
        'lousy-outages',
        plugins_url( 'assets/lousy-outages.css', dirname( __FILE__ ) ),
        [],
        filemtime( $base . 'lousy-outages.css' )
    );
    wp_enqueue_script(
        'lousy-outages',
        plugins_url( 'assets/lousy-outages.js', dirname( __FILE__ ) ),
        [],
        filemtime( $base . 'lousy-outages.js' ),
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
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
