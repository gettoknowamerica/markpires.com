<?php
/**
 * V98.1 Shakespeare Seed Queue
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid981($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function ins981($row){return gdb_insert('shakespeare_content_queue',$row);}
 $seeded=[];

 $scenarios=[
  ['expired_seller','My House Expired — Am I Doomed or Can I Win the Relaunch?','seller','Expired seller recovery guide with hopeful, strategic tone.'],
  ['buyer_top_5','Top 5 Things Buyers Should Do Before Touring Homes','buyer','Buyer prep article: notepad, top 5 must-haves, top 5 deal breakers, spouse comparison, search focus.'],
  ['seller_top_5','Top 5 Things to Do Before Selling Your Connecticut Home','seller','Seller prep guide for pricing, presentation, repair list, timing, and marketing.'],
  ['california_to_ct','Why California Buyers Are Moving to Connecticut','relocation','Relocation authority piece for California buyers comparing lifestyle, space, schools, taxes, and modern homes.'],
  ['modern_ct_homes','Why Modern Homes in Connecticut Are the New Craze','luxury','Modern architecture, lifestyle, clean lines, smart homes, Fairfield County demand.'],
  ['waterfront_living','Connecticut Waterfront Living Guide','luxury','Waterfront buyer/seller guide: lifestyle, flood insurance, marinas, commuting, property considerations.'],
  ['absentee_owner','Selling an Absentee-Owned Home in Connecticut','seller','Human-touch guide for out-of-area owners, tenants, condition, prep, timing, and remote sale.']
 ];
 foreach($scenarios as $s){
  $exists=gdb_one("SELECT id FROM shakespeare_content_queue WHERE scenario=? AND status IN ('queued','working','complete') LIMIT 1",[$s[0]]);
  if($exists) continue;
  $id=ins981(['queue_uid'=>uid981('shq'),'request_type'=>'scenario_article','title'=>$s[1],'scenario'=>$s[0],'audience'=>$s[2],'prompt'=>$s[3],'status'=>'queued','priority'=>900,'source_executive'=>'mark','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
  $seeded[]=['id'=>$id,'scenario'=>$s[0],'title'=>$s[1]];
 }

 $towns=['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Fairfield','Wilton','Weston','Ridgefield','Trumbull','Monroe','Shelton'];
 foreach($towns as $town){
  $exists=gdb_one("SELECT id FROM shakespeare_content_queue WHERE request_type='town_authority_page' AND town=? AND status IN ('queued','working','complete') LIMIT 1",[$town]);
  if($exists) continue;
  $title=$town.' CT Complete Town Guide';
  $prompt='Create a living town authority page with overview, history, parks, restaurants, community hotspots, schools, commute, luxury neighborhoods, buyer/seller CTAs, Discover CT video section, FAQ, schema, and internal links.';
  $id=ins981(['queue_uid'=>uid981('shq'),'request_type'=>'town_authority_page','title'=>$title,'town'=>$town,'audience'=>'relocation,buyer,seller,luxury','prompt'=>$prompt,'status'=>'queued','priority'=>700,'source_executive'=>'mark','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
  $seeded[]=['id'=>$id,'town'=>$town,'title'=>$title];
 }

 echo json_encode(['ok'=>true,'version'=>'V98.1 Shakespeare Seed Queue','seeded_count'=>count($seeded),'seeded'=>$seeded,'next'=>'Run /lead-engine/shakespeare-authority-engine-v98-1.php?key=timetomakethedonuts&limit=10','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V98.1 Shakespeare Seed Queue','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>