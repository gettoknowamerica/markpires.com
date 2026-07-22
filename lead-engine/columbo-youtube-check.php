<?php
/**
 * Goliath Omni OS v57.1.2
 * Columbo YouTube Vault Check
 *
 * This file NEVER stores API keys.
 * It only reads YouTube credentials/channel IDs from lead-engine/config.php.
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

function cvault($name, $fallback='') {
  if (defined($name)) return constant($name);
  $v = getenv($name);
  return $v !== false ? $v : $fallback;
}
function configured($v) {
  $v = trim((string)$v);
  return $v !== '' && strpos($v, 'PASTE_') !== 0 && strpos($v, 'YOUR_') !== 0;
}
function mask_key($v) {
  $v = (string)$v;
  if ($v === '') return null;
  if (strlen($v) <= 10) return str_repeat('*', strlen($v));
  return substr($v, 0, 4) . '...' . substr($v, -4);
}

$channels = [
  [
    'label' => 'Mark insPires the World',
    'channel_constant' => 'YOUTUBE_CHANNEL_MARK_INSPIRES',
    'channel_id' => cvault('YOUTUBE_CHANNEL_MARK_INSPIRES'),
    'key_constant' => 'YOUTUBE_API_KEY_MARK_INSPIRES',
    'api_key' => cvault('YOUTUBE_API_KEY_MARK_INSPIRES') ?: cvault('YOUTUBE_API_KEY'),
  ],
  [
    'label' => 'Discover Connecticut',
    'channel_constant' => 'YOUTUBE_CHANNEL_DISCOVER_CT',
    'channel_id' => cvault('YOUTUBE_CHANNEL_DISCOVER_CT'),
    'key_constant' => 'YOUTUBE_API_KEY_DISCOVER_CT',
    'api_key' => cvault('YOUTUBE_API_KEY_DISCOVER_CT') ?: cvault('YOUTUBE_API_KEY'),
  ],
];

$outChannels = [];
$ready = true;
foreach ($channels as $c) {
  $channelOk = configured($c['channel_id']);
  $keyOk = configured($c['api_key']);
  if (!$channelOk || !$keyOk) $ready = false;
  $outChannels[] = [
    'label' => $c['label'],
    'channel_constant' => $c['channel_constant'],
    'channel_configured' => $channelOk,
    'channel_id' => $channelOk ? $c['channel_id'] : null,
    'api_key_source' => configured(cvault($c['key_constant'])) ? $c['key_constant'] : (configured(cvault('YOUTUBE_API_KEY')) ? 'YOUTUBE_API_KEY fallback' : null),
    'api_key_configured' => $keyOk,
    'api_key_length' => $keyOk ? strlen((string)$c['api_key']) : 0,
    'api_key_preview' => $keyOk ? mask_key($c['api_key']) : null,
    'looks_like_google_api_key' => $keyOk ? (strpos((string)$c['api_key'], 'AIza') === 0) : false,
  ];
}

echo json_encode([
  'success' => $ready,
  'version' => '57.1.2',
  'message' => $ready ? 'Columbo can see both channel IDs and API keys from config.php.' : 'One or more channel IDs/API keys are missing from config.php.',
  'channels' => $outChannels,
  'expected_config_names' => [
    'YOUTUBE_API_KEY_MARK_INSPIRES',
    'YOUTUBE_CHANNEL_MARK_INSPIRES',
    'YOUTUBE_API_KEY_DISCOVER_CT',
    'YOUTUBE_CHANNEL_DISCOVER_CT',
    'optional_fallback' => 'YOUTUBE_API_KEY can be used as one shared key for both channels.'
  ],
  'next' => 'Run /lead-engine/columbo-youtube-index.php?key=YOUR_CRON_KEY&limit=25'
], JSON_PRETTY_PRINT);
