<?php
/**
 * EVENTS API — events.php
 * Path: /public_html/lead-engine/events.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$allowed_origins = [
  'https://discoverct.net',
  'https://www.discoverct.net',
  'https://markpires.com',
  'https://www.markpires.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
  header("Access-Control-Allow-Origin: $origin");
}

$cache_dir = __DIR__ . '/cache';
$cache_file = $cache_dir . '/events.json';

if (!is_dir($cache_dir)) {
  mkdir($cache_dir, 0755, true);
}

if (!file_exists($cache_file) || (time() - filemtime($cache_file)) > 90000) {
  echo json_encode([
    'updated' => null,
    'count' => 0,
    'events' => [],
    'note' => 'Event cache is not ready yet.'
  ]);
  exit;
}

$data = json_decode(file_get_contents($cache_file), true);
if (!is_array($data)) {
  echo json_encode([
    'updated' => null,
    'count' => 0,
    'events' => [],
    'note' => 'Event cache could not be read.'
  ]);
  exit;
}

$events = $data['events'] ?? [];
$cat = strtolower(trim($_GET['cat'] ?? 'all'));
$limit = min(50, max(1, intval($_GET['limit'] ?? 12)));
$search = strtolower(trim($_GET['search'] ?? ''));

if ($cat !== 'all') {
  $events = array_filter($events, function($e) use ($cat) {
    return strtolower($e['category'] ?? '') === $cat;
  });
}

if ($search !== '') {
  $events = array_filter($events, function($e) use ($search) {
    $title = strtolower($e['title'] ?? '');
    $location = strtolower($e['location'] ?? '');
    return strpos($title, $search) !== false || strpos($location, $search) !== false;
  });
}

$events = array_slice(array_values($events), 0, $limit);

echo json_encode([
  'updated' => $data['updated'] ?? null,
  'count' => count($events),
  'events' => $events
]);
?>