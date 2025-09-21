<?php
namespace LousyOutages;

class Providers {
    /**
     * Return all providers.
     * @return array
     */
    public static function list(): array {
        return [
            'github' => [
                'id'       => 'github',
                'name'     => 'GitHub',
                'provider' => 'GitHub',
                'endpoint' => 'https://www.githubstatus.com/api/v2/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://www.githubstatus.com/'
            ],
            'slack' => [
                'id'       => 'slack',
                'name'     => 'Slack',
                'provider' => 'Slack',
                'endpoint' => 'https://status.slack.com/api/v2.0.0/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://status.slack.com/'
            ],
            'cloudflare' => [
                'id'       => 'cloudflare',
                'name'     => 'Cloudflare',
                'provider' => 'Cloudflare',
                'endpoint' => 'https://www.cloudflarestatus.com/api/v2/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://www.cloudflarestatus.com/'
            ],
            'openai' => [
                'id'       => 'openai',
                'name'     => 'OpenAI',
                'provider' => 'OpenAI',
                'endpoint' => 'https://status.openai.com/api/v2/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://status.openai.com/'
            ],
            'aws' => [
                'id'       => 'aws',
                'name'     => 'AWS',
                'provider' => 'AWS',
                'endpoint' => 'https://status.aws.amazon.com/rss/all.rss',
                'type'     => 'rss',
                'url'      => 'https://status.aws.amazon.com/'
            ],
            'azure' => [
                'id'       => 'azure',
                'name'     => 'Azure',
                'provider' => 'Azure',
                'endpoint' => 'https://status.azure.com/en-us/status/feed',
                'type'     => 'rss',
                'url'      => 'https://status.azure.com/'
            ],
            'gcp' => [
                'id'       => 'gcp',
                'name'     => 'Google Cloud',
                'provider' => 'Google Cloud',
                'endpoint' => 'https://status.cloud.google.com/feed.json',
                'type'     => 'json',
                'url'      => 'https://status.cloud.google.com/'
            ],
        ];
    }

    /**
     * Return enabled providers from options or default all.
     */
    public static function enabled(): array {
        $all     = self::list();
        $enabled = get_option( 'lousy_outages_providers', array_keys( $all ) );
        return array_intersect_key( $all, array_flip( $enabled ) );
    }
}
