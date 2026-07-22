<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function gv79_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gv79_uid($p='asset'){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function gv79_json($v){return json_encode(is_array($v)?$v:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function gv79_install(){
  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_asset_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action_uid VARCHAR(90) NOT NULL UNIQUE,
    asset_source VARCHAR(80) NOT NULL DEFAULT 'deliverable',
    asset_id BIGINT NULL,
    executive_key VARCHAR(80) NULL,
    action_type VARCHAR(80) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'queued',
    prompt LONGTEXT NULL,
    result LONGTEXT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_asset (asset_source, asset_id),
    KEY idx_exec (executive_key),
    KEY idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_morning_priorities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    priority_uid VARCHAR(90) NOT NULL UNIQUE,
    priority_date DATE NOT NULL,
    rank_order INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    action_label VARCHAR(120) NOT NULL DEFAULT 'Review',
    action_url VARCHAR(255) NULL,
    business_reason LONGTEXT NULL,
    estimated_value VARCHAR(120) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'open',
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_date (priority_date),
    KEY idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  return ['ok'=>true,'tables'=>['goliath_asset_actions'=>gv79_table('goliath_asset_actions'),'goliath_morning_priorities'=>gv79_table('goliath_morning_priorities')]];
}
function gv79_asset_where($kind=''){
  $base="COALESCE(evidence_status,'')<>'legacy_archive' AND COALESCE(deliverable_type,'') NOT IN ('legacy_completion','legacy_brief') AND title NOT LIKE '%Production Mission:%' AND COALESCE(output_summary,'') NOT LIKE 'Legacy worker completion backfilled%'";
  $map=[
    'videos'=>"(executive_key IN ('scorsese','columbo','mozart') OR deliverable_type IN ('video_package','thumbnail_package','youtube_growth_package','media_package','ai_video_generation','mp4'))",
    'blogs'=>"(executive_key='shakespeare' OR deliverable_type IN ('publish_ready_blog','blog_article','landing_page','weekly_market_report','social_copy_package'))",
    'leads'=>"(executive_key IN ('scout','jessica') OR deliverable_type IN ('lead_list','verified_lead_csv','contact_enrichment_report','crm_enrichment','lead_score_report'))",
    'outreach'=>"(executive_key IN ('jessica','prospector','pandora') OR deliverable_type IN ('outreach_email_campaign','email_campaign','speaking_opportunity_pipeline','sponsor_pipeline','partnership_package'))",
    'seo'=>"(executive_key IN ('einstein','shakespeare') OR deliverable_type IN ('seo_audit','seo_schema_package','seo_aeo_growth','research_source_pack'))",
    'revenue'=>"(executive_key IN ('rockefeller','prospector','pandora') OR deliverable_type IN ('revenue_plan','sponsor_pipeline','speaking_opportunity_pipeline'))"
  ];
  return ($kind && isset($map[$kind])) ? "$base AND ".$map[$kind] : $base;
}
function gv79_count_sql($sql,$p=[]){try{return (int)((gdb_one($sql,$p)?:['c'=>0])['c']);}catch(Throwable $e){return 0;}}
function gv79_counts(){
  $c=[];
  foreach(['all'=>'','videos'=>'videos','blogs'=>'blogs','leads'=>'leads','outreach'=>'outreach','seo'=>'seo','revenue'=>'revenue'] as $k=>$kind)$c[$k]=gv79_count_sql("SELECT COUNT(*) c FROM goliath_deliverables WHERE ".gv79_asset_where($kind));
  if(gv79_table('internal_crm_contacts')){
    $c['homeowner_queued']=gv79_count_sql("SELECT COUNT(*) c FROM internal_crm_contacts WHERE research_status IN ('queued','needs_research','retry') AND property_address IS NOT NULL AND property_address<>''");
    $c['homeowner_assigned']=gv79_count_sql("SELECT COUNT(*) c FROM internal_crm_contacts WHERE research_status='assigned'");
    $c['phones']=gv79_count_sql("SELECT COUNT(*) c FROM internal_crm_contacts WHERE phone_1 IS NOT NULL AND phone_1<>''");
    $c['emails']=gv79_count_sql("SELECT COUNT(*) c FROM internal_crm_contacts WHERE email_1 IS NOT NULL AND email_1<>''");
  }
  return $c;
}
function gv79_build_morning_priorities(){
  gv79_install(); $today=date('Y-m-d');
  if(gdb_one("SELECT id FROM goliath_morning_priorities WHERE priority_date=? LIMIT 1",[$today])) return ['ok'=>true,'existing'=>true,'date'=>$today];
  $c=gv79_counts(); $items=[];
  if(($c['homeowner_assigned']??0)>0)$items[]=['Scout homeowner research is active','Open Scout CRM','/dashboard/scout-contact-workspace.php','Review assigned homeowners and watch for verified phone/email enrichment.','High lead value'];
  if(($c['leads']??0)>0)$items[]=['Review new lead assets','Open Lead Lists','/dashboard/goliath-assets.php?kind=leads','Look for call-ready sellers, contact lists, and CRM updates.','Immediate calls'];
  if(($c['outreach']??0)>0)$items[]=['Approve outreach drafts','Open Outreach','/dashboard/goliath-assets.php?kind=outreach','Jessica/Prospector opportunities need approval or revision.','Relationship/revenue'];
  if(($c['blogs']??0)>0)$items[]=['Review publish-ready blog/page assets','Open Blogs','/dashboard/goliath-assets.php?kind=blogs','Approve or revise SEO/AEO content designed to drive valuation leads.','Authority/leads'];
  if(($c['videos']??0)>0)$items[]=['Review video and media packages','Open Videos','/dashboard/scorsese-media-center.php','Approve finished videos/thumbnails or request edits.','Audience growth'];
  if(($c['seo']??0)>0)$items[]=['Review SEO/AEO improvements','Open SEO Work','/dashboard/goliath-assets.php?kind=seo','Einstein may have schema, FAQ, or page fixes ready.','Search leads'];
  while(count($items)<5)$items[]=['Let the Executive Council continue autonomous work','Open Missions','/dashboard/goliath-missions.php','No button needed. Let teams continue producing evidence-backed assets.','System growth'];
  $rank=1; foreach(array_slice($items,0,5) as $it){gdb_insert('goliath_morning_priorities',['priority_uid'=>gv79_uid('priority'),'priority_date'=>$today,'rank_order'=>$rank++,'title'=>$it[0],'action_label'=>$it[1],'action_url'=>$it[2],'business_reason'=>$it[3],'estimated_value'=>$it[4],'status'=>'open','metadata'=>gv79_json(['counts'=>$c])]);}
  return ['ok'=>true,'created'=>true,'date'=>$today,'counts'=>$c];
}
?>