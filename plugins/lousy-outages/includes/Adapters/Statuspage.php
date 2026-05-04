<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Adapters\Statuspage;

/**
 * Attempt to infer a service state from a Statuspage error payload or body.
 */
function detect_state_from_error(string $body): ?string {
    $body = trim($body);
    if ('' === $body) {
        return null;
    }

    $mapping = [
        'none'                => 'operational',
        'minor'               => 'degraded',
        'minor_outage'        => 'degraded',
        'degraded_performance'=> 'degraded',
        'partial'             => 'degraded',
        'partial_outage'      => 'degraded',
        'investigating'       => 'degraded',
        'identified'          => 'degraded',
        'monitoring'          => 'degraded',
        'major'               => 'outage',
        'major_outage'        => 'outage',
        'critical'            => 'outage',
        'maintenance'         => 'maintenance',
    ];

    $state = null;
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $indicator = strtolower((string) ($decoded['status']['indicator'] ?? $decoded['status'] ?? ''));
        if ($indicator && isset($mapping[$indicator])) {
            $state = $mapping[$indicator];
        }

        if (! $state && isset($decoded['page']['status_indicator'])) {
            $hint = strtolower((string) $decoded['page']['status_indicator']);
            if (isset($mapping[$hint])) {
                $state = $mapping[$hint];
            }
        }
    }

    if (! $state) {
        $haystack = strtolower($body);
        foreach (['major_outage', 'partial_outage', 'investigating', 'identified', 'monitoring'] as $needle) {
            if (false !== strpos($haystack, $needle)) {
                $state = $mapping[$needle] ?? 'degraded';
                break;
            }
        }
        if (! $state && false !== strpos($haystack, 'critical')) {
            $state = 'outage';
        }
    }

    if (! $state || 'operational' === $state) {
        return null;
    }

    return $state;
}
