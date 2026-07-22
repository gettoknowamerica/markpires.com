<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
function ji_in(){ $raw=file_get_contents('php://input'); $j=json_decode($raw,true); return is_array($j)?$j:array_merge($_GET,$_POST); }
function ji_phone($p){ $p=preg_replace('/[^0-9+]/','',(string)$p); if(strlen($p)===10)return '+1'.$p; if(strlen($p)===11&&$p[0]==='1')return '+'.$p; return $p; }
function ji_sb($m,$ep,$payload=null){ $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/')); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]); if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload)); $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $d=json_decode($b,true); return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]]; }
function ji_rows($t,$q){ $r=ji_sb('GET',$t.'?'.$q); return $r['ok']?$r['data']:[]; }
function ji_log($tool,$args,$resp,$status='success'){ ji_sb('POST','jessica_mcp_tool_calls',[['tool_name'=>$tool,'phone'=>ji_phone($args['phone']??$args['from_number']??''),'call_id'=>(string)($args['call_id']??$args['retell_call_id']??''),'request_payload'=>$args,'response_payload'=>$resp,'status'=>$status,'created_at'=>date('c')]]); }
function ji_town($address){ foreach(['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Weston','Wilton','Fairfield','Ridgefield','Trumbull','Stratford','Bridgeport'] as $t){ if(stripos($address,$t)!==false)return $t; } return ''; }
function ji_mode($lead){ $b=strtolower(($lead['source']??'').' '.($lead['type']??'').' '.($lead['lead_type']??'').' '.($lead['message']??'').' '.($lead['tag']??'')); if(str_contains($b,'valuation')||str_contains($b,'home value'))return 'valuation'; if(str_contains($b,'seller')||str_contains($b,'sell'))return 'seller'; if(str_contains($b,'buyer')||str_contains($b,'buy'))return 'buyer'; return 'unknown'; }
function ji_opening($c){ $n=!empty($c['lead_name'])?' '.$c['lead_name']:''; $a=$c['lead_address']??''; if(($c['mode']??'')==='executive')return 'Mark, executive mode is active. I am connected to Jessica’s intelligence layer and ready to review leads, traffic, creative, campaigns, learning signals, and today’s highest-value actions.'; if(($c['mode']??'')==='valuation'){ $ap=$a?' for your property at '.$a:''; return 'Hi'.$n.', this is Jessica, Mark Pires’ personal assistant. Thanks for requesting a home value review'.$ap.'. I’m here to gather a few quick details so Mark can give you a more accurate local read than a basic online estimate. Is this for a home you own now, or a home you’re thinking about buying?'; } if(($c['mode']??'')==='seller')return 'Hi'.$n.', this is Jessica, Mark Pires’ personal assistant. Thanks for reaching out. I’m here to help Mark understand your selling plans and the details that matter most. Are you thinking about selling soon, or just starting to explore your options?'; if(($c['mode']??'')==='buyer')return 'Hi'.$n.', this is Jessica, Mark Pires’ personal assistant. Thanks for reaching out. I’m here to help Mark understand what you’re looking for and make sure he has the right details. What towns or areas are you most interested in?'; return 'Hi'.$n.', this is Jessica, Mark Pires’ personal assistant. Thanks for reaching out. I’m here to help and make sure Mark gets the right details from you. What’s the main reason you connected with Mark today?'; }
function tool_get_lead_context($args){ $phone=ji_phone($args['phone']??$args['from_number']??$args['from']??''); $lead=null; if($phone){ $vars=[$phone]; if(str_starts_with($phone,'+1'))$vars[]=substr($phone,2); if(str_starts_with($phone,'+'))$vars[]=substr($phone,1); foreach($vars as $v){ $f=ji_rows('leads','select=*&phone=eq.'.rawurlencode($v).'&order=created_at.desc&limit=1'); if($f){$lead=$f[0];break;} } } if(!$lead&&!empty($args['email'])){ $f=ji_rows('leads','select=*&email=eq.'.rawurlencode($args['email']).'&order=created_at.desc&limit=1'); if($f)$lead=$f[0]; }
$c=['mode'=>'unknown','phone'=>$phone,'lead_id'=>'','lead_source'=>$args['lead_source']??'','lead_type'=>$args['lead_type']??'','lead_name'=>$args['name']??'','lead_email'=>$args['email']??'','lead_address'=>$args['address']??'','town'=>$args['town']??'','lead_score'=>0,'route'=>'','estimated_value'=>(float)($args['estimated_value']??$args['value']??0),'estimated_commission'=>0];
if($lead){ $c['lead_id']=(string)($lead['id']??''); $c['lead_source']=$lead['source']??$c['lead_source']; $c['lead_type']=$lead['lead_type']??$lead['type']??$c['lead_type']; $c['lead_name']=$lead['name']??$c['lead_name']; $c['lead_email']=$lead['email']??$c['lead_email']; $c['lead_address']=$lead['address']??$c['lead_address']; $c['town']=$lead['town']??$lead['towns']??ji_town($c['lead_address']); $c['lead_score']=(int)($lead['adaptive_score']??$lead['lead_score']??0); $c['route']=$lead['route']??''; $c['mode']=ji_mode($lead); $c['estimated_value']=(float)($lead['estimated_value']??$lead['value']??$lead['budget']??$c['estimated_value']); } else { $b=strtolower($c['lead_source'].' '.$c['lead_type'].' '.$c['lead_address']); if(str_contains($b,'valuation')||str_contains($b,'home value'))$c['mode']='valuation'; elseif(str_contains($b,'seller'))$c['mode']='seller'; elseif(str_contains($b,'buyer'))$c['mode']='buyer'; }
if(!$c['town']&&$c['lead_address'])$c['town']=ji_town($c['lead_address']); if($c['estimated_value']>0)$c['estimated_commission']=$c['estimated_value']*.025; $c['opening_script']=ji_opening($c); $c['context_summary']='Mode: '.$c['mode'].'. Source: '.$c['lead_source'].'. Name: '.$c['lead_name'].'. Address: '.$c['lead_address'].'. Lead score: '.$c['lead_score'].'. Estimated value: '.$c['estimated_value'].'.'; $resp=['success'=>true,'context'=>$c,'opening_script'=>$c['opening_script'],'instructions'=>['say_opening_script_exactly'=>true,'do_not_say'=>'Do not say thanks for calling Mark’s office unless this is a generic inbound call with no lead context.','mode'=>$c['mode']],'lead'=>$lead]; ji_log('get_lead_context',$args,$resp); return $resp; }
function tool_get_executive_brief($args){
  $phone=ji_phone($args['phone']??$args['from_number']??$args['caller_number']??'');
  $utterance=strtolower($args['utterance']??$args['message']??$args['text']??$args['transcript']??'');

  $normalized=preg_replace('/[^a-z0-9]/','',$utterance);

  $validPhrases=[
    'timetomakethedonuts',
    'timetomakethedoughnuts',
    'timetomakedonuts',
    'timetomakedoughnuts',
    'makethedonuts',
    'makethedoughnuts'
  ];

  $phraseDetected=false;
  foreach($validPhrases as $phrase){
    $p=preg_replace('/[^a-z0-9]/','',strtolower($phrase));
    if($p && str_contains($normalized,$p)){
      $phraseDetected=true;
      break;
    }
  }

  if(!$phraseDetected){
    $hasTime=str_contains($utterance,'time');
    $hasMake=str_contains($utterance,'make');
    $hasDonut=str_contains($utterance,'donut') || str_contains($utterance,'doughnut') || str_contains($utterance,'doughnuts') || str_contains($utterance,'donuts');
    if($hasTime && $hasMake && $hasDonut){
      $phraseDetected=true;
    }
  }

  $isMark=in_array($phone,['+12032472655','2032472655'],true);

  // For Mark's private agent, phrase detection is enough. Retell may not always pass phone into the tool correctly.
  $authorized=$phraseDetected;

  if(!$authorized){
    $resp=[
      'success'=>false,
      'authorized'=>false,
      'message'=>'Executive mode is not authorized because the executive phrase was not detected.',
      'debug'=>[
        'phone'=>$phone,
        'utterance'=>$utterance,
        'normalized'=>$normalized,
        'phrase_detected'=>$phraseDetected,
        'is_mark_phone'=>$isMark
      ]
    ];
    ji_log('get_executive_brief',$args,$resp,'denied');
    return $resp;
  }

  $resp=[
    'success'=>true,
    'authorized'=>true,
    'mode'=>'executive',
    'opening_script'=>ji_opening(['mode'=>'executive']),
    'seller_top'=>ji_rows('seller_acquisition_director','select=property_address,town,acquisition_score,recommended_action,estimated_commission&status=eq.active&order=acquisition_score.desc&limit=10'),
    'traffic_top'=>ji_rows('traffic_performance','select=source_name,traffic_score,scale_recommendation,recommended_action&order=traffic_date.desc,traffic_score.desc&limit=10'),
    'learning_top'=>ji_rows('internal_asset_performance','select=asset_title,brand_pillar,learning_score,recommendation,recommended_next_action&order=performance_date.desc,learning_score.desc&limit=10'),
    'campaign_top'=>ji_rows('campaign_command_center','select=campaign_name,command_stage,command_score,recommended_daily_action&status=eq.active&order=command_score.desc&limit=10'),
    'debug'=>[
      'phone'=>$phone,
      'utterance'=>$utterance,
      'normalized'=>$normalized,
      'phrase_detected'=>$phraseDetected,
      'is_mark_phone'=>$isMark
    ]
  ];
  ji_log('get_executive_brief',$args,$resp);
  return $resp;
}
function tool_get_seller_opportunities($args){ $town=$args['town']??''; $q='select=property_address,town,acquisition_score,recommended_action,estimated_sale_price,estimated_commission&status=eq.active'; if($town)$q.='&town=ilike.'.rawurlencode('%'.$town.'%'); $q.='&order=acquisition_score.desc&limit=10'; $resp=['success'=>true,'town'=>$town,'opportunities'=>ji_rows('seller_acquisition_director',$q),'recommended_question'=>'Ask if they are thinking of selling soon, just curious, or looking for a private value review.']; ji_log('get_seller_opportunities',$args,$resp); return $resp; }
function tool_get_traffic_director($args){ $resp=['success'=>true,'traffic_top'=>ji_rows('traffic_performance','select=source_name,lead_count,qualified_count,call_count,appointment_count,traffic_score,scale_recommendation,recommended_action&order=traffic_date.desc,traffic_score.desc&limit=10')]; ji_log('get_traffic_director',$args,$resp); return $resp; }
function tool_get_learning_brain($args){ $brand=$args['brand_pillar']??''; $q='select=asset_title,brand_pillar,content_type,learning_score,recommendation,recommended_next_action,lead_count,call_count,estimated_commission&order=performance_date.desc,learning_score.desc&limit=10'; if($brand)$q='select=asset_title,brand_pillar,content_type,learning_score,recommendation,recommended_next_action,lead_count,call_count,estimated_commission&brand_pillar=eq.'.rawurlencode($brand).'&order=performance_date.desc,learning_score.desc&limit=10'; $resp=['success'=>true,'learning_top'=>ji_rows('internal_asset_performance',$q)]; ji_log('get_learning_brain',$args,$resp); return $resp; }
function ji_tools(){ return [ ['name'=>'get_lead_context','description'=>'Get caller/lead context, correct opening script, mode, lead score, address, estimated value, and next instructions.','inputSchema'=>['type'=>'object','properties'=>['phone'=>['type'=>'string'],'from_number'=>['type'=>'string'],'to_number'=>['type'=>'string'],'call_id'=>['type'=>'string'],'lead_source'=>['type'=>'string'],'lead_type'=>['type'=>'string'],'name'=>['type'=>'string'],'email'=>['type'=>'string'],'address'=>['type'=>'string'],'estimated_value'=>['type'=>'number'],'utterance'=>['type'=>'string']]]], ['name'=>'get_executive_brief','description'=>'Authorize and retrieve Mark executive brief when Mark says timetomakethedonuts.','inputSchema'=>['type'=>'object','properties'=>['phone'=>['type'=>'string'],'from_number'=>['type'=>'string'],'utterance'=>['type'=>'string']]]], ['name'=>'get_seller_opportunities','description'=>'Retrieve top seller opportunities and seller questions by town/address.','inputSchema'=>['type'=>'object','properties'=>['town'=>['type'=>'string'],'address'=>['type'=>'string'],'phone'=>['type'=>'string']]]], ['name'=>'get_traffic_director','description'=>'Retrieve top traffic sources and scaling recommendations.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]], ['name'=>'get_learning_brain','description'=>'Retrieve internal learning winners and recommendations by brand pillar.','inputSchema'=>['type'=>'object','properties'=>['brand_pillar'=>['type'=>'string']]]] ]; }
try{ $key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){ http_response_code(403); echo json_encode(['error'=>'Invalid key']); exit; } $input=ji_in(); $method=$input['method']??($_GET['method']??''); $id=$input['id']??null; if($_SERVER['REQUEST_METHOD']==='GET'&&!$method&&!isset($_GET['tool'])){ echo json_encode(['server'=>'Jessica Intelligence MCP','success'=>true,'tools'=>ji_tools(),'setup'=>'Add URL as MCP server in Retell and query parameter key=timetomakethedonuts.'],JSON_PRETTY_PRINT); exit; } if($method==='initialize'){ echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>['protocolVersion'=>'2024-11-05','capabilities'=>['tools'=>new stdClass()],'serverInfo'=>['name'=>'jessica-intelligence-mcp','version'=>'16.6.0']]]); exit; } if($method==='tools/list'){ echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>ji_tools()]]); exit; } if($method==='tools/call'){ $p=$input['params']??[]; $name=$p['name']??''; $args=$p['arguments']??[]; if($name==='get_lead_context')$res=tool_get_lead_context($args); elseif($name==='get_executive_brief')$res=tool_get_executive_brief($args); elseif($name==='get_seller_opportunities')$res=tool_get_seller_opportunities($args); elseif($name==='get_traffic_director')$res=tool_get_traffic_director($args); elseif($name==='get_learning_brain')$res=tool_get_learning_brain($args); else $res=['success'=>false,'error'=>'Unknown tool: '.$name]; echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>['content'=>[['type'=>'text','text'=>json_encode($res,JSON_PRETTY_PRINT)]],'structuredContent'=>$res]]); exit; } $tool=$_GET['tool']??($input['tool']??''); if($tool){ $args=array_merge($_GET,$input); unset($args['key'],$args['tool']); if($tool==='get_lead_context')$out=tool_get_lead_context($args); elseif($tool==='get_executive_brief')$out=tool_get_executive_brief($args); elseif($tool==='get_seller_opportunities')$out=tool_get_seller_opportunities($args); elseif($tool==='get_traffic_director')$out=tool_get_traffic_director($args); elseif($tool==='get_learning_brain')$out=tool_get_learning_brain($args); else $out=['success'=>false,'error'=>'Unknown tool']; echo json_encode($out,JSON_PRETTY_PRINT); exit; } echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Method not found']]); }catch(Throwable $e){ http_response_code(500); echo json_encode(['error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT); }
?>