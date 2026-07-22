<?php
header('Content-Type: application/json; charset=utf-8');
$path = __DIR__ . '/../goliath-core/executive-tool-capability-dictionary.json';
$out = [
  'ok' => is_file($path),
  'version' => 'V75.6',
  'dictionary_path' => $path,
  'message' => is_file($path) ? 'Tool Capability Dictionary installed.' : 'Dictionary missing.',
  'time' => date('c')
];
if (is_file($path)) {
  $json = json_decode(file_get_contents($path), true);
  $out['capabilities'] = isset($json['capabilities']) ? count($json['capabilities']) : 0;
  $out['executives'] = isset($json['executive_playbooks']) ? count($json['executive_playbooks']) : 0;
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
