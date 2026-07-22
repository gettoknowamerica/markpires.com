<?php
/**
 * V95.7.1 Scout Dossier Completer — schema-safe
 * Fixes missing c.raw_json and any other optional CSV/context columns.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  if(file_exists(__DIR__.'/goliath-normalize.php')) require_once __DIR__.'/goliath-normalize.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function t9571($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function c9571($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function sel9571($table,$alias,$col,$as){return c9571($table,$col) ? "$alias.`$col` AS `$as`" : "NULL AS `$as`";}
  function j9571($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function u9571($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(c9571($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
  function norm9571($v,$type='text'){return function_exists('goliath_fix_first_letter')?goliath_fix_first_letter($v,$type):(string)$v;}
  function first9571($arr,$keys){foreach($keys as $k){if(is_array($arr)&&isset($arr[$k])&&trim((string)$arr[$k])!=='')return trim((string)$arr[$k]);}return '';}
  function rawmerge9571($row){
    $out=[];
    foreach(['raw_data','raw_json','metadata','c_raw_data','c_raw_json','browser_result_json'] as $k){
      if(!empty($row[$k])){
        $x=json_decode($row[$k],true);
        if(is_array($x)) $out=array_merge($out,$x);
      }
    }
    if(!empty($out['raw_json']) && is_string($out['raw_json'])){
      $x=json_decode($out['raw_json'],true);
      if(is_array($x)) $out=array_merge($out,$x);
    }
    return $out;
  }
  function infer9571($source,$raw){
    $blob=strtolower($source.' '.json_encode($raw));
    if(strpos($blob,'expired')!==false) return 'expired';
    if(strpos($blob,'withdrawn')!==false) return 'withdrawn';
    if(strpos($blob,'absentee')!==false || strpos($blob,'mailing')!==false) return 'absentee_owner';
    if(strpos($blob,'probate')!==false || strpos($blob,'estate')!==false) return 'probate';
    if(strpos($blob,'fsbo')!==false) return 'fsbo';
    if(strpos($blob,'luxury')!==false) return 'luxury';
    if(strpos($blob,'buyer')!==false) return 'buyer';
    if(strpos($blob,'seller')!==false) return 'seller';
    return 'general_owner';
  }
  function blog9571($town,$type){
    $town=norm9571($town);
    $slug=function_exists('goliath_town_slug')?goliath_town_slug($town):strtolower(trim(preg_replace('/[^a-z0-9]+/','-',$town),'-'));
    $slug=$slug?:'fairfield-county';
    if($type==='expired'||$type==='withdrawn') return '/blog/my-house-expired-what-to-do-next.html';
    if($type==='absentee_owner') return '/blog/selling-an-absentee-owned-home-in-connecticut.html';
    if($type==='buyer') return '/blog/top-5-things-buyers-should-do-before-touring-homes.html';
    if($type==='luxury') return '/blog/connecticut-luxury-home-buying-guide.html';
    return '/blog/'.$slug.'-home-selling-guide.html';
  }

  $limit=max(1,min(500,(int)($_GET['limit']??200)));

  $select=[
    "d.*",
    sel9571('internal_crm_contacts','c','raw_data','c_raw_data'),
    sel9571('internal_crm_contacts','c','raw_json','c_raw_json'),
    sel9571('internal_crm_contacts','c','source_file','c_source_file'),
    sel9571('internal_crm_contacts','c','source_id','c_source_id'),
    sel9571('internal_crm_contacts','c','source_row','c_source_row'),
    sel9571('internal_crm_contacts','c','owner_name','c_owner'),
    sel9571('internal_crm_contacts','c','property_address','c_address'),
    sel9571('internal_crm_contacts','c','mailing_address','c_mailing'),
    sel9571('internal_crm_contacts','c','town','c_town'),
    sel9571('internal_crm_contacts','c','state','c_state'),
    sel9571('internal_crm_contacts','c','zip','c_zip'),
    sel9571('internal_crm_contacts','c','best_phone','c_best_phone'),
    sel9571('internal_crm_contacts','c','phone_1','c_phone_1'),
    sel9571('internal_crm_contacts','c','phone_2','c_phone_2'),
    sel9571('internal_crm_contacts','c','best_email','c_best_email'),
    sel9571('internal_crm_contacts','c','email_1','c_email_1'),
    sel9571('internal_crm_contacts','c','email_2','c_email_2'),
    sel9571('internal_crm_contacts','c','notes','c_notes'),
    sel9571('internal_crm_contacts','c','evidence','c_evidence'),
    sel9571('internal_crm_contacts','c','contact_source_url','c_source_url'),
    sel9571('goliath_browser_jobs','b','result_json','browser_result_json'),
    sel9571('goliath_browser_jobs','b','evidence','browser_evidence'),
    sel9571('goliath_browser_jobs','b','completed_at','browser_completed_at')
  ];

  $rows=gdb_all("SELECT ".implode(",\n",$select)."
    FROM scout_intel_dossiers d
    LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
    LEFT JOIN goliath_browser_jobs b ON b.executive_key='scout'
      AND b.job_type='contact_enrichment'
      AND b.status='complete'
      AND b.prompt LIKE CONCAT('%Dossier ID: ',d.id,'%')
    WHERE d.handoff_status='ready_for_mark'
       OR COALESCE(d.best_phone,'')<>'' OR COALESCE(d.phone_1,'')<>'' OR COALESCE(d.phone,'')<>''
       OR COALESCE(d.best_email,'')<>'' OR COALESCE(d.email_1,'')<>'' OR COALESCE(d.email,'')<>''
       OR COALESCE(c.best_phone,'')<>'' OR COALESCE(c.phone_1,'')<>'' OR COALESCE(c.best_email,'')<>'' OR COALESCE(c.email_1,'')<>''
       OR COALESCE(d.research_status,'') IN ('queued_for_browser_intelligence','needs_external_search')
    ORDER BY COALESCE(d.completed_at,d.updated_at,d.created_at) DESC,d.id DESC
    LIMIT {$limit}")?:[];

  $done=[];
  foreach($rows as $r){
    $raw=rawmerge9571($r);
    $owner=$r['owner_name'] ?: ($r['c_owner']??'') ?: first9571($raw,['owner_name','OWNER_NAME','full_name','FULL_NAME','name','NAME','owner','OWNER']);
    $address=$r['property_address'] ?: ($r['c_address']??'') ?: first9571($raw,['property_address','PROPERTY_ADDRESS','address','ADDRESS','site_address','SITE_ADDRESS','listing_address','LISTING_ADDRESS']);
    $town=norm9571($r['town'] ?: ($r['c_town']??'') ?: first9571($raw,['town','TOWN','city','CITY','municipality','MUNICIPALITY']));
    $state=$r['state'] ?: ($r['c_state']??'CT') ?: 'CT';
    $zip=$r['zip'] ?: ($r['c_zip']??'') ?: first9571($raw,['zip','ZIP']);
    $mailing=$r['mailing_address'] ?: ($r['c_mailing']??'') ?: first9571($raw,['mailing_address','MAILING_ADDRESS','mail_address','MAIL_ADDRESS']);

    $sourceFile=$r['source_file'] ?: ($r['c_source_file']??'') ?: first9571($raw,['source_file','SOURCE_FILE','file','FILE','dataset_title','DATASET_TITLE']);
    $sourceLabel=$r['source_label'] ?: first9571($raw,['source_label','SOURCE_LABEL','list_name','LIST_NAME','dataset_type','DATASET_TYPE']);
    $leadType=infer9571($sourceLabel.' '.$sourceFile,$raw);

    $fields=[
      'last_status'=>first9571($raw,['last_status','LAST_STATUS','status','STATUS','listing_status','LISTING_STATUS','mls_status','MLS_STATUS']),
      'expired_date'=>first9571($raw,['expiration_date','EXPIRATION_DATE','expired_date','EXPIRED_DATE','off_market_date','OFF_MARKET_DATE']),
      'last_list_price'=>first9571($raw,['last_list_price','LAST_LIST_PRICE','list_price','LIST_PRICE','price','PRICE','LP']),
      'original_price'=>first9571($raw,['original_list_price','ORIGINAL_LIST_PRICE','original_price','ORIGINAL_PRICE','OLP']),
      'dom'=>first9571($raw,['days_on_market','DAYS_ON_MARKET','dom','DOM']),
      'mls'=>first9571($raw,['mls','MLS','mls_number','MLS_NUMBER','listing_id','LISTING_ID']),
      'broker'=>first9571($raw,['broker','BROKER','listing_broker','LISTING_BROKER','office_name','OFFICENAME','office','OFFICE']),
      'attempts'=>first9571($raw,['listing_attempts','LISTING_ATTEMPTS','attempts','ATTEMPTS','times_listed','TIMES_LISTED'])
    ];

    $phone=$r['best_phone'] ?: $r['phone_1'] ?: $r['phone'] ?: ($r['c_best_phone']??'') ?: ($r['c_phone_1']??'') ?: ($r['c_phone_2']??'');
    $email=$r['best_email'] ?: $r['email_1'] ?: $r['email'] ?: ($r['c_best_email']??'') ?: ($r['c_email_1']??'') ?: ($r['c_email_2']??'');

    $listing=["Lead type: ".$leadType];
    foreach($fields as $label=>$value){ if($value) $listing[]=ucwords(str_replace('_',' ',$label)).": ".$value; }
    if(count($listing)===1) $listing[]='No expired/listing fields found in current raw CSV mapping yet. Source context was still merged from CRM/source row.';

    $recommended=blog9571($town,$leadType);
    $call="Open with context: this came through as ".str_replace('_',' ',$leadType).". ";
    if($leadType==='expired'||$leadType==='withdrawn') $call.="Focus on relaunch strategy: pricing, presentation, buyer targeting, photos/video, and a stronger next-market plan.";
    elseif($leadType==='absentee_owner') $call.="Focus on convenience, remote selling, condition uncertainty, timing, and minimizing hassle.";
    else $call.="Use a soft value/check-in angle and offer a current valuation.";

    $summary=[
      'source_file'=>$sourceFile,'source_label'=>$sourceLabel,'source_id'=>$r['source_id']??($r['c_source_id']??''),'source_row'=>$r['source_row']??($r['c_source_row']??''),
      'lead_type'=>$leadType,'owner'=>$owner,'property_address'=>$address,'mailing_address'=>$mailing,'town'=>$town,'state'=>$state,'zip'=>$zip,
      'listing_fields'=>$fields,'phone'=>$phone,'email'=>$email
    ];

    u9571('scout_intel_dossiers',(int)$r['id'],[
      'owner_name'=>norm9571($owner),'property_address'=>norm9571($address),'mailing_address'=>norm9571($mailing),'town'=>$town,'state'=>$state,'zip'=>$zip,
      'source_label'=>$sourceLabel ?: $leadType,
      'listing_history'=>implode("\n",$listing),
      'nearby_sales'=>($r['nearby_sales']?:'Pending Holmes/Sherlock/Einstein market comp enrichment.'),
      'public_notes'=>'V95.7.1 merged internal CSV/CRM context with OpenClaw contact enrichment. Source: '.($sourceFile?:'internal CRM').'.',
      'call_strategy'=>$call,
      'recommended_blog'=>$recommended,
      'next_action'=>($phone||$email)?'Call-ready: contact + internal source context merged.':'Context merged; still needs contact enrichment.',
      'raw_json'=>j9571(['file_summary'=>$summary,'raw_internal'=>$raw,'v95_7_1'=>'schema_safe']),
      'evidence_log'=>trim(($r['evidence_log']??'')."\n".($r['browser_evidence']??'')."\n".($r['c_evidence']??'')."\nInternal source file: ".$sourceFile),
      'research_status'=>($phone||$email)?'ready_for_mark':'needs_contact_research',
      'handoff_status'=>($phone||$email)?'ready_for_mark':'not_ready',
      'confidence_score'=>($phone||$email)?92:65,
      'updated_at'=>gdb_now()
    ]);

    if(!empty($r['contact_id'])){
      u9571('internal_crm_contacts',(int)$r['contact_id'],[
        'owner_name'=>norm9571($owner),'property_address'=>norm9571($address),'mailing_address'=>norm9571($mailing),'town'=>$town,'state'=>$state,'zip'=>$zip,
        'contact_status'=>($phone||$email)?'ready_for_mark':'needs_contact_research',
        'research_status'=>($phone||$email)?'ready_for_mark':'needs_contact_research',
        'notes'=>trim(($r['c_notes']??'')."\n\n[V95.7.1 Scout Merge]\n".implode("\n",$listing)),
        'updated_at'=>gdb_now()
      ]);
    }

    $done[]=['dossier_id'=>(int)$r['id'],'owner'=>$owner,'town'=>$town,'lead_type'=>$leadType,'recommended_blog'=>$recommended,'phone_found'=>(bool)$phone,'source_file'=>$sourceFile];
  }

  echo json_encode(['ok'=>true,'version'=>'V95.7.1 Scout Dossier Completer Schema-Safe','completed_count'=>count($done),'completed'=>$done,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V95.7.1 Scout Dossier Completer Schema-Safe','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>