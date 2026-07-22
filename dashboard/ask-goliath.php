<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/ask-goliath.php'));exit;}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ask Goliath</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/ask-goliath-v73.css?v=730"></head>
<body><div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main askGoliathPage">
<section class="agHero"><div><p class="eyebrow">Conversational Command Center</p><h1>Ask Goliath</h1><p>No button-first workflow. Just talk to Goliath like your executive partner. Say: <b>Hey Goliath, I have a question.</b></p></div><a class="btn dark" href="/dashboard/goliath-mission-control.php">Mission Control</a></section>
<section class="agShell"><div id="v111Conversation" class="agMessages v111Conversation"><div class="agMsg goliath"><strong>Goliath</strong><p>Hey Mark — I’m here. Ask me anything, or tell me what you want the executive team to work on.</p></div></div>
<form id="v111ChatForm" class="agForm v111ChatForm"><input id="v111ChatInput" autocomplete="off" placeholder="Hey Goliath, I have a question..." autofocus><button type="submit">Send</button><button id="v111VoiceButton" type="button">🎙️ Enable Hands-Free Goliath</button><button id="v111StopVoice" type="button">Stop</button></form>
<div id="v111Connection">CONNECTING</div><div id="v111VoiceState">Tap Start Live Voice once.</div><audio id="v111AudioPlayer" preload="auto" playsinline></audio><div class="agHint">Uses Goliath’s local worker/LLM loop. If a response is still processing, this page will keep checking.</div></section>
<script>
window.GOLIATH_V111_KEY=<?=json_encode($key)?>;
window.ASK_GOLIATH_KEY=window.GOLIATH_V111_KEY;
</script>
<script src="/dashboard/assets/goliath-live-v117.js?v=1170"></script>

</main></div></body></html>
