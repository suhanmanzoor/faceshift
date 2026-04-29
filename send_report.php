<?php
require 'config.php';
requireAdmin();

// ── AJAX handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'preview') {
        $id   = $body['emp_id'] ?? '';
        $emps = loadJSON('employees.json');
        $att  = (loadJSON('attendance.json')[today()] ?? [])[$id] ?? null;
        $emp  = $emps[$id] ?? null;
        if (!$emp) { echo json_encode(['html' => '<p>Not found</p>']); exit; }
        echo json_encode(['html' => buildEmailHTML($emp, $att)]);
        exit;
    }

    if (in_array($action, ['send_single', 'send_all'])) {
        $employees = loadJSON('employees.json');
        $todayAtt  = loadJSON('attendance.json')[today()] ?? [];
        $active    = array_filter($employees, fn($e) => $e['active'] ?? true);

        $targets = $action === 'send_single'
            ? (isset($active[$body['emp_id']]) ? [$body['emp_id'] => $active[$body['emp_id']]] : [])
            : $active;

        $results = [];
        $sentLog = loadJSON('report_sent_log.json');

        foreach ($targets as $id => $emp) {
            if (empty($emp['email'])) {
                $results[] = ['id'=>$id,'name'=>$emp['name'],'success'=>false,'msg'=>'No email address'];
                continue;
            }

            $att     = $todayAtt[$id] ?? null;
            $subject = '['.OFFICE_NAME.'] Attendance Summary — '.date('d M Y');
            $html    = buildEmailHTML($emp, $att);

            try {
                if (!file_exists('vendor/autoload.php')) {
                    throw new Exception('PHPMailer missing. Run: composer require phpmailer/phpmailer');
                }
                require_once 'vendor/autoload.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = SMTP_PORT == 465 ? 'ssl' : 'tls';
                $mail->Port       = SMTP_PORT;
                $mail->setFrom(SMTP_USER, FROM_NAME);
                $mail->addAddress($emp['email'], $emp['name']);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $html;
                $mail->AltBody = strip_tags(str_replace(['<br>','</p>','</div>'], "\n", $html));
                $mail->send();

                $results[] = ['id'=>$id,'name'=>$emp['name'],'success'=>true,'msg'=>'Sent to '.$emp['email']];
                $sentLog[] = ['time'=>nowDT(),'ist'=>nowIST(),'emp_id'=>$id,'name'=>$emp['name'],
                              'email'=>$emp['email'],'success'=>true,'date'=>today()];
                logActivity($id, $emp['name'], 'report_sent', 'Daily report sent to '.$emp['email']);

            } catch (Exception $e) {
                $results[] = ['id'=>$id,'name'=>$emp['name'],'success'=>false,'msg'=>$e->getMessage()];
                $sentLog[] = ['time'=>nowDT(),'ist'=>nowIST(),'emp_id'=>$id,'name'=>$emp['name'],
                              'email'=>$emp['email']??'','success'=>false,'error'=>$e->getMessage(),'date'=>today()];
            }
        }
        saveJSON('report_sent_log.json', array_slice($sentLog, -500));
        $ok = count(array_filter($results, fn($r) => $r['success']));
        echo json_encode(['results'=>$results,'success_count'=>$ok,'fail_count'=>count($results)-$ok]);
        exit;
    }
    echo json_encode(['error' => 'Unknown action']); exit;
}

