<?php
if (!function_exists('goliath_active')) {
  function goliath_active($file) {
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $current === $file ? 'active' : '';
  }
}
?>
<aside class="goliath-sidebar">
  <div class="goliath-logo">
    <a href="/dashboard/goliath.php" aria-label="Goliath Command Home">
      <img src="/dashboard/assets/goliath-ai-full-logo.png?v=41" alt="Goliath AI">
    </a>
  </div>
  <nav class="goliath-nav">
    <a class="<?=goliath_active('goliath.php')?>" href="/dashboard/goliath.php">Command Home</a>
    <a class="<?=goliath_active('lead-intelligence.php')?>" href="/dashboard/lead-intelligence.php">Lead Intelligence</a>
    <div class="goliath-nav-break"></div>
    <a class="<?=goliath_active('goliath-opportunities.php')?>" href="/dashboard/goliath-opportunities.php">Opportunities</a>
    <a class="<?=goliath_active('creative-generation-studio.php')?>" href="/dashboard/creative-generation-studio.php">Goliath Studio</a>
    <a class="<?=goliath_active('executive-command-center.php')?>" href="/dashboard/executive-command-center.php">Executive</a>
    <a class="<?=goliath_active('owner-research-queue.php')?>" href="/dashboard/owner-research-queue.php">Owner Queue</a>
    <a class="<?=goliath_active('goliath-communications.php')?>" href="/dashboard/goliath-communications.php">Communications</a>
    <a class="<?=goliath_active('large-media-manager.php')?>" href="/dashboard/large-media-manager.php">Media Manager</a>
    <div class="goliath-nav-break"></div>
    <a class="<?=goliath_active('mls-intelligence-manager.php')?>" href="/dashboard/mls-intelligence-manager.php">MLS Intelligence</a>
    <a class="<?=goliath_active('core-links.php')?>" href="/dashboard/core-links.php">Core Links</a>
  </nav>
</aside>
