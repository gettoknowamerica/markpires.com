<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

if(!defined('HUBSPOT_PRIVATE_APP_TOKEN') || !HUBSPOT_PRIVATE_APP_TOKEN){
  echo json_encode(['success'=>false,'error'=>'HubSpot token missing']); exit;
}

$email = $_GET['email'] ?? ('goliath-test-' . time() . '@markpires.com');
$payload = [
  'properties' => [
    'email' => $email,
    'firstname' => 'Goliath',
    'lastname' => 'Diagnostic',
    'phone' => '2032472655',
    'lifecyclestage' => 'lead',
    'website' => 'https://markpires.com'
  ]
];

$ch=curl_init('https://api.hubapi.com/crm/v3/objects/contacts');
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>json_encode($payload),
  CURLOPT_HTTPHEADER=>[
    'Content-Type: application/json',
    'Authorization: Bearer '.HUBSPOT_PRIVATE_APP_TOKEN
  ],
  CURLOPT_TIMEOUT=>20
]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
$data=json_decode($body,true);

echo json_encode([
  'success'=>$http>=200&&$http<300 || $http===409,
  'http'=>$http,
  'curl_error'=>$err,
  'email'=>$email,
  'body'=>is_array($data)?$data:$body,
  'next_step'=>$http===401?'HubSpot token is invalid/expired.':($http===403?'HubSpot token lacks CRM contact permissions.':($http===409?'Contact already exists; capture update logic may need search/update fallback.':'If success true, HubSpot API is reachable.'))
],JSON_PRETTY_PRINT);
?>