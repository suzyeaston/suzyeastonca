<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\SignalSourceInterface;

class PublicChatterSource implements SignalSourceInterface {
    private array $requestDiagnostics = [];

    public function id(): string { return 'public_chatter'; }
    public function label(): string { return 'Public Chatter Radar'; }
    public function is_configured(): bool { return !empty(get_option('lousy_outages_public_chatter_enabled', '1')); }

    public function collect(array $options = []): array {
        $directEnabled = $this->direct_sources_enabled();
        $sourceCheckboxes = $this->source_checkboxes();
        $window = max(10, min(180, (int) apply_filters('lo_public_chatter_window_minutes', (int)($options['window_minutes'] ?? 30))));
        $thresholds = [
            'watch' => (int) apply_filters('lo_public_chatter_watch_threshold', 3),
            'trending' => (int) apply_filters('lo_public_chatter_trending_threshold', 6),
            'hot' => (int) apply_filters('lo_public_chatter_hot_threshold', 12),
            'queries_per_provider_source' => (int) apply_filters('lo_public_chatter_queries_per_provider_source', 4),
            'active_incident_queries_per_provider' => (int) apply_filters('lo_public_chatter_active_incident_queries_per_provider', 5),
            'source_budgets' => $this->source_budgets(),
        ];
        $providers = Providers::list();
        $activeIncidents = $this->active_incident_seed_providers($providers);
        $queryBuild = $this->build_query_sets($providers, $activeIncidents, $thresholds);
        $queries = (array) apply_filters('lo_public_chatter_queries', $queryBuild['queries'], $providers, $activeIncidents);
        $queries = $this->normalize_query_sets($queries, $queryBuild['providers']);

        $diag = $this->base_diagnostics($sourceCheckboxes, $directEnabled, $window, $thresholds, $queryBuild, $activeIncidents);
        $configured = $this->is_configured();
        $diag['configured'] = $configured;
        $diag['attempted'] = $configured;

        if (!$configured) {
            foreach (array_keys($sourceCheckboxes) as $sourceId) {
                $diag['skipped_sources'][$sourceId][] = 'master_disabled';
            }
            $diag['source_statuses'] = $this->source_statuses($sourceCheckboxes, $directEnabled, $diag);
            $diag['official_incident_corroboration'] = $this->official_incident_corroboration($activeIncidents, $diag, [], []);
            $this->store_diagnostics($diag);
            return [];
        }

        $enabledRuntimeSources = [];
        foreach ($sourceCheckboxes as $sourceId => $checked) {
            if (!$checked) {
                $diag['skipped_sources'][$sourceId][] = 'source_checkbox_disabled';
                continue;
            }
            if (!$directEnabled) {
                $diag['skipped_sources'][$sourceId][] = 'direct_sources_gate_disabled';
                continue;
            }
            $enabledRuntimeSources[$sourceId] = true;
        }

        $signals = [];
        $perProviderSourceCounts = [];
        $sourceBudgetUsed = array_fill_keys(array_keys($enabledRuntimeSources), 0);
        foreach ($queries as $providerId => $row) {
            $provider = (array)($row['provider'] ?? ['name' => ucfirst((string)$providerId), 'category'=>'general']);
            $providerName = (string)($provider['name'] ?? $providerId);
            $providerCategory = (string)($provider['category'] ?? 'general');
            $providerQueries = array_values(array_filter(array_map('strval', (array)($row['queries'] ?? []))));
            foreach ($enabledRuntimeSources as $sourceId => $_enabled) {
                $sourceBudget = (int)($thresholds['source_budgets'][$sourceId] ?? $thresholds['queries_per_provider_source']);
                $sourceBudgetRemaining = max(0, $sourceBudget - (int)($sourceBudgetUsed[$sourceId] ?? 0));
                if ($sourceBudgetRemaining <= 0) {
                    $diag['skipped_sources'][$sourceId][] = 'skipped_due_to_budget';
                    $diag['source_request_details'][$sourceId]['queries_skipped_due_to_budget'] = (int)($diag['source_request_details'][$sourceId]['queries_skipped_due_to_budget'] ?? 0) + count($providerQueries);
                    if ($sourceId === 'public_chatter_gdelt') {
                        $diag['gdelt_queries_skipped_due_to_budget'] = (int)$diag['gdelt_queries_skipped_due_to_budget'] + count($providerQueries);
                    }
                    continue;
                }
                $mentions = $this->collect_mentions($sourceId, $providerQueries, $window, min((int)$thresholds['queries_per_provider_source'], $sourceBudgetRemaining));
                $requestDiag = $this->requestDiagnostics[$sourceId] ?? [];
                $queriesAttemptedThisSource = (int)($requestDiag['queries_attempted'] ?? 0);
                $sourceBudgetUsed[$sourceId] = (int)($sourceBudgetUsed[$sourceId] ?? 0) + $queriesAttemptedThisSource;
                $diag['queries_attempted'] += $queriesAttemptedThisSource;
                $diag['source_request_details'][$sourceId]['queries_attempted'] = (int)($diag['source_request_details'][$sourceId]['queries_attempted'] ?? 0) + $queriesAttemptedThisSource;
                if ($sourceId === 'public_chatter_mastodon') {
                    $diag['source_request_details'][$sourceId]['instances_queried'] = array_values(array_unique(array_merge((array)($diag['source_request_details'][$sourceId]['instances_queried'] ?? []), (array)($requestDiag['instances_queried'] ?? []))));
                }
                if ($sourceId === 'public_chatter_gdelt') {
                    foreach (['gdelt_attempted','gdelt_rate_limited','gdelt_last_response_code','gdelt_cooldown_until','gdelt_rows_seen','gdelt_watch_candidates'] as $gdeltKey) {
                        if (array_key_exists($gdeltKey, $requestDiag)) {
                            $diag[$gdeltKey] = in_array($gdeltKey, ['gdelt_rows_seen','gdelt_watch_candidates'], true) ? (int)$diag[$gdeltKey] + (int)$requestDiag[$gdeltKey] : $requestDiag[$gdeltKey];
                        }
                    }
                }
                foreach ((array)($requestDiag['errors'] ?? []) as $errorReason) {
                    $diag['skipped_sources'][$sourceId][] = (string)$errorReason;
                }

                $count = count($mentions);
                $diag['mentions_seen_by_source'][$sourceId] = (int)($diag['mentions_seen_by_source'][$sourceId] ?? 0) + $count;
                $diag['mentions_seen_by_provider'][$providerId] = (int)($diag['mentions_seen_by_provider'][$providerId] ?? 0) + $count;
                $perProviderSourceCounts[$providerId][$sourceId] = (int)($perProviderSourceCounts[$providerId][$sourceId] ?? 0) + $count;

                if ($count <= 0) {
                    $diag['skipped_sources'][$sourceId][] = 'no_results';
                    continue;
                }

                $severity = $this->severity_for_count($count, $thresholds);
                if ($severity === '') {
                    $diag['skipped_sources'][$sourceId][] = 'below_threshold';
                    $diag['watch_candidates'][] = [
                        'provider_id' => (string)$providerId,
                        'provider_name' => $providerName,
                        'source' => $sourceId,
                        'source_label' => $this->source_label($sourceId),
                        'count' => $count,
                        'reason' => 'below_threshold',
                    ];
                    continue;
                }

                $signals[] = $this->build_signal($sourceId, (string)$providerId, $providerName, $providerCategory, $severity, $count, $window, $mentions);
                $diag['signals_built_by_provider'][$providerId] = (int)($diag['signals_built_by_provider'][$providerId] ?? 0) + 1;
            }
        }

        $diag['usable_results'] = count($signals);
        $diag['rows_stored'] = count($signals);
        $diag['watch_candidate_count'] = count($diag['watch_candidates']);
        $diag['source_statuses'] = $this->source_statuses($sourceCheckboxes, $directEnabled, $diag);
        $diag['official_incident_corroboration'] = $this->official_incident_corroboration($activeIncidents, $diag, $perProviderSourceCounts, array_keys($enabledRuntimeSources));
        $this->store_diagnostics($diag);
        return $signals;
    }

