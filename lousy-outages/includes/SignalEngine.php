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

    public static function summarize_fused_signals(int $windowMinutes = 60): array {
        $community = self::summarize_recent_signals($windowMinutes);
        $external = class_exists('\SuzyEaston\LousyOutages\ExternalSignals') ? ExternalSignals::get_recent_signals(['windowMinutes'=>$windowMinutes,'limit'=>200]) : [];
        $buckets = [];
        foreach ($community as $row) {
            $key = ($row['provider_id'] ?? '') . '|' . ($row['category'] ?? '') . '|' . ($row['region'] ?? '');
            $buckets[$key] = ['provider_id'=>(string)$row['provider_id'],'provider_name'=>(string)$row['provider_name'],'category'=>(string)($row['category']??'community'),'region'=>(string)($row['region']??''),'report_count'=>(int)$row['report_count'],'external_signal_count'=>0,'synthetic_failure_count'=>0,'sources'=>['community_reports'],'official_status_known'=>false,'confirmed'=>false,'last_observed_at'=>(string)($row['last_reported_at']??''),'base_confidence'=> self::class_confidence((string)$row['classification']) ];
        }
        foreach ($external as $row) {
            $key = ($row['provider_id'] ?? '') . '|' . ($row['category'] ?? '') . '|' . ($row['region'] ?? '');
            if (!isset($buckets[$key])) { $buckets[$key]=['provider_id'=>(string)($row['provider_id']??''),'provider_name'=>(string)($row['provider_name']??'Unknown'),'category'=>(string)($row['category']??''),'region'=>(string)($row['region']??''),'report_count'=>0,'external_signal_count'=>0,'synthetic_failure_count'=>0,'sources'=>[],'official_status_known'=>false,'confirmed'=>false,'last_observed_at'=>(string)($row['observed_at']??''),'base_confidence'=>0]; }
            $buckets[$key]['external_signal_count']++;
            if (($row['signal_type'] ?? '') === 'public_chatter') { $buckets[$key]['public_chatter_confidence'] = max((int)($buckets[$key]['public_chatter_confidence'] ?? 0),(int)($row['confidence'] ?? 0)); }
            $src=(string)($row['source']??'external'); if(!in_array($src,$buckets[$key]['sources'],true)) $buckets[$key]['sources'][]=$src;
            if (($row['source']??'')==='synthetic_canary') $buckets[$key]['synthetic_failure_count']++;
            $buckets[$key]['base_confidence'] = max((int)$buckets[$key]['base_confidence'], (int)($row['confidence'] ?? 0));
            if ((string)($row['observed_at']??'') > (string)$buckets[$key]['last_observed_at']) $buckets[$key]['last_observed_at']=(string)$row['observed_at'];
        }
        $signals=[];
        foreach($buckets as $b){ $conf=(int)$b['base_confidence']; $chatter=(int)($b['public_chatter_confidence'] ?? 0); if($chatter>0){ $conf=max($conf,$chatter); } if($chatter>0 && $b['report_count']>0){ $conf += 10; } if($chatter>0 && $b['external_signal_count']>0){ $conf += 15; } if(count($b['sources'])>=2) $conf += 15; if(!$b['confirmed']) $conf=min($conf,($chatter>0?85:95)); $class=$conf>=70?'hot':($conf>=45?'trending':($conf>=25?'watch':'quiet')); $msg='Community reports are trending for this provider. Official incident not confirmed.'; if($chatter>0 && $b['report_count']===0){$msg='Public chatter has increased for this provider. This is unconfirmed.';} if($chatter>0 && $b['report_count']>0){$msg='Public chatter and community reports both suggest a possible issue.';} if($chatter>0 && $b['external_signal_count']>0){$msg='Public chatter and external telemetry both suggest a possible issue.';} if($b['report_count']>0 && $b['external_signal_count']>0 && $chatter===0){$msg='External internet-health signals and community reports both suggest a possible issue.';} elseif($b['external_signal_count']>0 && $b['synthetic_failure_count']===0 && $b['report_count']===0){$msg='External internet-health telemetry suggests a possible issue. Official incident not confirmed.';} elseif($b['synthetic_failure_count']>0 && $b['report_count']===0){$msg='A lightweight public canary check failed. This is an unconfirmed signal.';}
            $signals[]=$b + ['confidence'=>$conf,'classification'=>$class,'message'=>$msg,'id'=>md5($b['provider_id'].'|'.$b['category'].'|'.$b['region'])];
        }
        usort($signals, static fn($a,$b)=>($b['confidence']<=>$a['confidence']));
        return $signals;
    }

    private static function class_confidence(string $class): int { if($class==='watch') return 20; if($class==='trending') return 45; if($class==='hot') return 65; return 0; }

}
