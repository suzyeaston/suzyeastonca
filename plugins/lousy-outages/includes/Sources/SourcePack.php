<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

class SourcePack {
    private static function opt(string $k, $d){ return function_exists('get_option') ? get_option($k,$d) : $d; }
    private static function filt(string $k, $v){ return function_exists('apply_filters') ? apply_filters($k,$v) : $v; }
    public static function enabled(): bool { return (bool) self::filt('lo_intel_source_pack_enabled', self::opt('lo_intel_source_pack_enabled', '1') === '1'); }
    public static function statuspage_sources(): array { return (array) self::filt('lo_statuspage_sources', self::opt('lo_statuspage_sources', [
        'github'=>['provider_id'=>'github','provider_name'=>'GitHub','base_url'=>'https://www.githubstatus.com'],
        'cloudflare'=>['provider_id'=>'cloudflare','provider_name'=>'Cloudflare','base_url'=>'https://www.cloudflarestatus.com'],
        'openai'=>['provider_id'=>'openai','provider_name'=>'OpenAI','base_url'=>'https://status.openai.com'],
        'atlassian'=>['provider_id'=>'atlassian','provider_name'=>'Atlassian','base_url'=>'https://status.atlassian.com'],
        'digitalocean'=>['provider_id'=>'digitalocean','provider_name'=>'DigitalOcean','base_url'=>'https://status.digitalocean.com'],
        'netlify'=>['provider_id'=>'netlify','provider_name'=>'Netlify','base_url'=>'https://www.netlifystatus.com'],
        'vercel'=>['provider_id'=>'vercel','provider_name'=>'Vercel','base_url'=>'https://www.vercel-status.com'],
        'zoom'=>['provider_id'=>'zoom','provider_name'=>'Zoom','base_url'=>'https://status.zoom.us'],
        'zscaler'=>['provider_id'=>'zscaler','provider_name'=>'Zscaler','base_url'=>'https://status.zscaler.com'],
        'sentry'=>['provider_id'=>'sentry','provider_name'=>'Sentry','base_url'=>'https://status.sentry.io'],
        'slack'=>['provider_id'=>'slack','provider_name'=>'Slack','base_url'=>'https://status.slack.com'],
    ])); }
    public static function statuspage_base_urls(): array { $urls=[]; foreach(self::statuspage_sources() as $key=>$row){ $urls[is_string($key)?$key:(string)($row['provider_id']??$key)] = is_array($row) ? (string)($row['base_url'] ?? '') : (string)$row; } return array_values(array_filter($urls)); }
    public static function provider_feed_sources(): array { return (array) self::filt('lo_provider_feed_sources', self::opt('lo_provider_feed_sources', [
        'aws'=>['provider_id'=>'aws','provider_name'=>'AWS','url'=>'https://status.aws.amazon.com/rss/all.rss','format'=>'rss'],
        'azure'=>['provider_id'=>'azure','provider_name'=>'Azure','url'=>'https://rssfeed.azure.status.microsoft/en-us/status/feed/','format'=>'rss'],
        'google_workspace'=>['provider_id'=>'google_workspace','provider_name'=>'Google Workspace / Gemini','url'=>'https://www.google.com/appsstatus/rss/en-CA','format'=>'rss'],
        'google_cloud'=>['provider_id'=>'google_cloud','provider_name'=>'Google Cloud','url'=>'https://status.cloud.google.com/incidents.json','format'=>'json_incidents'],
        'crowdstrike'=>['provider_id'=>'crowdstrike','provider_name'=>'CrowdStrike','url'=>'https://www.crowdstrike.com/blog/feed/','format'=>'rss','default_enabled'=>false],
        'github'=>['provider_id'=>'github','provider_name'=>'GitHub','url'=>'https://www.githubstatus.com/history.atom','format'=>'rss'],
        'cloudflare'=>['provider_id'=>'cloudflare','provider_name'=>'Cloudflare','url'=>'https://www.cloudflarestatus.com/history.atom','format'=>'rss'],
        'openai'=>['provider_id'=>'openai','provider_name'=>'OpenAI','url'=>'https://status.openai.com/history.atom','format'=>'rss'],
        'slack'=>['provider_id'=>'slack','provider_name'=>'Slack','url'=>'https://status.slack.com/feed/rss','format'=>'rss'],
        'atlassian'=>['provider_id'=>'atlassian','provider_name'=>'Atlassian','url'=>'https://status.atlassian.com/history.atom','format'=>'rss'],
        'zoom'=>['provider_id'=>'zoom','provider_name'=>'Zoom','url'=>'https://status.zoom.us/history.atom','format'=>'rss'],
        'sentry'=>['provider_id'=>'sentry','provider_name'=>'Sentry','url'=>'https://status.sentry.io/history.atom','format'=>'rss'],
        'netlify'=>['provider_id'=>'netlify','provider_name'=>'Netlify','url'=>'https://www.netlifystatus.com/history.atom','format'=>'rss'],
        'vercel'=>['provider_id'=>'vercel','provider_name'=>'Vercel','url'=>'https://www.vercel-status.com/history.atom','format'=>'rss'],
        'digitalocean'=>['provider_id'=>'digitalocean','provider_name'=>'DigitalOcean','url'=>'https://status.digitalocean.com/history.atom','format'=>'rss'],
        'zscaler'=>['provider_id'=>'zscaler','provider_name'=>'Zscaler','url'=>'https://status.zscaler.com/history.atom','format'=>'rss'],
    ])); }
    public static function provider_feed_urls(): array { $urls=[]; foreach(self::provider_feed_sources() as $key=>$row){ $urls[is_string($key)?$key:(string)($row['provider_id']??$key)] = is_array($row) ? (string)($row['url'] ?? '') : (string)$row; } return array_filter($urls); }
    public static function early_warning_queries(): array { return (array) self::filt('lo_early_warning_queries', self::opt('lo_early_warning_queries', [
        'GitHub Actions failing','GitHub down','npm outage','npm install failing','Docker Hub outage','Docker pull errors','PyPI outage','Vercel outage','Netlify outage','CI/CD outage','package registry outage','deploy failures','webhook delays','AWS outage','AWS API errors','Azure outage','Google Cloud outage','Cloudflare outage','Cloudflare Workers issue','API outage','API latency','elevated errors','status page incident','OpenAI API down','ChatGPT down','ChatGPT login error','Claude down','Anthropic API outage','SSO outage','authentication outage','login errors','MFA failure','Entra outage','Okta outage','Auth0 outage','RBC mobile banking down','TD EasyWeb down','Scotiabank app down','BMO online banking down','CIBC banking app down','Interac e-Transfer down','Rogers outage','TELUS outage','Bell outage','Freedom Mobile outage','no cell service Canada','debit payments failing Canada','911 outage BC','BC Services Card login down','TransLink Compass outage'
    ])); }
}
