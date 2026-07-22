<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google-calendar-client.php';
header('Content-Type: application/json; charset=utf-8');
$res=mp_calendar_request('ping',['source'=>'v11_10_test']);
echo json_encode(['success'=>!empty($res['ok']),'calendar_connected'=>!empty($res['ok']),'result'=>$res],JSON_PRETTY_PRINT);
?>