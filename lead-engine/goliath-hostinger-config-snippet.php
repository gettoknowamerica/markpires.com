<?php
/* Paste these constants near the database section of lead-engine/config.php */
define('GOLIATH_DB_HOST', 'localhost'); // Hostinger often uses localhost when PHP is on same hosting account
define('GOLIATH_DB_NAME', 'u851465535_0lwuV');
define('GOLIATH_DB_USER', 'u851465535_YOUR_USER');
define('GOLIATH_DB_PASS', 'PASTE_DATABASE_PASSWORD_HERE');
define('GOLIATH_DB_PORT', 3306);

// Keep these false until Goliath CRM verifies cleanly on live forms.
define('GOLIATH_DISABLE_SUPABASE', false);
define('GOLIATH_DISABLE_HUBSPOT', false);
