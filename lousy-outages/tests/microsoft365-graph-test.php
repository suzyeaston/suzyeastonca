<?php
declare(strict_types=1);

define('MINUTE_IN_SECONDS',60);
define('HOUR_IN_SECONDS',3600);
define('LOUSY_OUTAGES_M365_TENANT_ID','11111111-1111-4111-8111-111111111111');
define('LOUSY_OUTAGES_M365_CLIENT_ID','22222222-2222-4222-8222-222222222222');
define('LOUSY_OUTAGES_M365_CLIENT_SECRET','fixture-only-not-real');
$GLOBALS['opts']=[];
$GLOBALS['auth_code']=200;
$GLOBALS['graph_code']=200;
$GLOBALS['auth_requests']=[];
$GLOBALS['graph_requests']=[];
function get_option($k,$default=[]){return $GLOBALS['opts'][$k]??$default;}
function update_option($k,$v,$autoload=false){$GLOBALS['opts'][$k]=$v;return true;}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function wp_json_encode($v){return json_encode($v);}
function sanitize_key($v){return trim(preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)),'_-');}
function is_wp_error($value){return false;}
function wp_remote_retrieve_response_code($v){return (int)$v['response']['code'];}
function wp_remote_retrieve_body($v){return $v['body'];}
function wp_remote_post($url,$args){
    $GLOBALS['auth_requests'][]=['url'=>$url,'scope'=>$args['body']['scope']];
    return ['response'=>['code'=>$GLOBALS['auth_code']],'body'=>json_encode(['access_token'=>'fixture-access-token'])];
}
function wp_remote_get($url,$args){
    $GLOBALS['graph_requests'][]=['url'=>$url,'has_bearer'=>str_starts_with($args['headers']['Authorization'],'Bearer ')];
    $items=[
        ['id'=>'EX1550543','title'=>'Some users cannot connect to Exchange Online','service'=>'Exchange Online','status'=>'ServiceDegradation','classification'=>'Incident','isResolved'=>false,'startDateTime'=>'2026-11-12T15:00:00Z','lastModifiedDateTime'=>'2026-11-12T18:00:00Z','impactDescription'=>'Outlook sign-in and connectivity affected.'],
        ['id'=>'MO1550821','title'=>'Multiple Microsoft 365 services impacted','service'=>'Microsoft 365 suite','status'=>'ServiceInterruption','isResolved'=>false,'startDateTime'=>'2026-11-12T16:00:00Z'],
        ['id'=>'EX0000010','title'=>'Resolved historical issue','status'=>'ServiceRestored','isResolved'=>true,'startDateTime'=>'2026-11-11T16:00:00Z']
    ];
    return ['response'=>['code'=>$GLOBALS['graph_code']],'body'=>json_encode(['value'=>$items])];
}
function chk($x,$message){if(!$x){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
require __DIR__.'/../includes/Microsoft365ServiceHealth.php';
use SuzyEaston\LousyOutages\Microsoft365ServiceHealth as Graph;

chk(Graph::configured(),'authorized test credentials configured');
$result=Graph::refresh();
chk($result['status']==='completed'&&$result['active_count']===2,'authenticated Graph issues collected');
chk($GLOBALS['auth_requests'][0]['scope']==='https://graph.microsoft.com/.default','tenant auth uses Graph scope');
chk(str_contains($GLOBALS['graph_requests'][0]['url'],'/admin/serviceAnnouncement/issues'),'correct Graph endpoint');
chk($GLOBALS['graph_requests'][0]['has_bearer'],'Graph request supplies access token');
$issues=Graph::active_issues();
chk(count($issues)===2&&$issues[0]['id']==='EX1550543','unresolved Exchange issue retained');
chk($issues[1]['id']==='MO1550821'&&$issues[1]['severity']==='critical','major Microsoft issue classified');
chk(Graph::active_issues(time()+3600)===[],'official data expires instead of staying active forever');
chk(!str_contains(json_encode($GLOBALS['opts']),'fixture-only-not-real'),'secret never stored in WordPress options');
chk(!str_contains(json_encode($GLOBALS['opts']),'fixture-access-token'),'access token never stored in WordPress options');
$state=['providers'=>[],'outages'=>[],'signals'=>[],'meta'=>[]];
$augmented=Graph::augment_state($state);
chk(count($augmented['outages'])===2&&count($augmented['providers'])===1,'official Graph issues reach dashboard');
chk($augmented['providers'][0]['verification_status']==='verified','official Graph issues labeled verified');
$before=get_option(Graph::OPTION_STATE);
$GLOBALS['graph_code']=403;
$result=Graph::refresh();
chk($result['status']==='graph_http_or_schema_error'&&$result['http_status']===403,'auth errors visible in diagnostics');
chk(get_option(Graph::OPTION_STATE)===$before,'failed request preserves last successful data until expiry');
echo "Microsoft 365 authorized Graph adapter and dashboard regression passed\n";
