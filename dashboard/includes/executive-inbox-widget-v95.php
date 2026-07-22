<?php
// V95 Executive Inbox Widget for Mission Control
if(!function_exists('v95mc_rows')){function v95mc_rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}}
if(!function_exists('v95mc_one')){function v95mc_one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}}
$v95_new=(int)(v95mc_one("SELECT COUNT(*) c FROM executive_deliverables WHERE archived=0 AND viewed=0")['c']??0);
$v95_items=v95mc_rows("SELECT * FROM executive_deliverables WHERE archived=0 ORDER BY viewed ASC, created_at DESC, id DESC LIMIT 6");
?>
<section class="feedBox" style="margin-top:14px">
  <h3>V95 Executive Inbox <?=($v95_new?'<span class="newBadge">NEW '.$v95_new.'</span>':'')?></h3>
  <?php if(!count($v95_items)): ?><div class="eventMini"><span>No V95 deliverables yet.</span><span>Run dispatcher</span></div><?php endif; ?>
  <?php foreach($v95_items as $it): ?>
    <a class="eventMini" href="/dashboard/goliath-executive-inbox.php">
      <span><?=((int)$it['viewed']?'':'🔴 ')?><?=htmlspecialchars(strtoupper($it['executive_key']).' — '.$it['title'],ENT_QUOTES,'UTF-8')?></span>
      <span><?=htmlspecialchars($it['created_at'],ENT_QUOTES,'UTF-8')?></span>
    </a>
  <?php endforeach; ?>
  <div class="promptActions"><a class="btn gold" href="/dashboard/goliath-executive-inbox.php">Open Inbox</a><a class="btn green" href="/dashboard/goliath-live-executives.php">Live Executives</a></div>
</section>