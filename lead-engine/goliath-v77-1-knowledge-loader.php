<?php
/**
 * Goliath V77.1 — Knowledge Loader
 * Canonical load order:
 * Constitution -> Knowledge/plugins.md -> Knowledge/capabilities.json -> Executive Brain -> Mission
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function gv771_root(){
  return realpath(__DIR__.'/..') ?: dirname(__DIR__);
}
function gv771_read($path){
  return is_file($path) ? file_get_contents($path) : '';
}
function gv771_exec_key($exec){
  return strtolower(preg_replace('/[^a-z0-9_\-]+/','',(string)$exec));
}
function gv771_constitution(){
  $base = gv771_root().'/goliath-core/constitution';
  $txt = "";
  if(is_dir($base)){
    foreach(glob($base.'/*.md') ?: [] as $f){
      $txt .= "\n\n# Constitution: ".basename($f)."\n".gv771_read($f);
    }
  }
  return $txt;
}
function gv771_plugins_md(){
  return gv771_read(gv771_root().'/goliath-core/knowledge/plugins.md');
}
function gv771_capabilities_json(){
  return gv771_read(gv771_root().'/goliath-core/knowledge/capabilities.json');
}
function gv771_executive_brain($exec){
  $k = gv771_exec_key($exec);
  $p = gv771_root()."/goliath-core/executives/brains/{$k}/{$k}.md";
  if(is_file($p)) return gv771_read($p);
  $flat = gv771_root()."/goliath-core/executives/{$k}.md";
  if(is_file($flat)) return gv771_read($flat);
  return gv771_read(gv771_root()."/goliath-core/executives/brains/goliath/goliath.md");
}
function gv771_asset_contract(){
  return <<<'TXT'
V77.1 TARGETED ASSET CONTRACT:
No loose briefs. No generic strategy. No filler.

Every output must start with:
ASSET_TYPE:
EXECUTIVE:
BUSINESS_GOAL:
TARGET_AUDIENCE:
ACTIONABLE_ASSET:
EVIDENCE:
CLICKABLE_OUTPUTS:
QUALITY_SCORE:
BUSINESS_IMPACT_SCORE:
REVISION_PROMPT_SUGGESTIONS:
HANDOFFS:
NEXT_ACTION:

Rules:
- If the output cannot generate leads, authority, revenue, content, relationships, or a usable asset, mark it REVISION_NEEDED.
- If data is missing, create a NEEDS_DATA_REPORT with exact missing file/tool/source.
- Finished assets go to Workbench and Mark review, not through Goliath.
- Goliath coordinates the Executive Council and morning/evening briefs.
TXT;
}
function gv771_prompt($exec,$title,$mission,$metadata=[]){
  $exec = gv771_exec_key($exec ?: 'goliath');
  $meta = json_encode(is_array($metadata)?$metadata:[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  return "GOLIATH OMNI V77.1 KNOWLEDGE LOAD\n\n".
    "LOAD ORDER:\n1. Constitution\n2. Knowledge/plugins.md\n3. Knowledge/capabilities.json\n4. Executive Brain\n5. Current Mission\n6. Current Company State\n\n".
    "=== COMPANY CONSTITUTION ===\n".gv771_constitution()."\n\n".
    "=== SHARED PLUGIN KNOWLEDGE plugins.md ===\n".gv771_plugins_md()."\n\n".
    "=== STRUCTURED CAPABILITIES capabilities.json ===\n".gv771_capabilities_json()."\n\n".
    "=== EXECUTIVE BRAIN: {$exec} ===\n".gv771_executive_brain($exec)."\n\n".
    "=== CURRENT MISSION TITLE ===\n{$title}\n\n".
    "=== CURRENT MISSION ===\n{$mission}\n\n".
    "=== METADATA ===\n{$meta}\n\n".
    gv771_asset_contract()."\n\n".
    "FINAL STANDARD: targeted and powerful only. One useful, conversion-focused asset beats 100 generic outputs.";
}
?>