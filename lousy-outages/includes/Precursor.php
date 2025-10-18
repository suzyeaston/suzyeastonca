<?php
declare(strict_types=1);

namespace LousyOutages;

class Precursor {
    private $timeout;

    public function __construct($timeout = 5) {
        $this->timeout = max(2, (int) $timeout);
    }

    public function evaluate(array $provider, array $current_state): array {
        $risk     = 0;
        $signals  = array();
        $measures = array('latency_ms' => null, 'baseline_ms' => null, 'http_ok' => null);
        $summary  = 'No early signals';
        $probes   = $this->probe_urls_for($provider);

        $lat_ms  = null;
        $http_ok = true;

        if (!empty($probes)) {
            $probe_url = $probes[0];
            $t0        = microtime(true);
            $resp      = wp_remote_request($probe_url, array(
                'method'  => 'HEAD',
                'timeout' => $this->timeout,
            ));
            $lat_ms = (int) round((microtime(true) - $t0) * 1000);
            $measures['latency_ms'] = $lat_ms;

            if (is_wp_error($resp)) {
                $http_ok = false;
            } else {
                $code = (int) wp_remote_retrieve_response_code($resp);
                if (405 === $code) {
                    $t0   = microtime(true);
                    $resp = wp_remote_request($probe_url, array(
                        'method'  => 'GET',
                        'timeout' => $this->timeout,
                    ));
                    $lat_ms = (int) round((microtime(true) - $t0) * 1000);
                    $measures['latency_ms'] = $lat_ms;
                    if (is_wp_error($resp)) {
                        $http_ok = false;
                    } else {
                        $code = (int) wp_remote_retrieve_response_code($resp);
                        $http_ok = ($code >= 200 && $code < 400);
                    }
                } else {
                    $http_ok = ($code >= 200 && $code < 400);
                }
            }

            $measures['http_ok'] = $http_ok;

            $baseline = $this->update_and_get_baseline(isset($provider['id']) ? $provider['id'] : '', $lat_ms, $http_ok);
            if (null !== $baseline) {
                $measures['baseline_ms'] = $baseline;
            }

            if ($baseline && $lat_ms > (int) round($baseline * 1.8)) {
                $risk += 40;
                $signals[] = 'latency_spike';
            }
            if (!$http_ok) {
                $risk += 40;
                $signals[] = 'http_errors';
            }
        }

        $status       = strtolower((string) ($current_state['status'] ?? 'unknown'));
        $summary_text = (string) ($current_state['summary'] ?? '');
        $sniff_text   = strtolower($summary_text);
        if ('operational' === $status && (false !== strpos($sniff_text, 'degrad') || false !== strpos($sniff_text, 'partial'))) {
            $risk += 20;
            $signals[] = 'component_degraded';
        }

        if ($risk > 0) {
            $summary = 'Early warning: ' . implode(', ', $signals);
        }

        return array(
            'risk'       => min(100, $risk),
            'summary'    => $summary,
            'signals'    => $signals,
            'measures'   => $measures,
            'updated_at' => gmdate('c'),
        );
    }

    private function probe_urls_for(array $provider): array {
        $settings = get_option('lousy_outages_probes', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        $id = isset($provider['id']) ? (string) $provider['id'] : '';
        if ('' !== $id && isset($settings[$id]) && is_array($settings[$id]) && !empty($settings[$id])) {
            $urls = array();
            foreach ($settings[$id] as $url) {
                if (is_string($url) && '' !== trim($url)) {
                    $urls[] = trim($url);
                }
            }
            if (!empty($urls)) {
                return $urls;
            }
        }
        if ('zscaler' === $id) {
            return array('https://status.zscaler.com/');
        }
        return array();
    }

    private function update_and_get_baseline($provider_id, $latest_ms, $http_ok) {
        $provider_id = (string) $provider_id;
        if ('' === $provider_id) {
            return is_int($latest_ms) ? $latest_ms : null;
        }

        if (null === $latest_ms) {
            return null;
        }

        $latest_ms = (int) $latest_ms;
        if ($latest_ms <= 0) {
            $stored = get_option('lousy_outages_precursor_baselines', array());
            if (!is_array($stored) || !isset($stored[$provider_id]['avg'])) {
                return null;
            }
            return (int) round((float) $stored[$provider_id]['avg']);
        }

        $key = 'lousy_outages_precursor_baselines';
        $all = get_option($key, array());
        if (!is_array($all)) {
            $all = array();
        }
        $entry = isset($all[$provider_id]) && is_array($all[$provider_id]) ? $all[$provider_id] : array('avg' => null, 'n' => 0);

        if (!$http_ok && null !== $entry['avg']) {
            return (int) round((float) $entry['avg']);
        }

        if (null === $entry['avg'] || !$entry['n']) {
            $entry['avg'] = (float) $latest_ms;
            $entry['n']   = 1;
        } else {
            $alpha        = 0.2;
            $entry['avg'] = (1.0 - $alpha) * (float) $entry['avg'] + $alpha * (float) $latest_ms;
            $entry['n']   = (int) $entry['n'] + 1;
        }

        $all[$provider_id] = $entry;
        update_option($key, $all, false);

        return (int) round((float) $entry['avg']);
    }
}
