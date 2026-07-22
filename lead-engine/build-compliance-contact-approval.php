<?php
/**
 * V20.3 Compliance + Contact Approval Builder
 * Upload: /public_html/lead-engine/build-compliance-contact-approval.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function ccsb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$m,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>60
  ]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $rows=ccsb('GET','owner_enrichment_queue?select=*&order=priority_score.desc,updated_at.desc&limit=1000');
  if(!$rows['ok']){
    echo json_encode(['success'=>false,'error'=>'Could not read owner_enrichment_queue','details'=>$rows['body']],JSON_PRETTY_PRINT);
    exit;
  }

  $created=0; $updated=0; $approvedQueued=0; $blocked=0; $needsReview=0; $errors=[];

  foreach($rows['data'] as $r){
    $queueId=$r['id'];
    $priority=(int)($r['priority_score']??0);
    $seller=(int)($r['seller_signal_score']??0);
    $dnc=$r['dnc_status']??'not_checked';
    $realtor=$r['realtor_status']??'not_checked';

    // Default: research-only. Mark must approve before direct outreach.
    $permission='research_only';
    $approval='needs_review';
    $method='research';
    $reason='Research candidate. DNC, realtor exclusion, source permissions, and human approval are required before direct outreach.';

    if($priority>=80 || $seller>=80){
      $needsReview++;
      $reason='High-priority owner candidate. Move to human compliance review before outreach.';
      $method='manual_research';
    }

    if(($r['compliance_status']??'')==='blocked'){
      $permission='blocked';
      $approval='blocked';
      $method='none';
      $reason='Blocked by compliance status.';
      $blocked++;
    }

    $payload=[
      'owner_enrichment_queue_id'=>$queueId,
      'municipal_owner_record_id'=>$r['municipal_owner_record_id']??null,
      'owner_name'=>$r['owner_name']??'',
      'property_address'=>$r['property_address']??'',
      'town'=>$r['town']??'Fairfield',
      'seller_signal_score'=>$seller,
      'priority_score'=>$priority,
      'possible_phone'=>$r['possible_phone']??'',
      'possible_email'=>$r['possible_email']??'',
      'mailing_address'=>$r['mailing_address']??'',
      'dnc_status'=>'not_checked',
      'realtor_status'=>'not_checked',
      'opt_out_status'=>'not_checked',
      'contact_permission_status'=>$permission,
      'approval_status'=>$approval,
      'recommended_contact_method'=>$method,
      'jessica_reason'=>$reason,
      'updated_at'=>date('c')
    ];

    $existing=ccsb('GET','owner_compliance_reviews?select=id,approval_status,contact_permission_status,dnc_status,realtor_status,opt_out_status&owner_enrichment_queue_id=eq.'.rawurlencode($queueId).'&limit=1');

    if($existing['ok'] && !empty($existing['data'])){
      $old=$existing['data'][0];
      // preserve human decisions
      if(!in_array(($old['approval_status']??''),['needs_review',''],true)){
        $payload['approval_status']=$old['approval_status'];
        $payload['contact_permission_status']=$old['contact_permission_status'];
        $payload['dnc_status']=$old['dnc_status'];
        $payload['realtor_status']=$old['realtor_status'];
        $payload['opt_out_status']=$old['opt_out_status'];
      }
      $res=ccsb('PATCH','owner_compliance_reviews?id=eq.'.rawurlencode($old['id']),$payload);
      if($res['ok']) $updated++; else $errors[]=$res['body'];
    } else {
      $payload['created_at']=date('c');
      $res=ccsb('POST','owner_compliance_reviews',[$payload]);
      if($res['ok']) $created++; else $errors[]=$res['body'];
    }
  }

  // Push approved records to contact queue
  $approved=ccsb('GET','owner_compliance_reviews?select=*&approval_status=eq.approved&limit=500');
  if($approved['ok']){
    foreach($approved['data'] as $a){
      $method=$a['recommended_contact_method'] ?: 'mail_only';
      $perm=$a['contact_permission_status'] ?? 'research_only';

      if($perm==='blocked' || ($a['dnc_status']??'')==='blocked' || ($a['realtor_status']??'')==='blocked' || ($a['opt_out_status']??'')==='opted_out'){
        continue;
      }

      $script='Compliant owner research follow-up. Reference local homeowner value/resources only. No pressure. Do not imply private info. Mark should call personally for high-value/priority owner candidates.';
      if($perm==='mail_only') $script='Mail-only approved. Prepare private homeowner value/resource letter. No phone outreach.';
      if($perm==='approved_for_mark_call') $script='Mark call approved after DNC/realtor/opt-out review. Keep call short, local, helpful, and compliant.';
      if($perm==='approved_for_jessica_followup') $script='Jessica follow-up approved only if DNC/realtor/opt-out review is clear and human approved.';

      $existing=ccsb('GET','approved_owner_contact_queue?select=id&owner_compliance_review_id=eq.'.rawurlencode($a['id']).'&limit=1');
      $payload=[
        'owner_compliance_review_id'=>$a['id'],
        'owner_name'=>$a['owner_name'],
        'property_address'=>$a['property_address'],
        'town'=>$a['town'],
        'possible_phone'=>$a['possible_phone'],
        'possible_email'=>$a['possible_email'],
        'mailing_address'=>$a['mailing_address'],
        'contact_method'=>$method,
        'assigned_to'=>($perm==='approved_for_jessica_followup'?'Jessica':'Mark'),
        'priority_score'=>$a['priority_score'],
        'seller_signal_score'=>$a['seller_signal_score'],
        'script_notes'=>$script,
        'compliance_snapshot'=>[
          'dnc_status'=>$a['dnc_status'],
          'realtor_status'=>$a['realtor_status'],
          'opt_out_status'=>$a['opt_out_status'],
          'permission'=>$perm,
          'approved_at'=>$a['approved_at']
        ],
        'updated_at'=>date('c')
      ];
      if($existing['ok'] && !empty($existing['data'])){
        ccsb('PATCH','approved_owner_contact_queue?id=eq.'.rawurlencode($existing['data'][0]['id']),$payload);
      } else {
        $payload['created_at']=date('c');
        $q=ccsb('POST','approved_owner_contact_queue',[$payload]);
        if($q['ok']) $approvedQueued++; else $errors[]=$q['body'];
      }
    }
  }

  echo json_encode([
    'success'=>empty($errors),
    'reviews_created'=>$created,
    'reviews_updated'=>$updated,
    'needs_review_count'=>$needsReview,
    'blocked_count'=>$blocked,
    'approved_added_to_contact_queue'=>$approvedQueued,
    'errors'=>array_slice($errors,0,5),
    'compliance_note'=>'No direct outreach should happen until DNC, realtor exclusion, opt-out, and human approval are complete.'
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>