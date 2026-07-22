<?php
/**
 * V96.2 Jessica Relationship Engine
 * Converts Scout-ready dossiers into Jessica email drafts, relationship queue items, and timeline events.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 if(file_exists(__DIR__.'/executive-kernel-v96.php')) require_once __DIR__.'/executive-kernel-v96.php';
 if(file_exists(__DIR__.'/goliath-normalize.php')) require_once __DIR__.'/goliath-normalize.php';

 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function j962_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function j962_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function j962_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function j962_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
 function j962_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(j962_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function j962_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(j962_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function j962_name_first($name){$name=trim((string)$name);return $name?preg_split('/\s+/',$name)[0]:'there';}
 function j962_abs_url($path){$path=trim((string)$path);if(!$path)return 'https://www.markpires.com/blog/';if(strpos($path,'http')===0)return $path;return 'https://www.markpires.com/'.ltrim($path,'/');}
 function j962_infer_campaign($leadType,$town,$blog){
   $blob=strtolower($leadType.' '.$town.' '.$blog);
   if(strpos($blob,'expired')!==false||strpos($blob,'withdrawn')!==false) return 'expired_recovery';
   if(strpos($blob,'absentee')!==false) return 'absentee_owner';
   if(strpos($blob,'buyer')!==false) return 'buyer_intro';
   if(strpos($blob,'luxury')!==false) return 'luxury_intro';
   if(strpos($blob,'california')!==false||strpos($blob,'relocation')!==false) return 'relocation_intro';
   return 'seller_value_intro';
 }
 function j962_subject($campaign,$town){
   $town=trim((string)$town);
   if($campaign==='expired_recovery') return 'A smarter relaunch plan for your home';
   if($campaign==='absentee_owner') return 'A helpful resource for Connecticut property owners';
   if($campaign==='buyer_intro') return 'A quick home search resource from Mark';
   if($campaign==='luxury_intro') return 'A Connecticut luxury home resource from Mark';
   if($campaign==='relocation_intro') return 'A helpful Connecticut relocation resource';
   return $town ? 'A helpful '.$town.' real estate resource from Mark' : 'A helpful real estate resource from Mark';
 }
 function j962_body($name,$campaign,$blog,$town,$address){
   $first=htmlspecialchars(j962_name_first($name));
   $blogUrl=htmlspecialchars(j962_abs_url($blog));
   $markPhone=defined('MARK_PHONE')?MARK_PHONE:'203-247-2655';
   $markEmail=defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com';
   $topic='a resource Mark thought may be helpful';
   if($campaign==='expired_recovery') $topic='a resource on what to do when a home did not sell the first time';
   elseif($campaign==='absentee_owner') $topic='a resource for Connecticut owners managing a property from a distance';
   elseif($campaign==='buyer_intro') $topic='a buyer checklist that can save time before touring homes';
   elseif($campaign==='luxury_intro') $topic='a Connecticut luxury home guide';
   elseif($campaign==='relocation_intro') $topic='a Connecticut relocation guide';
   $townLine=$town?' in '.htmlspecialchars($town):'';
   $addrLine=$address?'<p style="margin:0 0 14px;color:#4b5563">Property reference: '.htmlspecialchars($address).'</p>':'';
   return '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;color:#111827;line-height:1.55">
    <h2 style="color:#111827;margin-bottom:8px">Hi '.$first.',</h2>
    <p>Jessica here from Mark Pires’ office.</p>
    '.$addrLine.'
    <p>Mark asked me to send over '.$topic.$townLine.'. It is short, practical, and may help as you think through your next step.</p>
    <p><a href="'.$blogUrl.'" style="display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:bold">Open the resource</a></p>
    <p>If you have any questions, you can reply here or call/text Mark directly at <b>'.htmlspecialchars($markPhone).'</b>.</p>
    <p style="margin-top:24px">Warmly,<br><b>Jessica</b><br>Executive Assistant to Mark Pires<br><a href="mailto:'.htmlspecialchars($markEmail).'">'.htmlspecialchars($markEmail).'</a></p>
   </div>';
 }
 function j962_text($name,$campaign,$blog,$town,$address){
   $first=j962_name_first($name);
   $markPhone=defined('MARK_PHONE')?MARK_PHONE:'203-247-2655';
   return "Hi {$first},\n\nJessica here from Mark Pires' office. Mark asked me to send a quick resource that may help as you think through your next step".($town?" in {$town}":"").".\n\n".j962_abs_url($blog)."\n\nIf anything changes, reply here or call/text Mark directly at {$markPhone}.\n\nWarmly,\nJessica\nExecutive Assistant to Mark Pires";
 }
 function j962_timeline($exec,$type,$title,$details,$meta=[],$contactId=null,$dossierId=null){
   if(j962_table('relationship_timeline')) j962_insert('relationship_timeline',['event_uid'=>j962_uid('rel'),'contact_id'=>$contactId,'dossier_id'=>$dossierId,'executive_key'=>$exec,'event_type'=>$type,'title'=>$title,'details'=>$details,'metadata'=>j962_json($meta),'created_at'=>gdb_now()]);
   if(function_exists('gx96_timeline')) gx96_timeline($exec,$type,$title,$details,$meta);
 }
 $limit=max(1,min(200,(int)($_GET['limit']??50)));
 if(function_exists('gx96_boot')) $boot=gx96_boot('jessica',['mission_type'=>'relationship_engine','title'=>'Prepare first-touch relationship drafts from Scout-ready dossiers']);

 $rows=gdb_all("SELECT d.*, c.email_1 c_email_1,c.email_2 c_email_2,c.best_email c_best_email,c.phone_1 c_phone_1,c.best_phone c_best_phone,c.owner_name c_owner,c.property_address c_address,c.town c_town,c.relationship_stage c_stage
  FROM scout_intel_dossiers d
  LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
  WHERE (d.handoff_status='ready_for_mark' OR d.research_status='ready_for_mark' OR COALESCE(d.best_phone,d.phone_1,d.phone,c.best_phone,c.phone_1,'')<>'' OR COALESCE(d.best_email,d.email_1,d.email,c.best_email,c.email_1,'')<>'')
    AND COALESCE(d.jessica_status,'') NOT IN ('drafted','approved','sent','do_not_contact')
  ORDER BY COALESCE(d.completion_score,0) DESC, COALESCE(d.completed_at,d.updated_at,d.created_at) DESC
  LIMIT {$limit}")?:[];

 $created=[];$skipped=[];
 foreach($rows as $r){
   $contactId=(int)($r['contact_id']??0);
   $dossierId=(int)$r['id'];
   $name=$r['owner_name']?:($r['c_owner']??'');
   $address=$r['property_address']?:($r['c_address']??'');
   $town=$r['town']?:($r['c_town']??'');
   $email=$r['best_email']?:($r['email_1']??'')?:($r['email_2']??'')?:($r['c_best_email']??'')?:($r['c_email_1']??'')?:($r['c_email_2']??'');
   $phone=$r['best_phone']?:($r['phone_1']??'')?:($r['phone']??'')?:($r['c_best_phone']??'')?:($r['c_phone_1']??'');
   $blog=$r['recommended_blog']?:'/blog/selling-an-absentee-owned-home-in-connecticut.html';
   $leadType=$r['source_label']?:'seller';
   $campaign=j962_infer_campaign($leadType,$town,$blog);
   $priority=($email?120:90)+(int)($r['completion_score']??0);

   $exists=gdb_one("SELECT id FROM jessica_relationship_queue WHERE dossier_id=? AND status IN ('queued','drafted','pending_approval','approved','sent') LIMIT 1",[$dossierId]);
   if($exists){$skipped[]=['dossier_id'=>$dossierId,'reason'=>'already_queued'];continue;}

   $queueId=j962_insert('jessica_relationship_queue',[
     'queue_uid'=>j962_uid('jq'),
     'contact_id'=>$contactId?:null,
     'dossier_id'=>$dossierId,
     'source_table'=>'scout_intel_dossiers',
     'source_id'=>$dossierId,
     'owner_name'=>$name,
     'property_address'=>$address,
     'town'=>$town,
     'lead_type'=>$leadType,
     'campaign'=>$campaign,
     'recommended_blog'=>$blog,
     'relationship_stage'=>'new',
     'priority'=>$priority,
     'status'=>'drafted',
     'due_at'=>date('Y-m-d H:i:s',time()+3600),
     'metadata'=>j962_json(['phone'=>$phone,'email'=>$email,'completion_score'=>$r['completion_score']??0,'call_strategy'=>$r['call_strategy']??'']),
     'created_at'=>gdb_now(),
     'updated_at'=>gdb_now()
   ]);

   $draftId=null;
   if($email){
     $subject=j962_subject($campaign,$town);
     $html=j962_body($name,$campaign,$blog,$town,$address);
     $text=j962_text($name,$campaign,$blog,$town,$address);
     $draftId=j962_insert('jessica_email_drafts',[
       'draft_uid'=>j962_uid('jdraft'),
       'queue_id'=>$queueId,
       'contact_id'=>$contactId?:null,
       'dossier_id'=>$dossierId,
       'to_email'=>$email,
       'to_name'=>$name,
       'subject'=>$subject,
       'body_html'=>$html,
       'body_text'=>$text,
       'recommended_blog'=>$blog,
       'status'=>'pending_approval',
       'created_at'=>gdb_now(),
       'updated_at'=>gdb_now()
     ]);
     j962_timeline('jessica','email_drafted','Jessica drafted first-touch email','Draft #'.$draftId.' created for '.$name, ['queue_id'=>$queueId,'draft_id'=>$draftId,'campaign'=>$campaign,'blog'=>$blog],$contactId,$dossierId);
   } else {
     j962_timeline('jessica','phone_only_lead','Jessica queued phone-only follow-up','No email found yet. Mark call reminder prepared for '.$name, ['queue_id'=>$queueId,'campaign'=>$campaign,'blog'=>$blog],$contactId,$dossierId);
   }

   j962_update('scout_intel_dossiers',$dossierId,['jessica_status'=>$email?'drafted':'phone_only','jessica_queue_id'=>$queueId,'relationship_stage'=>'new','updated_at'=>gdb_now()]);
   if($contactId) j962_update('internal_crm_contacts',$contactId,['relationship_stage'=>'new','updated_at'=>gdb_now()]);
   $created[]=['queue_id'=>$queueId,'draft_id'=>$draftId,'dossier_id'=>$dossierId,'name'=>$name,'email'=>$email,'phone'=>$phone,'campaign'=>$campaign,'blog'=>$blog];
 }

 echo json_encode(['ok'=>true,'version'=>'V96.2 Jessica Relationship Engine','created_count'=>count($created),'created'=>$created,'skipped'=>$skipped,'next'=>'Open /dashboard/jessica-relationship-center.php to review drafts.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V96.2 Jessica Relationship Engine','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>