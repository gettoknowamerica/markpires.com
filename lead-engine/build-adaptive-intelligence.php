<?php
/**
 * V7 Adaptive Intelligence Builder
 * Upload to: /public_html/lead-engine/build-adaptive-intelligence.php
 *
 * Run:
 * /lead-engine/build-adaptive-intelligence.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key'] ?? '';
if(!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb7($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=merge-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function key_clean($v){$v=trim((string)$v);return $v!==''?$v:'Unknown';}
function source_from_lead($l){
  $url=strtolower((string)($l['page_url']??'')); $type=strtolower((string)($l['type']??'')); $tag=$l['tag']??'';
  if(str_contains($url,'/towns/')) return 'Town Pages';
  if(str_contains($url,'/blog/')) return 'Blog';
  if(str_contains($url,'valuation')||$type==='valuation') return 'Home Valuation';
  return $tag ?: ($type ?: 'Website');
}
function score_adjustment($positive,$total){
  if($total<5) return [0,'low'];
  $rate=$total?($positive/$total):0;
  if($total>=20 && $rate>=0.45) return [15,'high'];
  if($total>=10 && $rate>=0.35) return [10,'medium'];
  if($rate>=0.25) return [5,'medium'];
  if($total>=10 && $rate<=0.05) return [-10,'medium'];
  if($total>=20 && $rate<=0.10) return [-5,'high'];
  return [0,$total>=10?'medium':'low'];
}
function upsert_rule($type,$key,$stats){
  $total=(int)($stats['total']??0);
  $pos=(int)($stats['positive']??0);
  $neg=max(0,$total-$pos);
  [$adj,$conf]=score_adjustment($pos,$total);
  $rate=$total?round($pos/$total,4):0;
  $rec=$adj>0 ? "Increase priority for {$type}: {$key}" : ($adj<0 ? "Lower priority for {$type}: {$key}" : "No score change yet for {$type}: {$key}");
  $payload=[[
    'rule_type'=>$type,'rule_key'=>$key,'sample_size'=>$total,'total_signals'=>$total,
    'positive_signals'=>$pos,'negative_signals'=>$neg,'conversion_rate'=>$rate,
    'score_adjustment'=>$adj,'confidence'=>$conf,'recommendation'=>$rec,'raw_stats'=>$stats,
    'updated_at'=>date('c')
  ]];
  return sb7('POST','adaptive_intelligence_rules',$payload);
}

$outcomes=sb7('GET','cold_call_outcomes?select=*&order=created_at.desc&limit=1000')['data'];
$future=sb7('GET','future_seller_pipeline?select=*&order=created_at.desc&limit=1000')['data'];
$leads=sb7('GET','leads?select=*&order=created_at.desc&limit=1000')['data'];

$townStats=[]; $sourceStats=[]; $outcomeStats=[];

foreach($outcomes as $o){
  $town=key_clean($o['town']??'');
  $outcome=key_clean($o['outcome']??'unknown');
  $positive=in_array($outcome,['interested','appointment','future_seller'],true);
  $townStats[$town]??=['total'=>0,'positive'=>0,'signals'=>[]];
  $townStats[$town]['total']++; if($positive)$townStats[$town]['positive']++; $townStats[$town]['signals'][]=$outcome;
  $outcomeStats[$outcome]??=['total'=>0,'positive'=>0];
  $outcomeStats[$outcome]['total']++; if($positive)$outcomeStats[$outcome]['positive']++;
}

foreach($future as $f){
  $town=key_clean($f['town']??'');
  $townStats[$town]??=['total'=>0,'positive'=>0,'signals'=>[]];
  $townStats[$town]['total']++; $townStats[$town]['positive']++; $townStats[$town]['signals'][]='future_pipeline';
}

foreach($leads as $l){
  $src=source_from_lead($l);
  $score=(int)($l['lead_score']??0);
  $positive=$score>=75 || strtolower((string)($l['type']??''))==='valuation';
  $sourceStats[$src]??=['total'=>0,'positive'=>0,'signals'=>[]];
  $sourceStats[$src]['total']++; if($positive)$sourceStats[$src]['positive']++; $sourceStats[$src]['signals'][]=$l['type']??'lead';
}

$rules=[];
foreach($townStats as $k=>$s){$res=upsert_rule('town',$k,$s);$rules[]=['type'=>'town','key'=>$k,'ok'=>$res['ok']];}
foreach($sourceStats as $k=>$s){$res=upsert_rule('source',$k,$s);$rules[]=['type'=>'source','key'=>$k,'ok'=>$res['ok']];}
foreach($outcomeStats as $k=>$s){$res=upsert_rule('outcome',$k,$s);$rules[]=['type'=>'outcome','key'=>$k,'ok'=>$res['ok']];}

$ruleRows=sb7('GET','adaptive_intelligence_rules?select=*')['data'];
$townAdj=[];$sourceAdj=[];
foreach($ruleRows as $r){
  if(($r['rule_type']??'')==='town') $townAdj[$r['rule_key']]=$r;
  if(($r['rule_type']??'')==='source') $sourceAdj[$r['rule_key']]=$r;
}

$updatedHomeowners=0;
$homeowners=sb7('GET','homeowner_intelligence?select=*&order=lead_score.desc&limit=1000')['data'];
foreach($homeowners as $h){
  $base=(int)($h['lead_score']??0); $town=key_clean($h['town']??'');
  $adj=(int)($townAdj[$town]['score_adjustment']??0);
  $reason=$adj!==0 ? "Adaptive town adjustment {$adj} for {$town}" : "No adaptive adjustment yet";
  $adaptive=max(0,min(125,$base+$adj));
  if(!empty($h['id'])){
    $res=sb7('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($h['id']),[
      'adaptive_score'=>$adaptive,'adaptive_adjustment'=>$adj,'adaptive_reason'=>$reason,'updated_at'=>date('c')
    ]);
    if($res['ok'])$updatedHomeowners++;
  }
}

$updatedLeads=0;
foreach($leads as $l){
  $base=(int)($l['lead_score']??0); $town=key_clean($l['town']??''); $src=source_from_lead($l);
  $adj=(int)($townAdj[$town]['score_adjustment']??0)+(int)($sourceAdj[$src]['score_adjustment']??0);
  $reason="Town {$town}: ".((int)($townAdj[$town]['score_adjustment']??0))."; Source {$src}: ".((int)($sourceAdj[$src]['score_adjustment']??0));
  $adaptive=max(0,min(125,$base+$adj));
  if(!empty($l['id'])){
    $res=sb7('PATCH','leads?id=eq.'.rawurlencode($l['id']),[
      'adaptive_score'=>$adaptive,'adaptive_adjustment'=>$adj,'adaptive_reason'=>$reason
    ]);
    if($res['ok'])$updatedLeads++;
  }
}

echo json_encode([
  'success'=>true,
  'rules_built'=>count($rules),
  'homeowners_updated'=>$updatedHomeowners,
  'leads_updated'=>$updatedLeads,
  'rules'=>$rules
],JSON_PRETTY_PRINT);
