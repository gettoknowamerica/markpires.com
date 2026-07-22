<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-executive-brief.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Executive Morning Brief</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-agent-detail-v54.css?v=540"></head><body><div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main g54Office"><section class="g54Hero"><div class="g54Seal"><span>⚡</span></div><div><p class="g54Eyebrow">Executive Council</p><h1>Morning Executive Brief</h1><h2>The Founder wakes to opportunity, not unfinished work.</h2><p>Published work, ready decisions, qualified calls, content, and council recommendations.</p></div><div class="g54Actions"><a class="btn dark" href="/dashboard/goliath-mission-control.php">Mission Control</a></div></section><section class="g54BriefGrid" id="g54BriefGrid"><div class="g54Skeleton">Loading Executive Council work...</div></section></main></div><script src="/dashboard/assets/goliath-executive-brief-v54.js?v=540" defer></script></body></html>
