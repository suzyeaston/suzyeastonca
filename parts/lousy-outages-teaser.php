<?php
use LousyOutages\I18n;
use LousyOutages\Summary;

$teaser_strings = [
    'teaserCaption' => 'Check if your favourite services are up. Insert coin to refresh.',
    'viewDashboard' => 'View Full Dashboard →',
];

$feed_url     = home_url( '/?feed=lousy_outages_status' ); // Pretty /feed/lousy_outages_status/ works after a permalink flush, but the query form is more reliable.

if ( class_exists( '\\LousyOutages\\I18n' ) ) {
    $locale         = I18n::determine_locale();
    $localized      = I18n::strings( $locale );
    $teaser_strings = array_merge( $teaser_strings, array_intersect_key( $localized, $teaser_strings ) );
}

$latest_incident = null;
$latest_signal = null;
$relative_started = '';
$teaser_href = home_url( '/lousy-outages/' );

if ( class_exists( '\\LousyOutages\\Summary' ) ) {
    $current = Summary::current();
    $kind = $current['kind'] ?? ( ! empty( $current['hasIncident'] ) ? 'outage' : 'clear' );
    if ( 'outage' === $kind ) {
        $latest_incident = (object) [
            'provider'   => $current['provider'] ?? '',
            'title'      => $current['title'] ?? '',
            'relative'   => $current['relative'] ?? '',
            'started_at' => $current['started_at'] ?? '',
        ];
        $relative_started = $latest_incident->relative;
        if ( ! empty( $current['href'] ) ) {
            $teaser_href = $current['href'];
        }
    } elseif ( 'signal' === $kind ) {
        $latest_signal = (object) [
            'provider'   => $current['provider'] ?? '',
            'status'     => $current['status'] ?? '',
            'relative'   => $current['relative'] ?? '',
            'started_at' => $current['started_at'] ?? '',
        ];
        $relative_started = $latest_signal->relative;
        if ( ! empty( $current['href'] ) ) {
            $teaser_href = $current['href'];
        }
    }
}
?>
<section id="lousy-outages-teaser" class="lo-home-teaser" aria-live="polite">
    <h2 class="lo-home-heading">Lousy Outages</h2>

    <?php if ( $latest_incident ) : ?>
        <p class="lo-home-pacman-alert" data-incident-start="<?php echo esc_attr( $latest_incident->started_at ); ?>" data-relative="<?php echo esc_attr( $relative_started ); ?>">
            <span class="lo-pacman" aria-hidden="true"></span>
            <span class="lo-pacman-dots" aria-hidden="true"></span>
            <span class="lo-alert-text">
                Outage detected:
                <strong><?php echo esc_html( $latest_incident->provider ); ?></strong>
                — <?php echo esc_html( $latest_incident->title ); ?>
                <?php if ( $relative_started ) : ?>
                    (started <?php echo esc_html( $relative_started ); ?>)
                <?php endif; ?>
            </span>
        </p>
    <?php elseif ( $latest_signal ) : ?>
        <p class="lo-home-pacman-alert lo-home-pacman-alert--signal" data-signal-start="<?php echo esc_attr( $latest_signal->started_at ); ?>" data-relative="<?php echo esc_attr( $relative_started ); ?>">
            <span class="lo-pacman" aria-hidden="true"></span>
            <span class="lo-pacman-dots" aria-hidden="true"></span>
            <span class="lo-alert-text">
                Signal detected:
                <strong><?php echo esc_html( $latest_signal->provider ); ?></strong>
                — <?php echo esc_html( $latest_signal->status ); ?>.
                No active incident posted yet.
                <?php if ( $relative_started ) : ?>
                    (checked <?php echo esc_html( $relative_started ); ?>)
                <?php endif; ?>
            </span>
        </p>
    <?php else : ?>
        <p class="lo-home-pacman-alert lo-home-pacman-alert--clear">
            <span class="lo-pacman" aria-hidden="true"></span>
            <span class="lo-pacman-dots" aria-hidden="true"></span>
            <span class="lo-alert-text">
                All clear: no active incidents right now. Insert coin to refresh.
            </span>
        </p>
    <?php endif; ?>

    <p class="caption"><?php echo esc_html( $teaser_strings['teaserCaption'] ); ?></p>

    <a class="lo-home-teaser-link" href="<?php echo esc_url( $teaser_href ); ?>"><?php echo esc_html( $teaser_strings['viewDashboard'] ); ?></a>

    <div class="lo-actions lo-actions--rss">
      <a class="lo-link" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener">
        <svg class="lo-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M6 17a2 2 0 11.001 3.999A2 2 0 016 17zm-2-7v3a8 8 0 018 8h3c0-6.075-4.925-11-11-11zm0-5v3c9.389 0 17 7.611 17 17h3C24 13.85 10.15 0 4 0z"/>
        </svg>
        Subscribe (RSS)
      </a>
    </div>
</section>