    public function direct_sources_enabled(): bool {
        $flag = defined('LOUSY_OUTAGES_ENABLE_DIRECT_PUBLIC_CHATTER') ? (bool) LOUSY_OUTAGES_ENABLE_DIRECT_PUBLIC_CHATTER : true;
        return (bool) apply_filters('lo_public_chatter_direct_sources_enabled', $flag);
    }

    public function source_checkboxes(): array {
        return [
            'public_chatter_bluesky' => !empty(get_option('lousy_outages_public_chatter_bluesky_enabled', '1')),
            'public_chatter_mastodon' => !empty(get_option('lousy_outages_public_chatter_mastodon_enabled', '1')),
            'public_chatter_gdelt' => !empty(get_option('lousy_outages_public_chatter_gdelt_enabled', '1')),
        ];
    }

    public function canadian_infrastructure_watchlist(): array {
        return [
            'telecom' => ['rogers'=>'Rogers','shaw'=>'Shaw','bell'=>'Bell','telus'=>'TELUS','freedom_mobile'=>'Freedom Mobile','videotron'=>'Videotron','fizz'=>'Fizz','koodo'=>'Koodo','virgin_plus'=>'Virgin Plus','public_mobile'=>'Public Mobile'],
            'payments' => ['interac'=>'Interac','etransfer'=>'e-Transfer','interac_debit'=>'Interac Debit','moneris'=>'Moneris','global_payments'=>'Global Payments','stripe_canada'=>'Stripe Canada'],
            'banking' => ['rbc'=>'RBC / Royal Bank','td_canada_trust'=>'TD / TD Canada Trust','scotiabank'=>'Scotiabank','bmo'=>'BMO / Bank of Montreal','cibc'=>'CIBC','national_bank'=>'National Bank','tangerine'=>'Tangerine','simplii'=>'Simplii','eq_bank'=>'EQ Bank','vancity'=>'Vancity','coast_capital'=>'Coast Capital','desjardins'=>'Desjardins'],
            'government_login' => ['bc_services_card'=>'BC Services Card','cra_my_account'=>'CRA My Account','service_canada'=>'Service Canada'],
            'transit' => ['translink_compass'=>'TransLink Compass'],
            'emergency_services' => ['911'=>'911','ecomm_911'=>'E-Comm 911'],
        ];
    }

