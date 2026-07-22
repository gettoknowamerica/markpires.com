<?php
/**
 * V99.4 Jessica → Shakespeare Link Handoff
 * Finds Jessica draft links that do not exist yet, creates an immediate publishable blog page,
 * registers the package in Shakespeare, and keeps the link alive before the email is sent.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function v994_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function v994_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v994_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v994_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(v994_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function v994_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(v994_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function v994_slug_title($slug){
   $slug=trim(preg_replace('/\.html$/','',basename($slug)));
   $words=array_map('ucfirst',explode('-',str_replace(['ct','cma'],['CT','CMA'],$slug)));
   $title=implode(' ',$words);
   $title=str_replace([' Ct ',' Cma '],[' CT ',' CMA '],$title);
   return $title;
 }
 function v994_public_path($url){
   $u=trim((string)$url);
   if(!$u)return '';
   if(strpos($u,'http')===0){$parts=parse_url($u);$u=$parts['path']??'';}
   if(!$u)return '';
   if($u[0]!=='/')$u='/'.$u;
   if(!preg_match('/\.html?$/i',$u))$u=rtrim($u,'/').'.html';
   return $u;
 }
 function v994_article($title,$path,$context=''){
   $safeTitle=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
   $context=trim(strip_tags((string)$context));
   $lead=$context ?: 'This guide was prepared by Mark Pires for Connecticut homeowners who want a practical, human-first plan before making a real estate decision.';
   return '<article class="mp-article">
<header class="hero"><p class="eyebrow">Mark Pires Connecticut Real Estate Guide</p><h1>'.$safeTitle.'</h1><p class="lede">'.htmlspecialchars($lead,ENT_QUOTES,'UTF-8').'</p></header>
<section><h2>Why this matters</h2><p>Real estate decisions are easier when you understand the timing, the property story, the market conditions, and the next best step. This guide gives you a clear starting point before you make a move.</p></section>
<section><h2>The smarter approach</h2><ol><li>Start with the actual property situation, not a generic online estimate.</li><li>Review timing, condition, location, and buyer demand.</li><li>Prepare the story and presentation before going public.</li><li>Use local context to make the outreach feel relevant instead of random.</li><li>Work from a specific plan so every next step has a purpose.</li></ol></section>
<section><h2>For absentee owners</h2><p>If you own a Connecticut property from a distance, the biggest challenge is usually not just price. It is coordination. Mark can help review condition, timing, access, market demand, possible prep work, and the cleanest path to a successful sale.</p></section>
<section><h2>How Mark can help</h2><p>Mark Pires combines Fairfield County experience, local storytelling, valuation strategy, Discover CT community knowledge, and cinematic marketing to help sellers understand their options before they commit to a plan.</p></section>
<section class="cta"><h2>Want a property-specific opinion?</h2><p>Call or text Mark Pires at <b>203-247-2655</b> or email <b>mark@markpires.com</b>.</p></section>
</article>';
 }
 function v994_page($title,$html,$meta=''){
   return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').' | Mark Pires</title><meta name="description" content="'.htmlspecialchars($meta ?: $title,ENT_QUOTES,'UTF-8').'"><style>body{margin:0;background:#f8fafc;color:#111827;font-family:Arial,sans-serif;line-height:1.65}.mp-article{max-width:980px;margin:auto;background:#fff;padding:34px;box-shadow:0 20px 60px #0001}.hero{background:linear-gradient(135deg,#111827,#334155);color:#fff;border-radius:22px;padding:30px;margin-bottom:24px}.eyebrow{color:#f6d679;text-transform:uppercase;font-weight:900;letter-spacing:.08em}.lede{font-size:20px;color:#e5e7eb}h1{font-size:42px;line-height:1.05;margin:0 0 10px}h2{color:#0f172a}.cta{background:#111827;color:#fff;border-radius:20px;padding:24px;margin-top:26px}.cta h2{color:#f6d679}@media(max-width:700px){.mp-article{padding:18px}.hero{padding:22px}h1{font-size:30px}}</style></head><body>'.$html.'</body></html>';
 }

 $limit=max(1,min(200,(int)($_GET['limit']??50)));
 $manual=v994_public_path($_GET['path']??'');
 $processed=[];$skipped=[];

 $items=[];
 if($manual){
   $items[]=['draft_id'=>null,'recommended_blog'=>$manual,'subject'=>v994_slug_title($manual),'body_text'=>'Manual blog link request from Goliath.'];
 } else {
   $items=gdb_all("SELECT id draft_id, recommended_blog, subject, body_text, body_html FROM jessica_email_drafts WHERE COALESCE(recommended_blog,'')<>'' ORDER BY created_at DESC LIMIT {$limit}")?:[];
 }

 foreach($items as $it){
   $path=v994_public_path($it['recommended_blog']??'');
   if(!$path){$skipped[]=['draft_id'=>$it['draft_id']??null,'reason'=>'no_path'];continue;}
   if(strpos($path,'/blog/')!==0)$path='/blog/'.basename($path);
   $abs=$_SERVER['DOCUMENT_ROOT'].$path;
   $slug=preg_replace('/\.html$/','',basename($path));
   $title=$it['subject'] ?: v994_slug_title($slug);
   if(stripos($title,'helpful')!==false || strlen($title)<12) $title=v994_slug_title($slug);
   $context=strip_tags(($it['body_text']??'') ?: ($it['body_html']??''));
   $exists=file_exists($abs);
   if(!$exists){
     if(!is_dir(dirname($abs))) mkdir(dirname($abs),0755,true);
     $html=v994_article($title,$path,$context);
     file_put_contents($abs,v994_page($title,$html,'A Connecticut real estate guide from Mark Pires.'));
   }

   $pkg=null;
   if(v994_table('shakespeare_content_packages')){
     $pkg=gdb_one("SELECT id FROM shakespeare_content_packages WHERE slug=? OR published_path=? LIMIT 1",[$slug,$path]);
     if(!$pkg){
       $pid=v994_insert('shakespeare_content_packages',[
        'package_uid'=>v994_uid('spkg'),
        'content_type'=>'scenario_article',
        'title'=>$title,
        'slug'=>$slug,
        'scenario'=>'jessica_requested',
        'audience'=>'seller',
        'primary_keyword'=>$title,
        'secondary_keywords'=>'Connecticut real estate, absentee owner, Fairfield County, Mark Pires',
        'recommended_for'=>'jessica_email,absentee_owner,seller_follow_up',
        'status'=>'published',
        'priority'=>950,
        'summary'=>'Auto-created because Jessica referenced this blog in an email draft.',
        'html_content'=>v994_article($title,$path,$context),
        'text_content'=>strip_tags(v994_article($title,$path,$context)),
        'meta_title'=>$title.' | Mark Pires',
        'meta_description'=>'A Connecticut real estate guide from Mark Pires.',
        'email_blurb'=>'Jessica can send this to seller and absentee-owner leads.',
        'jessica_use_case'=>'Auto-created for Jessica email link safety.',
        'einstein_status'=>'pending',
        'approval_status'=>'approved',
        'published_path'=>$path,
        'created_by'=>'jessica_shakespeare_handoff',
        'created_at'=>gdb_now(),
        'updated_at'=>gdb_now(),
        'approved_at'=>gdb_now(),
        'published_at'=>gdb_now()
       ]);
     } else $pid=(int)$pkg['id'];
   } else $pid=null;

   if(v994_table('shakespeare_content_queue')){
     $q=gdb_one("SELECT id FROM shakespeare_content_queue WHERE title=? AND status IN ('queued','working') LIMIT 1",[$title]);
     if(!$q){
       v994_insert('shakespeare_content_queue',[
        'queue_uid'=>v994_uid('shq'),
        'request_type'=>'enhance_existing_blog',
        'title'=>'Upgrade visuals and story depth: '.$title,
        'scenario'=>'jessica_requested',
        'audience'=>'seller',
        'prompt'=>'Jessica already referenced this page in an email. Shakespeare should now upgrade the article with stronger story, richer local detail, graphics requests for Pandora, video requests for Scorsese, and Einstein SEO/AEO review. Published path: '.$path,
        'status'=>'queued',
        'priority'=>980,
        'source_executive'=>'jessica',
        'package_id'=>$pid,
        'metadata'=>json_encode(['published_path'=>$path,'draft_id'=>$it['draft_id']??null]),
        'created_at'=>gdb_now(),
        'updated_at'=>gdb_now()
       ]);
     }
   }

   if(v994_table('relationship_timeline')){
     v994_insert('relationship_timeline',[
      'event_uid'=>v994_uid('rel'),
      'executive_key'=>'jessica',
      'event_type'=>'shakespeare_link_handoff',
      'title'=>'Jessica requested Shakespeare page: '.$title,
      'details'=>'Link is live at '.$path.' and queued for Shakespeare/Pandora/Scorsese enhancement.',
      'metadata'=>json_encode(['path'=>$path,'package_id'=>$pid,'draft_id'=>$it['draft_id']??null]),
      'priority'=>90,
      'is_new'=>1,
      'created_at'=>gdb_now()
     ]);
   }

   $processed[]=['draft_id'=>$it['draft_id']??null,'path'=>$path,'created_page'=>!$exists,'package_id'=>$pid,'title'=>$title];
 }

 echo json_encode(['ok'=>true,'version'=>'V99.4 Jessica Shakespeare Link Handoff','processed_count'=>count($processed),'processed'=>$processed,'skipped'=>$skipped,'next'=>'Run Shakespeare Authority Engine to enhance queued pages, then review in Shakespeare Authority Center.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V99.4 Jessica Shakespeare Link Handoff','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>