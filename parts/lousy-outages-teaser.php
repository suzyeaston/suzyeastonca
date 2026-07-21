<?php
$feed_url = home_url( '/?feed=lousy_outages_status' );

$teaser_data  = function_exists( 'get_lousy_outages_home_teaser_data' )
    ? get_lousy_outages_home_teaser_data()
    : [
        'headline' => 'All quiet. Suspicious, but fine.',
        'href'     => home_url( '/lousy-outages/' ),
        'status'   => 'clear',
        'footnote' => '',
        'feed_url' => $feed_url,
        'rows'     => [],
        'last_checked' => '',
    ];
$teaser_href = $teaser_data['href'] ?? home_url( '/lousy-outages/' );
$teaser_endpoint = rest_url( 'lousy-outages/v1/summary' );
$teaser_interval = 5 * MINUTE_IN_SECONDS * 1000;
$rows = isset( $teaser_data['rows'] ) && is_array( $teaser_data['rows'] ) ? array_slice( $teaser_data['rows'], 0, 3 ) : [];
$lead = $rows[0] ?? null;
$last_checked = $teaser_data['last_checked'] ?? '';
$active_count = max( 0, array_sum( array_map( static fn( $row ) => (int) preg_replace( '/\D+/', '', (string) ( $row['message'] ?? '1' ) ) ?: 1, $rows ) ) );
$provider_names = [];
foreach ( $rows as $row ) {
    $provider = trim( (string) ( $row['provider'] ?? '' ) );
    if ( '' !== $provider && ! in_array( $provider, $provider_names, true ) ) {
        $provider_names[] = $provider;
    }
}
$affected_provider_count = max( count( $provider_names ), (int) ( $teaser_data['affected_provider_count'] ?? 0 ) );
$other_providers = array_slice( array_values( array_diff( $provider_names, [ (string) ( $lead['provider'] ?? '' ) ] ) ), 0, 2 );
$is_delayed = empty( $rows ) && 'delayed' === (string) ( $teaser_data['status'] ?? '' );
$quip = '';
if ( class_exists( '\SuzyEaston\LousyOutages\PublicCopy' ) ) {
    $quip = \SuzyEaston\LousyOutages\PublicCopy::line( empty( $rows ) ? ( $is_delayed ? 'delayed' : 'clear' ) : 'minor', [
        'provider' => (string) ( $lead['provider'] ?? '' ),
        'severity' => (string) ( $lead['tone'] ?? '' ),
        'message' => (string) ( $lead['message'] ?? '' ),
    ] );
}
?>
<section id="lousy-outages-teaser" class="lo-home-teaser<?php echo esc_attr( empty( $rows ) ? ' lo-home-teaser--clear' : ' lo-home-teaser--active' ); ?>" aria-labelledby="lo-home-heading" data-lo-endpoint="<?php echo esc_url( $teaser_endpoint ); ?>" data-lo-dashboard-url="<?php echo esc_url( $teaser_href ); ?>" data-lo-refresh-interval="<?php echo esc_attr( (string) $teaser_interval ); ?>">
    <div class="lo-home-teaser__titlebar">
        <div>
            <p class="lo-home-kicker"><?php echo esc_html( 'live outage signal' ); ?></p>
            <h2 id="lo-home-heading" class="lo-home-heading"><?php echo esc_html( 'Lousy Outages' ); ?></h2>
        </div>
        <a class="lo-home-dashboard-link" href="<?php echo esc_url( $teaser_href ); ?>">View full status <span aria-hidden="true">→</span></a>
    </div>
    <div class="lo-home-ticker" role="status" aria-live="polite">
        <div class="lo-home-stat"><strong><?php echo esc_html( (string) $active_count ); ?></strong><span>active <?php echo esc_html( 1 === $active_count ? 'event' : 'events' ); ?></span></div>
        <div class="lo-home-stat"><strong><?php echo esc_html( (string) $affected_provider_count ); ?></strong><span>affected <?php echo esc_html( 1 === $affected_provider_count ? 'provider' : 'providers' ); ?></span></div>
        <div class="lo-home-lead">
            <?php if ( $lead ) : ?>
                <strong><?php echo esc_html( (string) ( $lead['provider'] ?? 'Provider' ) ); ?></strong>
                <span><?php echo esc_html( (string) ( $lead['message'] ?? 'Status update' ) ); ?></span>
            <?php else : ?>
                <strong><?php echo esc_html( $is_delayed ? 'Verification delayed' : 'No active incidents' ); ?></strong>
                <span><?php echo esc_html( $last_checked ? 'Last checked: ' . $last_checked : 'Provider checks are quiet.' ); ?></span>
            <?php endif; ?>
        </div>
        <?php if ( $other_providers ) : ?><p class="lo-home-other">Also watching: <?php echo esc_html( implode( ', ', $other_providers ) ); ?></p><?php endif; ?>
    </div>
    <?php if ( $quip ) : ?><p class="lo-home-quip"><?php echo esc_html( $quip ); ?></p><?php endif; ?>
</section>
