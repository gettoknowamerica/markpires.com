<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/director-test.php'));exit;}
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Director Test</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=34">
<script src="/dashboard/assets/goliath-ui.js?v=34" defer></script>
<script src="/dashboard/assets/goliath-director-v35-1.js?v=351" defer></script>
<style>.directorBox{max-width:900px}.directorPrompt{width:100%;min-height:150px;background:#050b16;color:#fff;border:1px solid #263753;border-radius:14px;padding:14px;font-size:14px}.rowBtns{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}</style>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top"><div><h1>Director Test</h1><p>Send a real command to the local Director worker, Ollama, and ComfyUI.</p></div></section>
<section class="g-panel directorBox">
<h2>Goliath Director <span>Wan + Flux bridge</span></h2>
<div class="g-inner">
<textarea id="directorPrompt" class="directorPrompt">Create a premium cinematic vertical real estate reel: modern Fairfield County home, golden hour, luxury lifestyle, dramatic camera movement, emotional seller-focused energy, House Detective noir edge.</textarea>
<div class="rowBtns">
<button class="g-btn g-btn-purple" onclick="directorCreateVideo(document.getElementById('directorPrompt').value,{duration:5})">🎬 Director Create Video</button>
<button class="g-btn g-btn-gold" onclick="directorCreateImage(document.getElementById('directorPrompt').value,{aspect_ratio:'16:9'})">🖼 Director Create Image</button>
<a class="g-btn g-btn-dark" href="/dashboard/goliath-mission-control.php">Mission Control</a>
</div>
</div>
</section>
</main>
</div>
</body>
</html>