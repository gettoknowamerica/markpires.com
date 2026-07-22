<?php
declare(strict_types=1);
/**
 * Goliath V119.3 Universal Capture
 * Internal MySQL is the source of truth. No HubSpot. No Supabase.
 */
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
$allowed=['https://markpires.com','https://www.markpires.com','https://discoverct.net','https://www.discoverct.net'];
$origin=$_SERVER['HTTP_ORIGIN']??'';
if($origin&&in_array($origin,$allowed,true))header('Access-Control-Allow-Origin: '.$origin);
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'success'=>false,'error'=>'POST only']);exit;}

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-internal-crm-v119-3.php';

try{
 $raw=(string)file_get_contents('php://input');
 $data=json_decode($raw,true);
 if(!is_array($data))$data=$_POST;
 if(!is_array($data)||!$data)throw new RuntimeException('No lead data received.');

 $lead=g1193_normalize($data);
 if($lead['email']===''&&$lead['phone']==='')throw new RuntimeException('Email or phone is required.');

 $saved=g1193_save($lead,$data);
 if(!$saved['ok'])throw new RuntimeException('Internal CRM commit failed.');

 $drip=g1193_seed_drip($lead,(int)$saved['contact_id'],(int)$saved['lead_id']);
 $enrichment=g1193_enqueue_enrichment($lead,(int)$saved['contact_id'],(int)$saved['lead_id']);
 $team=g1193_trigger_team($lead,(int)$saved['contact_id'],(int)$saved['lead_id']);

 // Optional acknowledgement through the existing internal Jessica dispatcher.
 $ack=['ok'=>false,'skipped'=>true];
 try{
  if(file_exists(__DIR__.'/jessica-dispatch.php')){
   require_once __DIR__.'/jessica-dispatch.php';
   if(function_exists('g93_send_acknowledgement'))$ack=g93_send_acknowledgement($lead,(int)$saved['contact_id']);
  }
 }catch(Throwable $mailError){
  g1193_insert('goliath_revenue_engine_failures',[
   'failure_uid'=>g1193_uid('failure'),'lead_uid'=>$lead['lead_uid'],
   'service'=>'acknowledgement_email','severity'=>'warning','message'=>$mailError->getMessage(),
   'payload'=>g1193_json($lead),'created_at'=>gdb_now()
  ]);
  $ack=['ok'=>false,'error'=>$mailError->getMessage()];
 }

 echo json_encode([
  'ok'=>true,'success'=>true,'version'=>'V119.3 Internal CRM Capture',
  'message'=>'Thank you so much for reaching out. Mark will be calling you shortly.',
  'lead_uid'=>$lead['lead_uid'],'crm_contact_id'=>$saved['contact_id'],'lead_id'=>$saved['lead_id'],
  'lead_score'=>$lead['lead_score'],'route'=>$lead['route'],
  'drip'=>$drip,'enrichment'=>$enrichment,'executive_tasks'=>$team,'acknowledgement'=>$ack,
  'source_of_truth'=>'Hostinger MySQL internal CRM'
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'success'=>false,'version'=>'V119.3 Internal CRM Capture','error'=>$e->getMessage()],JSON_PRETTY_PRINT);
}
?>