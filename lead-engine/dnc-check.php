<?php
/**
 * MarkPires Lead Engine — DNC Checker V2
 * Upload to: /public_html/lead-engine/dnc-check.php
 *
 * Supports downloaded National DNC formats like:
 * 203,9999999
 * 2039999999
 * 1,203,9999999
 * +12039999999
 *
 * Recommended for large files:
 * 1. Upload raw DNC file to /lead-engine/dnc/dnc-national.txt
 * 2. Run /lead-engine/dnc-build-index.php once
 * 3. Checker will use fast bucket index automatically
 */

function mp_dnc_normalize_phone_digits($phone) {
  $raw = trim((string)$phone);

  // Handle DNC CSV-style rows: 203,9999999 or 1,203,9999999
  if (strpos($raw, ',') !== false) {
    $parts = array_map('trim', explode(',', $raw));
    $parts = array_values(array_filter($parts, function($p) { return $p !== ''; }));

    if (count($parts) >= 2) {
      // last two numeric pieces should be area code + 7-digit number
      $last = preg_replace('/\D+/', '', $parts[count($parts) - 1]);
      $prev = preg_replace('/\D+/', '', $parts[count($parts) - 2]);

      if (strlen($prev) === 3 && strlen($last) === 7) {
        return $prev . $last;
      }
    }
  }

  $digits = preg_replace('/\D+/', '', $raw);

  if (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
    $digits = substr($digits, 1);
  }

  return $digits;
}

function mp_dnc_area_code($phone) {
  $digits = mp_dnc_normalize_phone_digits($phone);
  return strlen($digits) >= 10 ? substr($digits, 0, 3) : '';
}

function mp_dnc_suffix($phone) {
  $digits = mp_dnc_normalize_phone_digits($phone);
  return strlen($digits) === 10 ? substr($digits, 3) : '';
}

function mp_is_working_area_code($phone) {
  $area = mp_dnc_area_code($phone);
  return in_array($area, ['203','475','212','914'], true);
}

function mp_dnc_bucket_path($phone) {
  $digits = mp_dnc_normalize_phone_digits($phone);
  if (strlen($digits) !== 10) return '';

  $area = substr($digits, 0, 3);
  $suffix = substr($digits, 3);
  $bucket = substr($suffix, 0, 2);

  return __DIR__ . '/dnc/index/' . $area . '/' . $bucket . '.txt';
}

function mp_dnc_line_matches($line, $digits) {
  $lineDigits = mp_dnc_normalize_phone_digits($line);
  return $lineDigits === $digits;
}

function mp_dnc_scan_file_for_number($file, $digits) {
  if (!file_exists($file)) {
    return ['ok' => true, 'found' => false, 'reason' => 'file_missing'];
  }

  $handle = fopen($file, 'r');
  if (!$handle) {
    return ['ok' => false, 'found' => false, 'reason' => 'file_unreadable'];
  }

  while (($line = fgets($handle)) !== false) {
    if (mp_dnc_line_matches($line, $digits)) {
      fclose($handle);
      return ['ok' => true, 'found' => true, 'reason' => 'matched'];
    }
  }

  fclose($handle);
  return ['ok' => true, 'found' => false, 'reason' => 'not_found'];
}

function mp_is_dnc_number($phone) {
  $digits = mp_dnc_normalize_phone_digits($phone);

  if (strlen($digits) !== 10) {
    return [
      'ok' => false,
      'is_dnc' => false,
      'reason' => 'invalid_phone',
      'normalized' => $digits
    ];
  }

  // Fast path: area/bucket index created by dnc-build-index.php
  $bucketFile = mp_dnc_bucket_path($digits);
  if ($bucketFile && file_exists($bucketFile)) {
    $scan = mp_dnc_scan_file_for_number($bucketFile, $digits);
    return [
      'ok' => $scan['ok'],
      'is_dnc' => $scan['found'],
      'reason' => $scan['found'] ? 'matched_dnc_index' : 'not_found_in_index',
      'normalized' => $digits,
      'lookup' => 'bucket_index'
    ];
  }

  // Fallback: raw full file scan. Works, but slower for multi-million-line files.
  $rawFile = __DIR__ . '/dnc/dnc-national.txt';
  $scan = mp_dnc_scan_file_for_number($rawFile, $digits);

  return [
    'ok' => $scan['ok'],
    'is_dnc' => $scan['found'],
    'reason' => $scan['found'] ? 'matched_raw_dnc' : $scan['reason'],
    'normalized' => $digits,
    'lookup' => 'raw_file'
  ];
}

function mp_lead_is_cold_outbound($lead) {
  $blob = strtolower(json_encode($lead));

  $coldSignals = [
    'cold',
    'cold_call',
    'homeowner_intel',
    'homeowner_intelligence',
    'property_database',
    'skip_trace',
    'scrape',
    'scraped',
    'owner_database',
    'prospecting',
    'expired_listing',
    'fsbo'
  ];

  foreach ($coldSignals as $sig) {
    if (strpos($blob, $sig) !== false) return true;
  }

  return false;
}

function mp_lead_has_consent_or_warm_inquiry($lead) {
  $blob = strtolower(json_encode($lead));

  $warmSignals = [
    'consent',
    'home valuation',
    'valuation',
    'buyer guide',
    'seller guide',
    'town_guide',
    'consultation',
    'contact form',
    'markpires.com',
    'website',
    'inbound',
    'open_house',
    'listing inquiry'
  ];

  foreach ($warmSignals as $sig) {
    if (strpos($blob, $sig) !== false) return true;
  }

  return false;
}

/**
 * Main gate:
 * - Warm inbound website/form leads bypass DNC suppression.
 * - Cold homeowner/prospecting leads check DNC before Jessica calls.
 * - Expired/FSBO and other categories are flagged as "cold_check_required" by default here.
 *   Confirm your attorney/compliance source before bypassing DNC for any outbound campaign.
 */
function mp_should_block_outbound_call($phone, $lead = []) {
  $isCold = mp_lead_is_cold_outbound($lead);
  $isWarm = mp_lead_has_consent_or_warm_inquiry($lead);

  if ($isWarm && !$isCold) {
    return [
      'block' => false,
      'reason' => 'warm_inbound_or_consent_bypass',
      'dnc' => null
    ];
  }

  $dnc = mp_is_dnc_number($phone);

  if ($dnc['is_dnc']) {
    return [
      'block' => true,
      'reason' => 'national_dnc_cold_outbound_block',
      'dnc' => $dnc
    ];
  }

  return [
    'block' => false,
    'reason' => $isCold ? 'cold_outbound_clear' : 'not_marked_cold_clear',
    'dnc' => $dnc
  ];
}
