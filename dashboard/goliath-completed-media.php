<?php
header('Location: /dashboard/scorsese-studio-pro.php'.(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''));
exit;
