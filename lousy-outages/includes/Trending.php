<?php
declare(strict_types=1);

namespace LousyOutages;

/**
 * Lightweight trending detector that looks for correlated outage signals.
 */
class Trending
{
    private const ERROR_HISTORY_OPTION = 'lousy_outages_error_history';
    private const ERROR_WINDOW         = 600; // 10 minutes

    public function evaluate(array $providers): array
    {
        $timestamp      = time();
        $history        = $this->update_error_history($providers, $timestamp);
        $statuspageHits = [];
        $cloudHits      = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $slug = strtolower((string) ($provider['provider'] ?? $provider['id'] ?? ''));
            $kind = strtolower((string) ($provider['kind'] ?? ''));
            $status = strtolower((string) ($provider['status'] ?? ''));
            $indicator = strtolower((string) ($provider['indicator'] ?? ''));

            if ('statuspage' === $kind) {
                if (('' !== $indicator && 'none' !== $indicator) || in_array($status, ['degraded', 'major'], true)) {
                    $statuspageHits[] = $slug ?: 'statuspage';
                }
            }

            if (in_array($slug, ['aws', 'azure', 'gcp'], true)) {
                $hasIncident = !empty($provider['incidents']);
                if ($hasIncident || in_array($status, ['degraded', 'major'], true)) {
                    $cloudHits[] = $slug;
                }
            }
        }

        $errorProviders = $this->recent_error_providers($history, $timestamp);

        $active  = false;
        $signals = [];

        if ($statuspageHits && ($cloudHits || count($errorProviders) >= 3)) {
            $active = true;

            if ($statuspageHits) {
                $signals[] = 'Statuspage alerts: ' . $this->format_list($statuspageHits);
            }
            if ($cloudHits) {
                $signals[] = 'Cloud incidents: ' . $this->format_list($cloudHits);
            }
            if (count($errorProviders) >= 3) {
                $signals[] = 'HTTP errors from ' . count($errorProviders) . ' providers';
            }
        }

        return [
            'trending'     => $active,
            'signals'      => array_slice($signals, 0, 6),
            'generated_at' => gmdate('c', $timestamp),
        ];
    }

    private function update_error_history(array $providers, int $timestamp): array
    {
        $history = get_option(self::ERROR_HISTORY_OPTION, []);
        if (!is_array($history)) {
            $history = [];
        }

        $indexed = [];
        foreach ($history as $entry) {
            if (!is_array($entry) || empty($entry['slug']) || empty($entry['ts'])) {
                continue;
            }
            $slug = strtolower((string) $entry['slug']);
            $ts   = (int) $entry['ts'];
            if (($timestamp - $ts) <= self::ERROR_WINDOW) {
                $indexed[$slug] = $ts;
            }
        }

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $code = (int) ($provider['http_code'] ?? 0);
            if ($code < 400 || $code >= 600) {
                continue;
            }
            $slug = strtolower((string) ($provider['provider'] ?? $provider['id'] ?? ''));
            if ('' === $slug) {
                continue;
            }
            $indexed[$slug] = $timestamp;
        }

        $history = [];
        foreach ($indexed as $slug => $ts) {
            $history[] = [
                'slug' => $slug,
                'ts'   => $ts,
            ];
        }

        update_option(self::ERROR_HISTORY_OPTION, $history, false);

        return $history;
    }

    private function recent_error_providers(array $history, int $timestamp): array
    {
        $providers = [];
        foreach ($history as $entry) {
            if (!is_array($entry) || empty($entry['slug']) || empty($entry['ts'])) {
                continue;
            }
            $ts = (int) $entry['ts'];
            if (($timestamp - $ts) <= self::ERROR_WINDOW) {
                $providers[] = strtolower((string) $entry['slug']);
            }
        }

        return array_values(array_unique($providers));
    }

    private function format_list(array $slugs): string
    {
        $unique = array_values(array_unique($slugs));
        $labels = array_map(
            static function ($slug) {
                $slug = (string) $slug;
                if ('' === $slug) {
                    return '';
                }
                $special = [
                    'aws'   => 'AWS',
                    'gcp'   => 'GCP',
                    'gcp-json' => 'GCP',
                    'azure' => 'Azure',
                ];
                if (isset($special[$slug])) {
                    return $special[$slug];
                }
                return ucwords(str_replace(['-', '_'], ' ', $slug));
            },
            $unique
        );

        $labels = array_filter($labels, static function ($label) {
            return '' !== $label;
        });

        return implode(', ', $labels);
    }
}
