<?php
/**
 * MarkPires.com Lead Engine Config
 * Keep this file private. Never link to it.
 */

date_default_timezone_set('America/New_York');

/* =========================================================
   MARK / SITE
========================================================= */

define('MARK_NAME', 'Mark Pires');
define('MARK_EMAIL', 'mark@markpires.com');
define('MARK_PHONE', '203-247-2655');
define('SITE_DOMAIN', 'markpires.com');
define('LOG_PATH', __DIR__ . '/logs');
define('MARK_DASHBOARD_PASSWORD', 'Mannytheman13$');



/* =========================================================
   SMTP / HOSTINGER EMAIL
========================================================= */

define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'mark@markpires.com');
define('SMTP_PASS', 'Mannytheman13$');

/* =========================================================
   RESEND
========================================================= */

define('RESEND_API_KEY', 're_J244Um12_3GbkfkcMm7UDH6SKwQ5Yks1Q');
define('RESEND_FROM_EMAIL', 'mark@markpires.com');
if (!defined('MARK_EMAIL')) {
    define('MARK_EMAIL', 'mark@markpires.com');
}

/* =========================================================
   SUPABASE
========================================================= */

define('SUPABASE_URL', 'https://swuhovlypndlosfzzivw.supabase.co');
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InN3dWhvdmx5cG5kbG9zZnp6aXZ3Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MTA1Nzg0NSwiZXhwIjoyMDk2NjMzODQ1fQ.t6kPBZxW_87AJb1Tt-uXYtVR7J6BJ83o-Zvc27-Drh0');
define('SUPABASE_LEADS_TABLE', 'leads');

/* =========================================================
   HUBSPOT
========================================================= */

define('HUBSPOT_PRIVATE_APP_TOKEN', 'pat-na2-5fc9202e-4a25-49d8-990d-ebdd7404cb2d');
define(
    'GOOGLE_CALENDAR_WEBHOOK_URL',
    'https://script.google.com/macros/s/AKfycbw7LvILPjcbkeNazO7JsEHAxIBaY_aX2RA7u7gAcOAqMdjWmzdudxAEQvnu7vAdMRE/exec'
);

define(
    'GOOGLE_CALENDAR_SECRET',
    'timetomakethedonuts'
);

/*
|--------------------------------------------------------------------------
| OpenAI / xAI
|--------------------------------------------------------------------------
*/

define('OPENAI_API_KEY', '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');
define('XAI_API_KEY', 'xai-Ej47L9zX6zPsxdYl3dQI0h2k8evC9vVve9lA4hdb5r97VuTicvW8TtYZr1pdrWuDnSH5XRUFxHIyq8Nr');


/*
|--------------------------------------------------------------------------
| YOUTUBE
|--------------------------------------------------------------------------
*/

define('YOUTUBE_API_KEY_MARK_INSPIRES', 'AIzaSyCie2ygpFygwPY1hpzzHjTOtBspNKIk9rY');
define('YOUTUBE_CHANNEL_MARK_INSPIRES', 'UCu3f0qHwbQiNXCX5mjIKL9Q');

define('YOUTUBE_API_KEY_DISCOVER_CT', 'AIzaSyDeAHIwB2s7yu6X0z9yDZk2LLaWDkK-wZM');
define('YOUTUBE_CHANNEL_DISCOVER_CT', 'UCyMNm7MbIR4H4LMZRgnKSPw');

/*
|--------------------------------------------------------------------------
| Google
|--------------------------------------------------------------------------
*/

define('GOOGLE_CLIENT_ID', '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');
define('GOOGLE_CLIENT_ID', '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');

define('GOOGLE_MAPS_API_KEY', 'AIzaSyCie2ygpFygwPY1hpzzHjTOtBspNKIk9rY');
define('GOOGLE_PLACES_API_KEY', 'AIzaSyCie2ygpFygwPY1hpzzHjTOtBspNKIk9rY');
define('GOOGLE_GEOCODING_API_KEY', 'AIzaSyCie2ygpFygwPY1hpzzHjTOtBspNKIk9rY');
define('GOOGLE_ADDRESS_VERIFICATION', 'AIzaSyCie2ygpFygwPY1hpzzHjTOtBspNKIk9rY');
define('GOOGLE_BUSINESS_PROFILE_API_KEY', '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');

/* =========================================================
   RETELL AI
========================================================= */

define('RETELL_API_KEY', 'key_7aa64a6c651b0cee5f747b645a97');
define('RETELL_AGENT_ID_MARK_PRIORITY', 'agent_589616eba5f222117367a146f1');
define('RETELL_AGENT_ID_REFERRAL', 'agent_589616eba5f222117367a146f1');
define('RETELL_FROM_NUMBER', '+12037699448');
define('RETELL_WEBHOOK_KEY', 'timetomakethedonuts');


/* =========================================================
   TWILIO SMS / AFTER-HOURS
========================================================= */

define('TWILIO_ACCOUNT_SID', 'AC4378abde06de015bd6c5be3216ec4b47');
define('TWILIO_AUTH_TOKEN', '4071f9c36e837542e2e07e2991784b4a');
define('TWILIO_SMS_FROM', '+12037699448');
define('AFTER_HOURS_START_HOUR', 22);
define('AFTER_HOURS_END_HOUR', 8);
define('AFTER_HOURS_CRON_KEY', 'timetomakethedonuts');

/* ===============================
   GOLIATH HOSTINGER MYSQL CONFIG
   Add this near the bottom of /lead-engine/config.php
   Replace the values with Hostinger database credentials.
   =============================== */

define('GOLIATH_MYSQL_HOST', 'localhost');
define('GOLIATH_MYSQL_DATABASE', 'u851465535_0IwuV');
define('GOLIATH_MYSQL_USER', 'u851465535_l2Uk7');
define('GOLIATH_MYSQL_USERNAME', GOLIATH_MYSQL_USER);
define('GOLIATH_MYSQL_PASSWORD', 'Mannytheman13$');
define('GOLIATH_MYSQL_PORT', 3306);
define('GOLIATH_MYSQL_CHARSET', 'utf8mb4');

define('GOLIATH_DB_HOST', GOLIATH_MYSQL_HOST);
define('GOLIATH_DB_NAME', GOLIATH_MYSQL_DATABASE);
define('GOLIATH_DB_USER', GOLIATH_MYSQL_USER);
define('GOLIATH_DB_PASS', GOLIATH_MYSQL_PASSWORD);
define('GOLIATH_DB_PORT', GOLIATH_MYSQL_PORT);

/* =========================================================
   ROUTING RULES
========================================================= */

define('MARK_PRIORITY_MIN_VALUE', 500000);

$MARK_PRIORITY_TOWNS = [
  'fairfield',
  'westport',
  'new canaan',
  'darien',
  'greenwich',
  'wilton',
  'ridgefield',
  'weston',
  'easton',
  'trumbull',
  'stamford',
  'norwalk'
];

define('REFERRAL_FEE_PERCENT', 35);