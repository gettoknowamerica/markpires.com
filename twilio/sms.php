<?php
/**
 * SMS Auto Responder
 * Upload: /public_html/twilio/sms.php
 */

header('Content-Type: text/xml; charset=UTF-8');

$body = trim($_REQUEST['Body'] ?? '');

echo '<?xml version="1.0" encoding="UTF-8"?>';

?>
<Response>
  <Message>
Thank you for contacting Mark Pires and Discover Connecticut.

Jessica, Mark's assistant, received your message and a member of the team will follow up shortly.

If this is time-sensitive, call or text Mark directly at 203-247-2655.

Visit:
https://markpires.com
  </Message>
</Response>
