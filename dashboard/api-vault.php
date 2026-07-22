<?php
session_start();
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
?><!doctype html><html><head><title>API Vault</title><style>
body{font-family:Arial;margin:0;background:#f5f3ef}.hero{background:#111827;color:#fff;padding:30px}.hero h1{color:#c8a96e}
.wrap{padding:20px}.card{background:#fff;padding:20px;border-radius:12px}
</style></head><body>
<div class="hero"><h1>V18.5 API Vault</h1><p>Connection manager for Jessica OS.</p></div>
<div class="wrap">
<div class="card">
<h2>Services To Connect</h2>
<ul>
<li>Meta Business Manager</li>
<li>Google Ads</li>
<li>YouTube</li>
<li>Google Business Profile</li>
<li>Blotato</li>
<li>Canva</li>
<li>Retell</li>
<li>Twilio</li>
<li>HubSpot</li>
<li>Resend</li>
<li>Supabase</li>
<li>OpenAI</li>
</ul>
<p>Track status, account IDs, expiration dates, and connection health here.</p>
</div></div></body></html>