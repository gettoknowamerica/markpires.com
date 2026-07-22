<?php
/**
 * Goliath Command Bus 1.0
 * Runs daily/recurring missions, queues agent jobs, dispatches them to local_ai_tasks,
 * and logs everything to goliath_events so Mission Control shows real work.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function cb_json($arr, $code=200){ http_response_code($code); echo json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit; }
function cb_key(){ return defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts'; }
$key = $_GET['key'] ?? ($_POST['key'] ?? '');
if ($key !== cb_key()) cb_json(['success'=>false,'error'=>'Unauthorized command bus key.'],403);

if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) cb_json(['success'=>false,'error'=>'Supabase config missing.'],500);

function sb_request($method, $endpoint, $body=null){
    $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
    $headers = [
        'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw, true);
    return ['ok'=>$http>=200 && $http<300, 'http'=>$http, 'data'=>is_array($data)?$data:[], 'raw'=>$raw, 'error'=>$err];
}
function sb_get($endpoint){ return sb_request('GET',$endpoint); }
function sb_insert($table, $row){ return sb_request('POST', $table, $row); }
function sb_patch($endpoint, $row){ return sb_request('PATCH', $endpoint, $row); }

function event_log($department, $title, $detail, $metadata=[], $confidence=88, $roi=0){
    return sb_insert('goliath_events', [
        'department'=>$department,
        'title'=>$title,
        'detail'=>$detail,
        'status'=>'active',
        'confidence'=>$confidence,
        'roi_estimate'=>$roi,
        'metadata'=>$metadata
    ]);
}

$today = date('Y-m-d');
$mode = $_GET['mode'] ?? 'daily';
$dispatchLimit = max(1, min(50, (int)($_GET['limit'] ?? 12)));

$missions = [
    'Jessica' => [
        'mission_type'=>'communications_morning_sweep', 'priority'=>95,
        'instruction'=>'Jessica: Review all new leads, pending follow-ups, unread replies, and hot opportunities. Send only the highest-value internal summary first. If a lead needs immediate attention, prepare a concise Mark-style email and log the recommended next action. Do not fabricate lead data.',
        'payload'=>['channels'=>['resend','mission_control'],'deliverable'=>'communications_brief']
    ],
    'Scout' => [
        'mission_type'=>'lead_discovery_sweep', 'priority'=>100,
        'instruction'=>'Scout: Find real potential real estate opportunities for Mark Pires in Fairfield County CT. Prioritize expired listings, FSBO, foreclosure/public record signals, luxury seller opportunities, relocation buyer signals, and verified contact paths. Return only reviewable leads with source, confidence, phone/email if found, and next recommended action. Never fabricate contact data.',
        'payload'=>['territory'=>'Fairfield County CT','target'=>'money-producing real estate leads','deliverable'=>'lead_batch']
    ],
    'Scorsese' => [
        'mission_type'=>'media_queue_sweep', 'priority'=>70,
        'instruction'=>'Scorsese: Review pending creative/video tasks. Identify the next highest-value video or short-form asset to produce for real estate, Discover CT, House Detective, Mark Inspires, BeatSeat, or LegacySaved. Return direct deliverables first: title, hook, edit plan, asset needs, and output format.',
        'payload'=>['deliverable'=>'video_plan_or_render_queue']
    ],
    'Mozart' => [
        'mission_type'=>'music_archive_sweep', 'priority'=>65,
        'instruction'=>'Mozart: Review pending music/archive tasks. Find live musical performances or long improvisations that could become complete songs. Identify hook, strongest verses, intro/outro, and suggested radio-ready structure. Do not alter original intent; preserve Mark’s creative voice.',
        'payload'=>['deliverable'=>'song_reconstruction_plan']
    ],
    'Shakespeare' => [
        'mission_type'=>'authority_content_sweep', 'priority'=>80,
        'instruction'=>'Shakespeare: Create or improve high-authority content that can generate leads or strengthen Mark’s brands. Prioritize Fairfield County real estate AEO/SEO, relocation content, town guides, Discover CT articles, LegacySaved articles, BeatSeat content, and Mark Inspires authority pieces. Return publish-ready copy or a direct outline.',
        'payload'=>['deliverable'=>'blog_email_social_copy']
    ],
    'Einstein' => [
        'mission_type'=>'intelligence_scoring_sweep', 'priority'=>75,
        'instruction'=>'Einstein: Analyze recent leads, campaigns, activity logs, and agent output. Score opportunity quality, urgency, intent, and evidence. Return concise findings and specific recommendations for Goliath and Rockefeller.',
        'payload'=>['deliverable'=>'intelligence_scorecard']
    ],
    'Columbo' => [
        'mission_type'=>'youtube_archive_growth_sweep', 'priority'=>85,
        'instruction'=>'Columbo: Act as Chief Archivist and YouTube Growth Director. Continue cataloging Mark Inspires the World and Discover Connecticut. Find gold in long videos, identify songs/stories/comedy/emotional moments, propose titles, chapters, descriptions, thumbnails, SEO/AEO tags, and shorts. Direct deliverables first: timestamp links/clips/metadata. Nothing of value is ever forgotten.',
        'payload'=>['channels'=>['Mark Inspires the World','Discover Connecticut'],'deliverable'=>'archive_gold_queue']
    ],
    'Prospector' => [
        'mission_type'=>'opportunity_mining_sweep', 'priority'=>72,
        'instruction'=>'Prospector: Find new opportunity channels that can make Mark money. Look for partnerships, niches, lead sources, local events, sponsorships, businesses, builders, town signals, AI tools, and market angles. Return practical opportunities with next steps.',
        'payload'=>['deliverable'=>'opportunity_list']
    ],
    'Rockefeller' => [
        'mission_type'=>'roi_priority_sweep', 'priority'=>90,
        'instruction'=>'Rockefeller: Review today’s agent opportunities and prioritize by likely revenue, speed to money, effort, and strategic value. Tell Goliath where Mark should focus first. Output must be short, evidence-based, and action-oriented.',
        'payload'=>['deliverable'=>'roi_priority_brief']
    ],
    'Pandora' => [
        'mission_type'=>'business_expansion_sweep', 'priority'=>78,
        'instruction'=>'Pandora: Expand the empire beyond real estate. Find speaking engagements, podcasts, music/BeatSeat opportunities, LegacySaved partnerships, Discover CT sponsorships, venues, radio, wineries, local business alliances, and strategic distribution opportunities. Return direct outreach targets and recommended first moves.',
        'payload'=>['brands'=>['BeatSeat','Mark Inspires','LegacySaved','Discover CT','Goliath Omni'],'deliverable'=>'expansion_targets']
    ]
];

$run = sb_insert('goliath_command_bus_runs', ['run_type'=>$mode,'status'=>'started','message'=>'Goliath Command Bus started','payload'=>['mode'=>$mode,'agents'=>array_keys($missions)]]);
$runId = $run['data'][0]['id'] ?? null;

$queued = 0; $skipped = 0; $errors = [];
foreach ($missions as $agent=>$m) {
    $dedupe = $today . ':' . $agent . ':' . $m['mission_type'];
    $exists = sb_get('goliath_agent_jobs?select=id,status&dedupe_key=eq.'.rawurlencode($dedupe).'&limit=1');
    if ($exists['ok'] && !empty($exists['data'])) { $skipped++; continue; }
    $row = [
        'agent'=>$agent,
        'mission_type'=>$m['mission_type'],
        'priority'=>$m['priority'],
        'status'=>'queued',
        'instruction'=>$m['instruction'],
        'payload'=>array_merge($m['payload'], ['daily_key'=>$dedupe, 'created_by'=>'Goliath Command Bus 1.0']),
        'dedupe_key'=>$dedupe,
        'source'=>'daily_morning_orders',
        'scheduled_for'=>date('c')
    ];
    $ins = sb_insert('goliath_agent_jobs', $row);
    if ($ins['ok']) { $queued++; event_log($agent, 'Morning Order Queued', $m['mission_type'].' assigned by Goliath Command Bus.', ['agent'=>$agent,'job'=>$m['mission_type'],'dedupe_key'=>$dedupe], 92, $agent==='Scout'?25000:0); }
    else { $errors[] = ['agent'=>$agent,'http'=>$ins['http'],'raw'=>$ins['raw']]; }
}

// Dispatch queued due jobs into local_ai_tasks for the local worker stack.
$due = sb_get('goliath_agent_jobs?select=*&status=eq.queued&scheduled_for=lte.'.rawurlencode(date('c')).'&order=priority.desc,created_at.asc&limit='.$dispatchLimit);
$dispatched = 0;
if ($due['ok']) {
    foreach ($due['data'] as $job) {
        $id = $job['id'];
        sb_patch('goliath_agent_jobs?id=eq.'.rawurlencode($id), ['status'=>'dispatched','started_at'=>date('c'),'attempts'=>((int)($job['attempts']??0))+1,'updated_at'=>date('c')]);
        $prompt = "GOLIATH COMMAND BUS JOB\n".
            "Agent: {$job['agent']}\n".
            "Mission Type: {$job['mission_type']}\n".
            "Priority: {$job['priority']}\n".
            "Instruction:\n{$job['instruction']}\n\n".
            "Return direct deliverables first. No fluff. If you find leads, include source, confidence, phone/email only when verified, and recommended next action. If you create content, include title, deliverable body/plan, and target platform. Always include a concise summary for Mission Control.";
        $task = sb_insert('local_ai_tasks', [
            'prompt'=>$prompt,
            'status'=>'queued',
            'priority'=>(int)$job['priority'],
            'metadata'=>[
                'agent'=>$job['agent'],
                'goliath_job_id'=>$id,
                'command_bus'=>'1.0',
                'mission_type'=>$job['mission_type'],
                'deliverable_mode'=>'direct_first',
                'source'=>'goliath-command-bus.php'
            ]
        ]);
        if ($task['ok']) {
            $dispatched++;
            event_log($job['agent'], 'Mission Dispatched', $job['mission_type'].' dispatched to local AI worker queue.', ['agent'=>$job['agent'],'goliath_job_id'=>$id,'command_bus'=>'1.0'], 94, $job['agent']==='Scout'?25000:0);
        } else {
            sb_patch('goliath_agent_jobs?id=eq.'.rawurlencode($id), ['status'=>'failed','error'=>'Failed to insert local_ai_task: '.$task['raw'],'updated_at'=>date('c')]);
            $errors[] = ['agent'=>$job['agent'],'job_id'=>$id,'local_ai_task_error'=>$task['raw']];
        }
    }
}

if ($runId) sb_patch('goliath_command_bus_runs?id=eq.'.rawurlencode($runId), ['status'=>empty($errors)?'completed':'completed_with_errors','jobs_queued'=>$queued,'jobs_dispatched'=>$dispatched,'message'=>'Command Bus run complete','completed_at'=>date('c'),'payload'=>['queued'=>$queued,'skipped'=>$skipped,'dispatched'=>$dispatched,'errors'=>$errors]]);

event_log('Goliath', 'Command Bus Run Complete', "Queued {$queued}, skipped {$skipped}, dispatched {$dispatched} agent missions.", ['queued'=>$queued,'skipped'=>$skipped,'dispatched'=>$dispatched,'errors'=>$errors,'mode'=>$mode], empty($errors)?96:72, $queued>0?50000:0);

cb_json([
    'success'=>empty($errors),
    'mode'=>$mode,
    'message'=>'Goliath Command Bus 1.0 run complete.',
    'queued'=>$queued,
    'skipped_existing_today'=>$skipped,
    'dispatched_to_local_ai_tasks'=>$dispatched,
    'errors'=>$errors,
    'next'=>'Open /dashboard/goliath-command-bus.php and Mission Control. Your local worker should now see queued local_ai_tasks.'
]);
