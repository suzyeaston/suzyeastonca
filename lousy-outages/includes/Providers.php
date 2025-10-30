<?php
declare(strict_types=1);

namespace {
    function lousy_outages_default_providers(): array {
        return [
            // Statuspage JSON
            ['id' => 'github', 'name' => 'GitHub', 'type' => 'statuspage', 'url' => 'https://www.githubstatus.com/api/v2/summary.json'],
            ['id' => 'cloudflare', 'name' => 'Cloudflare', 'type' => 'statuspage', 'url' => 'https://www.cloudflarestatus.com/api/v2/summary.json'],
            ['id' => 'openai', 'name' => 'OpenAI', 'type' => 'statuspage', 'url' => 'https://status.openai.com/api/v2/summary.json'],
            ['id' => 'atlassian', 'name' => 'Atlassian', 'type' => 'statuspage', 'url' => 'https://status.atlassian.com/api/v2/summary.json'],
            ['id' => 'digitalocean', 'name' => 'DigitalOcean', 'type' => 'statuspage', 'url' => 'https://status.digitalocean.com/api/v2/summary.json'],
            ['id' => 'netlify', 'name' => 'Netlify', 'type' => 'statuspage', 'url' => 'https://www.netlifystatus.com/api/v2/summary.json'],
            ['id' => 'vercel', 'name' => 'Vercel', 'type' => 'statuspage', 'url' => 'https://www.vercel-status.com/api/v2/summary.json'],
            ['id' => 'pagerduty', 'name' => 'PagerDuty', 'type' => 'statuspage', 'url' => 'https://status.pagerduty.com/api/v2/summary.json'],
            ['id' => 'zoom', 'name' => 'Zoom', 'type' => 'statuspage', 'url' => 'https://status.zoom.us/api/v2/summary.json'],
            ['id' => 'zscaler', 'name' => 'Zscaler', 'type' => 'rss', 'url' => 'https://trust.zscaler.com/rss-feed'],

            // High-value adds (Statuspage JSON)
            ['id' => 'twilio', 'name' => 'Twilio', 'type' => 'statuspage', 'url' => 'https://status.twilio.com/api/v2/summary.json'],
            ['id' => 'linear', 'name' => 'Linear', 'type' => 'statuspage', 'url' => 'https://status.linear.app/api/v2/summary.json'],
            ['id' => 'sentry', 'name' => 'Sentry', 'type' => 'statuspage', 'url' => 'https://status.sentry.io/api/v2/summary.json'],

            // RSS/Atom feeds
            ['id' => 'slack', 'name' => 'Slack', 'type' => 'rss', 'url' => 'https://slack-status.com/feed/rss'],
            ['id' => 'aws', 'name' => 'AWS', 'type' => 'rss', 'url' => 'https://status.aws.amazon.com/rss/all.rss'],
            ['id' => 'azure', 'name' => 'Azure', 'type' => 'rss', 'url' => 'https://rssfeed.azure.status.microsoft/en-us/status/feed/'],
            ['id' => 'gcp', 'name' => 'Google Cloud', 'type' => 'atom', 'url' => 'https://www.google.com/appsstatus/dashboard/en-CA/feed.atom'],
            ['id' => 'qubeyond', 'name' => 'Qubeyond', 'type' => 'atom', 'url' => 'https://status.qubeyond.com/state_feed/feed.atom'],

            // Optional aggregate (disabled by default in settings)
            ['id' => 'downdetector-ca', 'name' => 'Downdetector (CA Aggregate)', 'type' => 'rss', 'url' => 'https://downdetector.ca/archive/?format=rss', 'disabled' => true, 'optional' => true],
        ];
    }
}

namespace LousyOutages {

    class Providers {
        /**
         * Return the provider configuration map.
         */
        public static function list(): array {
            $defaults  = \lousy_outages_default_providers();
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
}