// ── Email HTML builder ────────────────────────────────────────
function buildEmailHTML(array $emp, ?array $att): string {
    $present = $att !== null;
    $outMins = 0;
    $leaveRows = '';

    if ($present) {
        foreach ($att['leave_events'] ?? [] as $i => $ev) {
            if ($ev['left_at'] && $ev['returned_at'])
                $outMins += (strtotime($ev['returned_at']) - strtotime($ev['left_at'])) / 60;
            $ret = $ev['returned_at'] ?? '<span style="color:#ff4757;font-weight:600">Not returned</span>';
            $n   = $i + 1;
            $leaveRows .= "<tr><td style='padding:7px 14px;color:#777;font-size:12px;border-top:1px solid #eee'>Leave #{$n}</td>
                <td style='padding:7px 14px;font-size:12px;border-top:1px solid #eee'>
                Left {$ev['left_at']} → Returned {$ret}</td></tr>";
        }
    }

    $statusBg     = $present ? '#f0fff8' : '#fff5f5';
    $statusBorder = $present ? '#b2f5c8' : '#ffc5c5';
    $statusColor  = $present ? '#22863a' : '#c0392b';
    $statusEmoji  = $present ? '✅' : '❌';
    $statusWord   = $present ? 'PRESENT' : 'ABSENT';
    $checkin      = $att['check_in'] ?? '—';
    $conf         = isset($att['confidence']) ? $att['confidence'].'%' : '—';
    $leaveCount   = count($att['leave_events'] ?? []);
    $leaveColor   = $leaveCount > 0 ? '#e65100' : '#22863a';
    $officeName   = OFFICE_NAME;
    $shiftInfo    = SHIFT_START.' – '.SHIFT_END.' IST';
    $generated    = nowIST();
    $dateStr      = date('l, d F Y');
    $empName      = htmlspecialchars($emp['name']);
    $empDept      = htmlspecialchars($emp['department']);
    $outMinsR     = round($outMins);

    $leaveSection = $leaveRows ? "
    <div style='margin-bottom:20px'>
      <div style='font-size:11px;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px'>Leave Events</div>
      <table style='width:100%;border-collapse:collapse;background:#fffbf0;border-radius:8px;overflow:hidden;border:1px solid #ffe0b2'>
        {$leaveRows}
      </table>
    </div>" : '';

    $presentRows = $present ? "
        <tr style='background:#f9f9f9'>
          <td style='padding:10px 14px;color:#666;font-size:13px'>Check-in Time</td>
          <td style='padding:10px 14px;font-size:13px;font-weight:600;color:#22863a'>{$checkin}</td>
        </tr>
        <tr>
          <td style='padding:10px 14px;color:#666;font-size:13px'>Face Recognition</td>
          <td style='padding:10px 14px;font-size:13px'>{$conf} confidence</td>
        </tr>
        <tr style='background:#f9f9f9'>
          <td style='padding:10px 14px;color:#666;font-size:13px'>Leaves Today</td>
          <td style='padding:10px 14px;font-size:13px;font-weight:600;color:{$leaveColor}'>{$leaveCount} time(s)</td>
        </tr>
        <tr>
          <td style='padding:10px 14px;color:#666;font-size:13px'>Out-of-Office Time</td>
          <td style='padding:10px 14px;font-size:13px'>{$outMinsR} minutes</td>
        </tr>" : "
        <tr>
          <td colspan='2' style='padding:14px;text-align:center;color:#c0392b;font-size:13px'>
            Employee did not check in. Please contact HR if this is incorrect.
          </td>
        </tr>";

    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:20px;background:#f4f6f9;font-family:Helvetica Neue,Arial,sans-serif'>
  <div style='max-width:560px;margin:0 auto'>
    <div style='background:linear-gradient(135deg,#00c6d7,#0077b6);border-radius:12px 12px 0 0;padding:28px 32px;text-align:center'>
      <div style='font-size:22px;font-weight:700;color:#fff;margin-bottom:4px'>FaceShift Daily Report</div>
      <div style='color:rgba(255,255,255,.75);font-size:14px'>{$dateStr} · {$officeName}</div>
    </div>
    <div style='background:#fff;padding:28px 32px;border-radius:0 0 12px 12px;box-shadow:0 4px 16px rgba(0,0,0,.08)'>
      <p style='color:#333;font-size:15px;margin:0 0 20px'>Hello <strong>{$empName}</strong>,</p>
      <div style='background:{$statusBg};border:1px solid {$statusBorder};border-radius:10px;padding:20px;margin-bottom:20px;text-align:center'>
        <div style='font-size:36px;margin-bottom:8px'>{$statusEmoji}</div>
        <div style='font-size:20px;font-weight:700;color:{$statusColor}'>{$statusWord}</div>
        <div style='color:#888;font-size:13px;margin-top:4px'>{$dateStr}</div>
      </div>
      <table style='width:100%;border-collapse:collapse;margin-bottom:20px;border-radius:8px;overflow:hidden;border:1px solid #eee'>
        <tr><td style='padding:10px 14px;color:#666;font-size:13px;background:#f9f9f9'>Department</td>
            <td style='padding:10px 14px;font-size:13px;background:#f9f9f9'>{$empDept}</td></tr>
        {$presentRows}
      </table>
      {$leaveSection}
      <div style='background:#f4f6f9;border-radius:8px;padding:14px 16px'>
        <div style='font-size:12px;color:#888'>
          Shift hours: <strong style='color:#333'>{$shiftInfo}</strong> &nbsp;·&nbsp;
          Report generated: <strong style='color:#333'>{$generated}</strong>
        </div>
      </div>
      <p style='color:#bbb;font-size:11px;margin:20px 0 0;text-align:center'>
        Automated message from {$officeName} HR · Powered by FaceShift
      </p>
    </div>
  </div>
</body></html>";
}

