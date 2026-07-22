<?php
/**
 * ADMIN DASHBOARD — /admin/index.php
 * Mark Pires Lead Engine
 */

session_start();

define('SUPABASE_URL', '[swuhovlypndlosfzzivw.supabase.co](https://swuhovlypndlosfzzivw.supabase.co)');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InN3dWhvdmx5cG5kbG9zZnp6aXZ3Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MTA1Nzg0NSwiZXhwIjoyMDk2NjMzODQ1fQ.t6kPBZxW_87AJb1Tt-uXYtVR7J6BJ83o-Zvc27-Drh0');
define('LOG_DIR', dirname(__DIR__) . '/lead-engine/logs');

// ── Simple password auth ──────────────────────────────────────────────────
$ADMIN_PASS_HASH = '$2y$10$CHANGETHIS_run_password_hash_first'; 
// To generate: run php -r "echo password_hash('YourPassword123!', PASSWORD_DEFAULT);"
// then paste the output above

if (isset($_POST['password'])) {
    if (password_verify($_POST['password'], $ADMIN_PASS_HASH)) {
        $_SESSION['admin_auth'] = true;
        $_SESSION['admin_time'] = time();
    } else {
        $auth_error = 'Incorrect password.';
    }
}

// Auto-logout after 4 hours
if (isset($_SESSION['admin_time']) && (time() - $_SESSION['admin_time']) > 14400) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// ── Login page ────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_auth'])) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login — Mark Pires</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#1a1a2e;display:flex;align-items:center;justify-content:center;
         min-height:100vh;font-family:Georgia,serif}
    .box{background:#fff;padding:50px 40px;border-radius:10px;text-align:center;
         width:340px;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
    .logo{font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#999;margin-bottom:8px}
    h2{color:#1a1a2e;font-size:22px;margin-bottom:30px}
    input[type=password]{width:100%;padding:13px 15px;border:1px solid #ddd;border-radius:5px;
         font-size:15px;margin-bottom:15px;outline:none;transition:border .2s}
    input[type=password]:focus{border-color:#c8a96e}
    button{width:100%;background:#1a1a2e;color:white;padding:13px;border:none;border-radius:5px;
           font-size:15px;cursor:pointer;letter-spacing:1px;transition:background .2s}
    button:hover{background:#c8a96e}
    .error{color:#e74c3c;font-size:13px;margin-top:10px}
    .lock{font-size:36px;margin-bottom:15px}
  </style>
</head>
<body>
  <div class="box">
    <div class="lock">🔒</div>
    <div class="logo">Mark Pires · Lead Engine</div>
    <h2>Admin Access</h2>
    <form method="POST">
      <input type="password" name="password" placeholder="Enter password" autofocus>
      <button type="submit">ENTER DASHBOARD</button>
      <?php if (isset($auth_error)): ?>
        <div class="error"><?= htmlspecialchars($auth_error) ?></div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
<?php
    exit;
}

// ── Supabase query helper ─────────────────────────────────────────────────
function supabase_get(string $endpoint, string $params = ''): array {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint . ($params ? '?' . $params : '');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: '           . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: count=exact',
        ],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

function supabase_patch(string $table, string $filter, array $data): int {
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?' . $filter;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'apikey: '           . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);
    curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http;
}

// ── Handle AJAX actions from dashboard ────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $uid    = preg_replace('/[^a-z0-9_.]/', '', $_POST['uid'] ?? '');
        $status = in_array($_POST['status'], ['new','contacted','nurturing','hot','converted','dead'])
                  ? $_POST['status'] : 'new';
        $http   = supabase_patch('leads', "uid=eq.$uid", ['status' => $status, 'contacted' => true]);
        echo json_encode(['ok' => $http < 300]);
        exit;
    }

    if ($action === 'add_note') {
        $uid  = preg_replace('/[^a-z0-9_.]/', '', $_POST['uid'] ?? '');
        $note = htmlspecialchars(strip_tags($_POST['note'] ?? ''), ENT_QUOTES, 'UTF-8');
        $http = supabase_patch('leads', "uid=eq.$uid", ['notes' => $note]);
        echo json_encode(['ok' => $http < 300]);
        exit;
    }

    echo json_encode(['ok' => false]);
    exit;
}

// ── Load dashboard data ───────────────────────────────────────────────────
$page    = max(1, (int)($_GET['p'] ?? 1));
$per     = 25;
$offset  = ($page - 1) * $per;
$filter  = $_GET['filter'] ?? 'all';
$search  = htmlspecialchars(strip_tags($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');

$filter_param = '';
if ($filter !== 'all') $filter_param = "&type=eq.$filter";
if ($search)           $filter_param .= "&or=(email.ilike.*$search*,name.ilike.*$search*)";

$leads = supabase_get(
    'leads',
    "order=created_at.desc&limit=$per&offset=$offset" . $filter_param
);

// Stats
$all_leads  = supabase_get('leads', 'select=id');
$new_leads  = supabase_get('leads', 'status=eq.new&select=id');
$hot_leads  = supabase_get('leads', 'status=eq.hot&select=id');
$sellers    = supabase_get('leads', 'type=eq.seller&select=id');
$buyers     = supabase_get('leads', 'type=eq.buyer&select=id');
$converted  = supabase_get('leads', 'status=eq.converted&select=id');

// Recent logs (read from JSON log file — fast, no extra DB call)
$logs = [];
$log_file = LOG_DIR . '/capture-log-' . date('Y-m') . '.json';
if (file_exists($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice(array_reverse($lines), 0, 50);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) $logs[] = $entry;
    }
}

$total_leads = count($all_leads);
$total_pages = max(1, ceil($total_leads / $per));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lead Engine Admin — Mark Pires</title>
  <style>
    /* ── Reset & Base ── */
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
         font-size:14px;color:#2d2d2d}
    a{text-decoration:none;color:inherit}

    /* ── Sidebar ── */
    .sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1a1a2e;
             padding:0;overflow-y:auto;z-index:100}
    .sidebar-logo{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,.1)}
    .sidebar-logo .brand{color:#c8a96e;font-size:11px;letter-spacing:3px;text-transform:uppercase}
    .sidebar-logo h2{color:white;font-size:16px;margin-top:4px}
    .sidebar nav{padding:20px 0}
    .nav-item{display:block;padding:12px 20px;color:rgba(255,255,255,.7);font-size:13px;
              letter-spacing:.5px;transition:all .2s;border-left:3px solid transparent}
    .nav-item:hover,.nav-item.active{color:white;background:rgba(255,255,255,.08);
              border-left-color:#c8a96e}
    .nav-section{padding:15px 20px 5px;font-size:10px;letter-spacing:2px;
                 text-transform:uppercase;color:rgba(255,255,255,.3)}
    .sidebar-footer{padding:20px;border-top:1px solid rgba(255,255,255,.1);margin-top:auto}
    .logout-btn{display:block;color:rgba(255,255,255,.5);font-size:12px;text-align:center;
                padding:8px;border:1px solid rgba(255,255,255,.2);border-radius:4px;
                transition:all .2s}
    .logout-btn:hover{color:white;border-color:white}

    /* ── Main Content ── */
    .main{margin-left:220px;padding:30px;min-height:100vh}
    .page-header{margin-bottom:25px}
    .page-header h1{font-size:22px;color:#1a1a2e;font-weight:600}
    .page-header p{color:#888;font-size:13px;margin-top:4px}

    /* ── Stat Cards ── */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
                gap:15px;margin-bottom:30px}
    .stat-card{background:white;border-radius:8px;padding:20px;
               box-shadow:0 1px 3px rgba(0,0,0,.08);border-top:3px solid #eee}
    .stat-card.gold{border-top-color:#c8a96e}
    .stat-card.red{border-top-color:#e74c3c}
    .stat-card.green{border-top-color:#27ae60}
    .stat-card.blue{border-top-color:#3498db}
    .stat-card.purple{border-top-color:#9b59b6}
    .stat-num{font-size:32px;font-weight:700;color:#1a1a2e}
    .stat-label{font-size:11px;color:#999;letter-spacing:1px;text-transform:uppercase;margin-top:4px}

    /* ── Toolbar ── */
    .toolbar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center}
    .toolbar input[type=text]{padding:9px 14px;border:1px solid #ddd;border-radius:5px;
                              font-size:13px;width:250px;outline:none}
    .toolbar input:focus{border-color:#c8a96e}
    .filter-btn{padding:8px 16px;border:1px solid #ddd;border-radius:5px;background:white;
                cursor:pointer;font-size:13px;transition:all .2s}
    .filter-btn.active,.filter-btn:hover{background:#1a1a2e;color:white;border-color:#1a1a2e}

    /* ── Table ── */
    .table-wrap{background:white;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);
                overflow:hidden;margin-bottom:25px}
    table{width:100%;border-collapse:collapse}
    th{background:#f8f9fa;padding:12px 15px;text-align:left;font-size:11px;
       letter-spacing:1px;text-transform:uppercase;color:#666;border-bottom:1px solid #eee}
    td{padding:13px 15px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#fafafa}

    /* ── Badges ── */
    .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;
           font-weight:600;letter-spacing:.5px;text-transform:uppercase}
    .badge-new{background:#fff3cd;color:#856404}
    .badge-contacted{background:#cce5ff;color:#004085}
    .badge-nurturing{background:#d4edda;color:#155724}
    .badge-hot{background:#f8d7da;color:#721c24}
    .badge-converted{background:#d4edda;color:#155724}
    .badge-dead{background:#e2e3e5;color:#383d41}
    .badge-seller{background:#e8d5f5;color:#6f3690}
    .badge-buyer{background:#d5edf5;color:#1a6e8e}
    .badge-valuation{background:#fde8d8;color:#8e3a1a}

    /* ── Status dropdown ── */
    .status-select{border:none;background:transparent;font-size:11px;cursor:pointer;
                   font-weight:600;text-transform:uppercase;padding:0;outline:none}

    /* ── Lead detail panel ── */
    .detail-panel{display:none;background:#f8f9fa;padding:15px 20px;
                  border-top:1px solid #eee;font-size:13px}
    .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px}
    .detail-item label{font-size:11px;color:#999;text-transform:uppercase;display:block}
    .detail-item span{color:#1a1a2e;font-weight:500}
    .note-area{width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;
               font-size:13px;height:60px;resize:vertical;outline:none;font-family:inherit}
    .btn{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;
         font-size:12px;font-weight:600;letter-spacing:.5px;transition:all .2s}
    .btn-gold{background:#c8a96e;color:white}
    .btn-gold:hover{background:#b8925a}
    .btn-sm{padding:5px 12px;font-size:11px}

    /* ── Logs panel ── */
    .log-entry{display:flex;gap:12px;padding:9px 0;border-bottom:1px solid #f0f0f0;
               font-size:12px;align-items:flex-start}
    .log-entry:last-child{border-bottom:none}
    .log-time{color:#999;white-space:nowrap;min-width:130px}
    .log-service{font-weight:600;min-width:90px}
    .log-ok{color:#27ae60}
    .log-err{color:#e74c3c}
    .log-detail{color:#555;word-break:break-all}

    /* ── Pagination ── */
    .pagination{display:flex;gap:6px;justify-content:center;margin-top:20px}
    .page-btn{padding:7px 13px;border:1px solid #ddd;border-radius:4px;background:white;
              cursor:pointer;font-size:13px;transition:all .2s}
    .page-btn.active,.page-btn:hover{background:#1a1a2e;color:white;border-color:#1a1a2e}

    /* ── Tabs ── */
    .tabs{display:flex;gap:0;border-bottom:2px solid #eee;margin-bottom:25px}
    .tab{padding:12px 24px;cursor:pointer;font-size:13px;color:#888;
         border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
    .tab.active{color:#1a1a2e;border-bottom-color:#c8a96e;font-weight:600}

    /* ── Responsive ── */
    @media(max-width:768px){
      .sidebar{width:100%;position:relative;height:auto}
      .main{margin-left:0;padding:15px}
      .stats-grid{grid-template-columns:repeat(2,1fr)}
    }
  </style>
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<div class="sidebar">
  <div class="sidebar-logo">
    <div class="brand">Lead Engine</div>
    <h2>Mark Pires</h2>
  </div>
  <nav>
    <div class="nav-section">Pipeline</div>
    <a class="nav-item active" href="/admin/">📊 Dashboard</a>
    <a class="nav-item" href="/admin/?filter=seller">🏠 Seller Leads</a>
    <a class="nav-item" href="/admin/?filter=buyer">🔍 Buyer Leads</a>
    <a class="nav-item" href="/admin/?filter=valuation">💰 Valuations</a>
    <a class="nav-item" href="/admin/?filter=hot">🔴 Hot Leads</a>
    <a class="nav-item" href="/admin/?filter=converted">✅ Converted</a>
    <div class="nav-section">System</div>
    <a class="nav-item" href="/admin/?tab=logs">📋 Action Logs</a>
    <a class="nav-item" href="/admin/?tab=sequences">✉️ Email Sequences</a>
    <a class="nav-item" href="/admin/export.php">⬇️ Export CSV</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="/admin/?logout=1">Sign Out</a>
  </div>
</div>

<!-- ── Main ─────────────────────────────────────────────────────────────── -->
<div class="main">
  <div class="page-header">
    <h1>Lead Engine Dashboard</h1>
    <p>Fairfield County Real Estate · Last updated: <?= date('M j, Y g:i A') ?></p>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card gold">
      <div class="stat-num"><?= count($all_leads) ?></div>
      <div class="stat-label">Total Leads</div>
    </div>
    <div class="stat-card red">
      <div class="stat-num"><?= count($new_leads) ?></div>
      <div class="stat-label">New (Uncontacted)</div>
    </div>
    <div class="stat-card red">
      <div class="stat-num"><?= count($hot_leads) ?></div>
      <div class="stat-label">Hot Leads</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-num"><?= count($sellers) ?></div>
      <div class="stat-label">Seller Leads</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-num"><?= count($buyers) ?></div>
      <div class="stat-label">Buyer Leads</div>
    </div>
    <div class="stat-card green">
      <div class="stat-num"><?= count($converted) ?></div>
      <div class="stat-label">Converted</div>
    </div>
  </div>

  <!-- Tabs -->
  <?php $active_tab = $_GET['tab'] ?? 'leads'; ?>
  <div class="tabs">
    <div class="tab <?= $active_tab==='leads'?'active':'' ?>"
         onclick="location.href='/admin/'">All Leads</div>
    <div class="tab <?= $active_tab==='logs'?'active':'' ?>"
         onclick="location.href='/admin/?tab=logs'">Action Logs</div>
    <div class="tab <?= $active_tab==='sequences'?'active':'' ?>"
         onclick="location.href='/admin/?tab=sequences'">Email Sequences</div>
  </div>

  <?php if ($active_tab === 'leads'): ?>

  <!-- Toolbar -->
  <form method="GET" class="toolbar">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name or email...">
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <button type="submit" class="filter-btn">Search</button>
    <?php foreach (['all','seller','buyer','valuation','consultation','relocation'] as $f): ?>
      <a class="filter-btn <?= $filter===$f?'active':'' ?>"
         href="/admin/?filter=<?= $f ?>"><?= ucfirst($f) ?></a>
    <?php endforeach; ?>
  </form>

  <!-- Leads Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Name</th>
          <th>Email / Phone</th>
          <th>Type</th>
          <th>Status</th>
          <th>Source</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leads)): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:#999">
            No leads found.
          </td></tr>
        <?php endif; ?>
        <?php foreach ($leads as $lead):
          $uid      = htmlspecialchars($lead['uid'] ?? '');
          $lname    = htmlspecialchars($lead['name'] ?? '—');
          $lemail   = htmlspecialchars($lead['email'] ?? '');
          $lphone   = htmlspecialchars($lead['phone'] ?? '');
          $ltype    = htmlspecialchars($lead['type'] ?? '');
          $lstatus  = htmlspecialchars($lead['status'] ?? 'new');
          $lsource  = htmlspecialchars($lead['source'] ?? '');
          $ltowns   = htmlspecialchars($lead['towns'] ?? '');
          $ltimeline= htmlspecialchars($lead['timeline'] ?? '');
          $lgoal    = htmlspecialchars($lead['goal'] ?? '');
          $lmessage = htmlspecialchars($lead['message'] ?? '');
          $lnotes   = htmlspecialchars($lead['notes'] ?? '');
          $lprice   = htmlspecialchars($lead['price_range'] ?? '');
          $lpage    = htmlspecialchars($lead['page_url'] ?? '');
          $ldate    = date('M j, Y g:i A', strtotime($lead['created_at'] ?? 'now'));
        ?>
        <tr>
          <td style="white-space:nowrap;color:#888;font-size:12px"><?= $ldate ?></td>
          <td><strong><?= $lname ?></strong></td>
          <td>
            <a href="mailto:<?= $lemail ?>" style="color:#c8a96e"><?= $lemail ?></a>
            <?php if ($lphone): ?>
              <br><a href="tel:<?= $lphone ?>" style="color:#888;font-size:12px"><?= $lphone ?></a>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $ltype ?>"><?= $ltype ?></span></td>
          <td>
            <select class="status-select" onchange="updateStatus('<?= $uid ?>',this.value)">
              <?php foreach (['new','contacted','nurturing','hot','converted','dead'] as $s): ?>
                <option value="<?= $s ?>" <?= $lstatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td style="font-size:12px;color:#888"><?= $lsource ?></td>
          <td>
            <button class="btn btn-sm btn-gold"
                    onclick="toggleDetail('detail-<?= $uid ?>')">Details</button>
          </td>
        </tr>
        <!-- Expandable detail row -->
        <tr>
          <td colspan="7" style="padding:0">
            <div class="detail-panel" id="detail-<?= $uid ?>">
              <div class="detail-grid">
                <div class="detail-item">
                  <label>Towns of Interest</label>
                  <span><?= $ltowns ?: '—' ?></span>
                </div>
                <div class="detail-item">
                  <label>Timeline</label>
                  <span><?= $ltimeline ?: '—' ?></span>
                </div>
                <div class="detail-item">
                  <label>Price Range</label>
                  <span><?= $lprice ?: '—' ?></span>
                </div>
                <div class="detail-item">
                  <label>Goal</label>
                  <span><?= $lgoal ?: '—' ?></span>
                </div>
                <div class="detail-item" style="grid-column:1/-1">
                  <label>Message</label>
                  <span><?= $lmessage ?: '—' ?></span>
                </div>
                <div class="detail-item" style="grid-column:1/-1">
                  <label>Source Page</label>
                  <span><?= $lpage ?: '—' ?></span>
                </div>
                <div class="detail-item" style="grid-column:1/-1">
                  <label>Lead UID</label>
                  <span style="font-family:monospace;font-size:11px"><?= $uid ?></span>
                </div>
              </div>
              <label style="display:block;font-size:11px;color:#999;
                            text-transform:uppercase;margin-bottom:6px">Notes</label>
              <textarea class="note-area" id="note-<?= $uid ?>"
                        placeholder="Add a private note..."><?= $lnotes ?></textarea>
              <br><br>
              <button class="btn btn-gold btn-sm"
                      onclick="saveNote('<?= $uid ?>')">Save Note</button>
              <a href="mailto:<?= $lemail ?>" class="btn btn-sm"
                 style="background:#eee;color:#333;margin-left:8px">Email Lead</a>
              <?php if ($lphone): ?>
                <a href="tel:<?= $lphone ?>" class="btn btn-sm"
                   style="background:#eee;color:#333;margin-left:8px">Call Lead</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a class="page-btn <?= $i===$page?'active':'' ?>"
         href="/admin/?p=<?= $i ?>&filter=<?= urlencode($filter) ?>&q=<?= urlencode($search) ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php elseif ($active_tab === 'logs'): ?>

  <!-- Action Logs -->
  <div class="table-wrap" style="padding:20px">
    <h3 style="margin-bottom:20px;font-size:15px;color:#1a1a2e">
      Recent System Actions <span style="color:#999;font-weight:400;font-size:13px">
        (last 50 — <?= date('M Y') ?>)
      </span>
    </h3>
    <?php if (empty($logs)): ?>
      <p style="color:#999;text-align:center;padding:30px">
        No logs found. Check that /lead-engine/logs/ is writable.
      </p>
    <?php endif; ?>
    <?php foreach ($logs as $log_entry):
      $is_error = strtolower($log_entry['status'] ?? '') === 'error';
    ?>
    <div class="log-entry">
      <span class="log-time"><?= htmlspecialchars($log_entry['timestamp'] ?? '') ?></span>
      <span class="log-service <?= $is_error?'log-err':'log-ok' ?>">
        <?= htmlspecialchars($log_entry['service'] ?? '') ?>
      </span>
      <span class="badge <?= $is_error?'badge-hot':'badge-nurturing' ?>"
            style="min-width:60px;text-align:center">
        <?= htmlspecialchars($log_entry['status'] ?? '') ?>
      </span>
      <span class="log-detail"><?= htmlspecialchars($log_entry['detail'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($active_tab === 'sequences'): ?>

  <!-- Email Sequences -->
  <?php $seqs = supabase_get('email_sequences', 'order=scheduled_at.desc&limit=50'); ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Scheduled</th>
          <th>Email</th>
          <th>Sequence</th>
          <th>Step</th>
          <th>Status</th>
          <th>Sent At</th>
          <th>Opened</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($seqs)): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:#999">
            No sequences queued yet.
          </td></tr>
        <?php endif; ?>
        <?php foreach ($seqs as $seq): ?>
        <tr>
          <td style="font-size:12px;color:#888">
            <?= date('M j g:i A', strtotime($seq['scheduled_at'] ?? 'now')) ?>
          </td>
          <td><?= htmlspecialchars($seq['lead_email'] ?? '') ?></td>
          <td><?= htmlspecialchars($seq['sequence_name'] ?? '') ?></td>
          <td style="text-align:center"><?= (int)($seq['step'] ?? 0) ?></td>
          <td>
            <span class="badge badge-<?= $seq['status']==='sent'?'nurturing':($seq['status']==='failed'?'hot':'new') ?>">
              <?= htmlspecialchars($seq['status'] ?? '') ?>
            </span>
          </td>
          <td style="font-size:12px;color:#888">
            <?= $seq['sent_at'] ? date('M j g:i A', strtotime($seq['sent_at'])) : '—' ?>
          </td>
          <td style="text-align:center"><?= $seq['opened'] ? '✅' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; ?>
</div><!-- /main -->

<script>
// Toggle expandable detail row
function toggleDetail(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'block' ? 'none' : 'block';
}

// Update lead status via AJAX
function updateStatus(uid, status) {
  fetch('/admin/', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=update_status&uid=${uid}&status=${status}`
  })
  .then(r => r.json())
  .then(d => {
    if (!d.ok) alert('Status update failed — check logs.');
  });
}

// Save note via AJAX
function saveNote(uid) {
  const note = document.getElementById('note-' + uid).value;
  fetch('/admin/', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=add_note&uid=${encodeURIComponent(uid)}&note=${encodeURIComponent(note)}`
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      const btn = event.target;
      btn.textContent = 'Saved ✓';
      setTimeout(() => btn.textContent = 'Save Note', 2000);
    }
  });
}

// Auto-refresh stats every 60 seconds
setTimeout(() => location.reload(), 60000);
</script>
</body>
</html>
<?php } // end admin auth check ?>
