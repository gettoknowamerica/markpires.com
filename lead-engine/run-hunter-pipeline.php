<?php
ini_set('display_errors',0); error_reporting(E_ALL); require_once __DIR__ . '/config.php'; header('Content-Type: application/json; charset=utf-8');
function call_local($url){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>55]);$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return ['http'=>$h,'body'=>json_decode($b,true)?:$b];}
$key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$base='https://'.($_SERVER['HTTP_HOST']??'markpires.com').'/lead-engine/';
$steps=['municipal_intelligence'=>'build-municipal-owner-intelligence.php','owner_qualifier'=>'build-municipal-owner-qualifier.php','owner_enrichment_50'=>'build-owner-enrichment.php','compliance_builder'=>'build-compliance-contact-approval.php','street_intelligence'=>'build-street-intelligence.php','morning_brief'=>'build-morning-brief.php'];
$out=[];foreach($steps as $name=>$file){$url=$base.$file.'?key='.urlencode($key);if($name==='owner_enrichment_50')$url.='&limit=50';$out[$name]=call_local($url);}echo json_encode(['success'=>true,'pipeline'=>'Hunter OS V20.4','steps'=>$out],JSON_PRETTY_PRINT);
?>