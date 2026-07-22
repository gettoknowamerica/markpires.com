<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-voice.php'));exit;}
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hey Goliath</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=34">
<script src="/dashboard/assets/goliath-ui.js?v=34" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-voice-v35-6.css?v=356">
<script src="/dashboard/assets/goliath-voice-v35-6.js?v=356" defer></script>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div><h1>Hey Goliath</h1><p>Voice command center. Speak missions into the same event bus used by every dashboard button.</p></div>
  <button class="g-btn g-btn-gold" onclick="openGoliathVoice()">🎤 Open Voice</button>
</section>
<section class="g-panel">
<h2>Voice Mission Control <span>Rockefeller briefing + team commands</span></h2>
<div class="g-inner">
  <p class="g-subtle">Open the voice panel, say “Hey Goliath,” then give the mission. Goliath will route the command to Rockefeller, Jessica, Einstein, Scout, Scorsese, or Shakespeare.</p>
  <button class="g-btn g-btn-green" onclick="openGoliathVoice()">Start Voice Command Center</button>
</div>
</section>
</main>
</div>
</body>
</html>