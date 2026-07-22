<?php
/**
 * V18.3 Advertising Command Center Builder
 * Upload: /public_html/lead-engine/build-advertising-command-center.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function ad_sb($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>60
  ]);
  if($payload!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function ad_rows($t,$q){$r=ad_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $created=0; $actions=0; $updated=0; $errors=[];

  $existing=ad_rows('advertising_campaigns','select=id&limit=1');
  if(empty($existing)){
    $starter=[
      [
        'campaign_name'=>'Fairfield County Home Value Review',
        'provider'=>'meta',
        'campaign_type'=>'valuation',
        'town'=>'Fairfield County',
        'brand_pillar'=>'seller_authority',
        'objective'=>'lead_generation',
        'status'=>'needs_review',
        'daily_budget'=>10,
        'total_budget'=>100,
        'target_audience'=>'Fairfield County homeowners, likely sellers, downsizers, move-up sellers, luxury homeowners.',
        'offer'=>'Private local home value review from Mark Pyres.',
        'landing_page_url'=>'https://markpires.com/home-valuation.php',
        'creative_brief'=>'Premium seller ad. Hook: Your home may be worth more than an online estimate says. Visual: Fairfield County home / tasteful luxury / Mark Pyres authority.',
        'primary_text'=>'Online estimates miss the details that Fairfield County buyers actually pay for. Request a private local value review from Mark Pyres.',
        'headline'=>'What Is Your Home Really Worth?',
        'cta'=>'Request Private Value Review',
        'compliance_notes'=>'No guarantees. No discriminatory targeting. Housing category rules apply.',
        'command_score'=>82,
        'jessica_recommendation'=>'Approve for small-budget test. Strong seller intent and direct lead funnel.',
        'next_action'=>'Create Meta housing-category lead campaign and send creative to review.',
        'approval_status'=>'needs_review',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ],
      [
        'campaign_name'=>'House Detective Seller Story Campaign',
        'provider'=>'meta',
        'campaign_type'=>'house_detective',
        'town'=>'Fairfield County',
        'brand_pillar'=>'house_detective',
        'objective'=>'lead_generation',
        'status'=>'needs_review',
        'daily_budget'=>8,
        'total_budget'=>80,
        'target_audience'=>'Homeowners interested in premium listing marketing and standout presentation.',
        'offer'=>'Cinema-noir listing experience for sellers who want their home to stand out.',
        'landing_page_url'=>'https://markpires.com/home-valuation.php',
        'creative_brief'=>'Noir listing ad. Case file visuals, film grain, detective hook, emotional property storytelling.',
        'primary_text'=>'Most listings look the same. The House Detective turns your home into a story buyers remember.',
        'headline'=>'Every Home Has A Case',
        'cta'=>'Start Your Case File',
        'compliance_notes'=>'Housing category rules apply. Avoid personal attribute targeting.',
        'command_score'=>78,
        'jessica_recommendation'=>'Test after valuation campaign. Strong brand differentiation.',
        'next_action'=>'Generate creative and approve before launch.',
        'approval_status'=>'needs_review',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ],
      [
        'campaign_name'=>'Discover CT Relocation Audience Builder',
        'provider'=>'youtube',
        'campaign_type'=>'discover_ct',
        'town'=>'Fairfield County',
        'brand_pillar'=>'discover_ct',
        'objective'=>'audience_growth',
        'status'=>'draft',
        'daily_budget'=>5,
        'total_budget'=>50,
        'target_audience'=>'NYC relocation buyers, Connecticut lifestyle audience, Fairfield County movers.',
        'offer'=>'Discover CT local lifestyle content.',
        'landing_page_url'=>'https://markpires.com/',
        'creative_brief'=>'Promote high-performing Discover CT shorts. Build retargeting audience for future seller/buyer campaigns.',
        'primary_text'=>'Thinking about Connecticut? Discover the towns, people, and local stories that make Fairfield County special.',
        'headline'=>'Discover Connecticut Like A Local',
        'cta'=>'Watch More',
        'compliance_notes'=>'Brand/audience campaign. No restricted targeting.',
        'command_score'=>70,
        'jessica_recommendation'=>'Use as low-cost retargeting audience builder.',
        'next_action'=>'Wait until top-performing Discover CT clips are rendered.',
        'approval_status'=>'needs_review',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]
    ];
    $r=ad_sb('POST','advertising_campaigns',$starter);
    if($r['ok']) $created=count($starter); else $errors[]=$r['body'];
  }

  $campaigns=ad_rows('advertising_campaigns','select=*&order=command_score.desc&limit=100');
  foreach($campaigns as $c){
    $spend=(float)($c['spend']??0);
    $leads=(int)($c['leads']??0);
    $appointments=(int)($c['appointments']??0);
    $clicks=(int)($c['clicks']??0);
    $impressions=(int)($c['impressions']??0);
    $projected=(float)($c['projected_commission']??0);

    $ctr=$impressions>0?round(($clicks/$impressions)*100,2):0;
    $cpl=$leads>0?round($spend/$leads,2):0;

    $score=(int)($c['command_score']??50);
    if($leads>0) $score+=10;
    if($appointments>0) $score+=15;
    if($cpl>0 && $cpl<50) $score+=10;
    if($projected>0 && $spend>0) $score+=min(20,(int)(($projected/$spend)));
    $score=min(100,max(1,$score));

    $action='optimize';
    $reason='Improve creative, targeting, or landing fit before increasing budget.';
    $budgetChange=0;
    if($score>=85){
      $action='scale';
      $reason='Campaign has enough score/signal to justify scaling after human approval.';
      $budgetChange=round(((float)$c['daily_budget'])*0.20,2);
    } elseif($score<45 && $spend>20){
      $action='pause';
      $reason='Campaign score is weak relative to spend. Pause or rebuild creative.';
      $budgetChange=0;
    } elseif(($c['status']??'')==='needs_review'){
      $action='launch';
      $reason='Campaign is ready for human review and small-budget test.';
      $budgetChange=0;
    }

    ad_sb('PATCH','advertising_campaigns?id=eq.'.rawurlencode($c['id']),[
      'ctr'=>$ctr,
      'cpl'=>$cpl,
      'roi_score'=>$score,
      'command_score'=>$score,
      'jessica_recommendation'=>ucfirst($action).': '.$reason,
      'next_action'=>$reason,
      'updated_at'=>date('c')
    ]);
    $updated++;

    $existingAction=ad_rows('advertising_actions','select=id&advertising_campaign_id=eq.'.rawurlencode($c['id']).'&action_status=eq.recommended&limit=1');
    if(empty($existingAction)){
      $ar=ad_sb('POST','advertising_actions',[[
        'advertising_campaign_id'=>$c['id'],
        'action_type'=>$action,
        'action_status'=>'recommended',
        'reason'=>$reason,
        'budget_change'=>$budgetChange,
        'recommended_by'=>'Jessica',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]]);
      if($ar['ok']) $actions++; else $errors[]=$ar['body'];
    }
  }

  echo json_encode([
    'success'=>empty($errors),
    'starter_campaigns_created'=>$created,
    'campaigns_scored'=>$updated,
    'actions_created'=>$actions,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>