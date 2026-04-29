<?php
require 'config.php';

// ── Load settings from JSON (overrides config.php constants) ──
$settings = array_merge([
    'office_name'     => OFFICE_NAME,
    'office_lat'      => OFFICE_LAT,
    'office_lng'      => OFFICE_LNG,
    'office_radius_m' => OFFICE_RADIUS_M,
    'shift_start'     => SHIFT_START,
    'shift_end'       => SHIFT_END,
    'match_threshold' => 0.50,
    'scan_interval_ms'=> 700,
    'gps_interval_sec'=> 30,
    'require_gps'     => true,
    'welcome_message' => 'Look at the camera to mark your attendance',
    'logo_url'        => '',
], loadJSON('settings.json'));

// ── Shift active check using live settings ────────────────────
$now         = date('H:i');
$shiftActive = ($now >= $settings['shift_start'] && $now <= $settings['shift_end']);
$shiftStart  = $settings['shift_start'];
$shiftEnd    = $settings['shift_end'];
$officeName  = $settings['office_name'];
$officeLat   = (float)$settings['office_lat'];
$officeLng   = (float)$settings['office_lng'];
$officeR     = (int)$settings['office_radius_m'];
$threshold   = (float)$settings['match_threshold'];
$scanMs      = (int)$settings['scan_interval_ms'];
$gpsInterval = (int)$settings['gps_interval_sec'];
$requireGps  = (bool)$settings['require_gps'];
$welcomeMsg  = htmlspecialchars($settings['welcome_message']);

// ── Today quick stats for display ────────────────────────────
$todayAtt    = (loadJSON('attendance.json')[today()] ?? []);
$todayCount  = count($todayAtt);
$inOfficeNow = count(array_filter($todayAtt, fn($a) => $a['in_office'] ?? false));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FaceShift  — Mark Attendance</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --teal:#00d4e8;--green:#00ff88;--red:#ff4757;--yellow:#ffd700;
  --bg:#080a10;--s1:#0e1220;--s2:#131826;--border:#1c2235;
  --text:#e2e8f8;--muted:#6b7a99;--faint:#141928
}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column}

