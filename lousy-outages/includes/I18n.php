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
            'buttonLoading'     => 'Loading…',
            'teaserCaption'     => 'Check if your favourite services are up. Insert coin to refresh.',
            'microcopy'         => 'Vancouver weather: cloudy with a chance of outages.',
            'offlineMessage'    => 'Unable to reach the arcade right now. Try again soon.',
            'tickerFallback'    => 'All quiet on the outage front.',
            'unknownStatus'     => 'Unknown',
            'noPublicStatus'    => 'No public status API',
            'viewDashboard'     => 'View Full Dashboard →',
        ],
        'en-US' => [
            'updatedLabel'      => 'Updated:',
            'providerHeader'    => 'Provider',
            'statusHeader'      => 'Status',
            'messageHeader'     => 'Message',
            'buttonLabel'       => 'Insert coin to refresh',
            'buttonShortLabel'  => 'Insert coin',
            'buttonLoading'     => 'Loading…',
            'teaserCaption'     => 'Check if your favorite services are up. Insert coin to refresh.',
            'microcopy'         => 'Vancouver weather: cloudy with a chance of outages.',
            'offlineMessage'    => 'Unable to reach the arcade right now. Try again soon.',
            'tickerFallback'    => 'All quiet on the outage front.',
            'unknownStatus'     => 'Unknown',
            'noPublicStatus'    => 'No public status API',
            'viewDashboard'     => 'View Full Dashboard →',
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
