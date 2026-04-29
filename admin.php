<?php
require 'config.php';
requireAdmin();

// ── Load settings from JSON ───────────────────────────────────
$settings = array_merge([
    'office_name'     => OFFICE_NAME,
    'shift_start'     => SHIFT_START,
    'shift_end'       => SHIFT_END,
    'office_radius_m' => OFFICE_RADIUS_M,
], loadJSON('settings.json'));

$officeName  = $settings['office_name'];
$shiftStart  = $settings['shift_start'];
$shiftEnd    = $settings['shift_end'];
$shiftActive = (date('H:i') >= $shiftStart && date('H:i') <= $shiftEnd);

// ── Core data ─────────────────────────────────────────────────
$employees  = loadJSON('employees.json');
$attendance = loadJSON('attendance.json');
$activityLog= loadJSON('activity_log.json');

$active    = array_filter($employees, fn($e) => $e['active'] ?? true);
$todayAtt  = $attendance[today()] ?? [];
$total     = count($active);
$present   = count($todayAtt);
$absent    = $total - $present;
$inOffice  = count(array_filter($todayAtt, fn($a) => $a['in_office'] ?? false));

// ── Build table rows ──────────────────────────────────────────
$rows = [];
foreach ($active as $id => $emp) {
    $att     = $todayAtt[$id] ?? null;
    $outMins = 0;
    if ($att) {
        foreach ($att['leave_events'] ?? [] as $ev) {
            if ($ev['left_at'] && $ev['returned_at'])
                $outMins += (strtotime($ev['returned_at']) - strtotime($ev['left_at'])) / 60;
            elseif ($ev['left_at'] && !$ev['returned_at'])
                $outMins += (time() - strtotime(today().' '.$ev['left_at'])) / 60;
        }
    }
    $rows[] = [
        'id'          => $id,
        'name'        => $emp['name'],
        'email'       => $emp['email'] ?? '',
        'department'  => $emp['department'],
        'check_in'    => $att['check_in'] ?? null,
        'status'      => $att ? 'present' : 'absent',
        'in_office'   => $att['in_office'] ?? false,
        'leave_count' => count($att['leave_events'] ?? []),
        'out_mins'    => round($outMins),
        'confidence'  => $att['confidence'] ?? null,
        'last_seen'   => $att['last_seen'] ?? null,
    ];
}

// Sort: present first, then alpha
usort($rows, fn($a,$b) =>
    (($b['status'] === 'present') <=> ($a['status'] === 'present'))
    ?: strcmp($a['name'], $b['name'])
);
// ── Stats for mini charts ─────────────────────────────────────
$weekStats = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $count = count($attendance[$d] ?? []);
    $weekStats[] = ['date' => date('D', strtotime($d)), 'count' => $count, 'full' => $d];
}

$deptBreakdown = [];
foreach ($active as $id => $emp) {
    $dept = $emp['department'];
    if (!isset($deptBreakdown[$dept])) $deptBreakdown[$dept] = ['total'=>0,'present'=>0];
    $deptBreakdown[$dept]['total']++;
    if (isset($todayAtt[$id])) $deptBreakdown[$dept]['present']++;
}