/* ── Topbar ─────────────────────────────────────────────────── */
.topbar{
  background:var(--s1);border-bottom:1px solid var(--border);
  padding:.7rem 1.5rem;display:flex;align-items:center;
  justify-content:space-between;flex-shrink:0;position:sticky;top:0;z-index:50
}
.t-logo{display:flex;align-items:center;gap:.65rem}
.t-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--teal),#0077b6);
  border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.t-name{font-weight:700;color:#fff;font-size:.95rem}
.t-office{color:var(--muted);font-size:.72rem}
.t-right{display:flex;align-items:center;gap:.85rem}
.clock{font-variant-numeric:tabular-nums;color:var(--muted);font-size:.82rem;font-weight:500}
.shift-pill{
  display:inline-flex;align-items:center;gap:.4rem;font-size:.75rem;
  font-weight:600;padding:.3rem .75rem;border-radius:20px
}
.shift-on{background:rgba(0,255,136,.09);color:var(--green);border:1px solid rgba(0,255,136,.2)}
.shift-off{background:rgba(255,71,87,.09);color:var(--red);border:1px solid rgba(255,71,87,.2)}
.shift-dot{width:7px;height:7px;border-radius:50%;background:currentColor}
.pulse{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
.admin-lnk{
  color:var(--muted);font-size:.75rem;font-weight:500;text-decoration:none;
  padding:.3rem .7rem;border-radius:7px;border:1px solid var(--border);
  transition:all .18s;display:flex;align-items:center;gap:.35rem
}
.admin-lnk:hover{color:var(--text);border-color:var(--teal)}

/* ── Main wrap ───────────────────────────────────────────────── */
.main{flex:1;display:flex;align-items:center;justify-content:center;padding:1.5rem}

/* ── LOCKED SCREEN ───────────────────────────────────────────── */
.locked{text-align:center;max-width:460px;padding:1rem}
.locked-icon{
  width:80px;height:80px;border-radius:50%;
  background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.18);
  display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem
}
.locked h2{color:#fff;font-size:1.5rem;font-weight:700;margin-bottom:.6rem}
.locked p{color:var(--muted);font-size:.875rem;line-height:1.7}
.locked .shift-range{
  display:inline-block;background:var(--s1);border:1px solid var(--border);
  border-radius:10px;padding:.65rem 1.25rem;margin:1.25rem 0;
  font-size:.875rem;color:var(--text);font-weight:500
}
.locked .shift-range span{color:var(--teal);font-weight:700}
.locked-tip{
  background:var(--s2);border:1px solid var(--border);border-radius:10px;
  padding:.75rem 1rem;font-size:.78rem;color:var(--muted);margin-top:1rem
}
.locked-stat{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:1.25rem}
.lstat{background:var(--s1);border:1px solid var(--border);border-radius:10px;padding:.75rem}
.lstat-val{font-size:1.4rem;font-weight:700;color:#fff;font-variant-numeric:tabular-nums}
.lstat-lbl{font-size:.7rem;color:var(--muted);margin-top:.1rem}
.lstat-val.green{color:var(--green)}
.lstat-val.teal{color:var(--teal)}

/* ── SCANNER SCREEN ──────────────────────────────────────────── */
.scanner-wrap{width:100%;max-width:920px}
.scan-hdr{margin-bottom:1.1rem}
.scan-hdr h2{font-size:1.15rem;font-weight:700;color:#fff;margin-bottom:.2rem}
.scan-hdr p{color:var(--muted);font-size:.82rem}
.scanner-grid{display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start}
@media(max-width:800px){.scanner-grid{grid-template-columns:1fr}}

/* Camera card */
.cam-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.cam-topbar{
  padding:.65rem 1rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between
}
.cam-label{font-size:.78rem;font-weight:600;color:var(--muted);display:flex;align-items:center;gap:.4rem}
.rec-dot{width:8px;height:8px;border-radius:50%;background:var(--red);animation:pulse 1.2s infinite}
.cam-body{position:relative;background:#000;line-height:0}
#video{width:100%;display:block;transform:scaleX(-1);max-height:360px;object-fit:cover}
#faceCanvas{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none}
.scan-overlay{
  position:absolute;bottom:0;left:0;right:0;
  background:linear-gradient(transparent,rgba(8,10,16,.92));
  padding:.85rem 1rem;pointer-events:none
}
.scan-status-bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}
#scanLabel{font-size:.8rem;color:rgba(226,232,248,.85);flex:1}
.conf-badge{
  font-size:.7rem;font-weight:600;padding:.2rem .5rem;border-radius:20px;
  background:rgba(0,212,232,.15);color:var(--teal);border:1px solid rgba(0,212,232,.25);
  display:none;font-variant-numeric:tabular-nums
}
.cam-footer{
  padding:.65rem 1rem;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem
}
.gps-pill{display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:var(--muted)}
.gps-dot{width:8px;height:8px;border-radius:50%;background:var(--muted);transition:background .4s,box-shadow .4s}
.gps-dot.active{background:var(--green);box-shadow:0 0 6px var(--green)}
.gps-dot.outside{background:var(--yellow);box-shadow:0 0 6px var(--yellow)}
.gps-dot.denied{background:var(--red)}
.dist-text{font-size:.73rem;color:var(--muted);font-variant-numeric:tabular-nums}

/* Side panel */
.side{display:flex;flex-direction:column;gap:.85rem}
.panel{background:var(--s1);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.panel-hdr{padding:.7rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-title{font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.panel-body{padding:.85rem 1rem}

/* Status card */
.status-center{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.25rem 1rem;min-height:130px;text-align:center}
.s-emoji{font-size:2.2rem;margin-bottom:.5rem;line-height:1}
.s-name{font-size:1rem;font-weight:700;color:#fff;margin-bottom:.2rem}
.s-sub{font-size:.78rem;color:var(--muted);line-height:1.5}
.s-checkin{font-size:.88rem;font-weight:600;color:var(--green);margin:.3rem 0 .15rem}
.s-meta{font-size:.72rem;color:var(--muted)}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--teal);border-radius:50%;animation:spin .75s linear infinite;margin:0 auto .75rem}
@keyframes spin{to{transform:rotate(360deg)}}
.fade-in{animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* Outside warning banner */
.outside-banner{
  background:rgba(255,71,87,.06);border:1px solid rgba(255,71,87,.2);
  border-radius:10px;padding:.65rem .9rem;margin:.75rem 1rem;
  display:none;align-items:center;gap:.6rem;font-size:.78rem;color:var(--red)
}
.outside-banner.show{display:flex}

/* Leave events strip */
.leave-strip{display:flex;flex-direction:column;gap:.35rem;padding:.75rem 1rem;max-height:110px;overflow-y:auto}
.leave-ev{display:flex;align-items:center;gap:.5rem;font-size:.74rem}
.leave-ev-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.left-c{background:var(--red)}
.back-c{background:var(--green)}

/* Today list */
.today-list{max-height:200px;overflow-y:auto;padding:.75rem 1rem}
.today-item{display:flex;align-items:center;gap:.55rem;padding:.45rem 0;border-bottom:1px solid rgba(28,34,53,.5)}
.today-item:last-child{border:none}
.ti-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#0077b6);display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:700;color:#fff;flex-shrink:0}
.ti-name{flex:1;font-size:.78rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ti-time{font-size:.72rem;color:var(--muted);font-variant-numeric:tabular-nums;flex-shrink:0}
.ti-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* Instructions */
.instr-list{list-style:none;display:flex;flex-direction:column;gap:.4rem}
.instr-list li{display:flex;align-items:flex-start;gap:.5rem;font-size:.78rem;color:var(--muted);line-height:1.5}
.instr-list li span:first-child{font-size:.85rem;flex-shrink:0;margin-top:.05rem}

/* Flash */
.success-flash{animation:sFlash .5s ease}
@keyframes sFlash{0%{background:var(--bg)}50%{background:rgba(0,255,136,.04)}100%{background:var(--bg)}}
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:var(--faint);border-radius:2px}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="t-logo">
    <?php if (!empty($settings['logo_url'])): ?>
      <img src="<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" width="32" height="32" style="border-radius:8px">
    <?php else: ?>
    <div class="t-icon">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2">
        <circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/>
      </svg>
    </div>
    <?php endif; ?>
    <div>
      <div class="t-name">FaceShift </div>
      <div class="t-office"><?= $officeName ?></div>
    </div>
  </div>

  <div class="t-right">
    <span class="clock" id="clockEl">--:--:--</span>
    <span class="shift-pill <?= $shiftActive ? 'shift-on' : 'shift-off' ?>" id="shiftPill">
      <span class="shift-dot <?= $shiftActive ? 'pulse' : '' ?>" id="shiftDot"></span>
      <span id="shiftLabel"><?= $shiftActive ? 'Shift Active' : 'Shift Closed' ?></span>
      <span style="opacity:.6;font-size:.68rem;margin-left:.15rem"><?= $shiftStart ?>–<?= $shiftEnd ?></span>
    </span>
    <a class="admin-lnk" href="index.php">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      Admin
    </a>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main">

  <!-- ══ LOCKED ══════════════════════════════════════════════ -->
  <?php if (!$shiftActive): ?>
  <div class="locked">
    <div class="locked-icon">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="1.8">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
    </div>
    <h2>Attendance Locked</h2>
    <p>Face scanning is only available during official shift hours. The kiosk will unlock automatically when the shift begins.</p>
    <div class="shift-range">
      Shift hours &nbsp;→&nbsp; <span><?= $shiftStart ?></span> to <span><?= $shiftEnd ?></span>
    </div>
    <div class="locked-stat">
      <div class="lstat">
        <div class="lstat-val green"><?= $todayCount ?></div>
        <div class="lstat-lbl">Checked in today</div>
      </div>
      <div class="lstat">
        <div class="lstat-val teal"><?= $inOfficeNow ?></div>
        <div class="lstat-lbl">Currently in office</div>
      </div>
    </div>
    <div class="locked-tip">
      ⏳ This page auto-refreshes at <strong style="color:var(--text)"><?= $shiftStart ?></strong> to unlock the scanner.<br>
      Contact HR if you need to record attendance outside shift hours.
    </div>
  </div>

  <!-- ══ ACTIVE SCANNER ══════════════════════════════════════ -->
  <?php else: ?>
  <div class="scanner-wrap">
    <div class="scan-hdr">
      <h2>📷 Face Attendance Kiosk</h2>
      <p><?= $welcomeMsg ?></p>
    </div>

    <div class="scanner-grid">

      <!-- Camera -->
      <div class="cam-card">
        <div class="cam-topbar">
          <span class="cam-label">
            <span class="rec-dot"></span>
            Live Scan
          </span>
          <div style="display:flex;align-items:center;gap:.5rem">
            <span style="font-size:.7rem;color:var(--muted)" id="fpsLabel">initializing…</span>
            <span class="conf-badge" id="confBadge">0%</span>
          </div>
        </div>

        <div class="cam-body">
          <video id="video" autoplay playsinline muted></video>
          <canvas id="faceCanvas"></canvas>
          <!-- Outside office overlay on camera -->
          <div id="outsideOverlay" style="
            display:none;position:absolute;inset:0;
            background:rgba(255,71,87,.10);
            border:2px solid rgba(255,71,87,.45);
            border-radius:0;pointer-events:none;
            display:none;align-items:center;justify-content:center;flex-direction:column;gap:.4rem
          ">
            <div style="font-size:2rem">🚫</div>
            <div style="color:var(--red);font-size:.82rem;font-weight:700;text-align:center;padding:0 1rem">
              Outside Office<br>
              <span style="font-size:.7rem;font-weight:400;color:rgba(255,71,87,.7)">Move inside to mark attendance</span>
            </div>
          </div>
          <div class="scan-overlay">
            <div class="scan-status-bar">
              <span id="scanLabel">Loading AI models…</span>
            </div>
          </div>
        </div>

        <!-- Outside warning banner below camera -->
        <div class="outside-banner" id="outsideBanner">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span>You are <strong id="outsideDistText">outside</strong> the office geofence — move inside to mark attendance</span>
        </div>

        <div class="cam-footer">
          <div class="gps-pill">
            <div class="gps-dot" id="gpsDot"></div>
            <span id="gpsLabel">GPS: requesting…</span>
          </div>
          <span class="dist-text" id="distText"></span>
        </div>
      </div>

      <!-- Side panel -->
      <div class="side">

        <!-- Recognition status -->
        <div class="panel">
          <div class="panel-hdr">
            <span class="panel-title">Recognition Status</span>
            <span style="font-size:.7rem;color:var(--muted)" id="scanCountEl">0 scans</span>
          </div>
          <div id="statusCard">
            <div class="status-center">
              <div class="s-emoji">👁️</div>
              <div class="s-name" style="color:var(--muted)">Waiting…</div>
              <div class="s-sub">Position face in camera</div>
            </div>
          </div>
        </div>

        <!-- Leave / location events (shown after check-in) -->
        <div class="panel" id="leavePanel" style="display:none">
          <div class="panel-hdr">
            <span class="panel-title">Location Events</span>
            <span style="font-size:.7rem;color:var(--muted)" id="leaveCount">0 events</span>
          </div>
          <div class="leave-strip" id="leaveStrip">
            <div class="no-events" style="font-size:.75rem;color:var(--muted);text-align:center;padding:.5rem">No events yet</div>
          </div>
        </div>

        <!-- Today's attendance -->
        <div class="panel">
          <div class="panel-hdr">
            <span class="panel-title">Today's Check-ins</span>
            <span style="font-size:.7rem;color:var(--green);font-variant-numeric:tabular-nums" id="todayCountEl">
              <?= $todayCount ?> present
            </span>
          </div>
          <div class="today-list" id="todayList">
            <div style="color:var(--muted);font-size:.78rem;text-align:center;padding:.75rem">Loading…</div>
          </div>
        </div>

        <!-- Instructions -->
        <div class="panel">
          <div class="panel-hdr"><span class="panel-title">Instructions</span></div>
          <div class="panel-body">
            <ul class="instr-list">
              <li><span>👤</span><span>Centre your face in the camera frame</span></li>
              <li><span>💡</span><span>Ensure bright, even front lighting</span></li>
              <li><span>📍</span><span>Allow GPS — you must be inside office to check in</span></li>
              <li><span>📱</span><span>Keep this page open for live GPS tracking</span></li>
              <li><span>📊</span><span>Location is pinged every <?= $gpsInterval ?>s automatically</span></li>
            </ul>
          </div>
        </div>

      </div><!-- /side -->
    </div><!-- /scanner-grid -->
  </div><!-- /scanner-wrap -->
  <?php endif; ?>

</div><!-- /main -->

<!-- ══ JS ══════════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
// ── Runtime config from PHP / settings.json ───────────────────
const CFG = {
  officeLat     : <?= json_encode($officeLat) ?>,
  officeLng     : <?= json_encode($officeLng) ?>,
  officeRadius  : <?= json_encode($officeR) ?>,
  threshold     : <?= json_encode($threshold) ?>,
  scanMs        : <?= json_encode($scanMs) ?>,
  gpsIntervalSec: <?= json_encode($gpsInterval) ?>,
  requireGps    : <?= json_encode($requireGps) ?>,
  shiftStart    : <?= json_encode($shiftStart) ?>,
  shiftEnd      : <?= json_encode($shiftEnd) ?>,
};

const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';

// ── Clock + live shift-boundary reload ───────────────────────
function updateClock() {
  const n    = new Date();
  const hms  = n.toTimeString().slice(0, 8);
  document.getElementById('clockEl').textContent = hms;
  const hhmm = hms.slice(0, 5);
  if (hhmm === CFG.shiftStart || hhmm === CFG.shiftEnd) {
    setTimeout(() => location.reload(), 1000);
  }
}
setInterval(updateClock, 1000);
updateClock();

<?php if ($shiftActive): ?>
// ────────────────────────────────────────────────────────────
// Only executes during active shift
// ────────────────────────────────────────────────────────────

let matcher       = null;
let markedEmpId   = null;
let markedEmpName = null;
let isProcessing  = false;
let scanCount     = 0;
let gpsWatchId    = null;
let gpsIntervalId = null;
let lastLat       = null;
let lastLng       = null;
let lastInOffice  = null;   // null = unknown, true = inside, false = outside
let leaveEvents   = [];

// ── Boot ─────────────────────────────────────────────────────
async function boot() {
  setScanLabel('Loading AI models…');
  try {
    await Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
    ]);
    setScanLabel('AI ready ✓ — starting camera…');
    await startCamera();
  } catch(e) {
    setScanLabel('⚠️ Failed to load models. Check internet connection.');
    console.error('Model load error:', e);
  }
}

// ── Camera ───────────────────────────────────────────────────
async function startCamera() {
  const video = document.getElementById('video');
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode:'user', width:{ ideal:1280 }, height:{ ideal:720 } }
    });
    video.srcObject = stream;
    video.onloadedmetadata = () => {
      document.getElementById('fpsLabel').textContent = 'camera live';
      setScanLabel('Loading employee database…');
      loadDescriptors();
      loadTodayList();
      requestGPSOnce();   // Get initial GPS reading immediately
    };
  } catch(e) {
    setScanLabel('⚠️ Camera access denied — please allow camera.');
    document.getElementById('fpsLabel').textContent = 'no camera';
  }
}

// ── Load face descriptors from server ────────────────────────
async function loadDescriptors() {
  try {
    const res  = await fetch('api.php?action=descriptors');
    const data = await res.json();

    if (!data.employees || data.employees.length === 0) {
      setScanLabel('⚠️ No employees registered. Go Admin → Employees to register.');
      setStatusWaiting('No employees', 'Register employees first');
      return;
    }

    const labeled = data.employees.map(emp => {
      const desc = emp.descriptors.map(d => new Float32Array(d));
      return new faceapi.LabeledFaceDescriptors(emp.id + '::' + emp.name, desc);
    });

    matcher = new faceapi.FaceMatcher(labeled, CFG.threshold);
    setScanLabel(`Scanning… ${data.employees.length} employee${data.employees.length !== 1 ? 's' : ''} registered`);
    document.getElementById('fpsLabel').textContent = `${data.employees.length} registered`;
    startDetection();

  } catch(e) {
    setScanLabel('⚠️ Failed to load employee data.');
  }
}

// ── Face detection loop ───────────────────────────────────────
function startDetection() {
  const video  = document.getElementById('video');
  const canvas = document.getElementById('faceCanvas');

  setInterval(async () => {
    if (!matcher || isProcessing) return;
    if (video.paused || video.ended || video.readyState < 2) return;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx     = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const opts       = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 });
    const detections = await faceapi
      .detectAllFaces(video, opts)
      .withFaceLandmarks()
      .withFaceDescriptors();

    scanCount++;
    document.getElementById('scanCountEl').textContent = `${scanCount} scans`;

    if (detections.length === 0) {
      if (!markedEmpId) {
        setScanLabel('No face detected — look at the camera');
        setStatusWaiting('Waiting…', 'Position face in camera');
      }
      document.getElementById('confBadge').style.display = 'none';
      return;
    }

    for (const det of detections) {
      const box        = det.detection.box;
      const mirroredX  = canvas.width - box.x - box.width;
      const result     = matcher.findBestMatch(det.descriptor);
      const isKnown    = result.label !== 'unknown';
      const conf       = Math.round((1 - result.distance) * 100);

      // Draw face box
      ctx.strokeStyle = isKnown ? 'rgba(0,212,232,.9)' : 'rgba(255,71,87,.7)';
      ctx.lineWidth   = 2;
      ctx.strokeRect(mirroredX, box.y, box.width, box.height);

      if (isKnown) {
        ctx.fillStyle = 'rgba(0,212,232,.85)';
        ctx.font      = '12px Inter,sans-serif';
        const [, name] = result.label.split('::');
        ctx.fillText(`${name} ${conf}%`, mirroredX, box.y - 6);
      }

      // ── Attendance trigger gate ───────────────────────────
      if (isKnown && !markedEmpId && conf >= Math.round((1 - CFG.threshold) * 100)) {
        const [empId, empName] = result.label.split('::');
        const confidence       = 1 - result.distance;

        // GPS GATE: block if outside office
        if (CFG.requireGps && lastInOffice === false) {
          setScanLabel('🚫 You are outside the office — move inside to mark attendance');
          setStatusOutside();
          document.getElementById('confBadge').style.display = 'none';
          return;
        }

        // GPS still unknown — if requireGps, wait for it
        if (CFG.requireGps && lastInOffice === null) {
          setScanLabel('⏳ Waiting for GPS confirmation…');
          return;
        }

        document.getElementById('confBadge').textContent = `${conf}%`;
        document.getElementById('confBadge').style.display = 'inline-flex';
        setScanLabel(`Recognizing ${empName}… ${conf}%`);
        setStatusScanning(empName);
        isProcessing = true;
        await markAttendance(empId, empName, confidence);
      }
    }
  }, CFG.scanMs);
}

