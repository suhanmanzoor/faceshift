<?php
require 'config.php';
requireAdmin();

$saved   = false;
$error   = '';
$current = loadJSON('settings.json');

// ── Defaults for form pre-fill ────────────────────────────────
$S = array_merge([
    'office_name'            => 'TechCorp HQ',
    'office_address'         => 'New Delhi, India',
    'office_lat'             => 33.772661,
    'office_lng'             => 75.171258,
    'office_radius_m'        => 150,
    'shift_start'            => '09:00',
    'shift_end'              => '18:00',
    'working_days'           => [1,2,3,4,5],
    'tracker_ping_interval'  => 5,
    'smtp_host'              => 'smtp.gmail.com',
    'smtp_port'              => 587,
    'smtp_user'              => '',
    'smtp_pass'              => '',
    'from_name'              => 'HR Team',
    'auto_report_time'       => '18:30',
    'auto_report_enabled'    => false,
], $current);

// ── Handle Save ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $new = [
        'office_name'           => trim($_POST['office_name'] ?? ''),
        'office_address'        => trim($_POST['office_address'] ?? ''),
        'office_lat'            => (float)($_POST['office_lat'] ?? 0),
        'office_lng'            => (float)($_POST['office_lng'] ?? 0),
        'office_radius_m'       => (int)($_POST['office_radius_m'] ?? 150),
        'shift_start'           => $_POST['shift_start'] ?? '09:00',
        'shift_end'             => $_POST['shift_end']   ?? '18:00',
        'working_days'          => array_map('intval', $_POST['working_days'] ?? []),
        'tracker_ping_interval' => (int)($_POST['tracker_ping_interval'] ?? 5),
        'smtp_host'             => trim($_POST['smtp_host'] ?? ''),
        'smtp_port'             => (int)($_POST['smtp_port'] ?? 587),
        'smtp_user'             => trim($_POST['smtp_user'] ?? ''),
        'smtp_pass'             => $_POST['smtp_pass'] !== '••••••••' ? $_POST['smtp_pass'] : ($current['smtp_pass'] ?? ''),
        'from_name'             => trim($_POST['from_name'] ?? ''),
        'auto_report_time'      => $_POST['auto_report_time'] ?? '18:30',
        'auto_report_enabled'   => isset($_POST['auto_report_enabled']),
        'updated_at'            => nowDT(),
        'updated_by'            => $_SESSION['user'] ?? 'admin',
    ];

    if (empty($new['office_name'])) {
        $error = 'Office name is required.';
    } elseif (empty($new['working_days'])) {
        $error = 'Select at least one working day.';
    } elseif ($new['shift_start'] >= $new['shift_end']) {
        $error = 'Shift end time must be after shift start time.';
    } else {
        saveJSON('settings.json', $new);
        logActivity('system', 'Admin', 'settings_updated', 'Settings updated by admin');
        $saved = true;
        $S = $new;
    }
}

// ── Days map ──────────────────────────────────────────────────
$dayNames = [0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];
$todayDow = (int)date('w');

// ── Current shift status ──────────────────────────────────────
$shiftActive = isShiftActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings — FaceShift</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --teal:#00d4e8;--green:#00ff88;--red:#ff4757;--yellow:#ffd700;--blue:#4f9cf9;--purple:#a78bfa;--orange:#ff9f43;
  --bg:#080a10;--s1:#0e1220;--s2:#131826;--s3:#171d2c;
  --border:#1c2235;--border2:#222a3e;
  --text:#e2e8f8;--muted:#6b7a99;--faint:#1c2235;
}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;display:flex;min-height:100vh}

