<?php
declare(strict_types=1);

namespace LousyOutages;

class Providers {
    /**
     * Return the provider configuration map.
     */
    public static function list(): array {
        $providers = apply_filters(
            'lousy_outages_providers',
            [
                'github' => [
                    'name'   => 'GitHub',
                    'type'   => 'statuspage',
                    'summary'=> 'https://www.githubstatus.com/api/v2/summary.json',
                    'status_url' => 'https://www.githubstatus.com/',
                ],
                'slack' => [
                    'name'       => 'Slack',
                    'type'       => 'slack',
                    'current'    => 'https://status.slack.com/api/v2.0.0/current',
                    'status_url' => 'https://status.slack.com/',
                ],
                'zscaler' => [
                    'name'       => 'Zscaler',
                    'type'       => 'statuspage',
                    'summary'    => 'https://status.zscaler.com/api/v2/summary.json',
                    'status_url' => 'https://status.zscaler.com/',
                ],
                'cloudflare' => [
                    'name'   => 'Cloudflare',
                    'type'   => 'statuspage',
                    'summary'=> 'https://www.cloudflarestatus.com/api/v2/summary.json',
                    'status_url' => 'https://www.cloudflarestatus.com/',
                ],
                'openai' => [
                    'name'   => 'OpenAI',
                    'type'   => 'statuspage',
                    'summary'=> 'https://status.openai.com/api/v2/summary.json',
                    'status_url' => 'https://status.openai.com/',
                ],
                'aws' => [
                    'name'      => 'AWS',
                    'type'      => 'rss',
                    'rss'       => 'https://status.aws.amazon.com/rss/all.rss',
                    'status_url'=> 'https://status.aws.amazon.com/',
                ],
                'azure' => [
                    'name'      => 'Azure',
                    'type'      => 'rss',
                    'rss'       => 'https://azurestatuscdn.azureedge.net/en-us/status/feed/',
                    'status_url'=> 'https://status.azure.com/',
                ],
                'gcp' => [
                    'name'      => 'Google Cloud',
                    'type'      => 'atom',
                    'atom'      => 'https://status.cloud.google.com/feed.atom',
                    'status_url'=> 'https://status.cloud.google.com/',
                ],
                'digitalocean' => [
                    'name'   => 'DigitalOcean',
                    'type'   => 'statuspage',
                    'summary'=> 'https://status.digitalocean.com/api/v2/summary.json',
                    'status_url' => 'https://status.digitalocean.com/',
                ],
                'netlify' => [
                    'name'   => 'Netlify',
                    'type'   => 'statuspage',
                    'summary'=> 'https://www.netlifystatus.com/api/v2/summary.json',
                    'status_url' => 'https://www.netlifystatus.com/',
                ],
                'vercel' => [
                    'name'   => 'Vercel',
                    'type'   => 'statuspage',
                    'summary'=> 'https://www.vercel-status.com/api/v2/summary.json',
                    'status_url' => 'https://www.vercel-status.com/',
                ],
                'downdetector-ca' => [
                    'name'      => 'Downdetector (CA Aggregate)',
                    'type'      => 'rss-optional',
                    'enabled'   => false,
                    'rss'       => 'https://downdetector.ca/archive/?format=rss',
                    'status_url'=> 'https://downdetector.ca/',
                ],
            ]
        );

        foreach ( $providers as $id => &$provider ) {
            if ( ! is_array( $provider ) ) {
                unset( $providers[ $id ] );
                continue;
            }

            $provider['id']       = $provider['id'] ?? $id;
            $provider['enabled']  = array_key_exists( 'enabled', $provider ) ? (bool) $provider['enabled'] : true;
            $provider['status_url'] = $provider['status_url'] ?? self::derive_status_url( $provider );
        }
        unset( $provider );

        return $providers;
    }

    /**
     * Return enabled providers from options or defaults.
     */
    public static function enabled(): array {
        $all            = self::list();
        $default_enabled = array_keys( array_filter( $all, static fn( array $prov ): bool => $prov['enabled'] ) );
        $saved           = get_option( 'lousy_outages_providers', false );
        $enabled_ids     = is_array( $saved ) ? $saved : $default_enabled;

        $enabled = [];
        foreach ( $enabled_ids as $id ) {
            if ( isset( $all[ $id ] ) ) {
                $enabled[ $id ] = $all[ $id ];
            }
        }

        return $enabled;
    }

    private static function derive_status_url( array $provider ): string {
        foreach ( ['summary', 'rss', 'atom'] as $key ) {
            if ( empty( $provider[ $key ] ) || ! is_string( $provider[ $key ] ) ) {
                continue;
            }

            $url = $provider[ $key ];
            if ( 'summary' === $key ) {
                $url = preg_replace( '#/api/v\d+(?:\.\d+)*?/summary\.json$#', '/', $url );
            }

            $parts = wp_parse_url( $url );
            if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
                continue;
            }

            $base = $parts['scheme'] . '://' . $parts['host'];
            if ( 'summary' === $key ) {
                $path = isset( $parts['path'] ) ? trim( (string) $parts['path'], '/' ) : '';
                if ( $path && '/' !== $path ) {
                    $base .= '/' . $path;
                }
            }

            return trailingslashit( $base );
        }

        return trailingslashit( home_url( '/' ) );
    }
}
