<?php
require_once __DIR__ . '/lead-brain-core.php';
$key=$_GET['key']??($_POST['key']??'');
$good=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$good) gb_json(['success'=>false,'error'=>'Bad key'],403);
$lead=[
  'name'=>'Jim Anderson',
  'email'=>'commissioning-test@example.com',
  'phone'=>'203-555-0199',
  'source'=>'Goliath commissioning test',
  'lead_type'=>'buyer',
  'current_city'=>'Bozeman',
  'current_state'=>'Montana',
  'target_towns'=>['Westport','Fairfield','New Canaan'],
  'budget'=>'$1.2M',
  'timeline'=>'60 days',
  'home_style'=>'colonial, modern, family-friendly',
  'interests'=>['schools','commute','waterfront','community','lower Fairfield County'],
  'heat_score'=>92,
  'pipeline_value'=>36000,
  'status'=>'new_hot_lead',
  'next_action'=>'Create Montana-to-Westport content and prepare Mark call notes.',
  'assigned_agent'=>'Jessica',
  'notes'=>'Commissioning lead to prove Lead Brain, Jessica, Shakespeare, Scorsese and Distribution OS.',
  'metadata'=>['commissioning'=>true,'origin'=>'Lead Brain test button']
];
$r=gb_supabase('POST','goliath_lead_brain',$lead);
if(!$r['ok']||empty($r['body'][0]['id'])) gb_json(['success'=>false,'step'=>'create_lead','response'=>$r],500);
$leadId=$r['body'][0]['id'];
gb_seed_journey($leadId,$lead['name']);
$context='Jim Anderson from Bozeman Montana is considering Westport/Fairfield/New Canaan, budget '.$lead['budget'].', timeline '.$lead['timeline'].', interests: schools, commute, waterfront, community.';
gb_queue_agent_commands($leadId,$context);
gb_supabase('POST','goliath_notifications',[['lead_id'=>$leadId,'channel'=>'dashboard','title'=>'🔥 Commissioning hot lead created','message'=>'Jim Anderson is ready for the Lead Brain proof run.','priority'=>'hot','status'=>'queued']]);
gb_json(['success'=>true,'lead_id'=>$leadId,'message'=>'Lead Brain commissioning lead created and agent commands queued.']);