// ── Page data ─────────────────────────────────────────────────
$employees = loadJSON('employees.json');
$todayAtt  = loadJSON('attendance.json')[today()] ?? [];
$sentLog   = loadJSON('report_sent_log.json');
$active    = array_filter($employees, fn($e) => $e['active'] ?? true);
$day       = today();

$rows = [];
foreach ($active as $id => $emp) {
    $att     = $todayAtt[$id] ?? null;
    $outMins = 0;
    if ($att) {
        foreach ($att['leave_events'] ?? [] as $ev) {
            if ($ev['left_at'] && $ev['returned_at'])
                $outMins += (strtotime($ev['returned_at']) - strtotime($ev['left_at'])) / 60;
        }
    }
    $sentToday = false; $sentAt = null;
    foreach (array_reverse($sentLog) as $sl) {
        if (($sl['emp_id']??'') === $id && ($sl['date']??'') === $day && ($sl['success']??false)) {
            $sentToday = true; $sentAt = substr($sl['time']??'',11,5); break;
        }
    }
    $rows[] = [
        'id'         => $id,
        'name'       => $emp['name'],
        'email'      => $emp['email'] ?? '',
        'department' => $emp['department'],
        'check_in'   => $att['check_in'] ?? null,
        'status'     => $att ? 'present' : 'absent',
        'leave_count'=> count($att['leave_events'] ?? []),
        'out_mins'   => round($outMins),
        'confidence' => $att['confidence'] ?? null,
        'sent_today' => $sentToday,
        'sent_at'    => $sentAt,
    ];
}

