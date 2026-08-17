<?php
$teaser = function_exists( 'get_lousy_outages_home_teaser_data' ) ? get_lousy_outages_home_teaser_data() : [];
$dashboard_url = (string) ( $teaser['dashboard_url'] ?? home_url( '/lousy-outages/' ) );
$teaser_endpoint = rest_url( 'lousy-outages/v1/summary' );
$teaser_interval = 5 * MINUTE_IN_SECONDS * 1000;
$counts = is_array( $teaser['counts'] ?? null ) ? $teaser['counts'] : [];
$tone = sanitize_html_class( (string) ( $teaser['tone'] ?? 'ok' ) );
$down = (int) ( $counts['down'] ?? 0 );
$degraded = (int) ( $counts['degraded'] ?? 0 );
$advisory = (int) ( $counts['advisory'] ?? 0 );
$up = (int) ( $counts['up'] ?? 0 );
$tracked = (int) ( $counts['tracked'] ?? 0 );
$lo_version = defined( 'LOUSY_OUTAGES_VERSION' ) ? LOUSY_OUTAGES_VERSION : '0.5.7';
$sync_bits = [];
if ( ! empty( $teaser['fetched_label'] ) ) {
    $sync_bits[] = 'Last sync ' . (string) $teaser['fetched_label'];
}
if ( $tracked > 0 ) {
    $sync_bits[] = str_pad( (string) $tracked, 2, '0', STR_PAD_LEFT ) . ' tracked';
}
$sync_line = implode( ' · ', $sync_bits );
?>
<section id="feature-previews" class="home-feature-previews crt-block" aria-labelledby="feature-previews-title">
    <p class="home-section-kicker pixel-font"><?php echo esc_html( 'live previews' ); ?></p>
    <h2 id="feature-previews-title" class="pixel-font"><?php echo esc_html( 'FEATURE BUILDS' ); ?></h2>
    <p class="home-feature-previews__lede"><?php echo esc_html( 'Terminal windows into the stuff that actually ships. No brochureware.' ); ?></p>

    <div class="home-feature-previews__grid">
        <article
            class="home-feature-preview home-feature-preview--live home-feature-preview--<?php echo esc_attr( $tone ); ?>"
            aria-labelledby="home-preview-lo-title"
            data-lo-endpoint="<?php echo esc_url( $teaser_endpoint ); ?>"
            data-lo-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>"
            data-lo-refresh-interval="<?php echo esc_attr( (string) $teaser_interval ); ?>"
        >
            <header class="home-feature-preview__head">
                <p class="home-feature-preview__label pixel-font"><?php echo esc_html( 'status weather' ); ?></p>
                <h3 id="home-preview-lo-title" class="home-feature-preview__title pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3>
                <p class="home-feature-preview__tagline"><?php echo esc_html( 'Official status pages, read for you every 15 minutes. No spin, no vibes.' ); ?></p>
            </header>

            <div class="home-feature-preview__terminal" role="status" aria-live="polite">
                <div class="home-feature-preview__scan" aria-hidden="true"></div>
                <div class="home-feature-preview__shell">
                    <div class="home-feature-preview__shell-bar pixel-font">
                        <span class="home-feature-preview__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="home-feature-preview__path">lousy-outages@yvr — status --all</span>
                        <span class="home-feature-preview__build">build <?php echo esc_html( $lo_version ); ?></span>
                    </div>
                    <div class="home-feature-preview__shell-body">
                        <p class="home-feature-preview__prompt pixel-font"><span class="home-feature-preview__sigil">$</span> watch --every=15m ./poll-official-status<span class="home-feature-preview__caret" aria-hidden="true"></span></p>
                        <p class="home-feature-preview__verdict home-feature-preview__verdict--<?php echo esc_attr( $tone ); ?>" data-lo-preview-verdict><?php echo esc_html( (string) ( $teaser['verdict_line'] ?? '' ) ); ?></p>
                        <p class="home-feature-preview__verdict-sub" data-lo-preview-verdict-sub><?php echo esc_html( (string) ( $teaser['verdict_sub'] ?? '' ) ); ?></p>
                        <p class="home-feature-preview__meta" data-lo-preview-sync><?php echo esc_html( $sync_line ); ?></p>
                    </div>
                </div>

                <div class="home-feature-preview__readout" aria-label="<?php echo esc_attr( 'Status totals' ); ?>">
                    <div class="home-feature-preview__cell home-feature-preview__cell--down" data-lo-preview-stat="down">
                        <span class="home-feature-preview__value"><?php echo esc_html( str_pad( (string) $down, 2, '0', STR_PAD_LEFT ) ); ?></span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'DOWN' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--warn" data-lo-preview-stat="degraded">
                        <span class="home-feature-preview__value"><?php echo esc_html( str_pad( (string) $degraded, 2, '0', STR_PAD_LEFT ) ); ?></span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'DEGRADED' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--advisory" data-lo-preview-stat="advisory">
                        <span class="home-feature-preview__value"><?php echo esc_html( str_pad( (string) $advisory, 2, '0', STR_PAD_LEFT ) ); ?></span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'ADVISORIES' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--ok" data-lo-preview-stat="up">
                        <span class="home-feature-preview__value"><?php echo esc_html( str_pad( (string) $up, 2, '0', STR_PAD_LEFT ) ); ?></span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'UP' ); ?></span>
                    </div>
                </div>
            </div>

            <a class="pixel-button home-feature-preview__cta" href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html( 'Check status' ); ?></a>
        </article>

        <article class="home-feature-preview home-feature-preview--static" aria-labelledby="home-preview-gastown-title">
            <header class="home-feature-preview__head">
                <p class="home-feature-preview__label pixel-font"><?php echo esc_html( 'civic arcade world' ); ?></p>
                <h3 id="home-preview-gastown-title" class="home-feature-preview__title pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3>
                <p class="home-feature-preview__tagline"><?php echo esc_html( 'Open data, street mood, route logic. A Vancouver corridor you can walk.' ); ?></p>
            </header>

            <div class="home-feature-preview__terminal">
                <div class="home-feature-preview__scan" aria-hidden="true"></div>
                <div class="home-feature-preview__shell">
                    <div class="home-feature-preview__shell-bar pixel-font">
                        <span class="home-feature-preview__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="home-feature-preview__path">gastown@yvr — sim --corridor</span>
                        <span class="home-feature-preview__build">build 0.3.1</span>
                    </div>
                    <div class="home-feature-preview__shell-body">
                        <p class="home-feature-preview__prompt pixel-font"><span class="home-feature-preview__sigil">$</span> ./walk --from=water-st<span class="home-feature-preview__caret" aria-hidden="true"></span></p>
                        <p class="home-feature-preview__verdict home-feature-preview__verdict--ok"><?php echo esc_html( 'CORRIDOR LIVE' ); ?></p>
                        <p class="home-feature-preview__verdict-sub"><?php echo esc_html( 'Civic tiles loaded. Steam clock ticking. Pigeons hostile.' ); ?></p>
                        <p class="home-feature-preview__meta"><?php echo esc_html( 'Open data · arcade physics · mood engine' ); ?></p>
                    </div>
                </div>

                <div class="home-feature-preview__readout" aria-hidden="true">
                    <div class="home-feature-preview__cell home-feature-preview__cell--ok">
                        <span class="home-feature-preview__value">12</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'STOPS' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--warn">
                        <span class="home-feature-preview__value">03</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'EVENTS' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--dim">
                        <span class="home-feature-preview__value">47</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'TILES' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--dim">
                        <span class="home-feature-preview__value">01</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'MOOD' ); ?></span>
                    </div>
                </div>
            </div>

            <a class="pixel-button home-feature-preview__cta" href="<?php echo esc_url( home_url( '/gastown-sim/' ) ); ?>"><?php echo esc_html( 'Walk Gastown' ); ?></a>
        </article>

        <article class="home-feature-preview home-feature-preview--static" aria-labelledby="home-preview-ppp-title">
            <header class="home-feature-preview__head">
                <p class="home-feature-preview__label pixel-font"><?php echo esc_html( 'hockey arcade' ); ?></p>
                <h3 id="home-preview-ppp-title" class="home-feature-preview__title pixel-font"><?php echo esc_html( 'Pacific Power Play' ); ?></h3>
                <p class="home-feature-preview__tagline"><?php echo esc_html( '1993–94 Coliseum cabinet. Tap Bure, Linden, McLean. Drop the puck.' ); ?></p>
            </header>

            <div class="home-feature-preview__terminal">
                <div class="home-feature-preview__scan" aria-hidden="true"></div>
                <div class="home-feature-preview__shell">
                    <div class="home-feature-preview__shell-bar pixel-font">
                        <span class="home-feature-preview__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="home-feature-preview__path">ppp@yvr — arcade --period=1</span>
                        <span class="home-feature-preview__build">build 1.0.0</span>
                    </div>
                    <div class="home-feature-preview__shell-body">
                        <p class="home-feature-preview__prompt pixel-font"><span class="home-feature-preview__sigil">$</span> ./drop-puck --line=second<span class="home-feature-preview__caret" aria-hidden="true"></span></p>
                        <p class="home-feature-preview__verdict home-feature-preview__verdict--warn"><?php echo esc_html( 'PUCK DROPPED' ); ?></p>
                        <p class="home-feature-preview__verdict-sub"><?php echo esc_html( 'Second line on ice. Rain on the glass. Crowd loud.' ); ?></p>
                        <p class="home-feature-preview__meta"><?php echo esc_html( 'Browser cabinet · 2P local · rain FX' ); ?></p>
                    </div>
                </div>

                <div class="home-feature-preview__readout" aria-hidden="true">
                    <div class="home-feature-preview__cell home-feature-preview__cell--ok">
                        <span class="home-feature-preview__value">02</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'GOALS' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--warn">
                        <span class="home-feature-preview__value">14</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'SHOTS' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--down">
                        <span class="home-feature-preview__value">01</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'PIM' ); ?></span>
                    </div>
                    <div class="home-feature-preview__cell home-feature-preview__cell--dim">
                        <span class="home-feature-preview__value">P1</span>
                        <span class="home-feature-preview__stat-label pixel-font"><?php echo esc_html( 'PERIOD' ); ?></span>
                    </div>
                </div>
            </div>

            <a class="pixel-button home-feature-preview__cta" href="<?php echo esc_url( home_url( '/pacific-power-play/' ) ); ?>"><?php echo esc_html( 'Play game' ); ?></a>
        </article>
    </div>
</section>