/* SIDEBAR */
.sb{width:224px;background:var(--s1);border-right:1px solid var(--border);padding:1.25rem 1rem;flex-shrink:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column;position:sticky;top:0}
.sb-logo{display:flex;align-items:center;gap:.65rem;margin-bottom:2rem}
.sb-icon{width:34px;height:34px;background:linear-gradient(135deg,var(--teal),#0077b6);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-name{font-weight:700;color:#fff;font-size:.95rem}
.sb-version{color:var(--muted);font-size:.65rem;display:block}
.sb-section{color:var(--muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.6px;font-weight:600;padding:.2rem .75rem;margin:.75rem 0 .3rem}
.nav-a{display:flex;align-items:center;gap:.6rem;padding:.5rem .75rem;border-radius:8px;color:var(--muted);text-decoration:none;font-size:.83rem;margin-bottom:.15rem;transition:all .18s}
.nav-a:hover{background:var(--s2);color:var(--text)}
.nav-a.active{background:var(--s2);color:var(--teal)}
.nav-a svg{flex-shrink:0;width:15px;height:15px}
.sb-div{border:none;border-top:1px solid var(--border);margin:.5rem 0}
.sb-spacer{flex:1}
.shift-pill{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:8px;font-size:.78rem;font-weight:600;margin-bottom:.5rem}
.shift-on{background:rgba(0,255,136,.08);color:var(--green);border:1px solid rgba(0,255,136,.18)}
.shift-off{background:rgba(255,71,87,.08);color:var(--red);border:1px solid rgba(255,71,87,.18)}
.shift-dot{width:7px;height:7px;border-radius:50%;background:currentColor}
.pulse{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* MAIN */
.main{flex:1;padding:2rem;overflow-y:auto;max-width:900px}
.page-hdr{margin-bottom:1.75rem}
.page-title{font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:.2rem}
.page-sub{font-size:.8rem;color:var(--muted)}

/* ALERTS */
.alert{border-radius:10px;padding:.75rem 1rem;font-size:.83rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.6rem}
.alert-success{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);color:var(--green)}
.alert-error{background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);color:var(--red)}

/* SECTION CARD */
.section-card{background:var(--s1);border:1px solid var(--border);border-radius:14px;margin-bottom:1.25rem;overflow:hidden}
.sc-hdr{padding:.9rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.6rem}
.sc-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.sc-title{font-weight:700;font-size:.9rem;color:#fff}
.sc-sub{font-size:.75rem;color:var(--muted);margin-top:.1rem}
.sc-body{padding:1.25rem}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
.form-grid-1{display:grid;grid-template-columns:1fr;gap:1rem}
@media(max-width:640px){.form-grid,.form-grid-3{grid-template-columns:1fr}}
.form-group{display:flex;flex-direction:column;gap:.35rem}
.form-label{font-size:.77rem;font-weight:600;color:var(--muted);display:flex;align-items:center;gap:.35rem}
.form-label .req{color:var(--red);font-size:.7rem}
.form-hint{font-size:.7rem;color:var(--muted);margin-top:.15rem}
.form-input,.form-select{background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:.55rem .85rem;font-size:.85rem;font-family:'Inter',sans-serif;outline:none;transition:border .2s,box-shadow .2s;width:100%}
.form-input:focus,.form-select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,212,232,.1)}
.form-input::placeholder{color:var(--muted)}
.form-select option{background:var(--s1)}
.form-input.readonly{opacity:.55;cursor:not-allowed}
.form-divider{border:none;border-top:1px solid var(--border);margin:.25rem 0 .75rem}

/* DAYS PICKER */
.days-picker{display:flex;gap:.5rem;flex-wrap:wrap}
.day-btn{position:relative}
.day-btn input[type=checkbox]{position:absolute;opacity:0;width:0;height:0}
.day-label{display:flex;flex-direction:column;align-items:center;gap:.2rem;width:54px;padding:.55rem .35rem;background:var(--bg);border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:all .2s;user-select:none}
.day-label .day-short{font-size:.8rem;font-weight:700;color:var(--muted)}
.day-label .day-full{font-size:.6rem;color:var(--muted)}
.day-btn input:checked + .day-label{background:rgba(0,212,232,.1);border-color:var(--teal)}
.day-btn input:checked + .day-label .day-short{color:var(--teal)}
.day-btn input:checked + .day-label .day-full{color:var(--teal)}
.day-label:hover{border-color:var(--border2)}
.day-today .day-label{border-style:dashed}

/* SHIFT PREVIEW */
.shift-preview{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.sp-block{text-align:center}
.sp-val{font-size:1.1rem;font-weight:700;color:#fff;font-variant-numeric:tabular-nums}
.sp-lbl{font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-top:.15rem}
.sp-arrow{color:var(--muted);font-size:1.2rem}
.sp-duration{background:rgba(0,212,232,.08);border:1px solid rgba(0,212,232,.2);color:var(--teal);font-size:.78rem;font-weight:600;padding:.3rem .65rem;border-radius:20px;margin-left:auto}
.shift-status-live{display:flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:600;padding:.3rem .65rem;border-radius:20px}
.ssl-on{background:rgba(0,255,136,.1);color:var(--green);border:1px solid rgba(0,255,136,.2)}
.ssl-off{background:rgba(255,71,87,.1);color:var(--red);border:1px solid rgba(255,71,87,.2)}

/* TOGGLE */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:.5rem 0}
.toggle-label{font-size:.85rem;font-weight:500;color:var(--text)}
.toggle-sub{font-size:.75rem;color:var(--muted);margin-top:.15rem}
.toggle{position:relative;display:inline-block;width:42px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:var(--faint);border-radius:24px;cursor:pointer;transition:.2s}
.toggle-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle input:checked + .toggle-slider{background:var(--teal)}
.toggle input:checked + .toggle-slider:before{transform:translateX(18px)}

/* PING SLIDER */
.ping-slider{-webkit-appearance:none;appearance:none;height:4px;border-radius:2px;background:var(--faint);outline:none;width:100%}
.ping-slider::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:var(--teal);cursor:pointer;border:2px solid var(--bg);box-shadow:0 0 0 2px rgba(0,212,232,.25)}
.ping-val{font-size:1.1rem;font-weight:700;color:var(--teal);font-variant-numeric:tabular-nums;min-width:60px}

/* MAP PREVIEW */
.map-preview{background:var(--bg);border:1px solid var(--border);border-radius:10px;overflow:hidden;height:160px;display:flex;align-items:center;justify-content:center;margin-top:.75rem;position:relative}
#leafletMap{width:100%;height:100%}
.map-placeholder{color:var(--muted);font-size:.82rem;text-align:center}
.radius-chip{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.7);color:var(--teal);font-size:.72rem;font-weight:600;padding:.2rem .55rem;border-radius:20px;z-index:1000;pointer-events:none}

