<?php
/**
 * V21.3.2 Full Paged Consolidation
 * Fixes Supabase 1000-row API cap by paging through all failed_never_sold records.
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

function api($method,$ep,$body=null,$range=null){
  $headers=[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  if($range) $headers[]='Range: '.$range;
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>180]);
  if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
  $bodytxt=substr($raw,$hs); $data=json_decode($bodytxt,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$bodytxt,'data'=>is_array($data)?$data:[]];
}
function clean($s){
  $s=strtolower(trim((string)$s));
  $s=preg_replace('/\b(road|rd|street|st|avenue|ave|lane|ln|drive|dr|court|ct|place|pl|circle|cir|boulevard|blvd|terrace|ter|way|parkway|pkwy|highway|hwy)\b/','',$s);
  return preg_replace('/[^a-z0-9]+/','',$s);
}
function k($r){return clean($r['address']??'').'|'.clean($r['town']??'');}
function money($v){return (float)preg_replace('/[^0-9.]/','',(string)$v);}
function val($a,$keys){foreach($keys as $x){if(isset($a[$x])&&trim((string)$a[$x])!=='')return trim((string)$a[$x]);}return '';}
function dval($v){$t=strtotime((string)$v);return $t?date('Y-m-d',$t):null;}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  api('DELETE','jessica_opportunity_engine?opportunity_type=eq.goliath_unique_seller');

  $groups=[]; $totalRaw=0; $pageSize=1000; $offset=0;
  while(true){
    $range=$offset.'-'.($offset+$pageSize-1);
    $rows=api('GET','jessica_opportunity_engine?select=*&opportunity_type=eq.failed_never_sold&order=revenue_score.desc,created_at.desc',null,$range)['data'];
    if(!$rows) break;
    $totalRaw+=count($rows);

    foreach($rows as $r){
      $keyrow=k($r); if(strlen($keyrow)<4) continue;
      if(!isset($groups[$keyrow])){
        $groups[$keyrow]=[
          'address'=>$r['address']??'','town'=>$r['town']??'','max_score'=>(int)($r['revenue_score']??0),
          'attempts'=>0,'ids'=>[],'last_seen'=>null,'last_price'=>0,'highest_price'=>0,'owner_name'=>''
        ];
      }
      $g=&$groups[$keyrow];
      $g['attempts']++;
      $g['ids'][]=(string)($r['source_id']??$r['id']??'');
      $g['max_score']=max($g['max_score'],(int)($r['revenue_score']??0));

      // Parse quick details from why_now if present.
      $why=$r['why_now']??'';
      if(!$g['owner_name'] && preg_match('/Owner:\s*([^\.]+)/i',$why,$m)) $g['owner_name']=trim($m[1]);
      if(!$g['last_seen'] && preg_match('/failed activity:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i',$why,$m)) $g['last_seen']=$m[1];
      if(preg_match('/Last known list price:\s*\$([0-9,]+)/i',$why,$m)){
        $p=money($m[1]); if($p>0){$g['last_price']=$p; $g['highest_price']=max($g['highest_price'],$p);}
      }

      // Try raw failed record enrichment when source_id is available.
      $sid=$r['source_id']??'';
      if($sid){
        $raw=api('GET','mls_failed_records?select=*&id=eq.'.rawurlencode($sid).'&limit=1')['data'];
        if($raw){
          $fr=$raw[0];
          $owner=val($fr,['owner_name','Owner Name','owner','Owner']);
          if($owner) $g['owner_name']=$owner;
          $date=dval(val($fr,['expiration_date','expired_date','list_date','created_at','updated_at']));
          if($date && (!$g['last_seen']||$date>$g['last_seen'])) $g['last_seen']=$date;
          $price=money(val($fr,['list_price','price','List Price','Price','original_price']));
          if($price>0){$g['last_price']=$price;$g['highest_price']=max($g['highest_price'],$price);}
        }
      }
      unset($g);
    }

    if(count($rows)<$pageSize) break;
    $offset+=$pageSize;
  }

  $payload=[];
  foreach($groups as $g){
    $attemptBonus=min(20,max(0,($g['attempts']-1)*5));
    $recencyBonus=0;
    if($g['last_seen']){
      $days=floor((time()-strtotime($g['last_seen']))/86400);
      if($days<=90)$recencyBonus=15; elseif($days<=180)$recencyBonus=12; elseif($days<=365)$recencyBonus=9; elseif($days<=730)$recencyBonus=5;
    }
    $luxuryBonus=$g['highest_price']>=2000000?10:($g['highest_price']>=1000000?6:0);
    $score=min(100,(int)$g['max_score']+$attemptBonus+$recencyBonus+$luxuryBonus);
    $why='Unique unresolved failed-sale property. Attempts found: '.$g['attempts'].'.';
    if($g['last_seen']) $why.=' Last known failed activity: '.$g['last_seen'].'.';
    if($g['owner_name']) $why.=' Owner: '.$g['owner_name'].'.';
    if($g['last_price']>0) $why.=' Last known list price: $'.number_format($g['last_price']).'.';
    if($g['highest_price']>0) $why.=' Highest known list price: $'.number_format($g['highest_price']).'.';

    $payload[]=[
      'opportunity_date'=>date('Y-m-d'),'opportunity_type'=>'goliath_unique_seller','title'=>'Goliath Seller Opportunity: '.$g['address'],
      'source_table'=>'jessica_opportunity_engine','source_id'=>implode(',',array_slice($g['ids'],0,10)),
      'town'=>$g['town'],'address'=>$g['address'],'revenue_score'=>$score,'confidence_score'=>97,'urgency_score'=>min(100,75+$recencyBonus+$attemptBonus),
      'why_now'=>$why,'recommended_action'=>'Verify current MLS status and owner details. If still unsold/off-market, move to Mark review and prepare a value-first seller strategy.',
      'content_angle'=>'Seller education: failed listing recovery, pricing reset, and marketing relaunch strategy.',
      'ad_angle'=>'Did your home fail to sell? Get a fresh local strategy review before relisting.',
      'followup_angle'=>'Create outreach list by score, recency, town, price, bedrooms, and square footage.',
      'compliance_note'=>'MLS intelligence only. Mark performs human review before outreach.','status'=>'new','created_at'=>date('c'),'updated_at'=>date('c')
    ];
  }
  usort($payload,function($a,$b){return ($b['revenue_score']<=>$a['revenue_score']) ?: strcmp($a['address'],$b['address']);});

  $created=0;
  foreach(array_chunk($payload,200) as $chunk){
    $r=api('POST','jessica_opportunity_engine',$chunk);
    if($r['ok']) $created+=count($chunk);
  }

  echo json_encode([
    'success'=>true,
    'raw_failed_never_sold_records'=>$totalRaw,
    'unique_property_opportunities'=>count($groups),
    'consolidated_created'=>$created,
    'duplicates_removed'=>max(0,$totalRaw-count($groups)),
    'top_preview'=>array_slice($payload,0,10)
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>