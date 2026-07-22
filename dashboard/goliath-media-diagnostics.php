<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-media-diagnostics.php'));
  exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
$media=sbq('media_projects?select=*&order=created_at.desc&limit=120');
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Goliath Media Diagnostics</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=36">
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top"><div><h1>Media Diagnostics</h1><p>Confirms whether completed creations have playable server URLs.</p></div><a class="g-btn g-btn-dark" href="/dashboard/goliath-completed-media.php">Completed Media</a></section>
<section class="g-panel">
<h2>media_projects source_url check</h2>
<div class="g-tableWrap">
<table class="g-stealthTable">
<thead><tr><th>Title</th><th>URL</th><th>Server File</th><th>Open</th></tr></thead>
<tbody>
<?php foreach($media as $m):
  $u=$m['source_url']??'';
  $path=$u ? parse_url($u,PHP_URL_PATH) : '';
  $local=$path ? realpath(__DIR__.'/..').$path : '';
  $exists=$local && file_exists($local);
?>
<tr>
<td><div class="g-name"><?=h($m['title']??'Untitled')?></div><div class="g-subtle"><?=h($m['status']??'')?></div></td>
<td><?=h($u ?: 'NO source_url')?></td>
<td><span class="g-pill <?=$exists?'g-pill-green':'g-pill-red'?>"><?=$exists?'YES':'NO / MISSING'?></span></td>
<td><?php if($u): ?><a class="g-pill g-pill-blue" target="_blank" href="<?=h($u)?>">Open</a><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</div>
</body>
</html>