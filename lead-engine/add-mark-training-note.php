<?php
/**
 * V12.15.2 Add Mark Strategy Training Note — 500 Fix
 * Upload over: /public_html/lead-engine/add-mark-training-note.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key=$_GET['key']??$_POST['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb1522tfix($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST=>$method,
      CURLOPT_HTTPHEADER=>[
        'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
      ],
      CURLOPT_TIMEOUT=>30
    ]);
    if($payload!==null){
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
    return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }

  $title=$_GET['title']??$_POST['title']??'Mark Strategy Note';
  $note=$_GET['note']??$_POST['note']??'';
  $type=$_GET['note_type']??$_POST['note_type']??'mark_style';
  $applies=$_GET['applies_to']??$_POST['applies_to']??'all';
  $priority=(int)($_GET['priority']??$_POST['priority']??75);

  if(!$note){ echo json_encode(['success'=>false,'error'=>'Missing note']); exit; }

  $res=sb1522tfix('POST','mark_strategy_training_notes',[[
    'note_type'=>$type,
    'title'=>$title,
    'note'=>$note,
    'applies_to'=>$applies,
    'priority'=>$priority,
    'active'=>true,
    'raw_payload'=>['get'=>$_GET,'post'=>$_POST],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]]);

  echo json_encode(['success'=>$res['ok'],'inserted'=>$res['data'],'http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()], JSON_PRETTY_PRINT);
}
?>