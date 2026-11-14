<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

/**
 * Authenticated Microsoft 365 tenant service health.
 *
 * Microsoft does not expose detailed Exchange Online incidents via anonymous RSS.
 * This adapter is deliberately dormant without tenant-admin-approved Graph credentials.
 * Configure LOUSY_OUTAGES_M365_TENANT_ID, _CLIENT_ID, _CLIENT_SECRET in wp-config.php
 * (or the equivalent process environment). Never store tokens/secrets in WP options.
 */
final class Microsoft365ServiceHealth
{
    public const OPTION_STATE = 'lo_microsoft365_graph_issues_v1';
    public const OPTION_DIAGNOSTICS = 'lo_microsoft365_graph_diagnostics_v1';
    private const FRESHNESS_SECONDS = 45 * MINUTE_IN_SECONDS;
    private const ADMIN_URL = 'https://admin.microsoft.com/#/servicehealth';

    private static function credential(string $key): string
    {
        if (defined($key)) {
            return trim((string) constant($key));
        }
        $value = getenv($key);
        return is_string($value) ? trim($value) : '';
    }

    public static function configured(): bool
    {
        foreach (['LOUSY_OUTAGES_M365_TENANT_ID','LOUSY_OUTAGES_M365_CLIENT_ID','LOUSY_OUTAGES_M365_CLIENT_SECRET'] as $key) {
            if (self::credential($key) === '') {
                return false;
            }
        }
        return true;
    }

    public static function admin_url(): string
    {
        return self::ADMIN_URL;
    }

    /**
     * Refresh only from authenticated Graph; failed requests do not fabricate incidents.
     * The last successful snapshot ages out independently after 45 minutes.
     */
    public static function refresh(): array
    {
        $now = time();
        if (!self::configured()) {
            $diag = ['status'=>'not_configured','at'=>gmdate('c',$now),'active_count'=>0];
            update_option(self::OPTION_DIAGNOSTICS,$diag,false);
            return $diag;
        }

        $tenant = self::credential('LOUSY_OUTAGES_M365_TENANT_ID');
        $client = self::credential('LOUSY_OUTAGES_M365_CLIENT_ID');
        if (!preg_match('/^[a-f0-9-]{36}$/i',$tenant) || !preg_match('/^[a-f0-9-]{36}$/i',$client)) {
            return self::error_diagnostic('invalid_configuration',0);
        }

        $auth = wp_remote_post(
            'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token',
            [
                'timeout'=>8,
                'redirection'=>0,
                'sslverify'=>true,
                'headers'=>['Content-Type'=>'application/x-www-form-urlencoded'],
                'body'=>[
                    'client_id'=>$client,
                    'client_secret'=>self::credential('LOUSY_OUTAGES_M365_CLIENT_SECRET'),
                    'scope'=>'https://graph.microsoft.com/.default',
                    'grant_type'=>'client_credentials',
                ],
            ]
        );
        if (is_wp_error($auth)) {
            return self::error_diagnostic('auth_transport_error',0);
        }
        $authCode = (int)wp_remote_retrieve_response_code($auth);
        $tokenJson = json_decode((string)wp_remote_retrieve_body($auth),true);
        $token = is_array($tokenJson) ? (string)($tokenJson['access_token']??'') : '';
        if ($authCode !== 200 || $token === '') {
            return self::error_diagnostic('auth_http_error',$authCode);
        }

        $response = wp_remote_get(
            'https://graph.microsoft.com/v1.0/admin/serviceAnnouncement/issues?$top=100',
            [
                'timeout'=>10,
                'redirection'=>0,
                'sslverify'=>true,
                'headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json'],
            ]
        );
        unset($token,$tokenJson,$auth);
        if (is_wp_error($response)) {
            return self::error_diagnostic('graph_transport_error',0);
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $data = json_decode((string)wp_remote_retrieve_body($response),true);
        if ($code !== 200 || !is_array($data) || !isset($data['value']) || !is_array($data['value'])) {
            return self::error_diagnostic('graph_http_or_schema_error',$code);
        }

        $active = [];
        foreach (array_slice($data['value'],0,100) as $item) {
            if (!is_array($item)) continue;
            $issue = self::normalize_issue($item);
            if ($issue !== null) $active[$issue['id']]=$issue;
        }
        $active = array_values($active);
        $previous = (array)get_option(self::OPTION_STATE,[]);
        $changed = wp_json_encode((array)($previous['issues']??[])) !== wp_json_encode($active);
        update_option(self::OPTION_STATE,[
            'updated_at'=>gmdate('c',$now),
            'expires_at'=>gmdate('c',$now+self::FRESHNESS_SECONDS),
            'issues'=>$active,
            'source'=>'microsoft_graph_authenticated',
        ],false);
        $diag = [
            'status'=>'completed','at'=>gmdate('c',$now),'active_count'=>count($active),
            'http_status'=>$code,'changed'=>$changed,
            'source'=>'Microsoft Graph /admin/serviceAnnouncement/issues',
        ];
        update_option(self::OPTION_DIAGNOSTICS,$diag,false);
        if ($changed && class_exists(Feeds::class)) {
            Feeds::refresh_status_feed_cache('microsoft365-graph');
        }
        return $diag;
    }

