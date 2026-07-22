<?php
/**
 * V53 Capture Hook — makes every website lead immediately visible as a deliverable.
 */
require_once __DIR__ . '/goliath-v53-lib.php';

function g53_capture_lead_deliverable($lead, $supabaseResult=null){
  if(!is_array($lead)) return ['ok'=>false,'error'=>'bad_lead'];
  $name = $lead['name'] ?: 'New Website Lead';
  $score = $lead['lead_score'] ?? '';
  $title = 'New Lead: ' . $name . ($score !== '' ? ' — Score ' . $score : '');
  $leadJson = [
    'status'=>'completed',
    'summary'=>'Inbound website lead captured and ready for Mark/Jessica.',
    'leads'=>[[
      'name'=>$lead['name'] ?? '',
      'phone'=>$lead['phone'] ?? '',
      'email'=>$lead['email'] ?? '',
      'address'=>$lead['address'] ?? '',
      'town'=>$lead['town'] ?? '',
      'lead_type'=>$lead['type'] ?? 'website',
      'source'=>$lead['source'] ?? 'markpires.com',
      'confidence'=>$lead['lead_score'] ?? 80,
      'reason'=>$lead['message'] ?? ($lead['goal'] ?? ''),
      'next_action'=>'call_or_email'
    ]],
    'route'=>$lead['route'] ?? '',
    'supabase'=>$supabaseResult
  ];
  $text = g53_format_lead_batch($leadJson['leads']);
  $d = g53_create_deliverable('Scout','lead_batch',$title,$text,$leadJson,[
    'source'=>'capture.php',
    'lead'=>$lead,
    'route'=>$lead['route'] ?? '',
    'capture_hook'=>'v53'
  ], ($lead['lead_score'] ?? 0) >= 80 ? 'critical' : 'high');
  $link = '/dashboard/goliath-deliverables.php?agent=Scout';
  if($d['ok'] && is_array($d['data']) && isset($d['data'][0]['id'])) $link = '/dashboard/goliath-deliverables.php?id=' . rawurlencode($d['data'][0]['id']);
  g53_event('Scout',$title,'New inbound lead is ready. Click for phone/email/address.',$link,['lead'=>$lead,'source'=>'capture_hook_v53'],95,($lead['lead_score'] ?? 0)*100);
  g53_event('Jessica','Jessica follow-up needed: '.$name,'New lead requires communication handling.',$link,['lead'=>$lead,'source'=>'capture_hook_v53'],94,0);
  return ['ok'=>$d['ok'],'deliverable'=>$d,'link'=>$link];
}
