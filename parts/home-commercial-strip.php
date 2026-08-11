<?php
$teaser  = function_exists( 'get_lousy_outages_home_teaser_data' ) ? get_lousy_outages_home_teaser_data() : [];
$lo_url  = (string) ( $teaser['dashboard_url'] ?? home_url( '/lousy-outages/' ) );
$copy    = function_exists( 'se_home_lo_flyover_copy' ) ? se_home_lo_flyover_copy( $teaser ) : [
    'banner'    => 'LOUSY OUTAGES → monitoring provider signals',
    'report'    => 'latest report: no major active incident summary available right now',
    'tone'      => 'ok',
    'highlight' => false,
];
$tone      = sanitize_html_class( (string) ( $copy['tone'] ?? 'ok' ) );
$highlight = ! empty( $copy['highlight'] );
$banner    = (string) ( $copy['banner'] ?? 'LOUSY OUTAGES → monitoring provider signals' );
$report    = (string) ( $copy['report'] ?? 'latest report: no major active incident summary available right now' );
?>
<aside
    class="home-lo-flyover home-lo-flyover--<?php echo esc_attr( $tone ); ?><?php echo $highlight ? ' home-lo-flyover--hot' : ''; ?>"
    aria-label="<?php echo esc_attr( 'Lousy Outages live signal' ); ?>"
    data-lo-flyover
    data-lo-endpoint="<?php echo esc_url( rest_url( 'lousy-outages/v1/summary' ) ); ?>"
    data-lo-dashboard-url="<?php echo esc_url( $lo_url ); ?>"
>
    <div class="home-lo-flyover__scene" aria-hidden="true">
        <div class="home-lo-flyover__sky">
            <span class="home-lo-flyover__star home-lo-flyover__star--1"></span>
            <span class="home-lo-flyover__star home-lo-flyover__star--2"></span>
            <span class="home-lo-flyover__star home-lo-flyover__star--3"></span>
            <span class="home-lo-flyover__star home-lo-flyover__star--4"></span>
            <span class="home-lo-flyover__star home-lo-flyover__star--5"></span>
            <span class="home-lo-flyover__signal home-lo-flyover__signal--1"></span>
            <span class="home-lo-flyover__signal home-lo-flyover__signal--2"></span>
            <span class="home-lo-flyover__signal home-lo-flyover__signal--3"></span>
            <div class="home-lo-flyover__horizon">
                <div class="home-lo-flyover__mountains"></div>
                <div class="home-lo-flyover__skyline"></div>
            </div>
        </div>

        <div class="home-lo-flyover__track">
            <a
                class="home-lo-flyover__craft"
                href="<?php echo esc_url( $lo_url ); ?>"
                tabindex="-1"
                aria-hidden="true"
            >
                <span class="home-lo-flyover__plane" aria-hidden="true">
                    <span class="home-lo-flyover__plane-body"></span>
                    <span class="home-lo-flyover__plane-wing"></span>
                    <span class="home-lo-flyover__plane-tail"></span>
                    <span class="home-lo-flyover__plane-prop"></span>
                </span>
                <span class="home-lo-flyover__tether" aria-hidden="true"></span>
                <span class="home-lo-flyover__banner-skin">
                    <span class="home-lo-flyover__banner-text" data-lo-flyover-banner><?php echo esc_html( $banner ); ?></span>
                </span>
            </a>
        </div>
    </div>

    <div class="home-lo-flyover__panel">
        <p class="home-lo-flyover__mast pixel-font">
            <span class="home-lo-flyover__mast-label"><?php echo esc_html( 'live transmission' ); ?></span>
            <span class="home-lo-flyover__mast-state"><?php echo esc_html( $highlight ? 'signal detected' : 'monitoring' ); ?></span>
        </p>
        <a
            class="home-lo-flyover__primary"
            href="<?php echo esc_url( $lo_url ); ?>"
            aria-label="<?php echo esc_attr( $banner . ' — view Lousy Outages status' ); ?>"
        >
            <span class="home-lo-flyover__prompt" aria-hidden="true">&gt;</span>
            <span class="home-lo-flyover__primary-copy">
                <span class="home-lo-flyover__banner-line" data-lo-flyover-banner><?php echo esc_html( $banner ); ?></span>
            </span>
            <span class="home-lo-flyover__cta"><?php echo esc_html( 'open status ↗' ); ?></span>
        </a>
        <p class="home-lo-flyover__report" data-lo-flyover-report><?php echo esc_html( $report ); ?></p>
        <a class="home-lo-flyover__secondary" href="<?php echo esc_url( $lo_url ); ?>"><?php echo esc_html( 'view outage intel ↗' ); ?></a>
    </div>
</aside>