    private static function error_diagnostic(string $status, int $code): array
    {
        $diag=['status'=>$status,'at'=>gmdate('c'),'http_status'=>$code,'active_count'=>null];
        update_option(self::OPTION_DIAGNOSTICS,$diag,false);
        return $diag;
    }

    /** Keep only unresolved, provider-issued service-health items. */
    public static function normalize_issue(array $item): ?array
    {
        $id = strtoupper(trim((string)($item['id']??'')));
        $title = trim(wp_strip_all_tags((string)($item['title']??'')));
        if (!preg_match('/^[A-Z]{2}[0-9]{5,12}$/',$id) || $title==='') return null;
        $status = (string)($item['status']??'');
        if (!empty($item['isResolved']) || preg_match('/restored|resolved|closed|completed/i',$status)) return null;
        $start = (string)($item['startDateTime']??'');
        $modified = (string)($item['lastModifiedDateTime']??$start);
        $service = trim(wp_strip_all_tags((string)($item['service']??'Microsoft 365')));
        $impact = trim(wp_strip_all_tags((string)($item['impactDescription']??'')));
        $classification = trim((string)($item['classification']??''));
        return [
            'id'=>$id,'title'=>substr($title,0,220),
            'service'=>substr($service,0,100),'status'=>substr($status,0,60),
            'severity'=>preg_match('/interruption|major/i',$status)?'critical':'minor',
            'classification'=>substr($classification,0,40),
            'impact_description'=>substr($impact,0,360),
            'started_at'=>strtotime($start)>0?gmdate('c',strtotime($start)):gmdate('c'),
            'updated_at'=>strtotime($modified)>0?gmdate('c',strtotime($modified)):gmdate('c'),
        ];
    }

    public static function active_issues(int $now=0): array
    {
        $now=$now?:time();
        $snapshot=get_option(self::OPTION_STATE,[]);
        if (!is_array($snapshot)) return [];
        $expires=strtotime((string)($snapshot['expires_at']??''))?:0;
        if ($expires<$now || $expires>$now+2*HOUR_IN_SECONDS) return [];
        return array_values(array_filter((array)($snapshot['issues']??[]), 'is_array'));
    }

