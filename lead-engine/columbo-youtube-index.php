<?php
/**
 * Goliath Omni OS v57.1.2
 * Columbo YouTube Indexer
 *
 * This file NEVER stores API keys.
 * It reads all keys/channel IDs from lead-engine/config.php only.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$key = $_GET['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if ($key !== $expected) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function cfg($name, $fallback='') {
  if (defined($name)) return constant($name);
  $v = getenv($name);
  return $v !== false ? $v : $fallback;
}
function configured($v) {
  $v = trim((string)$v);
  return $v !== '' && strpos($v, 'PASTE_') !== 0 && strpos($v, 'YOUR_') !== 0;
}
function http_json($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>45]);
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $json = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300 && is_array($json), 'http'=>$http, 'error'=>$err, 'body'=>$body, 'json'=>$json];
}
function sb($method, $endpoint, $payload=null) {
  if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) return ['ok'=>false,'error'=>'Supabase config missing'];
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = [
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: resolution=merge-duplicates,return=representation'
  ];
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$headers, CURLOPT_TIMEOUT=>45]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($body,true);
  return ['ok'=>$http>=200 && $http<300, 'http'=>$http, 'error'=>$err, 'data'=>$data, 'raw'=>$body];
}
function yt_get($path, $params, $apiKey) {
  $params['key'] = $apiKey;
  return http_json('https://www.googleapis.com/youtube/v3/'.$path.'?'.http_build_query($params));
}
function iso8601_seconds($duration) {
  if (!$duration) return 0;
  preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $m);
  return ((int)($m[1] ?? 0))*3600 + ((int)($m[2] ?? 0))*60 + ((int)($m[3] ?? 0));
}
function score_video($stats, $seconds) {
  $views = (int)($stats['viewCount'] ?? 0);
  $likes = (int)($stats['likeCount'] ?? 0);
  $comments = (int)($stats['commentCount'] ?? 0);
  $score = 0;
  $score += min(55, (int)floor(log(max(1,$views), 1.7)));
  $score += min(25, (int)floor(log(max(1,$likes+1), 1.6)));
  $score += min(20, (int)floor(log(max(1,$comments+1), 1.5)));
  if ($seconds >= 20 && $seconds <= 90) $score += 5;
  return max(0, min(100, $score));
}
function resolve_channel($input, $apiKey) {
  $input = trim((string)$input);
  if (!$input) return null;
  if (preg_match('/^UC[a-zA-Z0-9_-]{20,}$/', $input)) return $input;
  if (strpos($input, 'youtube.com/') !== false) {
    if (preg_match('#/channel/(UC[a-zA-Z0-9_-]+)#', $input, $m)) return $m[1];
    if (preg_match('#/@([a-zA-Z0-9_.-]+)#', $input, $m)) $input = '@'.$m[1];
  }
  if ($input[0] === '@') {
    $handle = substr($input,1);
    $r = yt_get('channels', ['part'=>'id,snippet,statistics,contentDetails', 'forHandle'=>$handle], $apiKey);
    if ($r['ok'] && !empty($r['json']['items'][0]['id'])) return $r['json']['items'][0]['id'];
  }
  $r = yt_get('search', ['part'=>'snippet', 'type'=>'channel', 'q'=>$input, 'maxResults'=>1], $apiKey);
  if ($r['ok'] && !empty($r['json']['items'][0]['snippet']['channelId'])) return $r['json']['items'][0]['snippet']['channelId'];
  return null;
}

$limit = max(1, min(50, (int)($_GET['limit'] ?? 25)));

// ======================================================
// Goliath Vault → Columbo YouTube Sources
// ======================================================
// Keys and channel IDs are read from config.php only.
// Preferred two-key setup:
//   YOUTUBE_API_KEY_MARK_INSPIRES + YOUTUBE_CHANNEL_MARK_INSPIRES
//   YOUTUBE_API_KEY_DISCOVER_CT   + YOUTUBE_CHANNEL_DISCOVER_CT
// Optional shared-key fallback:
//   YOUTUBE_API_KEY can serve both channels.
$channels = [
  [
    'label' => 'Mark insPires the World',
    'channel_id' => cfg('YOUTUBE_CHANNEL_MARK_INSPIRES'),
    'api_key' => cfg('YOUTUBE_API_KEY_MARK_INSPIRES') ?: cfg('YOUTUBE_API_KEY'),
    'key_constant' => configured(cfg('YOUTUBE_API_KEY_MARK_INSPIRES')) ? 'YOUTUBE_API_KEY_MARK_INSPIRES' : 'YOUTUBE_API_KEY',
  ],
  [
    'label' => 'Discover Connecticut',
    'channel_id' => cfg('YOUTUBE_CHANNEL_DISCOVER_CT'),
    'api_key' => cfg('YOUTUBE_API_KEY_DISCOVER_CT') ?: cfg('YOUTUBE_API_KEY'),
    'key_constant' => configured(cfg('YOUTUBE_API_KEY_DISCOVER_CT')) ? 'YOUTUBE_API_KEY_DISCOVER_CT' : 'YOUTUBE_API_KEY',
  ],
];

$missing = [];
$channels = array_values(array_filter($channels, function($c) use (&$missing) {
  $ok = configured($c['channel_id'] ?? '') && configured($c['api_key'] ?? '');
  if (!$ok) $missing[] = ['label'=>$c['label'], 'channel_present'=>configured($c['channel_id'] ?? ''), 'key_present'=>configured($c['api_key'] ?? '')];
  return $ok;
}));

if (!$channels) {
  echo json_encode([
    'success'=>false,
    'version'=>'57.1.2',
    'error'=>'No complete YouTube sources configured in config.php.',
    'required_config_example'=>[
      "define('YOUTUBE_API_KEY_MARK_INSPIRES', 'AIza...');",
      "define('YOUTUBE_CHANNEL_MARK_INSPIRES', 'UCu3f0qHwbQiNXCX5mjIKL9Q');",
      "define('YOUTUBE_API_KEY_DISCOVER_CT', 'AIza...');",
      "define('YOUTUBE_CHANNEL_DISCOVER_CT', 'UCyMNm7MbIR4H4LMZRgnKSPw');"
    ],
    'missing'=>$missing
  ], JSON_PRETTY_PRINT);
  exit;
}

$run = sb('POST','columbo_youtube_index_runs', [[
  'run_status'=>'started',
  'phase'=>'resolving_channels',
  'progress'=>5,
  'channels_requested'=>count($channels),
  'notes'=>'Columbo started YouTube indexing run.',
  'raw_payload'=>['sources'=>array_map(function($c){ return ['label'=>$c['label'], 'channel_id'=>$c['channel_id'], 'key_constant'=>$c['key_constant']]; }, $channels)]
]]);
$runId = $run['ok'] && !empty($run['data'][0]['id']) ? $run['data'][0]['id'] : null;

$indexedChannels=0; $videosFound=0; $videosUpserted=0; $errors=[]; $results=[];
foreach($channels as $channelCfg) {
  $label = $channelCfg['label'];
  $input = $channelCfg['channel_id'];
  $apiKey = $channelCfg['api_key'];
  $channelId = resolve_channel($input, $apiKey);
  if (!$channelId) { $errors[] = ['channel'=>$label,'input'=>$input,'error'=>'Could not resolve channel']; continue; }

  $c = yt_get('channels', ['part'=>'snippet,statistics,contentDetails', 'id'=>$channelId], $apiKey);
  if (!$c['ok'] || empty($c['json']['items'][0])) {
    $errors[]=['channel'=>$label,'error'=>'Channel API failed','http'=>$c['http'], 'response'=>$c['json'] ?? null];
    continue;
  }
  $item = $c['json']['items'][0];
  $sn = $item['snippet'] ?? []; $st = $item['statistics'] ?? []; $cd = $item['contentDetails'] ?? [];
  $uploads = $cd['relatedPlaylists']['uploads'] ?? null;
  $thumb = $sn['thumbnails']['high']['url'] ?? ($sn['thumbnails']['default']['url'] ?? null);
  sb('POST','columbo_youtube_channels?on_conflict=channel_id', [[
    'channel_id'=>$channelId,
    'channel_title'=>$sn['title'] ?? $label,
    'channel_handle'=>$sn['customUrl'] ?? null,
    'description'=>$sn['description'] ?? null,
    'thumbnail_url'=>$thumb,
    'published_at'=>$sn['publishedAt'] ?? null,
    'subscriber_count'=>(int)($st['subscriberCount'] ?? 0),
    'video_count'=>(int)($st['videoCount'] ?? 0),
    'view_count'=>(int)($st['viewCount'] ?? 0),
    'uploads_playlist_id'=>$uploads,
    'source_label'=>$label,
    'last_indexed_at'=>gmdate('c'),
    'raw_payload'=>$item
  ]]);
  $indexedChannels++;
  if (!$uploads) { $errors[]=['channel'=>$label,'error'=>'No uploads playlist found']; continue; }

  $plist = yt_get('playlistItems', ['part'=>'snippet,contentDetails', 'playlistId'=>$uploads, 'maxResults'=>$limit], $apiKey);
  if (!$plist['ok']) { $errors[]=['channel'=>$label,'error'=>'Playlist API failed','http'=>$plist['http'],'response'=>$plist['json'] ?? null]; continue; }
  $videoIds=[];
  foreach(($plist['json']['items'] ?? []) as $pi) {
    $vid = $pi['contentDetails']['videoId'] ?? ($pi['snippet']['resourceId']['videoId'] ?? null);
    if ($vid) $videoIds[] = $vid;
  }
  $videoIds = array_values(array_unique($videoIds));
  $videosFound += count($videoIds);
  if (!$videoIds) continue;

  $vdata = yt_get('videos', ['part'=>'snippet,statistics,contentDetails', 'id'=>implode(',', $videoIds), 'maxResults'=>50], $apiKey);
  if (!$vdata['ok']) { $errors[]=['channel'=>$label,'error'=>'Videos API failed','http'=>$vdata['http'],'response'=>$vdata['json'] ?? null]; continue; }
  $rows=[];
  foreach(($vdata['json']['items'] ?? []) as $v) {
    $vsn = $v['snippet'] ?? []; $vst = $v['statistics'] ?? []; $vcd = $v['contentDetails'] ?? [];
    $dur = $vcd['duration'] ?? ''; $secs = iso8601_seconds($dur);
    $vid = $v['id'];
    $rows[] = [
      'video_id'=>$vid,
      'channel_id'=>$channelId,
      'channel_title'=>$vsn['channelTitle'] ?? ($sn['title'] ?? $label),
      'title'=>$vsn['title'] ?? '',
      'description'=>$vsn['description'] ?? '',
      'published_at'=>$vsn['publishedAt'] ?? null,
      'thumbnail_url'=>$vsn['thumbnails']['high']['url'] ?? ($vsn['thumbnails']['default']['url'] ?? null),
      'video_url'=>'https://www.youtube.com/watch?v='.$vid,
      'duration_iso'=>$dur,
      'duration_seconds'=>$secs,
      'view_count'=>(int)($vst['viewCount'] ?? 0),
      'like_count'=>(int)($vst['likeCount'] ?? 0),
      'comment_count'=>(int)($vst['commentCount'] ?? 0),
      'performance_score'=>score_video($vst, $secs),
      'columbo_notes'=>'Indexed by Columbo. Ready for title, hook, retention, archive, and repurpose analysis.',
      'opportunity_type'=>'archive',
      'status'=>'indexed',
      'raw_payload'=>$v,
      'updated_at'=>gmdate('c')
    ];
  }
  if ($rows) {
    $up = sb('POST','columbo_youtube_videos?on_conflict=video_id', $rows);
    if ($up['ok']) $videosUpserted += count($rows);
    else $errors[]=['channel'=>$label,'error'=>'Video upsert failed','response'=>$up];
  }
  $results[] = ['label'=>$label,'channel_id'=>$channelId,'videos_indexed'=>count($rows ?? []), 'key_source'=>$channelCfg['key_constant']];
}

$progress = $errors ? 85 : 100;
if ($runId) sb('PATCH','columbo_youtube_index_runs?id=eq.'.rawurlencode($runId), [
  'run_status'=>$errors ? 'completed_with_notes' : 'completed',
  'phase'=>$errors ? 'completed_with_notes' : 'completed',
  'progress'=>$progress,
  'channels_indexed'=>$indexedChannels,
  'videos_found'=>$videosFound,
  'videos_upserted'=>$videosUpserted,
  'error_message'=>$errors ? json_encode($errors) : null,
  'raw_payload'=>['results'=>$results,'errors'=>$errors],
  'updated_at'=>gmdate('c')
]);

echo json_encode([
  'success'=>empty($errors),
  'version'=>'57.1.2',
  'run_id'=>$runId,
  'channels_requested'=>count($channels),
  'channels_indexed'=>$indexedChannels,
  'videos_found'=>$videosFound,
  'videos_upserted'=>$videosUpserted,
  'progress'=>$progress,
  'results'=>$results,
  'errors'=>$errors,
  'next'=>'Open Columbo office or query columbo_youtube_videos. Next release turns indexed videos into archive/repurpose assets.'
], JSON_PRETTY_PRINT);
