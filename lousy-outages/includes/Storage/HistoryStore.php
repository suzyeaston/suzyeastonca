<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Storage;

class HistoryStore
{
    public const OPTION_CANONICAL = 'lo_event_log_v2';
    public const OPTION_BACKUP = 'lo_history_migration_backup_v2';
    public const OPTION_MARKER = 'lo_history_migration_v2_marker';
    public const SOURCE_OPTIONS = [
        'lo_event_log',
        'lo_event_log_compacted_v1',
        'lousy_outages_history',
        'lousy_outages_log',
        'lousy_outages_states',
    ];

    public function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lousy_outages_history_events';
    }

    public function installTable(): void
    {
        global $wpdb;
        $table = $this->tableName();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            guid varchar(191) NOT NULL,
            provider varchar(80) NOT NULL,
            provider_label varchar(160) NOT NULL DEFAULT '',
            title text NOT NULL,
            description longtext NULL,
            status varchar(80) NOT NULL DEFAULT 'incident',
            severity varchar(40) NOT NULL DEFAULT 'degraded',
            source varchar(80) NOT NULL DEFAULT 'provider',
            first_seen bigint(20) unsigned NOT NULL DEFAULT 0,
            last_seen bigint(20) unsigned NOT NULL DEFAULT 0,
            important tinyint(1) NOT NULL DEFAULT 0,
            url text NULL,
            components_json longtext NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY guid (guid),
            KEY provider_last_seen (provider,last_seen),
            KEY important_last_seen (important,last_seen),
            KEY severity_last_seen (severity,last_seen)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function tableExists(): bool
    {
        global $wpdb;
        $table = $this->tableName();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public function loadCanonical(): array
    {
        $stored = get_option(self::OPTION_CANONICAL, []);
        return is_array($stored) ? $stored : [];
    }

    public function addEvent(array $event): array
    {
        $normalized = self::normalizeRichEvent($event);
        if ($this->tableExists()) {
            global $wpdb;
            $wpdb->replace($this->tableName(), [
                'guid'=>$normalized['guid'], 'provider'=>$normalized['provider'], 'provider_label'=>$normalized['provider_label'],
                'title'=>$normalized['title'], 'description'=>$normalized['description'], 'status'=>$normalized['status'], 'severity'=>$normalized['severity'],
                'source'=>$normalized['source'], 'first_seen'=>(int)$normalized['first_seen'], 'last_seen'=>(int)$normalized['last_seen'],
                'important'=>!empty($normalized['important']) ? 1 : 0, 'url'=>$normalized['url'], 'components_json'=>wp_json_encode($normalized['components']),
            ], ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s']);
        }
        return $normalized;
    }

    public function migrationStatus(): array
    {
        $marker = get_option(self::OPTION_MARKER, []);
        return is_array($marker) ? array_diff_key($marker, ['events' => true]) : [];
    }

    public function markValidated(array $events): void
    {
        update_option(self::OPTION_MARKER, [
            'validated' => true,
            'completed_at' => gmdate('c'),
            'backup_option' => self::OPTION_BACKUP,
            'canonical_option' => self::OPTION_CANONICAL,
            'after_dedupe' => count($events),
            'provider_counts' => self::providerCounts($events),
            'oldest_timestamp' => self::oldestTimestamp($events),
            'newest_timestamp' => self::newestTimestamp($events),
        ], false);
    }

    public function migrate(bool $force = false): array
    {
        $marker = get_option(self::OPTION_MARKER, []);
        if (!$force && is_array($marker) && !empty($marker['validated'])) {
            return array_diff_key($marker, ['events' => true]);
        }

        $original = $this->readSourceOptions();
        $backup = [
            'created_at' => gmdate('c'),
            'source_options' => $original,
            'counts' => $this->countSources($original),
            'checksum' => hash('sha256', wp_json_encode($original) ?: serialize($original)),
        ];
        update_option(self::OPTION_BACKUP, $backup, false);
        update_option(self::OPTION_MARKER, ['started_at' => gmdate('c'), 'validated' => false, 'backup_option' => self::OPTION_BACKUP], false);

        $prepared = self::prepareEventsFromOptions($original);
        $merged = self::dedupeEvents($prepared['events']);

        if (count($merged) > count($prepared['events'])) {
            update_option(self::OPTION_MARKER, ['validated' => false, 'error' => 'dedupe_count_invalid', 'backup_option' => self::OPTION_BACKUP], false);
            return ['validated' => false, 'events' => []];
        }

        update_option(self::OPTION_CANONICAL, $merged, false);
        $report = [
            'validated' => true,
            'completed_at' => gmdate('c'),
            'backup_option' => self::OPTION_BACKUP,
            'canonical_option' => self::OPTION_CANONICAL,
            'source_counts' => $backup['counts'],
            'before_dedupe' => count($prepared['events']),
            'after_dedupe' => count($merged),
            'provider_counts' => self::providerCounts($merged),
            'oldest_timestamp' => self::oldestTimestamp($merged),
            'newest_timestamp' => self::newestTimestamp($merged),
        ];
        update_option(self::OPTION_MARKER, $report, false);
        return $report;
    }

    public function queryPage(array $args): array
    {
        if (!$this->tableExists()) {
            return ['events'=>array_slice($this->loadCanonical(), (int)($args['offset'] ?? 0), (int)($args['per_page'] ?? 20)), 'total'=>count($this->loadCanonical())];
        }
        global $wpdb;
        $where = ['1=1']; $values = [];
        if (!empty($args['cutoff'])) { $where[] = 'first_seen >= %d'; $values[] = (int)$args['cutoff']; }
        if (!empty($args['providers'])) { $in = implode(',', array_fill(0, count($args['providers']), '%s')); $where[] = "provider IN ($in)"; foreach ($args['providers'] as $p) { $values[] = $p; } }
        if (!empty($args['important_only'])) { $where[] = 'important = 1'; }
        $sqlWhere = implode(' AND ', $where);
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->tableName()} WHERE {$sqlWhere}", $values));
        $limit = max(1, min(50, (int)($args['per_page'] ?? 20))); $offset = max(0, (int)($args['offset'] ?? 0));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tableName()} WHERE {$sqlWhere} ORDER BY last_seen DESC, id DESC LIMIT %d OFFSET %d", array_merge($values, [$limit, $offset])), ARRAY_A);
        $events = [];
        foreach ((array)$rows as $row) { $row['components'] = json_decode((string)($row['components_json'] ?? '[]'), true) ?: []; $events[] = self::normalizeRichEvent($row); }
        return ['events'=>$events, 'total'=>$total];
    }

    public function exportSourceOptions(): array { return $this->readSourceOptions(); }

    private function readSourceOptions(): array
    {
        $out = [];
        foreach (self::SOURCE_OPTIONS as $option) { $out[$option] = get_option($option, null); }
        return $out;
    }

    private function countSources(array $source): array
    {
        $counts = [];
        foreach (self::SOURCE_OPTIONS as $option) {
            $value = $source[$option] ?? null;
            $counts[$option] = is_array($value) ? count($value) : (null === $value ? 0 : 1);
        }
        return $counts;
    }

    public static function prepareEventsFromOptions(array $source): array
    {
        $events = [];
        foreach (['lo_event_log', self::OPTION_CANONICAL] as $option) {
            $rows = $source[$option] ?? [];
            if (!is_array($rows)) { continue; }
            foreach ($rows as $row) { if (is_array($row)) { $events[] = self::normalizeRichEvent($row); } }
        }
        foreach (['lousy_outages_history', 'lousy_outages_log'] as $option) {
            $rows = $source[$option] ?? [];
            if (!is_array($rows)) { continue; }
            foreach ($rows as $row) { if (is_array($row)) { $events[] = self::normalizeLegacyEvent($row); } }
        }
        $states = $source['lousy_outages_states'] ?? [];
        if (is_array($states)) {
            foreach ($states as $id => $row) {
                if (is_array($row) && !empty($row['status'])) {
                    $row['id'] = $row['id'] ?? $id;
                    $row['time'] = isset($row['updated_at']) ? strtotime((string)$row['updated_at']) : time();
                    $events[] = self::normalizeLegacyEvent($row);
                }
            }
        }
        return ['events' => array_values(array_filter($events))];
    }

    public static function dedupeEvents(array $events): array
    {
        $out = [];
        foreach ($events as $event) {
            if (!is_array($event)) { continue; }
            $event = self::normalizeRichEvent($event);
            $key = self::dedupeKey($event);
            if (isset($out[$key])) {
                $out[$key]['first_seen'] = min((int)$out[$key]['first_seen'], (int)$event['first_seen']);
                $out[$key]['last_seen'] = max((int)$out[$key]['last_seen'], (int)$event['last_seen']);
                $out[$key]['components'] = array_values(array_unique(array_merge((array)($out[$key]['components'] ?? []), (array)($event['components'] ?? []))));
                continue;
            }
            $out[$key] = $event;
        }
        usort($out, static fn($a, $b): int => ((int)($b['last_seen'] ?? 0)) <=> ((int)($a['last_seen'] ?? 0)));
        return array_values($out);
    }

    public static function normalizeRichEvent(array $event): array
    {
        $provider = sanitize_key((string)($event['provider'] ?? $event['id'] ?? 'provider')) ?: 'provider';
        $title = trim((string)($event['title'] ?? $event['summary'] ?? $event['description'] ?? 'Incident'));
        $first = self::timestamp($event['first_seen'] ?? $event['published'] ?? $event['time'] ?? $event['updated_at'] ?? time());
        $last = self::timestamp($event['last_seen'] ?? $event['updated_at'] ?? $event['time'] ?? $first);
        $status = strtolower(trim((string)($event['status_normal'] ?? $event['status'] ?? 'incident'))) ?: 'incident';
        return [
            'provider' => $provider, 'provider_label' => (string)($event['provider_label'] ?? $event['provider_name'] ?? self::slugLabel($provider)),
            'guid' => (string)($event['guid'] ?? $event['id'] ?? sha1($provider.'|'.$title.'|'.$first)), 'title' => $title,
            'description' => (string)($event['description'] ?? $title), 'status' => $status, 'severity' => strtolower((string)($event['severity'] ?? self::severityForStatus($status))),
            'source' => (string)($event['source'] ?? 'provider'), 'url' => (string)($event['url'] ?? ''), 'first_seen' => $first, 'last_seen' => $last ?: $first,
            'published' => (string)($event['published'] ?? gmdate('Y-m-d H:i:s T', $first)), 'important' => isset($event['important']) ? (bool)$event['important'] : !in_array(self::severityForStatus($status), ['info','maintenance'], true),
            'components' => array_values((array)($event['components'] ?? [])),
        ];
    }

    public static function normalizeLegacyEvent(array $entry): array
    {
        $provider = sanitize_key((string)($entry['id'] ?? $entry['provider'] ?? 'provider')) ?: 'provider';
        $status = strtolower(trim((string)($entry['status'] ?? 'unknown'))) ?: 'unknown';
        $time = self::timestamp($entry['time'] ?? $entry['updated_at'] ?? time());
        return self::normalizeRichEvent(['provider'=>$provider,'provider_label'=>$entry['provider_label'] ?? self::slugLabel($provider),'guid'=>'legacy|'.$provider.'|'.$status.'|'.$time,'title'=>'Status reported: '.ucfirst(str_replace('_',' ',$status)),'description'=>'Legacy status transition imported from pre-v2 history.','status'=>$status,'severity'=>self::severityForStatus($status),'source'=>'legacy_status','first_seen'=>$time,'last_seen'=>$time,'published'=>gmdate('Y-m-d H:i:s T',$time),'important'=>!in_array(self::severityForStatus($status), ['info','maintenance'], true)]);
    }

    private static function dedupeKey(array $event): string
    {
        $guid = trim((string)($event['guid'] ?? ''));
        if ($guid !== '' && 0 !== strpos($guid, 'legacy|')) { return sanitize_key((string)$event['provider']).'|guid|'.strtolower($guid); }
        $bucket = (int) floor(((int)($event['first_seen'] ?? $event['last_seen'] ?? 0)) / (6 * HOUR_IN_SECONDS));
        $title = strtolower(preg_replace('/\s+/', ' ', trim((string)($event['title'] ?? ''))) ?? '');
        return sanitize_key((string)$event['provider']).'|'.$title.'|'.strtolower((string)($event['status'] ?? '')).'|'.$bucket;
    }

    private static function timestamp($value): int { if (is_numeric($value)) { return (int)$value; } $ts = strtotime((string)$value); return $ts ?: time(); }
    private static function severityForStatus(string $status): string { return in_array($status, ['major','critical','outage','major_outage'], true) ? 'outage' : (in_array($status, ['ok','none','operational','resolved'], true) ? 'info' : (false !== strpos($status, 'maintenance') ? 'maintenance' : 'degraded')); }
    private static function slugLabel(string $slug): string { return ucwords(str_replace(['_','-'], ' ', $slug)); }
    public static function providerCounts(array $events): array { $c=[]; foreach($events as $e){$p=(string)($e['provider']??'provider');$c[$p]=($c[$p]??0)+1;} ksort($c); return $c; }
    public static function oldestTimestamp(array $events): ?int { $t=[]; foreach($events as $e){if(!empty($e['first_seen'])){$t[]=(int)$e['first_seen'];}} return $t ? min($t) : null; }
    public static function newestTimestamp(array $events): ?int { $t=[]; foreach($events as $e){if(!empty($e['last_seen'])){$t[]=(int)$e['last_seen'];}} return $t ? max($t) : null; }
}
