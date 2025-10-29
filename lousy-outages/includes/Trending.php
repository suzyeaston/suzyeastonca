<?php
declare(strict_types=1);

namespace LousyOutages;

class Trending
{
    private const CLOUD_SLUGS = ['aws', 'azure', 'gcp'];
    private const MAJOR_STATES = ['major', 'outage'];
    private const DEGRADED_STATES = ['degraded', 'major', 'outage'];

    public function evaluate(array $providers): array
    {
        $timestamp = time();
        $degraded = [];
        $major = [];
        $cloudRecent = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $slug = strtolower((string) ($provider['provider'] ?? $provider['id'] ?? ''));
            if ('' === $slug) {
                continue;
            }

            $status = strtolower((string) ($provider['status'] ?? $provider['overall'] ?? $provider['stateCode'] ?? 'unknown'));

            if (in_array($status, self::DEGRADED_STATES, true)) {
                $degraded[] = $slug;
            }

            if (in_array($status, self::MAJOR_STATES, true)) {
                $major[] = $slug;
            }

            if (in_array($slug, self::CLOUD_SLUGS, true) && $this->has_recent_cloud_incident($provider, $timestamp)) {
                $cloudRecent[] = $slug;
            }
        }

        $degraded = array_values(array_unique($degraded));
        $major = array_values(array_unique($major));
        $cloudRecent = array_values(array_unique($cloudRecent));

        $active = false;
        $signals = [];

        if (count($degraded) >= 2) {
            $active = true;
            $signals[] = 'Multiple providers impacted: ' . $this->format_list($degraded);
        }

        if ($major && $cloudRecent) {
            if (!$active) {
                $active = true;
                $signals[] = 'Major outages: ' . $this->format_list($major);
                $signals[] = 'Cloud incidents (last 6h): ' . $this->format_list($cloudRecent);
            } else {
                $signals[] = 'Major outages: ' . $this->format_list($major);
                $signals[] = 'Cloud incidents (last 6h): ' . $this->format_list($cloudRecent);
            }
        } elseif ($active && $major) {
            $signals[] = 'Major outages: ' . $this->format_list($major);
        }

        return [
            'trending'     => $active,
            'signals'      => array_slice(array_filter($signals), 0, 6),
            'generated_at' => gmdate('c', $timestamp),
        ];
    }

    private function has_recent_cloud_incident(array $provider, int $now): bool
    {
        if (empty($provider['incidents']) || !is_array($provider['incidents'])) {
            return false;
        }

        $window = $this->cloud_window_seconds();

        foreach ($provider['incidents'] as $incident) {
            if (!is_array($incident)) {
                continue;
            }

            $timestamps = [];
            foreach (['updated_at', 'updatedAt', 'started_at', 'startedAt'] as $field) {
                if (empty($incident[$field])) {
                    continue;
                }
                $time = strtotime((string) $incident[$field]);
                if ($time) {
                    $timestamps[] = $time;
                }
            }

            if (isset($incident['timestamp'])) {
                $raw = (int) $incident['timestamp'];
                if ($raw > 0) {
                    $timestamps[] = $raw;
                }
            }

            foreach ($timestamps as $time) {
                if (($now - $time) <= $window) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cloud_window_seconds(): int
    {
        $base = defined('HOUR_IN_SECONDS') ? (int) HOUR_IN_SECONDS : 3600;
        return $base * 6;
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
                    'azure' => 'Azure',
                    'gcp'   => 'GCP',
                    'gcp-json' => 'GCP',
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