    private function base_diagnostics(array $sourceCheckboxes, bool $directEnabled, int $window, array $thresholds, array $queryBuild, array $activeIncidents): array {
        return [
            'configured' => false,
            'attempted' => false,
            'direct_sources_enabled' => $directEnabled,
            'direct_sources_disabled_by_safe_default' => !$directEnabled,
            'enabled_sources' => $sourceCheckboxes,
            'skipped_sources' => ['public_chatter_bluesky'=>[],'public_chatter_mastodon'=>[],'public_chatter_gdelt'=>[]],
            'source_statuses' => [],
            'providers_scanned' => array_values(array_map(static fn($p) => ['provider_id'=>(string)$p['id'], 'provider_name'=>(string)$p['name'], 'category'=>(string)($p['category'] ?? 'general'), 'seed_types'=>(array)($p['seed_types'] ?? [])], (array)$queryBuild['providers'])),
            'providers_scanned_count' => count((array)$queryBuild['providers']),
            'active_incident_seed_providers' => array_values($activeIncidents),
            'canadian_infrastructure_watchlist' => $this->watchlist_summary(),
            'canadian_infrastructure_providers_scanned' => $this->flat_watchlist_providers(),
            'queries_attempted' => 0,
            'queries_planned' => (int)$queryBuild['query_count'],
            'mentions_seen_by_source' => ['public_chatter_bluesky'=>0,'public_chatter_mastodon'=>0,'public_chatter_gdelt'=>0],
            'mentions_seen_by_provider' => [],
            'signals_built_by_provider' => [],
            'thresholds' => $thresholds,
            'scan_window_minutes' => $window,
            'ran_at' => gmdate('c'),
            'watch_candidates' => [],
            'watch_candidate_count' => 0,
            'official_incident_corroboration' => [],
            'source_budgets' => $thresholds['source_budgets'],
            'source_request_details' => [
                'public_chatter_bluesky' => ['queries_attempted'=>0,'queries_skipped_due_to_budget'=>0],
                'public_chatter_mastodon' => ['queries_attempted'=>0,'queries_skipped_due_to_budget'=>0,'instances_queried'=>[]],
                'public_chatter_gdelt' => ['queries_attempted'=>0,'queries_skipped_due_to_budget'=>0],
            ],
            'gdelt_enabled' => !empty($sourceCheckboxes['public_chatter_gdelt']),
            'gdelt_attempted' => false,
            'gdelt_rate_limited' => false,
            'gdelt_cooldown_until' => $this->gdelt_cooldown_until(),
            'gdelt_last_response_code' => (int)get_option('lo_public_chatter_gdelt_last_response_code', 0),
            'gdelt_queries_skipped_due_to_budget' => 0,
            'gdelt_rows_seen' => 0,
            'gdelt_watch_candidates' => 0,
            'usable_results' => 0,
            'rows_stored' => 0,
        ];
    }

