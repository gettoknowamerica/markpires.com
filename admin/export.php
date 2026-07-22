<?php
session_start();
if (!isset($_SESSION['admin_auth'])) { header('Location: /admin/'); exit; }

define('SUPABASE_URL', '[swuhovlypndlosfzzivw.supabase.co](https://swuhovlypndlosfzzivw.supabase.co)');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InN3dWhvdmx5cG5kbG9zZnp6aXZ3Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MTA1Nzg0NSwiZXhwIjoyMDk2NjMzODQ1fQ.t6kPBZxW_87AJb1Tt-uXYtVR7J6BJ83o-Zvc27-Drh0');

$ch = curl_init(SUPABASE_URL . '/rest/v1/leads?order=created_at.desc&limit=1000');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>[
    'apikey: '.SUPABASE_KEY, 'Authorization: Bearer '.SUPABASE_KEY,
    'Content-Type: application/json',
]]);
$body  = curl_exec($ch); curl_close($ch);
$leads = json_decode($body, true) ?? [];

$filename = 'markpires-leads-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"$filename\"");

$fp = fopen('php://output', 'w');
if (!empty($leads)) {
    fputcsv($fp, array_keys($leads[0]));
    foreach ($leads as $lead) {
        fputcsv($fp, array_values($lead));
    }
}
fclose($fp);
