<?php
$teaser = function_exists( 'get_lousy_outages_home_teaser_data' ) ? get_lousy_outages_home_teaser_data() : [];
$dashboard_url = (string) ( $teaser['dashboard_url'] ?? home_url( '/lousy-outages/' ) );
$teaser_endpoint = rest_url( 'lousy-outages/v1/summary' );
$teaser_interval = 5 * MINUTE_IN_SECONDS * 1000;
$counts = is_array( $teaser['counts'] ?? null ) ? $teaser['counts'] : [];
$lead = is_array( $teaser['lead'] ?? null ) ? $teaser['lead'] : [];
$also = is_array( $teaser['also'] ?? null ) ? $teaser['also'] : [];
$urls = is_array( $teaser['urls'] ?? null ) ? $teaser['urls'] : [];
$tone = sanitize_html_class( (string) ( $teaser['tone'] ?? 'ok' ) );
$down = (int) ( $counts['down'] ?? 0 );
$degraded = (int) ( $counts['degraded'] ?? 0 );
$advisory = (int) ( $counts['advisory'] ?? 0 );
$up = (int) ( $counts['up'] ?? 0 );
$tracked = (int) ( $counts['tracked'] ?? 0 );
?>
<section id="lousy-outages-teaser" class="lo-home-teaser lo-home-teaser--<?php echo esc_attr( $tone ); ?>" aria-labelledby="lo-home-heading" data-lo-endpoint="<?php echo esc_url( $teaser_endpoint ); ?>" data-lo-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>" data-lo-refresh-interval="<?php echo esc_attr( (string) $teaser_interval ); ?>">
    <div class="lo-home-teaser__titlebar">
        <div>
            <p class="lo-home-kicker"><?php echo esc_html( 'live outage signal' ); ?></p>
            <h2 id="lo-home-heading" class="lo-home-heading"><?php echo esc_html( 'Lousy Outages' ); ?></h2>
        </div>
        <a class="lo-home-dashboard-link" href="<?php echo esc_url( $urls['full'] ?? $dashboard_url ); ?>"><?php echo esc_html( 'View full status' ); ?> <span aria-hidden="true">→</span></a>
    </div>

    <p class="lo-home-verdict" data-lo-verdict-line><?php echo esc_html( (string) ( $teaser['verdict_line'] ?? '' ) ); ?></p>
    <p class="lo-home-verdict__sub" data-lo-verdict-sub><?php echo esc_html( (string) ( $teaser['verdict_sub'] ?? '' ) ); ?></p>

    <div class="lo-home-teaser__screen" role="status" aria-live="polite">
        <a class="lo-home-stat lo-home-stat--down" data-lo-stat="down" href="<?php echo esc_url( $urls['active'] ?? $dashboard_url . '#active' ); ?>">
            <strong><?php echo esc_html( str_pad( (string) $down, 2, '0', STR_PAD_LEFT ) ); ?></strong>
            <span><?php echo esc_html( 'DOWN' ); ?></span>
        </a>
        <a class="lo-home-stat lo-home-stat--warn" data-lo-stat="degraded" href="<?php echo esc_url( $urls['degraded'] ?? $dashboard_url . '#degraded' ); ?>">
            <strong><?php echo esc_html( str_pad( (string) $degraded, 2, '0', STR_PAD_LEFT ) ); ?></strong>
            <span><?php echo esc_html( 'DEGRADED' ); ?></span>
        </a>
        <a class="lo-home-stat lo-home-stat--advisory" data-lo-stat="advisory" href="<?php echo esc_url( $urls['advisories'] ?? $dashboard_url . '#advisories' ); ?>">
            <strong><?php echo esc_html( str_pad( (string) $advisory, 2, '0', STR_PAD_LEFT ) ); ?></strong>
            <span><?php echo esc_html( 'ADVISORIES' ); ?></span>
        </a>
        <a class="lo-home-stat lo-home-stat--ok" data-lo-stat="up" href="<?php echo esc_url( $urls['matrix'] ?? $dashboard_url . '#providers' ); ?>">
            <strong><?php echo esc_html( str_pad( (string) $up, 2, '0', STR_PAD_LEFT ) ); ?></strong>
            <span><?php echo esc_html( 'UP' ); ?></span>
        </a>

        <div class="lo-home-lead" data-lo-lead>
            <span class="lo-home-chip lo-home-chip--<?php echo esc_attr( sanitize_html_class( (string) ( $lead['kind'] ?? $tone ) ) ); ?>" data-lo-lead-label><?php echo esc_html( (string) ( $lead['label'] ?? '' ) ); ?></span>
            <a class="lo-home-lead__link" data-lo-lead-link href="<?php echo esc_url( (string) ( $lead['url'] ?? $dashboard_url ) ); ?>">
                <strong data-lo-lead-title><?php echo esc_html( (string) ( $lead['title'] ?? '' ) ); ?></strong>
            </a>
            <span data-lo-lead-summary><?php echo esc_html( (string) ( $lead['summary'] ?? '' ) ); ?></span>
            <?php if ( ! empty( $lead['provider'] ) ) : ?>
                <a class="lo-home-provider-link" data-lo-provider-link href="<?php echo esc_url( (string) ( $lead['section_url'] ?? $dashboard_url ) ); ?>">
                    <span data-lo-lead-provider><?php echo esc_html( (string) $lead['provider'] ); ?></span>
                </a>
            <?php else : ?>
                <span class="lo-home-provider-link" data-lo-lead-provider hidden></span>
            <?php endif; ?>
        </div>

        <?php if ( $also ) : ?>
            <ul class="lo-home-also" data-lo-also>
                <?php foreach ( $also as $item ) : ?>
                    <?php if ( ! is_array( $item ) ) { continue; } ?>
                    <li>
                        <a class="lo-home-also__link lo-home-also__link--<?php echo esc_attr( sanitize_html_class( (string) ( $item['tone'] ?? 'dim' ) ) ); ?>" href="<?php echo esc_url( (string) ( $item['url'] ?? $dashboard_url ) ); ?>">
                            <span class="lo-home-also__label"><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></span>
                            <span class="lo-home-also__title"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <ul class="lo-home-also" data-lo-also hidden></ul>
        <?php endif; ?>

        <p class="lo-home-sync" data-lo-sync>
            <?php
            $sync_bits = [];
            if ( ! empty( $teaser['fetched_label'] ) ) {
                $sync_bits[] = 'Last sync ' . (string) $teaser['fetched_label'];
            }
            if ( $tracked > 0 ) {
                $sync_bits[] = str_pad( (string) $tracked, 2, '0', STR_PAD_LEFT ) . ' tracked';
            }
            echo esc_html( implode( ' · ', $sync_bits ) );
            ?>
        </p>
    </div>

    <p class="lo-home-upgrade"><a class="lo-home-dashboard-link" data-lo-upgrade href="<?php echo esc_url( home_url( '/lousy-outages/pricing/' ) ); ?>"><?php echo esc_html( 'Save your watchlist' ); ?> <span aria-hidden="true">→</span></a></p>
</section>
