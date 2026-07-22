<?php
/**
 * EVENTS API — events.php
 * Returns cached Fairfield County events as JSON
 * Both sites call: GET /lead-engine/events.php?cat=food&limit=12
 *
 * Params:
 *   cat    = all | arts | food | music | family | sports | realestate | local
 *   limit  = number of results (default 12, max 50)
 *   search = keyword filter
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600'); // 1 hour browser cache

$allowed_origins = ['https://discoverct.net','https://www.discoverct.net','https://markpires.com','https://www.markpires.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) header("Access-Control-Allow-Origin: $origin");

$cache_file = __DIR__ . '/cache/events.json';

// If cache missing or stale (>25h), return static fallback
if (!file_exists($cache_file) || (time() - filemtime($cache_file)) > 90000) {
    // Trigger a background sync
    if (function_exists('fastcgi_finish_request')) {
        echo json_encode(['updated'=>date('Y-m-d H:i:s'),'count'=>0,'events'=>[],'note'=>'Syncing...']);
        fastcgi_finish_request();
        require_once __DIR__ . '/cron-sync-events.php';
    } else {
        echo json_encode(['updated'=>null,'count'=>0,'events'=>[],'note'=>'Cache building...']);
    }
    exit;
}

$data   = json_decode(file_get_contents($cache_file), true);
$events = $data['events'] ?? [];

// Filter
$cat    = strtolower(trim($_GET['cat']   ?? 'all'));
$limit  = min(50, max(1, intval($_GET['limit'] ?? 12)));
$search = strtolower(trim($_GET['search'] ?? ''));

if ($cat !== 'all') {
    $events = array_filter($events, fn($e) => ($e['category'] ?? '') === $cat);
}
if ($search) {
    $events = array_filter($events, fn($e) =>
        stripos($e['title'], $search) !== false || stripos($e['location'], $search) !== false
    );
}

$events = array_slice(array_values($events), 0, $limit);

echo json_encode([
    'updated' => $data['updated'] ?? null,
    'count'   => count($events),
    'events'  => $events,
]);