$presentCount = count(array_filter($rows, fn($r) => $r['status'] === 'present'));
$sentCount    = count(array_filter($rows, fn($r) => $r['sent_today']));
$smtpOk       = !empty(SMTP_USER) && SMTP_USER !== 'your@gmail.com';
$recentSent   = array_slice(array_reverse($sentLog), 0, 8);
$shiftActive  = isShiftActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Send Reports — FaceShift</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--teal:#00d4e8;--green:#00ff88;--red:#ff4757;--yellow:#ffd700;--blue:#4f9cf9;--purple:#a78bfa;
  --bg:#080a10;--s1:#0e1220;--s2:#131826;--border:#1c2235;--border2:#222a3e;
  --text:#e2e8f8;--muted:#6b7a99;--faint:#1c2235}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;display:flex;min-height:100vh}
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
.main{flex:1;padding:2rem;overflow-y:auto}
.page-hdr{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.page-title{font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:.2rem}
.page-sub{font-size:.8rem;color:var(--muted)}
.btn{border:none;cursor:pointer;font-family:'Inter',sans-serif;font-weight:600;transition:all .18s;display:inline-flex;align-items:center;gap:.4rem;border-radius:8px;font-size:.82rem;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,var(--teal),#0077b6);color:#fff;padding:.55rem 1.1rem}
.btn-primary:hover{opacity:.88}
.btn-primary:disabled{opacity:.45;cursor:not-allowed}
.btn-ghost{background:var(--s2);color:var(--muted);border:1px solid var(--border);padding:.45rem .9rem}
.btn-ghost:hover{color:var(--text)}
.btn-sm{padding:.35rem .75rem;font-size:.76rem}
/* KPI */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem;margin-bottom:1.5rem}
@media(max-width:900px){.kpi-row{grid-template-columns:1fr 1fr}}
.kpi{background:var(--s1);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem}
.kpi-val{font-size:1.8rem;font-weight:700;color:#fff;font-variant-numeric:tabular-nums;line-height:1;margin-bottom:.2rem}
.kpi-lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.kv-teal{color:var(--teal)}.kv-green{color:var(--green)}.kv-red{color:var(--red)}.kv-purple{color:var(--purple)}
/* SMTP warning */
.smtp-warn{background:rgba(255,159,67,.08);border:1px solid rgba(255,159,67,.2);border-radius:10px;padding:.85rem 1rem;font-size:.82rem;color:#ff9f43;display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem}
/* Content grid */
.grid{display:grid;grid-template-columns:1fr 300px;gap:1.25rem}
@media(max-width:1050px){.grid{grid-template-columns:1fr}}
.card{background:var(--s1);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.card-hdr{padding:.85rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.card-title{font-size:.88rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.5rem}
/* Table */
table.rt{width:100%;border-collapse:collapse}
table.rt thead th{padding:.5rem .9rem;text-align:left;font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);white-space:nowrap}
table.rt tbody td{padding:.6rem .9rem;border-bottom:1px solid rgba(28,34,53,.5);font-size:.82rem;vertical-align:middle}
table.rt tbody tr:last-child td{border:none}
table.rt tbody tr{transition:background .15s}
table.rt tbody tr:hover td{background:rgba(19,24,38,.6)}
.emp-cell{display:flex;align-items:center;gap:.55rem}
.emp-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#0077b6);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;color:#fff;flex-shrink:0}
.emp-av.absent{background:var(--faint)}
.badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.68rem;font-weight:600;padding:.18rem .5rem;border-radius:20px}
.b-present{background:rgba(0,255,136,.1);color:var(--green);border:1px solid rgba(0,255,136,.2)}
.b-absent{background:rgba(107,122,153,.1);color:var(--muted);border:1px solid rgba(107,122,153,.2)}
.b-sent{background:rgba(0,212,232,.1);color:var(--teal);border:1px solid rgba(0,212,232,.2)}
.b-nomail{background:rgba(255,71,87,.1);color:var(--red);border:1px solid rgba(255,71,87,.2)}
/* Send btn per row */
.btn-send-row{background:var(--s2);border:1px solid var(--border);color:var(--muted);padding:.28rem .65rem;border-radius:6px;font-size:.72rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all .18s;white-space:nowrap}
.btn-send-row:hover{border-color:var(--teal);color:var(--teal)}
.btn-send-row.sent{background:rgba(0,212,232,.08);border-color:rgba(0,212,232,.2);color:var(--teal);cursor:default}
.btn-send-row:disabled{opacity:.4;cursor:not-allowed}
/* Log */
.log-item{display:flex;gap:.6rem;padding:.6rem 1rem;border-bottom:1px solid rgba(28,34,53,.5);align-items:center}
.log-item:last-child{border:none}
.log-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.log-dot.ok{background:var(--green)}.log-dot.fail{background:var(--red)}
.log-nm{font-size:.8rem;font-weight:500;flex:1}
.log-em{font-size:.72rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px}
.log-t{font-size:.7rem;color:var(--muted);font-variant-numeric:tabular-nums;flex-shrink:0}
/* Toast */
.toast-wrap{position:fixed;bottom:1.5rem;right:1.5rem;display:flex;flex-direction:column;gap:.5rem;z-index:500;pointer-events:none}
.toast{background:var(--s1);border:1px solid var(--border);border-radius:10px;padding:.75rem 1rem;font-size:.82rem;display:flex;align-items:center;gap:.6rem;max-width:320px;pointer-events:all;animation:toastIn .2s ease;box-shadow:0 4px 16px rgba(0,0,0,.4)}
@keyframes toastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.success{border-color:rgba(0,255,136,.2);color:var(--green)}
.toast.error{border-color:rgba(255,71,87,.2);color:var(--red)}
/* Preview modal */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:300;display:none;align-items:center;justify-content:center;padding:1rem}
.modal-bg.open{display:flex}
.modal{background:var(--s1);border:1px solid var(--border);border-radius:16px;width:600px;max-width:95vw;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.modal-hdr{padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.modal-title{font-weight:700;color:#fff;font-size:.95rem}
.modal-close{background:none;border:none;color:var(--muted);font-size:1.4rem;cursor:pointer;transition:color .2s;line-height:1}
.modal-close:hover{color:#fff}
.modal-body{flex:1;overflow-y:auto;padding:0}
.email-frame{width:100%;min-height:420px;border:none;background:#fff}
.spin{animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--faint);border-radius:2px}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sb">
  <div class="sb-logo">
    <div class="sb-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2"><circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/></svg></div>
    <div><div class="sb-name">FaceShift</div><span class="sb-version"><?= OFFICE_NAME ?></span></div>
  </div>
  <span class="sb-section">Main</span>
  <a class="nav-a" href="admin.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a>
  <a class="nav-a" href="register.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg> Employees</a>
  <a class="nav-a" href="logs.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Activity Logs</a>
  <a class="nav-a active" href="send_report.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Send Reports</a>
  <a class="nav-a" href="settings.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a>
  <hr class="sb-div">
  <a class="nav-a" href="attendance.php" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg> Face Kiosk</a>
  <div class="sb-spacer"></div>
  <hr class="sb-div">
  <div class="shift-pill <?= $shiftActive ? 'shift-on' : 'shift-off' ?>">
    <div class="shift-dot <?= $shiftActive ? 'pulse' : '' ?>"></div>
    <span><?= $shiftActive ? 'Shift Active' : 'Shift Closed' ?></span>
    <span style="margin-left:auto;font-size:.68rem;opacity:.7"><?= SHIFT_START ?>–<?= SHIFT_END ?></span>
  </div>
  <a class="nav-a" href="logout.php" style="color:#ff4757"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
</div>

<!-- MAIN -->
<div class="main">
  <div class="page-hdr">
    <div>
      <div class="page-title">✉️ Send Reports</div>
      <div class="page-sub"><?= date('l, d M Y') ?> · <?= $presentCount ?>/<?= count($rows) ?> present · <?= $sentCount ?> report<?= $sentCount!==1?'s':'' ?> sent today</div>
    </div>
    <button class="btn btn-primary" id="sendAllBtn" onclick="sendAll()" <?= empty($rows) ? 'disabled' : '' ?>>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Send All Reports
    </button>
  </div>

  <?php if (!$smtpOk): ?>
  <div class="smtp-warn">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    SMTP not configured. <a href="settings.php" style="color:inherit;font-weight:700;margin-left:.3rem">Configure in Settings →</a>
  </div>
  <?php endif; ?>

  <!-- KPI -->
  <div class="kpi-row">
    <div class="kpi"><div class="kpi-val kv-teal"><?= count($rows) ?></div><div class="kpi-lbl">Total Employees</div></div>
    <div class="kpi"><div class="kpi-val kv-green"><?= $presentCount ?></div><div class="kpi-lbl">Present Today</div></div>
    <div class="kpi"><div class="kpi-val kv-red"><?= count($rows) - $presentCount ?></div><div class="kpi-lbl">Absent Today</div></div>
    <div class="kpi"><div class="kpi-val kv-purple"><?= $sentCount ?></div><div class="kpi-lbl">Reports Sent</div></div>
  </div>

  <!-- Main grid -->
  <div class="grid">

    <!-- Employee Table -->
    <div class="card">
      <div class="card-hdr">
        <span class="card-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          Employee Reports
        </span>
        <span style="font-size:.73rem;color:var(--muted)" id="progressText"><?= $sentCount ?>/<?= count($rows) ?> sent</span>
      </div>
      <div style="overflow-x:auto">
        <table class="rt">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Status</th>
              <th>Check-in</th>
              <th>Leaves</th>
              <th>Out Time</th>
              <th>Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="reportBody">
            <?php foreach ($rows as $r): ?>
            <tr id="row-<?= $r['id'] ?>">
              <td>
                <div class="emp-cell">
                  <div class="emp-av <?= $r['status']==='absent'?'absent':'' ?>"><?= strtoupper(substr($r['name'],0,2)) ?></div>
                  <div>
                    <div style="font-weight:500;font-size:.83rem"><?= htmlspecialchars($r['name']) ?></div>
                    <div style="color:var(--muted);font-size:.7rem"><?= $r['id'] ?></div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($r['status']==='present'): ?>
                  <span class="badge b-present">✅ Present</span>
                <?php else: ?>
                  <span class="badge b-absent">❌ Absent</span>
                <?php endif; ?>
              </td>
              <td style="color:<?= $r['check_in']?'var(--green)':'var(--muted)' ?>;font-variant-numeric:tabular-nums">
                <?= $r['check_in'] ?: '—' ?>
              </td>
              <td style="text-align:center;color:<?= $r['leave_count']>0?'var(--yellow)':'var(--muted)' ?>">
                <?= $r['status']==='present' ? $r['leave_count'] : '—' ?>
              </td>
              <td style="color:var(--muted);font-size:.78rem;font-variant-numeric:tabular-nums">
                <?= $r['status']==='present' ? $r['out_mins'].'m' : '—' ?>
              </td>
              <td style="font-size:.75rem;color:var(--muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php if ($r['email']): ?>
                  <span title="<?= htmlspecialchars($r['email']) ?>"><?= htmlspecialchars($r['email']) ?></span>
                <?php else: ?>
                  <span class="badge b-nomail">No email</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:.4rem;align-items:center">
                  <?php if ($r['email']): ?>
                  <button class="btn-send-row <?= $r['sent_today']?'sent':'' ?>"
                          id="sbtn-<?= $r['id'] ?>"
                          onclick="sendSingle('<?= $r['id'] ?>','<?= htmlspecialchars($r['name']) ?>')"
                          <?= $r['sent_today']?'disabled':'' ?>>
                    <?= $r['sent_today'] ? ('✓ '.$r['sent_at']) : 'Send' ?>
                  </button>
                  <button class="btn-send-row" onclick="previewEmail('<?= $r['id'] ?>','<?= htmlspecialchars($r['name']) ?>')" title="Preview email" style="padding:.28rem .5rem">👁</button>
                  <?php else: ?>
                  <span style="color:var(--muted);font-size:.72rem">—</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--muted);font-size:.85rem">
              No employees registered. <a href="register.php" style="color:var(--teal)">Add employees →</a>
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right: Sent log -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">📬 Sent Log</span>
          <span style="font-size:.72rem;color:var(--muted)">Recent</span>
        </div>
        <div id="sentLogList" style="max-height:320px;overflow-y:auto">
          <?php if (empty($recentSent)): ?>
          <div style="text-align:center;padding:2rem;color:var(--muted);font-size:.8rem">No reports sent yet</div>
          <?php else: foreach ($recentSent as $sl): ?>
          <div class="log-item">
            <div class="log-dot <?= ($sl['success']??false)?'ok':'fail' ?>"></div>
            <div style="flex:1;min-width:0">
              <div class="log-nm"><?= htmlspecialchars($sl['name']??$sl['emp_id']??'') ?></div>
              <div class="log-em"><?= htmlspecialchars($sl['email']??'') ?></div>
            </div>
            <div class="log-t"><?= isset($sl['time']) ? substr($sl['time'],11,5) : '' ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- SMTP status card -->
      <div class="card">
        <div class="card-hdr"><span class="card-title">⚙️ SMTP Config</span></div>
        <div style="padding:1rem">
          <div style="display:flex;flex-direction:column;gap:.6rem">
            <div style="display:flex;justify-content:space-between;font-size:.8rem">
              <span style="color:var(--muted)">Host</span>
              <span style="font-variant-numeric:tabular-nums"><?= htmlspecialchars(SMTP_HOST) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.8rem">
              <span style="color:var(--muted)">Port</span>
              <span><?= SMTP_PORT ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.8rem">
              <span style="color:var(--muted)">From</span>
              <span style="color:var(--muted);font-size:.72rem;max-width:150px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap"><?= htmlspecialchars(SMTP_USER) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.8rem">
              <span style="color:var(--muted)">Status</span>
              <span class="badge <?= $smtpOk?'b-present':'b-nomail' ?>"><?= $smtpOk?'✅ Configured':'⚠️ Not set' ?></span>
            </div>
          </div>
          <a href="settings.php" style="display:block;margin-top:.85rem;font-size:.78rem;color:var(--teal);text-decoration:none;text-align:center">Edit in Settings →</a>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- Email Preview Modal -->
<div class="modal-bg" id="previewModal">
  <div class="modal">
    <div class="modal-hdr">
      <span class="modal-title" id="previewTitle">Email Preview</span>
      <button class="modal-close" onclick="closePreview()">×</button>
    </div>
    <div class="modal-body">
      <div id="previewLoader" style="padding:3rem;text-align:center;color:var(--muted)">
        <div style="width:28px;height:28px;border:3px solid var(--faint);border-top-color:var(--teal);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto .75rem"></div>
        Loading preview…
      </div>
      <iframe id="previewFrame" class="email-frame" style="display:none" title="Email preview"></iframe>
    </div>
  </div>
</div>

<script>
let sending = false;

// ── Send single ───────────────────────────────────────────────
async function sendSingle(empId, name) {
  if (sending) return;
  const btn = document.getElementById('sbtn-' + empId);
  const orig = btn.textContent;
  btn.disabled = true;
  btn.textContent = '…';

  try {
    const res  = await fetch('send_report.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'send_single', emp_id: empId})
    });
    const data = await res.json();
    const r    = data.results?.[0];

    if (r?.success) {
      btn.className   = 'btn-send-row sent';
      btn.textContent = '✓ ' + new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
      showToast('✅ Report sent to ' + name, 'success');
      updateProgress();
    } else {
      btn.disabled    = false;
      btn.textContent = orig;
      showToast('⚠️ Failed: ' + (r?.msg || 'Unknown error'), 'error');
    }
  } catch(e) {
    btn.disabled    = false;
    btn.textContent = orig;
    showToast('⚠️ Network error', 'error');
  }
}

// ── Send all ──────────────────────────────────────────────────
async function sendAll() {
  if (sending) return;
  if (!confirm('Send daily attendance reports to ALL employees with email addresses?')) return;

  sending = true;
  const btn = document.getElementById('sendAllBtn');
  const origHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Sending…';

  try {
    const res  = await fetch('send_report.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'send_all'})
    });
    const data = await res.json();

    data.results?.forEach(r => {
      const btn = document.getElementById('sbtn-' + r.id);
      if (!btn) return;
      if (r.success) {
        btn.className   = 'btn-send-row sent';
        btn.disabled    = true;
        btn.textContent = '✓ ' + new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
      }
    });

    const ok   = data.success_count || 0;
    const fail = data.fail_count    || 0;
    showToast(`✅ Sent ${ok} report${ok!==1?'s':''}${fail?' · ⚠️ '+fail+' failed':''}`,
              fail > 0 ? 'error' : 'success');
    updateProgress();
  } catch(e) {
    showToast('⚠️ Send failed: ' + e.message, 'error');
  }

  sending    = false;
  btn.disabled = false;
  btn.innerHTML = origHTML;
}

// ── Preview ───────────────────────────────────────────────────
async function previewEmail(empId, name) {
  document.getElementById('previewTitle').textContent = 'Preview — ' + name;
  document.getElementById('previewLoader').style.display = 'block';
  document.getElementById('previewFrame').style.display  = 'none';
  document.getElementById('previewModal').classList.add('open');

  try {
    const res  = await fetch('send_report.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'preview', emp_id: empId})
    });
    const data = await res.json();
    const frame = document.getElementById('previewFrame');
    frame.srcdoc = data.html;
    frame.onload = () => {
      document.getElementById('previewLoader').style.display = 'none';
      frame.style.display = 'block';
    };
  } catch(e) {
    document.getElementById('previewLoader').innerHTML = '<span style="color:#ff4757">Failed to load preview</span>';
  }
}

function closePreview() {
  document.getElementById('previewModal').classList.remove('open');
  document.getElementById('previewFrame').srcdoc = '';
}
document.getElementById('previewModal').addEventListener('click', e => {
  if (e.target === document.getElementById('previewModal')) closePreview();
});

// ── Progress text ─────────────────────────────────────────────
function updateProgress() {
  const total = document.querySelectorAll('[id^="sbtn-"]').length;
  const sent  = document.querySelectorAll('[id^="sbtn-"].sent').length;
  document.getElementById('progressText').textContent = sent + '/' + total + ' sent';
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type='success') {
  const wrap = document.getElementById('toastWrap');
  const t    = document.createElement('div');
  t.className = 'toast ' + type;
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>
</body>
</html>
