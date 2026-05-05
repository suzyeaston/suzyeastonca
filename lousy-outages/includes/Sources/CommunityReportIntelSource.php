<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;
use SuzyEaston\LousyOutages\UserReports;

class CommunityReportIntelSource implements SignalSourceInterface {
    public function id(): string { return 'community_report_intel'; }
    public function label(): string { return 'Community Report Intel'; }
    public function is_configured(): bool { return true; }
    private function has_issue_language(string $text): bool { return (bool) preg_match('/\b(down|outage|incident|degraded|error|failure|unavailable|latency|disruption)\b/i', $text); }
    public function collect(array $options = []): array { $rows=UserReports::recent(60,100); $out=[]; foreach($rows as $r){ $txt=sanitize_text_field((string)($r['issue_text']??$r['symptom']??'')); if(!$this->has_issue_language($txt)) continue; $out[]=['source'=>'community_reports','source_type'=>'community_report','adapter_id'=>'community_report_normalizer','source_id'=>sanitize_text_field((string)($r['id']??md5(wp_json_encode($r)))),'provider_id'=>sanitize_key((string)($r['provider_id']??'')),'provider_name'=>sanitize_text_field((string)($r['provider_name']??$r['provider_id']??'Unknown')),'category'=>sanitize_key((string)($r['category']??'community')),'region'=>sanitize_text_field((string)($r['region']??'')),'signal_type'=>'user_report','severity'=>'watch','confidence'=>45,'title'=>'Community report: '.sanitize_text_field((string)($r['provider_name']??$r['provider_id']??'provider')),'message'=>mb_substr($txt,0,220),'evidence_quality'=>'weak','official_confirmed'=>false,'unconfirmed_note'=>'Community reports are unconfirmed unless corroborated.','observed_at'=>sanitize_text_field((string)($r['reported_at']??gmdate('Y-m-d H:i:s')))]; } return $out; }
}
