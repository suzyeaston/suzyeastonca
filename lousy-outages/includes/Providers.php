<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Providers {
    private const OPTION_DEFAULT_ENABLED = 'lousy_outages_default_enabled_providers';

    private static function defaultProviders(): array {
        return [
            // Statuspage JSON
            [
                'id'         => 'github',
                'name'       => 'GitHub',
                'type'       => 'statuspage',
                'url'        => 'https://www.githubstatus.com/api/v2/summary.json',
                'status_url' => 'https://www.githubstatus.com/',
            ],
            [
                'id'         => 'cloudflare',
                'name'       => 'Cloudflare',
                'type'       => 'statuspage',
                'url'        => 'https://www.cloudflarestatus.com/api/v2/summary.json',
                'status_url' => 'https://www.cloudflarestatus.com/',
            ],
            [
                'id'         => 'openai',
                'name'       => 'OpenAI',
                'type'       => 'statuspage',
                'url'        => 'https://status.openai.com/api/v2/summary.json',
                'status_url' => 'https://status.openai.com/',
            ],
            [
                'id'         => 'atlassian',
                'name'       => 'Atlassian',
                'type'       => 'statuspage',
                'url'        => 'https://status.atlassian.com/api/v2/summary.json',
                'status_url' => 'https://status.atlassian.com/',
            ],
            [
                'id'         => 'digitalocean',
                'name'       => 'DigitalOcean',
                'type'       => 'statuspage',
                'url'        => 'https://status.digitalocean.com/api/v2/summary.json',
                'status_url' => 'https://status.digitalocean.com/',
            ],
            [
                'id'         => 'netlify',
                'name'       => 'Netlify',
                'type'       => 'statuspage',
                'url'        => 'https://www.netlifystatus.com/api/v2/summary.json',
                'status_url' => 'https://www.netlifystatus.com/',
            ],
            [
                'id'         => 'vercel',
                'name'       => 'Vercel',
                'type'       => 'statuspage',
                'url'        => 'https://www.vercel-status.com/api/v2/summary.json',
                'status_url' => 'https://www.vercel-status.com/',
            ],
            [
                'id'         => 'zoom',
                'name'       => 'Zoom',
                'type'       => 'statuspage',
                'url'        => 'https://status.zoom.us/api/v2/summary.json',
                'status_url' => 'https://status.zoom.us/',
            ],
            [
                'id'         => 'zscaler',
                'name'       => 'Zscaler',
                'type'       => 'statuspage',
                'url'        => 'https://status.zscaler.com/api/v2/summary.json',
                'status_url' => 'https://status.zscaler.com/',
            ],

            // High-value adds (Statuspage JSON)
            [
                'id'         => 'twilio',
                'name'       => 'Twilio',
                'type'       => 'statuspage',
                'url'        => 'https://status.twilio.com/api/v2/summary.json',
                'status_url' => 'https://status.twilio.com/',
            ],
            [
                'id'         => 'linear',
                'name'       => 'Linear',
                'type'       => 'statuspage',
                'url'        => 'https://status.linear.app/api/v2/summary.json',
                'status_url' => 'https://status.linear.app/',
            ],
            [
                'id'         => 'sentry',
                'name'       => 'Sentry',
                'type'       => 'statuspage',
                'url'        => 'https://status.sentry.io/api/v2/summary.json',
                'status_url' => 'https://status.sentry.io/',
            ],

            // RSS/Atom feeds
            ['id' => 'aws', 'name' => 'AWS', 'type' => 'rss', 'url' => 'https://status.aws.amazon.com/rss/all.rss'],
            [
                'id'         => 'slack',
                'name'       => 'Slack',
                'type'       => 'rss',
                'url'        => 'https://slack-status.com/feed/rss',
                'status_url' => 'https://status.slack.com/',
            ],
            ['id' => 'azure', 'name' => 'Azure', 'type' => 'rss', 'url' => 'https://rssfeed.azure.status.microsoft/en-us/status/feed/'],
            ['id' => 'gcp', 'name' => 'Google Cloud', 'type' => 'atom', 'url' => 'https://www.google.com/appsstatus/dashboard/en-CA/feed.atom'],
            ['id' => 'qubeyond', 'name' => 'Qubeyond', 'type' => 'atom', 'url' => 'https://status.qubeyond.com/state_feed/feed.atom'],

            // Optional aggregate (disabled by default in settings)
            ['id' => 'downdetector-ca', 'name' => 'Downdetector (CA Aggregate)', 'type' => 'rss', 'url' => 'https://downdetector.ca/archive/?format=rss', 'disabled' => true, 'optional' => true],
        ];
    }

    /**
     * Return the provider configuration map.
     */
    public static function list(): array {
        $defaults  = self::defaultProviders();
        $providers = [];

        foreach ( $defaults as $provider ) {
            if ( ! is_array( $provider ) ) {
                continue;
            }

            $id = isset( $provider['id'] ) ? (string) $provider['id'] : '';
            if ( '' === $id ) {
                continue;
            }

            $providers[ $id ] = $provider;
        }

        $providers = apply_filters( 'lousy_outages_providers', $providers );
        if ( ! is_array( $providers ) ) {
            $providers = [];
        }

        foreach ( $providers as $id => &$provider ) {
            if ( ! is_array( $provider ) ) {
                unset( $providers[ $id ] );
                continue;
            }

            $provider = self::prepare_provider( (string) $id, $provider );
        }
        unset( $provider );

        return $providers;
    }

    /**
     * Return enabled providers from options or defaults.
     */
    public static function enabled(): array {
        $all             = self::list();
        $default_enabled = array_keys(
            array_filter(
                $all,
                static fn( array $prov ): bool => ! empty( $prov['enabled'] )
            )
        );
        $saved               = get_option( 'lousy_outages_providers', false );
        $stored_default_list = get_option( self::OPTION_DEFAULT_ENABLED, null );
        $previous_defaults   = is_array( $stored_default_list )
            ? array_values(
                array_filter(
                    array_map( 'strval', $stored_default_list ),
                    static fn( $value ): bool => '' !== (string) $value
                )
            )
            : [];

        if ( is_array( $saved ) ) {
            $enabled_ids = [];
            foreach ( $saved as $id ) {
                if ( ! is_scalar( $id ) ) {
                    continue;
                }
                $id = (string) $id;
                if ( '' === $id ) {
                    continue;
                }
                $enabled_ids[] = $id;
            }

            $new_defaults = array_diff( $default_enabled, $previous_defaults );
            $updated      = false;

            if ( ! empty( $new_defaults ) ) {
                foreach ( $new_defaults as $id ) {
                    if ( ! in_array( $id, $enabled_ids, true ) ) {
                        $enabled_ids[] = $id;
                        $updated       = true;
                    }
                }
            }

            if ( $updated ) {
                $enabled_ids = array_values( array_unique( $enabled_ids ) );
                update_option( 'lousy_outages_providers', $enabled_ids, false );
            }

            if ( $previous_defaults !== $default_enabled ) {
                update_option( self::OPTION_DEFAULT_ENABLED, $default_enabled, false );
            }
        } else {
            $enabled_ids = $default_enabled;

            if ( $previous_defaults !== $default_enabled ) {
                update_option( self::OPTION_DEFAULT_ENABLED, $default_enabled, false );
            }
        }

        $enabled = [];
        foreach ( $enabled_ids as $id ) {
            if ( isset( $all[ $id ] ) ) {
                $enabled[ $id ] = $all[ $id ];
            }
        }

        return $enabled;
    }

    private static function prepare_provider( string $id, array $provider ): array {
        $provider['id']   = $id;
        $provider['name'] = (string) ( $provider['name'] ?? $id );
        $provider['type'] = strtolower( (string) ( $provider['type'] ?? 'statuspage' ) );

        if ( ! isset( $provider['url'] ) ) {
            $endpoint = self::coalesce_endpoint( $provider );
            if ( null !== $endpoint ) {
                $provider['url'] = $endpoint;
            }
        }

        $provider['enabled'] = array_key_exists( 'enabled', $provider )
            ? (bool) $provider['enabled']
            : ! ( isset( $provider['disabled'] ) && $provider['disabled'] );
        unset( $provider['disabled'] );

        $provider['optional'] = ! empty( $provider['optional'] );

        if ( empty( $provider['status_url'] ) || ! is_string( $provider['status_url'] ) ) {
            $provider['status_url'] = self::derive_status_url( $provider );
        }

        // Maintain legacy endpoint keys for backwards compatibility with filters.
        if ( 'statuspage' === $provider['type'] && empty( $provider['summary'] ) && ! empty( $provider['url'] ) ) {
            $provider['summary'] = $provider['url'];
        }
        if ( 'rss' === $provider['type'] && empty( $provider['rss'] ) && ! empty( $provider['url'] ) ) {
            $provider['rss'] = $provider['url'];
        }
        if ( 'atom' === $provider['type'] && empty( $provider['atom'] ) && ! empty( $provider['url'] ) ) {
            $provider['atom'] = $provider['url'];
        }
        if ( in_array( $provider['type'], [ 'slack', 'slack_current' ], true ) && empty( $provider['current'] ) && ! empty( $provider['url'] ) ) {
            $provider['current'] = $provider['url'];
        }

        return $provider;
    }

    private static function coalesce_endpoint( array $provider ): ?string {
        foreach ( [ 'summary', 'url', 'rss', 'atom', 'current', 'endpoint' ] as $key ) {
            if ( empty( $provider[ $key ] ) || ! is_string( $provider[ $key ] ) ) {
                continue;
            }

            return $provider[ $key ];
        }

        return null;
    }

    private static function derive_status_url( array $provider ): string {
        $wellKnown = [
            'zscaler'  => 'https://trust.zscaler.com/',
            'slack'    => 'https://status.slack.com/',
            'qubeyond' => 'https://status.qubeyond.com/',
        ];

        if ( ! empty( $provider['id'] ) && isset( $wellKnown[ $provider['id'] ] ) ) {
            return trailingslashit( $wellKnown[ $provider['id'] ] );
        }

        $type           = strtolower( (string) ( $provider['type'] ?? 'statuspage' ) );
        $candidates     = [];
        $candidate_keys = [ 'status_url', 'url', 'summary', 'rss', 'atom', 'current', 'endpoint' ];

        foreach ( $candidate_keys as $key ) {
            if ( empty( $provider[ $key ] ) || ! is_string( $provider[ $key ] ) ) {
                continue;
            }
            $candidates[] = $provider[ $key ];
        }

        foreach ( $candidates as $candidate ) {
            $candidate = trim( $candidate );
            if ( '' === $candidate ) {
                continue;
            }

            $adjusted = $candidate;
            if ( in_array( $type, [ 'statuspage', 'slack', 'slack_current' ], true ) ) {
                $adjusted = preg_replace( '#/api/v\d+(?:\.\d+)*?/(summary\.json|current|status\.json)$#', '/', $adjusted );
                $adjusted = preg_replace( '#/api/v\d+(?:\.\d+)*?/history\.(?:rss|atom)$#', '/', (string) $adjusted );
            }

            if ( in_array( $type, [ 'rss', 'atom', 'rss-optional' ], true ) ) {
                $adjusted = preg_replace( '#/(?:feed(?:s)?/?)?(?:[^/]*\.(?:rss|atom|xml))/?$#i', '/', (string) $adjusted );
            }

            $parts = wp_parse_url( $adjusted ?: $candidate );
            if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
                continue;
            }

            $base = $parts['scheme'] . '://' . $parts['host'];
            if ( ! empty( $parts['path'] ) ) {
                $path = trim( (string) $parts['path'], '/' );
                if ( '' !== $path ) {
                    if ( in_array( $type, [ 'statuspage', 'slack', 'slack_current' ], true ) ) {
                        $path = preg_replace( '#^api/.*$#', '', $path );
                    } elseif ( in_array( $type, [ 'rss', 'atom', 'rss-optional' ], true ) ) {
                        $segments = array_filter( explode( '/', $path ), static fn( $seg ) => '' !== $seg );
                        while ( $segments ) {
                            $last = strtolower( (string) end( $segments ) );
                            if ( '' === $last ) {
                                array_pop( $segments );
                                continue;
                            }
                            if ( preg_match( '#\.(?:rss|atom|xml)$#', $last ) || in_array( $last, [ 'feed', 'feeds', 'rss', 'atom' ], true ) ) {
                                array_pop( $segments );
                                continue;
                            }
                            break;
                        }
                        $path = implode( '/', $segments );
                    }
                    $path = trim( (string) $path, '/' );
                    if ( '' !== $path ) {
                        $base .= '/' . $path;
                    }
                }
            }

            return trailingslashit( $base );
        }

        return trailingslashit( home_url( '/' ) );
    }
}
