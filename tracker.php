<?php
require 'config.php';
$pingMins   = TRACKER_PING_INTERVAL;
$shiftEnd   = SHIFT_END;
$shiftStart = SHIFT_START;
$officeName = OFFICE_NAME;

// Check shift active for initial page render
$now         = date('H:i');
$shiftActive = ($now >= $shiftStart && $now <= $shiftEnd);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FaceShift — Employee Tracker</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --teal:#00d4e8;--green:#00ff88;--red:#ff4757;--yellow:#ffd700;
  --bg:#080a10;--s1:#0e1220;--s2:#131826;--border:#1c2235;
  --text:#e2e8f8;--muted:#6b7a99;--faint:#1c2235
}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column}

/* ── Topbar ─────────────────────────────────────────────────── */
.topbar{background:var(--s1);border-bottom:1px solid var(--border);padding:.7rem 1.25rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.tb-brand{display:flex;align-items:center;gap:.6rem}
.tb-icon{width:34px;height:34px;background:linear-gradient(135deg,var(--teal),#0077b6);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tb-name{font-weight:700;color:#fff;font-size:.95rem}
.tb-sub{color:var(--muted);font-size:.72rem}
.tb-right{display:flex;align-items:center;gap:.85rem}
.ist-clock{font-size:.8rem;color:var(--muted);font-variant-numeric:tabular-nums}
.shift-pill{display:inline-flex;align-items:center;gap:.4rem;font-size:.73rem;font-weight:600;padding:.28rem .7rem;border-radius:20px}
.shift-on{background:rgba(0,255,136,.09);color:var(--green);border:1px solid rgba(0,255,136,.2)}
.shift-off{background:rgba(255,71,87,.09);color:var(--red);border:1px solid rgba(255,71,87,.2)}
.shift-dot{width:7px;height:7px;border-radius:50%;background:currentColor}
.pulse{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
.kiosk-lnk{color:var(--muted);font-size:.75rem;text-decoration:none;padding:.3rem .7rem;border-radius:7px;border:1px solid var(--border);transition:all .18s;display:flex;align-items:center;gap:.35rem}
.kiosk-lnk:hover{color:var(--text);border-color:var(--teal)}

/* ── Main ────────────────────────────────────────────────────── */
.main{flex:1;display:flex;align-items:center;justify-content:center;padding:1.25rem}

/* ── SHIFT LOCKED (pre/post shift) ───────────────────────────── */
.shift-locked{text-align:center;max-width:400px}
.sl-icon{width:72px;height:72px;border-radius:50%;background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.18);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem}
.shift-locked h2{color:#fff;font-weight:700;margin-bottom:.5rem}
.shift-locked p{color:var(--muted);font-size:.875rem;line-height:1.7}
.shift-range{display:inline-block;background:var(--s1);border:1px solid var(--border);border-radius:10px;padding:.6rem 1.2rem;margin:1rem 0;font-size:.875rem;color:var(--text)}
.shift-range span{color:var(--teal);font-weight:700}

/* ── STEP 1 — Employee ID ─────────────────────────────────────── */
.step-wrap{width:100%;max-width:420px;animation:fadeUp .4s ease}
.step-card{background:var(--s1);border:1px solid var(--border);border-radius:18px;padding:2rem}
.step-icon{width:52px;height:52px;border-radius:13px;background:linear-gradient(135deg,var(--teal),#0077b6);display:flex;align-items:center;justify-content:center;margin-bottom:1.2rem}
.step-title{color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:.3rem}
.step-sub{color:var(--muted);font-size:.82rem;line-height:1.65;margin-bottom:1.4rem}
.field{margin-bottom:1rem}
label{display:block;color:#8892a4;font-size:.78rem;font-weight:500;margin-bottom:.4rem}
input[type=text]{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:.7rem 1rem;font-size:.95rem;font-family:'Inter',sans-serif;outline:none;transition:border .2s,box-shadow .2s;text-transform:uppercase;letter-spacing:.05em}
input[type=text]:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,212,232,.1)}
.btn-full{width:100%;background:linear-gradient(135deg,var(--teal),#0077b6);border:none;color:#fff;padding:.78rem;border-radius:10px;font-weight:600;font-size:.92rem;cursor:pointer;font-family:'Inter',sans-serif;transition:opacity .2s}
.btn-full:hover{opacity:.85}
.btn-full:disabled{opacity:.45;cursor:not-allowed}
.err-msg{background:rgba(255,71,87,.1);border:1px solid rgba(255,71,87,.25);color:#ff6b7a;border-radius:8px;padding:.6rem .9rem;font-size:.8rem;margin-bottom:1rem;display:none}
.emp-found-card{background:var(--s2);border:1px solid rgba(0,212,232,.2);border-radius:10px;padding:.85rem 1rem;display:none;align-items:center;gap:.75rem;margin-bottom:1.25rem;animation:fadeUp .3s ease}
.emp-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#0077b6);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#fff;flex-shrink:0}
.emp-details .ename{font-weight:600;color:#fff;font-size:.9rem}
.emp-details .edept{color:var(--muted);font-size:.75rem}
.emp-details .eid{color:var(--teal);font-size:.72rem}
.found-icon{margin-left:auto;color:var(--green);font-size:1.1rem}

/* ── STEP 2 — Face Verify ─────────────────────────────────────── */
.verify-wrap{width:100%;max-width:500px;animation:fadeUp .4s ease}
.verify-card{background:var(--s1);border:1px solid var(--border);border-radius:18px;overflow:hidden}
.verify-hdr{padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.verify-ttl{color:#fff;font-weight:700;font-size:.95rem}
.verify-sub{color:var(--muted);font-size:.78rem;margin-top:.25rem}
.cam-box{position:relative;background:#000;line-height:0}
#vVideo{width:100%;display:block;transform:scaleX(-1);min-height:250px;object-fit:cover}
#vCanvas{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none}
/* Corner frame */
.v-corners{position:absolute;inset:0;pointer-events:none}
.vc{position:absolute;width:22px;height:22px;border-color:var(--teal);border-style:solid;border-width:0;transition:border-color .3s}
.vc.tl{top:10px;left:10px;border-top-width:2px;border-left-width:2px}
.vc.tr{top:10px;right:10px;border-top-width:2px;border-right-width:2px}
.vc.bl{bottom:10px;left:10px;border-bottom-width:2px;border-left-width:2px}
.vc.br{bottom:10px;right:10px;border-bottom-width:2px;border-right-width:2px}
.vc.ok{border-color:var(--green)}
.vscanline{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--teal),transparent);animation:sl 2.5s ease-in-out infinite}
@keyframes sl{0%{top:5%;opacity:0}8%{opacity:1}92%{opacity:1}100%{top:95%;opacity:0}}
.verify-foot{padding:.8rem 1.25rem;border-top:1px solid var(--border)}
.verify-status{font-size:.8rem;color:var(--muted);text-align:center;min-height:1.2em}
.back-btn{background:none;border:none;color:var(--muted);font-size:.8rem;cursor:pointer;font-family:'Inter',sans-serif;margin-top:.6rem;display:block;width:100%;text-align:center;transition:color .2s}
.back-btn:hover{color:var(--text)}

/* ── STEP 3 — Active Tracking ─────────────────────────────────── */
.tracker-wrap{width:100%;max-width:480px;animation:fadeUp .4s ease;display:flex;flex-direction:column;gap:.85rem}

.emp-header{background:var(--s1);border:1px solid var(--border);border-radius:16px;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:.85rem}
.eh-av{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#0077b6);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;color:#fff;flex-shrink:0}
.eh-name{font-weight:700;color:#fff;font-size:.95rem;line-height:1.3}
.eh-detail{color:var(--muted);font-size:.75rem}
.eh-id{color:var(--teal);font-size:.72rem}
.verified-badge{margin-left:auto;background:rgba(0,255,136,.1);border:1px solid rgba(0,255,136,.2);color:var(--green);font-size:.68rem;font-weight:600;padding:.22rem .55rem;border-radius:20px;white-space:nowrap}

/* Status card */
.status-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;padding:1.5rem;text-align:center;transition:border-color .4s,background .4s}
.status-card.in-off{border-color:rgba(0,255,136,.4);background:rgba(0,255,136,.04)}
.status-card.outside{border-color:rgba(255,215,0,.35);background:rgba(255,215,0,.03)}
.status-card.gps-err{border-color:rgba(255,71,87,.3);background:rgba(255,71,87,.03)}
.status-card.stopped{border-color:var(--border);background:var(--s1)}
.status-big{font-size:2.8rem;margin-bottom:.4rem;line-height:1}
.status-label{font-size:1rem;font-weight:700;color:#fff;margin-bottom:.3rem}
.dist-text{font-size:.8rem;color:var(--muted);margin-bottom:.35rem}
.ist-update{font-size:.72rem;color:var(--muted)}

/* Ping card */
.ping-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;padding:1.1rem 1.25rem}
.ping-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem}
.ping-ttl{font-size:.8rem;font-weight:600;color:var(--muted)}
.ping-badge{background:var(--s2);border:1px solid var(--border);color:var(--teal);font-size:.7rem;padding:.18rem .5rem;border-radius:20px;font-variant-numeric:tabular-nums}
.ping-countdown{margin-bottom:.75rem}
.pcd-label{font-size:.7rem;color:var(--muted);margin-bottom:.3rem;display:flex;justify-content:space-between}
.pcd-bar-wrap{background:var(--faint);border-radius:4px;height:5px;overflow:hidden}
.pcd-bar{height:100%;background:linear-gradient(90deg,var(--teal),#0077b6);border-radius:4px;transition:width 1s linear;width:100%}
.pcd-time{font-size:.75rem;color:var(--muted);margin-top:.3rem;text-align:right;font-variant-numeric:tabular-nums}
.ping-log{max-height:190px;overflow-y:auto}
.ping-row{display:flex;align-items:center;gap:.55rem;padding:.38rem 0;border-bottom:1px solid rgba(28,34,53,.5);font-size:.75rem}
.ping-row:last-child{border:none}
.ping-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.ping-time-col{color:var(--muted);font-variant-numeric:tabular-nums;width:72px;flex-shrink:0;font-size:.7rem}
.ping-detail-col{flex:1;color:var(--text)}

/* Shift end card */
.shift-end-card{background:rgba(255,71,87,.06);border:1px solid rgba(255,71,87,.2);border-radius:14px;padding:1.1rem 1.25rem;text-align:center;display:none}
.se-icon{font-size:1.8rem;margin-bottom:.4rem}
.se-title{color:#fff;font-weight:700;font-size:.95rem;margin-bottom:.3rem}
.se-desc{color:var(--muted);font-size:.8rem;line-height:1.6}

/* Shared */
.spin{display:inline-block;width:16px;height:16px;border:2.5px solid var(--faint);border-top-color:var(--teal);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:var(--faint);border-radius:2px}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="tb-brand">
    <div class="tb-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
        <circle cx="12" cy="10" r="3"/>
      </svg>
    </div>
    <div>
      <div class="tb-name">Employee GPS Tracker</div>
      <div class="tb-sub"><?= $officeName ?> · Pings every <?= $pingMins ?> min during shift</div>
    </div>
  </div>
  <div class="tb-right">
    <span class="ist-clock" id="clock">--:--:--</span>
    <span class="shift-pill <?= $shiftActive ? 'shift-on' : 'shift-off' ?>">
      <span class="shift-dot <?= $shiftActive ? 'pulse' : '' ?>"></span>
      <?= $shiftActive ? 'Shift Active' : 'Shift Closed' ?>
      <span style="opacity:.6;font-size:.68rem;margin-left:.1rem"><?= $shiftStart ?>–<?= $shiftEnd ?></span>
    </span>
    <a class="kiosk-lnk" href="attendance.php">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/>
      </svg>
      Kiosk
    </a>
  </div>
</div>

<div class="main" id="mainArea">

  <!-- ══ SHIFT NOT ACTIVE ════════════════════════════════════ -->
  <?php if (!$shiftActive): ?>
  <div class="shift-locked">
    <div class="sl-icon">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="1.8">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
    </div>
    <h2>Tracker Unavailable</h2>
    <p>GPS location tracking is only active during shift hours. The tracker will unlock automatically when the shift begins.</p>
    <div class="shift-range">
      Shift: <span><?= $shiftStart ?></span> → <span><?= $shiftEnd ?></span>
    </div>
    <p style="font-size:.78rem;color:var(--muted)">This page will reload automatically at shift start.</p>
  </div>

  <?php else: ?>

  <!-- ══ STEP 1: ENTER EMPLOYEE ID ═══════════════════════════ -->
  <div class="step-wrap" id="step1">
    <div class="step-card">
      <div class="step-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2">
          <rect x="2" y="7" width="20" height="14" rx="2"/>
          <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          <line x1="12" y1="12" x2="12" y2="16"/>
          <line x1="10" y1="14" x2="14" y2="14"/>
        </svg>
      </div>
      <div class="step-title">Start Location Tracking</div>
      <div class="step-sub">Enter your Employee ID. You'll then verify your identity via face recognition before tracking begins for the day.</div>

      <div class="err-msg" id="errMsg"></div>

      <div class="emp-found-card" id="empFoundCard">
        <div class="emp-av" id="empAv">--</div>
        <div class="emp-details">
          <div class="ename" id="empFoundName">--</div>
          <div class="edept" id="empFoundDept">--</div>
          <div class="eid" id="empFoundId">--</div>
        </div>
        <div class="found-icon">✓</div>
      </div>

      <div class="field">
        <label>Employee ID</label>
        <input type="text" id="empIdInput" placeholder="EMP0001" maxlength="10"
          oninput="this.value=this.value.toUpperCase()"
          onkeydown="if(event.key==='Enter')lookupEmployee()">
      </div>
      <button class="btn-full" id="lookupBtn" onclick="lookupEmployee()">Look Up Employee →</button>
    </div>
  </div>

  <!-- ══ STEP 2: FACE VERIFICATION ══════════════════════════ -->
  <div class="verify-wrap" id="step2" style="display:none">
    <div class="verify-card">
      <div class="verify-hdr">
        <div class="verify-ttl">Face Verification</div>
        <div class="verify-sub">Confirm identity as <strong id="verifyName" style="color:var(--teal)">--</strong></div>
      </div>
      <div class="cam-box">
        <video id="vVideo" autoplay playsinline muted></video>
        <canvas id="vCanvas"></canvas>
        <div class="v-corners">
          <div class="vc tl" id="vtl"></div>
          <div class="vc tr" id="vtr"></div>
          <div class="vc bl" id="vbl"></div>
          <div class="vc br" id="vbr"></div>
          <div class="vscanline"></div>
        </div>
      </div>
      <div class="verify-foot">
        <div class="verify-status" id="verifyStatus">Loading AI models…</div>
      </div>
    </div>
    <button class="back-btn" onclick="goBack()">← Try a different ID</button>
  </div>

  <!-- ══ STEP 3: ACTIVE TRACKING ════════════════════════════ -->
  <div class="tracker-wrap" id="step3" style="display:none">

    <!-- Employee header -->
    <div class="emp-header">
      <div class="eh-av" id="trackAv">--</div>
      <div>
        <div class="eh-name" id="trackName">--</div>
        <div class="eh-detail" id="trackDept">--</div>
        <div class="eh-id" id="trackId">--</div>
      </div>
      <div class="verified-badge">✓ Face Verified</div>
    </div>

    <!-- Location status -->
    <div class="status-card" id="statusCard">
      <div class="status-big" id="statusEmoji">📡</div>
      <div class="status-label" id="statusLabel">Acquiring GPS…</div>
      <div class="dist-text" id="distText">Waiting for location…</div>
      <div class="ist-update" id="statusTime"></div>
    </div>

    <!-- Ping countdown + log -->
    <div class="ping-card">
      <div class="ping-hdr">
        <span class="ping-ttl">📡 GPS Pings · every <?= $pingMins ?> min</span>
        <span class="ping-badge" id="pingCount">0 pings sent</span>
      </div>
      <div class="ping-countdown">
        <div class="pcd-label">
          <span>Next ping in</span>
        </div>
        <div class="pcd-bar-wrap">
          <div class="pcd-bar" id="pcdBar"></div>
        </div>
        <div class="pcd-time" id="pcdTime"><?= $pingMins ?>:00</div>
      </div>
      <div class="ping-log" id="pingLog">
        <div class="ping-empty" style="color:var(--muted);font-size:.78rem;text-align:center;padding:.75rem">Tracking starting…</div>
      </div>
    </div>

    <!-- Shift end notice -->
    <div class="shift-end-card" id="shiftEndCard">
      <div class="se-icon">🔒</div>
      <div class="se-title">Shift Ended</div>
      <div class="se-desc">Your shift ended at <strong style="color:var(--text)"><?= $shiftEnd ?></strong>. Tracking has stopped automatically.<br>Check your email for today's attendance report.</div>
    </div>

  </div>

  <?php endif; ?>

</div><!-- /.main -->

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
// ── Runtime config ───────────────────────────────────────────
const MODEL_URL      = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
const PING_INTERVAL  = <?= $pingMins * 60 ?>;      // seconds
const OFFICE_LAT     = <?= json_encode((float)OFFICE_LAT) ?>;
const OFFICE_LNG     = <?= json_encode((float)OFFICE_LNG) ?>;
const OFFICE_R       = <?= json_encode((int)OFFICE_RADIUS_M) ?>;
const SHIFT_END_TIME = <?= json_encode($shiftEnd) ?>;
const SHIFT_START_TIME = <?= json_encode($shiftStart) ?>;

// ── State ────────────────────────────────────────────────────
let currentEmpId   = null;
let currentEmpName = null;
let currentEmpDept = null;
let empDescriptors = null;
let camStream      = null;
let verifyLoop     = null;
let countdownTimer = null;
let pingSeconds    = PING_INTERVAL;
let pingTotal      = 0;
let trackingActive = false;
let locWatchId     = null;
let lastLat        = null;
let lastLng        = null;
let lastInOffice   = null;
let modelsLoaded   = false;

// ── IST Clock ─────────────────────────────────────────────────
function tickClock() {
  document.getElementById('clock').textContent =
    new Date().toLocaleTimeString('en-IN', {
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      timeZone: 'Asia/Kolkata'
    }) + ' IST';
}
setInterval(tickClock, 1000);
tickClock();

// ── Shift boundary monitor ────────────────────────────────────
function checkShiftBoundary() {
  const hhmm = new Date().toLocaleTimeString('en-IN', {
    hour: '2-digit', minute: '2-digit', hour12: false,
    timeZone: 'Asia/Kolkata'
  }).substring(0, 5);

  // Auto-reload to show locked screen when shift ends
  if (hhmm === SHIFT_END_TIME || hhmm === SHIFT_START_TIME) {
    setTimeout(() => location.reload(), 1500);
  }

  // Stop tracking if shift ended
  if (trackingActive && hhmm >= SHIFT_END_TIME) {
    stopTracking();
  }
}
setInterval(checkShiftBoundary, 30000);

<?php if ($shiftActive): ?>
// ════════════════════════════════════════════════════════════
// STEP 1: EMPLOYEE ID LOOKUP
// ════════════════════════════════════════════════════════════
async function lookupEmployee() {
  const raw   = document.getElementById('empIdInput').value.trim().toUpperCase();
  const btn   = document.getElementById('lookupBtn');
  const errEl = document.getElementById('errMsg');
  const found = document.getElementById('empFoundCard');

  if (!raw) { showErr('Please enter your Employee ID.'); return; }

  btn.disabled    = true;
  btn.innerHTML   = '<span class="spin"></span> Looking up…';
  errEl.style.display = 'none';
  found.style.display = 'none';

  try {
    const res = await fetch(`api.php?action=employee_descriptors&emp_id=${encodeURIComponent(raw)}`);
    const data = await res.json();

    if (!data.found) {
      showErr('Employee ID not found. Please check and try again.');
      btn.disabled  = false;
      btn.textContent = 'Look Up Employee →';
      return;
    }

    // Store globally
    currentEmpId    = data.id;
    currentEmpName  = data.name;
    currentEmpDept  = data.department;
    empDescriptors  = data.descriptors;

    // Show found card
    document.getElementById('empAv').textContent        = data.name.substring(0, 2).toUpperCase();
    document.getElementById('empFoundName').textContent = data.name;
    document.getElementById('empFoundDept').textContent = data.department;
    document.getElementById('empFoundId').textContent   = data.id;
    found.style.display = 'flex';

    // Short delay then go to face verify
    setTimeout(() => goToFaceVerify(), 700);

  } catch(e) {
    showErr('Network error. Please try again.');
    btn.disabled    = false;
    btn.textContent = 'Look Up Employee →';
  }
}

function showErr(msg) {
  const el = document.getElementById('errMsg');
  el.textContent   = msg;
  el.style.display = 'block';
}

// ════════════════════════════════════════════════════════════
// STEP 2: FACE VERIFICATION
// ════════════════════════════════════════════════════════════
async function goToFaceVerify() {
  document.getElementById('step1').style.display    = 'none';
  document.getElementById('step2').style.display    = 'flex';
  document.getElementById('verifyName').textContent = currentEmpName;

  setVerifyStatus('Loading AI face recognition models…');

  try {
    if (!modelsLoaded) {
      await Promise.all([
        faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
      ]);
      modelsLoaded = true;
    }
    setVerifyStatus('Models ready · Starting camera…');
    await startVerifyCam();
  } catch(e) {
    setVerifyStatus('⚠️ Failed to load AI models. Check internet connection.');
  }
}

async function startVerifyCam() {
  try {
    camStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
    });
    const vid = document.getElementById('vVideo');
    vid.srcObject = camStream;
    vid.onloadedmetadata = () => {
      setVerifyStatus(`Look at the camera, ${currentEmpName}…`);
      startVerifyLoop();
    };
  } catch(e) {
    setVerifyStatus('⚠️ Camera access denied — please allow camera.');
  }
}

function startVerifyLoop() {
  const video  = document.getElementById('vVideo');
  const canvas = document.getElementById('vCanvas');
  const ctx    = canvas.getContext('2d');

  // Build a matcher for ONLY this employee
  const labeled = [new faceapi.LabeledFaceDescriptors(
    currentEmpId + '|' + currentEmpName,
    empDescriptors.map(d => new Float32Array(d))
  )];
  const matcher = new faceapi.FaceMatcher(labeled, 0.5);

  let attempts   = 0;
  let confirmed  = false;

  verifyLoop = setInterval(async () => {
    if (confirmed || video.paused || video.readyState < 2) return;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const det = await faceapi
      .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!det) {
      attempts++;
      setVerifyStatus(attempts > 5
        ? '⚠️ No face detected — look directly at the camera'
        : `Scanning… ${currentEmpName}, position your face`);
      return;
    }

    attempts = 0;
    const result  = matcher.findBestMatch(det.descriptor);
    const conf    = 1 - result.distance;
    const confPct = Math.round(conf * 100);
    const box     = det.detection.box;
    const mx      = canvas.width - box.x - box.width; // mirrored

    const isMatch = result.label !== 'unknown' && conf >= 0.55;

    // Draw face box
    ctx.strokeStyle = isMatch ? '#00ff88' : '#ff4757';
    ctx.lineWidth   = 2.5;
    ctx.strokeRect(mx, box.y, box.width, box.height);

    // Confidence label
    ctx.fillStyle = ctx.strokeStyle;
    ctx.font      = 'bold 12px Inter,sans-serif';
    ctx.fillText(`${confPct}%`, mx + 4, box.y - 5);

    // Animate corners
    ['vtl', 'vtr', 'vbl', 'vbr'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.className = 'vc ' + id.substring(1) + (isMatch ? ' ok' : '');
    });

    if (isMatch) {
      confirmed = true;
      setVerifyStatus(`✅ Identity confirmed! (${confPct}% match)`);
      clearInterval(verifyLoop);
      verifyLoop = null;
      setTimeout(() => {
        stopVerifyCam();
        activateTracking(conf);
      }, 900);
    } else {
      setVerifyStatus(conf > 0.3
        ? `Recognizing… ${confPct}% — hold still`
        : `Face not matching ${currentEmpId} — are you ${currentEmpName}?`);
    }
  }, 700);
}

function stopVerifyCam() {
  if (verifyLoop) { clearInterval(verifyLoop); verifyLoop = null; }
  if (camStream)  { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
}

function setVerifyStatus(msg) {
  document.getElementById('verifyStatus').textContent = msg;
}

function goBack() {
  stopVerifyCam();
  currentEmpId = currentEmpName = currentEmpDept = empDescriptors = null;
  document.getElementById('step2').style.display        = 'none';
  document.getElementById('step1').style.display        = 'flex';
  document.getElementById('empIdInput').value           = '';
  document.getElementById('empFoundCard').style.display = 'none';
  document.getElementById('errMsg').style.display       = 'none';
  document.getElementById('lookupBtn').disabled         = false;
  document.getElementById('lookupBtn').textContent      = 'Look Up Employee →';
}

// ════════════════════════════════════════════════════════════
// STEP 3: ACTIVATE TRACKING
// ════════════════════════════════════════════════════════════
async function activateTracking(faceConf) {
  // Validate check-in on server
  try {
    const res = await fetch('api.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify({ action: 'start_tracker', emp_id: currentEmpId })
    }).then(r => r.json());

    if (!res.success) {
      document.getElementById('step2').style.display    = 'none';
      document.getElementById('step1').style.display    = 'flex';
      document.getElementById('errMsg').textContent     = res.msg || 'Please mark attendance at the kiosk first.';
      document.getElementById('errMsg').style.display   = 'block';
      document.getElementById('lookupBtn').disabled     = false;
      document.getElementById('lookupBtn').textContent  = 'Look Up Employee →';
      return;
    }
  } catch(e) {
    // Network error: proceed anyway, pings will fail gracefully
  }

  // Switch UI to step 3
  document.getElementById('step2').style.display   = 'none';
  document.getElementById('step3').style.display   = 'flex';

  document.getElementById('trackAv').textContent   = currentEmpName.substring(0, 2).toUpperCase();
  document.getElementById('trackName').textContent = currentEmpName;
  document.getElementById('trackDept').textContent = currentEmpDept || '';
  document.getElementById('trackId').textContent   = currentEmpId;

  trackingActive = true;

  // Start continuous GPS watch for live status card
  startGPSWatch();

  // Send first ping immediately, then cycle
  await sendPing();
  startPingCycle();
}

// ════════════════════════════════════════════════════════════
// GPS WATCH — continuous updates for status card display only
// ════════════════════════════════════════════════════════════
function startGPSWatch() {
  if (!navigator.geolocation) {
    updateStatusCard(null, null, false, true);
    return;
  }

  locWatchId = navigator.geolocation.watchPosition(
    pos => {
      lastLat = pos.coords.latitude;
      lastLng = pos.coords.longitude;
      const dist     = haversine(lastLat, lastLng, OFFICE_LAT, OFFICE_LNG);
      lastInOffice   = dist <= OFFICE_R;
      updateStatusCard(dist, lastInOffice, true, false);
    },
    err => {
      updateStatusCard(null, null, false, true);
    },
    { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 }
  );
}

function updateStatusCard(dist, inOffice, gpsOk, gpsErr) {
  const card    = document.getElementById('statusCard');
  const emoji   = document.getElementById('statusEmoji');
  const label   = document.getElementById('statusLabel');
  const distTxt = document.getElementById('distText');
  const timeTxt = document.getElementById('statusTime');

  const ist = new Date().toLocaleTimeString('en-IN', {
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Kolkata'
  }) + ' IST';
  timeTxt.textContent = 'Updated at ' + ist;

  if (gpsErr || !gpsOk) {
    card.className      = 'status-card gps-err';
    emoji.textContent   = '⚠️';
    label.textContent   = 'GPS Unavailable';
    distTxt.textContent = 'Enable location access to continue tracking';
    return;
  }

  if (inOffice) {
    card.className      = 'status-card in-off';
    emoji.textContent   = '🏢';
    label.textContent   = 'In Office';
    distTxt.textContent = `${Math.round(dist)}m from office centre · Within ${OFFICE_R}m radius`;
  } else {
    card.className      = 'status-card outside';
    emoji.textContent   = '🚗';
    label.textContent   = 'Outside Office';
    distTxt.textContent = `${Math.round(dist)}m from office · Beyond ${OFFICE_R}m geofence`;
  }
}

// ════════════════════════════════════════════════════════════
// PING CYCLE — server data send every PING_INTERVAL seconds
// ════════════════════════════════════════════════════════════
function startPingCycle() {
  pingSeconds = PING_INTERVAL;
  updateCountdownBar();

  countdownTimer = setInterval(() => {
    if (!trackingActive) return;
    pingSeconds--;
    updateCountdownBar();
    if (pingSeconds <= 0) {
      pingSeconds = PING_INTERVAL;
      sendPing();
    }
  }, 1000);
}

function updateCountdownBar() {
  const pct  = (pingSeconds / PING_INTERVAL) * 100;
  const mins = Math.floor(pingSeconds / 60);
  const secs = pingSeconds % 60;
  document.getElementById('pcdBar').style.width  = pct + '%';
  document.getElementById('pcdTime').textContent =
    mins.toString().padStart(2, '0') + ':' + secs.toString().padStart(2, '0');
}

async function sendPing() {
  if (!trackingActive) return;

  // Fresh GPS position for ping
  const pos  = await getPosition();
  const lat  = pos?.lat  ?? lastLat;
  const lng  = pos?.lng  ?? lastLng;
  const acc  = pos?.acc  ?? 0;
  const ist  = new Date().toLocaleTimeString('en-IN', {
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Kolkata'
  }) + ' IST';

  if (!lat || !lng) {
    addPingRow(false, '⚠️ GPS unavailable', null, ist);
    return;
  }

  const dist     = haversine(lat, lng, OFFICE_LAT, OFFICE_LNG);
  const inOffice = dist <= OFFICE_R;

  try {
    const res = await fetch('api.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify({
        action  : 'tracker_ping',
        emp_id  : currentEmpId,
        lat,
        lng,
        accuracy: acc
      })
    }).then(r => r.json());

    pingTotal++;
    document.getElementById('pingCount').textContent =
      pingTotal + ' ping' + (pingTotal !== 1 ? 's' : '') + ' sent';

    const serverIn = res.in_office ?? inOffice;
    addPingRow(true,
      (serverIn ? '🏢 In office' : '🚗 Outside office') + ` · ${Math.round(dist)}m`,
      null, ist, serverIn);

    // Update status card from server truth
    updateStatusCard(dist, serverIn, true, false);

    // Shift ended on server side
    if (res.shift_active === false) stopTracking();

  } catch(e) {
    addPingRow(false, '⚠️ Network error — will retry next cycle', null, ist);
  }
}

// Promisified single GPS read
function getPosition() {
  return new Promise(resolve => {
    if (!navigator.geolocation) { resolve(null); return; }
    navigator.geolocation.getCurrentPosition(
      p  => resolve({ lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  });
}

// Add row to on-screen log
function addPingRow(success, detail, dist, ist, inOffice) {
  const log   = document.getElementById('pingLog');
  const empty = log.querySelector('.ping-empty');
  if (empty) empty.remove();

  const color = !success ? 'var(--red)' : (inOffice ? 'var(--green)' : 'var(--yellow)');

  const row = document.createElement('div');
  row.className = 'ping-row';
  row.innerHTML = `
    <div class="ping-dot" style="background:${color}"></div>
    <div class="ping-time-col">${ist || '--:--'}</div>
    <div class="ping-detail-col">${detail}</div>`;

  log.insertBefore(row, log.firstChild);

  // Cap at 25 entries
  while (log.children.length > 25) log.removeChild(log.lastChild);
}

// ════════════════════════════════════════════════════════════
// STOP TRACKING (shift end)
// ════════════════════════════════════════════════════════════
function stopTracking() {
  trackingActive = false;

  if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
  if (locWatchId)     { navigator.geolocation.clearWatch(locWatchId); locWatchId = null; }

  // Update UI
  const card = document.getElementById('statusCard');
  card.className = 'status-card stopped';
  document.getElementById('statusEmoji').textContent = '🔒';
  document.getElementById('statusLabel').textContent = 'Tracking Stopped';
  document.getElementById('distText').textContent    = 'Shift ended — GPS tracking disabled';

  document.getElementById('shiftEndCard').style.display = 'block';
  document.getElementById('pcdBar').style.width         = '0%';
  document.getElementById('pcdBar').style.background    = 'var(--muted)';
  document.getElementById('pcdTime').textContent        = '00:00';

  const ist = new Date().toLocaleTimeString('en-IN', {
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Kolkata'
  }) + ' IST';
  addPingRow(false, '🔒 Shift ended · Tracking stopped automatically', null, ist, false);
}

// ── Haversine distance ────────────────────────────────────────
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
<?php endif; ?>
</script>
</body>
</html>