    private function store_diagnostics(array $diag): void {
        foreach ($diag['skipped_sources'] as $sourceId => $reasons) {
            $diag['skipped_sources'][$sourceId] = array_values(array_unique(array_filter(array_map('strval', (array)$reasons))));
        }
        $diag['gdelt_watch_candidates'] = count(array_filter((array)($diag['watch_candidates'] ?? []), static fn($c): bool => (($c['source'] ?? '') === 'public_chatter_gdelt')));
        update_option('lo_diag_'.$this->id(), $diag, false);
    }

    private function build_query_sets(array $providers, array $activeIncidents, array $thresholds): array {
        $entries = [];
        foreach ($this->default_queries($providers) as $providerId => $queries) {
            $provider = (array)($providers[$providerId] ?? ['name'=>ucfirst((string)$providerId), 'category'=>'general']);
            $entries[$providerId] = ['provider'=>['id'=>(string)$providerId,'name'=>(string)($provider['name'] ?? $providerId),'category'=>(string)($provider['category'] ?? 'general'),'seed_types'=>['default']], 'queries'=>$queries];
        }
        foreach ($activeIncidents as $incident) {
            $providerId = (string)($incident['provider_id'] ?? '');
            if ($providerId === '') { continue; }
            $providerName = (string)($incident['provider_name'] ?? $providerId);
            if (!isset($entries[$providerId])) {
                $entries[$providerId] = ['provider'=>['id'=>$providerId,'name'=>$providerName,'category'=>(string)($incident['category'] ?? 'official_incident'),'seed_types'=>[]], 'queries'=>[]];
            }
            $entries[$providerId]['provider']['seed_types'][] = 'active_incident';
            $entries[$providerId]['queries'] = array_merge((array)$entries[$providerId]['queries'], $this->active_incident_queries($providerName, (string)($incident['title'] ?? ''), (int)$thresholds['active_incident_queries_per_provider']));
        }
        foreach ($this->canadian_infrastructure_watchlist() as $category => $items) {
            foreach ($items as $providerId => $name) {
                if (!isset($entries[$providerId])) {
                    $entries[$providerId] = ['provider'=>['id'=>(string)$providerId,'name'=>(string)$name,'category'=>(string)$category,'seed_types'=>[]], 'queries'=>[]];
                }
                $entries[$providerId]['provider']['seed_types'][] = 'canadian_infrastructure';
                $entries[$providerId]['queries'] = array_merge((array)$entries[$providerId]['queries'], $this->provider_queries((string)$name));
            }
        }
        return $this->normalize_query_sets($entries, []);
    }

    private function normalize_query_sets(array $queries, array $providers): array {
        $normalized = [];
        foreach ($queries as $providerId => $row) {
            if (is_array($row) && array_key_exists('queries', $row)) {
                $provider = (array)($row['provider'] ?? ($providers[$providerId] ?? []));
                $providerQueries = (array)$row['queries'];
            } else {
                $provider = (array)($providers[$providerId] ?? []);
                $providerQueries = (array)$row;
            }
            $id = sanitize_key((string)($provider['id'] ?? $providerId));
            if ($id === '') { continue; }
            $provider['id'] = $id;
            $provider['name'] = (string)($provider['name'] ?? ucfirst($id));
            $provider['seed_types'] = array_values(array_unique((array)($provider['seed_types'] ?? ['custom'])));
            $normalized[$id] = ['provider'=>$provider, 'queries'=>array_values(array_unique(array_filter(array_map(static fn($q) => trim((string)$q), $providerQueries))))];
            $normalized[$id]['queries'] = array_slice($normalized[$id]['queries'], 0, 8);
        }
        if ($providers === []) {
            return ['queries'=>$normalized, 'providers'=>array_values(array_column($normalized, 'provider')), 'query_count'=>array_sum(array_map(static fn($r) => count((array)$r['queries']), $normalized))];
        }
        return $normalized;
    }

    private function default_queries(array $providers): array {
        $map = [
            'cloudflare' => ['cloudflare down','cloudflare outage','cloudflare warp down'],
            'aws' => ['aws down','aws outage','aws us-east-1 errors'],
            'azure' => ['azure down','azure outage','microsoft 365 down'],
            'google_cloud' => ['google cloud outage','gcp outage','google cloud errors'],
            'google_workspace' => ['google workspace outage','gmail down','google drive down'],
            'openai' => ['chatgpt down','openai outage','openai api errors'],
            'slack' => ['slack down','slack outage'],
            'github' => ['github down','github actions failing','github outage'],
        ];
        return array_intersect_key($map, $providers);
    }

