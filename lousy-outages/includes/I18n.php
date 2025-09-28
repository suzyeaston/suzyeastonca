<?php
namespace LousyOutages;

class I18n {
    private const STRINGS = [
        'en-CA' => [
            'updatedLabel'      => 'Updated:',
            'providerHeader'    => 'Provider',
            'statusHeader'      => 'Status',
            'messageHeader'     => 'Message',
            'buttonLabel'       => 'Insert coin to refresh',
            'buttonShortLabel'  => 'Insert coin',
            'buttonLoading'     => 'Refreshing…',
            'teaserCaption'     => 'Check if your favourite services are up. Insert coin to refresh.',
            'microcopy'         => 'Vancouver weather: cloudy with a chance of outages.',
            'offlineMessage'    => 'Arcade link is jittery. Data might be stale—try again soon.',
            'tickerFallback'    => 'All quiet on the outage front.',
            'unknownStatus'     => 'Unknown',
            'noPublicStatus'    => 'No public status API',
            'viewDashboard'     => 'View Full Dashboard →',
            'detailsLabel'      => 'Details',
            'detailsHide'       => 'Hide details',
            'noIncidents'       => 'No active incidents. Go write a chorus.',
            'degradedNoIncidents' => 'Status page shows degraded performance. Pop over there for the detailed log.',
            'etaInvestigating'  => 'ETA: investigating — translation: nobody knows yet.',
            'viewProvider'      => 'View provider status →',
        ],
        'en-US' => [
            'updatedLabel'      => 'Updated:',
            'providerHeader'    => 'Provider',
            'statusHeader'      => 'Status',
            'messageHeader'     => 'Message',
            'buttonLabel'       => 'Insert coin to refresh',
            'buttonShortLabel'  => 'Insert coin',
            'buttonLoading'     => 'Refreshing…',
            'teaserCaption'     => 'Check if your favourite services are up. Insert coin to refresh.',
            'microcopy'         => 'Vancouver weather: cloudy with a chance of outages.',
            'offlineMessage'    => 'Arcade link is jittery. Data might be stale—try again soon.',
            'tickerFallback'    => 'All quiet on the outage front.',
            'unknownStatus'     => 'Unknown',
            'noPublicStatus'    => 'No public status API',
            'viewDashboard'     => 'View Full Dashboard →',
            'detailsLabel'      => 'Details',
            'detailsHide'       => 'Hide details',
            'noIncidents'       => 'No active incidents. Go write a chorus.',
            'degradedNoIncidents' => 'Status page shows degraded performance. Pop over there for the detailed log.',
            'etaInvestigating'  => 'ETA: investigating — translation: nobody knows yet.',
            'viewProvider'      => 'View provider status →',
        ],
    ];

    public static function determine_locale(): string {
        $default = 'en-CA';
        $site    = function_exists( '\\get_locale' ) ? (string) get_locale() : $default;
        $site    = str_replace( '_', '-', $site );

        if ( isset( self::STRINGS[ $site ] ) ) {
            return $site;
        }

        if ( 0 === strpos( strtolower( $site ), 'en-us' ) ) {
            return 'en-US';
        }

        return $default;
    }

    public static function strings( ?string $locale = null ): array {
        $locale   = $locale ? str_replace( '_', '-', $locale ) : self::determine_locale();
        $fallback = self::STRINGS['en-US'];
        $primary  = self::STRINGS[ $locale ] ?? [];

        return array_merge( $fallback, $primary );
    }

    public static function all(): array {
        return self::STRINGS;
    }
}
