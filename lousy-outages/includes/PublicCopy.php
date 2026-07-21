<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

final class PublicCopy {
    private const REVIEW_DATE = '2026-10-01';

    private const LINES = [
        'clear' => [
            'All quiet. Suspicious, but fine.',
            'Checking status pages from Waterfront to the cloud.',
        ],
        'delayed' => [
            'Provider verification is taking the scenic route down Kingsway.',
            'Vancouver rain on the status feed. Retrying shortly.',
        ],
        'minor' => [
            'A third-period wobble, but for APIs.',
            'Traffic is moving like Lions Gate at 5:07.',
        ],
        'alerts' => [
            'Get the alert before your group chat becomes an incident-response channel.',
        ],
    ];

    private const SERIOUS_TERMS = [
        'abuse', 'attack', 'breach', 'casualty', 'conflict', 'death', 'earthquake', 'explosion',
        'fire', 'flood', 'harm', 'hurricane', 'injury', 'malware', 'security', 'war', 'wildfire',
    ];

    public static function review_date(): string {
        return self::REVIEW_DATE;
    }

    public static function line(string $state, array $context = []): string {
        if (self::should_suppress($state, $context)) {
            return '';
        }
        $key = isset(self::LINES[$state]) ? $state : 'clear';
        $lines = self::LINES[$key];
        $seed = gmdate('Y-m-d') . '|' . $key . '|' . strtolower((string)($context['provider'] ?? ''));
        $index = abs((int) crc32($seed)) % max(1, count($lines));
        return $lines[$index] ?? '';
    }

    public static function should_suppress(string $state, array $context = []): bool {
        $severity = strtolower((string)($context['severity'] ?? $context['impact'] ?? ''));
        if (in_array($severity, ['major', 'critical', 'major_outage', 'critical_outage'], true)) {
            return true;
        }
        $text = strtolower(implode(' ', array_map('strval', $context)));
        foreach (self::SERIOUS_TERMS as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }
        return false;
    }
}
