<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$quality_system = [
  'name'=>'Scorsese Quality Gate',
  'minimum_publish_score'=>88,
  'rule'=>'No Scorsese output should be treated as done until it passes the quality gate.',
  'rubric'=>[
    'hook'=>'First 1.5 seconds must create curiosity, motion, beauty, or emotional tension.',
    'stability'=>'Reject shaky, accidental, wandering, or dead footage unless intentionally cinematic.',
    'pacing'=>'Cut dead air, duplicate rooms, repeated takes, slow pans with no reveal, and weak transitions.',
    'story'=>'Every shot should advance the walkthrough: arrival, reveal, lifestyle, detail, emotional close.',
    'conversion'=>'The edit must make the viewer want to visit, call, save, share, or ask a question.',
    'brand'=>'Must feel like Mark Pires: cinematic, smart, local, premium, human, not generic AI.'
  ],
  'revision_template'=>'This does not pass Mark Pires/Scorsese standards yet. Recut with a stronger hook, remove shaky/fluff shots, improve pacing, choose only the best emotional moments, add a clear CTA, and return a cleaner review cut.'
];

echo json_encode(['success'=>true,'scorsese_quality_system'=>$quality_system],JSON_PRETTY_PRINT);
?>