<?php
use LousyOutages\I18n;

$teaser_strings = [
    'teaserCaption' => 'Check if your favourite services are up. Insert coin to refresh.',
    'viewDashboard' => 'View Full Dashboard →',
];

$feed_url     = esc_url( home_url('/outages/feed') );
$fallback_url = esc_url( add_query_arg('lousy_outages_feed', '1', home_url('/') ) );

if ( class_exists( '\\LousyOutages\\I18n' ) ) {
    $locale         = I18n::determine_locale();
    $localized      = I18n::strings( $locale );
    $teaser_strings = array_merge( $teaser_strings, array_intersect_key( $localized, $teaser_strings ) );
}
?>
<section id="lousy-outages-teaser" aria-live="polite">
  <div class="lo-teaser">
    <h2 class="pixel-font">Lousy Outages</h2>
    <div class="lo-teaser-meta">
      <div class="providers"></div>
      <p class="caption"><?php echo esc_html( $teaser_strings['teaserCaption'] ); ?></p>
    </div>
    <div class="lo-actions">
      <a class="lo-link" href="<?php echo $feed_url; ?>" target="_blank" rel="noopener">
        <svg class="lo-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M6 17a2 2 0 11.001 3.999A2 2 0 016 17zm-2-7v3a8 8 0 018 8h3c0-6.075-4.925-11-11-11zm0-5v3c9.389 0 17 7.611 17 17h3C24 13.85 10.15 0 4 0z"/>
        </svg>
        Subscribe (RSS)
      </a>
      <noscript>
        <p><a class="lo-link" href="<?php echo $fallback_url; ?>">RSS (fallback)</a></p>
      </noscript>
    </div>
    <div class="lo-actions">
      <a class="view-all pixel-button" href="/lousy-outages/"><?php echo esc_html( $teaser_strings['viewDashboard'] ); ?></a>
    </div>
  </div>
</section>
