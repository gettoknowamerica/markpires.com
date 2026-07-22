<?php
/**
 * MarkPires UTM Campaign Builder V1
 * Upload to: /public_html/dashboard/utm-builder.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$site = 'https://markpires.com';
$towns = [
  'fairfield','wilton','ridgefield','darien','westport','weston','new-canaan','norwalk','easton','stamford','greenwich',
  'milford','trumbull','monroe','redding','shelton'
];

$basePath = $_GET['path'] ?? '/';
$town = $_GET['town'] ?? '';
$source = $_GET['source'] ?? 'facebook';
$medium = $_GET['medium'] ?? 'social';
$campaign = $_GET['campaign'] ?? 'discover_ct_town_pages';
$content = $_GET['content'] ?? 'town_video_cta';
$term = $_GET['term'] ?? '';

if ($town) $basePath = '/towns/' . $town . '.html';

$url = $site . $basePath;
$params = array_filter([
  'utm_source'=>$source,
  'utm_medium'=>$medium,
  'utm_campaign'=>$campaign,
  'utm_content'=>$content,
  'utm_term'=>$term
], fn($v)=>$v !== '');

$final = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>UTM Campaign Builder — Mark Pires</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}.inner{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;gap:18px}.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68)}.header a{color:#fff;text-decoration:none;opacity:.8}
.wrap{max-width:1200px;margin:0 auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05);padding:22px;margin-bottom:18px}
h2{font-family:Georgia,serif;margin:0 0 14px}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
label{font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#777;font-weight:800}
input,select,textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;font-size:15px;margin-top:5px}
textarea{min-height:95px}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:900;cursor:pointer}.gold{background:var(--gold);color:#111}.light{background:#f2efe8;color:#111}
.result{background:#10101a;color:#fff;border-radius:14px;padding:18px;word-break:break-all;font-size:16px;line-height:1.55}.copy{margin-top:12px}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.card{background:#faf9f6;border:1px solid var(--line);border-radius:14px;padding:14px}.card strong{display:block;margin-bottom:8px}
@media(max-width:850px){.grid,.cards{grid-template-columns:1fr}.inner{display:block}.wrap{padding:14px}}
</style>
<script>
function copyUrl(){
  const text=document.getElementById('finalUrl').innerText;
  navigator.clipboard.writeText(text).then(()=>alert('Copied campaign link'));
}
</script>
</head>
<body>
<header class="header"><div class="inner"><div><div class="brand">UTM Campaign Builder</div><div class="sub">Create trackable links for town pages, ads, posts, blogs, and CTAs.</div></div><div><a href="/dashboard/operations.php">Operations</a> · <a href="/dashboard/conversions.php">Conversions</a></div></div></header>

<main class="wrap">
<section class="panel">
  <h2>Build Trackable Link</h2>
  <form method="get">
    <div class="grid">
      <div>
        <label>Town Page Shortcut</label>
        <select name="town">
          <option value="">No town shortcut</option>
          <?php foreach($towns as $t): ?><option value="<?=h($t)?>" <?=$town===$t?'selected':''?>><?=h(ucwords(str_replace('-',' ',$t)))?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Custom Path</label>
        <input name="path" value="<?=h($basePath)?>" placeholder="/towns/fairfield.html">
      </div>
      <div>
        <label>UTM Source</label>
        <select name="source">
          <?php foreach(['facebook','instagram','youtube','tiktok','google','email','qr','direct_mail','open_house','discover_ct'] as $s): ?><option value="<?=h($s)?>" <?=$source===$s?'selected':''?>><?=h($s)?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>UTM Medium</label>
        <select name="medium">
          <?php foreach(['social','paid_social','video','email','organic','qr','print','referral','cpc'] as $m): ?><option value="<?=h($m)?>" <?=$medium===$m?'selected':''?>><?=h($m)?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Campaign</label>
        <input name="campaign" value="<?=h($campaign)?>" placeholder="discover_ct_town_pages">
      </div>
      <div>
        <label>Content</label>
        <input name="content" value="<?=h($content)?>" placeholder="town_video_cta">
      </div>
      <div>
        <label>Term / Audience</label>
        <input name="term" value="<?=h($term)?>" placeholder="fairfield_homeowners">
      </div>
    </div>
    <p><button class="btn gold" type="submit">Build Link</button></p>
  </form>
</section>

<section class="panel">
  <h2>Campaign Link</h2>
  <div class="result" id="finalUrl"><?=h($final)?></div>
  <p class="copy"><button class="btn gold" onclick="copyUrl()">Copy Link</button> <a class="btn light" href="<?=h($final)?>" target="_blank">Open Link</a></p>
</section>

<section class="panel">
  <h2>Fast Presets</h2>
  <div class="cards">
    <div class="card">
      <strong>Facebook Town Ad</strong>
      <a class="btn light" href="?town=fairfield&source=facebook&medium=paid_social&campaign=discover_ct_town_pages&content=video_ad&term=fairfield_homeowners">Use Preset</a>
    </div>
    <div class="card">
      <strong>Instagram Reel</strong>
      <a class="btn light" href="?town=westport&source=instagram&medium=social&campaign=discover_ct_reels&content=reel_caption_link&term=westport_buyers">Use Preset</a>
    </div>
    <div class="card">
      <strong>QR Door Knock</strong>
      <a class="btn light" href="?town=greenwich&source=qr&medium=print&campaign=door_knock_2026&content=letter_qr&term=greenwich_homeowners">Use Preset</a>
    </div>
  </div>
</section>
</main>
</body>
</html>
