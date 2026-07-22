<?php
function goliath_ui_head(){
  echo '<link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4">';
  echo '<link rel="icon" type="image/png" href="/dashboard/assets/goliath-ai-full-logo.png?v=4">';
}
function goliath_ui_open(){
  echo '<div class="goliath-shell">';
  require __DIR__ . '/goliath-sidebar.php';
  echo '<main class="goliath-main">';
}
function goliath_ui_close(){
  echo '</main></div>';
}
?>