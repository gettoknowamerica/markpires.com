<?php
/**
 * V18.2 Jessica OS Master Manual + API Checklist
 * Upload: /public_html/dashboard/jessica-os-master-manual.php
 */
session_start();
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$apis = [
  ['Retell AI','Voice agent, MCP tools, Jessica calls','Connected','Retell API key, Agent IDs, webhook/MCP config'],
  ['Twilio','Phone number/call transport','Partial / Verify','Twilio Account SID, Auth Token, phone number'],
  ['Supabase','Database, system memory, dashboards','Connected','Service role key, project URL'],
  ['HubSpot','CRM lead storage','Partial / Verify','Private app token, contact properties'],
  ['Resend','Email notifications','Connected / Verify','API key, verified sending domain'],
  ['Blotato','Social posting/distribution','Next','API key, account/social connections'],
  ['Canva','Design creation/creative workflow','Next','Canva app/API access or manual design links'],
  ['Meta Ads','Facebook/Instagram ads','Needed before ad launch','Business Manager, Ad Account ID, access token, Pixel'],
  ['Google Ads','Search/YouTube/Display ads','Needed before ad launch','Developer token, OAuth client, customer ID'],
  ['YouTube','Video publishing/Shorts','Needed','OAuth client, channel permissions'],
  ['Google Business Profile','Local posts/reputation','Needed','GBP account access, OAuth'],
  ['OpenAI / image/video model','Creative generation, copy, scripts, images','Optional/Next','API key if using external generation'],
  ['Whisper / faster-whisper','Transcription / captions','Open-source local','Server install if rendering/transcribing on server'],
  ['FFmpeg','Video/audio processing','Open-source local','Server binary / shell access'],
  ['Demucs / RNNoise / SoX','Audio restoration','Open-source local','Optional server install']
];

$systems = [
  'Lead OS' => [
    'Home Valuation Funnel','Lead capture, seller valuation intent, AI follow-up.',
    'Seller Acquisition Director','Scores seller opportunities and priority follow-up.',
    'Traffic Scaling Director','Shows which sources deserve more budget/time.',
    'ROI Attribution','Connects spend, lead source, call, appointment, projected commission.',
    'Revenue Forecast','Forecasts pipeline and commission opportunity.'
  ],
  'Jessica Voice OS' => [
    'Retell Agent Jessica','Live AI concierge voice.',
    'Jessica MCP Server','Live tool bridge for get_lead_context and get_executive_brief.',
    'Executive Mode','Triggered by “time to make the doughnuts.”',
    'Conversation Learning','Turns calls into future improvements.',
    'Voice Intelligence','Call/transcript intelligence.'
  ],
  'Creator OS' => [
    'Media Director','Raw media intake and project scoring.',
    'Shorts Factory','Opus-style hook moments, director notes, effect stack.',
    'Content Intelligence','Turns transcript/notes into hooks, captions, CTAs, episode plans.',
    'Creative Command Center','Human final editor: captions, lower thirds, logos, title cards.',
    'Audio Command Center','Noise reduction, vocal isolation planning, EQ/compression/loudness.',
    'Render Kit','FFmpeg JSON/shell render recipes.',
    'Canva Bridge','Canva-ready creative briefs and approval to Blotato.'
  ],
  'Advertising OS' => [
    'Campaign Command Center','Campaign strategy and daily action recommendations.',
    'Ad Launch Director','Launch planning and handoff.',
    'Creative Intelligence Director','Creative scoring and improvement direction.',
    'Creative Review Studio','Review/edit ad creative before launch.',
    'Traffic Scaling Director','Move budget toward conversion winners.',
    'Live Ad Launch','Launch queue once API keys are connected.'
  ],
  'Distribution OS' => [
    'Blotato Distribution Director','Queues social posts across platforms.',
    'Blotato Direct Publishing','Direct publishing control once connected.',
    'SEO / AEO Content','Search and answer-engine content planning.',
    'Content Mine Director','Repurposes existing Mark/Discover CT/House Detective content.'
  ],
  'Executive OS' => [
    'Goliath Command Center','Mac OS style master navigation.',
    'Executive Snapshot','Daily operating view.',
    'Morning Executive Brief','Daily summary.',
    'Cron Monitor','Automation health.',
    'Internal Learning Brain','What Jessica learns over time.'
  ]
];