    private function provider_queries(string $name): array {
        $base = trim($name);
        $plain = trim((string)preg_replace('/\s*\/.*$/', '', $base));
        $queries = [$base.' outage', $base.' down', $base.' errors'];
        if ($plain !== '' && strcasecmp($plain, $base) !== 0) {
            $queries[] = $plain.' outage';
            $queries[] = $plain.' down';
        }
        return array_values(array_unique($queries));
    }

    private function active_incident_queries(string $providerName, string $title, int $limit): array {
        $queries = [$providerName.' outage', $providerName.' down', $providerName.' errors'];
        $short = $this->short_phrase($title);
        if ($short !== '') { $queries[] = $short; }
        $keyword = $this->incident_keyword($title);
        if ($keyword !== '') { $queries[] = $providerName.' '.$keyword; }
        return array_slice(array_values(array_unique(array_filter($queries))), 0, max(1, $limit));
    }

    private function short_phrase(string $text): string {
        $text = trim((string)preg_replace('/[^\pL\pN\s\-]/u', ' ', $text));
        $parts = preg_split('/\s+/', $text) ?: [];
        return trim(implode(' ', array_slice($parts, 0, 6)));
    }

    private function incident_keyword(string $title): string {
        $words = ['login','api','errors','latency','degraded','outage','payments','email','network','dns','dashboard','e-transfer','debit'];
        $lower = strtolower($title);
        foreach ($words as $word) { if (str_contains($lower, $word)) { return $word; } }
        return '';
    }

    private function active_incident_seed_providers(array $providers): array {
        $snapshot = function_exists('lousy_outages_get_snapshot') ? \lousy_outages_get_snapshot(false) : get_option('lousy_outages_snapshot', []);
        if (!is_array($snapshot) || empty($snapshot['providers']) || !is_array($snapshot['providers'])) {
            $snapshot = get_option('lousy_outages_snapshot', []);
        }
        $out = [];
        foreach ((array)($snapshot['providers'] ?? []) as $tile) {
            if (!is_array($tile)) { continue; }
            $providerId = sanitize_key((string)($tile['provider'] ?? $tile['id'] ?? ''));
            if ($providerId === '') { continue; }
            $status = strtolower((string)($tile['status'] ?? $tile['stateCode'] ?? ''));
            $incidents = (array)($tile['incidents'] ?? []);
            $active = !in_array($status, ['', 'operational', 'ok', 'unknown'], true) || !empty($incidents);
            if (!$active) { continue; }
            $title = (string)($tile['summary'] ?? $tile['status_label'] ?? $tile['state'] ?? 'Active incident');
            if (!empty($incidents[0]) && is_array($incidents[0])) {
                $title = (string)($incidents[0]['name'] ?? $incidents[0]['title'] ?? $title);
                $status = strtolower((string)($incidents[0]['status'] ?? $status));
            }
            $out[$providerId] = [
                'provider_id' => $providerId,
                'provider_name' => (string)($tile['name'] ?? $tile['provider_name'] ?? ($providers[$providerId]['name'] ?? $providerId)),
                'official_status' => $status ?: 'active',
                'title' => $title,
                'category' => (string)($providers[$providerId]['category'] ?? 'official_incident'),
            ];
        }
        return array_values($out);
    }

