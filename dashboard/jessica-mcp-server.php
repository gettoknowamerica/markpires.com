<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php'; if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;} function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} function sb166d($m,$ep,$p=null){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));$b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];} $logs=sb166d('GET','jessica_mcp_tool_calls?select=*&order=created_at.desc&limit=200'); $key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY'; $base='https://markpires.com/lead-engine/retell/jessica-mcp.php'; $test=$base.'?key='.$key.'&tool=get_lead_context&phone=2032472655'; ?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V16.6 Jessica MCP Server</title><style>body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:24px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:16px}.btn{border:0;background:#c8a96e;color:#111;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer;text-decoration:none;display:inline-block}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}pre{white-space:pre-wrap;background:#111;color:#fff;padding:14px;border-radius:12px;max-height:360px;overflow:auto}</style></head><body><div class="header"><div class="brand">V16.6 Jessica MCP Intelligence Server</div><div>Live tool bridge between Retell MCP and Jessica’s Supabase intelligence.</div></div><main class="wrap"><p><a class="btn" target="_blank" href="<?=h($test)?>">Test Lead Context Tool</a><a class="btn light" href="/dashboard/jessica-intelligence-connector.php">V16.5 Connector</a></p><section class="panel"><h2>Retell MCP Settings</h2><div class="inner"><pre>Name:
Jessica Intelligence

URL:
<?=h($base)?>

Timeout:
10000

Query Parameter:
key = <?=h($key)?>

Headers:
None required</pre></div></section><section class="panel"><h2>Available MCP Tools</h2><div class="inner"><pre>get_lead_context
get_executive_brief
get_seller_opportunities
get_traffic_director
get_learning_brain</pre></div></section><section class="panel"><h2>Retell Prompt Instruction</h2><div class="inner"><pre>At the beginning of every call, call the MCP tool get_lead_context using the caller phone number, from_number, to_number, call_id, and any known lead source/name/address. If the response contains opening_script, say that opening_script exactly before asking any other question.

If Mark says "timetomakethedonuts", call get_executive_brief with Mark's phone number and the utterance. If authorized, switch to executive mode and summarize leads, traffic, seller opportunities, campaigns, and learning recommendations.

When discussing seller opportunities, call get_seller_opportunities. When discussing what is working, call get_learning_brain and get_traffic_director.</pre></div></section><section class="panel"><h2>Recent MCP Tool Calls</h2><table><tr><th>Time</th><th>Tool</th><th>Phone</th><th>Status</th><th>Response</th></tr><?php foreach($logs as $l):?><tr><td><?=h($l['created_at'])?></td><td><?=h($l['tool_name'])?></td><td><?=h($l['phone'])?></td><td><?=h($l['status'])?></td><td><pre><?=h(json_encode($l['response_payload'],JSON_PRETTY_PRINT))?></pre></td></tr><?php endforeach;?></table></section></main></body></html>