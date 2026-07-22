<?php
/**
 * dialer.php
 * Retell dialing is now handled by /lead-engine/capture.php.
 * This file is intentionally disabled so there is no second/competing dialer.
 */

header('Content-Type: application/json; charset=utf-8');

http_response_code(403);

echo json_encode([
  'success' => false,
  'message' => 'Dialer disabled. Retell calls are triggered through /lead-engine/capture.php after valid form consent.'
]);
?>