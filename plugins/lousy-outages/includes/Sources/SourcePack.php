<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

class SourcePack {
    private static function opt(string $k, $d){ return function_exists('get_option') ? get_option($k,$d) : $d; }
    private static function filt(string $k, $v){ return function_exists('apply_filters') ? apply_filters($k,$v) : $v; }
    public static function enabled(): bool { return (bool) self::filt('lo_intel_source_pack_enabled', self::opt('lo_intel_source_pack_enabled', '1') === '1'); }
    public static function statuspage_base_urls(): array { return (array) self::filt('lo_statuspage_base_urls', self::opt('lo_statuspage_base_urls', [
        'https://www.githubstatus.com','https://status.atlassian.com','https://www.cloudflarestatus.com','https://status.openai.com','https://status.slack.com','https://www.vercel-status.com','https://www.netlifystatus.com','https://status.npmjs.org','https://www.dockerstatus.com','https://status.zoom.us',
    ])); }
    public static function provider_feed_urls(): array { return (array) self::filt('lo_provider_feed_urls', self::opt('lo_provider_feed_urls', [
        'https://www.githubstatus.com/history.atom','https://status.atlassian.com/history.atom','https://www.cloudflarestatus.com/history.atom','https://status.openai.com/history.atom','https://www.vercel-status.com/history.atom','https://www.netlifystatus.com/history.atom','https://status.npmjs.org/history.atom','https://www.dockerstatus.com/history.atom',
    ])); }
    public static function early_warning_queries(): array { return (array) self::filt('lo_early_warning_queries', self::opt('lo_early_warning_queries', [
        'GitHub Actions failing','GitHub down','npm outage','npm install failing','Docker Hub outage','Docker pull errors','PyPI outage','Vercel outage','Netlify outage','CI/CD outage','package registry outage','deploy failures','webhook delays','AWS outage','AWS API errors','Azure outage','Google Cloud outage','Cloudflare outage','Cloudflare Workers issue','API outage','API latency','elevated errors','status page incident','OpenAI API down','ChatGPT down','ChatGPT login error','Claude down','Anthropic API outage','SSO outage','authentication outage','login errors','MFA failure','Entra outage','Okta outage','Auth0 outage','Interac e-Transfer outage','Canadian bank outage','Rogers outage','Telus internet outage','Bell outage','Freedom Mobile outage','BC Services Card login issue','TransLink Compass outage'
    ])); }
}
