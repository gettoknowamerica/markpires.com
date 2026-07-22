<?php
require_once __DIR__ . '/dnc-check.php';

header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
$lead = [
  'source' => $_GET['source'] ?? 'cold_call',
  'type' => $_GET['type'] ?? 'homeowner_intelligence',
  'tag' => $_GET['tag'] ?? 'cold'
];

echo json_encode([
  'phone' => $phone,
  'lead_context' => $lead,
  'dnc_result' => mp_is_dnc_number($phone),
  'block_check' => mp_should_block_outbound_call($phone, $lead)
], JSON_PRETTY_PRINT);
