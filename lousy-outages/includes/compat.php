<?php
// Legacy incident shim so older integrations keep working alongside the namespaced classes.
if ( ! class_exists('LO_Incident') ) {
    if ( ! class_exists('SuzyEaston\\LousyOutages\\Model\\Incident') ) {
        require_once __DIR__ . '/Model/Incident.php';
    }

    if ( ! class_exists('LousyOutages\\Model\\Incident') && class_exists('SuzyEaston\\LousyOutages\\Model\\Incident') ) {
        class_alias('SuzyEaston\\LousyOutages\\Model\\Incident', 'LousyOutages\\Model\\Incident');
    }

    class LO_Incident extends \SuzyEaston\LousyOutages\Model\Incident {
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
                'All systems operational.',
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
if ( ! class_exists('SuzyEaston\\LousyOutages\\Model\\LegacyIncident') ) {
    class_alias('LO_Incident', 'SuzyEaston\\LousyOutages\\Model\\LegacyIncident');
}

if ( ! class_exists('LousyOutages\\Model\\LegacyIncident') ) {
    class_alias('LO_Incident', 'LousyOutages\\Model\\LegacyIncident');
}

if ( ! class_exists('LousyOutages\\Fetcher') && class_exists('SuzyEaston\\LousyOutages\\Fetcher') ) {
    // LO: keep legacy namespace compatibility for tests and old hooks.
    class_alias('SuzyEaston\\LousyOutages\\Fetcher', 'LousyOutages\\Fetcher');
}

if ( ! class_exists('LousyOutages\\Summary') && class_exists('SuzyEaston\\LousyOutages\\Summary') ) {
    // LO: surface incident summary data to legacy templates.
    class_alias('SuzyEaston\\LousyOutages\\Summary', 'LousyOutages\\Summary');
}

if ( ! class_exists('LousyOutages\\I18n') && class_exists('SuzyEaston\\LousyOutages\\I18n') ) {
    // LO: allow legacy front-end strings lookup.
    class_alias('SuzyEaston\\LousyOutages\\I18n', 'LousyOutages\\I18n');
}
