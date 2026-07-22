<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-asset-compounding-engine.php';
header('Content-Type: application/json');
echo json_encode(gac_health(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>