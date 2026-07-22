<?php
/**
 * V20.8 Opportunity Context Builder
 * Upload: /public_html/lead-engine/build-opportunity-context.php
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function sb($m,$ep,$p=null){
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
 if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
 $b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
 return ['ok'=>$h>=200&&$h<300,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function add_context($type,$title,$table,$id,$score,$summary,$action,$content,$ad){
 return sb('POST','jessica_opportunity_context',[[
  'context_date'=>date('Y-m-d'),'opportunity_type'=>$type,'title'=>$title,'source_table'=>$table,'source_id'=>(string)$id,'score'=>$score,
  'summary'=>$summary,'recommended_action'=>$action,'connected_content_idea'=>$content,'connected_ad_idea'=>$ad,'next_step_status'=>'new',
  'created_at'=>date('c'),'updated_at'=>date('c')
 ]]);
}
try{
 $key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
 $created=0;

 $streets=sb('GET','street_intelligence?select=*&order=street_score.desc&limit=10')['data'];
 foreach($streets as $s){
  if((int)$s['street_score']<60) continue;
  add_context('street','Street Opportunity: '.$s['street_name'],'street_intelligence',$s['id']??$s['street_name'],(int)$s['street_score'],
   ($s['jessica_notes']??'Street has seller research signals.'),
   'Research top owners on '.$s['street_name'].' and prepare seller-facing local value angle.',
   'Create a short: “What homeowners on '.$s['street_name'].' should know about today’s market.”',
   'Test a local Home Value Funnel ad angle around '.$s['town'].' homeowner equity and real local pricing.');
  $created++;
 }

 $approved=sb('GET','approved_owner_contact_queue?select=*&order=priority_score.desc&limit=10')['data'];
 foreach($approved as $a){
  add_context('owner','Owner Opportunity: '.$a['owner_name'],'approved_owner_contact_queue',$a['id']??'',(int)$a['priority_score'],
   'Approved owner contact queue item at '.$a['property_address'].'.',
   'Mark should review and contact according to approved method: '.$a['contact_method'].'.',
   'Create a homeowner resource piece relevant to this street/town.',
   'Use this signal to inspire a seller-focused valuation ad, not a direct-personalized public ad.');
  $created++;
 }

 $leads=sb('GET','leads?select=*&order=created_at.desc&limit=10')['data'];
 foreach($leads as $l){
  $score=(int)($l['adaptive_score']??$l['lead_score']??0);
  if($score<60) continue;
  add_context('lead','Lead Opportunity: '.($l['name']??'Unknown'),'leads',$l['id']??'',$score,
   'Lead entered Goliath: '.($l['name']??'Unknown').' at '.($l['address']??'').'.',
   'Call or personally review this lead quickly if routed to Mark priority.',
   'Create follow-up content answering the likely question behind this lead.',
   'Use lead pattern to improve Home Value Funnel ad copy.');
  $created++;
 }

 echo json_encode(['success'=>true,'contexts_created'=>$created],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>