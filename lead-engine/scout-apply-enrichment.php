<?php
/**
 * V93.2.7 Scout Apply Enrichment
 * Robust parser for local-ai-task-update wrappers:
 * - result may be raw JSON
 * - result may be {"output":"{...}"}
 * - result may be text containing JSON
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function col927($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function upd927($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col927($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
  function v927($a,$k){return isset($a[$k])?trim((string)$a[$k]):'';}
  function extract_json927($s){
    $s=(string)$s;
    $a=json_decode($s,true);
    if(is_array($a)){
      if(isset($a['output']) && is_string($a['output'])){
        $b=extract_json927($a['output']);
        if(is_array($b)) return $b;
      }
      if(isset($a['result']) && is_string($a['result'])){
        $b=extract_json927($a['result']);
        if(is_array($b)) return $b;
      }
      return $a;
    }
    if(preg_match('/```json\s*(\{.*?\})\s*```/s',$s,$m)){
      $a=json_decode($m[1],true); if(is_array($a)) return $a;
    }
    if(preg_match('/```\s*(\{.*?\})\s*```/s',$s,$m)){
      $a=json_decode($m[1],true); if(is_array($a)) return $a;
    }
    $start=strpos($s,'{'); $end=strrpos($s,'}');
    if($start!==false && $end!==false && $end>$start){
      $sub=substr($s,$start,$end-$start+1);
      $a=json_decode($sub,true); if(is_array($a)) return $a;
    }
    return null;
  }

  $limit=max(1,min(200,(int)($_GET['limit']??100)));
  $tasks=gdb_all("SELECT * FROM local_ai_tasks WHERE LOWER(agent)='scout' AND task_type IN ('scout_contact_enrichment','scout_contact_enrichment_v2') AND status='completed' ORDER BY completed_at DESC,id ASC LIMIT {$limit}")?:[];

  $applied=[]; $guarded=[];
  foreach($tasks as $t){
    $meta=json_decode($t['metadata']??'',true); if(!is_array($meta))$meta=[];
    $res=extract_json927($t['result']??'');
    if(!is_array($res)) $res=extract_json927($t['output']??'');

    $dossierId=(int)($res['dossier_id']??($meta['dossier_id']??0));
    $contactId=(int)($res['contact_id']??($meta['contact_id']??0));
    $phone1=$res?v927($res,'phone_1'):''; $phone2=$res?v927($res,'phone_2'):'';
    $email1=$res?strtolower(v927($res,'email_1')):''; $email2=$res?strtolower(v927($res,'email_2')):'';
    $status=$res?strtolower(v927($res,'status')):'missing_json';
    $evidence=$res?v927($res,'source_evidence'):'';
    $notes=$res?v927($res,'research_notes'):'';
    $searchUrls=$res['search_urls']??($meta['search_urls']??[]);
    $hasReal=($phone1||$phone2||$email1||$email2);

    if(!$hasReal){
      if($dossierId){
        upd927('scout_intel_dossiers',$dossierId,[
          'research_status'=>'needs_external_search',
          'handoff_status'=>'not_ready',
          'next_action'=>'Needs external search/API contact enrichment. Local worker returned no phone/email.',
          'public_notes'=>trim($notes."\n\nSearch URLs:\n".(is_array($searchUrls)?implode("\n",$searchUrls):'')),
          'evidence_log'=>trim($evidence ?: 'No phone/email found by local worker.'),
          'updated_at'=>gdb_now()
        ]);
      }
      $guarded[]=['task_id'=>(int)$t['id'],'dossier_id'=>$dossierId,'status'=>$status,'reason'=>'no_real_contact_found'];
      continue;
    }

    $phoneConf=(int)($res['phone_confidence']??($phone1||$phone2?70:0));
    $emailConf=(int)($res['email_confidence']??($email1||$email2?70:0));
    $bestPhone=$phone1 ?: $phone2; $bestEmail=$email1 ?: $email2;

    if($contactId){
      upd927('internal_crm_contacts',$contactId,[
        'phone_1'=>$phone1,'phone_2'=>$phone2,'best_phone'=>$bestPhone,
        'email_1'=>$email1,'email_2'=>$email2,'best_email'=>$bestEmail,
        'phone_confidence'=>$phoneConf,'email_confidence'=>$emailConf,
        'contact_source'=>'local_worker_search','contact_source_url'=>(is_array($searchUrls)&&count($searchUrls)?$searchUrls[0]:''),
        'contact_verified_at'=>gdb_now(),'contact_enrichment_status'=>'candidate_found','contact_enrichment_notes'=>$notes,
        'research_status'=>'ready_for_mark','evidence'=>$evidence,'notes'=>$notes,'last_researched_at'=>gdb_now(),'updated_at'=>gdb_now()
      ]);
    }
    if($dossierId){
      upd927('scout_intel_dossiers',$dossierId,[
        'phone_1'=>$phone1,'phone_2'=>$phone2,'best_phone'=>$bestPhone,'phone'=>trim(implode(' | ',array_filter([$phone1,$phone2]))),
        'email_1'=>$email1,'email_2'=>$email2,'best_email'=>$bestEmail,'email'=>trim(implode(' | ',array_filter([$email1,$email2]))),
        'contact_source'=>'local_worker_search','contact_source_url'=>(is_array($searchUrls)&&count($searchUrls)?$searchUrls[0]:''),
        'contact_verified_at'=>gdb_now(),'contact_confidence'=>max($phoneConf,$emailConf),'confidence_score'=>85,
        'research_status'=>'ready_for_mark','handoff_status'=>'ready_for_mark',
        'next_action'=>'Ready for Mark: candidate contact route found by local worker. Verify before outreach.',
        'evidence_log'=>$evidence,'public_notes'=>$notes,'completed_at'=>gdb_now(),'updated_at'=>gdb_now()
      ]);
    }
    $applied[]=['task_id'=>(int)$t['id'],'dossier_id'=>$dossierId,'contact_id'=>$contactId,'phone'=>$phone1,'email'=>$email1,'status'=>$status];
  }
  echo json_encode(['ok'=>true,'version'=>'V93.2.7 Scout Apply Enrichment','applied_count'=>count($applied),'guarded_count'=>count($guarded),'applied'=>$applied,'guarded'=>$guarded,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V93.2.7 Scout Apply Enrichment','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>