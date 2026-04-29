<?php
require 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
if (!$action) $action = $body['action'] ?? ($_POST['action'] ?? '');

// ── GET: shift status (real-time from config) ────────────────
if ($action === 'shift_status') {
    echo json_encode([
        'active'      => isShiftActive(),
        'shift_start' => SHIFT_START,
        'shift_end'   => SHIFT_END,
        'now_ist'     => date('H:i'),
        'time_display'=> date('h:i A') . ' IST'
    ]);
    exit;
}

// ── GET: all employee face descriptors ───────────────────────
if ($action === 'descriptors') {
    $employees = loadJSON('employees.json');
    $out = [];
    foreach ($employees as $emp) {
        if (!($emp['active'] ?? true)) continue;
        $out[] = ['id' => $emp['id'], 'name' => $emp['name'], 'descriptors' => $emp['descriptors']];
    }
    echo json_encode(['employees' => $out]);
    exit;
}

// ── GET: descriptors for ONE employee (tracker verification) ──
if ($action === 'employee_descriptors') {
    $empId     = $_GET['emp_id'] ?? '';
    $employees = loadJSON('employees.json');
    if (!isset($employees[$empId]) || !($employees[$empId]['active'] ?? true)) {
        echo json_encode(['found' => false]); exit;
    }
    $emp = $employees[$empId];
    echo json_encode([
        'found'       => true,
        'id'          => $emp['id'],
        'name'        => $emp['name'],
        'department'  => $emp['department'],
        'descriptors' => $emp['descriptors']
    ]);
    exit;
}

// ── POST: mark attendance (called from kiosk after GPS check) ─
if ($action === 'mark_attendance') {
    $empId   = $body['emp_id']    ?? '';
    $empName = $body['emp_name']  ?? '';
    $conf    = (float)($body['confidence'] ?? 0);
    $lat     = (float)($body['lat']  ?? 0);
    $lng     = (float)($body['lng']  ?? 0);

    if (!$empId) { echo json_encode(['success' => false, 'msg' => 'Missing ID']); exit; }

    // Verify still in premises at time of marking
    $dist     = ($lat && $lng) ? haversine($lat, $lng, OFFICE_LAT, OFFICE_LNG) : 0;
    $inOffice = ($dist <= OFFICE_RADIUS_M);

    $attendance = loadJSON('attendance.json');
    $day        = today();
    if (!isset($attendance[$day])) $attendance[$day] = [];

    if (isset($attendance[$day][$empId])) {
        echo json_encode(['success' => true, 'already' => true, 'check_in' => $attendance[$day][$empId]['check_in'], 'ist' => $attendance[$day][$empId]['check_in_ist']]);
        exit;
    }

    $attendance[$day][$empId] = [
        'emp_id'        => $empId,
        'name'          => $empName,
        'check_in'      => nowTime(),
        'check_in_ist'  => date('h:i A') . ' IST',
        'check_in_dt'   => nowDT(),
        'status'        => 'present',
        'confidence'    => round($conf * 100, 1),
        'check_in_lat'  => $lat,
        'check_in_lng'  => $lng,
        'check_in_dist' => round($dist),
        'in_office'     => true,
        'tracker_active'=> false,
        'last_ping'     => null,
        'location_pings'=> [],
        'leave_events'  => []
    ];
    saveJSON('attendance.json', $attendance);
    logActivity($empId, $empName, 'check_in', "Checked in at office | Confidence: " . round($conf*100) . "% | Distance: {$dist}m");

    echo json_encode(['success' => true, 'check_in' => nowTime(), 'check_in_ist' => date('h:i A') . ' IST']);
    exit;
}

// ── POST: employee tracker verification + start ──────────────
if ($action === 'start_tracker') {
    $empId = $body['emp_id'] ?? '';
    $employees = loadJSON('employees.json');
    if (!isset($employees[$empId])) { echo json_encode(['success' => false, 'msg' => 'Employee not found']); exit; }

    $attendance = loadJSON('attendance.json');
    $day        = today();
    if (!isset($attendance[$day][$empId])) {
        echo json_encode(['success' => false, 'msg' => 'Not checked in at kiosk yet. Please mark attendance at the office entrance first.']);
        exit;
    }

    $attendance[$day][$empId]['tracker_active'] = true;
    $attendance[$day][$empId]['tracker_start']  = nowDT();
    saveJSON('attendance.json', $attendance);

    $emp = $employees[$empId];
    logActivity($empId, $emp['name'], 'tracker_started', 'Employee tracking portal activated');

    echo json_encode(['success' => true, 'name' => $emp['name'], 'department' => $emp['department']]);
    exit;
}

