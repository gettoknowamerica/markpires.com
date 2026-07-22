<?php
session_start();
$agent=$_GET['agent']??'Scout';
header('Location: /dashboard/goliath-deliverables.php?agent='.rawurlencode($agent));
exit;
