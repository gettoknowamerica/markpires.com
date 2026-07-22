<?php
if(!function_exists('goliath_h')){function goliath_h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$GOLIATH_SECTIONS=[
 'Command'=>[
  ['Goliath OS','/commandcenter.php','Master OS command center.'],
  ['Jessica Core','/dashboard/jessica-os.php','Talk to Jessica OS.'],
  ['Morning Brief','/dashboard/morning-brief.php','Daily Jessica intelligence briefing.'],
  ['Dashboard Home','/dashboard/','Main dashboard login/home.']
 ],
 'Hunter'=>[
  ['Hunter Dashboard','/dashboard/hunter-dashboard.php','Main Hunter intelligence OS.'],
  ['Municipal CSV Import','/dashboard/municipal-csv-import.php','Import town owner CSV files.'],
  ['Municipal Owner Qualifier','/dashboard/municipal-owner-qualifier.php','Review 7+ year and seller signals.'],
  ['Owner Enrichment Engine','/dashboard/owner-enrichment-engine.php','Research queue and search queries.'],
  ['Compliance Approval','/dashboard/compliance-contact-approval.php','Human compliance gate.'],
  ['Street Research','/dashboard/street-research.php','Create street research projects.'],
  ['Street Intelligence','/dashboard/street-intelligence.php','Street scoring and patterns.'],
  ['Run Hunter Pipeline','/lead-engine/run-hunter-pipeline.php?key=timetomakethedonuts','One-click pipeline.']
 ],
 'Hot Leads'=>[
  ['Lead Dashboard','/dashboard/leads.php','Incoming leads.'],
  ['Seller Acquisition Director','/dashboard/seller-acquisition-director.php','Seller lead priority.'],
  ['Hot Lead Heat Map','/dashboard/hot-leads-heat-map.php','Lead heat map.']
 ],
 'Creator Center'=>[
  ['Large Media Upload','/dashboard/large-media-upload.php','Chunk uploader for large raw videos.'],
  ['Video Review Studio','/dashboard/video-review-studio.php','Media player, clip notes, editor controls.'],
  ['Media Director','/dashboard/jessica-media-director.php','Raw media intake.'],
  ['Shorts Factory','/dashboard/jessica-shorts-factory.php','Hook moments.'],
  ['Audio Command Center','/dashboard/jessica-audio-command-center.php','Audio cleanup.'],
  ['Creative Command Center','/dashboard/jessica-creative-command-center.php','Human editor controls.']
 ],
 'Advertising'=>[
  ['Advertising Command Center','/dashboard/advertising-command-center.php','Provider setup and budgets.'],
  ['Campaign Command Center','/dashboard/campaign-command-center.php','Campaign command.'],
  ['Traffic Scaling Director','/dashboard/traffic-scaling-director.php','Scale winners.'],
  ['API Vault','/dashboard/api-vault.php','Connection manager.']
 ],
 'Run Builders'=>[
  ['Hunter Pipeline','/lead-engine/run-hunter-pipeline.php?key=timetomakethedonuts','Run Hunter pipeline.'],
  ['Street Intelligence','/lead-engine/build-street-intelligence.php?key=timetomakethedonuts','Refresh street scores.'],
  ['Morning Brief','/lead-engine/build-morning-brief.php?key=timetomakethedonuts','Build daily brief.'],
  ['Owner Enrichment 50','/lead-engine/build-owner-enrichment.php?key=timetomakethedonuts&limit=50','Run small safe batch.'],
  ['Compliance Builder','/lead-engine/build-compliance-contact-approval.php?key=timetomakethedonuts','Build compliance queue.']
 ]
];
?>
<style>
.gos-top{position:sticky;top:0;z-index:99999;background:rgba(15,23,42,.97);color:#fff;border-bottom:2px solid #c8a96e;height:48px;display:flex;align-items:center;box-shadow:0 8px 30px #0003;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.gos-logo{font-family:Georgia,serif;color:#c8a96e;font-weight:900;font-size:21px;padding:0 18px;white-space:nowrap}.gos-menu{display:flex;height:48px;overflow-x:auto}.gos-mi{position:relative;height:48px;display:flex;align-items:center;padding:0 13px;font-size:13px;font-weight:800;white-space:nowrap}.gos-mi:hover{background:#1f2937}.gos-drop{display:none;position:fixed;top:48px;background:white;color:#111;min-width:320px;max-width:460px;box-shadow:0 15px 50px #0004;border-radius:0 0 14px 14px;overflow:hidden}.gos-mi:hover .gos-drop{display:block}.gos-drop a{display:block;text-decoration:none;color:#111;padding:10px 14px;border-bottom:1px solid #eee}.gos-drop a:hover{background:#f6f1e8}.gos-drop strong{font-size:13px;display:block}.gos-drop span{font-size:11px;color:#666;display:block;margin-top:2px;line-height:1.3}
</style>
<div class="gos-top"><div class="gos-logo">Goliath OS</div><div class="gos-menu">
<?php foreach($GOLIATH_SECTIONS as $name=>$links): ?><div class="gos-mi"><?=goliath_h($name)?><div class="gos-drop">
<?php foreach($links as $l): ?><a href="<?=goliath_h($l[1])?>"><strong><?=goliath_h($l[0])?></strong><span><?=goliath_h($l[2])?></span></a><?php endforeach; ?>
</div></div><?php endforeach; ?>
</div></div>