// ── POST: GPS ping every 5 minutes from tracker ──────────────
if ($action === 'tracker_ping') {
    $empId = $body['emp_id'] ?? '';
    $lat   = (float)($body['lat'] ?? 0);
    $lng   = (float)($body['lng'] ?? 0);
    $acc   = (float)($body['accuracy'] ?? 0);

    if (!$empId || !$lat) { echo json_encode(['success' => false]); exit; }

    $attendance = loadJSON('attendance.json');
    $day        = today();
    if (!isset($attendance[$day][$empId])) { echo json_encode(['success' => false, 'msg' => 'Not checked in']); exit; }

    $dist     = haversine($lat, $lng, OFFICE_LAT, OFFICE_LNG);
    $inOffice = ($dist <= OFFICE_RADIUS_M);
    $wasIn    = $attendance[$day][$empId]['in_office'] ?? true;

    $ping = [
        'time'      => nowDT(),
        'ist'       => date('h:i A') . ' IST',
        'lat'       => $lat,
        'lng'       => $lng,
        'accuracy'  => round($acc),
        'dist_m'    => round($dist),
        'in_office' => $inOffice
    ];

    // Detect leave / return events
    if ($wasIn && !$inOffice) {
        $ping['event'] = 'left_office';
        $attendance[$day][$empId]['leave_events'][] = ['left_at' => nowTime(), 'left_ist' => date('h:i A') . ' IST', 'returned_at' => null];
        $emp = loadJSON('employees.json')[$empId] ?? [];
        logActivity($empId, $emp['name'] ?? $empId, 'left_office', "Left office premises · " . round($dist) . "m from office");
    }
    if (!$wasIn && $inOffice) {
        $ping['event'] = 'returned';
        $evs  = &$attendance[$day][$empId]['leave_events'];
        $last = count($evs) - 1;
        if ($last >= 0 && $evs[$last]['returned_at'] === null) {
            $evs[$last]['returned_at']  = nowTime();
            $evs[$last]['returned_ist'] = date('h:i A') . ' IST';
        }
        $emp = loadJSON('employees.json')[$empId] ?? [];
        logActivity($empId, $emp['name'] ?? $empId, 'returned', 'Returned to office premises');
    }

    $attendance[$day][$empId]['location_pings'][] = $ping;
    $attendance[$day][$empId]['in_office']   = $inOffice;
    $attendance[$day][$empId]['last_ping']   = nowDT();
    $attendance[$day][$empId]['last_ping_ist']= date('h:i A') . ' IST';
    $attendance[$day][$empId]['last_lat']    = $lat;
    $attendance[$day][$empId]['last_lng']    = $lng;
    saveJSON('attendance.json', $attendance);

    echo json_encode([
        'success'   => true,
        'in_office' => $inOffice,
        'dist_m'    => round($dist),
        'ist'       => date('h:i A') . ' IST',
        'shift_active' => isShiftActive()
    ]);
    exit;
}

// ── GET: today's data for admin dashboard ────────────────────
if ($action === 'today_data') {
    if (!isAdmin()) { echo json_encode(['error' => 'Unauthorized']); exit; }

    $employees  = loadJSON('employees.json');
    $attendance = loadJSON('attendance.json');
    $day        = today();
    $todayAtt   = $attendance[$day] ?? [];
    $active     = array_filter($employees, fn($e) => $e['active'] ?? true);
    $rows       = [];

    foreach ($active as $id => $emp) {
        if (isset($todayAtt[$id])) {
            $att     = $todayAtt[$id];
            $outMins = 0;
            foreach ($att['leave_events'] as $ev) {
                if ($ev['left_at'] && $ev['returned_at']) {
                    $outMins += (strtotime(today().' '.$ev['returned_at']) - strtotime(today().' '.$ev['left_at'])) / 60;
                } elseif ($ev['left_at']) {
                    $outMins += (time() - strtotime(today().' '.$ev['left_at'])) / 60;
                }
            }
            $rows[] = [
                'id'             => $id,
                'name'           => $emp['name'],
                'department'     => $emp['department'],
                'check_in'       => $att['check_in'],
                'check_in_ist'   => $att['check_in_ist'] ?? $att['check_in'],
                'status'         => 'present',
                'in_office'      => $att['in_office'] ?? true,
                'tracker_active' => $att['tracker_active'] ?? false,
                'last_ping_ist'  => $att['last_ping_ist'] ?? null,
                'leave_count'    => count($att['leave_events']),
                'out_mins'       => round($outMins),
                'ping_count'     => count($att['location_pings']),
                'confidence'     => $att['confidence']
            ];
        } else {
            $rows[] = [
                'id' => $id, 'name' => $emp['name'], 'department' => $emp['department'],
                'check_in' => null, 'status' => 'absent', 'in_office' => false,
                'tracker_active' => false, 'leave_count' => 0, 'out_mins' => 0, 'ping_count' => 0
            ];
        }
    }

    $logs = array_reverse(array_slice(loadJSON('activity_log.json'), -60));
    echo json_encode([
        'rows'         => $rows,
        'logs'         => $logs,
        'total'        => count($rows),
        'present'      => count(array_filter($rows, fn($r) => $r['status']==='present')),
        'absent'       => count(array_filter($rows, fn($r) => $r['status']==='absent')),
        'in_office'    => count(array_filter($rows, fn($r) => $r['in_office'] && $r['status']==='present')),
        'tracking'     => count(array_filter($rows, fn($r) => $r['tracker_active'])),
        'shift_active' => isShiftActive(),
        'ist_now'      => date('h:i A') . ' IST'
    ]);
    exit;
}