// ── Mark attendance API call ──────────────────────────────────
async function markAttendance(empId, empName, confidence) {
  try {
    const res  = await fetch('api.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify({
        action: 'mark_attendance',
        emp_id: empId, emp_name: empName, confidence
      })
    });
    const data = await res.json();

    if (!data.success && !data.already) {
      setScanLabel('⚠️ Server error. Try again.');
      isProcessing = false;
      return;
    }

    markedEmpId   = empId;
    markedEmpName = empName;
    document.body.classList.add('success-flash');
    setTimeout(() => document.body.classList.remove('success-flash'), 500);

    if (data.already) {
      setStatusAlready(empName, empId, data.checkin, confidence);
      setScanLabel(`👋 Welcome back, ${empName}! Already checked in at ${data.checkin}`);
    } else {
      setStatusSuccess(empName, empId, data.check_in, confidence);
      setScanLabel(`✅ ${empName} marked present at ${data.check_in}`);
      loadTodayList();
    }

    document.getElementById('leavePanel').style.display = 'block';
    startGPSTracking(empId);

  } catch(e) {
    setScanLabel('⚠️ Network error. Check connection.');
    isProcessing = false;
  }
}

// ── GPS: one-shot initial read ────────────────────────────────
function requestGPSOnce() {
  if (!navigator.geolocation) {
    setGPS('denied', 'GPS not supported', '');
    lastInOffice = null;
    return;
  }
  navigator.geolocation.getCurrentPosition(
    pos => {
      lastLat      = pos.coords.latitude;
      lastLng      = pos.coords.longitude;
      const dist   = haversine(lastLat, lastLng, CFG.officeLat, CFG.officeLng);
      lastInOffice = dist <= CFG.officeRadius;
      applyOutsideUI(lastInOffice, dist);
    },
    () => {
      if (CFG.requireGps) {
        setGPS('denied', 'GPS required — allow location', '');
        lastInOffice = null;
      } else {
        setGPS('denied', 'GPS optional — unavailable', '');
        lastInOffice = true; // don't block if GPS not required
      }
    },
    { enableHighAccuracy: true, timeout: 12000 }
  );
}

