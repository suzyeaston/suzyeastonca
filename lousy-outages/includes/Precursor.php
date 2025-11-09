<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Precursor {
    private $timeout;
    private $downdetector;

    public function __construct($timeout = 5, ?Downdetector $downdetector = null) {
        $this->timeout      = max(2, (int) $timeout);
        $this->downdetector = $downdetector ?: new Downdetector();
    }

    public function evaluate(array $provider, array $current_state): array {
        $risk     = 0;
        $signals  = array();
        $measures = array('latency_ms' => null, 'baseline_ms' => null, 'http_ok' => null);
        $summary  = 'No early signals';
        $details  = array();
        $probes   = $this->probe_urls_for($provider);

        $lat_ms  = null;
        $http_ok = true;

        if (!empty($probes)) {
            $probe_url = $probes[0];
            $t0        = microtime(true);
            $resp      = wp_remote_request($probe_url, array(
                'method'  => 'HEAD',
                'timeout' => $this->timeout,
                'headers' => array('User-Agent' => $this->user_agent()),
            ));
            $lat_ms = (int) round((microtime(true) - $t0) * 1000);
            $measures['latency_ms'] = $lat_ms;

            if (is_wp_error($resp)) {
                $http_ok = false;
            } else {
                $code = (int) wp_remote_retrieve_response_code($resp);
                if (in_array($code, array(401, 403, 405), true)) {
                    $t0   = microtime(true);
                    $resp = wp_remote_request($probe_url, array(
                        'method'  => 'GET',
                        'timeout' => $this->timeout,
                        'headers' => array('User-Agent' => $this->user_agent()),
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

        $this->enrich_with_downdetector($provider, $signals, $measures, $details, $risk);

        if ($risk > 0) {
            $summary_bits = array();
            if (!empty($details['downdetector']['titles'])) {
                $summary_bits[] = implode(' • ', $details['downdetector']['titles']);
            }
            if (empty($summary_bits)) {
                $summary_bits[] = 'signals detected: ' . implode(', ', $signals);
            }
            $summary = 'Early warning: ' . implode(' • ', array_filter($summary_bits));
        }

        return array(
            'risk'       => min(100, $risk),
            'summary'    => $summary,
            'signals'    => $signals,
            'measures'   => $measures,
            'updated_at' => gmdate('c'),
            'details'    => $details,
        );
    }

    private function enrich_with_downdetector(array $provider, array &$signals, array &$measures, array &$details, int &$risk): void
    {
        if (!$this->downdetector) {
            return;
        }

        $matches = $this->downdetector->matches($provider);
        if (empty($matches)) {
            return;
        }

        $signals[] = 'downdetector_trend';
        $count     = count($matches);
        $latest    = $matches[0];
        $age       = isset($latest['age_minutes']) ? (int) $latest['age_minutes'] : null;

        $measures['downdetector_reports']     = $count;
        $measures['downdetector_age_minutes'] = $age;

        $titles = array();
        foreach (array_slice($matches, 0, 3) as $match) {
            if (!empty($match['title'])) {
                $titles[] = (string) $match['title'];
            }
        }

        $details['downdetector'] = array(
            'matches' => $matches,
            'titles'  => $titles,
        );

        if ($age !== null && $age <= 30) {
            $risk += 55;
        } else {
            $risk += 40;
        }
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
            return array('https://trust.zscaler.com/');
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

    private function user_agent(): string {
        $site = home_url();
        return 'Mozilla/5.0 (compatible; LousyOutagesBot/3.1; +' . $site . ')';
    }
}
