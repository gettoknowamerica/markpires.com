<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json');

echo json_encode([
 'success'=>true,
 'version'=>'V17.3',
 'features'=>[
   'Hook Extraction',
   'Emotional Scoring',
   'Curiosity Scoring',
   'Authority Scoring',
   'Caption Generator',
   'CTA Generator',
   'Hashtag Generator',
   'Jessica Director Notes'
 ],
 'next'=>'V17.4 Canva Publishing Bridge'
],JSON_PRETTY_PRINT);