// ── GPS: continuous tracking after check-in ───────────────────
function startGPSTracking(empId) {
  if (!navigator.geolocation) return;
  const opts = { enableHighAccuracy: true, maximumAge: 20000, timeout: 15000 };

  // watchPosition for immediate callbacks on movement
  gpsWatchId = navigator.geolocation.watchPosition(
    pos => {
      lastLat = pos.coords.latitude;
      lastLng = pos.coords.longitude;
      sendLocation(empId, lastLat, lastLng);
    },
    err => {
      setGPS('denied',
        err.code === 1 ? 'GPS permission denied' : 'GPS unavailable', '');
    },
    opts
  );

  // Belt-and-braces interval (watchPosition can stall on some phones)
  gpsIntervalId = setInterval(() => {
    if (lastLat !== null) sendLocation(empId, lastLat, lastLng);
  }, CFG.gpsIntervalSec * 1000);
}

// ── Send location to server ───────────────────────────────────
async function sendLocation(empId, lat, lng) {
  const dist     = haversine(lat, lng, CFG.officeLat, CFG.officeLng);
  const inOffice = dist <= CFG.officeRadius;

  // ── OUTSIDE + no state change → UI only, ZERO data sent ──
  if (!inOffice && lastInOffice === false) {
    applyOutsideUI(false, dist);
    const locLine = document.getElementById('statusLocLine');
    if (locLine) locLine.textContent = `🚗 Outside office — ${Math.round(dist)}m away`;
    return;  // <-- no fetch, no data sent to server
  }

  // ── State changed OR currently in office → send to server ──
  try {
    const res  = await fetch('api.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify({ action: 'update_location', emp_id: empId, lat, lng })
    });
    const data = await res.json();

    applyOutsideUI(inOffice, dist);

    // Record leave / return event in UI
    if (lastInOffice !== null && lastInOffice !== inOffice) {
      if (!inOffice) {
        leaveEvents.push({ left_at: nowHHMM(), returned_at: null });
        appendLeaveEvent('left', nowHHMM());
      } else {
        const last = leaveEvents[leaveEvents.length - 1];
        if (last && !last.returned_at) {
          last.returned_at = nowHHMM();
          appendLeaveEvent('returned', nowHHMM());
        }
      }
      updateLeaveCount();
    }

    lastInOffice = inOffice;

    const locLine = document.getElementById('statusLocLine');
    if (locLine) {
      locLine.textContent = inOffice
        ? '🏢 In office'
        : `🚗 Outside — ${Math.round(dist)}m away`;
    }

  } catch(e) { /* silent */ }
}