    private function collect_mentions(string $sourceId, array $queries, int $window, int $limit): array {
        $out=[]; $diag=['queries_attempted'=>0,'errors'=>[],'instances_queried'=>[]];
        if ($sourceId === 'public_chatter_gdelt') {
            $diag += ['gdelt_attempted'=>false,'gdelt_rate_limited'=>false,'gdelt_cooldown_until'=>$this->gdelt_cooldown_until(),'gdelt_last_response_code'=>(int)get_option('lo_public_chatter_gdelt_last_response_code', 0),'gdelt_rows_seen'=>0,'gdelt_watch_candidates'=>0];
            if ($diag['gdelt_cooldown_until'] !== '') {
                $diag['errors'][] = 'cooldown';
                $this->requestDiagnostics[$sourceId] = $diag;
                return [];
            }
        }
        foreach(array_slice($queries,0,max(1,$limit)) as $q){
            $diag['queries_attempted']++;
            if ($sourceId==='public_chatter_bluesky') { $result=$this->search_bluesky($q,$window); }
            elseif ($sourceId==='public_chatter_mastodon') { $result=$this->search_mastodon($q,$window); }
            elseif ($sourceId==='public_chatter_gdelt') { $diag['gdelt_attempted']=true; $result=$this->search_gdelt($q); }
            else { $result=['mentions'=>[],'errors'=>['request_error']]; }
            $out=array_merge($out,(array)($result['mentions'] ?? []));
            $diag['errors']=array_merge($diag['errors'], (array)($result['errors'] ?? []));
            $diag['instances_queried']=array_merge($diag['instances_queried'], (array)($result['instances_queried'] ?? []));
            if ($sourceId === 'public_chatter_gdelt') {
                $diag['gdelt_rows_seen'] += (int)($result['rows_seen'] ?? 0);
                if (isset($result['response_code'])) { $diag['gdelt_last_response_code'] = (int)$result['response_code']; }
                if (!empty($result['rate_limited'])) { $diag['gdelt_rate_limited'] = true; }
                $cooldownUntil = $this->gdelt_cooldown_until();
                if ($cooldownUntil !== '') { $diag['gdelt_cooldown_until'] = $cooldownUntil; break; }
            }
        }
        $this->requestDiagnostics[$sourceId] = $diag;
        return array_values(array_unique($out));
    }

    private function search_bluesky(string $q,int $window): array {
        $url = add_query_arg(['q'=>$q,'limit'=>25,'sort'=>'latest'],'https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts');
        $res = wp_remote_get($url,['timeout'=>8]);
        if(is_wp_error($res)) { return ['mentions'=>[],'errors'=>['request_error']]; }
        if((int)wp_remote_retrieve_response_code($res)>=400) { return ['mentions'=>[],'errors'=>['api_http_error']]; }
        $body = json_decode((string)wp_remote_retrieve_body($res), true); $posts=(array)($body['posts']??[]); $min=time()-$window*60; $hashes=[];
        foreach($posts as $p){ $created = strtotime((string)($p['record']['createdAt'] ?? $p['indexedAt'] ?? '')); if($created && $created < $min) continue; $hashes[] = hash('sha256', (string)($p['uri'] ?? '').'|'.substr((string)($p['record']['text'] ?? ''),0,60)); }
        return ['mentions'=>$hashes,'errors'=>[]];
    }

    private function search_mastodon(string $q,int $window): array {
        $instances=(array)apply_filters('lo_public_chatter_mastodon_instances',['https://mastodon.social']); $hashes=[]; $min=time()-$window*60; $errors=[]; $queried=[];
        foreach(array_slice($instances,0,2) as $instance){ $instance=rtrim((string)$instance,'/'); $queried[]=$instance; $url = $instance.'/api/v2/search?q='.rawurlencode($q).'&type=statuses&limit=20'; $res=wp_remote_get($url,['timeout'=>8]); if(is_wp_error($res)){ $errors[]='request_error'; continue; } if((int)wp_remote_retrieve_response_code($res)>=400){ $errors[]='api_http_error'; continue; } $body=json_decode((string)wp_remote_retrieve_body($res),true); foreach((array)($body['statuses']??[]) as $s){ $created=strtotime((string)($s['created_at']??'')); if($created && $created<$min) continue; $hashes[] = hash('sha256',(string)($s['url']??'').'|'.substr(wp_strip_all_tags((string)($s['content']??'')),0,60)); }}
        return ['mentions'=>$hashes,'errors'=>array_values(array_unique($errors)),'instances_queried'=>array_values(array_unique($queried))];
    }

