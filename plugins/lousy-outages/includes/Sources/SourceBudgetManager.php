<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages\Sources;

class SourceBudgetManager {
    private const KEY = 'lo_source_budget_state';
    public static function can_attempt(string $source, string $host = '', int $dailyCap = 0): array {
        $s = self::state(); $now=time(); $row=(array)($s[$source]??[]);
        if (!empty($row['next_allowed_at']) && $now < (int)$row['next_allowed_at']) return ['ok'=>false,'reason'=>'cooldown_active'];
        if ($dailyCap>0) { $day=gmdate('Y-m-d'); $used=(int)(($row['daily'][$day]??0)); if($used>=$dailyCap) return ['ok'=>false,'reason'=>'daily_budget_exhausted']; }
        if ($host !== '') { $h=(array)($row['hosts'][$host]??[]); if(!empty($h['next_allowed_at']) && $now < (int)$h['next_allowed_at']) return ['ok'=>false,'reason'=>'per_host_throttle']; }
        return ['ok'=>true];
    }
    public static function mark_attempt(string $source, string $host = '', int $hostCooldown = 0): void { $s=self::state(); $day=gmdate('Y-m-d'); $s[$source]['daily'][$day]=(int)($s[$source]['daily'][$day]??0)+1; if($host!==''&&$hostCooldown>0){$s[$source]['hosts'][$host]['next_allowed_at']=time()+$hostCooldown*60;} self::save($s); }
    public static function mark_result(string $source, bool $ok, int $httpCode = 200): void { $s=self::state(); $now=time(); $row=(array)($s[$source]??[]); if($ok){$row['last_success_at']=$now;$row['consecutive_failures']=0;} else { $row['last_error_at']=$now; $fails=(int)($row['consecutive_failures']??0)+1; $row['consecutive_failures']=$fails; $row['next_allowed_at']=$now+min(3600, (int)pow(2,min($fails,8))*30); } if($httpCode===429){$row['last_429_at']=$now;$row['next_allowed_at']=$now+1800;} $s[$source]=$row; self::save($s); }
    public static function source_state(string $source): array { return (array)(self::state()[$source]??[]); }
    private static function state(): array { $v=get_option(self::KEY,[]); return is_array($v)?$v:[]; }
    private static function save(array $s): void { update_option(self::KEY,$s,false); }
}
