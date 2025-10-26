<?php
use LousyOutages\I18n;
use LousyOutages\Summary;

$teaser_strings = [
    'teaserCaption' => 'Check if your favourite services are up. Insert coin to refresh.',
    'viewDashboard' => 'View Full Dashboard →',
];

$feed_url     = esc_url( home_url('/lousy-outages/feed/') );

if ( class_exists( '\\LousyOutages\\I18n' ) ) {
    $locale         = I18n::determine_locale();
    $localized      = I18n::strings( $locale );
    $teaser_strings = array_merge( $teaser_strings, array_intersect_key( $localized, $teaser_strings ) );
}

$summary_data = [
    'message'      => 'All systems operational',
    'href'         => home_url( '/lousy-outages/' ),
    'incident'     => false,
    'incidentDate' => '',
    'relative'     => '',
];

if ( class_exists( '\\LousyOutages\\Summary' ) ) {
    $current = Summary::current();
    if ( ! empty( $current['hasIncident'] ) ) {
        $provider = $current['provider'] ?? '';
        $title    = $current['title'] ?? '';
        $slug     = sanitize_title( (string) ( $current['providerId'] ?? $provider ) );
        $summary_data['incident']     = true;
        $summary_data['message']      = trim( sprintf( '⚠️ Outage detected: %s — %s', $provider, $title ) );
        if ( ! empty( $current['relative'] ) ) {
            $summary_data['message'] .= sprintf( ' (started %s)', $current['relative'] );
        }
        $summary_data['href']         = $slug ? home_url( '/lousy-outages/#provider-' . $slug ) : home_url( '/lousy-outages/' );
        $summary_data['incidentDate'] = $current['started_at'] ?? '';
        $summary_data['relative']     = $current['relative'] ?? '';
    }
}

$status_classes = $summary_data['incident'] ? 'lo-status-line lo-status-line--alert' : 'lo-status-line lo-status-line--ok';
$summary_attrs  = '';
if ( $summary_data['incidentDate'] ) {
    $summary_attrs .= ' data-incident-start="' . esc_attr( $summary_data['incidentDate'] ) . '"';
}
if ( $summary_data['relative'] ) {
    $summary_attrs .= ' data-relative="' . esc_attr( $summary_data['relative'] ) . '"';
}
?>
<section id="lousy-outages-teaser" aria-live="polite">
  <div class="lo-teaser">
    <h2 class="pixel-font">Lousy Outages</h2>
    <p class="<?php echo esc_attr( $status_classes ); ?>">
      <a class="lo-status-link" href="<?php echo esc_url( $summary_data['href'] ); ?>" data-lo-summary<?php echo $summary_attrs; ?>>
        <?php echo esc_html( $summary_data['message'] ); ?>
      </a>
    </p>
    <p class="caption"><?php echo esc_html( $teaser_strings['teaserCaption'] ); ?></p>
    <div class="lo-actions lo-actions--primary">
      <a class="view-all pixel-button" href="/lousy-outages/"><?php echo esc_html( $teaser_strings['viewDashboard'] ); ?></a>
    </div>
    <div class="lo-actions lo-actions--rss">
      <a class="lo-link" href="<?php echo $feed_url; ?>" target="_blank" rel="noopener">
        <svg class="lo-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M6 17a2 2 0 11.001 3.999A2 2 0 016 17zm-2-7v3a8 8 0 018 8h3c0-6.075-4.925-11-11-11zm0-5v3c9.389 0 17 7.611 17 17h3C24 13.85 10.15 0 4 0z"/>
        </svg>
        Subscribe (RSS)
      </a>
    </div>
  </div>
</section>
