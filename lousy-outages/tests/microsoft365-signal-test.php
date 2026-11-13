<?php
declare(strict_types=1);

const MINUTE_IN_SECONDS=60;
const HOUR_IN_SECONDS=3600;
$GLOBALS['options']=['lousy_outages_public_chatter_enabled'=>'0'];
$GLOBALS['cron']=[];
$GLOBALS['actions']=[];
function sanitize_key($v){return trim(preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)),'_-');}
function apply_filters($hook,$value){return $value;}
function get_option($name,$default=false){return $GLOBALS['options'][$name]??$default;}
function update_option($name,$value,$autoload=false){$GLOBALS['options'][$name]=$value;return true;}
function add_action($name,$callback){$GLOBALS['actions'][$name][]=$callback;}
function wp_next_scheduled($name){return $GLOBALS['cron'][$name]??false;}
function wp_schedule_event($time,$cadence,$name){$GLOBALS['cron'][$name]=$time;return true;}
function check_lo($test,$message){if(!$test){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}}
require __DIR__.'/../includes/SignalSourceInterface.php';
require __DIR__.'/../includes/ProviderRegistry.php';
require __DIR__.'/../includes/Providers.php';
require __DIR__.'/../includes/Sources/PublicChatterSource.php';
require __DIR__.'/../includes/Microsoft365Signals.php';

use SuzyEaston\LousyOutages\Sources\PublicChatterSource;
use SuzyEaston\LousyOutages\Microsoft365Signals;
$source=new PublicChatterSource();
$method=new ReflectionMethod(PublicChatterSource::class,'build_query_sets');
$queries=$method->invoke($source,['azure'=>['id'=>'azure','name'=>'Azure','category'=>'cloud']],[],['active_incident_queries_per_provider'=>5]);
check_lo(isset($queries['queries']['microsoft365']),'Microsoft 365 has a separate public-signal query family');
check_lo(in_array('outlook down',$queries['queries']['microsoft365']['queries'],true),'Outlook is searched under Microsoft 365');
check_lo(!in_array('microsoft 365 down',$queries['queries']['azure']['queries'],true),'Microsoft 365 is not classified as Azure');
check_lo($queries['queries']['microsoft365']['provider']['name']==='Microsoft 365','signal provider label is correct');
Microsoft365Signals::bootstrap();
check_lo(isset($GLOBALS['actions']['init']),'M365 scheduler registered');
Microsoft365Signals::schedule();
$first=$GLOBALS['cron'][Microsoft365Signals::HOOK]??0;
check_lo($first>time(),'isolated scan schedules in the future');
Microsoft365Signals::schedule();
check_lo($GLOBALS['cron'][Microsoft365Signals::HOOK]===$first,'scan scheduling is idempotent');
Microsoft365Signals::scan();
check_lo($GLOBALS['options']['lo_microsoft365_signal_scan_result']['status']==='public_chatter_disabled','disabled public chatter makes no network calls');
echo "Microsoft 365 bounded source regression passed\n";
