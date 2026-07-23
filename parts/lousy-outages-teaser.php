<?php
$feed_url = home_url( '/?feed=lousy_outages_status' );
$teaser_data = function_exists( 'get_lousy_outages_home_teaser_data' ) ? get_lousy_outages_home_teaser_data() : [];
$dashboard_url = $teaser_data['dashboard_url'] ?? home_url( '/lousy-outages/' );
$teaser_endpoint = rest_url( 'lousy-outages/v1/summary' );
$teaser_interval = 5 * MINUTE_IN_SECONDS * 1000;
$outage_count = (int) ( $teaser_data['outage_event_count'] ?? 0 );
$notice_count = (int) ( $teaser_data['official_notice_count'] ?? 0 );
$provider_count = (int) ( $teaser_data['affected_provider_count'] ?? 0 );
$lead_title = (string) ( $teaser_data['lead_event_title'] ?? '' );
$summary = (string) ( $teaser_data['summary'] ?? '' );
$provider_name = (string) ( $teaser_data['provider_name'] ?? '' );
$lifecycle = (string) ( $teaser_data['lifecycle_state'] ?? '' );
$other_providers = is_array( $teaser_data['other_affected_providers'] ?? null ) ? $teaser_data['other_affected_providers'] : [];
$is_active = $outage_count > 0;
?>
<section id="lousy-outages-teaser" class="lo-home-teaser<?php echo esc_attr( $is_active ? ' lo-home-teaser--active' : ' lo-home-teaser--clear' ); ?>" aria-labelledby="lo-home-heading" data-lo-endpoint="<?php echo esc_url( $teaser_endpoint ); ?>" data-lo-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>" data-lo-refresh-interval="<?php echo esc_attr( (string) $teaser_interval ); ?>">
    <div class="lo-home-teaser__titlebar">
        <div>
            <p class="lo-home-kicker"><?php echo esc_html( 'live outage signal' ); ?></p>
            <h2 id="lo-home-heading" class="lo-home-heading"><?php echo esc_html( 'Lousy Outages' ); ?></h2>
        </div>
        <a class="lo-home-dashboard-link" href="<?php echo esc_url( $dashboard_url ); ?>">View full status <span aria-hidden="true">→</span></a>
    </div>
    <div class="lo-home-ticker lo-home-teaser__screen" role="status" aria-live="polite">
        <a class="lo-home-stat" data-lo-stat="outages" href="<?php echo esc_url( $teaser_data['active_url'] ?? ( $dashboard_url . '#active-incidents' ) ); ?>"><strong><?php echo esc_html( (string) $outage_count ); ?></strong><span><?php echo esc_html( 'Outage ' . ( 1 === $outage_count ? 'event' : 'events' ) ); ?></span></a>
        <a class="lo-home-stat" data-lo-stat="providers" href="<?php echo esc_url( $teaser_data['affected_url'] ?? ( $dashboard_url . '#monitored-services' ) ); ?>"><strong><?php echo esc_html( (string) $provider_count ); ?></strong><span><?php echo esc_html( 'Affected ' . ( 1 === $provider_count ? 'provider' : 'providers' ) ); ?></span></a>
        <a class="lo-home-stat" data-lo-stat="notices" href="<?php echo esc_url( $teaser_data['active_url'] ?? ( $dashboard_url . '#active-incidents' ) ); ?>"><strong><?php echo esc_html( (string) $notice_count ); ?></strong><span><?php echo esc_html( 'Official ' . ( 1 === $notice_count ? 'notice' : 'notices' ) ); ?></span></a>
        <div class="lo-home-lead" data-lo-lead>
            <?php if ( $is_active ) : ?>
                <a class="lo-home-lead__link" data-lo-lead-link href="<?php echo esc_url( $teaser_data['lead_url'] ?? $dashboard_url ); ?>"><strong data-lo-lead-title><?php echo esc_html( $lead_title ?: 'Active provider incident' ); ?></strong></a>
                <span data-lo-lead-summary><?php echo esc_html( $summary ?: 'Latest official update is available on the full dashboard.' ); ?></span>
                <a class="lo-home-provider-link" data-lo-provider-link href="<?php echo esc_url( $teaser_data['provider_url'] ?? $dashboard_url ); ?>"><span data-lo-lead-provider><?php echo esc_html( trim( $provider_name . ( $lifecycle ? ' · ' . $lifecycle : '' ) ) ); ?></span></a>
            <?php else : ?>
                <strong><?php echo esc_html( 'No active incidents' ); ?></strong>
                <span><?php echo esc_html( ! empty( $teaser_data['last_checked'] ) ? 'Last checked: ' . $teaser_data['last_checked'] : 'Provider checks are quiet.' ); ?></span>
            <?php endif; ?>
        </div>
        <?php if ( $other_providers ) : ?><p class="lo-home-other">Also watching: <?php foreach ( $other_providers as $i => $provider ) : ?><?php if ( $i ) : ?>, <?php endif; ?><a href="<?php echo esc_url( $provider['href'] ?? $dashboard_url ); ?>"><?php echo esc_html( (string) ( $provider['name'] ?? 'Provider' ) ); ?></a><?php endforeach; ?></p><?php endif; ?>
    </div>
</section>
