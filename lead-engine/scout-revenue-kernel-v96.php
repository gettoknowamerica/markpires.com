<?php
/**
 * V96.1 Scout Revenue Kernel
 * Boot Scout under Constitution, read internal dossier context first, then queue OpenClaw only for missing contact/social fields.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  require_once __DIR__.'/executive-kernel-v96.php';
  if(file_exists(__DIR__.'/goliath-normalize.php')) require_once __DIR__.'/goliath-normalize.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function sr96_first($arr,$keys){foreach($keys as $k){if(is_array($arr)&&isset($arr[$k])&&trim((string)$arr[$k])!=='')return trim((string)$arr[$k]);}return '';}
  function sr96_raw($row){
    $out=[];
    foreach(['raw_data','raw_json','metadata','c_raw_data','c_raw_json'] as $k){
      if(!empty($row[$k])){
        $x=json_decode($row[$k],true);
        if(is_array($x)) $out=array_merge($out,$x);
      }
    }
    if(!empty($out['raw_json'])&&is_string($out['raw_json'])){
      $x=json_decode($out['raw_json'],true);
      if(is_array($x)) $out=array_merge($out,$x);
    }
    return $out;
  }
  function sr96_score($row,$raw){
    $score=0;$checks=[];
    $phone=($row['best_phone']??'')?:($row['phone_1']??'')?:($row['phone']??'')?:($row['c_best_phone']??'')?:($row['c_phone_1']??'');
    $email=($row['best_email']??'')?:($row['email_1']??'')?:($row['email']??'')?:($row['c_best_email']??'')?:($row['c_email_1']??'');
    $address=($row['property_address']??'')?:($row['c_address']??'')?:sr96_first($raw,['property_address','ADDRESS','SITE_ADDRESS']);
    $listing=($row['listing_history']??'')?:sr96_first($raw,['STATUS','listing_status','expiration_date','DOM','LIST_PRICE']);
    $social=($row['social_profiles']??'');
    $source=($row['source_file']??'')?:($row['c_source_file']??'');
    if($phone){$score+=20;$checks[]='phone';}
    if($email){$score+=10;$checks[]='email';}
    if($address){$score+=15;$checks[]='property';}
    if($listing){$score+=20;$checks[]='listing/internal context';}
    if($social){$score+=10;$checks[]='social profiles';}
    if($source){$score+=10;$checks[]='source file';}
    if(($row['call_strategy']??'')){$score+=10;$checks[]='call strategy';}
    if(($row['recommended_blog']??'')){$score+=5;$checks[]='recommended blog';}
    return [min(100,$score),$checks];
  }
  function sr96_missing($row,$raw){
    $missing=[];
    $phone=($row['best_phone']??'')?:($row['phone_1']??'')?:($row['phone']??'')?:($row['c_best_phone']??'')?:($row['c_phone_1']??'');
    $email=($row['best_email']??'')?:($row['email_1']??'')?:($row['email']??'')?:($row['c_best_email']??'')?:($row['c_email_1']??'');
    if(!$phone) $missing[]='phone';
    if(!$email) $missing[]='email';
    if(empty($row['social_profiles'])) $missing[]='social_profiles';
    if(empty($row['listing_history']) && !sr96_first($raw,['STATUS','listing_status','expiration_date','DOM','LIST_PRICE'])) $missing[]='listing_history';
    if(empty($row['nearby_sales'])) $missing[]='nearby_sales';
    return $missing;
  }

  $limit=max(1,min(100,(int)($_GET['limit']??25)));
  $boot=gx96_boot('scout',['mission_type'=>'revenue_kernel','title'=>'Build complete seller dossiers before OpenClaw and Jessica handoff']);

  $rows=gdb_all("SELECT d.*,
      c.raw_data c_raw_data,
      NULL c_raw_json,
      c.source_file c_source_file,
      c.owner_name c_owner,
      c.property_address c_address,
      c.town c_town,
      c.best_phone c_best_phone,
      c.phone_1 c_phone_1,
      c.best_email c_best_email,
      c.email_1 c_email_1
    FROM scout_intel_dossiers d
    LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
    WHERE COALESCE(d.handoff_status,'')<>'do_not_contact'
    ORDER BY
      CASE WHEN d.handoff_status='ready_for_mark' THEN 0 ELSE 1 END,
      COALESCE(d.completed_at,d.updated_at,d.created_at) DESC,
      d.id DESC
    LIMIT {$limit}")?:[];

  $processed=[];$queuedBrowser=[];$readyJessica=[];

  foreach($rows as $r){
    $raw=sr96_raw($r);
    [$score,$checks]=sr96_score($r,$raw);
    $missing=sr96_missing($r,$raw);
    $phone=($r['best_phone']??'')?:($r['phone_1']??'')?:($r['phone']??'')?:($r['c_best_phone']??'')?:($r['c_phone_1']??'');
    $email=($r['best_email']??'')?:($r['email_1']??'')?:($r['email']??'')?:($r['c_best_email']??'')?:($r['c_email_1']??'');

    gx96_update('scout_intel_dossiers',(int)$r['id'],[
      'boot_hash'=>$boot['boot_hash'],
      'completion_score'=>$score,
      'next_action'=>($phone||$email)?'Revenue-ready dossier: Scout booted, internal file merged, contact found. Queue Jessica.':'Scout booted and internal file checked. Missing '.implode(', ',$missing).'. Queue OpenClaw only for missing fields.',
      'updated_at'=>gdb_now()
    ]);

    gx96_timeline('scout','dossier_scored','Scout scored dossier #'.$r['id'],'Completion '.$score.'%; checks: '.implode(', ',$checks).'; missing: '.implode(', ',$missing),[
      'dossier_id'=>(int)$r['id'],
      'completion_score'=>$score,
      'missing'=>$missing,
      'boot_hash'=>$boot['boot_hash']
    ]);

    if(($phone||$email) && $score>=55){
      $readyJessica[]=(int)$r['id'];
      // queue Jessica draft/follow-up as executive_followups when table exists
      if(gx96_table('executive_followups')){
        $exists=gdb_one("SELECT id FROM executive_followups WHERE executive_key='jessica' AND contact_id=? AND campaign='scout_ready_intro' AND status IN ('queued','drafted','pending_approval') LIMIT 1",[(int)($r['contact_id']??0)]);
        if(!$exists && !empty($r['contact_id'])){
          gx96_insert('executive_followups',[
            'followup_uid'=>gx96_uid('follow'),
            'contact_id'=>(int)$r['contact_id'],
            'executive_key'=>'jessica',
            'status'=>'queued',
            'campaign'=>'scout_ready_intro',
            'title'=>'Draft first-touch email for '.(($r['owner_name']??'')?:($r['c_owner']??'contact')),
            'message'=>'Scout marked this contact ready. Draft a short human email with the recommended blog and prepare Mark call reminder.',
            'due_at'=>date('Y-m-d H:i:s',time()+300),
            'metadata'=>gx96_json(['dossier_id'=>(int)$r['id'],'completion_score'=>$score,'recommended_blog'=>$r['recommended_blog']??'']),
            'created_at'=>gdb_now(),
            'updated_at'=>gdb_now()
          ]);
        }
      }
      gx96_timeline('jessica','queued_from_scout','Jessica queued for Scout-ready dossier','Dossier #'.$r['id'].' has contact data and needs first-touch marketing draft.',['dossier_id'=>(int)$r['id']]);
    } else {
      // Create browser job if missing contact/social and no queued/working job exists.
      if(gx96_table('goliath_browser_jobs') && array_intersect($missing,['phone','email','social_profiles'])){
        $exists=gdb_one("SELECT id FROM goliath_browser_jobs WHERE executive_key='scout' AND job_type='contact_enrichment' AND status IN ('queued','working') AND prompt LIKE CONCAT('%Dossier ID: ',?,'%') LIMIT 1",[(int)$r['id']]);
        if(!$exists){
          $owner=($r['owner_name']??'')?:($r['c_owner']??'');
          $address=($r['property_address']??'')?:($r['c_address']??'');
          $town=($r['town']??'')?:($r['c_town']??'');
          $prompt="Dossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nTown: {$town}\n\nScout already loaded internal context under Constitution. OpenClaw mission: find missing fields only: ".implode(', ',$missing).". Return phones, emails, Facebook, LinkedIn, Instagram, website, evidence URLs. Do not invent.";
          $jobId=gx96_insert('goliath_browser_jobs',[
            'job_uid'=>gx96_uid('gbj'),
            'executive_key'=>'scout',
            'job_type'=>'contact_enrichment',
            'target_name'=>$owner,
            'target_address'=>$address,
            'target_town'=>$town,
            'prompt'=>$prompt,
            'status'=>'queued',
            'progress'=>0,
            'current_step'=>'Queued by V96 Scout Revenue Kernel for missing fields only',
            'priority'=>750,
            'created_at'=>gdb_now(),
            'updated_at'=>gdb_now()
          ]);
          if($jobId) $queuedBrowser[]=['dossier_id'=>(int)$r['id'],'browser_job_id'=>$jobId,'missing'=>$missing];
        }
      }
    }

    $processed[]=['dossier_id'=>(int)$r['id'],'completion_score'=>$score,'missing'=>$missing,'ready_for_jessica'=>in_array((int)$r['id'],$readyJessica,true)];
  }

  echo json_encode([
    'ok'=>true,
    'version'=>'V96.1 Scout Revenue Kernel',
    'boot_hash'=>$boot['boot_hash'],
    'processed_count'=>count($processed),
    'processed'=>$processed,
    'queued_browser_count'=>count($queuedBrowser),
    'queued_browser'=>$queuedBrowser,
    'ready_for_jessica_count'=>count($readyJessica),
    'ready_for_jessica'=>$readyJessica,
    'next'=>'Keep OpenClaw bridge running. Run Jessica queue next when ready.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V96.1 Scout Revenue Kernel','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>