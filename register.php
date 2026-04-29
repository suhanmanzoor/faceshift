<?php
require 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $dept        = trim($_POST['department'] ?? '');
        $descriptors = json_decode($_POST['descriptors'] ?? '[]', true);
        $photos      = json_decode($_POST['photos'] ?? '[]', true);

        if (!$name || !$email || !$dept || count($descriptors) < 3) {
            echo json_encode(['success' => false, 'msg' => 'Missing fields or face captures']); exit;
        }

        $employees = loadJSON('employees.json');
        $id        = 'EMP' . str_pad(count($employees) + 1, 4, '0', STR_PAD_LEFT);

        // Save photos
        $dir = FACES_DIR . $id . '/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        foreach ($photos as $i => $photo) {
            $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo));
            file_put_contents($dir . "angle_{$i}.jpg", $imgData);
        }

        $employees[$id] = [
            'id'            => $id,
            'name'          => $name,
            'email'         => $email,
            'department'    => $dept,
            'registered_at' => nowDT(),
            'descriptors'   => $descriptors,
            'active'        => true
        ];
        saveJSON('employees.json', $employees);

        $logs = loadJSON('activity_log.json');
        $logs[] = ['time' => nowDT(), 'emp_id' => $id, 'name' => $name, 'event' => 'registered', 'detail' => "Employee registered by admin"];
        saveJSON('activity_log.json', array_slice($logs, -500));

        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'delete') {
        $employees = loadJSON('employees.json');
        $id        = $_POST['id'] ?? '';
        if (isset($employees[$id])) {
            $employees[$id]['active'] = false;
            saveJSON('employees.json', $employees);
        }
        echo json_encode(['success' => true]); exit;
    }
}

