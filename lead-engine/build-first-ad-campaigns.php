<?php
/**
 * V12.4.1 First Ad Campaign Builder — 500 Fix
 * Upload over: /public_html/lead-engine/build-first-ad-campaigns.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  function sb1241($method, $endpoint, $payload = null) {
    $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
    $headers = [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ];
    $headers[] = $method === 'POST'
      ? 'Prefer: resolution=ignore-duplicates,return=representation'
      : 'Prefer: return=representation';

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 45
    ]);

    if ($payload !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $data = json_decode($body, true);
    return [
      'ok' => $http >= 200 && $http < 300,
      'http' => $http,
      'body' => $body,
      'error' => $err,
      'data' => is_array($data) ? $data : []
    ];
  }

  function copy_for_campaign1241($c) {
    $town = $c['town'] ?: 'Fairfield County';
    $landing = $c['landing_page'] ?: '/home-valuation';
    $offer = $c['primary_offer'] ?: 'Free Home Value Review';
    $aud = strtolower((string)($c['audience'] ?? ''));
    $name = strtolower((string)($c['campaign_name'] ?? ''));

    if (str_contains($aud, 'seller') || str_contains($name, 'home value')) {
      return [
        'fb_text' => "Thinking about selling in {$town}? Online estimates miss renovations, condition, lot, street, and buyer demand. Jessica can help start a smarter local home value review with Mark Pires.",
        'fb_headline' => "What Is Your {$town} Home Really Worth?",
        'ig' => "Fairfield County sellers: before you trust an online estimate, get a smarter local read. {$offer}.",
        'g_heads' => ["{$town} Home Value", "What Is My Home Worth?", "Fairfield County Realtor", "Sell Smarter In {$town}", "Free Home Value Review"],
        'g_desc' => ["Get a smarter local home value review with Mark Pires.", "Online estimates miss local details. Start with Jessica today."],
        'retarget' => "Still curious what your {$town} home could sell for? Jessica can help you take the next step.",
        'prompt' => "Luxury Fairfield County home exterior at golden hour, elegant Connecticut neighborhood, warm inviting real estate ad, no text overlay, premium cinematic look.",
        'cta' => 'Get My Home Value',
        'budget' => 25
      ];
    }

    if (str_contains($aud, 'nyc') || str_contains($aud, 'relocation') || str_contains($landing, 'relocation')) {
      return [
        'fb_text' => "Leaving NYC or Westchester for Connecticut? Jessica can help narrow down Fairfield County towns based on commute, schools, space, lifestyle, and budget.",
        'fb_headline' => "Moving To CT? Find Your Best Town",
        'ig' => "More space. Better lifestyle. Still close to NYC. Discover which Fairfield County town fits you.",
        'g_heads' => ["Moving To Connecticut", "NYC To CT Homes", "Fairfield County Towns", "Best CT Towns For Buyers", "Relocation Guide"],
        'g_desc' => ["Compare Fairfield County towns before you buy.", "Jessica helps match your lifestyle, commute, and budget."],
        'retarget' => "Still comparing CT towns? Get a smarter town match before you start touring homes.",
        'prompt' => "Young family looking out over beautiful Fairfield County Connecticut town center and homes, aspirational relocation lifestyle, cinematic natural light, no text overlay.",
        'cta' => 'Get My Town Match',
        'budget' => 25
      ];
    }

    return [
      'fb_text' => "Builders and investors watching Fairfield County: Jessica is organizing land, teardown, renovation, and acquisition opportunity signals by town.",
      'fb_headline' => "Builder Opportunities In {$town}",
      'ig' => "Land, teardown, renovation, and acquisition signals in {$town}. Join the builder opportunity watchlist.",
      'g_heads' => ["{$town} Builder Leads", "CT Land Opportunities", "Teardown Opportunities", "Fairfield County Builders", "Investor Watchlist"],
      'g_desc' => ["Watch land, teardown, and renovation signals by town.", "Join Mark Pires' Fairfield County builder opportunity list."],
      'retarget' => "Still looking for your next Fairfield County project? Join the opportunity watchlist.",
      'prompt' => "Premium Connecticut land parcel and luxury construction concept, builder developer opportunity, aerial style, cinematic, no text overlay.",
      'cta' => 'Join Watchlist',
      'budget' => 20
    ];
  }

  $campaigns = sb1241('GET', 'first_campaign_plan?select=*&status=eq.draft&order=priority_score.desc&limit=50')['data'];
  $created = [];
  $errors = [];

  foreach ($campaigns as $c) {
    if (!is_array($c) || empty($c['id'])) continue;

    $copy = copy_for_campaign1241($c);
    $checklist = [
      'landing_page_exists' => !empty($c['landing_page']),
      'lead_form_connected' => true,
      'retell_followup_ready' => true,
      'calendar_ready' => defined('GOOGLE_CALENDAR_WEBHOOK_URL') && GOOGLE_CALENDAR_WEBHOOK_URL,
      'budget_set' => true,
      'tracking_needed' => true
    ];

    $patch = sb1241('PATCH', 'first_campaign_plan?id=eq.' . rawurlencode($c['id']), [
      'facebook_primary_text' => $copy['fb_text'],
      'facebook_headline' => $copy['fb_headline'],
      'instagram_caption' => $copy['ig'],
      'google_search_headlines' => $copy['g_heads'],
      'google_search_descriptions' => $copy['g_desc'],
      'retargeting_angle' => $copy['retarget'],
      'creative_prompt' => $copy['prompt'],
      'campaign_budget' => $copy['budget'],
      'launch_checklist' => $checklist,
      'updated_at' => date('c')
    ]);

    if (!$patch['ok']) {
      $errors[] = ['campaign'=>$c['campaign_name'] ?? '', 'stage'=>'patch_campaign', 'http'=>$patch['http'], 'body'=>$patch['body']];
      continue;
    }

    $assets = [
      ['asset_type'=>'meta_ad','headline'=>$copy['fb_headline'],'body'=>$copy['fb_text'],'cta'=>$copy['cta']],
      ['asset_type'=>'instagram_caption','headline'=>$copy['fb_headline'],'body'=>$copy['ig'],'cta'=>$copy['cta']],
      ['asset_type'=>'google_search','headline'=>implode(' | ', array_slice($copy['g_heads'], 0, 3)),'body'=>implode(' ', $copy['g_desc']),'cta'=>$copy['cta']],
      ['asset_type'=>'retargeting','headline'=>'Still Interested?','body'=>$copy['retarget'],'cta'=>$copy['cta']],
      ['asset_type'=>'image_prompt','headline'=>'Creative Prompt','body'=>$copy['prompt'],'cta'=>'Generate Image']
    ];

    foreach ($assets as $a) {
      $payload = [[
        'campaign_id' => $c['id'],
        'campaign_name' => $c['campaign_name'] ?? '',
        'asset_type' => $a['asset_type'],
        'headline' => $a['headline'],
        'body' => $a['body'],
        'cta' => $a['cta'],
        'landing_page' => $c['landing_page'] ?? '/home-valuation',
        'status' => 'draft',
        'raw_payload' => ['campaign'=>$c, 'copy'=>$copy],
        'created_at' => date('c'),
        'updated_at' => date('c')
      ]];

      $r = sb1241('POST', 'campaign_launch_assets', $payload);
      if ($r['ok']) {
        $created[] = ($c['campaign_name'] ?? 'campaign') . ' ' . $a['asset_type'];
      } else {
        $errors[] = ['campaign'=>$c['campaign_name'] ?? '', 'asset'=>$a['asset_type'], 'http'=>$r['http'], 'body'=>$r['body']];
      }
    }
  }

  echo json_encode([
    'success' => empty($errors),
    'campaigns_checked' => count($campaigns),
    'assets_created' => count($created),
    'created' => $created,
    'errors' => $errors
  ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'PHP exception',
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine()
  ], JSON_PRETTY_PRINT);
}
?>