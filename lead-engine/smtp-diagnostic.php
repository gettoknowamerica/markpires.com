<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

$phpmailer_path = __DIR__ . '/PHPMailer/src/';
if (file_exists($phpmailer_path . 'PHPMailer.php')) {
  require_once $phpmailer_path . 'Exception.php';
  require_once $phpmailer_path . 'PHPMailer.php';
  require_once $phpmailer_path . 'SMTP.php';
} else {
  echo json_encode(['success'=>false,'error'=>'PHPMailer missing at /lead-engine/PHPMailer/src/']); exit;
}

try{
  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;
  $mail->Password = SMTP_PASS;
  $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = SMTP_PORT;
  $mail->setFrom(SMTP_USER, MARK_NAME);
  $mail->addAddress(MARK_EMAIL, MARK_NAME);
  $mail->isHTML(true);
  $mail->Subject = 'V22.1 SMTP Diagnostic — MarkPires.com';
  $mail->Body = '<h2>SMTP fallback is working.</h2><p>This means lead notifications can send even while Resend domain verification is pending.</p>';
  $mail->AltBody = 'SMTP fallback is working.';
  $mail->send();
  echo json_encode(['success'=>true,'method'=>'smtp','sent_to'=>MARK_EMAIL], JSON_PRETTY_PRINT);
}catch(Throwable $e){
  echo json_encode(['success'=>false,'method'=>'smtp','error'=>$e->getMessage()], JSON_PRETTY_PRINT);
}
?>