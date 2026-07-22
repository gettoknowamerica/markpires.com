<?php function gactive($f){return basename($_SERVER['SCRIPT_NAME']??'')===$f?'active':'';} ?>
<div class="mobileTop">
  <a href="/dashboard/goliath-mission-control.php"><img src="/dashboard/assets/goliath-ai-full-logo.png?v=319" alt="Goliath AI"></a>
  <select class="mobileNavSelect" onchange="if(this.value) location.href=this.value">
    <option value="/dashboard/goliath-mission-control.php">Mission Control</option>
    <option value="/dashboard/goliath.php">Executive Home</option>
    <option value="/dashboard/daily-hot-sheet.php">Daily Hot Sheet</option>
    <option value="/dashboard/scorsese-media-center.php">Scorsese Media Center</option>
    <option value="/dashboard/contact-numbers.php">Contact Numbers</option>
    <option value="/dashboard/lead-intelligence.php">Website Leads</option>
    <option value="/dashboard/new-expired-listings.php">Expired Listings</option>
    <option value="/dashboard/followup-command.php">Follow-Up Command</option>
  </select>
</div>
<aside class="side">
  <div class="logo"><a href="/dashboard/goliath-mission-control.php"><img src="/dashboard/assets/goliath-ai-full-logo.png?v=319" alt="Goliath AI"></a></div>
  <nav class="nav">
    <a class="<?=gactive('goliath-mission-control.php')?>" href="/dashboard/goliath-mission-control.php">Mission Control</a>
    <a class="<?=gactive('goliath.php')?>" href="/dashboard/goliath.php">Executive Home</a>
    <a class="<?=gactive('daily-hot-sheet.php')?>" href="/dashboard/daily-hot-sheet.php">Daily Hot Sheet</a>
    <a class="<?=gactive('scorsese-media-center.php')?>" href="/dashboard/scorsese-media-center.php">Scorsese Media Center</a>
    <a class="<?=gactive('contact-numbers.php')?>" href="/dashboard/contact-numbers.php">Contact Numbers</a>
    <a class="<?=gactive('lead-intelligence.php')?>" href="/dashboard/lead-intelligence.php">Website Leads</a>
    <div class="break"></div>
    <a class="<?=gactive('goliath-opportunities.php')?>" href="/dashboard/goliath-opportunities.php">Seller Opportunities</a>
    <a class="<?=gactive('new-expired-listings.php')?>" href="/dashboard/new-expired-listings.php">New Expired Listings</a>
    <a class="<?=gactive('followup-command.php')?>" href="/dashboard/followup-command.php">Follow-Up Command</a>
    <a class="<?=gactive('goliath-omni-brain.php')?>" href="/dashboard/goliath-omni-brain.php">Goliath Omni Brain</a>
    <a class="<?=gactive('local-ai-bridge.php')?>" href="/dashboard/local-ai-bridge.php">Local AI Bridge</a>
    <a class="<?=gactive('campaigns.php')?>" href="/dashboard/campaigns.php">Ad & Organic Campaigns</a>
  </nav>
</aside>