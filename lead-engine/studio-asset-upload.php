<?php
require_once __DIR__.'/config.php';
$key=$_POST['key']??$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);die('Invalid key');}
$dir=__DIR__.'/../dashboard/assets/uploads/studio';
if(!is_dir($dir)) mkdir($dir,0775,true);
if(empty($_FILES['asset']['tmp_name'])){die('No file uploaded');}
$name=preg_replace('/[^a-zA-Z0-9._-]+/','-',basename($_FILES['asset']['name']));
$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
$allowed=['png','jpg','jpeg','webp','gif','svg','mp4','mov'];
if(!in_array($ext,$allowed,true)){die('File type not allowed');}
move_uploaded_file($_FILES['asset']['tmp_name'],$dir.'/'.time().'-'.$name);
header('Location:/dashboard/goliath-studio.php?asset=uploaded&fresh='.time());
?>