$cron = [
  '/lead-engine/cron-master.php?key=timetomakethedonuts',
  '/lead-engine/build-media-director.php?key=timetomakethedonuts',
  '/lead-engine/build-shorts-factory.php?key=timetomakethedonuts',
  '/lead-engine/build-render-kit.php?key=timetomakethedonuts',
  '/lead-engine/build-content-intelligence.php?key=timetomakethedonuts',
  '/lead-engine/build-canva-bridge.php?key=timetomakethedonuts',
  '/lead-engine/build-creative-command-center.php?key=timetomakethedonuts',
  '/lead-engine/build-audio-command-center.php?key=timetomakethedonuts',
  '/lead-engine/build-traffic-scaling-director.php?key=timetomakethedonuts',
  '/lead-engine/build-campaign-command-center.php?key=timetomakethedonuts',
  '/lead-engine/build-roi-attribution.php?key=timetomakethedonuts',
  '/lead-engine/build-revenue-forecast.php?key=timetomakethedonuts',
  '/lead-engine/build-morning-executive-brief.php?key=timetomakethedonuts&send=1'
];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica OS Master Manual</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:linear-gradient(135deg,#111827,#0b1020);color:white;padding:38px 24px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:48px;margin:0 0 8px}.hero p{color:#ddd;font-size:17px;max-width:1000px}.wrap{max-width:1700px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{background:#fff;border-radius:18px;box-shadow:0 3px 16px #0001;margin:18px 0;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:18px}.kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.box{background:#fff;border-radius:16px;padding:18px;box-shadow:0 3px 16px #0001}.num{font-size:30px;color:#c8a96e;font-weight:900}table{width:100%;border-collapse:collapse}td,th{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #eee;font-size:14px}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}code,pre{background:#111827;color:#fff;border-radius:10px;padding:12px;display:block;white-space:pre-wrap}.tag{display:inline-block;background:#111827;color:white;border-radius:999px;padding:4px 8px;font-size:11px;margin:2px}.needed{background:#7f1d1d}.next{background:#92400e}.ok{background:#14532d}@media(max-width:1000px){.grid,.kpi{grid-template-columns:1fr}.hero h1{font-size:34px}.wrap{padding:14px}}</style></head><body>
<section class="hero"><h1>Jessica OS Master Manual</h1><p>V18.2 owner manual, architecture map, API checklist, launch checklist, and operating guide for Goliath OS.</p></section>
<main class="wrap">
<section class="kpi"><div class="box"><div class="num">5</div>Core operating systems</div><div class="box"><div class="num">15+</div>Directors / dashboards</div><div class="box"><div class="num">5</div>Retell MCP tools</div><div class="box"><div class="num">V18</div>Current OS generation</div></section>

<section class="panel"><h2>What We Built</h2><div class="inner"><p><strong>Jessica OS / Goliath OS</strong> is an AI operating system for real estate growth, voice concierge automation, content creation, lead routing, advertising intelligence, and executive reporting. Jessica is now connected to a live intelligence layer through Retell MCP, and the command center connects the tools from one Mac-style interface.</p></div></section>

<div class="grid">
<?php foreach($systems as $name=>$items): ?>
<section class="panel"><h2><?=h($name)?></h2><div class="inner"><table><tr><th>System</th><th>Purpose</th></tr>
<?php for($i=0;$i<count($items);$i+=2): ?><tr><td><strong><?=h($items[$i])?></strong></td><td><?=h($items[$i+1])?></td></tr><?php endfor; ?>
</table></div></section>
<?php endforeach; ?>
</div>

<section class="panel"><h2>Retell MCP Tools</h2><div class="inner"><table><tr><th>Tool</th><th>Purpose</th></tr>
<tr><td><strong>get_lead_context</strong></td><td>Finds caller lead context, valuation/seller/buyer mode, opening script, lead score, and address.</td></tr>
<tr><td><strong>get_executive_brief</strong></td><td>Activates executive mode when Mark says “time to make the doughnuts.”</td></tr>
<tr><td><strong>get_seller_opportunities</strong></td><td>Pulls seller opportunities by town/address.</td></tr>
<tr><td><strong>get_traffic_director</strong></td><td>Pulls traffic winners and scale/optimize/watch recommendations.</td></tr>
<tr><td><strong>get_learning_brain</strong></td><td>Pulls content/brand learning winners and recommended next actions.</td></tr>
</table></div></section>

<section class="panel"><h2>API / Account Checklist</h2><div class="inner"><table><tr><th>Service</th><th>Use</th><th>Status</th><th>Needed</th></tr>
<?php foreach($apis as $a): $class=(stripos($a[2],'needed')!==false?'needed':(stripos($a[2],'next')!==false||stripos($a[2],'verify')!==false?'next':'ok')); ?>
<tr><td><strong><?=h($a[0])?></strong></td><td><?=h($a[1])?></td><td><span class="tag <?=$class?>"><?=h($a[2])?></span></td><td><?=h($a[3])?></td></tr>
<?php endforeach; ?>
</table></div></section>

<section class="panel"><h2>Recommended Cron / Builder URLs</h2><div class="inner"><pre><?php foreach($cron as $c) echo "https://markpires.com".$c."\n"; ?></pre></div></section>

<section class="panel"><h2>Launch Checklist</h2><div class="inner"><table><tr><th>Step</th><th>Action</th></tr>
<tr><td>1</td><td>Confirm commandcenter.php and Goliath nav are live.</td></tr>
<tr><td>2</td><td>Run all builder URLs once manually and verify no 500 errors.</td></tr>
<tr><td>3</td><td>Submit a new home valuation lead and confirm Jessica calls with correct valuation context.</td></tr>
<tr><td>4</td><td>Say “time to make the doughnuts” and confirm executive mode returns live data.</td></tr>
<tr><td>5</td><td>Upload one Discover CT / House Detective raw video and run Media Director → Shorts Factory → Content Intelligence → Creative Command → Audio Command.</td></tr>
<tr><td>6</td><td>Connect Blotato and Canva workflows.</td></tr>
<tr><td>7</td><td>Connect Meta/Google/YouTube ad accounts before live paid automation.</td></tr>
<tr><td>8</td><td>Begin with small budget and require human approval before Jessica reallocates spend.</td></tr>
</table></div></section>

<section class="panel"><h2>Upload Size Guidance</h2><div class="inner"><p>Current uploads use Hostinger local file storage, not Supabase Storage. The practical limit is usually PHP/Hostinger settings: <strong>upload_max_filesize</strong>, <strong>post_max_size</strong>, <strong>memory_limit</strong>, and request timeout. For large raw video, use Hostinger File Manager or SFTP into <code>/public_html/uploads/media/raw/</code> and then reference the file inside the Media Director workflow.</p></div></section>
</main></body></html>