// ── Apply outside / inside UI changes ─────────────────────────
function applyOutsideUI(inOffice, dist) {
  const banner  = document.getElementById('outsideBanner');
  const overlay = document.getElementById('outsideOverlay');

  if (inOffice) {
    setGPS('active', '🏢 In office', `${Math.round(dist)}m from office`);
    banner.classList.remove('show');
    if (overlay) overlay.style.display = 'none';
    // Reset outside status card only if not yet checked in
    if (!markedEmpId) setStatusWaiting('Waiting…', 'Position face in camera');
  } else {
    setGPS('outside', '⚠️ Outside office', `${Math.round(dist)}m from office`);
    banner.classList.add('show');
    document.getElementById('outsideDistText').textContent =
      `${Math.round(dist)}m outside`;
    if (overlay) overlay.style.display = 'flex';
    // Only show outside status card if not already checked in
    if (!markedEmpId) setStatusOutside();
  }
}

// ── Leave panel UI helpers ────────────────────────────────────
function appendLeaveEvent(type, time) {
  const strip = document.getElementById('leaveStrip');
  const noEv  = strip.querySelector('.no-events');
  if (noEv) noEv.remove();
  const colorClass = type === 'left' ? 'left-c' : 'back-c';
  const label      = type === 'left' ? '🚗 Left office' : '🏢 Returned';
  const textColor  = type === 'left' ? 'var(--yellow)' : 'var(--green)';
  strip.insertAdjacentHTML('beforeend', `
    <div class="leave-ev">
      <div class="leave-ev-dot ${colorClass}"></div>
      <span style="color:${textColor}">${label}</span>
      <span style="color:var(--muted);margin-left:auto;font-variant-numeric:tabular-nums">${time}</span>
    </div>`);
  strip.scrollTop = strip.scrollHeight;
}

