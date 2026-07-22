<?php
/**
 * Scout Contact Numbers Builder Alias
 *
 * Upload to:
 * /public_html/lead-engine/build-contact-numbers.php
 *
 * This keeps the Scout button working while preserving the existing
 * V14.2 Contact Enrichment Queue Builder file:
 * /public_html/lead-engine/build-contact-enrichment.php
 */

$target = __DIR__ . '/build-contact-enrichment.php';

if (!file_exists($target)) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(404);
  echo json_encode([
    'success' => false,
    'error' => 'missing_builder',
    'message' => 'build-contact-enrichment.php was not found in /lead-engine/. Upload it or rename the existing contact enrichment builder.',
    'expected_file' => '/public_html/lead-engine/build-contact-enrichment.php'
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

require $target;
?>