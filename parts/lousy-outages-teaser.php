<?php
$feed_url = home_url( '/?feed=lousy_outages_status' );

$teaser_data  = function_exists( 'get_lousy_outages_home_teaser_data' )
    ? get_lousy_outages_home_teaser_data()
    : [
        'headline' => 'all quiet. suspicious, but fine.',
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
$rows = isset( $teaser_data['rows'] ) && is_array( $teaser_data['rows'] ) ? array_slice( $teaser_data['rows'], 0, 5 ) : [];
$last_checked = $teaser_data['last_checked'] ?? '';
$active_count = count( $rows );
$provider_names = [];
foreach ( $rows as $row ) {
    $provider = trim( (string) ( $row['provider'] ?? '' ) );
    if ( '' !== $provider && ! in_array( $provider, $provider_names, true ) ) {
        $provider_names[] = $provider;
    }
}
$provider_summary = implode( ' + ', array_slice( $provider_names, 0, 3 ) );
if ( count( $provider_names ) > 3 ) {
    $provider_summary .= ' +' . ( count( $provider_names ) - 3 ) . ' more';
}
?>
<section id="lousy-outages-teaser" class="lo-home-teaser<?php echo esc_attr( empty( $rows ) ? ' lo-home-teaser--clear' : ' lo-home-teaser--active' ); ?>" aria-labelledby="lo-home-heading" data-lo-endpoint="<?php echo esc_url( $teaser_endpoint ); ?>" data-lo-dashboard-url="<?php echo esc_url( $teaser_href ); ?>" data-lo-refresh-interval="<?php echo esc_attr( (string) $teaser_interval ); ?>">
    <div class="lo-home-teaser__titlebar">
        <div class="lo-home-title-copy">
            <p class="lo-home-kicker"><?php echo esc_html( 'arcade system monitor' ); ?></p>
            <h2 id="lo-home-heading" class="lo-home-heading"><?php echo esc_html( 'Live outage signal' ); ?></h2>
        </div>
        <span class="lo-home-status-light<?php echo esc_attr( empty( $rows ) ? ' lo-home-status-light--clear' : ' lo-home-status-light--alert' ); ?>">
            <span class="screen-reader-text"><?php echo esc_html( empty( $rows ) ? 'No active provider incidents' : 'Active provider incidents or degraded signals' ); ?></span>
        </span>
    </div>

    <div class="lo-home-teaser__screen">
        <?php if ( empty( $rows ) ) : ?>
            <div class="lo-home-empty">
                <p><?php echo esc_html( 'all quiet. suspicious, but fine.' ); ?></p>
                <p><?php echo esc_html( $last_checked ? 'last checked: ' . $last_checked : 'last checked: recently' ); ?></p>
            </div>
        <?php else : ?>
            <div class="lo-home-live-band" role="status" aria-live="polite">
                <span class="lo-home-live-band__dot" aria-hidden="true"></span>
                <div>
                    <p class="lo-home-live-band__label">LIVE OUTAGE SIGNAL</p>
                    <p class="lo-home-live-band__count"><?php echo esc_html( $active_count . ' latest incident ' . ( 1 === $active_count ? 'signal' : 'signals' ) ); ?></p>
                    <?php if ( $provider_summary ) : ?>
                        <p class="lo-home-live-band__providers"><?php echo esc_html( $provider_summary ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <ul class="lo-home-alert-list">
                <?php foreach ( $rows as $row ) : ?>
                    <li class="lo-home-alert lo-home-alert--<?php echo esc_attr( $row['tone'] ?? 'unknown' ); ?>">
                        <div class="lo-home-alert__meta">
                            <strong class="lo-home-alert__provider"><?php echo esc_html( $row['provider'] ?? 'Unknown provider' ); ?></strong>
                            <span class="lo-home-alert__status"><?php echo esc_html( $row['label'] ?? 'Status' ); ?></span>
                        </div>
                        <p class="lo-home-alert__body">
                            <?php echo esc_html( $row['message'] ?? 'Status update' ); ?>
                        </p>
                        <div class="lo-home-alert__times">
                            <?php if ( ! empty( $row['started'] ) ) : ?>
                                <time class="lo-home-alert__time" datetime="<?php echo esc_attr( $row['started'] ); ?>"><?php echo esc_html( 'Started ' . $row['started'] ); ?></time>
                            <?php endif; ?>
                            <?php if ( ! empty( $row['updated'] ) ) : ?>
                                <time class="lo-home-alert__time" datetime="<?php echo esc_attr( $row['updated'] ); ?>"><?php echo esc_html( 'Updated ' . $row['updated'] ); ?></time>
                            <?php endif; ?>
                        </div>
                        <a class="lo-home-alert__details" href="<?php echo esc_url( $row['href'] ?? $teaser_href ); ?>">Details</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <a class="lo-home-dashboard-link" href="<?php echo esc_url( $teaser_href ); ?>">check status <span aria-hidden="true">→</span></a>
</section>
