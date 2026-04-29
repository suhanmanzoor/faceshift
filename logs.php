<?php
require 'config.php';
requireAdmin();

$employees  = loadJSON('employees.json');
$actLog     = loadJSON('activity_log.json');
$attendance = loadJSON('attendance.json');

// Build employee list for filter dropdown
$activeEmps = array_filter($employees, fn($e) => $e['active'] ?? true);

// Date filter (default today)
$filterDate = $_GET['date']    ?? today();
$filterEmp  = $_GET['emp']     ?? 'all';
$filterType = $_GET['type']    ?? 'all';
$filterQ    = trim($_GET['q']  ?? '');

// Filter activity logs
$filtered = array_filter($actLog, function($log) use ($filterDate, $filterEmp, $filterType, $filterQ) {
    $logDate = substr($log['time'] ?? '', 0, 10);
    if ($filterDate && $logDate !== $filterDate) return false;
    if ($filterEmp  !== 'all' && ($log['emp_id'] ?? '') !== $filterEmp) return false;
    if ($filterType !== 'all' && ($log['event']  ?? '') !== $filterType) return false;
    if ($filterQ) {
        $hay = strtolower(($log['name'] ?? '') . ' ' . ($log['detail'] ?? '') . ' ' . ($log['emp_id'] ?? ''));
        if (strpos($hay, strtolower($filterQ)) === false) return false;
    }
    return true;
});
$filtered = array_reverse(array_values($filtered));

// Location pings for selected date + employee
$locationPings = [];
$dayAtt = $attendance[$filterDate] ?? [];
if ($filterEmp !== 'all' && isset($dayAtt[$filterEmp])) {
    $locationPings = $dayAtt[$filterEmp]['location_pings'] ?? [];
    $locationPings = array_reverse($locationPings);
} elseif ($filterEmp === 'all') {
    foreach ($dayAtt as $empId => $att) {
        foreach ($att['location_pings'] ?? [] as $p) {
            $locationPings[] = array_merge($p, [
                'emp_id' => $empId,
                'name'   => $employees[$empId]['name'] ?? $empId
            ]);
        }
    }
    usort($locationPings, fn($a,$b) => strcmp($b['time'], $a['time']));
    $locationPings = array_slice($locationPings, 0, 100);
}

// Stats for selected date
$dayStats = [
    'check_in'       => 0,
    'left_office'    => 0,
    'returned'       => 0,
    'tracker_started'=> 0,
    'total'          => count($filtered)
];
foreach ($filtered as $log) {
    $ev = $log['event'] ?? '';
    if (isset($dayStats[$ev])) $dayStats[$ev]++;
}

$eventTypes = ['check_in','left_office','returned','registered','tracker_started','report_sent'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activity Logs — FaceShift </title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--teal:#00d4e8;--green:#00ff88;--red:#ff4757;--yellow:#ffd700;--blue:#4f9cf9;--purple:#a78bfa;--bg:#080a10;--s1:#0e1220;--s2:#131826;--border:#1c2235;--text:#e2e8f8;--muted:#6b7a99;--faint:#1c2235}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;display:flex;min-height:100vh}

/* SIDEBAR */
.sb{width:220px;background:var(--s1);border-right:1px solid var(--border);padding:1.25rem 1rem;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column}
.sb-logo{display:flex;align-items:center;gap:.6rem;margin-bottom:2rem}
.sb-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--teal),#0077b6);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-title{font-weight:700;color:#fff;font-size:.95rem}
.nav-a{display:flex;align-items:center;gap:.6rem;padding:.5rem .75rem;border-radius:8px;color:var(--muted);text-decoration:none;font-size:.83rem;margin-bottom:.2rem;transition:all .18s}
.nav-a:hover{background:var(--s2);color:var(--text)}
.nav-a.active{background:var(--s2);color:var(--teal)}
.nav-a svg{flex-shrink:0}
.sb-div{border-color:var(--border);margin:.6rem 0}
.sb-spacer{flex:1}

