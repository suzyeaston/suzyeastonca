<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class SignalEngine {
    public static function classify_score(int $count, int $uniqueReporterCount = 0): string {
        $watch = (int) apply_filters('lo_signal_watch_threshold', 2);
        $trending = (int) apply_filters('lo_signal_trending_threshold', 3);
        $hot = (int) apply_filters('lo_signal_hot_threshold', 5);
        if ($count >= $hot) return 'hot';
        if ($count >= $trending) return 'trending';
        if ($count >= $watch) return 'watch';
        return 'quiet';
    }

    public static function score_provider(array $reports, array $options = []): array {
        $count = count($reports);
        $symptoms = [];
        $unique = [];
        $last = '';
        foreach ($reports as $r) {
            $sym = (string)($r['symptom'] ?? 'other');
            $symptoms[$sym] = ($symptoms[$sym] ?? 0) + 1;
            $ip = (string)($r['ip_hash'] ?? '');
            if ($ip) $unique[$ip] = true;
            $created = (string)($r['created_at'] ?? '');
            if ($created > $last) $last = $created;
        }
        arsort($symptoms);
        $top = (string)array_key_first($symptoms);
        $class = self::classify_score($count, count($unique));
        $message = self::message_for_class($class);
        return ['report_count'=>$count,'unique_reporter_count'=>count($unique),'top_symptom'=>$top ?: 'other','classification'=>$class,'message'=>$message,'last_reported_at'=>$last];
    }

    public static function message_for_class(string $class): string {
        if ($class === 'watch') return 'A few community reports are coming in. Possible issue reported by users. Unconfirmed signal — we’re watching this.';
        if ($class === 'trending') return 'Community reports are trending for this provider. Unconfirmed signal; no official incident yet.';
        if ($class === 'hot') return 'High volume of community reports. No official confirmation yet unless listed below. Unconfirmed signal.';
        return 'No unusual community reports.';
    }

    public static function summarize_recent_signals(int $windowMinutes = 60): array {
        $windowMinutes = (int) apply_filters('lo_signal_window_minutes', $windowMinutes);
        $reports = UserReports::get_recent_reports(['windowMinutes'=>$windowMinutes,'limit'=>500]);
        $providers = Providers::list();
        $grouped = [];
        foreach ($reports as $r) { $grouped[(string)$r['provider_id']][] = $r; }
        $signals = [];
        foreach ($grouped as $provider_id => $rows) {
            $score = self::score_provider($rows);
            $signals[] = array_merge($score, [
                'provider_id'=>$provider_id,
                'provider_name'=>(string)($providers[$provider_id]['name'] ?? ucfirst($provider_id)),
                'severity'=>(string)($rows[0]['severity'] ?? 'unknown'),
                'region'=>(string)($rows[0]['region'] ?? ''),
                'official_status_known'=>false,
            ]);
        }
        usort($signals, static fn($a,$b)=>($b['report_count']<=>$a['report_count']));
        return $signals;
    }
}
