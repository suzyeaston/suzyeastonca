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
                'endpoint' => 'https://www.githubstatus.com/api/v2/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://www.githubstatus.com/'
            ],
            'slack' => [
                'id'       => 'slack',
                'name'     => 'Slack',
                'endpoint' => 'https://status.slack.com/api/v2.0.0/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://status.slack.com/'
            ],
            'cloudflare' => [
                'id'       => 'cloudflare',
                'name'     => 'Cloudflare',
                'endpoint' => 'https://www.cloudflarestatus.com/api/v2/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://www.cloudflarestatus.com/'
            ],
            'openai' => [
                'id'       => 'openai',
                'name'     => 'OpenAI',
                'endpoint' => 'https://status.openai.com/api/v2/summary.json',
                'type'     => 'statuspage',
                'url'      => 'https://status.openai.com/'
            ],
            'aws' => [
                'id'       => 'aws',
                'name'     => 'AWS',
                'endpoint' => 'https://status.aws.amazon.com/rss/all.rss',
                'type'     => 'rss',
                'url'      => 'https://status.aws.amazon.com/'
            ],
            'azure' => [
                'id'       => 'azure',
                'name'     => 'Azure',
                'endpoint' => 'https://azurestatuscdn.azureedge.net/en-us/status/feed/',
                'type'     => 'rss',
                'url'      => 'https://status.azure.com/'
            ],
            'gcp' => [
                'id'       => 'gcp',
                'name'     => 'Google Cloud',
                'endpoint' => 'https://status.cloud.google.com/feed.atom',
                'type'     => 'rss',
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
