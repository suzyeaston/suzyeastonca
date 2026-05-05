<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

class ChatterRejectionReasons {
    public static function definitions(): array {
        return [
            'resolved_or_historical' => [
                'code' => 'resolved_or_historical',
                'short_label' => 'Historical or resolved',
                'public_description' => 'Old incident, status-page history, postmortem, or discussion about past availability. Not current smoke.',
                'priority' => 90,
            ],
            'quote_missing_provider_or_failure' => [
                'code' => 'quote_missing_provider_or_failure',
                'short_label' => 'Not actionable',
                'public_description' => 'The quote does not contain both a tracked provider and a concrete failure symptom close together.',
                'priority' => 80,
            ],
            'quote_missing_provider' => [
                'code' => 'quote_missing_provider',
                'short_label' => 'Missing provider',
                'public_description' => 'The quote has failure language but no tracked provider.',
                'priority' => 70,
            ],
            'quote_missing_failure' => [
                'code' => 'quote_missing_failure',
                'short_label' => 'Missing failure symptom',
                'public_description' => 'The quote names a provider but does not describe a concrete service problem.',
                'priority' => 70,
            ],
            'negated_issue' => [
                'code' => 'negated_issue',
                'short_label' => 'Negated report',
                'public_description' => 'The quote says there is not an issue, e.g. “not down,” “no outage,” or “haven’t seen any outage.”',
                'priority' => 95,
            ],
            'generic_noise' => [
                'code' => 'generic_noise',
                'short_label' => 'Generic noise',
                'public_description' => 'Business, pricing, job, AI, or general tech chatter without current service impact.',
                'priority' => 60,
            ],
        ];
    }

    public static function get(string $code): array {
        $all = self::definitions();
        return $all[$code] ?? [
            'code' => $code,
            'short_label' => ucwords(str_replace('_', ' ', $code)),
            'public_description' => 'Filtered chatter that did not meet current-signal quality checks.',
            'priority' => 10,
        ];
    }

    public static function summarize_counts(array $counts): array {
        $rows = [];
        foreach ($counts as $code => $count) {
            if (!is_string($code) || $code === '' || (int) $count <= 0) {
                continue;
            }
            $meta = self::get($code);
            $rows[] = [
                'code' => $meta['code'],
                'count' => (int) $count,
                'label' => $meta['short_label'],
                'description' => $meta['public_description'],
                'priority' => (int) ($meta['priority'] ?? 0),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => ((int) $b['count'] <=> (int) $a['count']) ?: ((int) $b['priority'] <=> (int) $a['priority']));
        return $rows;
    }
}
