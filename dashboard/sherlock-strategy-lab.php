<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
$name = basename($_SERVER['PHP_SELF'],'.php');
$labels=[
 'einstein-intelligence-center'=>'Einstein Intelligence Center',
 'sherlock-strategy-lab'=>'Sherlock Strategy Lab',
 'pandora-design-studio'=>'Pandora Design Studio',
 'mozart-audio-studio'=>'Mozart Audio Studio'
];
$title=$labels[$name]??'Executive Workspace';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title)?></title><style>body{margin:0;background:#030712;color:#fff;font-family:Arial}.wrap{max-width:900px;margin:auto;padding:24px}.panel{background:#07111f;border:1px solid #ffffff22;border-radius:24px;padding:20px}h1{color:#d4af37}.btn{display:inline-block;background:#d4af37;color:#111;padding:10px 12px;border-radius:12px;text-decoration:none;font-weight:900}</style></head><body><div class="wrap"><div class="panel"><h1><?=htmlspecialchars($title)?></h1><p>This executive room is registered and ready for the next sprint. The executive boots through the Constitution and will receive a full production workspace in the refinement pass.</p><a class="btn" href="/dashboard/executive-roster.php">Back to Executive Roster</a></div></div></body></html>