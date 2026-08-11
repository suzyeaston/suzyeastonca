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
    <div class="home-signal-strip__mast">
        <span class="home-signal-strip__eyebrow pixel-font"><?php echo esc_html( 'LIVE // SUZY OS' ); ?></span>
        <span class="home-signal-strip__state"><?php echo esc_html( $highlight ? 'signal detected' : 'systems nominal' ); ?></span>
    </div>

    <a class="home-signal-strip__primary home-signal-strip__product--lo" href="<?php echo esc_url( $lo_url ); ?>">
        <span class="home-signal-strip__prompt" aria-hidden="true">&gt;</span>
        <span class="home-signal-strip__primary-copy">
            <span class="home-signal-strip__label"><?php echo esc_html( 'Lousy Outages' ); ?></span>
            <span class="home-signal-strip__verdict" data-signal-lo-verdict><?php echo esc_html( $verdict ); ?></span>
        </span>
        <span class="home-signal-strip__metrics" aria-label="<?php echo esc_attr( 'Current outage counts' ); ?>">
            <?php if ( $down > 0 ) : ?>
                <span class="home-signal-strip__badge home-signal-strip__badge--down"><?php echo esc_html( str_pad( (string) $down, 2, '0', STR_PAD_LEFT ) . ' down' ); ?></span>
            <?php endif; ?>
            <?php if ( $degraded > 0 ) : ?>
                <span class="home-signal-strip__badge home-signal-strip__badge--degraded"><?php echo esc_html( str_pad( (string) $degraded, 2, '0', STR_PAD_LEFT ) . ' degraded' ); ?></span>
            <?php endif; ?>
        </span>
        <span class="home-signal-strip__action"><?php echo esc_html( 'open status ↗' ); ?></span>
    </a>

    <div class="home-signal-strip__utility">
        <a class="home-signal-strip__utility-item" href="<?php echo esc_url( $pricing ); ?>">
            <span class="home-signal-strip__utility-key"><?php echo esc_html( 'WATCHLISTS' ); ?></span>
            <span class="home-signal-strip__utility-value"><?php echo esc_html( 'provider monitoring' ); ?></span>
        </a>
        <a class="home-signal-strip__utility-item" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">
            <span class="home-signal-strip__utility-key"><?php echo esc_html( 'CONSULTING' ); ?></span>
            <span class="home-signal-strip__utility-value"><?php echo esc_html( 'debug · automate · deep dive' ); ?></span>
        </a>
        <span class="home-signal-strip__utility-item home-signal-strip__utility-item--future">
            <span class="home-signal-strip__utility-key"><?php echo esc_html( 'YVR DATA' ); ?></span>
            <span class="home-signal-strip__utility-value"><?php echo esc_html( 'api + mcp soon' ); ?></span>
        </span>
        <button type="button" class="home-signal-strip__contact" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'CONTACT' ); ?></button>
    </div>
</nav>
