<?php
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';
function goliath_mc_agents(){
  return [
    'Goliath'=>'Chief Executive Officer & Executive Council','Jessica'=>'Communications, Scheduling & Follow-Up','Scout'=>'Lead Discovery & Market Intelligence','Scorsese'=>'Film Director & Creative Production','Mozart'=>'Music Composition & Audio Production','Shakespeare'=>'Blogs, Copywriting & Storytelling','Einstein'=>'Chief Intelligence Officer','Columbo'=>'Archive Intelligence & YouTube Growth','Prospector'=>'Opportunity Discovery & Trend Mining','Rockefeller'=>'Revenue Optimization & Financial Decisions','Pandora'=>'Business Expansion & Partnerships'
  ];
}
function scb_table_exists_for_mc($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function goliath_mc_live(){
  $agents=goliath_mc_agents(); $live=[];
  foreach($agents as $name=>$desc){$live[$name]=['queued'=>0,'working'=>0,'review'=>0,'complete'=>0,'blocked'=>0,'total'=>0,'progress'=>0,'phase'=>'standing by','title'=>'Standing by','active'=>null];}
  if(!gdb_enabled()) return $live;
  $rows=gdb_all("SELECT * FROM executive_commissions WHERE status IN ('queued','working','review','complete','blocked') ORDER BY updated_at DESC, created_at DESC LIMIT 300");
  foreach($rows as $r){
    $name=ucfirst(strtolower($r['executive_key'])); if(!isset($live[$name])) continue;
    $s=strtolower($r['status']); if($s==='failed')$s='blocked'; if(!isset($live[$name][$s]))$s='queued';
    $live[$name][$s]++; $live[$name]['total']++;
    if(in_array($s,['working','review','queued'])){
      $score=($s==='working'?400:($s==='review'?300:200))+(int)$r['priority']+(int)$r['progress'];
      if($score>($live[$name]['active']['score']??-1)){
        $live[$name]['active']=['score'=>$score,'row'=>$r,'bucket'=>$s];
      }
    }
  }
  if(scb_table_exists_for_mc('scorsese_comfy_jobs') && isset($live['Scorsese'])){
    try{
      $cq=(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('queued','retry')")?:['c'=>0])['c']);
      $cw=(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('working','rendering')")?:['c'=>0])['c']);
      $cc=(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('complete','completed')")?:['c'=>0])['c']);
      $live['Scorsese']['queued'] += $cq;
      $live['Scorsese']['working'] += $cw;
      $live['Scorsese']['complete'] += $cc;
      $live['Scorsese']['total'] += ($cq+$cw+$cc);
      if($cw>0){ $live['Scorsese']['progress']=45; $live['Scorsese']['phase']='rendering'; $live['Scorsese']['title']='Rendering ComfyUI media assets';}
      elseif($cq>0){ $live['Scorsese']['progress']=15; $live['Scorsese']['phase']='queued'; $live['Scorsese']['title']='ComfyUI media jobs queued';}
      elseif($cc>0){ $live['Scorsese']['progress']=100; $live['Scorsese']['phase']='complete'; $live['Scorsese']['title']='Rendered media ready for review';}
    }catch(Throwable $e){}
  }
  foreach($live as $name=>$v){
    if($v['active']){
      $r=$v['active']['row']; $p=(int)$r['progress'];
      $live[$name]['progress']=max(0,min(100,$p));
      $live[$name]['phase']=$r['current_phase']?:$v['active']['bucket'];
      $live[$name]['title']=$r['current_task']?:$r['title'];
    } elseif($v['complete']>0){$live[$name]['progress']=100;$live[$name]['phase']='complete';$live[$name]['title']='Latest work complete';}
  }
  return $live;
}
function goliath_mc_events($limit=80){
  if(!gdb_enabled()) return [];
  
  if(gal_has_table('goliath_notifications')){
    return gdb_all("SELECT created_at, executive AS department, notification_type AS event_type, title, message AS detail, priority, action_url AS link_url FROM goliath_notifications WHERE is_dismissed=0 ORDER BY created_at DESC LIMIT ".(int)$limit);
  }
  return gdb_all("SELECT created_at, executive_key AS department, notification_type AS event_type, title, message AS detail, priority, action_url AS link_url FROM executive_notifications ORDER BY created_at DESC LIMIT ".(int)$limit);
}
