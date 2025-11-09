<?php
declare(strict_types=1);

namespace LousyOutages;

class Downdetector {
    private const CACHE_KEY = 'lousy_outages_downdetector_trends';
    private const CACHE_TTL = 300;

    /** @var int */
    private $timeout;

    /** @var string */
    private $feedUrl;

    public function __construct(int $timeout = 6, ?string $feedUrl = null) {
        $this->timeout = max(2, $timeout);
        $defaultUrl    = 'https://downdetector.ca/archive/?format=rss';
        $this->feedUrl = $feedUrl ?: (string) apply_filters('lousy_outages_downdetector_feed', $defaultUrl);
    }

    public function matches(array $provider): array {
        $entries  = $this->getTrends();
        if (empty($entries)) {
            return [];
        }

        $keywords = $this->keywordsFor($provider);
        if (empty($keywords)) {
            return [];
        }

        $matches  = [];
        $now      = time();
        $maxAge   = (int) apply_filters('lousy_outages_downdetector_max_age', 120); // minutes
        $cutoff   = $now - max(10, $maxAge) * 60;

        foreach ($entries as $entry) {
            $title = isset($entry['title']) ? (string) $entry['title'] : '';
            if ('' === $title) {
                continue;
            }

            $ts = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
            if ($ts && $ts < $cutoff) {
                continue;
            }

            $haystack = strtolower($title);
            foreach ($keywords as $keyword) {
                if (false !== strpos($haystack, $keyword)) {
                    $match = $entry;
                    if ($ts > 0) {
                        $match['age_minutes'] = max(0, (int) round(($now - $ts) / 60));
                    }
                    $matches[] = $match;
                    break;
                }
            }
        }

        return $matches;
    }

    private function getTrends(): array {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get($this->feedUrl, [
            'timeout' => $this->timeout,
            'headers' => [
                'Accept'     => 'application/rss+xml, application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => $this->userAgent(),
            ],
        ]);

        if (is_wp_error($response)) {
            set_transient(self::CACHE_KEY, [], self::CACHE_TTL);
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            set_transient(self::CACHE_KEY, [], self::CACHE_TTL);
            return [];
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ('' === trim($body)) {
            set_transient(self::CACHE_KEY, [], self::CACHE_TTL);
            return [];
        }

        $entries = $this->parseFeed($body);
        set_transient(self::CACHE_KEY, $entries, self::CACHE_TTL);

        return $entries;
    }

    private function parseFeed(string $body): array {
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$xml || !isset($xml->channel->item)) {
            return [];
        }

        $entries = [];
        foreach ($xml->channel->item as $item) {
            $title = sanitize_text_field((string) ($item->title ?? ''));
            if ('' === $title) {
                continue;
            }
            $published = (string) ($item->pubDate ?? ($item->published ?? ''));
            $timestamp = $published ? strtotime($published) : 0;
            $entries[] = [
                'title'       => $title,
                'timestamp'   => $timestamp ?: time(),
                'publishedAt' => $timestamp ? gmdate('c', $timestamp) : gmdate('c'),
                'link'        => isset($item->link) ? sanitize_text_field((string) $item->link) : '',
            ];
        }

        usort(
            $entries,
            static function (array $a, array $b): int {
                return (int) ($b['timestamp'] ?? 0) <=> (int) ($a['timestamp'] ?? 0);
            }
        );

        return $entries;
    }

    private function keywordsFor(array $provider): array {
        $id   = strtolower((string) ($provider['id'] ?? ''));
        $name = strtolower((string) ($provider['name'] ?? ''));

        $map = [
            'aws'         => ['amazon web services', 'amazon'],
            'azure'       => ['microsoft', 'microsoft azure'],
            'gcp'         => ['google cloud', 'google'],
            'github'      => ['github'],
            'zscaler'     => ['zscaler'],
            'cloudflare'  => ['cloudflare'],
            'openai'      => ['openai'],
            'digitalocean'=> ['digitalocean', 'digital ocean'],
            'netlify'     => ['netlify'],
            'vercel'      => ['vercel'],
            'atlassian'   => ['atlassian', 'jira', 'confluence', 'bitbucket'],
            'zoom'        => ['zoom'],
            'qubeyond'    => ['qubeyond'],
        ];

        $keywords = [];
        if ('' !== $name) {
            $keywords[] = $name;
        }
        if ('' !== $id) {
            $keywords[] = $id;
        }
        if (isset($map[$id])) {
            $keywords = array_merge($keywords, $map[$id]);
        }

        $keywords = array_map(
            static function ($keyword): string {
                return strtolower(trim((string) $keyword));
            },
            array_filter($keywords)
        );

        return array_values(array_unique(array_filter($keywords)));
    }

    private function userAgent(): string {
        $site = home_url();
        return 'Mozilla/5.0 (compatible; LousyOutagesBot/3.1; +' . $site . ')';
    }
}
