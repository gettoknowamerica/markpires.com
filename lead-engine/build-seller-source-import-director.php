<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try{
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}

function sb151($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>35]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function rows151($t,$q){$r=sb151('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

$waiting=rows151('seller_source_import_queue','select=*&import_status=eq.new&order=created_at.asc&limit=500');
$pushed=0;$errors=[];
foreach($waiting as $q){
  $row=[[
    'source_type'=>$q['source_type']?:'fsbo',
    'source_platform'=>$q['source_platform']?:'manual',
    'source_url'=>$q['source_url']?:'',
    'listing_title'=>$q['listing_title']?:'',
    'property_address'=>$q['property_address']?:'',
    'town'=>$q['town']?:'',
    'state'=>$q['state']?:'CT',
    'county'=>'Fairfield County',
    'list_price'=>(float)($q['list_price']??0),
    'owner_name'=>$q['owner_name']?:'',
    'owner_phone'=>$q['owner_phone']?:'',
    'owner_email'=>$q['owner_email']?:'',
    'dnc_status'=>empty($q['owner_phone'])?'unchecked':'unchecked',
    'realtor_status'=>'unchecked',
    'approval_status'=>'source_review',
    'notes'=>'Imported by V15.1 Seller Source Import Director. '.$q['notes'],
    'raw_payload'=>$q,
    'status'=>'active',
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $r=sb151('POST','seller_opportunity_sources',$row);
  if($r['ok']){
    $pushed++;
    $id=$r['data'][0]['id']??'';
    sb151('PATCH','seller_source_import_queue?id=eq.'.rawurlencode($q['id']),['import_status'=>'pushed','pushed_to_seller_engine'=>true,'seller_opportunity_id'=>(string)$id,'updated_at'=>date('c')]);
  } else $errors[]=['queue_id'=>$q['id'],'body'=>$r['body']];
}

$watch=rows151('seller_source_watchlist','select=*&status=eq.active&order=created_at.asc&limit=200');
$all=rows151('seller_source_import_queue','select=*&order=created_at.desc&limit=1000');
$c=['waiting'=>0,'fsbo'=>0,'expired'=>0,'withdrawn'=>0,'pushed'=>0];
foreach($all as $x){
  if(($x['import_status']??'')==='new')$c['waiting']++;
  if(($x['source_type']??'')==='fsbo')$c['fsbo']++;
  if(($x['source_type']??'')==='expired')$c['expired']++;
  if(($x['source_type']??'')==='withdrawn')$c['withdrawn']++;
  if(($x['import_status']??'')==='pushed')$c['pushed']++;
}

$brief="V15.1 SELLER SOURCE IMPORT DIRECTOR\\n========================================\\n\\n";
$brief.="Watch Sources:     ".count($watch)."\\n";
$brief.="Imports Waiting:   ".$c['waiting']."\\n";
$brief.="Pushed This Run:   ".$pushed."\\n";
$brief.="Total Pushed:      ".$c['pushed']."\\n";
$brief.="FSBO Imports:      ".$c['fsbo']."\\n";
$brief.="Expired Imports:   ".$c['expired']."\\n";
$brief.="Withdrawn Imports: ".$c['withdrawn']."\\n\\n";
$brief.="WATCHLIST\\n----------------------------------------\\n";
foreach(array_slice($watch,0,15) as $i=>$w){$brief.=($i+1).". ".$w['source_name']." — ".$w['source_platform']." — ".$w['source_url']."\\n";}
$recs=[
  'Use this as the safe intake layer for Zillow/Homes/FSBO/manual exports.',
  'Do not rely on scraping blocked sites; paste or import allowed listing data.',
  'After push, run V14.1, V14.2, V14.3 and V14.5 to score, enrich, exclude Realtors and prioritize calls.'
];
$daily=[[
  'briefing_date'=>date('Y-m-d'),'watch_sources'=>count($watch),'imports_waiting'=>$c['waiting'],'pushed_today'=>$pushed,
  'fsbo_count'=>$c['fsbo'],'expired_count'=>$c['expired'],'withdrawn_count'=>$c['withdrawn'],'top_sources'=>array_slice($watch,0,20),
  'briefing_text'=>$brief,'recommendations'=>$recs,'created_at'=>date('c'),'updated_at'=>date('c')
]];
$dr=sb151('POST','seller_source_import_briefings',$daily);
if(!$dr['ok']&&str_contains($dr['body'],'duplicate key'))sb151('PATCH','seller_source_import_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);

echo json_encode(['success'=>empty($errors),'watch_sources'=>count($watch),'imports_waiting'=>$c['waiting'],'pushed_this_run'=>$pushed,'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>