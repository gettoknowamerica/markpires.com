<?php
/**
 * Goliath V76.1 — Company Brain + Opportunity Radar
 * Reads V76 deliverables, memory, handoffs, pipeline events, and completions.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(file_exists(__DIR__.'/goliath-v76-operating-system.php')) require_once __DIR__.'/goliath-v76-operating-system.php';

function gcb_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gcb_all($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function gcb_one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function gcb_json($v){if(is_array($v))return $v; if(is_string($v)){$j=json_decode($v,true);return is_array($j)?$j:[];} return [];}
function gcb_score($row){
  $score=25;
  $type=strtolower((string)($row['deliverable_type']??''));
  $exec=strtolower((string)($row['executive_key']??''));
  $e=strtolower((string)($row['evidence_status']??''));
  $title=strtolower((string)($row['title']??''));
  $summary=strtolower((string)($row['output_summary']??''));
  if($e==='verified') $score+=25;
  if(in_array($exec,['scout','jessica','prospector','pandora','rockefeller'])) $score+=15;
  if(preg_match('/lead|seller|buyer|valuation|crm|follow|appointment|revenue|sponsor|press|speaking|partnership|seo|video|media|listing/',$type.' '.$title.' '.$summary)) $score+=20;
  $score+=(int)min(15, max(0, (int)($row['roi_score']??0)/7));
  return min(100,$score);
}
function gcb_company_snapshot(){
  if(function_exists('gv76_install')) @gv76_install();
  $deliverables=gcb_table('goliath_deliverables')?gcb_all("SELECT * FROM goliath_deliverables ORDER BY created_at DESC LIMIT 250"):[];
  $handoffs=gcb_table('executive_handoffs')?gcb_all("SELECT * FROM executive_handoffs ORDER BY created_at DESC LIMIT 100"):[];
  $pipeline=gcb_table('executive_pipeline_events')?gcb_all("SELECT * FROM executive_pipeline_events ORDER BY created_at DESC LIMIT 200"):[];
  $memory=gcb_table('executive_memory')?gcb_all("SELECT * FROM executive_memory WHERE active=1 ORDER BY updated_at DESC LIMIT 120"):[];
  $comfy=gcb_table('scorsese_comfy_jobs')?gcb_all("SELECT * FROM scorsese_comfy_jobs ORDER BY COALESCE(completed_at,updated_at,created_at) DESC LIMIT 100"):[];
  $notifications=gcb_table('goliath_notifications')?gcb_all("SELECT * FROM goliath_notifications ORDER BY created_at DESC LIMIT 80"):[];
  $byExec=[]; $verified=0; $needs=0; $today=0;
  foreach($deliverables as $d){
    $e=strtolower((string)($d['executive_key']??'goliath'));
    if(!isset($byExec[$e])) $byExec[$e]=['total'=>0,'verified'=>0,'needs'=>0,'score'=>0,'latest'=>null];
    $byExec[$e]['total']++;
    if(($d['evidence_status']??'')==='verified'){ $verified++; $byExec[$e]['verified']++; } else { $needs++; $byExec[$e]['needs']++; }
    $byExec[$e]['score']=max($byExec[$e]['score'], gcb_score($d));
    if(!$byExec[$e]['latest']) $byExec[$e]['latest']=$d['title']??'Deliverable';
    if(!empty($d['created_at']) && date('Y-m-d',strtotime($d['created_at']))===date('Y-m-d')) $today++;
  }
  $opps=[];
  foreach($deliverables as $d){
    $score=gcb_score($d);
    if($score>=55){
      $opps[]=[
        'source'=>'deliverable',
        'score'=>$score,
        'executive'=>$d['executive_key']??'goliath',
        'title'=>$d['title']??'Opportunity',
        'why'=>'High-value deliverable with '.($d['evidence_status']??'unknown').' evidence status.',
        'url'=>'/dashboard/goliath-deliverables.php?deliverable_id='.($d['id']??''),
        'next_action'=>$d['next_action']??'Review and choose next action.'
      ];
    }
  }
  foreach($handoffs as $h){
    if(($h['status']??'queued')==='queued'){
      $opps[]=[
        'source'=>'handoff',
        'score'=>70,
        'executive'=>$h['to_executive']??'goliath',
        'title'=>$h['title']??'Queued executive handoff',
        'why'=>'Queued handoff waiting for the next executive.',
        'url'=>'/dashboard/goliath-executive-memory.php?exec='.urlencode($h['to_executive']??''),
        'next_action'=>$h['expected_output']??'Complete the handoff.'
      ];
    }
  }
  usort($opps,function($a,$b){return ($b['score']??0)<=>($a['score']??0);});
  return [
    'ok'=>true,
    'version'=>'V76.1 Company Brain',
    'counts'=>[
      'deliverables'=>count($deliverables),
      'verified'=>$verified,
      'needs_evidence'=>$needs,
      'today'=>$today,
      'handoffs'=>count($handoffs),
      'pipeline_events'=>count($pipeline),
      'memory'=>count($memory),
      'comfy_jobs'=>count($comfy)
    ],
    'by_executive'=>$byExec,
    'opportunity_radar'=>array_slice($opps,0,12),
    'latest'=>[
      'deliverables'=>array_slice($deliverables,0,12),
      'handoffs'=>array_slice($handoffs,0,12),
      'pipeline'=>array_slice($pipeline,0,20),
      'memory'=>array_slice($memory,0,12),
      'notifications'=>array_slice($notifications,0,12)
    ],
    'generated_at'=>date('c')
  ];
}
?>