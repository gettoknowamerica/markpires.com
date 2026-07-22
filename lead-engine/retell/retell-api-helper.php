<?php
/**
 * V20.1.3 Retell API Helper
 * Upload: /public_html/lead-engine/retell/retell-api-helper.php
 *
 * Purpose:
 * Replace deprecated POST /v2/list-calls with POST /v3/list-calls.
 */

function retell_api_key_v2013(){
  if(defined('RETELL_API_KEY') && RETELL_API_KEY) return RETELL_API_KEY;
  $env = getenv('RETELL_API_KEY');
  return $env ?: '';
}

function retell_post_v2013($path, $payload = []){
  $key = retell_api_key_v2013();
  if(!$key){
    return ['success'=>false,'error'=>'RETELL_API_KEY missing'];
  }

  $ch = curl_init('https://api.retellai.com' . $path);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $key,
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 45
  ]);

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  $json = json_decode($body, true);
  return [
    'success' => $http >= 200 && $http < 300,
    'http' => $http,
    'error' => $err,
    'data' => is_array($json) ? $json : $body
  ];
}

/**
 * NEW Retell call listing.
 *
 * Old deprecated:
 * POST /v2/list-calls
 *
 * New:
 * POST /v3/list-calls
 *
 * New response shape:
 * {
 *   "items": [...],
 *   "pagination_key": "...",
 *   "has_more": true/false
 * }
 */
function retell_list_calls_v3($limit = 50, $pagination_key = null, $filters = []){
  $payload = array_merge([
    'limit' => $limit
  ], $filters);

  if($pagination_key){
    $payload['pagination_key'] = $pagination_key;
  }

  return retell_post_v2013('/v3/list-calls', $payload);
}

function retell_extract_items_v2013($response){
  if(!is_array($response) || empty($response['success'])) return [];
  $data = $response['data'] ?? [];
  if(isset($data['items']) && is_array($data['items'])) return $data['items'];
  if(is_array($data)) return $data;
  return [];
}
?>