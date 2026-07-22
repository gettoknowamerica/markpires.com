<?php
/**
 * Missed Calls -> Jessica
 * Upload: /public_html/twilio/voice.php
 *
 * Twilio number webhook should point here.
 */

header('Content-Type: text/xml; charset=UTF-8');

$from = $_REQUEST['From'] ?? '';
$to   = $_REQUEST['To'] ?? '';

echo '<?xml version="1.0" encoding="UTF-8"?>';

?>
<Response>

  <!-- Try Mark first for 20 seconds -->
  <Dial timeout="20" callerId="<?php echo htmlspecialchars($from, ENT_QUOTES); ?>">
    <Number>+12032472655</Number>
  </Dial>

  <!-- If Mark does not answer, send to Jessica -->
  <Say voice="alice">
    Mark is unavailable at the moment. Please hold while I connect you with Jessica, Mark's assistant.
  </Say>

  <!-- Replace URL below with your Retell inbound number once finalized -->
  <Dial>
    <Number>+12037699448</Number>
  </Dial>

  <Say voice="alice">
    We were unable to connect your call. Please leave a message after the tone.
  </Say>

  <Record
      maxLength="180"
      playBeep="true"
      transcribe="true"
      transcribeCallback="https://markpires.com/twilio/voicemail-webhook.php" />

</Response>