    /**
     * Expose authenticated incidents in dashboard lanes without modifying the
     * canonical provider snapshot or claiming a global healthy state for a tenant.
     */
    public static function augment_state(array $state): array
    {
        $issues=self::active_issues();
        $related=null;
        if (!$issues && class_exists(Microsoft365Signals::class) && class_exists(\SuzyEaston\LousyOutages\Storage\EpisodeStore::class)) {
            $related=Microsoft365Signals::related_provider_reports(
                (new \SuzyEaston\LousyOutages\Storage\EpisodeStore())->publications(150)
            );
        }
        if (!$issues && !$related) return $state;
        $state['providers']=(array)($state['providers']??[]);
        foreach($state['providers'] as $tile) {
            if (sanitize_key((string)($tile['provider_id']??$tile['id']??''))==='microsoft365') return $state;
        }

        if ($issues) {
            $severity=in_array('critical',array_column($issues,'severity'),true)?'major_outage':'degraded';
            $incidents=[];
            foreach ($issues as $issue) {
                $incidents[]=[
                    'id'=>$issue['id'],'provider_id'=>'microsoft365',
                    'provider'=>'Microsoft 365',
                    'title'=>$issue['title'],'display_title'=>$issue['title'],
                    'source_title'=>$issue['title'],
                    'status'=>$issue['severity']==='critical'?'major':'degraded',
                    'impact'=>$issue['severity']==='critical'?'major':'degraded',
                    'service_name'=>$issue['service'],
                    'started_at'=>$issue['started_at'],
                    'updated_at'=>$issue['updated_at'],
                    'last_official_update'=>$issue['updated_at'],
                    'checked_at'=>gmdate('c'),
                    'source_type'=>'microsoft_graph',
                    'url'=>self::ADMIN_URL,
                ];
                $state['outages'][]=$incidents[count($incidents)-1];
            }
            $tile=[
                'id'=>'microsoft365','provider_id'=>'microsoft365','name'=>'Microsoft 365',
                'stateCode'=>$severity,'status'=>$severity,'state'=>'Service issue',
                'tile_kind'=>'outage','lane'=>'outages','sort_key'=>95,
                'verification_status'=>'verified','is_stale'=>false,
                'summary'=>count($issues).' active Microsoft Graph service-health issue(s)',
                'source_type'=>'microsoft_graph','url'=>self::ADMIN_URL,
                'incidents'=>$incidents,'updated_at'=>gmdate('c'),
            ];
            $state['providers'][]=$tile;
            $state['meta']['active_outage_count']=count((array)($state['outages']??[]));
            $state['meta']['affected_provider_count']=count(array_unique(array_map(
                static fn($i)=>sanitize_key((string)($i['provider_id']??'')),(array)$state['outages']
            )));
            $ids=(array)($state['meta']['current_official_provider_ids']??[]);
            $ids[]='microsoft365';
            $state['meta']['current_official_provider_ids']=array_values(array_unique($ids));
            return $state;
        }

        $watch = ($related['correlation_tier'] ?? '') === 'related_reports';
        $tile=[
            'id'=>'microsoft365','provider_id'=>'microsoft365','name'=>'Microsoft 365',
            'stateCode'=>'degraded','status'=>'degraded','state'=>$watch ? 'Related active reports' : 'Unconfirmed reports',
            'tile_kind'=>'signal','lane'=>'signals','sort_key'=>45,
            'verification_status'=>'unconfirmed','is_stale'=>false,
            'summary'=>$watch
                ? sprintf('%d independent providers have active Microsoft-related integration issues. Incident starts differ; a common Microsoft root cause is not established.', count((array)($related['provider_ids']??[])))
                : sprintf('%d independent providers reported Microsoft 365-related incidents within 90 minutes. Shared dependency suspected; root cause unconfirmed.', count((array)($related['provider_ids']??[]))),
            'source_type'=>'cross_provider_corroboration','category'=>'communications',
            'correlation_count'=>count((array)($related['provider_ids']??[])),
            'correlation_confidence'=>$related['confidence'] ?? 'moderate',
            'correlation_tier'=>$related['correlation_tier'] ?? 'onset_cluster',
            'correlation_sources'=>array_values((array)($related['evidence']??[])),
            'url'=>'https://status.cloud.microsoft/m365',
            'incidents'=>[],'data_observed_at'=>gmdate('c',(int)$related['timestamp']),
            'updated_at'=>gmdate('c',(int)$related['timestamp']),
        ];
        $state['signals'][]=$tile;
        $state['providers'][]=$tile;
        $state['meta']['signal_count']=count((array)$state['signals']);
        return $state;
    }
}
