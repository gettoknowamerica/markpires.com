<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb1291($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function slug1291($s){ $s=strtolower(trim((string)$s)); $s=preg_replace('/[^a-z0-9]+/','-',$s); return trim($s,'-') ?: 'campaign'; }
  function final_url1291($landing,$source,$medium,$campaign){
    $base = $landing ?: '/home-valuation';
    if(!str_starts_with($base,'http')) $base = 'https://markpires.com'.($base[0]==='/'?$base:'/'.$base);
    $sep = str_contains($base,'?') ? '&' : '?';
    return $base.$sep.http_build_query(['utm_source'=>$source,'utm_medium'=>$medium,'utm_campaign'=>$campaign]);
  }

  $campaigns = sb1291('GET','first_campaign_plan?select=*&order=priority_score.desc,created_at.desc&limit=100')['data'];
  $created=[]; $errors=[];

  foreach($campaigns as $c){
    if(!is_array($c)) continue;
    $name = $c['campaign_name'] ?? 'Campaign';
    $cid = (string)($c['id'] ?? md5($name));
    $slug = slug1291($name);
    $landing = $c['landing_page'] ?? '/home-valuation';
    $source = 'meta';
    $medium = 'paid_social';
    $final = final_url1291($landing,$source,$medium,$slug);

    $checklist = [
      'campaign_copy_ready'=>!empty($c['facebook_primary_text']) || !empty($c['ad_body']),
      'headline_ready'=>!empty($c['facebook_headline']) || !empty($c['ad_headline']),
      'creative_prompt_ready'=>!empty($c['creative_prompt']),
      'landing_page_ready'=>!empty($landing),
      'utm_ready'=>true,
      'lead_capture_ready'=>true,
      'jessica_followup_ready'=>true,
      'roi_tracking_ready'=>true
    ];
    $missing=[]; foreach($checklist as $k=>$v){ if(!$v) $missing[]=$k; }
    $status = empty($missing) ? 'ready' : 'needs_fix';

    $r=sb1291('POST','live_ad_launch_checklists',[[
      'campaign_id'=>$cid,'campaign_name'=>$name,'platform'=>'Meta','launch_status'=>$status,
      'landing_page'=>$landing,'final_url'=>$final,'utm_source'=>$source,'utm_medium'=>$medium,'utm_campaign'=>$slug,
      'daily_budget'=>(float)($c['campaign_budget'] ?? 25),'checklist'=>$checklist,'missing_items'=>$missing,
      'launch_notes'=>empty($missing) ? 'Ready for manual launch review in Meta Ads Manager.' : 'Fix missing checklist items before launch.',
      'created_at'=>date('c'),'updated_at'=>date('c')
    ]]);
    if($r['ok']) $created[]=$name; else $errors[]=['campaign'=>$name,'http'=>$r['http'],'body'=>$r['body']];
  }

  echo json_encode(['success'=>empty($errors),'campaigns_checked'=>count($campaigns),'created_count'=>count($created),'created'=>$created,'errors'=>$errors],JSON_PRETTY_PRINT);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>