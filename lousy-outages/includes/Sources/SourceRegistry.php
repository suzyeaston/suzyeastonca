<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

/**
 * Describes source lanes/capabilities without making each source equally trusted.
 */
class SourceRegistry {
    private static function opt(string $key, $default) { return function_exists('get_option') ? get_option($key, $default) : $default; }
    private static function filt(string $key, $value) { return function_exists('apply_filters') ? apply_filters($key, $value) : $value; }

    public static function definitions(): array {
        $defs = [
            'statuspage_intel' => self::def('statuspage_intel','Statuspage official APIs','official_status','official','official incident API',false,false,true,false,true,'cached',true,true,true),
            'provider_feed' => self::def('provider_feed','Provider RSS/JSON feeds','provider_feed','official','RSS/JSON feed',false,false,true,false,true,'cached',true,true,true),
            'hacker_news_chatter' => self::def('hacker_news_chatter','Hacker News developer chatter','public_chatter','unconfirmed','developer/forum chatter',false,true,true,false,true,'tiny',true,true,false),
            'public_chatter_bluesky' => self::def('public_chatter_bluesky','Bluesky public search','public_chatter','unconfirmed','public social search',false,true,true,false,true,'small',true,true,false),
            'public_chatter_mastodon' => self::def('public_chatter_mastodon','Mastodon federated search','public_chatter','unconfirmed','federated social search',false,true,true,false,true,'tiny',true,true,false),
            'public_chatter_reddit' => self::def('public_chatter_reddit','Reddit field reports','public_chatter','unconfirmed','official Reddit API/OAuth search',true,true,true,true,true,'small',false,true,false),
            'public_chatter_gdelt' => self::def('public_chatter_gdelt','GDELT open-web/news search','open_web','corroborating','open web/news search',false,true,true,true,true,'tiny',true,true,false),
            'cloudflare_radar' => self::def('cloudflare_radar','Cloudflare Radar','internet_health','telemetry','network/ASN telemetry',true,false,false,true,true,'cached',false,true,false),
            'caida_ioda' => self::def('caida_ioda','CAIDA IODA','internet_health','telemetry','macroscopic internet outage detection',false,false,false,true,true,'cached',false,true,false),
            'community_reports' => self::def('community_reports','First-party community reports','community_report','unconfirmed','first-party report',false,false,true,true,true,'cached',true,true,false),
            'synthetic_canary' => self::def('synthetic_canary','Synthetic canaries','internet_health','telemetry','synthetic/canary signal',false,false,true,true,true,'cached',true,true,false),
        ];
        return (array) self::filt('lo_source_registry_definitions', $defs);
    }

    private static function def(string $key,string $name,string $lane,string $trust,string $cap,bool $auth,bool $keyword,bool $provider,bool $geo,bool $time,string $budget,bool $enabled,bool $safe,bool $solo): array {
        return ['source_key'=>$key,'display_name'=>$name,'lane'=>$lane,'trust_level'=>$trust,'source_capability'=>$cap,'requires_auth'=>$auth,'supports_keyword_search'=>$keyword,'supports_provider_filter'=>$provider,'supports_geo_filter'=>$geo,'supports_time_filter'=>$time,'budget_profile'=>$budget,'default_enabled'=>$enabled,'safe_for_public_summary'=>$safe,'can_promote_by_itself'=>$solo];
    }

    public static function get(string $key): array { $defs=self::definitions(); return (array)($defs[$key] ?? []); }
    public static function by_lane(): array { return self::group('lane'); }
    public static function by_trust_level(): array { return self::group('trust_level'); }
    private static function group(string $field): array { $out=[]; foreach(self::definitions() as $key=>$def){ $g=(string)($def[$field]??'unknown'); $out[$g][]=$key; } ksort($out); return $out; }

    public static function reddit_credentials_configured(): bool {
        return trim((string)self::opt('lousy_outages_reddit_client_id','')) !== '' && trim((string)self::opt('lousy_outages_reddit_client_secret','')) !== '' && trim((string)self::opt('lousy_outages_reddit_user_agent','')) !== '';
    }

