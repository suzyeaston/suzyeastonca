<?php
// Legacy incident shim so older integrations keep working alongside the namespaced classes.
if ( ! class_exists('LO_Incident') ) {
    if ( ! class_exists('LousyOutages\\Model\\Incident') ) {
        require_once __DIR__ . '/Model/Incident.php';
    }

    class LO_Incident extends \LousyOutages\Model\Incident {
        public function __construct($provider, $id, $title, $status, $url, $component = null, $impact = null, $detected_at = null, $resolved_at = null) {
            $detected = (null === $detected_at || '' === $detected_at) ? time() : (int) $detected_at;
            $resolved = (null === $resolved_at || '' === $resolved_at) ? null : (int) $resolved_at;

            parent::__construct(
                (string) $provider,
                (string) $id,
                (string) $title,
                (string) $status,
                (string) $url,
                null !== $component ? (string) $component : null,
                null !== $impact ? (string) $impact : null,
                $detected,
                $resolved
            );
        }

        public static function create($provider, $id, $title, $status, $url, $component = null, $impact = null, $detected_at = null, $resolved_at = null) {
            return new self($provider, $id, $title, $status, $url, $component, $impact, $detected_at, $resolved_at);
        }

        public static function operational($provider, $url) {
            $now = time();
            return new self(
                $provider,
                self::make_id($provider, 'operational', $now),
                'All systems operational',
                'operational',
                $url,
                null,
                null,
                $now,
                null
            );
        }

        public static function make_id($provider, $title, $timestamp) {
            $base = strtolower((string) $provider . '-' . (string) $title);
            $base = preg_replace('/[^a-z0-9]+/', '-', $base);
            $base = trim((string) $base, '-');
            if ('' === $base) {
                $base = 'incident';
            }

            return $base . '-' . (int) $timestamp;
        }
    }
}

// Allow legacy code referencing the old namespaced alias to continue working if needed.
if ( ! class_exists('LousyOutages\\Model\\LegacyIncident') ) {
    class_alias('LO_Incident', 'LousyOutages\\Model\\LegacyIncident');
}
