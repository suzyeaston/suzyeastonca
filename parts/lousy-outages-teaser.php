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
$rows = isset( $teaser_data['rows'] ) && is_array( $teaser_data['rows'] ) ? array_slice( $teaser_data['rows'], 0, 4 ) : [];
$last_checked = $teaser_data['last_checked'] ?? '';
?>
<section id="lousy-outages-teaser" class="lo-home-teaser" aria-labelledby="lo-home-heading">
    <div class="lo-home-teaser__titlebar">
        <p class="lo-home-kicker">lousy outages</p>
        <h2 id="lo-home-heading" class="lo-home-heading">status board for modern chaos</h2>
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
            <ul class="lo-home-alert-list">
                <?php foreach ( $rows as $row ) : ?>
                    <li class="lo-home-alert lo-home-alert--<?php echo esc_attr( $row['tone'] ?? 'unknown' ); ?>">
                        <div class="lo-home-alert__meta">
                            <strong><?php echo esc_html( $row['provider'] ?? 'Unknown provider' ); ?></strong>
                            <span><?php echo esc_html( $row['label'] ?? 'Status' ); ?></span>
                        </div>
                        <a class="lo-home-alert__body" href="<?php echo esc_url( $row['href'] ?? $teaser_href ); ?>">
                            <?php echo esc_html( $row['message'] ?? 'Status update' ); ?>
                        </a>
                        <?php if ( ! empty( $row['time'] ) ) : ?>
                            <time class="lo-home-alert__time"><?php echo esc_html( $row['time'] ); ?></time>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <a class="lo-home-dashboard-link" href="<?php echo esc_url( $teaser_href ); ?>">check status <span aria-hidden="true">→</span></a>
</section>
