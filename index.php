<?php
require 'config.php';
if (isAdmin()) { header('Location: admin.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['username']) === ADMIN_USER && password_verify(trim($_POST['password']), ADMIN_PASS_HASH)) {
        $_SESSION['role'] = 'admin';
        header('Location: admin.php'); exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FaceShift  — Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#080a10;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;padding:1rem}
  .wrap{width:100%;max-width:400px}
  .logo-wrap{text-align:center;margin-bottom:2rem}
  .logo-icon{width:60px;height:60px;background:linear-gradient(135deg,#00d4e8,#0077b6);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:.75rem;box-shadow:0 8px 24px rgba(0,212,232,.25)}
  .logo-title{color:#fff;font-size:1.4rem;font-weight:700}
  .logo-sub{color:#6b7a99;font-size:.82rem;margin-top:.2rem}
  .card{background:#0e1220;border:1px solid #1c2235;border-radius:18px;padding:2rem}
  .err{background:rgba(255,71,87,.1);border:1px solid rgba(255,71,87,.25);color:#ff6b7a;border-radius:8px;padding:.65rem .9rem;font-size:.82rem;margin-bottom:1.25rem}
  .field{margin-bottom:1rem}
  label{display:block;color:#8892a4;font-size:.78rem;font-weight:500;margin-bottom:.4rem}
  input{width:100%;background:#080a10;border:1px solid #1c2235;color:#e2e8f8;border-radius:10px;padding:.7rem 1rem;font-size:.9rem;font-family:'Inter',sans-serif;outline:none;transition:border .2s,box-shadow .2s}
  input:focus{border-color:#00d4e8;box-shadow:0 0 0 3px rgba(0,212,232,.1)}
  .btn{width:100%;background:linear-gradient(135deg,#00d4e8,#0077b6);border:none;color:#fff;padding:.8rem;border-radius:10px;font-weight:600;font-size:.95rem;cursor:pointer;font-family:'Inter',sans-serif;margin-top:.5rem;transition:opacity .2s}
  .btn:hover{opacity:.88}
  .divider{border-color:#1c2235;margin:1.5rem 0}
  .links{display:flex;flex-direction:column;gap:.6rem}
  .portal-link{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;background:#080a10;border:1px solid #1c2235;border-radius:10px;text-decoration:none;color:#e2e8f8;font-size:.85rem;transition:border-color .2s}
  .portal-link:hover{border-color:#00d4e8}
  .pl-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
  .pl-text{font-weight:500}
  .pl-sub{color:#6b7a99;font-size:.72rem}
  .pl-arrow{margin-left:auto;color:#6b7a99}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo-wrap">
    <div class="logo-icon">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2">
        <circle cx="12" cy="8" r="4"/>
        <path d="M3 21v-1a9 9 0 0 1 18 0v1"/>
      </svg>
    </div>
    <div class="logo-title">FaceShift </div>
    <div class="logo-sub">AI-Powered Attendance · <?= OFFICE_NAME ?></div>
  </div>

  <div class="card">
    <?php if ($error): ?><div class="err"><?= $error ?></div><?php endif; ?>
    <form method="POST">
      <div class="field"><label>Admin Username</label><input name="username" placeholder="admin" required autocomplete="username"></div>
      <div class="field"><label>Password</label><input type="password" name="password" placeholder="••••••••" required autocomplete="current-password"></div>
      <button class="btn">Login to Admin Panel →</button>
    </form>
    <hr class="divider">
    <div class="links">
      <a href="attendance.php" class="portal-link">
        <div class="pl-icon" style="background:rgba(0,212,232,.1)">📷</div>
        <div><div class="pl-text">Attendance Kiosk</div><div class="pl-sub">Face scan at office entrance</div></div>
        <span class="pl-arrow">›</span>
      </a>
      <a href="tracker.php" class="portal-link">
        <div class="pl-icon" style="background:rgba(0,255,136,.1)">📍</div>
        <div><div class="pl-text">Employee Tracker Portal</div><div class="pl-sub">Open on your phone · GPS tracking</div></div>
        <span class="pl-arrow">›</span>
      </a>
    </div>
  </div>
</div>
</body>
</html>