/* SAVE BAR */
.save-bar{position:sticky;bottom:0;background:var(--s1);border-top:1px solid var(--border);padding:.85rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin:0 -2rem;width:calc(100% + 4rem)}
.save-bar-left{font-size:.8rem;color:var(--muted)}
.save-bar-left span{color:var(--text);font-weight:500}
.btn{border:none;cursor:pointer;font-family:'Inter',sans-serif;font-weight:600;transition:all .18s;display:inline-flex;align-items:center;gap:.4rem;border-radius:8px;font-size:.83rem;padding:.55rem 1.25rem}
.btn-primary{background:linear-gradient(135deg,var(--teal),#0077b6);color:#fff}
.btn-primary:hover{opacity:.88}
.btn-ghost{background:var(--s2);color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{color:var(--text)}
.btn-danger-ghost{background:rgba(255,71,87,.1);color:var(--red);border:1px solid rgba(255,71,87,.2)}
.btn-danger-ghost:hover{background:rgba(255,71,87,.2)}

/* JSON PREVIEW */
.json-box{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:.85rem 1rem;font-size:.72rem;font-family:'Courier New',monospace;color:#a8c4e0;line-height:1.7;max-height:180px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}

::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--faint);border-radius:2px}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sb">
  <div class="sb-logo">
    <div class="sb-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2">
        <circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/>
      </svg>
    </div>
    <div>
      <div class="sb-name">FaceShift</div>
      <span class="sb-version"><?= OFFICE_NAME ?></span>
    </div>
  </div>

  <span class="sb-section">Main</span>
  <a class="nav-a" href="admin.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-a" href="register.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg>
    Employees
  </a>
  <a class="nav-a" href="logs.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Activity Logs
  </a>
  <a class="nav-a" href="send_report.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    Send Reports
  </a>
  <a class="nav-a active" href="settings.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    Settings
  </a>

  <hr class="sb-div">
  <a class="nav-a" href="attendance.php" target="_blank">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg>
    Face Kiosk
  </a>

  <div class="sb-spacer"></div>
  <hr class="sb-div">
  <div class="shift-pill <?= $shiftActive ? 'shift-on' : 'shift-off' ?>">
    <div class="shift-dot <?= $shiftActive ? 'pulse' : '' ?>"></div>
    <span><?= $shiftActive ? 'Shift Active' : 'Shift Closed' ?></span>
    <span style="margin-left:auto;font-size:.68rem;opacity:.7"><?= SHIFT_START ?>–<?= SHIFT_END ?></span>
  </div>
  <a class="nav-a" href="logout.php" style="color:#ff4757">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Logout
  </a>
</div>

<!-- MAIN -->
<div class="main">

  <div class="page-hdr">
    <div class="page-title">⚙️ Settings</div>
    <div class="page-sub">Configure office, shift timings, working days and email — saved to <code style="color:var(--teal)">data/settings.json</code></div>
  </div>

  <?php if ($saved): ?>
  <div class="alert alert-success">
    <span>✅</span> Settings saved successfully at <?= nowIST() ?>. Changes take effect immediately.
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error">
    <span>⚠️</span> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" id="settingsForm">

    <!-- ── OFFICE DETAILS ── -->
    <div class="section-card">
      <div class="sc-hdr">
        <div class="sc-icon" style="background:rgba(79,156,249,.12)">🏢</div>
        <div>
          <div class="sc-title">Office Details</div>
          <div class="sc-sub">Name, address and GPS geofence</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="form-grid" style="margin-bottom:1rem">
          <div class="form-group">
            <label class="form-label">Office Name <span class="req">*</span></label>
            <input type="text" name="office_name" class="form-input" value="<?= htmlspecialchars($S['office_name']) ?>" required placeholder="e.g. TechCorp HQ">
          </div>
          <div class="form-group">
            <label class="form-label">Office Address</label>
            <input type="text" name="office_address" class="form-input" value="<?= htmlspecialchars($S['office_address']) ?>" placeholder="e.g. New Delhi, India">
          </div>
        </div>
        <div class="form-grid-3" style="margin-bottom:1rem">
          <div class="form-group">
            <label class="form-label">Latitude <span class="req">*</span></label>
            <input type="number" name="office_lat" id="fLat" class="form-input" value="<?= $S['office_lat'] ?>" step="0.000001" required placeholder="33.772661" oninput="updateMap()">
            <span class="form-hint">GPS latitude of office</span>
          </div>
          <div class="form-group">
            <label class="form-label">Longitude <span class="req">*</span></label>
            <input type="number" name="office_lng" id="fLng" class="form-input" value="<?= $S['office_lng'] ?>" step="0.000001" required placeholder="75.171258" oninput="updateMap()">
            <span class="form-hint">GPS longitude of office</span>
          </div>
          <div class="form-group">
            <label class="form-label">Geofence Radius (m)</label>
            <input type="number" name="office_radius_m" id="fRadius" class="form-input" value="<?= $S['office_radius_m'] ?>" min="50" max="5000" step="10" placeholder="150" oninput="updateMap();updateRadiusLabel()">
            <span class="form-hint">Area around office considered "in-office"</span>
          </div>
        </div>

        <!-- Map preview -->
        <div class="map-preview" id="mapWrap">
          <div class="radius-chip" id="radiusChip"><?= $S['office_radius_m'] ?>m radius</div>
          <div id="leafletMap"></div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap">
          <button type="button" class="btn btn-ghost" onclick="locateMe()" style="font-size:.75rem;padding:.4rem .8rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Use My Location
          </button>
          <span style="font-size:.72rem;color:var(--muted);align-self:center">Click map to set office location · Drag marker to adjust</span>
        </div>
      </div>
    </div>

    <!-- ── SHIFT TIMINGS ── -->
    <div class="section-card">
      <div class="sc-hdr">
        <div class="sc-icon" style="background:rgba(0,212,232,.12)">🕐</div>
        <div>
          <div class="sc-title">Shift Timings</div>
          <div class="sc-sub">Working hours and attendance window</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="form-grid" style="margin-bottom:1rem">
          <div class="form-group">
            <label class="form-label">Shift Start Time <span class="req">*</span></label>
            <input type="time" name="shift_start" id="fShiftStart" class="form-input" value="<?= $S['shift_start'] ?>" required oninput="updateShiftPreview()">
            <span class="form-hint">Kiosk opens at this time IST</span>
          </div>
          <div class="form-group">
            <label class="form-label">Shift End Time <span class="req">*</span></label>
            <input type="time" name="shift_end" id="fShiftEnd" class="form-input" value="<?= $S['shift_end'] ?>" required oninput="updateShiftPreview()">
            <span class="form-hint">Kiosk locks at this time IST</span>
          </div>
        </div>

        <!-- Live shift preview -->
        <div class="shift-preview" id="shiftPreview">
          <div class="sp-block">
            <div class="sp-val" id="spStart"><?= $S['shift_start'] ?></div>
            <div class="sp-lbl">Start IST</div>
          </div>
          <div class="sp-arrow">→</div>
          <div class="sp-block">
            <div class="sp-val" id="spEnd"><?= $S['shift_end'] ?></div>
            <div class="sp-lbl">End IST</div>
          </div>
          <div class="sp-duration" id="spDuration"></div>
          <div class="shift-status-live <?= $shiftActive ? 'ssl-on' : 'ssl-off' ?>" id="spStatus">
            <?= $shiftActive ? '🟢 Active Now' : '🔴 Closed Now' ?>
          </div>
        </div>

        <div style="margin-top:1rem">
          <div class="form-group">
            <label class="form-label">Auto Report Time</label>
            <div style="display:flex;gap:.75rem;align-items:center">
              <input type="time" name="auto_report_time" class="form-input" value="<?= $S['auto_report_time'] ?>" style="max-width:160px">
              <label class="toggle" style="flex-shrink:0">
                <input type="checkbox" name="auto_report_enabled" id="autoRepToggle" <?= $S['auto_report_enabled'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
              </label>
              <span style="font-size:.8rem;color:var(--muted)" id="autoRepLabel">
                <?= $S['auto_report_enabled'] ? 'Auto-send enabled' : 'Manual send only' ?>
              </span>
            </div>
            <span class="form-hint">Time to automatically send daily attendance reports</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── WORKING DAYS ── -->
    <div class="section-card">
      <div class="sc-hdr">
        <div class="sc-icon" style="background:rgba(0,255,136,.12)">📅</div>
        <div>
          <div class="sc-title">Working Days</div>
          <div class="sc-sub">Attendance kiosk only activates on selected days</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="days-picker" id="daysPicker">
          <?php foreach ($dayNames as $num => $name):
            $checked  = in_array($num, $S['working_days']);
            $isToday  = ($num === $todayDow);
            $short    = substr($name, 0, 3);
          ?>
          <div class="day-btn <?= $isToday ? 'day-today' : '' ?>">
            <input type="checkbox" name="working_days[]" value="<?= $num ?>" id="day<?= $num ?>" <?= $checked ? 'checked' : '' ?>>
            <label for="day<?= $num ?>" class="day-label">
              <span class="day-short"><?= $short ?></span>
              <span class="day-full"><?= $isToday ? '(Today)' : '' ?></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:.85rem;font-size:.78rem;color:var(--muted)" id="workingDaySummary"></div>
      </div>
    </div>

    <!-- ── LOCATION TRACKING ── -->
    <div class="section-card">
      <div class="sc-hdr">
        <div class="sc-icon" style="background:rgba(255,159,67,.12)">📡</div>
        <div>
          <div class="sc-title">Location Tracking</div>
          <div class="sc-sub">GPS ping frequency for employee tracking</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="form-group">
          <label class="form-label">Ping Interval</label>
          <div style="display:flex;align-items:center;gap:1rem;margin-top:.25rem">
            <input type="range" name="tracker_ping_interval" id="pingSlider" class="ping-slider" min="1" max="30" step="1" value="<?= $S['tracker_ping_interval'] ?>" oninput="document.getElementById('pingVal').textContent=this.value">
            <span class="ping-val"><span id="pingVal"><?= $S['tracker_ping_interval'] ?></span> min</span>
          </div>
          <span class="form-hint" style="margin-top:.5rem">How often employee phone sends GPS location (1 = every minute, battery intensive · 10+ = lighter on battery)</span>
        </div>
      </div>
    </div>

    <!-- ── EMAIL ── -->
    <div class="section-card">
      <div class="sc-hdr">
        <div class="sc-icon" style="background:rgba(167,139,250,.12)">✉️</div>
        <div>
          <div class="sc-title">Email / SMTP</div>
          <div class="sc-sub">Used for daily attendance report emails</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="form-grid" style="margin-bottom:1rem">
          <div class="form-group">
            <label class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" class="form-input" value="<?= htmlspecialchars($S['smtp_host']) ?>" placeholder="smtp.gmail.com">
          </div>
          <div class="form-group">
            <label class="form-label">SMTP Port</label>
            <select name="smtp_port" class="form-select">
              <option value="587" <?= $S['smtp_port']==587?'selected':'' ?>>587 — TLS (recommended)</option>
              <option value="465" <?= $S['smtp_port']==465?'selected':'' ?>>465 — SSL</option>
              <option value="25"  <?= $S['smtp_port']==25?'selected':'' ?>>25 — SMTP</option>
            </select>
          </div>
        </div>
        <div class="form-grid" style="margin-bottom:1rem">
          <div class="form-group">
            <label class="form-label">From Email</label>
            <input type="email" name="smtp_user" class="form-input" value="<?= htmlspecialchars($S['smtp_user']) ?>" placeholder="hr@yourcompany.com">
          </div>
          <div class="form-group">
            <label class="form-label">App Password</label>
            <input type="password" name="smtp_pass" class="form-input" value="<?= $S['smtp_pass'] ? '••••••••' : '' ?>" placeholder="Gmail App Password" autocomplete="new-password">
            <span class="form-hint">Use Gmail App Password, not your login password</span>
          </div>
        </div>
        <div class="form-group" style="max-width:320px">
          <label class="form-label">From Name</label>
          <input type="text" name="from_name" class="form-input" value="<?= htmlspecialchars($S['from_name']) ?>" placeholder="HR Team">
        </div>
      </div>
    </div>

    <!-- ── JSON PREVIEW ── -->
    <div class="section-card">
      <div class="sc-hdr">
        <div class="sc-icon" style="background:rgba(107,122,153,.12)">📄</div>
        <div>
          <div class="sc-title">settings.json Preview</div>
          <div class="sc-sub">Current saved configuration file</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="json-box"><?= htmlspecialchars(json_encode(loadJSON('settings.json'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></div>
        <div style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
          <button type="button" class="btn btn-ghost" onclick="downloadJSON()" style="font-size:.75rem;padding:.4rem .8rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download settings.json
          </button>
          <?php if ($S['updated_at'] ?? false): ?>
          <span style="font-size:.73rem;color:var(--muted);align-self:center">
            Last saved: <?= $S['updated_at'] ?> by <?= $S['updated_by'] ?? 'admin' ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- SAVE BAR -->
    <div class="save-bar">
      <div class="save-bar-left">
        Saving to <span>data/settings.json</span> · Changes apply immediately on next page load
      </div>
      <div style="display:flex;gap:.6rem">
        <a href="admin.php" class="btn btn-ghost" style="text-decoration:none">Cancel</a>
        <button type="submit" name="save_settings" class="btn btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Settings
        </button>
      </div>
    </div>

  </form>
</div>

<!-- Leaflet.js for map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ── Map ───────────────────────────────────────────────────────
let map, marker, circle;
const initLat = <?= $S['office_lat'] ?>;
const initLng = <?= $S['office_lng'] ?>;
const initR   = <?= $S['office_radius_m'] ?>;

document.addEventListener('DOMContentLoaded', () => {
  map = L.map('leafletMap', { zoomControl: true }).setView([initLat, initLng], 16);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
  }).addTo(map);

  marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
  circle = L.circle([initLat, initLng], {
    radius: initR, color: '#00d4e8', fillColor: '#00d4e8', fillOpacity: 0.08, weight: 2
  }).addTo(map);

  marker.on('dragend', e => {
    const p = e.target.getLatLng();
    document.getElementById('fLat').value = p.lat.toFixed(6);
    document.getElementById('fLng').value = p.lng.toFixed(6);
    circle.setLatLng(p);
  });

  map.on('click', e => {
    marker.setLatLng(e.latlng);
    circle.setLatLng(e.latlng);
    document.getElementById('fLat').value = e.latlng.lat.toFixed(6);
    document.getElementById('fLng').value = e.latlng.lng.toFixed(6);
  });

  updateShiftPreview();
  updateWorkingDaySummary();
});

function updateMap() {
  const lat = parseFloat(document.getElementById('fLat').value);
  const lng = parseFloat(document.getElementById('fLng').value);
  const r   = parseInt(document.getElementById('fRadius').value) || 150;
  if (isNaN(lat)||isNaN(lng)) return;
  marker.setLatLng([lat,lng]);
  circle.setLatLng([lat,lng]);
  circle.setRadius(r);
  map.setView([lat,lng], map.getZoom());
}

function updateRadiusLabel() {
  const r = parseInt(document.getElementById('fRadius').value) || 150;
  document.getElementById('radiusChip').textContent = r + 'm radius';
  if (circle) circle.setRadius(r);
}

function locateMe() {
  if (!navigator.geolocation) return alert('Geolocation not supported');
  navigator.geolocation.getCurrentPosition(pos => {
    const lat = pos.coords.latitude.toFixed(6);
    const lng = pos.coords.longitude.toFixed(6);
    document.getElementById('fLat').value = lat;
    document.getElementById('fLng').value = lng;
    updateMap();
  }, () => alert('Could not get location'));
}

// ── Shift Preview ─────────────────────────────────────────────
function updateShiftPreview() {
  const s = document.getElementById('fShiftStart').value;
  const e = document.getElementById('fShiftEnd').value;
  document.getElementById('spStart').textContent = s || '--:--';
  document.getElementById('spEnd').textContent   = e || '--:--';

  if (s && e) {
    const [sh,sm] = s.split(':').map(Number);
    const [eh,em] = e.split(':').map(Number);
    const totalMins = (eh*60+em) - (sh*60+sm);
    if (totalMins > 0) {
      const h = Math.floor(totalMins/60), m = totalMins%60;
      document.getElementById('spDuration').textContent =
        (h ? h+'h ' : '') + (m ? m+'m' : '') + ' shift';
    } else {
      document.getElementById('spDuration').textContent = '⚠️ Invalid range';
    }

    // Live status check
    const now = new Date();
    const nowMins = now.getHours()*60 + now.getMinutes();
    const startMins = sh*60+sm, endMins = eh*60+em;
    const active = nowMins >= startMins && nowMins <= endMins;
    const el = document.getElementById('spStatus');
    el.textContent = active ? '🟢 Active Now' : '🔴 Closed Now';
    el.className   = 'shift-status-live ' + (active ? 'ssl-on' : 'ssl-off');
  }
}

// ── Working Days Summary ──────────────────────────────────────
function updateWorkingDaySummary() {
  const names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const boxes = document.querySelectorAll('input[name="working_days[]"]:checked');
  const days  = Array.from(boxes).map(b => names[parseInt(b.value)]);
  const el    = document.getElementById('workingDaySummary');
  if (days.length === 0) {
    el.innerHTML = '<span style="color:var(--red)">⚠️ No working days selected — attendance will never activate</span>';
  } else if (days.length === 7) {
    el.textContent = '✅ Every day is a working day';
  } else {
    el.textContent = '✅ Working days: ' + days.join(', ');
  }
}

document.querySelectorAll('input[name="working_days[]"]').forEach(cb => {
  cb.addEventListener('change', updateWorkingDaySummary);
});

// ── Auto report toggle label ──────────────────────────────────
document.getElementById('autoRepToggle').addEventListener('change', function() {
  document.getElementById('autoRepLabel').textContent =
    this.checked ? 'Auto-send enabled' : 'Manual send only';
});

// ── Download JSON ─────────────────────────────────────────────
function downloadJSON() {
  const content = document.querySelector('.json-box').textContent;
  const blob    = new Blob([content], {type:'application/json'});
  const a       = document.createElement('a');
  a.href        = URL.createObjectURL(blob);
  a.download    = 'settings.json';
  a.click();
}
</script>
</body>
</html>