// ── GET: single employee descriptors for tracker self-verify ─
if ($action === 'employee_descriptors') {
    $empId     = $_GET['emp_id'] ?? '';
    $employees = loadJSON('employees.json');

    if (!$empId || !isset($employees[$empId]) || !($employees[$empId]['active'] ?? true)) {
        echo json_encode(['found' => false]); exit;
    }

    $emp = $employees[$empId];
    echo json_encode([
        'found'       => true,
        'id'          => $emp['id'],
        'name'        => $emp['name'],
        'department'  => $emp['department'],
        'descriptors' => $emp['descriptors']
    ]);
    exit;
}

// ── POST: start tracker session (validates check-in exists) ──
if ($action === 'start_tracker') {
    $empId      = $body['emp_id'] ?? '';
    $attendance = loadJSON('attendance.json');
    $day        = today();

    if (!$empId || !isset($attendance[$day][$empId])) {
        echo json_encode([
            'success' => false,
            'msg'     => 'You have not checked in today. Please mark attendance at the office kiosk first.'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'check_in' => $attendance[$day][$empId]['check_in']]);
    exit;
}

// ── POST: tracker GPS ping ────────────────────────────────────
if ($action === 'tracker_ping') {
    $empId = $body['emp_id'] ?? '';
    $lat   = (float)($body['lat']      ?? 0);
    $lng   = (float)($body['lng']      ?? 0);
    $acc   = (float)($body['accuracy'] ?? 0);

    if (!$empId || !$lat) {
        echo json_encode(['success' => false, 'msg' => 'Invalid data']); exit;
    }

    $attendance = loadJSON('attendance.json');
    $day        = today();

    if (!isset($attendance[$day][$empId])) {
        echo json_encode(['success' => false, 'msg' => 'Not checked in', 'shift_active' => isShiftActive()]); exit;
    }

    $dist      = haversine($lat, $lng, OFFICE_LAT, OFFICE_LNG);
    $inOffice  = ($dist <= OFFICE_RADIUS_M);
    $wasIn     = $attendance[$day][$empId]['in_office'] ?? true;

    $logEntry = [
        'time'      => nowDT(),
        'lat'       => $lat,
        'lng'       => $lng,
        'accuracy'  => $acc,
        'dist_m'    => round($dist),
        'in_office' => $inOffice,
        'source'    => 'tracker'
    ];

    // Leave event
    if ($wasIn && !$inOffice) {
        $logEntry['event'] = 'left_office';
        $attendance[$day][$empId]['leave_events'][] = ['left_at' => nowTime(), 'returned_at' => null];
        $logs = loadJSON('activity_log.json');
        $emp  = loadJSON('employees.json')[$empId] ?? [];
        $logs[] = ['time' => nowDT(), 'emp_id' => $empId, 'name' => $emp['name'] ?? $empId, 'event' => 'left_office', 'detail' => "Left office ({$dist}m away) — tracker ping"];
        saveJSON('activity_log.json', array_slice($logs, -500));
    }

    // Return event
    if (!$wasIn && $inOffice) {
        $logEntry['event'] = 'returned';
        $leaveEvents = &$attendance[$day][$empId]['leave_events'];
        $last = count($leaveEvents) - 1;
        if ($last >= 0 && $leaveEvents[$last]['returned_at'] === null) {
            $leaveEvents[$last]['returned_at'] = nowTime();
        }
        $logs = loadJSON('activity_log.json');
        $emp  = loadJSON('employees.json')[$empId] ?? [];
        $logs[] = ['time' => nowDT(), 'emp_id' => $empId, 'name' => $emp['name'] ?? $empId, 'event' => 'returned', 'detail' => 'Returned to office — tracker ping'];
        saveJSON('activity_log.json', array_slice($logs, -500));
    }

    $attendance[$day][$empId]['location_logs'][] = $logEntry;
    $attendance[$day][$empId]['in_office']   = $inOffice;
    $attendance[$day][$empId]['last_seen']   = nowDT();
    $attendance[$day][$empId]['last_lat']    = $lat;
    $attendance[$day][$empId]['last_lng']    = $lng;
    saveJSON('attendance.json', $attendance);

    echo json_encode([
        'success'      => true,
        'in_office'    => $inOffice,
        'dist_m'       => round($dist),
        'shift_active' => isShiftActive()
    ]);
    exit;
}

echo json_encode(['error' => 'Unknown action: ' . $action]);