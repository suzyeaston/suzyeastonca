<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $autoload = null) { return true; } }
require_once __DIR__ . '/../lousy-outages/includes/ProviderRegistry.php';
require_once __DIR__ . '/../lousy-outages/includes/Providers.php';
require_once __DIR__ . '/../lousy-outages/includes/Adapters.php';

use SuzyEaston\LousyOutages\ProviderRegistry;
use SuzyEaston\LousyOutages\Providers;
use function SuzyEaston\LousyOutages\Adapters\from_statuspage_summary;

$tests = [];

$tests['registry_compatibility_keeps_enabled_ids'] = static function (): void {
    $enabled = Providers::enabled();
    foreach (['openai','github','cloudflare','anthropic','elevenlabs'] as $id) {
        if (!isset($enabled[$id])) {
            throw new RuntimeException("Expected enabled provider {$id}");
        }
        if (($enabled[$id]['id'] ?? '') !== $id || empty($enabled[$id]['status_url'])) {
            throw new RuntimeException("Provider {$id} is missing compatibility fields");
        }
    }
};

$tests['candidate_sources_not_enabled'] = static function (): void {
    $enabled = Providers::enabled();
    foreach (['cursor','perplexity','adobe','runway','stability_ai','google_gemini'] as $id) {
        if (isset($enabled[$id])) {
            throw new RuntimeException("Candidate {$id} must not be enabled until verified");
        }
    }
};

$tests['statuspage_components_and_timeline_preserved'] = static function (): void {
    $payload = json_encode([
        'page' => ['updated_at' => '2026-07-21T00:00:00Z'],
        'status' => ['indicator' => 'minor'],
        'incidents' => [[
            'id' => 'inc-1', 'name' => 'API degraded', 'status' => 'identified', 'impact' => 'minor',
            'started_at' => '2026-07-21T00:00:00Z', 'updated_at' => '2026-07-21T00:05:00Z',
            'components' => [['id' => 'cmp-1', 'name' => 'API', 'status' => 'degraded_performance']],
            'incident_updates' => [['id' => 'upd-1', 'status' => 'identified', 'body' => 'We found the issue.', 'created_at' => '2026-07-21T00:04:00Z']],
        ]],
    ]);
    $normalized = from_statuspage_summary((string)$payload);
    $incident = $normalized['incidents'][0] ?? [];
    if (($incident['components'][0]['name'] ?? '') !== 'API') { throw new RuntimeException('Component not preserved'); }
    if (($incident['timeline'][0]['status'] ?? '') !== 'identified') { throw new RuntimeException('Timeline not preserved'); }
    if (($incident['source_type'] ?? '') !== 'statuspage') { throw new RuntimeException('Source type not preserved'); }
};

$tests['registry_has_ai_creative_focus_without_candidate_inflation'] = static function (): void {
    $enabled = ProviderRegistry::enabled();
    $ai = array_values(array_filter($enabled, static fn($p) => ($p['category'] ?? '') === 'ai'));
    $creative = array_values(array_filter($enabled, static fn($p) => ($p['category'] ?? '') === 'creative'));
    if (count($ai) < 6 || count($creative) < 1) { throw new RuntimeException('Expected focused AI/creative enabled catalog'); }
};

foreach ($tests as $name => $test) {
    $test();
    echo "ok - {$name}\n";
}