$employees = loadJSON('employees.json');
$active    = array_filter($employees, fn($e) => $e['active'] ?? true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register Employee — FaceShift</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#090b11;color:#e0e6ff;font-family:'Inter',sans-serif;display:flex;min-height:100vh}
  .sidebar{width:220px;background:#0f1220;border-right:1px solid #1a2035;padding:1.25rem 1rem;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto}
  .sb-logo{display:flex;align-items:center;gap:.6rem;margin-bottom:2rem}
  .sb-logo-icon{width:32px;height:32px;background:linear-gradient(135deg,#00c6d7,#0077b6);border-radius:8px;display:flex;align-items:center;justify-content:center}
  .sb-logo-text{font-weight:700;color:#fff}
  .nav-lnk{display:flex;align-items:center;gap:.6rem;padding:.5rem .75rem;border-radius:8px;color:#8892a4;text-decoration:none;font-size:.85rem;margin-bottom:.25rem;transition:all .2s}
  .nav-lnk:hover{background:#1a2035;color:#e0e6ff}
  .nav-lnk.active{background:#1a2035;color:#00c6d7}
  .nav-lnk svg{flex-shrink:0}
  .sb-divider{border-color:#1a2035;margin:.75rem 0}
  .main{flex:1;padding:2rem;overflow-y:auto}
  .page-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
  .page-title{font-size:1.25rem;font-weight:700;color:#fff}
  .page-sub{color:#6b7a99;font-size:.85rem}
  .btn-add{background:linear-gradient(135deg,#00c6d7,#0077b6);border:none;color:#fff;padding:.5rem 1.1rem;border-radius:10px;font-weight:600;font-size:.85rem;cursor:pointer;transition:opacity .2s}
  .btn-add:hover{opacity:.85}
  .card{background:#0f1220;border:1px solid #1a2035;border-radius:14px;padding:1.25rem}
  .card-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
  .emp-row{display:flex;align-items:center;gap:.75rem;padding:.65rem .75rem;background:#131726;border:1px solid #1a2035;border-radius:10px;margin-bottom:.5rem}
  .emp-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#00c6d7,#0077b6);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:#fff;flex-shrink:0}
  .emp-name{flex:1;font-weight:500;font-size:.9rem}
  .emp-sub{color:#6b7a99;font-size:.75rem}
  .dept-badge{background:#1a2035;color:#00c6d7;border:1px solid #1e3050;font-size:.7rem;padding:.2rem .55rem;border-radius:20px}
  .btn-del{background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.25);color:#ff6b7a;font-size:.75rem;padding:.25rem .6rem;border-radius:6px;cursor:pointer;transition:all .2s}
  .btn-del:hover{background:rgba(220,53,69,.3)}
  /* Modal */
  .modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;z-index:1000;padding:1rem}
  .modal-bg.show{display:flex}
  .modal-box{background:#0f1220;border:1px solid #1a2035;border-radius:18px;width:100%;max-width:820px;max-height:90vh;overflow-y:auto}
  .modal-hdr{padding:1.25rem 1.5rem;border-bottom:1px solid #1a2035;display:flex;align-items:center;justify-content:space-between}
  .modal-title{font-weight:700;color:#fff;font-size:1rem}
  .modal-close{background:none;border:none;color:#6b7a99;font-size:1.4rem;cursor:pointer;line-height:1;transition:color .2s}
  .modal-close:hover{color:#fff}
  .modal-body{padding:1.5rem}
  .modal-footer{padding:1rem 1.5rem;border-top:1px solid #1a2035;display:flex;justify-content:flex-end;gap:.75rem}
  .form-group{margin-bottom:1rem}
  .form-label{color:#8892a4;font-size:.8rem;margin-bottom:.35rem;display:block}
  .form-input{width:100%;background:#090b11;border:1px solid #1a2035;color:#e0e6ff;border-radius:10px;padding:.6rem .9rem;font-size:.875rem;outline:none;transition:border .2s}
  .form-input:focus{border-color:#00c6d7;box-shadow:0 0 0 3px rgba(0,198,215,.12)}
  .btn-sec{background:#1a2035;border:1px solid #2a3048;color:#8892a4;padding:.5rem 1rem;border-radius:10px;font-size:.85rem;cursor:pointer;transition:all .2s}
  .btn-sec:hover{color:#e0e6ff}
  /* Camera */
  .cam-box{background:#090b11;border:1px solid #1a2035;border-radius:12px;overflow:hidden}
  #regVideo{width:100%;display:block;transform:scaleX(-1);max-height:300px;object-fit:cover}
  .angle-dots{display:flex;gap:.5rem;margin-bottom:.75rem}
  .dot{width:34px;height:34px;border-radius:50%;border:2px solid #1a2035;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#6b7a99;transition:all .3s}
  .dot.active{border-color:#00c6d7;color:#00c6d7}
  .dot.done{background:#198754;border-color:#198754;color:#fff}
  .preview-slot{width:72px;height:72px;background:#090b11;border:1px dashed #1a2035;border-radius:8px;overflow:hidden;flex-shrink:0}
  .preview-slot img{width:100%;height:100%;object-fit:cover}
  .loading-models{text-align:center;padding:2rem;color:#6b7a99;font-size:.85rem}
  #statusMsg{font-size:.82rem;min-height:1.5rem;margin-top:.5rem}
</style>
</head>
<body>

<div class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2"><circle cx="12" cy="8" r="4"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/></svg></div>
    <span class="sb-logo-text">FaceShift</span>
  </div>
  <a class="nav-lnk" href="admin.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a>
  <a class="nav-lnk active" href="register.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg> Employees</a>
  <a class="nav-lnk" href="send_report.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Send Reports</a>
  <hr class="sb-divider">
  <a class="nav-lnk" href="logout.php" style="color:#ff6b7a"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
</div>

<div class="main">
  <div class="page-hdr">
    <div>
      <div class="page-title">Employees</div>
      <div class="page-sub">Register and manage employees for face recognition</div>
    </div>
    <button class="btn-add" onclick="openModal()">+ Add Employee</button>
  </div>

  <div class="card">
    <div class="card-hdr">
      <span style="font-weight:600;color:#fff">All Employees
        <span style="background:#1a2035;color:#6b7a99;font-size:.72rem;padding:.15rem .5rem;border-radius:10px;margin-left:.4rem"><?= count($active) ?></span>
      </span>
    </div>
    <div id="empList">
      <?php foreach ($active as $emp): ?>
      <div class="emp-row" id="emp-<?= $emp['id'] ?>">
        <div class="emp-av"><?= strtoupper(substr($emp['name'],0,2)) ?></div>
        <div style="flex:1">
          <div class="emp-name"><?= htmlspecialchars($emp['name']) ?> <span style="color:#6b7a99;font-size:.75rem"><?= $emp['id'] ?></span></div>
          <div class="emp-sub"><?= htmlspecialchars($emp['email']) ?></div>
        </div>
        <span class="dept-badge"><?= htmlspecialchars($emp['department']) ?></span>
        <button class="btn-del" onclick="deleteEmp('<?= $emp['id'] ?>')">Remove</button>
      </div>
      <?php endforeach; ?>
      <?php if (empty($active)): ?>
      <p style="color:#6b7a99;text-align:center;padding:2rem;font-size:.875rem">No employees yet — click "Add Employee" to register your first one.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Register Modal -->
<div class="modal-bg" id="regModal">
  <div class="modal-box">
    <div class="modal-hdr">
      <span class="modal-title">Register New Employee</span>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <div id="modelsLoader" class="loading-models">
        <div style="width:28px;height:28px;border:3px solid #1a2035;border-top-color:#00c6d7;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto .75rem"></div>
        Loading face recognition models…
      </div>
      <div id="regForm" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem">
          <div class="form-group mb-0"><label class="form-label">Full Name *</label><input id="fName" class="form-input" placeholder="John Doe"></div>
          <div class="form-group mb-0"><label class="form-label">Email *</label><input id="fEmail" type="email" class="form-input" placeholder="john@co.com"></div>
          <div class="form-group mb-0"><label class="form-label">Department *</label><input id="fDept" class="form-input" placeholder="Engineering"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
          <div>
            <div class="cam-box">
              <video id="regVideo" autoplay playsinline muted></video>
              <canvas id="regCanvas" style="display:none"></canvas>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:.75rem">
              <button class="btn-add" id="captureBtn" onclick="captureFrame()" style="flex:1;padding:.5rem">📸 Capture Angle <span id="angNum">1</span>/3</button>
              <button class="btn-sec" onclick="resetFace()">↺ Reset</button>
            </div>
          </div>
          <div>
            <p style="color:#6b7a99;font-size:.8rem;margin-bottom:.75rem">Capture from <strong style="color:#e0e6ff">3 angles</strong>: left, front, right</p>
            <div class="angle-dots">
              <div class="dot active" id="d0">◀</div>
              <div class="dot" id="d1">➤</div>
              <div class="dot" id="d2">▶</div>
              <span style="color:#6b7a99;font-size:.75rem;margin-left:.5rem;align-self:center">Left · Front · Right</span>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:.75rem" id="previews">
              <div class="preview-slot" id="p0"></div>
              <div class="preview-slot" id="p1"></div>
              <div class="preview-slot" id="p2"></div>
            </div>
            <div id="statusMsg" style="color:#8892a4"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-sec" onclick="closeModal()">Cancel</button>
      <button class="btn-add" id="saveBtn" onclick="saveEmployee()" disabled>Save Employee</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
let modelsReady = false, stream = null;
let captured = { descriptors: [], photos: [] };
let angle = 0;

async function loadModels() {
  if (modelsReady) return;
  try {
    await Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
    ]);
    modelsReady = true;
    document.getElementById('modelsLoader').style.display = 'none';
    document.getElementById('regForm').style.display     = 'block';
    startRegCam();
  } catch(e) {
    document.getElementById('modelsLoader').innerHTML = '<span style="color:#ff6b7a">Failed to load AI models. Check network or /models/ folder.</span>';
  }
}

async function startRegCam() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640 } });
    document.getElementById('regVideo').srcObject = stream;
  } catch(e) { setMsg('Camera access denied.', 'danger'); }
}

async function openModal() {
  document.getElementById('regModal').classList.add('show');
  loadModels();
}

function closeModal() {
  document.getElementById('regModal').classList.remove('show');
  if (stream) stream.getTracks().forEach(t => t.stop());
  resetFace();
  ['fName','fEmail','fDept'].forEach(id => document.getElementById(id).value = '');
}

async function captureFrame() {
  if (!modelsReady) return;
  const video = document.getElementById('regVideo');
  const canvas = document.getElementById('regCanvas');
  canvas.width = video.videoWidth; canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);

  setMsg('Processing…', 'info');
  const det = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
  if (!det) { setMsg('⚠️ No face detected. Adjust lighting and position.', 'warn'); return; }

  const desc  = Array.from(det.descriptor);
  const photo = canvas.toDataURL('image/jpeg', 0.85);
  captured.descriptors.push(desc);
  captured.photos.push(photo);

  document.getElementById('p' + angle).innerHTML = `<img src="${photo}">`;
  document.getElementById('d' + angle).className = 'dot done';
  document.getElementById('d' + angle).textContent = '✓';
  angle++;

  if (angle < 3) {
    document.getElementById('d' + angle).className = 'dot active';
    document.getElementById('angNum').textContent = angle + 1;
    setMsg(['✓ Left captured! Now face forward.','✓ Front captured! Now turn right.'][angle-1], 'success');
  } else {
    document.getElementById('captureBtn').disabled = true;
    document.getElementById('captureBtn').textContent = '✓ All 3 angles captured';
    document.getElementById('saveBtn').disabled = false;
    setMsg('✅ All angles captured! Fill details and save.', 'success');
  }
}

function resetFace() {
  captured = { descriptors: [], photos: [] };
  angle = 0;
  [0,1,2].forEach(i => {
    document.getElementById('d'+i).className = i===0 ? 'dot active' : 'dot';
    document.getElementById('d'+i).textContent = ['◀','➤','▶'][i];
    document.getElementById('p'+i).innerHTML = '';
  });
  document.getElementById('captureBtn').disabled = false;
  document.getElementById('captureBtn').innerHTML = '📸 Capture Angle <span id="angNum">1</span>/3';
  document.getElementById('saveBtn').disabled = true;
  setMsg('','');
}

async function saveEmployee() {
  const name  = document.getElementById('fName').value.trim();
  const email = document.getElementById('fEmail').value.trim();
  const dept  = document.getElementById('fDept').value.trim();
  if (!name || !email || !dept) { setMsg('Fill all fields.', 'warn'); return; }
  if (captured.descriptors.length < 3) { setMsg('Capture all 3 angles first.', 'warn'); return; }

  document.getElementById('saveBtn').disabled = true;
  document.getElementById('saveBtn').textContent = 'Saving…';

  const fd = new FormData();
  fd.append('action', 'register');
  fd.append('name', name); fd.append('email', email); fd.append('department', dept);
  fd.append('descriptors', JSON.stringify(captured.descriptors));
  fd.append('photos', JSON.stringify(captured.photos));

  const res  = await fetch('register.php', { method: 'POST', body: fd });
  const data = await res.json();

  if (data.success) {
    document.getElementById('empList').insertAdjacentHTML('afterbegin', `
      <div class="emp-row" id="emp-${data.id}">
        <div class="emp-av">${name.substring(0,2).toUpperCase()}</div>
        <div style="flex:1">
          <div class="emp-name">${name} <span style="color:#6b7a99;font-size:.75rem">${data.id}</span></div>
          <div class="emp-sub">${email}</div>
        </div>
        <span class="dept-badge">${dept}</span>
        <button class="btn-del" onclick="deleteEmp('${data.id}')">Remove</button>
      </div>`);
    closeModal();
  } else {
    setMsg(data.msg || 'Save failed.', 'danger');
    document.getElementById('saveBtn').disabled = false;
    document.getElementById('saveBtn').textContent = 'Save Employee';
  }
}

async function deleteEmp(id) {
  if (!confirm('Remove this employee from the system?')) return;
  const fd = new FormData();
  fd.append('action', 'delete'); fd.append('id', id);
  const res = await fetch('register.php', { method: 'POST', body: fd });
  const d   = await res.json();
  if (d.success) document.getElementById('emp-'+id)?.remove();
}

function setMsg(msg, type) {
  const c = { info:'#00c6d7', success:'#00ff88', warn:'#ffd700', danger:'#ff6b7a' };
  document.getElementById('statusMsg').innerHTML = msg
    ? `<span style="color:${c[type]||'#8892a4'}">${msg}</span>` : '';
}
</script>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>
</body>
</html>