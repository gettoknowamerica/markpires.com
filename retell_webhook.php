<?php
// ═══════════════════════════════════════════════════════════════
// RETELL AI MASTER WEBHOOK ROUTER (MarkPires.com SaaS)
// ═══════════════════════════════════════════════════════════════
header('Content-Type: application/json');

// 1. Capture the raw structural payload coming from Retell AI's server
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook event signature.']);
    exit();
}

// We only care when a voice call is fully finished and analyzed by the LLM
if ($data['event'] === 'call_analyzed') {
    $callData   = $data['call'];
    $analysis   = $callData['call_analysis'] ?? [];
    
    // Core operational metrics to pass to your database logs
    $phoneNumber = $callData['to_number'];
    $durationSeconds = $callData['duration_ms'] / 1000;
    $recordingUrl = $callData['recording_url'] ?? '';
    
    // 2. Parse Custom Variables and Outcomes from the Voice Assistant
    $callStatus = $analysis['call_completion_status'] ?? 'completed'; // e.g., completed, voicemail, dropped
    $userSentiment = $analysis['user_sentiment'] ?? 'Neutral';
    $summaryText = $analysis['call_summary'] ?? 'No summary generated.';
    
    // Check custom parameters passed back by your agent functions
    $customVariables = $callData['retell_llm_dynamic_variables'] ?? [];
    $leadStatus = $customVariables['status'] ?? 'Follow_Up_Required'; // Captured via update_lead_status tool
    
    // 3. INTERNAL ROUTING MATRIX
    if ($leadStatus === 'Clean_Booked') {
        // Secure the extracted appointment metrics from the function parameters
        $appointmentDate = $customVariables['date'] ?? '';
        $appointmentTime = $customVariables['time'] ?? '';
        
        // ACTION: Trigger Hubspot / Supabase calendar logging function here
        logBookedAppointment($phoneNumber, $appointmentDate, $appointmentTime, $summaryText, $recordingUrl);
        
    } elseif ($leadStatus === 'DNC') {
        // ACTION: Move the lead instantly to your internal global master blacklists
        blacklistPhoneNumberDNC($phoneNumber);
    } else {
        // ACTION: Update standard tracking tags for normal review loops
        updateGeneralLeadStatus($phoneNumber, $leadStatus, $summaryText);
    }
}

// Always respond with a clean 200 OK signature so Retell knows you received the payload safely
http_response_code(200);
echo json_encode(['success' => true]);

// ═══════════════════════════════════════════════════════════════
// BACKEND OPERATION HELPERS (Connect directly to your CRM architectures)
// ═══════════════════════════════════════════════════════════════

function logBookedAppointment($phone, $date, $time, $summary, $audio) {
    // Paste your standard Supabase Insert PDO queries or HubSpot Deal creation cURL code here
    // Example: Pushes notification to Mark's desk with the direct ElevenLabs audio link
}

function blacklistPhoneNumberDNC($phone) {
    // Paste your database target deletion query or blacklists array patcher code here
    // Example: UPDATE leads SET status = 'DNC_RESTRICTED' WHERE phone = :phone
}

function updateGeneralLeadStatus($phone, $status, $summary) {
    // Standard status logging mechanism to keep database entries tightly synced
}
?>
