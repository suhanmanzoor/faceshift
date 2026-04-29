<?php
date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) session_start();

// ── PATHS (defined before settings load) ─────────────────────
define('DATA_DIR',  __DIR__ . '/data/');
define('FACES_DIR', __DIR__ . '/uploads/faces/');

// ── LOAD SETTINGS.JSON (with hardcoded fallbacks) ─────────────
function loadSettings(): array {
    $path = DATA_DIR . 'settings.json';
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}

$_S = loadSettings();

// Office
define('OFFICE_NAME',     $_S['office_name']     ?? 'TechCorp HQ');
define('OFFICE_ADDRESS',  $_S['office_address']  ?? 'New Delhi, India');
define('OFFICE_LAT',      (float)($_S['office_lat']      ?? 33.772661));
define('OFFICE_LNG',      (float)($_S['office_lng']      ?? 75.171258));
define('OFFICE_RADIUS_M', (int)($_S['office_radius_m']   ?? 150));

// Shift
define('SHIFT_START', $_S['shift_start'] ?? '09:00');
define('SHIFT_END',   $_S['shift_end']   ?? '18:00');

// Working days (0=Sun,1=Mon,...,6=Sat)
define('WORKING_DAYS', $_S['working_days'] ?? [1,2,3,4,5]);

// Admin
define('ADMIN_USER',      'admin');
define('ADMIN_PASS_HASH', '$2y$12$0dUlavw/7U3YCo0tTsW6DOri7z7Qak7/YFjsc..OU4GbA2d5XyleG');

// Email
define('SMTP_HOST', $_S['smtp_host'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int)($_S['smtp_port'] ?? 587));
define('SMTP_USER', $_S['smtp_user'] ?? 'your@gmail.com');
define('SMTP_PASS', $_S['smtp_pass'] ?? 'your-app-password');
define('FROM_NAME', $_S['from_name'] ?? OFFICE_NAME . ' HR');

// Tracker
define('TRACKER_PING_INTERVAL', (int)($_S['tracker_ping_interval'] ?? 5));

unset($_S); // clean up

// ═════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═════════════════════════════════════════════════════════════

function loadJSON(string $f): array {
    $p = DATA_DIR . $f;
    if (!file_exists($p)) return [];
    return json_decode(file_get_contents($p), true) ?: [];
}

function saveJSON(string $f, array $d): void {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    file_put_contents(DATA_DIR . $f, json_encode($d, JSON_PRETTY_PRINT));
}

function today():   string { return date('Y-m-d'); }
function nowTime(): string { return date('H:i:s'); }
function nowDT():   string { return date('Y-m-d H:i:s'); }
function nowIST():  string { return date('d M Y, h:i A') . ' IST'; }

function isShiftActive(): bool {
    // Check working day first
    $todayDow = (int)date('w'); // 0=Sun
    $workDays = WORKING_DAYS;
    if (!in_array($todayDow, $workDays)) return false;

    $t = date('H:i');
    return ($t >= SHIFT_START && $t <= SHIFT_END);
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function requireAdmin(): void {
    if (!isAdmin()) { header('Location: index.php'); exit; }
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R  = 6371000;
    $dL = deg2rad($lat2 - $lat1);
    $dO = deg2rad($lon2 - $lon1);
    $a  = sin($dL/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dO/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function logActivity(string $empId, string $name, string $event, string $detail): void {
    $logs   = loadJSON('activity_log.json');
    $logs[] = [
        'time'   => nowDT(),
        'ist'    => nowIST(),
        'emp_id' => $empId,
        'name'   => $name,
        'event'  => $event,
        'detail' => $detail
    ];
    saveJSON('activity_log.json', array_slice($logs, -1000));
}
define('TRACKER_PING_INTERVAL', 2); // GPS ping every N minutes