$recentLogs = array_reverse(array_slice($activityLog, -30));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — FaceShift </title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --teal:#00d4e8;--green:#00ff88;--red:#ff4757;--yellow:#ffd700;--blue:#4f9cf9;--purple:#a78bfa;
  --bg:#080a10;--s1:#0e1220;--s2:#131826;--s3:#171d2e;
  --border:#1c2235;--border2:#222a3e;
  --text:#e2e8f8;--muted:#6b7a99;--faint:#141928;
  --radius:12px
}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;display:flex;min-height:100vh;overflow:hidden}

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.sb{
  width:228px;background:var(--s1);border-right:1px solid var(--border);
  padding:0;flex-shrink:0;height:100vh;overflow-y:auto;
  display:flex;flex-direction:column;position:sticky;top:0;
  transition:width .2s
}
.sb-head{padding:1.1rem 1rem .85rem;border-bottom:1px solid var(--border)}
.sb-logo{display:flex;align-items:center;gap:.65rem}
.sb-icon{
  width:34px;height:34px;
  background:linear-gradient(135deg,var(--teal),#0077b6);
  border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0
}
.sb-name{font-weight:700;color:#fff;font-size:.95rem;line-height:1.1}
.sb-office{color:var(--muted);font-size:.67rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}

.sb-body{flex:1;padding:.75rem .7rem;overflow-y:auto}
.sb-section{
  color:var(--muted);font-size:.63rem;text-transform:uppercase;
  letter-spacing:.7px;font-weight:600;padding:.1rem .4rem;
  margin:1rem 0 .3rem
}
.sb-section:first-child{margin-top:.2rem}
.nav-a{
  display:flex;align-items:center;gap:.6rem;
  padding:.5rem .65rem;border-radius:9px;
  color:var(--muted);text-decoration:none;
  font-size:.82rem;margin-bottom:.1rem;
  transition:all .16s;position:relative
}
.nav-a:hover{background:var(--s2);color:var(--text)}
.nav-a.active{background:var(--s2);color:var(--teal)}
.nav-a.active::before{
  content:'';position:absolute;left:0;top:25%;bottom:25%;
  width:3px;background:var(--teal);border-radius:0 3px 3px 0
}
.nav-a svg{flex-shrink:0;width:15px;height:15px;opacity:.8}
.nav-a.active svg{opacity:1}
.nav-badge{
  margin-left:auto;font-size:.65rem;font-weight:700;
  padding:.1rem .45rem;border-radius:20px;font-variant-numeric:tabular-nums
}
.nb-green{background:rgba(0,255,136,.12);color:var(--green)}
.nb-red{background:rgba(255,71,87,.12);color:var(--red)}
.nb-yellow{background:rgba(255,215,0,.12);color:var(--yellow)}
.nb-muted{background:var(--faint);color:var(--muted)}
.nb-teal{background:rgba(0,212,232,.12);color:var(--teal)}

.sb-div{border:none;border-top:1px solid var(--border);margin:.4rem 0}

.sb-foot{padding:.75rem .7rem;border-top:1px solid var(--border)}
.shift-pill{
  display:flex;align-items:center;gap:.45rem;
  padding:.45rem .7rem;border-radius:9px;
  font-size:.76rem;font-weight:600;margin-bottom:.4rem
}
.sp-on{background:rgba(0,255,136,.07);color:var(--green);border:1px solid rgba(0,255,136,.15)}
.sp-off{background:rgba(255,71,87,.07);color:var(--red);border:1px solid rgba(255,71,87,.15)}
.sp-dot{width:7px;height:7px;border-radius:50%;background:currentColor}
.pulse{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.sp-time{margin-left:auto;font-size:.67rem;opacity:.7}
.admin-info{
  display:flex;align-items:center;gap:.55rem;
  padding:.45rem .65rem;border-radius:9px;background:var(--s2)
}
.admin-av{
  width:28px;height:28px;border-radius:50%;
  background:linear-gradient(135deg,#a78bfa,#6366f1);
  display:flex;align-items:center;justify-content:center;
  font-size:.68rem;font-weight:700;color:#fff;flex-shrink:0
}
.admin-nm{font-size:.78rem;font-weight:600;color:var(--text)}
.admin-role{font-size:.66rem;color:var(--muted)}

/* ═══════════════════════════════════════════
   MAIN
═══════════════════════════════════════════ */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.topbar{
  background:var(--s1);border-bottom:1px solid var(--border);
  padding:.7rem 1.5rem;display:flex;align-items:center;
  justify-content:space-between;flex-shrink:0;gap:.75rem;flex-wrap:wrap
}
.tb-left{display:flex;flex-direction:column;gap:.1rem}
.page-title{font-size:1.1rem;font-weight:700;color:#fff}
.page-sub{font-size:.76rem;color:var(--muted)}
.tb-right{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.clock-badge{
  font-variant-numeric:tabular-nums;color:var(--muted);
  font-size:.8rem;font-weight:500;padding:.3rem .65rem;
  background:var(--s2);border:1px solid var(--border);border-radius:8px
}
.btn{
  border:none;cursor:pointer;font-family:'Inter',sans-serif;
  font-weight:600;transition:all .16s;display:inline-flex;
  align-items:center;gap:.4rem;border-radius:8px;
  font-size:.8rem;white-space:nowrap
}
.btn-primary{background:linear-gradient(135deg,var(--teal),#0077b6);color:#fff;padding:.45rem 1rem}
.btn-primary:hover{opacity:.88}
.btn-ghost{background:var(--s2);color:var(--muted);border:1px solid var(--border);padding:.4rem .85rem}
.btn-ghost:hover{color:var(--text);border-color:var(--border2)}
.btn-ghost:disabled{opacity:.45;cursor:not-allowed}
.btn-danger{background:rgba(255,71,87,.12);color:var(--red);border:1px solid rgba(255,71,87,.2);padding:.4rem .85rem}
.btn-danger:hover{background:rgba(255,71,87,.22)}

/* scrollable content area */
.content{flex:1;overflow-y:auto;padding:1.25rem 1.5rem}

/* ── KPI grid ─────────────────────────────────────────── */
.kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.85rem;margin-bottom:1.25rem}
@media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:800px){.kpi-grid{grid-template-columns:1fr 1fr}}
.kpi{background:var(--s1);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;position:relative;overflow:hidden}
.kpi-icon{position:absolute;right:.85rem;top:.85rem;font-size:1.5rem;opacity:.18}
.kpi-lbl{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;margin-bottom:.35rem}
.kpi-val{font-size:2rem;font-weight:700;color:#fff;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:.2rem}
.kpi-sub{font-size:.7rem;color:var(--muted);display:flex;align-items:center;gap:.3rem}
.kpi-trend{font-size:.68rem;font-weight:600;padding:.1rem .4rem;border-radius:20px}
.kt-up{background:rgba(0,255,136,.1);color:var(--green)}
.kt-dn{background:rgba(255,71,87,.1);color:var(--red)}
.kv-teal{color:var(--teal)}.kv-green{color:var(--green)}.kv-red{color:var(--red)}.kv-yellow{color:var(--yellow)}.kv-purple{color:var(--purple)}

/* ── Main content row ─────────────────────────────────── */
.content-row{display:grid;grid-template-columns:1fr 300px;gap:1.1rem;margin-bottom:1.1rem}
@media(max-width:1100px){.content-row{grid-template-columns:1fr}}

/* ── Cards ────────────────────────────────────────────── */
.card{background:var(--s1);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-hdr{
  padding:.75rem 1.1rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap
}
.card-title{font-size:.85rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.45rem}
.card-sub{font-size:.72rem;color:var(--muted)}

/* ── Bottom row ────────────────────────────────────────── */
.bottom-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.1rem}
@media(max-width:1200px){.bottom-row{grid-template-columns:1fr 1fr}}
@media(max-width:800px){.bottom-row{grid-template-columns:1fr}}

/* ── Attendance table ─────────────────────────────────── */
.att-table{width:100%;border-collapse:collapse}
.att-table thead th{
  padding:.5rem .85rem;text-align:left;
  font-size:.68rem;font-weight:600;color:var(--muted);
  text-transform:uppercase;letter-spacing:.4px;
  border-bottom:1px solid var(--border);white-space:nowrap
}
.att-table tbody td{
  padding:.55rem .85rem;border-bottom:1px solid rgba(28,34,53,.5);
  font-size:.81rem;font-variant-numeric:tabular-nums;vertical-align:middle
}
.att-table tbody tr:last-child td{border:none}
.att-table tbody tr{transition:background .15s}
.att-table tbody tr:hover td{background:rgba(19,24,38,.7)}
.emp-cell{display:flex;align-items:center;gap:.55rem}
.emp-av{
  width:28px;height:28px;border-radius:50%;
  background:linear-gradient(135deg,var(--teal),#0077b6);
  display:flex;align-items:center;justify-content:center;
  font-size:.58rem;font-weight:700;color:#fff;flex-shrink:0
}
.emp-av.absent{background:var(--s3)}
.emp-nm{font-weight:500;font-size:.81rem;white-space:nowrap}
.emp-id{font-size:.68rem;color:var(--muted)}
.badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.67rem;font-weight:600;padding:.18rem .5rem;border-radius:20px;white-space:nowrap}
.b-present{background:rgba(0,255,136,.09);color:var(--green);border:1px solid rgba(0,255,136,.18)}
.b-absent{background:rgba(107,122,153,.09);color:var(--muted);border:1px solid rgba(107,122,153,.18)}
.b-in{background:rgba(0,212,232,.09);color:var(--teal);border:1px solid rgba(0,212,232,.18)}
.b-out{background:rgba(255,215,0,.09);color:var(--yellow);border:1px solid rgba(255,215,0,.18)}
.b-leaves{background:rgba(255,159,67,.09);color:#ff9f43;border:1px solid rgba(255,159,67,.18)}
.search-bar{
  display:flex;align-items:center;gap:.5rem;background:var(--s2);
  border:1px solid var(--border);border-radius:8px;
  padding:.35rem .7rem;transition:border .2s
}
.search-bar:focus-within{border-color:var(--teal)}
.search-bar svg{color:var(--muted);flex-shrink:0}
.search-bar input{background:none;border:none;outline:none;color:var(--text);font-size:.8rem;font-family:'Inter',sans-serif;width:160px}
.search-bar input::placeholder{color:var(--muted)}
.filter-tabs{display:flex;gap:.3rem}
.ftab{background:var(--s2);border:1px solid var(--border);color:var(--muted);padding:.28rem .65rem;border-radius:7px;font-size:.72rem;font-weight:600;cursor:pointer;transition:all .16s;font-family:'Inter',sans-serif}
.ftab:hover{color:var(--text)}
.ftab.active{background:var(--s3);border-color:var(--border2);color:var(--teal)}

/* ── Activity log ─────────────────────────────────────── */
.log-scroll{max-height:400px;overflow-y:auto}
.log-item{display:flex;align-items:flex-start;gap:.6rem;padding:.6rem 1rem;border-bottom:1px solid rgba(28,34,53,.5);transition:background .15s}
.log-item:last-child{border:none}
.log-item:hover{background:rgba(19,24,38,.5)}
.log-ev-icon{
  width:26px;height:26px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;flex-shrink:0;margin-top:.1rem
}
.ev-check_in{background:rgba(0,255,136,.1)}
.ev-left_office{background:rgba(255,215,0,.1)}
.ev-returned{background:rgba(0,212,232,.1)}
.ev-registered{background:rgba(167,139,250,.1)}
.ev-report_sent{background:rgba(79,156,249,.1)}
.ev-default{background:var(--s2)}
.log-body{flex:1;min-width:0}
.log-name{font-size:.8rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.log-detail{font-size:.72rem;color:var(--muted);margin-top:.1rem;line-height:1.4}
.log-time{font-size:.7rem;color:var(--muted);flex-shrink:0;font-variant-numeric:tabular-nums}

/* ── Mini bar chart (7-day) ───────────────────────────── */
.bar-chart{display:flex;align-items:flex-end;gap:.4rem;padding:.85rem 1rem;height:100px}
.bar-col{display:flex;flex-direction:column;align-items:center;gap:.3rem;flex:1}
.bar-track{flex:1;width:100%;background:var(--faint);border-radius:4px 4px 0 0;position:relative;min-height:4px;overflow:hidden}
.bar-fill{
  position:absolute;bottom:0;left:0;right:0;
  background:linear-gradient(to top,var(--teal),rgba(0,212,232,.4));
  border-radius:4px 4px 0 0;transition:height .5s ease
}
.bar-fill.today{background:linear-gradient(to top,var(--green),rgba(0,255,136,.4))}
.bar-lbl{font-size:.62rem;color:var(--muted);font-weight:500}
.bar-val{font-size:.65rem;color:var(--muted);font-variant-numeric:tabular-nums}

/* ── Dept breakdown ───────────────────────────────────── */
.dept-row{display:flex;align-items:center;gap:.65rem;padding:.55rem 1rem;border-bottom:1px solid rgba(28,34,53,.5)}
.dept-row:last-child{border:none}
.dept-name{flex:1;font-size:.8rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dept-track{flex:2;height:5px;background:var(--faint);border-radius:20px;overflow:hidden}
.dept-fill{height:100%;background:linear-gradient(90deg,var(--teal),var(--blue));border-radius:20px;transition:width .6s ease}
.dept-frac{font-size:.73rem;color:var(--muted);font-variant-numeric:tabular-nums;flex-shrink:0;width:36px;text-align:right}

/* ── Quick actions ────────────────────────────────────── */
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;padding:.85rem 1rem}
.qa-btn{
  display:flex;flex-direction:column;align-items:center;gap:.4rem;
  padding:.85rem .5rem;border-radius:10px;background:var(--s2);
  border:1px solid var(--border);cursor:pointer;
  font-family:'Inter',sans-serif;color:var(--muted);
  font-size:.75rem;font-weight:600;text-decoration:none;
  transition:all .18s;text-align:center
}
.qa-btn:hover{border-color:var(--teal);color:var(--teal);background:rgba(0,212,232,.04)}
.qa-btn svg{width:20px;height:20px;opacity:.7;transition:opacity .18s}
.qa-btn:hover svg{opacity:1}
.qa-btn.danger:hover{border-color:var(--red);color:var(--red);background:rgba(255,71,87,.04)}

/* ── Employee detail drawer ───────────────────────────── */
.drawer-bg{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;display:none;align-items:flex-start;justify-content:flex-end}
.drawer-bg.open{display:flex}
.drawer{
  width:360px;max-width:95vw;height:100vh;
  background:var(--s1);border-left:1px solid var(--border);
  overflow-y:auto;display:flex;flex-direction:column;
  animation:drawerIn .22s ease
}
@keyframes drawerIn{from{transform:translateX(30px);opacity:.6}to{transform:none;opacity:1}}
.drawer-hdr{padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.drawer-title{font-weight:700;color:#fff;font-size:.95rem}
.drawer-close{background:none;border:none;color:var(--muted);font-size:1.4rem;cursor:pointer;transition:color .2s;line-height:1}
.drawer-close:hover{color:#fff}
.drawer-body{padding:1.25rem;flex:1}
.d-av{
  width:56px;height:56px;border-radius:50%;
  background:linear-gradient(135deg,var(--teal),#0077b6);
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;font-weight:700;color:#fff;margin:0 auto 1rem
}
.d-name{text-align:center;font-weight:700;font-size:1.05rem;color:#fff;margin-bottom:.2rem}
.d-sub{text-align:center;font-size:.78rem;color:var(--muted);margin-bottom:1.25rem}
.d-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1.25rem}
.d-stat{background:var(--s2);border:1px solid var(--border);border-radius:10px;padding:.75rem}
.d-stat-val{font-size:1.4rem;font-weight:700;color:#fff;font-variant-numeric:tabular-nums;margin-bottom:.15rem}
.d-stat-lbl{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.d-section{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:.1rem 0 .5rem}
.d-ev-list{display:flex;flex-direction:column;gap:.35rem}
.d-ev{display:flex;align-items:center;gap:.5rem;font-size:.78rem;padding:.4rem .6rem;background:var(--s2);border-radius:8px}
.d-ev-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.dot-green{background:var(--green)}.dot-yellow{background:var(--yellow)}.dot-red{background:var(--red)}.dot-teal{background:var(--teal)}

/* ── Toast ────────────────────────────────────────────── */
.toast-wrap{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.4rem;z-index:400;pointer-events:none}
.toast{background:var(--s1);border:1px solid var(--border);border-radius:10px;padding:.65rem 1rem;font-size:.8rem;display:flex;align-items:center;gap:.6rem;pointer-events:all;animation:toastIn .2s ease;max-width:280px;box-shadow:0 4px 20px rgba(0,0,0,.4)}
@keyframes toastIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.toast.success{border-color:rgba(0,255,136,.2);color:var(--green)}
.toast.error{border-color:rgba(255,71,87,.2);color:var(--red)}
.toast.info{border-color:rgba(0,212,232,.2);color:var(--teal)}

/* ── Misc ─────────────────────────────────────────────── */
.spin{animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.empty-state{text-align:center;padding:2rem;color:var(--muted);font-size:.82rem}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--faint);border-radius:2px}
.separator{height:1px;background:var(--border);margin:.35rem 0}
</style>
</head>
<body>

<!-- ════════════════════════════════
     SIDEBAR
════════════════════════════════ -->
<div class="sb">
  <div class="sb-head">
    <div class="sb-logo">
      <div class="sb-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2">
          <circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/>
        </svg>
      </div>
      <div>
        <div class="sb-name">FaceShift </div>
        <div class="sb-office"><?= $officeName ?></div>
      </div>
    </div>
  </div>

  <div class="sb-body">

    <div class="sb-section">Overview</div>

    <a class="nav-a active" href="admin.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
      </svg>
      Dashboard
      <span class="nav-badge <?= $shiftActive ? 'nb-green' : 'nb-muted' ?>"><?= $shiftActive ? 'Live' : 'Off' ?></span>
    </a>

    <a class="nav-a" href="logs.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
      </svg>
      Activity Logs
      <?php if (count($activityLog) > 0): ?>
      <span class="nav-badge nb-teal"><?= count($activityLog) > 99 ? '99+' : count($activityLog) ?></span>
      <?php endif; ?>
    </a>

    <div class="sb-section">People</div>

    <a class="nav-a" href="register.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/>
        <line x1="16" y1="11" x2="22" y2="11"/>
      </svg>
      Employees
      <span class="nav-badge nb-muted"><?= $total ?></span>
    </a>

    <a class="nav-a" href="register.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      All Employees
      <span class="nav-badge <?= $present > 0 ? 'nb-green' : 'nb-muted' ?>"><?= $present ?>/<?= $total ?></span>
    </a>

    <div class="sb-section">Reports</div>

    <a class="nav-a" href="send_report.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
        <polyline points="22,6 12,13 2,6"/>
      </svg>
      Send Reports
    </a>

    <a class="nav-a" href="send_report.php?export=csv">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Export Attendance
    </a>

    <div class="sb-section">System</div>

    <a class="nav-a" href="settings.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
      </svg>
      Settings
    </a>

    <div class="separator"></div>

    <a class="nav-a" href="attendance.php" target="_blank">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <circle cx="12" cy="10" r="3"/>
        <path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/>
      </svg>
      Face Kiosk
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;margin-left:auto;opacity:.4">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
        <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
      </svg>
    </a>

  </div><!-- /sb-body -->

  <div class="sb-foot">
    <div class="shift-pill <?= $shiftActive ? 'sp-on' : 'sp-off' ?>">
      <div class="sp-dot <?= $shiftActive ? 'pulse' : '' ?>"></div>
      <span><?= $shiftActive ? 'Shift Active' : 'Shift Closed' ?></span>
      <span class="sp-time"><?= $shiftStart ?>–<?= $shiftEnd ?></span>
    </div>
    <div class="admin-info">
      <div class="admin-av">AD</div>
      <div>
        <div class="admin-nm"><?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?></div>
        <div class="admin-role">Administrator</div>
      </div>
      <a href="logout.php" style="margin-left:auto;color:var(--muted);display:flex;transition:color .18s" title="Logout">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</div>

<!-- ════════════════════════════════
     MAIN
════════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="tb-left">
      <div class="page-title">Dashboard</div>
      <div class="page-sub" id="dateSubtitle"></div>
    </div>
    <div class="tb-right">
      <span class="clock-badge" id="clockEl">--:--:--</span>
      <button class="btn btn-ghost" id="refreshBtn" onclick="refreshAll()">
        <svg id="refreshIcon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="23 4 23 10 17 10"/>
          <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        Refresh
      </button>
      <a href="send_report.php" class="btn btn-primary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
        Send Reports
      </a>
    </div>
  </div>

  <!-- Scrollable content -->
  <div class="content">

    <!-- ── KPI Row ─────────────────────────────────────────── -->
    <div class="kpi-grid">
      <div class="kpi">
        <div class="kpi-icon">👥</div>
        <div class="kpi-lbl">Total Employees</div>
        <div class="kpi-val" id="k-total"><?= $total ?></div>
        <div class="kpi-sub"><span class="kpi-trend nb-muted"><?= $total ?> active</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-icon">✅</div>
        <div class="kpi-lbl">Present Today</div>
        <div class="kpi-val kv-green" id="k-present"><?= $present ?></div>
        <div class="kpi-sub">
          <?php $pct = $total > 0 ? round($present/$total*100) : 0; ?>
          <span class="kpi-trend <?= $pct >= 80 ? 'kt-up' : 'kt-dn' ?>"><?= $pct ?>%</span>
          attendance rate
        </div>
      </div>
      <div class="kpi">
        <div class="kpi-icon">❌</div>
        <div class="kpi-lbl">Absent Today</div>
        <div class="kpi-val kv-red" id="k-absent"><?= $absent ?></div>
        <div class="kpi-sub"><span class="kpi-trend <?= $absent === 0 ? 'kt-up' : 'kt-dn' ?>"><?= $absent === 0 ? 'All in!' : $absent.' missing' ?></span></div>
      </div>
      <div class="kpi">
        <div class="kpi-icon">🏢</div>
        <div class="kpi-lbl">In Office Now</div>
        <div class="kpi-val kv-teal" id="k-inoffice"><?= $inOffice ?></div>
        <div class="kpi-sub">of <?= $present ?> present</div>
      </div>
      <div class="kpi">
        <div class="kpi-icon">🚗</div>
        <div class="kpi-lbl">Outside Office</div>
        <div class="kpi-val kv-yellow" id="k-outside"><?= max(0, $present - $inOffice) ?></div>
        <div class="kpi-sub">left premises today</div>
      </div>
    </div>

    <!-- ── Attendance table + Log ──────────────────────────── -->
    <div class="content-row">

      <!-- Attendance Table -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Today's Attendance
          </span>
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <div class="filter-tabs">
              <button class="ftab active" data-filter="all" onclick="setFilter(this,'all')">All</button>
              <button class="ftab" data-filter="present" onclick="setFilter(this,'present')">Present</button>
              <button class="ftab" data-filter="absent" onclick="setFilter(this,'absent')">Absent</button>
              <button class="ftab" data-filter="outside" onclick="setFilter(this,'outside')">Outside</button>
            </div>
            <div class="search-bar">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
              <input id="searchInput" placeholder="Search name or ID…" oninput="filterTable()">
            </div>
            <span class="card-sub" id="tableCount"><?= count($rows) ?> employees</span>
          </div>
        </div>
        <div style="overflow-x:auto;max-height:420px;overflow-y:auto">
          <table class="att-table">
            <thead style="position:sticky;top:0;background:var(--s1);z-index:1">
              <tr>
                <th>Employee</th>
                <th>Dept</th>
                <th>Check-in</th>
                <th>Status</th>
                <th>Location</th>
                <th>Leaves</th>
                <th>Out Time</th>
                <th>Confidence</th>
                <th>Last Seen</th>
              </tr>
            </thead>
            <tbody id="attBody">
              <?php foreach ($rows as $r): ?>
              <tr
                class="att-row"
                data-status="<?= $r['status'] ?>"
                data-location="<?= (!$r['in_office'] && $r['status']==='present') ? 'outside' : 'in' ?>"
                data-name="<?= strtolower($r['name']) ?>"
                data-id="<?= strtolower($r['id']) ?>"
                onclick="openDrawer(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)"
                style="cursor:pointer"
              >
                <td>
                  <div class="emp-cell">
                    <div class="emp-av <?= $r['status']==='absent'?'absent':'' ?>"><?= strtoupper(substr($r['name'],0,2)) ?></div>
                    <div>
                      <div class="emp-nm"><?= htmlspecialchars($r['name']) ?></div>
                      <div class="emp-id"><?= $r['id'] ?></div>
                    </div>
                  </div>
                </td>
                <td style="color:var(--muted);font-size:.78rem"><?= htmlspecialchars($r['department']) ?></td>
                <td style="color:<?= $r['check_in'] ? 'var(--green)' : 'var(--muted)' ?>">
                  <?= $r['check_in'] ?: '—' ?>
                </td>
                <td>
                  <?= $r['status']==='present'
                    ? '<span class="badge b-present">✅ Present</span>'
                    : '<span class="badge b-absent">Absent</span>' ?>
                </td>
                <td>
                  <?php if ($r['status']==='present'): ?>
                    <span class="badge <?= $r['in_office'] ? 'b-in' : 'b-out' ?>">
                      <?= $r['in_office'] ? '🏢 In Office' : '🚗 Outside' ?>
                    </span>
                  <?php else: ?>
                    <span style="color:var(--muted);font-size:.75rem">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($r['status']==='present'): ?>
                    <?php if ($r['leave_count'] > 0): ?>
                      <span class="badge b-leaves"><?= $r['leave_count'] ?>× left</span>
                    <?php else: ?>
                      <span style="color:var(--muted)">0</span>
                    <?php endif; ?>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:.78rem">
                  <?= $r['status']==='present' ? $r['out_mins'].'m' : '—' ?>
                </td>
                <td style="color:var(--muted);font-size:.78rem">
                  <?= $r['confidence'] !== null ? $r['confidence'].'%' : '—' ?>
                </td>
                <td style="color:var(--muted);font-size:.73rem">
                  <?= $r['last_seen'] ? substr($r['last_seen'],11,5) : '—' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Activity Log -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Activity
          </span>
          <span class="card-sub" id="logCountEl"><?= count($recentLogs) ?> events</span>
        </div>
        <div class="log-scroll" id="logList">
          <?php if (empty($recentLogs)): ?>
          <div class="empty-state">No activity yet today</div>
          <?php else: ?>
          <?php foreach ($recentLogs as $log): ?>
          <?php
            $evMap = [
              'check_in'    => ['✅','ev-check_in'],
              'left_office' => ['🚗','ev-left_office'],
              'returned'    => ['🏢','ev-returned'],
              'registered'  => ['👤','ev-registered'],
              'report_sent' => ['📧','ev-report_sent'],
            ];
            [$icon, $cls] = $evMap[$log['event']] ?? ['•','ev-default'];
          ?>
          <div class="log-item">
            <div class="log-ev-icon <?= $cls ?>"><?= $icon ?></div>
            <div class="log-body">
              <div class="log-name"><?= htmlspecialchars($log['name'] ?? $log['emp_id'] ?? '') ?></div>
              <div class="log-detail"><?= htmlspecialchars($log['detail'] ?? $log['event'] ?? '') ?></div>
            </div>
            <div class="log-time"><?= substr($log['time'] ?? '',11,5) ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="padding:.6rem 1rem;border-top:1px solid var(--border);text-align:center">
          <a href="logs.php" style="color:var(--teal);font-size:.75rem;font-weight:600;text-decoration:none">View all logs →</a>
        </div>
      </div>
    </div><!-- /content-row -->

    <!-- ── Bottom row: 7-day chart + Dept breakdown + Quick actions ── -->
    <div class="bottom-row">

      <!-- 7-day attendance chart -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            7-Day Attendance
          </span>
          <span class="card-sub">last 7 days</span>
        </div>
        <?php $maxCount = max(1, max(array_column($weekStats, 'count'))); ?>
        <div class="bar-chart" id="barChart">
          <?php foreach ($weekStats as $i => $ws): ?>
          <div class="bar-col">
            <div class="bar-val"><?= $ws['count'] ?: '' ?></div>
            <div class="bar-track">
              <div class="bar-fill <?= $ws['full'] === today() ? 'today' : '' ?>"
                   style="height:<?= $maxCount > 0 ? round($ws['count']/$maxCount*100) : 0 ?>%">
              </div>
            </div>
            <div class="bar-lbl"><?= $ws['date'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="padding:.5rem 1rem .85rem;display:flex;gap:.85rem">
          <div style="display:flex;align-items:center;gap:.35rem;font-size:.7rem;color:var(--muted)">
            <div style="width:10px;height:10px;background:var(--teal);border-radius:2px"></div> Past days
          </div>
          <div style="display:flex;align-items:center;gap:.35rem;font-size:.7rem;color:var(--muted)">
            <div style="width:10px;height:10px;background:var(--green);border-radius:2px"></div> Today
          </div>
        </div>
      </div>

      <!-- Dept breakdown -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            By Department
          </span>
          <span class="card-sub"><?= count($deptBreakdown) ?> depts</span>
        </div>
        <?php if (empty($deptBreakdown)): ?>
        <div class="empty-state">No department data</div>
        <?php else: ?>
        <?php foreach ($deptBreakdown as $dept => $d):
          $pct = $d['total'] > 0 ? round($d['present']/$d['total']*100) : 0; ?>
        <div class="dept-row">
          <div class="dept-name" title="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></div>
          <div class="dept-track"><div class="dept-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="dept-frac"><?= $d['present'] ?>/<?= $d['total'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Quick actions -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            Quick Actions
          </span>
        </div>
        <div class="qa-grid">
          <a class="qa-btn" href="register.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <line x1="19" y1="8" x2="19" y2="14"/>
              <line x1="16" y1="11" x2="22" y2="11"/>
            </svg>
            Add Employee
          </a>
          <a class="qa-btn" href="attendance.php" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <circle cx="12" cy="10" r="3"/>
              <path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/>
            </svg>
            Open Kiosk
          </a>
          <a class="qa-btn" href="send_report.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="22" y1="2" x2="11" y2="13"/>
              <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Send Reports
          </a>
          <a class="qa-btn" href="logs.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
            </svg>
            View Logs
          </a>
          <a class="qa-btn" href="settings.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"/>
              <path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
            </svg>
            Settings
          </a>
          <a class="qa-btn danger" href="logout.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Logout
          </a>
        </div>
      </div>

    </div><!-- /bottom-row -->
  </div><!-- /content -->
</div><!-- /main -->

<!-- ════════════════════════════════
     EMPLOYEE DRAWER
════════════════════════════════ -->
<div class="drawer-bg" id="drawerBg" onclick="closeDrawer(event)">
  <div class="drawer" id="drawer">
    <div class="drawer-hdr">
      <span class="drawer-title" id="drawerTitle">Employee Detail</span>
      <button class="drawer-close" onclick="closeDrawerDirect()">×</button>
    </div>
    <div class="drawer-body" id="drawerBody">
      <!-- filled by JS -->
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ════════════════════════════════
     JAVASCRIPT
════════════════════════════════ -->
<script>
// ── Clock ────────────────────────────────────────────────────
const shiftStart = <?= json_encode($shiftStart) ?>;
const shiftEnd   = <?= json_encode($shiftEnd) ?>;

function updateClock() {
  const n = new Date();
  document.getElementById('clockEl').textContent =
    n.toLocaleTimeString('en-IN',{hour12:false});
  // Auto-reload at shift transitions
  const hm = n.toTimeString().slice(0,5);
  if (hm === shiftStart || hm === shiftEnd) setTimeout(()=>location.reload(), 1000);
}
setInterval(updateClock, 1000); updateClock();
document.getElementById('dateSubtitle').textContent =
  new Date().toLocaleDateString('en-IN',{weekday:'long',day:'numeric',month:'long',year:'numeric'});

// ── Filter / Search ───────────────────────────────────────────
let activeFilter = 'all';

function setFilter(btn, filter) {
  document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  activeFilter = filter;
  filterTable();
}

function filterTable() {
  const q    = document.getElementById('searchInput').value.toLowerCase().trim();
  const rows = document.querySelectorAll('.att-row');
  let visible = 0;
  rows.forEach(row => {
    const name  = row.dataset.name;
    const id    = row.dataset.id;
    const status= row.dataset.status;
    const loc   = row.dataset.location;
    const matchSearch  = !q || name.includes(q) || id.includes(q);
    const matchFilter  =
      activeFilter === 'all' ||
      (activeFilter === 'present' && status === 'present') ||
      (activeFilter === 'absent'  && status === 'absent')  ||
      (activeFilter === 'outside' && loc === 'outside');
    const show = matchSearch && matchFilter;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('tableCount').textContent = `${visible} shown`;
}

// ── Refresh all data ──────────────────────────────────────────
async function refreshAll() {
  const btn  = document.getElementById('refreshBtn');
  const icon = document.getElementById('refreshIcon');
  icon.classList.add('spin'); btn.disabled = true;

  try {
    const res  = await fetch('api.php?action=today_data');
    const data = await res.json();

    // KPIs
    animNum('k-total',    data.total);
    animNum('k-present',  data.present);
    animNum('k-absent',   data.absent);
    animNum('k-inoffice', data.in_office);
    animNum('k-outside',  Math.max(0, data.present - data.in_office));

    // Table
    const tbody = document.getElementById('attBody');
    tbody.innerHTML = data.rows.map(r => `
      <tr class="att-row"
          data-status="${r.status}"
          data-location="${!r.in_office && r.status==='present' ? 'outside':'in'}"
          data-name="${r.name.toLowerCase()}"
          data-id="${r.id.toLowerCase()}"
          onclick="openDrawer(${JSON.stringify(r).replace(/"/g,'&quot;')})"
          style="cursor:pointer">
        <td><div class="emp-cell">
          <div class="emp-av ${r.status==='absent'?'absent':''}">${r.name.substring(0,2).toUpperCase()}</div>
          <div><div class="emp-nm">${r.name}</div><div class="emp-id">${r.id}</div></div>
        </div></td>
        <td style="color:var(--muted);font-size:.78rem">${r.department}</td>
        <td style="color:${r.check_in?'var(--green)':'var(--muted)'}">${r.check_in||'—'}</td>
        <td>${r.status==='present'
          ? '<span class="badge b-present">✅ Present</span>'
          : '<span class="badge b-absent">Absent</span>'}</td>
        <td>${r.status==='present'
          ? `<span class="badge ${r.in_office?'b-in':'b-out'}">${r.in_office?'🏢 In Office':'🚗 Outside'}</span>`
          : '<span style="color:var(--muted);font-size:.75rem">—</span>'}</td>
        <td>${r.status==='present'
          ? (r.leave_count > 0
             ? `<span class="badge b-leaves">${r.leave_count}× left</span>`
             : '<span style="color:var(--muted)">0</span>')
          : '<span style="color:var(--muted)">—</span>'}</td>
        <td style="color:var(--muted);font-size:.78rem">${r.status==='present'?r.out_mins+'m':'—'}</td>
        <td style="color:var(--muted);font-size:.78rem">${r.confidence!=null?r.confidence+'%':'—'}</td>
        <td style="color:var(--muted);font-size:.73rem">${r.last_seen?r.last_seen.substring(11,16):'—'}</td>
      </tr>`).join('');

    // Log
    if (data.logs) {
      const icons = {check_in:'✅',left_office:'🚗',returned:'🏢',registered:'👤',report_sent:'📧'};
      const cls   = {check_in:'ev-check_in',left_office:'ev-left_office',returned:'ev-returned',registered:'ev-registered',report_sent:'ev-report_sent'};
      document.getElementById('logList').innerHTML = data.logs.map(l => `
        <div class="log-item">
          <div class="log-ev-icon ${cls[l.event]||'ev-default'}">${icons[l.event]||'•'}</div>
          <div class="log-body">
            <div class="log-name">${l.name||l.emp_id||''}</div>
            <div class="log-detail">${l.detail||l.event||''}</div>
          </div>
          <div class="log-time">${(l.time||'').substring(11,16)}</div>
        </div>`).join('') || '<div class="empty-state">No activity yet</div>';
      document.getElementById('logCountEl').textContent = `${data.logs.length} events`;
    }

    filterTable();
    toast('Dashboard refreshed', 'success');
  } catch(e) {
    toast('Refresh failed — check connection', 'error');
  }

  icon.classList.remove('spin'); btn.disabled = false;
}

// ── Animate numbers ───────────────────────────────────────────
function animNum(id, target) {
  const el = document.getElementById(id);
  if (!el) return;
  const start = parseInt(el.textContent) || 0;
  const diff  = target - start;
  let i = 0;
  const t = setInterval(() => {
    i++;
    el.textContent = Math.round(start + diff * i/20);
    if (i >= 20) { clearInterval(t); el.textContent = target; }
  }, 18);
}

// ── Employee detail drawer ────────────────────────────────────
function openDrawer(r) {
  document.getElementById('drawerTitle').textContent = r.name;
  document.getElementById('drawerBody').innerHTML = `
    <div class="d-av">${r.name.substring(0,2).toUpperCase()}</div>
    <div class="d-name">${r.name}</div>
    <div class="d-sub">${r.id} · ${r.department}</div>
    <div class="d-stat-grid">
      <div class="d-stat">
        <div class="d-stat-val" style="color:${r.status==='present'?'var(--green)':'var(--muted)'}">${r.status==='present'?'✅':'❌'}</div>
        <div class="d-stat-lbl">${r.status==='present'?'Present':'Absent'}</div>
      </div>
      <div class="d-stat">
        <div class="d-stat-val" style="color:var(--teal)">${r.check_in||'—'}</div>
        <div class="d-stat-lbl">Check-in</div>
      </div>
      <div class="d-stat">
        <div class="d-stat-val" style="color:var(--yellow)">${r.leave_count||0}</div>
        <div class="d-stat-lbl">Leaves today</div>
      </div>
      <div class="d-stat">
        <div class="d-stat-val">${r.out_mins||0}m</div>
        <div class="d-stat-lbl">Out of office</div>
      </div>
    </div>
    <div style="margin-bottom:.85rem">
      <div class="d-section">Current Location</div>
      <div class="d-ev-list">
        <div class="d-ev">
          <div class="d-ev-dot ${r.in_office?'dot-green':'dot-yellow'}"></div>
          <span>${r.in_office?'🏢 In office now':'🚗 Outside office'}</span>
          ${r.last_seen?`<span style="margin-left:auto;font-size:.7rem;color:var(--muted)">seen ${r.last_seen.substring(11,16)}</span>`:''}
        </div>
      </div>
    </div>
    ${r.confidence!=null?`
    <div style="margin-bottom:.85rem">
      <div class="d-section">Face Recognition</div>
      <div class="d-ev-list">
        <div class="d-ev">
          <div class="d-ev-dot dot-teal"></div>
          <span>Confidence: <strong>${r.confidence}%</strong></span>
        </div>
      </div>
    </div>`:''}
    <div style="display:flex;gap:.5rem;margin-top:1rem">
      <a href="register.php" class="btn btn-ghost" style="flex:1;justify-content:center;text-decoration:none;font-size:.78rem">Edit</a>
      <a href="send_report.php" class="btn btn-primary" style="flex:1;justify-content:center;text-decoration:none;font-size:.78rem">Send Report</a>
    </div>`;
  document.getElementById('drawerBg').classList.add('open');
}

function closeDrawer(e) {
  if (e.target === document.getElementById('drawerBg')) closeDrawerDirect();
}
function closeDrawerDirect() {
  document.getElementById('drawerBg').classList.remove('open');
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeDrawerDirect(); });

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type='info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  const ic = { success:'✅', error:'❌', info:'ℹ️' };
  el.innerHTML = `<span>${ic[type]||'ℹ️'}</span><span>${msg}</span>`;
  document.getElementById('toastWrap').appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

// Auto-refresh every 30 seconds
setInterval(refreshAll, 30000);
</script>
</body>
</html>
