<?php
use LousyOutages\I18n;

$teaser_strings = [
    'teaserCaption' => 'Check if your favourite services are up. Insert coin to refresh.',
    'viewDashboard' => 'View full dashboard',
    'subscribeRss'  => 'Subscribe (RSS)',
];

$feed_url = home_url( '/?feed=lousy_outages_status' ); // Pretty /feed/lousy_outages_status/ works after a permalink flush, but the query form is more reliable.

if ( class_exists( '\\LousyOutages\\I18n' ) ) {
    $locale         = I18n::determine_locale();
    $localized      = I18n::strings( $locale );
    $teaser_strings = array_merge( $teaser_strings, array_intersect_key( $localized, $teaser_strings ) );
}

$teaser_data  = function_exists( 'get_lousy_outages_home_teaser_data' )
    ? get_lousy_outages_home_teaser_data()
    : [
        'headline' => 'All clear — no active outages right now.',
        'href'     => home_url( '/lousy-outages/' ),
        'status'   => 'clear',
        'footnote' => '',
        'feed_url' => $feed_url,
    ];
$teaser_href  = $teaser_data['href'] ?? home_url( '/lousy-outages/' );
$feed_url     = $teaser_data['feed_url'] ?? $feed_url;
$status       = $teaser_data['status'] ?? 'clear';
$status_class = 'clear' === $status ? ' lo-home-pacman-alert--clear' : ' lo-home-pacman-alert--outage';
$footnote     = $teaser_data['footnote'] ?? '';
?>
<section id="lousy-outages-teaser" class="lo-home-teaser" aria-live="polite">
    <h2 class="lo-home-heading">Lousy Outages</h2>

    <p class="lo-home-pacman-alert<?php echo esc_attr( $status_class ); ?>">
        <span class="lo-pacman" aria-hidden="true"></span>
        <span class="lo-pacman-dots" aria-hidden="true"></span>
        <span class="lo-alert-text">
            <?php echo esc_html( $teaser_data['headline'] ?? '' ); ?>
        </span>
    </p>

    <?php if ( ! empty( $footnote ) ) : ?>
        <p class="lo-home-footnote"><?php echo wp_kses_post( $footnote ); ?></p>
    <?php endif; ?>

    <p class="caption"><?php echo esc_html( $teaser_strings['teaserCaption'] ); ?></p>

    <div class="lo-actions lo-actions--rss">
        <a class="lo-link" href="<?php echo esc_url( $teaser_href ); ?>">
            <?php echo esc_html( $teaser_strings['viewDashboard'] ); ?>
        </a>
        <a class="lo-link" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener">
            <svg class="lo-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M6 17a2 2 0 11.001 3.999A2 2 0 016 17zm-2-7v3a8 8 0 018 8h3c0-6.075-4.925-11-11-11zm0-5v3c9.389 0 17 7.611 17 17h3C24 13.85 10.15 0 4 0z"/>
            </svg>
            <?php echo esc_html( $teaser_strings['subscribeRss'] ); ?>
        </a>
    </div>
</section>
