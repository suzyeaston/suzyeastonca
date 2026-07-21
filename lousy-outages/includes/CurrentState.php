<?php
declare(strict_types=1);

if (! defined('ABSPATH')) { exit; }

/**
 * Read the validated canonical saved snapshot and normalize public current lanes.
 * This accessor is intentionally read-only: no provider collection, history rebuild,
 * migration, synthetic fallback snapshot, or cache write is performed here.
 */
function lousy_outages_current_state_incident_ids(array $incidents): array {
    $ids = [];
    foreach ($incidents as $incident) {
        if (!is_array($incident)) { continue; }
        $provider = sanitize_key((string)($incident['provider_id'] ?? $incident['provider'] ?? ''));
        $id = trim((string)($incident['id'] ?? $incident['guid'] ?? ''));
        if ('' === $id) {
            $id = sha1($provider . '|' . (string)($incident['title'] ?? $incident['display_title'] ?? $incident['summary'] ?? '') . '|' . (string)($incident['started_at'] ?? $incident['startedAt'] ?? '') . '|' . (string)($incident['updated_at'] ?? $incident['updatedAt'] ?? $incident['last_official_update'] ?? ''));
        }
        $ids[] = $provider . ':' . $id;
    }
    sort($ids);
    return $ids;
}

function lousy_outages_get_current_state(): array {
    $snapshot = get_option('lousy_outages_snapshot', []);
    $schema = defined('LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION') ? (int) LOUSY_OUTAGES_SNAPSHOT_SCHEMA_VERSION : 0;
    $version = defined('LOUSY_OUTAGES_VERSION') ? (string) LOUSY_OUTAGES_VERSION : '';
    $base = [
        'outages' => [], 'signals' => [], 'unverified' => [], 'operational' => [], 'providers' => [],
        'meta' => ['active_outage_count'=>0,'affected_provider_count'=>0,'signal_count'=>0,'unverified_count'=>0,'operational_count'=>0,'generated_at'=>gmdate('c'),'freshness_window_seconds'=>function_exists('lousy_outages_signal_freshness_seconds') ? lousy_outages_signal_freshness_seconds() : 2700,'current_official_provider_ids'=>[]],
        'fetched_at' => '', 'source' => 'missing_snapshot', 'errors' => [], 'plugin_version' => $version, 'snapshot_schema_version' => $schema,
    ];
    if (!is_array($snapshot) || empty($snapshot['providers']) || (function_exists('lousy_outages_snapshot_schema_is_current') && !lousy_outages_snapshot_schema_is_current($snapshot))) {
        $providers = \SuzyEaston\LousyOutages\Providers::enabled();
        foreach ($providers as $id => $provider) {
            $base['unverified'][] = ['id'=>sanitize_key((string)$id),'provider_id'=>sanitize_key((string)$id),'name'=>(string)($provider['name'] ?? $id),'lane'=>'unverified','stale_label'=>'Verification delayed; no valid saved provider snapshot is available.'];
        }
        $base['providers'] = $base['unverified'];
        $base['meta']['unverified_count'] = count($base['unverified']);
        $base['errors'][] = ['code'=>'missing_or_invalid_snapshot','message'=>'No valid saved Lousy Outages snapshot is available.'];
        return $base;
    }
    $derived_state = function_exists('lousy_outages_current_state_from_snapshot') ? lousy_outages_current_state_from_snapshot($snapshot) : [];
    $stored_state = isset($snapshot['current_state']) && is_array($snapshot['current_state']) ? $snapshot['current_state'] : [];
    $derived_outage_ids = lousy_outages_current_state_incident_ids((array)($derived_state['outages'] ?? []));
    $stored_outage_ids = lousy_outages_current_state_incident_ids((array)($stored_state['outages'] ?? []));
    $stored_count = (int)($stored_state['meta']['active_outage_count'] ?? count((array)($stored_state['outages'] ?? [])));
    $derived_count = count((array)($derived_state['outages'] ?? []));
    $state = $stored_state;
    if (!$state || $stored_count !== $derived_count || $stored_outage_ids !== $derived_outage_ids) {
        $state = $derived_state;
    }
    foreach (['outages','signals','unverified','operational'] as $lane) { $base[$lane] = array_values(array_filter((array)($state[$lane] ?? []), 'is_array')); }
    $base['providers'] = array_values(array_filter((array)($snapshot['providers'] ?? []), 'is_array'));
    $base['fetched_at'] = (string)($snapshot['fetched_at'] ?? '');
    $base['source'] = (string)($snapshot['source'] ?? 'snapshot');
    $base['errors'] = array_values((array)($snapshot['errors'] ?? []));
    $base['meta'] = array_merge($base['meta'], is_array($state['meta'] ?? null) ? $state['meta'] : []);
    $affected_provider_ids = array_values(array_unique(array_filter(array_map(static function ($incident): string {
        return is_array($incident) ? sanitize_key((string)($incident['provider_id'] ?? $incident['provider'] ?? '')) : '';
    }, $base['outages']))));
    $base['meta']['active_outage_count'] = count($base['outages']);
    $base['meta']['official_incident_count'] = count($base['outages']);
    $base['meta']['affected_provider_count'] = count($affected_provider_ids);
    $base['meta']['current_official_provider_ids'] = $affected_provider_ids;
    $base['meta']['signal_count'] = count($base['signals']);
    $base['meta']['unverified_count'] = count($base['unverified']);
    $base['meta']['operational_count'] = count($base['operational']);
    return $base;
}
