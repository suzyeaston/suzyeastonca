<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/SignalSourceInterface.php';
require_once dirname(__DIR__).'/includes/Sources/PublicIncidentNewsSource.php';
use SuzyEaston\LousyOutages\Sources\PublicIncidentNewsSource as Source;
$groups=[];
foreach (Source::groups() as $group) $groups[$group['id']]=$group;
$now=strtotime('2026-11-17T22:00:00Z');
$positive=[
['us_intelligence','Classified NSA communications network goes down during maintenance','nsa'],
['ca_intelligence','CSIS systems down during service disruption','csis'],
['ca_intelligence','Communications Security Establishment network outage reported','cse'],
['ca_payments','Interac e-Transfer down for customers across Canada','interac'],
['ca_payments','Moneris payments outage affects Canada','moneris'],
['ca_banks_major','RBC online banking outage affects Canadian customers','rbc'],
['ca_banks_major','TD EasyWeb app down for customers','td_canada_trust'],
['ca_banks_major','BMO technical issue blocks online banking','bmo'],
['ca_banks_other','Vancity banking service disruption in BC','vancity'],
['ca_telecom_major','Rogers network outage affecting Canadian customers','rogers'],
['ca_telecom_major','TELUS mobile outage across British Columbia','telus'],
['ca_telecom_major','Bell internet down in Ontario','bell'],
['ca_telecom_other','SaskTel network outage impacts Saskatchewan','sasktel'],
['ca_public_services','CRA My Account unavailable after technical issue','cra_my_account'],
['ca_critical_services','BC Hydro service outage affects customers','bc_hydro']
];
$passed=0;
foreach($positive as [$group,$headline,$expected]){
    $actual=Source::match_item(['title'=>$headline,'published_at'=>'Tue, 17 Nov 2026 20:30:00 GMT'],$groups[$group],$now);
    if(!in_array($expected,array_column($actual,'id'),true)){fwrite(STDERR,"Missed {$expected}: {$headline}\n");exit(1);}
    $passed++;
}
$negative=[
['ca_banks_major','RBC shares down amid Canadian stock market selloff','Tue, 17 Nov 2026 20:30:00 GMT'],
['us_intelligence','NASA network outage causes disruption','Tue, 17 Nov 2026 20:30:00 GMT'],
['ca_intelligence','US think tank CSIS network outage in Washington','Tue, 17 Nov 2026 20:30:00 GMT'],
['ca_telecom_major','Rogers wins award for network reliability','Tue, 17 Nov 2026 20:30:00 GMT'],
['ca_payments','Interac outage drill scheduled for staff','Tue, 17 Nov 2026 20:30:00 GMT'],
['ca_banks_major','RBC online banking outage yesterday','Tue, 10 Nov 2026 20:30:00 GMT'],
['ca_payments','Interac e-Transfer down','']
];
foreach($negative as [$group,$headline,$published]){
    if(Source::match_item(['title'=>$headline,'published_at'=>$published],$groups[$group],$now)){fwrite(STDERR,"False positive: {$headline}\n");exit(1);}
    $passed++;
}
echo "Public incident news matching: {$passed} scenarios passed\n";