function updateLeaveCount() {
  document.getElementById('leaveCount').textContent =
    `${leaveEvents.length} event${leaveEvents.length !== 1 ? 's' : ''}`;
}

// ── Status card states ────────────────────────────────────────
function setStatusWaiting(name, sub) {
  document.getElementById('statusCard').innerHTML = `
    <div class="status-center">
      <div class="s-emoji">👁️</div>
      <div class="s-name" style="color:var(--muted)">${name}</div>
      <div class="s-sub">${sub}</div>
    </div>`;
}

function setStatusScanning(name) {
  document.getElementById('statusCard').innerHTML = `
    <div class="status-center">
      <div class="spinner"></div>
      <div class="s-name" style="color:var(--teal)">Recognizing…</div>
      <div class="s-sub">${name}</div>
    </div>`;
}

function setStatusOutside() {
  document.getElementById('statusCard').innerHTML = `
    <div class="status-center fade-in">
      <div class="s-emoji">🚫</div>
      <div class="s-name" style="color:var(--red)">Outside Office</div>
      <div class="s-sub" style="color:var(--muted)">
        You must be inside the office<br>premises to mark attendance
      </div>
      <div style="
        margin-top:.75rem;font-size:.72rem;
        background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);
        border-radius:8px;padding:.45rem .75rem;color:var(--red)
      ">⛔ Geofence check failed</div>
    </div>`;
}

