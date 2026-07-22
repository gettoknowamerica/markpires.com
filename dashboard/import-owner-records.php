<?php
/**
 * V13.1.1 Owner + Realtor Import Center
 * Upload: /public_html/dashboard/import-owner-records.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function clean_phone_imp($p){
  $p=preg_replace('/[^0-9]/','',(string)$p);
  if(strlen($p)===11 && substr($p,0,1)==='1') $p=substr($p,1);
  return $p;
}
function sb_imp($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>60
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}
function pick($row,$names,$default=''){
  foreach($names as $n){
    foreach($row as $k=>$v){
      if(strtolower(trim($k))===strtolower(trim($n))) return trim((string)$v);
    }
  }
  return $default;
}
function parse_csv_file($path){
  $rows=[];
  if(($h=fopen($path,'r'))!==false){
    $headers=fgetcsv($h);
    if(!$headers) return [];
    $headers=array_map(function($x){return trim((string)$x);},$headers);
    while(($data=fgetcsv($h))!==false){
      $row=[];
      foreach($headers as $i=>$k){ $row[$k]=$data[$i]??''; }
      $rows[]=$row;
    }
    fclose($h);
  }
  return $rows;
}

$msg='';
$result=null;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv_file'])){
  $type=$_POST['import_type']??'owner';
  $batchName=trim($_POST['batch_name']??('Import '.date('Y-m-d H:i')));
  $sourceName=trim($_POST['source_name']??'manual_csv');
  $townDefault=trim($_POST['town']??'');
  $market=trim($_POST['market']??'Lower Fairfield County');

  if(!is_uploaded_file($_FILES['csv_file']['tmp_name'])){
    $msg='No CSV uploaded.';
  } else {
    $rows=parse_csv_file($_FILES['csv_file']['tmp_name']);
    $created=0; $errors=[];

    if($type==='realtor'){
      $payload=[];
      foreach($rows as $r){
        $payload[]=[
          'name'=>pick($r,['name','agent name','realtor name','full name','owner_name']),
          'phone'=>clean_phone_imp(pick($r,['phone','mobile','cell','cell phone','phone number'])),
          'email'=>strtolower(pick($r,['email','email address'])),
          'company'=>pick($r,['company','brokerage','office']),
          'town'=>pick($r,['town','city'],$townDefault),
          'source'=>$sourceName,
          'status'=>'active',
          'raw_payload'=>$r,
          'created_at'=>date('c'),
          'updated_at'=>date('c')
        ];
      }
      foreach(array_chunk($payload,100) as $chunk){
        $res=sb_imp('POST','realtor_exclusion_list',$chunk);
        if($res['ok']) $created+=count($chunk); else $errors[]=$res['body'];
      }
      $result=['type'=>'realtor_exclusion_list','rows'=>count($rows),'created'=>$created,'errors'=>$errors];
    } else {
      $batch=[[
        'batch_name'=>$batchName,
        'source_name'=>$sourceName,
        'source_type'=>'csv_owner_data',
        'town'=>$townDefault,
        'market'=>$market,
        'total_rows'=>count($rows),
        'status'=>'imported',
        'notes'=>'Imported from dashboard CSV import center.',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $b=sb_imp('POST','contact_acquisition_batches',$batch);
      $batchId=$b['ok'] && !empty($b['data'][0]['id']) ? $b['data'][0]['id'] : null;

      $payload=[];
      foreach($rows as $r){
        $town=pick($r,['town','city','municipality'],$townDefault);
        $owner=pick($r,['owner_name','owner','name','full name','property owner','mailing name']);
        $phone=clean_phone_imp(pick($r,['phone','mobile','cell','cell phone','phone number','primary phone']));
        $email=strtolower(pick($r,['email','email address']));
        $address=pick($r,['property_address','address','site address','situs address','property address']);
        $mailing=pick($r,['mailing_address','mailing address','owner address']);
        $years=pick($r,['years_owned','years owned']);
        $value=pick($r,['estimated_value','estimated value','market value','assessed value','value']);
        $equity=pick($r,['estimated_equity','estimated equity','equity']);
        $sale=pick($r,['last_sale_price','sale price','last sale price']);
        $dnc=pick($r,['dnc_status','dnc status'],'unchecked');
        $consent=pick($r,['consent_status','consent status'],'unknown');
        $approval=pick($r,['approval_status','approval status'],'review');

        $payload[]=[
          'batch_id'=>$batchId,
          'source_table'=>'csv_import',
          'source_id'=>'',
          'source_name'=>$sourceName,
          'source_type'=>'csv_owner_data',
          'owner_name'=>$owner,
          'phone'=>$phone,
          'email'=>$email,
          'property_address'=>$address,
          'mailing_address'=>$mailing,
          'town'=>$town,
          'state'=>pick($r,['state'],'CT'),
          'market'=>$market,
          'property_type'=>pick($r,['property_type','property type','style']),
          'years_owned'=>is_numeric($years)?(float)$years:0,
          'last_sale_price'=>is_numeric($sale)?(float)$sale:0,
          'estimated_value'=>is_numeric($value)?(float)$value:0,
          'estimated_equity'=>is_numeric($equity)?(float)$equity:0,
          'owner_occupied'=>in_array(strtolower(pick($r,['owner_occupied','owner occupied'],'')),['yes','true','1','y']),
          'dnc_status'=>$dnc,
          'dnc_checked'=>$dnc!=='unchecked',
          'dnc_match'=>in_array($dnc,['blocked','listed','do_not_call'],true),
          'realtor_checked'=>false,
          'realtor_match'=>false,
          'consent_status'=>$consent,
          'approval_status'=>$approval,
          'approved_contact'=>false,
          'call_eligible'=>false,
          'sms_eligible'=>false,
          'email_eligible'=>false,
          'motivation'=>'CSV owner import pending Jessica acquisition scoring',
          'motivation_score'=>0,
          'contact_score'=>0,
          'priority_band'=>'C',
          'recommended_action'=>'Run V13.1 Contact Acquisition after import.',
          'next_step'=>'score_with_acquisition_engine',
          'raw_payload'=>$r,
          'status'=>'review',
          'created_at'=>date('c'),
          'updated_at'=>date('c')
        ];
      }
      foreach(array_chunk($payload,100) as $chunk){
        $res=sb_imp('POST','contact_acquisition_candidates',$chunk);
        if($res['ok']) $created+=count($chunk); else $errors[]=$res['body'];
      }
      if($batchId){
        sb_imp('PATCH','contact_acquisition_batches?id=eq.'.rawurlencode($batchId),['candidates_created'=>$created,'updated_at'=>date('c')]);
      }
      $result=['type'=>'contact_acquisition_candidates','rows'=>count($rows),'created'=>$created,'errors'=>$errors];
    }
  }
}

?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>V13.1.1 Owner + Realtor Import</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1100px;margin:auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;padding:22px}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:11px 14px;border-radius:9px;font-weight:900;cursor:pointer}.light{background:#f2efe8}.field{margin:12px 0}label{display:block;font-weight:800;margin-bottom:5px}input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}@media(max-width:800px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}</style>
</head>
<body>
<div class="header"><div class="brand">V13.1.1 Import Center</div><div>Upload owner CSVs and Realtor exclusion CSVs so Jessica can clear the contact bottleneck</div></div>
<main class="wrap">
<p><a class="btn light" href="/dashboard/contact-acquisition-center.php">Contact Acquisition</a> <a class="btn light" href="/dashboard/approved-contact-pipeline.php">Approved Pool</a></p>

<?php if($msg):?><div class="panel"><?=h($msg)?></div><?php endif;?>
<?php if($result):?><div class="panel"><h2>Import Result</h2><pre><?=h(json_encode($result,JSON_PRETTY_PRINT))?></pre></div><?php endif;?>

<div class="grid">
<section class="panel">
<h2>Upload CSV</h2>
<form method="post" enctype="multipart/form-data">
<div class="field"><label>Import Type</label><select name="import_type"><option value="owner">Owner / Homeowner Records</option><option value="realtor">Realtor Exclusion List</option></select></div>
<div class="field"><label>Batch Name</label><input name="batch_name" value="Owner Import <?=h(date('Y-m-d'))?>"></div>
<div class="field"><label>Source Name</label><input name="source_name" value="manual_csv"></div>
<div class="field"><label>Default Town</label><input name="town" placeholder="Westport"></div>
<div class="field"><label>Market</label><input name="market" value="Lower Fairfield County"></div>
<div class="field"><label>CSV File</label><input type="file" name="csv_file" accept=".csv" required></div>
<button class="btn" type="submit">Import CSV</button>
</form>
</section>

<section class="panel">
<h2>Accepted Owner CSV Columns</h2>
<pre>owner_name, phone, email, property_address, mailing_address, town, state, property_type, years_owned, last_sale_price, estimated_value, estimated_equity, owner_occupied, dnc_status, consent_status, approval_status

Minimum useful columns:
owner_name, phone OR email, property_address, town

To become call eligible:
phone + dnc_status=clear + consent_status=opt_in/prior_relationship/business_contact + approval_status=approved</pre>

<h2>Accepted Realtor CSV Columns</h2>
<pre>name, phone, email, company, town

Jessica will use this table to flag/remove agents before approving contacts.</pre>
</section>
</div>
</main>
</body>
</html>