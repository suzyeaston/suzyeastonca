<?php
declare(strict_types=1);

namespace LousyOutages;

/**
 * Lightweight trending detector that looks for correlated outage signals.
 */
class Trending
{
    private const OPTION_KEY     = 'lousy_outages_trending_buffer';
    private const BUFFER_WINDOW  = 3600; // seconds to retain historical signals (60 minutes)
    private const SIGNAL_WINDOW  = 900;  // seconds for co-occurring signals (15 minutes)
    private const MAX_SIGNAL_LOG = 24;

    /**
     * Evaluate the latest provider states and return a trending summary.
     */
    public function evaluate(array $providers, array $map): array
    {
        $timestamp = time();
        $signals   = $this->collect_signals($providers, $timestamp);

        $buffer   = $this->load_buffer();
        $buffer[] = [
            'ts'      => $timestamp,
            'signals' => $signals,
        ];
        $buffer = $this->prune_buffer($buffer, $timestamp);
        $this->store_buffer($buffer);

        $recentSignals = $this->recent_signals($buffer, $timestamp);
        $unique        = array_values(array_unique($recentSignals));
        $active        = count($unique) >= 2;

        return [
            'trending'     => $active,
            'signals'      => array_slice($unique, 0, self::MAX_SIGNAL_LOG),
            'generated_at' => gmdate('c', $timestamp),
        ];
    }

    /**
     * Convert provider payloads into normalized signal identifiers.
     */
    private function collect_signals(array $providers, int $timestamp): array
    {
        $signals = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $slug = strtolower((string) ($provider['id'] ?? $provider['provider'] ?? ''));
            if ('' === $slug) {
                continue;
            }

            $overall = strtolower((string) ($provider['overall'] ?? $provider['status'] ?? ''));
            if (in_array($overall, ['degraded', 'major', 'outage'], true)) {
                $signals[] = $slug . ':status=' . $overall;
            }

            if (!empty($provider['error'])) {
                $signals[] = $slug . ':fetch_error';
            }

            if (!empty($provider['probe']) && is_array($provider['probe'])) {
                $probe = $provider['probe'];
                $rate  = isset($probe['window_error_rate']) ? (float) $probe['window_error_rate'] : 0.0;
                if ($rate >= 0.3) {
                    $signals[] = $slug . ':probe=' . round($rate * 100) . '%';
                } elseif (!empty($probe['recent_failure'])) {
                    $signals[] = $slug . ':probe';
                }
            }

            $incidents = is_array($provider['incidents'] ?? null) ? $provider['incidents'] : [];
            foreach ($incidents as $incident) {
                if (!is_array($incident)) {
                    continue;
                }
                $reference = $this->latest_timestamp($incident);
                if ($reference && ($timestamp - $reference) <= self::SIGNAL_WINDOW) {
                    switch ($slug) {
                        case 'aws':
                            $signals[] = 'aws:rss+new_item';
                            break;
                        case 'azure':
                            $signals[] = 'azure:rss+new_item';
                            break;
                        case 'gcp':
                            $signals[] = 'gcp:json+new_item';
                            break;
                        default:
                            $signals[] = $slug . ':incident';
                            break;
                    }
                    break;
                }
            }

            if ('cloudflare' === $slug && in_array($overall, ['degraded', 'major', 'outage'], true)) {
                $signals[] = 'cloudflare:indicator=' . $overall;
            }
        }

        return $signals;
    }

    private function latest_timestamp(array $incident): int
    {
        foreach (['updated_at', 'updatedAt', 'started_at', 'startedAt'] as $key) {
            if (empty($incident[$key])) {
                continue;
            }
            $time = strtotime((string) $incident[$key]);
            if ($time) {
                return $time;
            }
        }

        return 0;
    }

    private function recent_signals(array $buffer, int $timestamp): array
    {
        $signals = [];

        foreach ($buffer as $entry) {
            if (!is_array($entry) || empty($entry['signals']) || empty($entry['ts'])) {
                continue;
            }

            $ts = (int) $entry['ts'];
            if (($timestamp - $ts) > self::SIGNAL_WINDOW) {
                continue;
            }

            if (is_array($entry['signals'])) {
                $signals = array_merge($signals, $entry['signals']);
            }
        }

        return $signals;
    }

    private function prune_buffer(array $buffer, int $timestamp): array
    {
        $cutoff = $timestamp - self::BUFFER_WINDOW;

        $buffer = array_filter(
            $buffer,
            static function ($entry) use ($cutoff) {
                if (!is_array($entry) || empty($entry['ts'])) {
                    return false;
                }
                return ((int) $entry['ts']) >= $cutoff;
            }
        );

        if (count($buffer) > self::MAX_SIGNAL_LOG) {
            $buffer = array_slice(array_values($buffer), -1 * self::MAX_SIGNAL_LOG);
        }

        return array_values($buffer);
    }

    private function load_buffer(): array
    {
        $buffer = get_option(self::OPTION_KEY, []);
        return is_array($buffer) ? $buffer : [];
    }

    private function store_buffer(array $buffer): void
    {
        update_option(self::OPTION_KEY, $buffer, false);
    }
}
