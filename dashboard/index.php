<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';

function dashboard_password_ok($password){
  $candidates = [];
  if(defined('DASHBOARD_PASSWORD')) $candidates[] = DASHBOARD_PASSWORD;
  if(defined('GOLIATH_PASSWORD')) $candidates[] = GOLIATH_PASSWORD;
  if(defined('ADMIN_PASSWORD')) $candidates[] = ADMIN_PASSWORD;
  if(defined('AFTER_HOURS_CRON_KEY')) $candidates[] = AFTER_HOURS_CRON_KEY;
  $candidates[] = 'timetomakethedonuts';
  foreach($candidates as $p){ if($p && hash_equals((string)$p,(string)$password)) return true; }
  return false;
}
function safe_next($next){
  $next = trim((string)$next);
  if(!$next) return '/dashboard/core-links.php';
  if(str_starts_with($next,'http')){
    $u=parse_url($next);
    $next=($u['path']??'/dashboard/core-links.php').(isset($u['query'])?'?'.$u['query']:'');
  }
  if(!str_starts_with($next,'/')) $next='/'.$next;
  if(str_contains($next,"\n")||str_contains($next,"\r")) return '/dashboard/core-links.php';
  if($next==='/dashboard/'||$next==='/dashboard/index.php') return '/dashboard/core-links.php';
  return $next;
}
$next=$_GET['next']??'';
if(!$next && !empty($_SERVER['HTTP_REFERER'])){
  $ref=$_SERVER['HTTP_REFERER']; $host=$_SERVER['HTTP_HOST']??'';
  if(str_contains($ref,$host)&&str_contains($ref,'/dashboard/')&&!str_contains($ref,'/dashboard/index.php')) $next=$ref;
}
$next=safe_next($next);
if(isset($_GET['logout'])){$_SESSION=[];session_destroy();header('Location:/dashboard/');exit;}
if(!empty($_SESSION['mp_dashboard_auth'])){header('Location:'.$next);exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $next=safe_next($_POST['next']??$next);
  if(dashboard_password_ok($_POST['password']??'')){
    $_SESSION['mp_dashboard_auth']=true;
    $_SESSION['mp_dashboard_auth_time']=time();
    header('Location:'.$next);exit;
  }
  $error='Password was not accepted.';
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Login</title><style>
body{margin:0;background:#080c14;color:#f5f0e7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;min-height:100vh;display:grid;place-items:center}.card{width:min(520px,92vw);background:#111827;border:1px solid rgba(212,175,55,.25);border-radius:22px;padding:32px;box-shadow:0 30px 90px #0008}h1{font-family:Georgia,serif;color:#d4af37;margin:0 0 8px;font-size:38px}p{color:#b8b2a8;line-height:1.55}input{width:100%;box-sizing:border-box;background:#070b14;color:white;border:1px solid #2d3748;border-radius:12px;padding:14px;font-size:18px;margin:16px 0}button{width:100%;background:#d4af37;color:#111;border:0;border-radius:12px;padding:14px;font-weight:900;font-size:15px;cursor:pointer}.err{background:#3b1111;color:#fecaca;border-left:4px solid #ef4444;padding:10px;border-radius:10px}small{color:#7d8796}
</style></head><body><form class="card" method="post"><h1>Goliath OS</h1><p>Secure command access. After login you will return to the page you requested.</p><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><input type="password" name="password" placeholder="Password" autofocus><input type="hidden" name="next" value="<?=htmlspecialchars($next,ENT_QUOTES)?>"><button>Enter Goliath</button><p><small>Destination: <?=htmlspecialchars($next)?></small></p></form></body></html>