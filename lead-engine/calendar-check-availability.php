<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google-calendar-client.php';
header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? date('Y-m-d', strtotime('+1 day'));
$duration = (int)($_GET['duration'] ?? 60);
$buffer = (int)($_GET['buffer'] ?? 30);

$res = mp_calendar_request('availability', [
  'date' => $date,
  'duration_minutes' => $duration,
  'buffer_minutes' => $buffer,
  'start_hour' => (int)($_GET['start_hour'] ?? 9),
  'end_hour' => (int)($_GET['end_hour'] ?? 17)
]);

echo json_encode([
  'success' => !empty($res['ok']),
  'date' => $date,
  'result' => $res
], JSON_PRETTY_PRINT);
?>