<?php
/**
 * V20.1 Municipal Owner Intelligence
 * Upload: /public_html/dashboard/municipal-owner-intelligence.php
 */
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>60]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b,'http'=>$http];
}
function money($s){return (float)str_replace([',','$'],'',(string)$s);}
function norm_addr($a){$a=strtoupper(trim($a));$a=preg_replace('/\s+/',' ',$a);$a=str_replace([' RD',' LN',' CT',' AVE',' ST',' DR'],[' ROAD',' LANE',' COURT',' AVENUE',' STREET',' DRIVE'],$a);return $a;}
function is_addr($l){return preg_match('/^\d+\s+.+(ROAD|RD|LANE|LN|COURT|CT|AVENUE|AVE|STREET|ST|DRIVE|DR|CREEK)/i',$l);}
function parse_tax_text($txt,$town){
  preg_match_all('/^\d{4}-\d{2}-\d{7}\s*$/m',$txt,$m,PREG_OFFSET_CAPTURE);
  $records=[];
  for($i=0;$i<count($m[0]);$i++){
    $start=$m[0][$i][1]; $end=($i+1<count($m[0]))?$m[0][$i+1][1]:strlen($txt);
    $block=substr($txt,$start,$end-$start);
    if(stripos($block,'(REAL ESTATE')===false) continue;
    $bill=trim($m[0][$i][0]); $year=(int)substr($bill,0,4);
    $lines=[];
    foreach(preg_split('/\R/',str_replace("\t","\n",$block)) as $ln){$ln=trim($ln);if($ln!=='')$lines[]=$ln;}
    $idx=null; foreach($lines as $j=>$ln){if(stripos($ln,'(REAL ESTATE')!==false){$idx=$j;break;}}
    if($idx===null) continue;
    $after=[];
    for($j=$idx+1;$j<count($lines);$j++){
      $ln=$lines[$j];
      if(strlen($ln)>0 && $ln[0]==='$') break;
      if(preg_match('/^\d{1,4}\s+\d{1,4}\s*[A-Z]?$/',$ln)) continue;
      $after[]=$ln;
    }
    $addrIdx=null; foreach($after as $j=>$ln){if(is_addr($ln)){$addrIdx=$j;break;}}
    if($addrIdx===null) continue;
    $owner=trim(implode(' ',array_slice($after,0,$addrIdx)));
    $owner=preg_replace('/\s+/',' ',str_replace(['(SV)',' (SV)'],'',$owner));
    $address=norm_addr($after[$addrIdx]);
    preg_match_all('/\$[\d,]+\.\d{2}/',$block,$dol);
    $total=isset($dol[0][0])?money($dol[0][0]):0;
    $paid=isset($dol[0][1])?money($dol[0][1]):0;
    $out=isset($dol[0][2])?money($dol[0][2]):0;
    $records[]=['year'=>$year,'bill'=>$bill,'owner'=>$owner,'address'=>$address,'total_tax'=>$total,'paid'=>$paid,'outstanding'=>$out];
  }
  return $records;
}
function aggregate_records($records,$town,$source){
  $groups=[];
  foreach($records as $r){$groups[$r['address']][]=$r;}
  $out=[];
  foreach($groups as $addr=>$g){
    usort($g,function($a,$b){return $a['year']<=>$b['year'];});
    $latest=end($g); $owner=$latest['owner'];
    $years=[]; foreach($g as $r){if($r['owner']===$owner)$years[$r['year']]=1;}
    $yearsSeen=count($years); $firstCurrent=min(array_keys($years));
    $sameSince2019=isset($years[2019]) && $latest['year']>=2025;
    $ownerType=preg_match('/\bLLC\b|TRUST|TRUSTEE|ESTATE|CHURCH|CAPITAL|INC\b|CORP\b/i',$owner)?'entity/trust':'individual';
    $score=50 + ($sameSince2019?20:($yearsSeen>=3?8:0)) + ($latest['total_tax']>=30000?25:($latest['total_tax']>=20000?15:($latest['total_tax']>=15000?8:0))) + ($latest['outstanding']>0?8:0) - ($ownerType==='entity/trust'?5:0);
    $score=max(1,min(100,$score));
    $street=preg_replace('/^\d+\s+/','',$addr);
    $out[]=[
      'source_town'=>$town,
      'source_street'=>ucwords(strtolower($street)),
      'property_address'=>ucwords(strtolower($addr)),
      'property_address_key'=>$addr,
      'current_owner'=>ucwords(strtolower($owner)),
      'owner_type'=>$ownerType,
      'latest_tax_year'=>$latest['year'],
      'latest_total_tax'=>$latest['total_tax'],
      'latest_paid'=>$latest['paid'],
      'latest_outstanding'=>$latest['outstanding'],
      'first_year_seen'=>$g[0]['year'],
      'first_year_current_owner_seen'=>$firstCurrent,
      'years_current_owner_seen'=>$yearsSeen,
      'ownership_signal'=>$sameSince2019?'7+ year owner signal':($yearsSeen>=3?'multi-year owner':'recent/changed owner'),
      'owner_change_count_in_file'=>count(array_unique(array_column($g,'owner')))-1,
      'estimated_value_proxy'=>round($latest['total_tax']*1000,0),
      'seller_priority_score'=>$score,
      'recommended_action'=>'Research owner tenure/property value; DNC/realtor exclusion before outreach',
      'import_status'=>'ready_for_jessica_review',
      'source'=>$source,
      'notes'=>'Parsed from municipal tax portal text. Public-record source; no phone/email included.',
      'raw_payload'=>['records'=>$g],
      'updated_at'=>date('c')
    ];
  }
  usort($out,function($a,$b){return $b['seller_priority_score']<=>$a['seller_priority_score'];});
  return $out;
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $town=trim($_POST['source_town']??'Fairfield');
  $source=trim($_POST['source_description']??'Municipal tax portal paste');
  $raw=trim($_POST['raw_text']??'');
  if(!empty($_FILES['raw_file']['tmp_name'])) $raw=file_get_contents($_FILES['raw_file']['tmp_name']);
  if($raw){
    $parsed=parse_tax_text($raw,$town);
    $agg=aggregate_records($parsed,$town,$source);
    $inserted=0;$updated=0;$errors=[];
    foreach($agg as $row){
      $existing=sb('GET','municipal_owner_imports?select=id&source_town=eq.'.rawurlencode($town).'&property_address_key=eq.'.rawurlencode($row['property_address_key']).'&limit=1');
      if(!empty($existing['data'])){
        $id=$existing['data'][0]['id'];
        $r=sb('PATCH','municipal_owner_imports?id=eq.'.rawurlencode($id),$row);
        if($r['ok'])$updated++;else$errors[]=$r['body'];
      }else{
        $r=sb('POST','municipal_owner_imports',[$row]);
        if($r['ok'])$inserted++;else$errors[]=$r['body'];
      }
    }
    sb('POST','municipal_import_batches',[[
      'batch_name'=>$town.' '.date('Y-m-d H:i'),
      'source_town'=>$town,
      'source_description'=>$source,
      'raw_file_name'=>$_FILES['raw_file']['name']??'paste',
      'records_seen'=>count($agg),
      'records_inserted'=>$inserted,
      'records_updated'=>$updated,
      'duplicates_skipped'=>max(0,count($agg)-$inserted-$updated),
      'status'=>empty($errors)?'completed':'completed_with_errors',
      'created_at'=>date('c')
    ]]);
    $msg='Parsed '.count($parsed).' tax records into '.count($agg).' unique property records. Inserted '.$inserted.', updated '.$updated.'.';
    if($errors)$msg.=' Errors: '.implode(' | ',array_slice($errors,0,3));
  }else $msg='Paste text or upload a raw .txt file.';
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=sb('GET','municipal_owner_imports?select=*&order=seller_priority_score.desc,latest_total_tax.desc&limit=300')['data'];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Municipal Owner Intelligence</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:linear-gradient(135deg,#111827,#0b1020);color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:42px;margin:0}.wrap{max-width:1900px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;box-shadow:0 3px 16px #0001;margin:18px 0;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:16px;border-bottom:1px solid #eee}.inner{padding:16px}.btn{border:0;background:#c8a96e;color:#111;padding:10px 13px;border-radius:9px;font-weight:900;text-decoration:none;display:inline-block;cursor:pointer}input,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ddd;border-radius:10px;margin:6px 0 12px}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #eee;text-align:left;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}.score{font-size:28px;font-weight:900;color:#c8a96e}.muted{color:#777;font-size:12px}.notice{background:#fff8e8;border-left:5px solid #c8a96e;padding:12px;border-radius:10px}</style></head><body>
<section class="hero"><h1>V20.1 Municipal Owner Intelligence</h1><div>Paste/export town tax portal data. Jessica dedupes, scores, and creates a compliant research list.</div></section>
<main class="wrap"><p><a class="btn" target="_blank" href="/lead-engine/build-municipal-owner-intelligence.php?key=<?=h($key)?>">Run Scoring Builder</a> <a class="btn" href="/commandcenter.php">Goliath OS</a></p>
<?php if($msg):?><div class="panel"><div class="inner notice"><?=h($msg)?></div></div><?php endif;?>
<section class="panel"><h2>Import Tax Portal Paste / TXT</h2><div class="inner"><form method="post" enctype="multipart/form-data"><label>Town</label><input name="source_town" value="Fairfield"><label>Source Description</label><input name="source_description" value="Fairfield MyTax portal paste"><label>Upload raw TXT</label><input type="file" name="raw_file" accept=".txt,.csv"><label>Or paste raw portal text</label><textarea name="raw_text" rows="10" placeholder="Paste municipal tax portal results here..."></textarea><button class="btn">Import + Dedupe</button></form><p class="muted">Duplicate rule: same town + normalized property address updates the existing record instead of adding another duplicate.</p></div></section>
<section class="panel"><h2>Priority Owner Research List</h2><table><tr><th>Score</th><th>Property</th><th>Owner</th><th>Signals</th><th>Action</th></tr><?php foreach($rows as $r):?><tr><td><div class="score"><?=h($r['seller_priority_score'])?></div></td><td><strong><?=h($r['property_address'])?></strong><div class="muted"><?=h($r['source_town'])?> / <?=h($r['source_street'])?></div></td><td><?=h($r['current_owner'])?><div class="muted"><?=h($r['owner_type'])?></div></td><td>Tax: $<?=number_format((float)$r['latest_total_tax'])?><br>Outstanding: $<?=number_format((float)$r['latest_outstanding'])?><br><?=h($r['ownership_signal'])?><br>Current owner years seen: <?=h($r['years_current_owner_seen'])?></td><td><?=h($r['recommended_action'])?><div class="muted"><?=h($r['import_status'])?></div></td></tr><?php endforeach;?></table></section>
</main></body></html>