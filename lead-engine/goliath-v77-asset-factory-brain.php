<?php
/**
 * Goliath V77 — Executive Asset Factory Brain
 * Loads executive manuals, plugin dictionary, and enforces action-first asset contracts.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function gv77_root(){
  return realpath(__DIR__.'/..') ?: dirname(__DIR__);
}
function gv77_manual_path($exec){
  $exec=strtolower(preg_replace('/[^a-z0-9_\-]+/','',(string)$exec));
  return gv77_root().'/goliath-core/executives/'.$exec.'.md';
}
function gv77_get_manual($exec){
  $p=gv77_manual_path($exec);
  if(is_file($p)) return file_get_contents($p);
  $fallback=gv77_manual_path('goliath');
  return is_file($fallback)?file_get_contents($fallback):'';
}
function gv77_asset_contract(){
  return <<<'TXT'
V77 ASSET FACTORY REQUIREMENT:
Do not produce generic executive briefs. Produce an actionable business asset or a needs-data report.

Your answer must begin with:
ASSET_TYPE:
EXECUTIVE:
BUSINESS_GOAL:
ACTIONABLE_ASSET:
EVIDENCE:
CLICKABLE_OUTPUTS:
QUALITY_SCORE:
BUSINESS_IMPACT_SCORE:
HANDOFFS:
NEXT_ACTION:

If no asset was created, ACTIONABLE_ASSET must explain why and name the missing tool/data/source.
TXT;
}
function gv77_enhance_prompt($exec,$title,$prompt,$metadata=[]){
  $manual=gv77_get_manual($exec);
  $title=(string)$title;
  $prompt=(string)$prompt;
  $meta=json_encode(is_array($metadata)?$metadata:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  return "YOU ARE OPERATING UNDER GOLIATH OMNI V77 ASSET FACTORY.\n\n".
    "EXECUTIVE MANUAL:\n".$manual."\n\n".
    "CURRENT MISSION TITLE:\n".$title."\n\n".
    "CURRENT TASK CONTEXT:\n".$prompt."\n\n".
    "METADATA:\n".$meta."\n\n".
    gv77_asset_contract()."\n\n".
    "FINAL REMINDER: Quality over quantity. No fake contacts, fake companies, fake links, fake statistics, fake MLS data, or fake opportunities. If verified data is missing, say what is missing and what tool/source is required.";
}
function gv77_parse_asset($text){
  $keys=['ASSET_TYPE','EXECUTIVE','BUSINESS_GOAL','ACTIONABLE_ASSET','EVIDENCE','CLICKABLE_OUTPUTS','QUALITY_SCORE','BUSINESS_IMPACT_SCORE','HANDOFFS','NEXT_ACTION'];
  $out=[];
  foreach($keys as $i=>$k){
    $next=array_slice($keys,$i+1);
    $pattern='/'.$k.':\s*(.*?)(?=\n(?:'.implode('|',$next).'):\s*|\z)/is';
    if(preg_match($pattern,(string)$text,$m)) $out[strtolower($k)]=trim($m[1]);
  }
  return $out;
}
function gv77_quality_status($text){
  $a=gv77_parse_asset($text);
  $action=$a['actionable_asset']??'';
  $evidence=$a['evidence']??'';
  $click=$a['clickable_outputs']??'';
  $q=$a['quality_score']??'';
  $business=$a['business_impact_score']??'';
  $hasUrl=preg_match('/https?:\/\/|\/dashboard\/|\/uploads\/|\/media-assets\/|\.csv|\.html|\.mp4|\.png|record|lead_id|commission|task/i',$action."\n".$evidence."\n".$click);
  if(stripos($action,'NEEDS_')!==false || stripos($evidence,'NEEDS_')!==false) return 'needs_data';
  if(!$hasUrl) return 'missing_clickable_asset';
  return 'asset_ready';
}
?>