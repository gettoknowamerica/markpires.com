<?php
require_once __DIR__ . '/scorsese-comfy-bridge.php';

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);

/*
  V75.5.3 compatibility patch:
  Accept JSON body, POST form data, or GET query params.
  This prevents missing_job_id when a local worker sends GET updates.
*/
if (!is_array($in) || empty($in)) {
    $in = array_merge($_GET, $_POST);
}

$key = $in['key'] ?? ($_GET['key'] ?? '');
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';

if (!hash_equals($expected, (string)$key)) {
    scb_out([
        'success' => false,
        'error' => 'bad_key'
    ], 403);
}

scb_out(scb_complete($in));