/* MAIN */
.main{flex:1;padding:1.75rem;overflow-y:auto;min-height:100vh}
.page-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.page-title{font-size:1.2rem;font-weight:700;color:#fff}
.page-sub{color:var(--muted);font-size:.8rem;margin-top:.15rem}
.ist-tag{color:var(--teal);font-size:.72rem;background:rgba(0,212,232,.1);border:1px solid rgba(0,212,232,.2);padding:.2rem .5rem;border-radius:20px}

/* KPI ROW */
.kpi-row{display:grid;grid-template-columns:repeat(5,1fr);gap:.85rem;margin-bottom:1.5rem}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(3,1fr)}}
@media(max-width:500px){.kpi-row{grid-template-columns:1fr 1fr}}
.kpi{background:var(--s1);border:1px solid var(--border);border-radius:12px;padding:1rem;text-align:center}
.kpi-n{font-size:1.6rem;font-weight:700;color:#fff;font-variant-numeric:tabular-nums;line-height:1}
.kpi-l{color:var(--muted);font-size:.72rem;margin-top:.3rem}
.kpi-total .kpi-n{color:var(--teal)}
.kpi-ci .kpi-n{color:var(--green)}
.kpi-lo .kpi-n{color:var(--yellow)}
.kpi-rt .kpi-n{color:var(--blue)}
.kpi-tr .kpi-n{color:var(--purple)}

/* FILTER BAR */
.filter-bar{background:var(--s1);border:1px solid var(--border);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:.65rem;align-items:flex-end}
.fb-group{display:flex;flex-direction:column;gap:.3rem}
.fb-label{font-size:.72rem;color:var(--muted);font-weight:500}
.fb-input,.fb-select{background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:.45rem .75rem;font-size:.82rem;font-family:'Inter',sans-serif;outline:none;transition:border .2s}
.fb-input:focus,.fb-select:focus{border-color:var(--teal)}
.fb-select option{background:var(--s1)}
.fb-search{flex:1;min-width:180px}
.fb-search .fb-input{width:100%}
.btn-filter{background:linear-gradient(135deg,var(--teal),#0077b6);border:none;color:#fff;padding:.45rem 1.1rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap;transition:opacity .2s}
.btn-filter:hover{opacity:.85}
.btn-reset{background:var(--s2);border:1px solid var(--border);color:var(--muted);padding:.45rem .9rem;border-radius:8px;font-size:.82rem;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap;text-decoration:none;transition:all .2s}
.btn-reset:hover{color:var(--text)}

/* TABS */
.tabs{display:flex;gap:.5rem;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:.75rem}
.tab{padding:.4rem .9rem;border-radius:8px;font-size:.82rem;font-weight:500;cursor:pointer;color:var(--muted);border:none;background:none;font-family:'Inter',sans-serif;transition:all .2s}
.tab.active{background:var(--s2);color:var(--teal)}
.tab:hover:not(.active){color:var(--text)}
.tab-count{background:var(--faint);color:var(--muted);font-size:.68rem;padding:.1rem .4rem;border-radius:10px;margin-left:.3rem}
.tab.active .tab-count{background:rgba(0,212,232,.15);color:var(--teal)}

/* CONTENT PANEL */
.tab-panel{display:none}.tab-panel.active{display:block}

/* LOG TABLE */
.log-table-wrap{background:var(--s1);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.log-table-hdr{padding:.75rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.lth-title{font-size:.85rem;font-weight:600;color:var(--text)}
.lth-count{font-size:.75rem;color:var(--muted)}
table{width:100%;border-collapse:collapse}
thead th{padding:.55rem .9rem;text-align:left;font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);white-space:nowrap}
tbody td{padding:.6rem .9rem;border-bottom:1px solid rgba(28,34,53,.5);font-size:.82rem;vertical-align:middle}
tbody tr:last-child td{border:none}
tbody tr:hover td{background:rgba(19,24,38,.6)}
.td-time{color:var(--muted);font-variant-numeric:tabular-nums;white-space:nowrap;font-size:.75rem}
.td-ist{color:var(--teal);font-size:.7rem;display:block;margin-top:.1rem}
.emp-cell{display:flex;align-items:center;gap:.5rem}
.emp-av-xs{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#0077b6);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;color:#fff;flex-shrink:0}
.emp-nm{font-weight:500}
.emp-id-xs{color:var(--muted);font-size:.7rem}
.detail-cell{color:var(--muted);max-width:300px;font-size:.78rem}

/* EVENT BADGES */
.ev-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;font-weight:600;padding:.2rem .55rem;border-radius:20px;white-space:nowrap}
.ev-check_in       {background:rgba(0,255,136,.1);color:var(--green);border:1px solid rgba(0,255,136,.2)}
.ev-left_office    {background:rgba(255,215,0,.1);color:var(--yellow);border:1px solid rgba(255,215,0,.2)}
.ev-returned       {background:rgba(0,212,232,.1);color:var(--teal);border:1px solid rgba(0,212,232,.2)}
.ev-registered     {background:rgba(79,156,249,.1);color:var(--blue);border:1px solid rgba(79,156,249,.2)}
.ev-tracker_started{background:rgba(167,139,250,.1);color:var(--purple);border:1px solid rgba(167,139,250,.2)}
.ev-report_sent    {background:rgba(107,122,153,.1);color:var(--muted);border:1px solid rgba(107,122,153,.2)}

/* PING TABLE */
.in-off-chip {display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;padding:.2rem .5rem;border-radius:20px}
.in-off-chip.yes{background:rgba(0,255,136,.1);color:var(--green);border:1px solid rgba(0,255,136,.2)}
.in-off-chip.no {background:rgba(255,215,0,.1);color:var(--yellow);border:1px solid rgba(255,215,0,.2)}

/* EMPTY STATE */
.empty{text-align:center;padding:3rem;color:var(--muted)}
.empty-icon{font-size:2.5rem;margin-bottom:.75rem;opacity:.5}
.empty p{font-size:.85rem}

/* EXPORT BTN */
.btn-export{background:var(--s2);border:1px solid var(--border);color:var(--muted);padding:.4rem .85rem;border-radius:8px;font-size:.78rem;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s;display:inline-flex;align-items:center;gap:.4rem}
.btn-export:hover{color:var(--text);border-color:var(--teal)}

/* TIMELINE VIEW */
.timeline{padding:.75rem 1.25rem}
.tl-item{display:flex;gap:.85rem;padding:.6rem 0;position:relative}
.tl-item:not(:last-child)::after{content:'';position:absolute;left:15px;top:30px;bottom:-6px;width:1px;background:var(--border)}
.tl-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;z-index:1}
.tl-check_in       {background:rgba(0,255,136,.15)}
.tl-left_office    {background:rgba(255,215,0,.15)}
.tl-returned       {background:rgba(0,212,232,.15)}
.tl-registered     {background:rgba(79,156,249,.15)}
.tl-tracker_started{background:rgba(167,139,250,.15)}
.tl-report_sent    {background:rgba(107,122,153,.15)}
.tl-body{flex:1}
.tl-top{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.tl-name{font-weight:600;font-size:.85rem;color:var(--text)}
.tl-time{color:var(--muted);font-size:.72rem;font-variant-numeric:tabular-nums}
.tl-detail{color:var(--muted);font-size:.78rem;margin-top:.15rem}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--faint);border-radius:2px}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sb">
  <div class="sb-logo">
    <div class="sb-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2"><circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/></svg></div>
    <span class="sb-title">FaceShift </span>
  </div>
  <a class="nav-a" href="admin.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard
  </a>
  <a class="nav-a" href="register.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg> Employees
  </a>
  <a class="nav-a active" href="logs.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Activity Logs
  </a>
  <a class="nav-a" href="send_report.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Send Reports
  </a>
  <hr class="sb-div">
  <a class="nav-a" href="attendance.php" target="_blank">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg> Kiosk
  </a>
  <a class="nav-a" href="tracker.php" target="_blank">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> Tracker
  </a>
  <div class="sb-spacer"></div>
  <hr class="sb-div">
  <a class="nav-a" href="logout.php" style="color:#ff6b7a">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout
  </a>
</div>

<!-- MAIN -->
<div class="main">

  <div class="page-hdr">
    <div>
      <div class="page-title">Activity Logs</div>
      <div class="page-sub">All attendance events, GPS pings and system activity · <span style="color:var(--teal)"><?= nowIST() ?></span></div>
    </div>
    <button class="btn-export" onclick="exportCSV()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </button>
  </div>

  <!-- KPI ROW -->
  <div class="kpi-row">
    <div class="kpi kpi-total"><div class="kpi-n"><?= $dayStats['total'] ?></div><div class="kpi-l">Total Events</div></div>
    <div class="kpi kpi-ci"><div class="kpi-n"><?= $dayStats['check_in'] ?></div><div class="kpi-l">Check-ins</div></div>
    <div class="kpi kpi-lo"><div class="kpi-n"><?= $dayStats['left_office'] ?></div><div class="kpi-l">Left Office</div></div>
    <div class="kpi kpi-rt"><div class="kpi-n"><?= $dayStats['returned'] ?></div><div class="kpi-l">Returned</div></div>
    <div class="kpi kpi-tr"><div class="kpi-n"><?= $dayStats['tracker_started'] ?></div><div class="kpi-l">Trackers Started</div></div>
  </div>

  <!-- FILTER BAR -->
  <form method="GET" class="filter-bar">
    <div class="fb-group">
      <span class="fb-label">Date (IST)</span>
      <input type="date" name="date" class="fb-input" value="<?= htmlspecialchars($filterDate) ?>" max="<?= today() ?>">
    </div>
    <div class="fb-group">
      <span class="fb-label">Employee</span>
      <select name="emp" class="fb-select">
        <option value="all">All Employees</option>
        <?php foreach ($activeEmps as $id => $e): ?>
        <option value="<?= $id ?>" <?= $filterEmp === $id ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?> (<?= $id ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fb-group">
      <span class="fb-label">Event Type</span>
      <select name="type" class="fb-select">
        <option value="all">All Events</option>
        <option value="check_in"        <?= $filterType==='check_in'        ?'selected':'' ?>>✅ Check-in</option>
        <option value="left_office"     <?= $filterType==='left_office'     ?'selected':'' ?>>🚗 Left Office</option>
        <option value="returned"        <?= $filterType==='returned'        ?'selected':'' ?>>🏢 Returned</option>
        <option value="tracker_started" <?= $filterType==='tracker_started' ?'selected':'' ?>>📍 Tracker Started</option>
        <option value="registered"      <?= $filterType==='registered'      ?'selected':'' ?>>👤 Registered</option>
        <option value="report_sent"     <?= $filterType==='report_sent'     ?'selected':'' ?>>✉️ Report Sent</option>
      </select>
    </div>
    <div class="fb-group fb-search">
      <span class="fb-label">Search</span>
      <input type="text" name="q" class="fb-input" placeholder="Name, ID, detail…" value="<?= htmlspecialchars($filterQ) ?>">
    </div>
    <button type="submit" class="btn-filter">Apply Filters</button>
    <a href="logs.php" class="btn-reset">Reset</a>
  </form>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('table', this)">
      Table View <span class="tab-count"><?= count($filtered) ?></span>
    </button>
    <button class="tab" onclick="switchTab('timeline', this)">
      Timeline View <span class="tab-count"><?= count($filtered) ?></span>
    </button>
    <button class="tab" onclick="switchTab('pings', this)">
      GPS Pings <span class="tab-count"><?= count($locationPings) ?></span>
    </button>
  </div>

  <!-- TAB: TABLE VIEW -->
  <div class="tab-panel active" id="tab-table">
    <div class="log-table-wrap">
      <div class="log-table-hdr">
        <span class="lth-title">Activity Log</span>
        <div style="display:flex;align-items:center;gap:.75rem">
          <span class="lth-count"><?= count($filtered) ?> event<?= count($filtered)!==1?'s':'' ?> · <?= $filterDate ?></span>
          <input type="text" id="tableSearch" class="fb-input" placeholder="Quick search…" oninput="filterTable(this.value)" style="width:160px;padding:.35rem .65rem">
        </div>
      </div>
      <?php if (empty($filtered)): ?>
      <div class="empty"><div class="empty-icon">📭</div><p>No events found for the selected filters.</p></div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table id="logTable">
          <thead>
            <tr>
              <th style="cursor:pointer" onclick="sortTable(0)">Time (IST) ↕</th>
              <th>Employee</th>
              <th>Event</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filtered as $log):
              $ev    = $log['event'] ?? 'unknown';
              $icons = ['check_in'=>'✅','left_office'=>'🚗','returned'=>'🏢','registered'=>'👤','tracker_started'=>'📍','report_sent'=>'✉️'];
              $icon  = $icons[$ev] ?? '•';
              $empId = $log['emp_id'] ?? '';
              $empNm = $log['name']   ?? $empId;
              $istT  = $log['ist']    ?? (isset($log['time']) ? date('h:i A', strtotime($log['time'])) . ' IST' : '--');
              $rawT  = isset($log['time']) ? substr($log['time'],11,8) : '--';
            ?>
            <tr>
              <td class="td-time">
                <?= $rawT ?>
                <span class="td-ist"><?= $istT ?></span>
              </td>
              <td>
                <div class="emp-cell">
                  <div class="emp-av-xs"><?= strtoupper(substr($empNm,0,2)) ?></div>
                  <div>
                    <div class="emp-nm"><?= htmlspecialchars($empNm) ?></div>
                    <div class="emp-id-xs"><?= htmlspecialchars($empId) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="ev-badge ev-<?= $ev ?>"><?= $icon ?> <?= ucwords(str_replace('_',' ',$ev)) ?></span>
              </td>
              <td class="detail-cell"><?= htmlspecialchars($log['detail'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: TIMELINE VIEW -->
  <div class="tab-panel" id="tab-timeline">
    <div class="log-table-wrap">
      <div class="log-table-hdr">
        <span class="lth-title">Event Timeline</span>
        <span class="lth-count"><?= $filterDate ?> IST</span>
      </div>
      <?php if (empty($filtered)): ?>
      <div class="empty"><div class="empty-icon">📭</div><p>No events found.</p></div>
      <?php else: ?>
      <div class="timeline">
        <?php foreach ($filtered as $log):
          $ev    = $log['event'] ?? 'unknown';
          $icons = ['check_in'=>'✅','left_office'=>'🚗','returned'=>'🏢','registered'=>'👤','tracker_started'=>'📍','report_sent'=>'✉️'];
          $icon  = $icons[$ev] ?? '•';
          $empNm = $log['name'] ?? ($log['emp_id'] ?? '');
          $istT  = $log['ist']  ?? (isset($log['time']) ? date('h:i A', strtotime($log['time'])).' IST' : '--');
        ?>
        <div class="tl-item">
          <div class="tl-icon tl-<?= $ev ?>"><?= $icon ?></div>
          <div class="tl-body">
            <div class="tl-top">
              <span class="tl-name"><?= htmlspecialchars($empNm) ?></span>
              <span class="ev-badge ev-<?= $ev ?>"><?= ucwords(str_replace('_',' ',$ev)) ?></span>
              <span class="tl-time"><?= $istT ?></span>
            </div>
            <div class="tl-detail"><?= htmlspecialchars($log['detail'] ?? '') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: GPS PINGS -->
  <div class="tab-panel" id="tab-pings">
    <div class="log-table-wrap">
      <div class="log-table-hdr">
        <span class="lth-title">📡 GPS Location Pings</span>
        <span class="lth-count"><?= count($locationPings) ?> ping<?= count($locationPings)!==1?'s':'' ?></span>
      </div>
      <?php if (empty($locationPings)): ?>
      <div class="empty"><div class="empty-icon">📡</div><p>No GPS pings recorded<?= $filterEmp==='all'?' for this date':' for this employee' ?>.</p></div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Time (IST)</th>
              <?php if ($filterEmp === 'all'): ?><th>Employee</th><?php endif; ?>
              <th>Status</th>
              <th>Distance</th>
              <th>Accuracy</th>
              <th>Coordinates</th>
              <th>Event</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($locationPings as $p):
              $ist = $p['ist'] ?? (isset($p['time']) ? date('h:i A', strtotime($p['time'])).' IST' : '--');
              $inOff = $p['in_office'] ?? false;
              $evMap = ['left_office'=>'🚗 Left Office','returned'=>'🏢 Returned'];
              $pingEv = $evMap[$p['event'] ?? ''] ?? '—';
            ?>
            <tr>
              <td class="td-time">
                <?= isset($p['time']) ? substr($p['time'],11,8) : '--' ?>
                <span class="td-ist"><?= $ist ?></span>
              </td>
              <?php if ($filterEmp === 'all'): ?>
              <td>
                <div class="emp-cell">
                  <div class="emp-av-xs"><?= strtoupper(substr($p['name']??'?',0,2)) ?></div>
                  <div class="emp-nm"><?= htmlspecialchars($p['name'] ?? $p['emp_id'] ?? '') ?></div>
                </div>
              </td>
              <?php endif; ?>
              <td><span class="in-off-chip <?= $inOff?'yes':'no' ?>"><?= $inOff ? '🏢 In Office' : '🚗 Outside' ?></span></td>
              <td style="font-variant-numeric:tabular-nums"><?= isset($p['dist_m']) ? number_format($p['dist_m']).'m' : '—' ?></td>
              <td style="color:var(--muted);font-size:.75rem"><?= isset($p['accuracy']) ? '±'.$p['accuracy'].'m' : '—' ?></td>
              <td style="color:var(--muted);font-size:.72rem;font-variant-numeric:tabular-nums">
                <?= isset($p['lat']) ? number_format($p['lat'],6).',' . number_format($p['lng'],6) : '—' ?>
              </td>
              <td style="font-size:.75rem;color:var(--muted)"><?= $pingEv ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.main -->

<script>
// Tab switching
function switchTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// Quick search in table
function filterTable(q) {
  const rows = document.querySelectorAll('#logTable tbody tr');
  const lq   = q.toLowerCase();
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(lq) ? '' : 'none';
  });
}

// Sort table by column
let sortDir = -1;
function sortTable(col) {
  const tbody = document.querySelector('#logTable tbody');
  const rows  = Array.from(tbody.querySelectorAll('tr'));
  rows.sort((a, b) => {
    const ta = a.querySelectorAll('td')[col]?.textContent.trim() ?? '';
    const tb = b.querySelectorAll('td')[col]?.textContent.trim() ?? '';
    return ta.localeCompare(tb) * sortDir;
  });
  sortDir *= -1;
  rows.forEach(r => tbody.appendChild(r));
}

// Export CSV
function exportCSV() {
  const rows   = document.querySelectorAll('#logTable tbody tr');
  const lines  = [['Time','Employee','ID','Event','Detail']];
  rows.forEach(row => {
    const tds = row.querySelectorAll('td');
    if (row.style.display === 'none') return;
    lines.push([
      tds[0]?.textContent.trim().replace(/\s+/g,' ') ?? '',
      tds[1]?.querySelector('.emp-nm')?.textContent.trim() ?? '',
      tds[1]?.querySelector('.emp-id-xs')?.textContent.trim() ?? '',
      tds[2]?.textContent.trim() ?? '',
      tds[3]?.textContent.trim() ?? ''
    ]);
  });
  const csv  = lines.map(r => r.map(c => `"${c.replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], {type:'text/csv'});
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = `FaceShift _logs_<?= $filterDate ?>.csv`;
  a.click();
}
</script>
</body>
</html>