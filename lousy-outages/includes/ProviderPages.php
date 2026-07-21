<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class ProviderPages {
    public static function bootstrap(): void {
        add_action('init', [self::class, 'rewrite']);
        add_filter('query_vars', static function(array $vars): array { $vars[] = 'lo_provider'; return $vars; });
        add_action('template_redirect', [self::class, 'render']);
    }

    public static function rewrite(): void {
        add_rewrite_rule('^lousy-outages/provider/([^/]+)/?$', 'index.php?lo_provider=$matches[1]', 'top');
    }

    public static function provider_state(string $provider_id): array {
        $provider_id = sanitize_key($provider_id);
        $state = function_exists('lousy_outages_get_current_state') ? \lousy_outages_get_current_state() : [];
        foreach ((array)($state['providers'] ?? []) as $provider) {
            if (is_array($provider) && sanitize_key((string)($provider['provider_id'] ?? $provider['id'] ?? $provider['provider'] ?? '')) === $provider_id) {
                return $provider;
            }
        }
        return [];
    }

    public static function active_incident_ids(string $provider_id): array {
        $tile = self::provider_state($provider_id);
        return array_values(array_filter(array_map(static fn($i) => is_array($i) ? (string)($i['id'] ?? '') : '', (array)($tile['incidents'] ?? []))));
    }

    public static function render(): void {
        $provider_id = sanitize_key((string)get_query_var('lo_provider'));
        if ('' === $provider_id) { return; }
        $registry = ProviderRegistry::all();
        $meta = null;
        foreach ($registry as $provider) { if (($provider['id'] ?? '') === $provider_id && !empty($provider['enabled'])) { $meta = $provider; break; } }
        $tile = self::provider_state($provider_id);
        if (!$meta || !$tile) { status_header(404); nocache_headers(); echo '<!doctype html><meta charset="utf-8"><title>Provider not monitored</title><main><h1>Provider not monitored</h1><p>This provider is not currently supported by Lousy Outages.</p></main>'; exit; }
        $incidents = array_values(array_filter((array)($tile['incidents'] ?? []), 'is_array'));
        $history = [];
        if (class_exists('SuzyEaston\\LousyOutages\\Storage\\HistoryStore')) {
            $store = new \SuzyEaston\LousyOutages\Storage\HistoryStore();
            if (method_exists($store, 'query')) { $history = (array)$store->query(['provider'=>$provider_id, 'days'=>30, 'limit'=>25]); }
        }
        get_header();
        echo '<main class="lousy-outages lousy-outages-provider"><p><a href="'.esc_url(home_url('/lousy-outages/')).'">← Lousy Outages</a></p>';
        echo '<h1>'.esc_html((string)$meta['name']).' status</h1>';
        echo '<p><strong>Current state:</strong> '.esc_html((string)($tile['status_label'] ?? $tile['status'] ?? 'Unknown')).'</p>';
        echo '<p><strong>Last successful check:</strong> '.esc_html((string)($tile['checked_at'] ?? $tile['updated_at'] ?? 'Unknown')).'</p>';
        echo '<p><a href="'.esc_url((string)$meta['status_url']).'" rel="noopener" target="_blank">Official status source</a></p>';
        echo '<section><h2>Active incidents</h2>';
        if (!$incidents) { echo '<p>No active official incidents are stored for this provider.</p>'; }
        foreach ($incidents as $incident) { echo '<article><h3>'.esc_html((string)($incident['display_title'] ?? $incident['title'] ?? 'Incident')).'</h3><p>'.esc_html((string)($incident['lifecycle_state'] ?? $incident['status'] ?? '')).'</p><p>'.esc_html((string)($incident['summary'] ?? '')).'</p>'; if (!empty($incident['url'])) { echo '<p><a href="'.esc_url((string)$incident['url']).'">Official incident link</a></p>'; } echo '</article>'; }
        echo '</section><section><h2>30-day history</h2>';
        if (!$history) { echo '<p>No retained 30-day history records are available yet.</p>'; }
        foreach ($history as $row) { if (!is_array($row)) { continue; } echo '<article><h3>'.esc_html((string)($row['title'] ?? $row['incident_title'] ?? 'Incident')).'</h3><p>'.esc_html((string)($row['status'] ?? $row['lifecycle_state'] ?? '')).'</p></article>'; }
        echo '</section><section><h2>Alerts</h2><p>Public dashboard, provider pages, basic history and RSS remain free. Email alert preferences are stored separately from entitlement and billing state so a future personal/supporter tier can be added safely.</p></section>';
        echo '<section><h2>Data source</h2><p>'.esc_html((string)$meta['source_notes']).' Lousy Outages labels official incidents separately from community reports and early signals.</p></section></main>';
        get_footer(); exit;
    }
}
