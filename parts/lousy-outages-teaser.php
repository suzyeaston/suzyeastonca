<?php
$feed_url = home_url( '/?feed=lousy_outages_status' ); // Pretty /feed/lousy_outages_status/ works after a permalink flush, but the query form is more reliable.

$teaser_data  = function_exists( 'get_lousy_outages_home_teaser_data' )
    ? get_lousy_outages_home_teaser_data()
    : [
        'headline' => 'All clear — no active outages right now.',
        'href'     => home_url( '/lousy-outages/' ),
        'status'   => 'clear',
        'footnote' => '',
        'feed_url' => $feed_url,
        'rows'     => [],
    ];
$teaser_href = $teaser_data['href'] ?? home_url( '/lousy-outages/' );
$rows = isset( $teaser_data['rows'] ) && is_array( $teaser_data['rows'] ) ? $teaser_data['rows'] : [];
?>
<section id="lousy-outages-teaser" class="lo-home-teaser">
    <div class="lo-home-teaser__titlebar">
        <h2 class="lo-home-heading">lousy outages</h2>
        <span class="lo-home-status-light<?php echo esc_attr( empty( $rows ) ? ' lo-home-status-light--clear' : ' lo-home-status-light--alert' ); ?>">
            <span class="screen-reader-text"><?php echo esc_html( empty( $rows ) ? 'No active alerts' : 'Active alerts' ); ?></span>
        </span>
    </div>

    <div class="lo-home-teaser__screen">
        <?php if ( empty( $rows ) ) : ?>
            <p class="lo-home-empty">all quiet. suspicious, but fine.</p>
        <?php else : ?>
            <ul class="lo-home-alert-list">
                <?php foreach ( $rows as $row ) : ?>
                    <li class="lo-home-alert lo-home-alert--<?php echo esc_attr( $row['tone'] ?? 'unknown' ); ?>">
                        <span class="lo-home-alert__label"><?php echo esc_html( $row['label'] ?? 'Status' ); ?></span>
                        <a class="lo-home-alert__body" href="<?php echo esc_url( $row['href'] ?? $teaser_href ); ?>">
                            <strong><?php echo esc_html( $row['provider'] ?? 'Unknown provider' ); ?></strong>
                            <span><?php echo esc_html( $row['message'] ?? '' ); ?></span>
                        </a>
                        <?php if ( ! empty( $row['time'] ) ) : ?>
                            <time class="lo-home-alert__time"><?php echo esc_html( $row['time'] ); ?></time>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <a class="lo-home-dashboard-link" href="<?php echo esc_url( $teaser_href ); ?>">open dashboard <span aria-hidden="true">→</span></a>
</section>
