<?php
$teaser     = function_exists( 'get_lousy_outages_home_teaser_data' ) ? get_lousy_outages_home_teaser_data() : [];
$lo_url     = home_url( '/lousy-outages/' );
$pricing    = home_url( '/lousy-outages/pricing/' );
$verdict    = (string) ( $teaser['verdict_line'] ?? 'Independent outage intel for AI + creative stacks' );
$tone       = sanitize_html_class( (string) ( $teaser['tone'] ?? 'ok' ) );
$counts     = is_array( $teaser['counts'] ?? null ) ? $teaser['counts'] : [];
$down       = (int) ( $counts['down'] ?? 0 );
$degraded   = (int) ( $counts['degraded'] ?? 0 );
$highlight  = $down > 0 || $degraded > 0 || in_array( $tone, [ 'down', 'warn', 'advisory', 'degraded', 'bad' ], true );
?>
<nav class="home-signal-strip home-signal-strip--<?php echo esc_attr( $tone ); ?><?php echo $highlight ? ' home-signal-strip--hot' : ''; ?>" aria-label="<?php echo esc_attr( 'Products and live signals' ); ?>">
    <a class="home-signal-strip__product home-signal-strip__product--lo" href="<?php echo esc_url( $lo_url ); ?>">
        <span class="home-signal-strip__label pixel-font"><?php echo esc_html( 'lousy outages' ); ?></span>
        <span class="home-signal-strip__verdict" data-signal-lo-verdict><?php echo esc_html( $verdict ); ?></span>
        <?php if ( $down > 0 || $degraded > 0 ) : ?>
            <span class="home-signal-strip__badge pixel-font">
                <?php
                if ( $down > 0 ) {
                    echo esc_html( str_pad( (string) $down, 2, '0', STR_PAD_LEFT ) . ' down' );
                } elseif ( $degraded > 0 ) {
                    echo esc_html( str_pad( (string) $degraded, 2, '0', STR_PAD_LEFT ) . ' degraded' );
                }
                ?>
            </span>
        <?php endif; ?>
    </a>
    <span class="home-signal-strip__pipe" aria-hidden="true">·</span>
    <a class="home-signal-strip__link pixel-font" href="<?php echo esc_url( $pricing ); ?>"><?php echo esc_html( 'watchlists' ); ?></a>
    <span class="home-signal-strip__pipe" aria-hidden="true">·</span>
    <span class="home-signal-strip__future pixel-font"><?php echo esc_html( 'yvr data layer — api + mcp soon' ); ?></span>
    <button type="button" class="home-signal-strip__contact pixel-font" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'contact' ); ?></button>
</nav>
