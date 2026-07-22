<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function api($m,$ep,$body=null,$range=null,$extra=[]){
 $headers=array_merge(['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],$extra);
 if($range)$headers[]='Range: '.$range;
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>120]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode($b,true);
 return['ok'=>$http>=200&&$http<300,'http'=>$http,'data'=>is_array($data)?$data:[],'body'=>$b];
}
function clean($s){$s=strtolower(trim((string)$s));$s=preg_replace('/\b(road|rd|street|st|avenue|ave|lane|ln|drive|dr|court|ct|place|pl|circle|cir|boulevard|blvd|terrace|ter|way|parkway|pkwy|highway|hwy)\b/','',$s);return preg_replace('/[^a-z0-9]+/','',$s);}
function keyrow($r){return clean($r['address']??'').'|'.clean($r['town']??'');}
function money($v){return(float)preg_replace('/[^0-9.]/','',(string)$v);}
function parse_why($why){
 $out=['owner'=>'','date'=>null,'price'=>0];
 if(preg_match('/Owner:\s*([^\.]+)/i',$why,$m))$out['owner']=trim($m[1]);
 if(preg_match('/failed activity:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i',$why,$m))$out['date']=$m[1];
 if(preg_match('/Last known list price:\s*\$([0-9,]+)/i',$why,$m))$out['price']=money($m[1]);
 return$out;
}
try{
 $key=$_GET['key']??'';if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
 $offset=max(0,(int)($_GET['offset']??0));$limit=max(1,min(1000,(int)($_GET['limit']??1000)));$range=$offset.'-'.($offset+$limit-1);
 $rows=api('GET','jessica_opportunity_engine?select=*&opportunity_type=eq.failed_never_sold&order=revenue_score.desc,created_at.desc',null,$range)['data'];
 $processed=0;$new=0;$updated=0;
 foreach($rows as $r){
  $mk=keyrow($r); if(strlen($mk)<4)continue; $processed++;
  $why=$r['why_now']??''; $p=parse_why($why); $score=(int)($r['revenue_score']??0); $sid=(string)($r['source_id']??$r['id']??'');
  $existing=api('GET','goliath_consolidation_work?select=*&match_key=eq.'.rawurlencode($mk).'&limit=1')['data'];
  if($existing){
    $e=$existing[0];
    $ids=trim(($e['source_ids']??'').','.$sid,',');
    $patch=[
      'max_score'=>max((int)($e['max_score']??0),$score),
      'attempts'=>(int)($e['attempts']??0)+1,
      'source_ids'=>$ids,
      'last_seen'=>($p['date'] && (empty($e['last_seen'])||$p['date']>$e['last_seen']))?$p['date']:$e['last_seen'],
      'owner_name'=>$p['owner']?:($e['owner_name']??''),
      'last_price'=>$p['price']?:($e['last_price']??0),
      'highest_price'=>max((float)($e['highest_price']??0),(float)$p['price']),
      'updated_at'=>date('c')
    ];
    api('PATCH','goliath_consolidation_work?match_key=eq.'.rawurlencode($mk),$patch); $updated++;
  } else {
    api('POST','goliath_consolidation_work',[[
      'match_key'=>$mk,'address'=>$r['address']??'','town'=>$r['town']??'','max_score'=>$score,'attempts'=>1,'source_ids'=>$sid,
      'last_seen'=>$p['date'],'owner_name'=>$p['owner'],'last_price'=>$p['price'],'highest_price'=>$p['price'],'created_at'=>date('c'),'updated_at'=>date('c')
    ]]); $new++;
  }
 }
 echo json_encode(['success'=>true,'offset'=>$offset,'limit'=>$limit,'processed'=>$processed,'new_unique'=>$new,'updated_existing'=>$updated,'next_offset'=>$offset+$limit,'has_more'=>count($rows)===$limit],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>