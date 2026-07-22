<?php function gactive34($f){return basename($_SERVER['SCRIPT_NAME']??'')===$f?'active':'';} ?>
<div class="g-mobileTop">
  <a href="/dashboard/goliath-mission-control.php"><img src="/dashboard/assets/goliath-ai-full-logo.png?v=36" alt="Goliath AI"></a>
  <select class="g-mobileSelect" onchange="if(this.value) location.href=this.value">
    <option value="/dashboard/goliath-mission-control.php">Mission Control</option>
    <option value="/dashboard/daily-hot-sheet.php">Daily Hot Sheet</option>
    <option value="/dashboard/followup-command.php">Follow-Up Command</option>
    <option value="/dashboard/scorsese-media-center.php">Scorsese Media Center</option>
    <option value="/dashboard/goliath-voice.php">Hey Goliath</option>
    <option value="/dashboard/goliath-system-health.php">System Health</option>
  </select>
</div>
<aside class="g-side">
  <div class="g-logo"><a href="/dashboard/goliath-mission-control.php"><img src="/dashboard/assets/goliath-ai-full-logo.png?v=36" alt="Goliath AI"></a></div>
  <nav class="g-nav">
    <a class="<?=gactive34('goliath-mission-control.php')?>" href="/dashboard/goliath-mission-control.php">Mission Control</a>
    <a class="<?=gactive34('daily-hot-sheet.php')?>" href="/dashboard/daily-hot-sheet.php">Daily Hot Sheet</a>
    <a class="<?=gactive34('goliath-mission.php')?>" href="/dashboard/goliath-mission.php">Mission Timeline</a>
    <a class="<?=gactive34('goliath-launch-candidate.php')?>" href="/dashboard/goliath-launch-candidate.php">Launch Candidate</a>
    <a class="<?=gactive34('goliath-system-health.php')?>" href="/dashboard/goliath-system-health.php">System Health</a>
    <div class="g-break"></div>
    <a class="<?=gactive34('lead-intelligence.php')?>" href="/dashboard/lead-intelligence.php">Website Leads</a>
    <a class="<?=gactive34('contact-numbers.php')?>" href="/dashboard/contact-numbers.php">Contact Numbers</a>
    <a class="<?=gactive34('goliath-opportunities.php')?>" href="/dashboard/goliath-opportunities.php">Seller Opportunities</a>
    <a class="<?=gactive34('new-expired-listings.php')?>" href="/dashboard/new-expired-listings.php">Expired Listings</a>
    <a class="<?=gactive34('followup-command.php')?>" href="/dashboard/followup-command.php">Follow-Up Command</a>
    <div class="g-break"></div>
    <a class="<?=gactive34('scorsese-media-center.php')?>" href="/dashboard/scorsese-media-center.php">Scorsese Media Center</a>
    <a class="<?=gactive34('director-test.php')?>" href="/dashboard/director-test.php">Director Test</a>
    <a class="<?=gactive34('goliath-voice.php')?>" href="/dashboard/goliath-voice.php">Hey Goliath</a>
    <div class="g-break"></div>
    <a class="<?=gactive34('goliath-omni-brain.php')?>" href="/dashboard/goliath-omni-brain.php">Goliath Omni Brain</a>
    <a class="<?=gactive34('campaigns.php')?>" href="/dashboard/campaigns.php">Campaigns</a>
  </nav>
</aside>