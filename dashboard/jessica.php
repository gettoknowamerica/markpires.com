<?php
session_start();
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location: /dashboard/?next=/dashboard/jessica.php');
  exit;
}
header('Location: /dashboard/jessica-drive-mode.php');
exit;
?>