    private function search_gdelt(string $q): array {
        $cacheKey = 'lo_public_chatter_gdelt_cache_' . md5($q);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return ['mentions'=>(array)($cached['mentions'] ?? []),'errors'=>[],'rows_seen'=>(int)($cached['rows_seen'] ?? 0),'response_code'=>(int)get_option('lo_public_chatter_gdelt_last_response_code', 0),'cached'=>true];
        }
        $url=add_query_arg(['query'=>$q,'mode'=>'ArtList','format'=>'json','timespan'=>'60m','sort'=>'datedesc'],'https://api.gdeltproject.org/api/v2/doc/doc');
        $res=wp_remote_get($url,['timeout'=>8]);
        if(is_wp_error($res)) { $this->gdelt_register_failure(0, 'timeout'); return ['mentions'=>[],'errors'=>['request_error','cooldown'],'response_code'=>0,'rate_limited'=>true]; }
        $code=(int)wp_remote_retrieve_response_code($res); update_option('lo_public_chatter_gdelt_last_response_code', $code, false);
        if($code===429 || $code===403 || $code>=500) { $this->gdelt_register_failure($code, 'rate_limit'); return ['mentions'=>[],'errors'=>[$code===429?'rate_limited':'api_http_error','cooldown'],'response_code'=>$code,'rate_limited'=>true]; }
        if($code>=400) return ['mentions'=>[],'errors'=>['api_http_error'],'response_code'=>$code];
        delete_option('lo_public_chatter_gdelt_failure_count');
        $body=json_decode((string)wp_remote_retrieve_body($res),true); $arts=(array)($body['articles']??[]); $hashes=[]; foreach(array_slice($arts,0,12) as $a){ $hashes[] = hash('sha256',(string)($a['url']??'').'|'.(string)($a['title']??'')); }
        $summary = ['mentions'=>array_values(array_unique($hashes)), 'rows_seen'=>count($arts)];
        set_transient($cacheKey, $summary, (int)apply_filters('lo_public_chatter_gdelt_cache_ttl', 20 * MINUTE_IN_SECONDS));
        return ['mentions'=>$summary['mentions'],'errors'=>[],'rows_seen'=>$summary['rows_seen'],'response_code'=>$code];
    }

    private function source_budgets(): array {
        return (array) apply_filters('lo_public_chatter_source_budgets', [
            'public_chatter_bluesky' => 10,
            'public_chatter_mastodon' => 6,
            'public_chatter_gdelt' => 3,
        ]);
    }

    private function gdelt_cooldown_until(): string {
        $until = (int)get_transient('lo_public_chatter_gdelt_cooldown_until');
        return $until > time() ? gmdate('c', $until) : '';
    }

    private function gdelt_register_failure(int $code, string $reason): void {
        $count = max(1, (int)get_option('lo_public_chatter_gdelt_failure_count', 0) + 1);
        update_option('lo_public_chatter_gdelt_failure_count', $count, false);
        $seconds = min(6 * HOUR_IN_SECONDS, (15 * MINUTE_IN_SECONDS) * (2 ** min(4, $count - 1)));
        if ($code === 429 || $code === 403) { $seconds = max($seconds, HOUR_IN_SECONDS); }
        $until = time() + $seconds;
        set_transient('lo_public_chatter_gdelt_cooldown_until', $until, $seconds);
        update_option('lo_public_chatter_gdelt_last_response_code', $code, false);
    }

    private function source_statuses(array $checkboxes, bool $directEnabled, array $diag): array {
        $out = ['hacker_news_chatter'=>['label'=>'HN chatter','status'=>!empty(get_option('lo_hn_chatter_enabled', '1')) ? 'enabled' : 'disabled']];
        foreach ($checkboxes as $sourceId => $checked) {
            $reasons = (array)($diag['skipped_sources'][$sourceId] ?? []);
            $status = !$checked ? 'disabled' : ($directEnabled ? 'enabled' : 'blocked_by_safe_default');
            if ($checked && $directEnabled && in_array('cooldown', $reasons, true)) { $status = 'cooldown'; }
            if ($checked && $directEnabled && (!empty($diag['gdelt_rate_limited'])) && $sourceId === 'public_chatter_gdelt') { $status = 'rate_limited'; }
            if ($checked && $directEnabled && in_array('skipped_due_to_budget', $reasons, true)) { $status = 'budget_skipped'; }
            $out[$sourceId] = ['label'=>$this->source_label($sourceId),'status'=>$status,'reasons'=>$reasons,'last_response_code'=>$sourceId==='public_chatter_gdelt' ? (int)($diag['gdelt_last_response_code'] ?? 0) : 0,'cooldown_until'=>$sourceId==='public_chatter_gdelt' ? (string)($diag['gdelt_cooldown_until'] ?? '') : ''];
        }
        $cfDiag = (array)get_option('lo_diag_cloudflare_radar', []);
        $out['cloudflare_radar'] = ['label'=>'Cloudflare Radar (external telemetry)','status'=>!empty($cfDiag['configured']) ? 'configured' : 'not_configured'];
        return $out;
    }

    private function official_incident_corroboration(array $activeIncidents, array $diag, array $perProviderSourceCounts, array $enabledRuntimeSourceIds): array {
        $rows = [];
        foreach ($activeIncidents as $incident) {
            $providerId = (string)($incident['provider_id'] ?? '');
            $promoted = (int)($diag['signals_built_by_provider'][$providerId] ?? 0) > 0;
            $watch = 0;
            foreach ((array)$diag['watch_candidates'] as $candidate) {
                if (($candidate['provider_id'] ?? '') === $providerId) { $watch += (int)($candidate['count'] ?? 0); }
            }
            if (!$enabledRuntimeSourceIds) { $label = 'No chatter sources enabled'; }
            elseif ($promoted) { $label = 'Public corroboration'; }
            elseif ($watch > 0 || !empty($perProviderSourceCounts[$providerId])) { $label = 'Public watch'; }
            else { $label = 'Official only'; }
            $rows[] = [
                'provider_id'=>$providerId,
                'provider_name'=>(string)($incident['provider_name'] ?? $providerId),
                'official_status'=>(string)($incident['official_status'] ?? 'active'),
                'public_chatter_promoted'=>$promoted,
                'watch_candidates'=>$watch,
                'sources_checked'=>array_map(fn($s) => $this->source_label((string)$s), $enabledRuntimeSourceIds),
                'result_label'=>$label,
            ];
        }
        return $rows;
    }

    private function watchlist_summary(): array {
        $summary = [];
        foreach ($this->canadian_infrastructure_watchlist() as $category => $items) {
            $summary[] = ['category'=>$category, 'label'=>$this->watchlist_category_label($category), 'count'=>count($items), 'providers'=>array_values($items)];
        }
        return $summary;
    }

    private function flat_watchlist_providers(): array {
        $out=[]; foreach($this->canadian_infrastructure_watchlist() as $category=>$items){ foreach($items as $id=>$name){ $out[]=['provider_id'=>(string)$id,'provider_name'=>(string)$name,'category'=>(string)$category]; }} return $out;
    }

    private function watchlist_category_label(string $category): string {
        $labels=['telecom'=>'Telecom','payments'=>'Payments','banking'=>'Banking','government_login'=>'Government login','transit'=>'Transit','emergency_services'=>'Emergency services'];
        return $labels[$category] ?? ucwords(str_replace('_',' ',$category));
    }

    private function source_label(string $sourceId): string {
        $labels=['public_chatter_bluesky'=>'Bluesky','public_chatter_mastodon'=>'Mastodon','public_chatter_gdelt'=>'GDELT open web','hacker_news_chatter'=>'HN chatter'];
        return $labels[$sourceId] ?? $sourceId;
    }

    private function severity_for_count(int $count, array $t): string { if($count >= $t['hot']) return 'hot'; if($count >= $t['trending']) return 'trending'; if($count >= $t['watch']) return 'watch'; return ''; }
    private function build_signal(string $source,string $providerId,string $providerName,string $category,string $severity,int $count,int $window,array $mentions): array {
        $confMap=['watch'=>25,'trending'=>45,'hot'=>65]; if($source==='public_chatter_gdelt') $confMap=['watch'=>35,'trending'=>55,'hot'=>70];
        $msg = $source==='public_chatter_gdelt' ? "Recent open-web/news mentions suggest a possible {$providerName} issue. Official status may still be unconfirmed." : "Public posts mentioning possible {$providerName} issues increased recently. This is unconfirmed.";
        return ['source'=>$source,'provider_id'=>$providerId,'provider_name'=>$providerName,'category'=>$source==='public_chatter_gdelt'?'open_web':$category,'region'=>'global','signal_type'=>'public_chatter','severity'=>$severity,'confidence'=>min(85,max(0,(int)$confMap[$severity])),'title'=>$source==='public_chatter_gdelt'?"Open web mentions increasing for {$providerName}":"Public chatter mentions increasing for {$providerName}",'message'=>$msg,'url'=>$this->safe_url_for_source($source,$providerName),'observed_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$window*60),'raw_hash'=>hash('sha256',$source.'|'.$providerId.'|'.$severity.'|'.count($mentions).'|'.implode('|',$mentions))];
    }
    private function safe_url_for_source(string $source, string $provider): string { if($source==='public_chatter_bluesky') return 'https://bsky.app/search?q='.rawurlencode($provider.' outage'); if($source==='public_chatter_mastodon') return 'https://mastodon.social'; if($source==='public_chatter_gdelt') return 'https://api.gdeltproject.org/api/v2/doc/doc'; return ''; }
}
