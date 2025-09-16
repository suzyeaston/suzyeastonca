<?php
use LousyOutages\I18n;

$teaser_strings = [
    'teaserCaption' => 'Check if your favourite services are up. Insert coin to refresh.',
    'viewDashboard' => 'View Full Dashboard →',
];

if ( class_exists( '\\LousyOutages\\I18n' ) ) {
    $locale         = I18n::determine_locale();
    $localized      = I18n::strings( $locale );
    $teaser_strings = array_merge( $teaser_strings, array_intersect_key( $localized, $teaser_strings ) );
}
?>
<section id="lousy-outages-teaser" class="lo-teaser" aria-live="polite">
  <h2 class="pixel-font">Lousy Outages</h2>
  <div class="providers"></div>
  <p class="caption"><?php echo esc_html( $teaser_strings['teaserCaption'] ); ?></p>
  <a class="view-all pixel-button" href="/lousy-outages/"><?php echo esc_html( $teaser_strings['viewDashboard'] ); ?></a>
</section>