function setStatusSuccess(name, id, time, conf) {
  document.getElementById('statusCard').innerHTML = `
    <div class="status-center fade-in">
      <div class="s-emoji">✅</div>
      <div class="s-name">${name}</div>
      <div class="s-sub" style="color:var(--muted)">${id}</div>
      <div class="s-checkin">Checked in at ${time}</div>
      <div class="s-meta">Confidence: ${Math.round(conf * 100)}%</div>
      <div class="s-meta" id="statusLocLine">📍 Acquiring GPS…</div>
    </div>`;
}

function setStatusAlready(name, id, checkin, conf) {
  document.getElementById('statusCard').innerHTML = `
    <div class="status-center fade-in">
      <div class="s-emoji">👋</div>
      <div class="s-name">${name}</div>
      <div class="s-sub" style="color:var(--muted)">${id}</div>
      <div class="s-checkin">Checked in at ${checkin}</div>
      <div class="s-meta">Confidence: ${Math.round(conf * 100)}%</div>
      <div class="s-meta" id="statusLocLine">📍 Acquiring GPS…</div>
    </div>`;
}

// ── Today's check-in list ─────────────────────────────────────
async function loadTodayList() {
  try {
    const res  = await fetch('api.php?action=today_data');
    const data = await res.json();
    const list = document.getElementById('todayList');
    const rows = (data.rows || []).filter(r => r.status === 'present');

    document.getElementById('todayCountEl').textContent = `${rows.length} present`;

    if (!rows.length) {
      list.innerHTML = '<div style="color:var(--muted);font-size:.76rem;text-align:center;padding:.75rem">No attendance yet today</div>';
      return;
    }

    list.innerHTML = rows.map(r => `
      <div class="today-item">
        <div class="ti-av">${r.name.substring(0, 2).toUpperCase()}</div>
        <div class="ti-name">${r.name}</div>
        <div class="ti-time">${r.check_in || '—'}</div>
        <div class="ti-dot" style="background:${r.in_office ? 'var(--green)' : 'var(--yellow)'}"></div>
      </div>`).join('');
  } catch(e) { /* silent */ }
}

// ── Utility helpers ───────────────────────────────────────────
function setScanLabel(txt) {
  document.getElementById('scanLabel').textContent = txt;
}

function setGPS(state, label, dist) {
  document.getElementById('gpsDot').className   = `gps-dot ${state}`;
  document.getElementById('gpsLabel').textContent = label;
  if (dist) document.getElementById('distText').textContent = dist;
}

function haversine(lat1, lon1, lat2, lon2) {
  const R  = 6371000;
  const dL = (lat2 - lat1) * Math.PI / 180;
  const dO = (lon2 - lon1) * Math.PI / 180;
  const a  = Math.sin(dL / 2) ** 2
           + Math.cos(lat1 * Math.PI / 180)
           * Math.cos(lat2 * Math.PI / 180)
           * Math.sin(dO / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function nowHHMM() {
  return new Date().toTimeString().slice(0, 5);
}

// Auto-refresh today list every 30s
setInterval(loadTodayList, 30000);

// Boot!
boot();
<?php endif; ?>
</script>
</body>
</html>