    public static function source_enabled(string $key): bool {
        $map = [
            'public_chatter_bluesky' => 'lousy_outages_public_chatter_bluesky_enabled',
            'public_chatter_mastodon' => 'lousy_outages_public_chatter_mastodon_enabled',
            'public_chatter_gdelt' => 'lousy_outages_public_chatter_gdelt_enabled',
            'public_chatter_reddit' => 'lousy_outages_public_chatter_reddit_enabled',
            'hacker_news_chatter' => 'lo_hn_chatter_enabled',
            'cloudflare_radar' => 'lousy_outages_cloudflare_radar_enabled',
            'caida_ioda' => 'lousy_outages_caida_ioda_enabled',
        ];
        $def = self::get($key);
        $default = !empty($def['default_enabled']) ? '1' : '0';
        return !empty(self::opt($map[$key] ?? 'lo_source_enabled_'.$key, $default));
    }

    public static function runtime_statuses(array $diag = []): array {
        $out=[];
        foreach (self::definitions() as $key=>$def) {
            $enabled = self::source_enabled($key);
            $configured = true;
            if ($key === 'public_chatter_reddit') { $configured = self::reddit_credentials_configured(); }
            if ($key === 'cloudflare_radar') { $configured = trim((string)self::opt('lousy_outages_cloudflare_radar_token','')) !== '' || defined('LOUSY_OUTAGES_CLOUDFLARE_RADAR_TOKEN') || getenv('LOUSY_OUTAGES_CLOUDFLARE_RADAR_TOKEN'); }
            $status = !$enabled ? 'disabled' : ($configured ? 'enabled' : 'not_configured');
            $row = ['label'=>$def['display_name'],'status'=>$status,'lane'=>$def['lane'],'trust_level'=>$def['trust_level'],'capability'=>$def['source_capability'],'requires_auth'=>(bool)$def['requires_auth'],'configured'=>$configured,'enabled'=>$enabled];
            if (isset($diag['source_statuses'][$key]) && is_array($diag['source_statuses'][$key])) { $row = array_merge($row, (array)$diag['source_statuses'][$key]); }
            $out[$key]=$row;
        }
        return $out;
    }

    public static function aggregate_diagnostics(array $diag = []): array {
        $statuses = self::runtime_statuses($diag);
        $out = ['sources_configured'=>[],'sources_enabled'=>[],'sources_attempted'=>[],'sources_not_configured'=>[],'sources_disabled_by_admin'=>[],'sources_skipped_by_budget'=>[],'sources_in_cooldown'=>[],'sources_failed'=>[],'sources_by_lane'=>self::by_lane(),'sources_by_trust_level'=>self::by_trust_level()];
        foreach ($statuses as $key=>$row) {
            if (!empty($row['configured'])) { $out['sources_configured'][]=$key; } else { $out['sources_not_configured'][]=$key; }
            if (!empty($row['enabled'])) { $out['sources_enabled'][]=$key; } else { $out['sources_disabled_by_admin'][]=$key; }
            $status=(string)($row['status']??'');
            if (in_array($status,['enabled','configured','rate_limited','cooldown','budget_skipped'],true)) { $out['sources_attempted'][]=$key; }
            if ($status==='budget_skipped') { $out['sources_skipped_by_budget'][]=$key; }
            if (in_array($status,['cooldown','rate_limited'],true)) { $out['sources_in_cooldown'][]=$key; }
            if (in_array($status,['failed','api_http_error','request_error'],true)) { $out['sources_failed'][]=$key; }
        }
        return $out;
    }

    public static function lane_label(string $lane): string {
        return ['official_status'=>'Official feeds','provider_feed'=>'Provider feeds','public_chatter'=>'Public/social chatter','open_web'=>'Open web','internet_health'=>'Internet health','community_report'=>'Community reports'][$lane] ?? ucwords(str_replace('_',' ',$lane));
    }
}
