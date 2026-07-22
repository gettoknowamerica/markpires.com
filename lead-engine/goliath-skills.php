<?php
/**
 * Goliath Omni V53 — Agent Commissioning / Skills
 * Central place for what each agent must produce.
 */

function g53_json_contract($agent){
  $contracts = [
    'Scout' => [
      'deliverable_type' => 'lead_batch',
      'schema' => [
        'status' => 'completed|blocked',
        'summary' => 'plain English summary',
        'leads' => [[
          'name' => 'real name if known',
          'phone' => 'real phone if known',
          'email' => 'real email if known',
          'address' => 'property address if known',
          'town' => 'town/city',
          'lead_type' => 'seller|buyer|valuation|expired|fsbo|owner|referral|unknown',
          'source' => 'where this record came from',
          'confidence' => 0,
          'reason' => 'why this may become an appointment',
          'next_action' => 'call|email|research|nurture'
        ]],
        'missing_sources' => ['specific data source needed if no real leads can be returned'],
        'next_actions' => ['what should happen next']
      ]
    ],
    'Jessica' => [
      'deliverable_type' => 'communication',
      'schema' => ['status'=>'completed|blocked','emails'=>[],'followups'=>[],'notifications'=>[],'next_actions'=>[]]
    ],
    'Einstein' => [
      'deliverable_type' => 'analysis',
      'schema' => ['status'=>'completed','scores'=>[],'reasoning'=>[],'next_actions'=>[]]
    ],
    'Rockefeller' => [
      'deliverable_type' => 'priority_brief',
      'schema' => ['status'=>'completed','top_priorities'=>[],'projected_value'=>0,'call_order'=>[]]
    ],
    'Columbo' => [
      'deliverable_type' => 'archive_gold',
      'schema' => ['status'=>'completed|blocked','clips'=>[],'titles'=>[],'thumbnail_prompts'=>[],'seo'=>[],'missing_sources'=>[]]
    ],
    'Shakespeare' => [
      'deliverable_type' => 'content_draft',
      'schema' => ['status'=>'completed','title'=>'','html'=>'','markdown'=>'','meta_description'=>'','cta'=>'','social_posts'=>[]]
    ],
    'Scorsese' => [
      'deliverable_type' => 'video_package',
      'schema' => ['status'=>'completed','video_plan'=>'','captions'=>[],'thumbnail_prompt'=>'','publish_package'=>[]]
    ],
    'Mozart' => [
      'deliverable_type' => 'song_package',
      'schema' => ['status'=>'completed|blocked','hook'=>'','lyrics'=>'','arrangement'=>'','mix_plan'=>'','missing_sources'=>[]]
    ],
    'Prospector' => [
      'deliverable_type' => 'opportunity_batch',
      'schema' => ['status'=>'completed','opportunities'=>[],'contacts'=>[],'next_actions'=>[]]
    ],
    'Pandora' => [
      'deliverable_type' => 'expansion_opportunity',
      'schema' => ['status'=>'completed','opportunities'=>[],'business_line'=>'','contacts'=>[],'next_actions'=>[]]
    ]
  ];
  return $contracts[$agent] ?? ['deliverable_type'=>'work_output','schema'=>['status'=>'completed','summary'=>'','next_actions'=>[]]];
}

function g53_agent_prompt($agent, $job){
  $type = $job['job_type'] ?? ($job['task_type'] ?? 'general');
  $mission = $job['description'] ?? ($job['title'] ?? 'Run assigned mission.');
  $payload = $job['payload'] ?? [];
  if (is_string($payload)) $payload = json_decode($payload, true) ?: [];
  $contract = g53_json_contract($agent);
  $schema = json_encode($contract['schema'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

  $common = "You are {$agent}, a commissioned Goliath Omni executive agent.\n"
    . "Your job is to create a real deliverable, not a status report.\n"
    . "Return ONLY valid JSON. No markdown fences. No explanation outside JSON.\n"
    . "Do not fabricate names, phone numbers, emails, addresses, URLs, listings, source records, appointments, or results.\n"
    . "If the required source/data/API is unavailable, return status='blocked' and list exact missing_sources.\n"
    . "Deliverable type: {$contract['deliverable_type']}\n"
    . "Required JSON shape example:\n{$schema}\n\n"
    . "MISSION:\n{$mission}\n\n";

  if ($agent === 'Scout') {
    return $common
      . "SCOUT COMMISSION:\n"
      . "Find and normalize real lead opportunities only from owned, approved, or provided data. Prioritize today's inbound website leads, approved imports, homeowner_intelligence, compliant_lead_imports, and any source records included in this job payload.\n"
      . "Every usable lead must include at least a phone or email OR a property address with a source. If contact info is missing, mark next_action='research', not 'call'.\n"
      . "If no source records are available, return blocked and say which approved source is needed: MLS export, expired listing export, FSBO CSV, public-record import, vendor list, or website lead feed.\n"
      . "JOB PAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  }

  if ($agent === 'Jessica') {
    return $common
      . "JESSICA COMMISSION:\n"
      . "Coordinate communications. Draft admin notifications and follow-ups. Only claim an email was sent if the system supplied proof. Otherwise return a draft and next_action.\n"
      . "Use a concise professional Mark Pires tone.\n"
      . "JOB PAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  }

  if ($agent === 'Columbo') {
    return $common
      . "COLUMBO COMMISSION:\n"
      . "Catalog and repurpose Mark's YouTube/content archive. If no transcript/video/source URL is supplied, return blocked with the exact source needed.\n"
      . "When source is available, return timestamps, clip concepts, titles, descriptions, SEO/AEO keywords, thumbnail prompts, and shorts queue items.\n"
      . "JOB PAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  }

  if ($agent === 'Shakespeare') {
    return $common
      . "SHAKESPEARE COMMISSION:\n"
      . "Produce publish-ready content: title, slug, meta description, HTML, markdown, social captions, CTA, and internal-link suggestions.\n"
      . "JOB PAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  }

  return $common . "JOB TYPE: {$type}\nJOB PAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
