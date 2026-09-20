<?php
require_once __DIR__ . '/../includes/auth_helper.php';
requireRole('doctor','../index.php');
require_once __DIR__ . '/../config/db.php';
$user = currentUser();
$uid  = (int)$user['id'];

// Doctor profile
$profile = $conn->query("SELECT u.*,dp.* FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id WHERE u.id=$uid")->fetch_assoc();

// Stats
$s_pend = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='pending'")->fetch_assoc()['c'];
$s_conf = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='confirmed'")->fetch_assoc()['c'];
$s_comp = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='completed'")->fetch_assoc()['c'];
$s_total= $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid")->fetch_assoc()['c'];

// Pending appointments
$pending = [];
$r = $conn->query("SELECT a.*,u.full_name pat_name,u.email pat_email,u.phone pat_phone,u.gender pat_gender
  FROM appointments a JOIN users u ON a.patient_id=u.id
  WHERE a.doctor_id=$uid AND a.status='pending'
  ORDER BY a.appointment_date,a.appointment_time");
while($row=$r->fetch_assoc()) $pending[]=$row;

// Confirmed appointments
$confirmed = [];
$r = $conn->query("SELECT a.*,u.full_name pat_name,u.email pat_email,u.phone pat_phone
  FROM appointments a JOIN users u ON a.patient_id=u.id
  WHERE a.doctor_id=$uid AND a.status='confirmed'
  ORDER BY a.appointment_date,a.appointment_time");
while($row=$r->fetch_assoc()) $confirmed[]=$row;

// Today's appointments
$today_appts = [];
$today = date('Y-m-d');
$r = $conn->query("SELECT a.*,u.full_name pat_name FROM appointments a JOIN users u ON a.patient_id=u.id
  WHERE a.doctor_id=$uid AND a.appointment_date='$today' AND a.status IN('pending','confirmed')
  ORDER BY a.appointment_time");
while($row=$r->fetch_assoc()) $today_appts[]=$row;

// All appointments (history)
$all_appts = [];
$r = $conn->query("SELECT a.*,u.full_name pat_name FROM appointments a JOIN users u ON a.patient_id=u.id
  WHERE a.doctor_id=$uid ORDER BY a.appointment_date DESC,a.appointment_time DESC LIMIT 100");
while($row=$r->fetch_assoc()) $all_appts[]=$row;

// Prescriptions uploaded
$my_prescriptions = [];
$r = $conn->query("SELECT p.*,u.full_name pat_name,a.appointment_date
  FROM prescriptions p JOIN users u ON p.patient_id=u.id
  LEFT JOIN appointments a ON p.appointment_id=a.id
  WHERE p.doctor_id=$uid ORDER BY p.uploaded_at DESC");
while($row=$r->fetch_assoc()) $my_prescriptions[]=$row;

// Notifications
$notifs=[];
$r=$conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 12");
while($row=$r->fetch_assoc()) $notifs[]=$row;
$unread=$conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];

// Patients list (distinct patients who had appointments)
$my_patients=[];
$r=$conn->query("SELECT DISTINCT u.id,u.full_name,u.email,u.phone,u.gender,
  COUNT(a.id) total_appts, MAX(a.appointment_date) last_visit
  FROM appointments a JOIN users u ON a.patient_id=u.id
  WHERE a.doctor_id=$uid GROUP BY u.id ORDER BY last_visit DESC");
while($row=$r->fetch_assoc()) $my_patients[]=$row;

$specs=['General Medicine','Cardiology','Dermatology','Neurology','Orthopedics','Pediatrics',
  'Psychiatry','Gynecology','Ophthalmology','ENT','Oncology','Endocrinology','Nephrology','Gastroenterology','Pulmonology','Urology'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Doctor Dashboard — MediCare</title>
<link rel="stylesheet" href="../assets/css/dashboard.css"/>
<style>
  .sidebar{background:#064e3b}
  .sb-role{color:rgba(255,255,255,.4)}
  .nav-item.active{background:rgba(255,255,255,.14)}
  .nav-item:hover{background:rgba(255,255,255,.08)}
  .sidebar-section{color:rgba(255,255,255,.3)}
  .btn-primary{background:linear-gradient(135deg,#064e3b,#059669)}
  .topbar-title{color:#064e3b}
  .page-header h1{color:#064e3b}
  .stat-ico.ico-teal{background:#ccfbf1}
  .appt-card{background:linear-gradient(135deg,#064e3b,#047857)}
  .pat-card{background:var(--white);border:1.5px solid var(--g200);border-radius:var(--radius);padding:16px 18px;transition:var(--trans)}
  .pat-card:hover{border-color:#059669;transform:translateY(-1px);box-shadow:var(--shadow-md)}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sb-icon">🏥</div>
    <div><div class="sb-name">MediCare</div><div class="sb-role">Doctor Portal</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">Overview</div>
    <button class="nav-item active" id="nav-dashboard" onclick="showPage('dashboard')"><span class="ni">🏠</span>Dashboard</button>
    <button class="nav-item" id="nav-appointments" onclick="showPage('appointments')">
      <span class="ni">📋</span>Appointments<?php if($s_pend>0):?><span class="nav-count"><?=$s_pend?></span><?php endif?>
    </button>
    <button class="nav-item" id="nav-today" onclick="showPage('today')">
      <span class="ni">📅</span>Today's Schedule<?php if(count($today_appts)>0):?><span class="nav-count"><?=count($today_appts)?></span><?php endif?>
    </button>
    <div class="sidebar-section">Patients</div>
    <button class="nav-item" id="nav-patients" onclick="showPage('patients')"><span class="ni">👥</span>My Patients</button>
    <button class="nav-item" id="nav-prescriptions" onclick="showPage('prescriptions')"><span class="ni">💊</span>Prescriptions</button>
    <button class="nav-item" id="nav-history" onclick="showPage('history')"><span class="ni">🕐</span>History</button>
    <div class="sidebar-section">Account</div>
    <button class="nav-item" id="nav-profile" onclick="showPage('profile')"><span class="ni">👤</span>My Profile</button>
    <button class="nav-item" id="nav-schedule" onclick="showPage('schedule')"><span class="ni">⏰</span>Set Availability</button>
  </nav>
  <div class="sidebar-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?=strtoupper(substr($user['name'],0,1))?></div>
      <div><div class="sb-uname">Dr. <?=htmlspecialchars($user['name'])?></div>
      <div class="sb-uemail"><?=htmlspecialchars($profile['specialization']??'')?></div></div>
    </div>
    <a href="../auth/logout.php" class="nav-item" style="color:rgba(255,100,100,.75);margin-top:3px"><span class="ni">🚪</span>Sign out</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
<header class="topbar">
  <div style="display:flex;align-items:center;gap:10px">
    <button class="tb-btn" id="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none">☰</button>
    <span class="topbar-title" id="topbar-title">Dashboard</span>
  </div>
  <div class="topbar-right">
    <div style="position:relative">
      <button class="tb-btn" id="notif-btn" onclick="toggleNotif()">🔔<?php if($unread>0):?><span class="nb"></span><?php endif?></button>
      <div class="notif-dropdown hidden" id="notif-drop">
        <div style="padding:11px 15px;border-bottom:1px solid var(--g100);display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:700;font-size:13.5px">Notifications</span>
          <button onclick="markAllRead()" style="font-size:12px;color:var(--blue);background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif">Mark all read</button>
        </div>
        <?php if(empty($notifs)):?>
        <div style="padding:24px;text-align:center;color:var(--g400);font-size:13px">No notifications</div>
        <?php else: foreach($notifs as $n):?>
        <div class="notif-item <?=$n['is_read']?'':'unread'?>">
          <?php if(!$n['is_read']):?><div class="notif-dot"></div><?php endif?>
          <div><div class="notif-text"><strong><?=htmlspecialchars($n['title'])?></strong><br><?=htmlspecialchars($n['message'])?></div>
          <div class="notif-time"><?=date('M j, g:i a',strtotime($n['created_at']))?></div></div>
        </div>
        <?php endforeach; endif?>
      </div>
    </div>
    <span style="font-size:13px;color:var(--g500)">Dr. <strong style="color:#064e3b"><?=explode(' ',$user['name'])[0]?></strong></span>
  </div>
</header>

<div class="page-content">

<!-- DASHBOARD -->
<div class="page active" id="page-dashboard">
  <div class="page-header">
    <h1>Good <?=date('H')<12?'morning':( date('H')<17?'afternoon':'evening')?>, Dr. <?=explode(' ',$user['name'])[0]?>! 👋</h1>
    <p><?=date('l, F j, Y')?></p>
  </div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-ico ico-amber">🕐</div><div><div class="stat-num" id="dst-pending"><?=$s_pend?></div><div class="stat-lbl">Pending</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-green">✅</div><div><div class="stat-num" id="dst-confirmed"><?=$s_conf?></div><div class="stat-lbl">Confirmed</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-blue">🏁</div><div><div class="stat-num" id="dst-completed"><?=$s_comp?></div><div class="stat-lbl">Completed</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-teal">👥</div><div><div class="stat-num" id="dst-patients"><?=count($my_patients)?></div><div class="stat-lbl">Total Patients</div></div></div>
  </div>

  <!-- Today -->
  <div class="card">
    <div class="card-header"><span class="card-title">📅 Today's Appointments — <?=date('M j, Y')?></span>
      <button class="btn btn-primary btn-sm" onclick="showPage('today')">View All</button></div>
    <div class="card-body" id="d-today-dash">
      <?php if(empty($today_appts)):?>
      <div class="empty-state"><div class="es-icon">🌟</div><h4>No appointments today</h4><p>Enjoy your day!</p></div>
      <?php else: foreach($today_appts as $a):?>
      <div class="appt-card">
        <div>
          <div class="ac-doc"><?=htmlspecialchars($a['pat_name'])?></div>
          <div class="ac-spec" style="opacity:.7;font-size:12px;margin-bottom:8px"><?=htmlspecialchars($a['reason']??'No reason stated')?></div>
          <div class="ac-meta"><span>🕐 <?=date('g:i A',strtotime($a['appointment_time']))?></span></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
          <span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span>
          <?php if($a['status']==='pending'):?>
          <button class="cancel-btn" onclick="confirmAppt(<?=$a['id']?>)" style="background:rgba(22,163,74,.3)">✓ Confirm</button>
          <?php endif?>
          <button class="cancel-btn" onclick="openUpload(<?=$a['id']?>,<?=$a['patient_id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')">💊 Prescription</button>
        </div>
      </div>
      <?php endforeach; endif?>
    </div>
  </div>

  <!-- Pending -->
  <div class="card">
    <div class="card-header"><span class="card-title">🕐 Pending Appointments</span></div>
    <div class="card-body" id="d-pend-dash" style="padding:0">
      <?php if(empty($pending)):?>
      <div class="empty-state"><div class="es-icon">✅</div><h4>No pending appointments</h4></div>
      <?php else:?>
      <div class="table-wrap"><table>
        <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($pending as $a):?>
        <tr>
          <td><strong><?=htmlspecialchars($a['pat_name'])?></strong><br><span style="font-size:11px;color:var(--g400)"><?=htmlspecialchars($a['pat_phone']??'')?></span></td>
          <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
          <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['reason']??'—')?></td>
          <td style="display:flex;gap:5px;flex-wrap:wrap">
            <button class="btn btn-success btn-sm" onclick="confirmAppt(<?=$a['id']?>)">✓ Confirm</button>
            <button class="btn btn-primary btn-sm" onclick="openUpload(<?=$a['id']?>,<?=$a['patient_id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')">💊 Rx</button>
            <button class="btn btn-ghost btn-sm" onclick="completeAppt(<?=$a['id']?>)">Complete</button>
          </td>
        </tr>
        <?php endforeach?>
        </tbody>
      </table></div>
      <?php endif?>
    </div>
  </div>
</div>

<!-- APPOINTMENTS PAGE -->
<div class="page" id="page-appointments">
  <div class="page-header"><h1>All Appointments</h1><p>Manage all patient appointments</p></div>
  <div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('tab-pend',this)">Pending <span style="background:#fef3c7;color:#92400e;padding:1px 7px;border-radius:99px;font-size:11px;margin-left:4px"><?=$s_pend?></span></button>
    <button class="tab-btn" onclick="switchTab('tab-conf',this)">Confirmed <span style="background:#dcfce7;color:#166534;padding:1px 7px;border-radius:99px;font-size:11px;margin-left:4px"><?=$s_conf?></span></button>
  </div>
  <div id="tab-pend" class="tab-panel active">
    <div class="card"><div class="card-body" id="d-tab-pend" style="padding:0">
      <?php if(empty($pending)):?>
      <div class="empty-state"><div class="es-icon">✅</div><h4>No pending appointments</h4></div>
      <?php else:?>
      <div class="table-wrap"><table>
        <thead><tr><th>Patient</th><th>Contact</th><th>Date</th><th>Time</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($pending as $a):?>
        <tr>
          <td><strong><?=htmlspecialchars($a['pat_name'])?></strong></td>
          <td><span style="font-size:12px"><?=htmlspecialchars($a['pat_email']??'')?><br><?=htmlspecialchars($a['pat_phone']??'')?></span></td>
          <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
          <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
          <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['reason']??'—')?></td>
          <td><div style="display:flex;gap:5px;flex-wrap:wrap">
            <button class="btn btn-success btn-sm" onclick="confirmAppt(<?=$a['id']?>)">✓ Confirm</button>
            <button class="btn btn-primary btn-sm" onclick="openUpload(<?=$a['id']?>,<?=$a['patient_id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')">💊 Rx</button>
            <button class="btn btn-ghost btn-sm" onclick="completeAppt(<?=$a['id']?>)">✓ Complete</button>
            <button class="btn btn-ghost btn-sm" onclick="viewIntake(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')" style="border-color:#7c3aed;color:#7c3aed">📋 Intake</button>
          </div></td>
        </tr>
        <?php endforeach?>
        </tbody>
      </table></div>
      <?php endif?>
    </div></div>
  </div>
  <div id="tab-conf" class="tab-panel">
    <div class="card"><div class="card-body" id="d-tab-conf" style="padding:0">
      <?php if(empty($confirmed)):?>
      <div class="empty-state"><div class="es-icon">📅</div><h4>No confirmed appointments</h4></div>
      <?php else:?>
      <div class="table-wrap"><table>
        <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($confirmed as $a):?>
        <tr>
          <td><strong><?=htmlspecialchars($a['pat_name'])?></strong></td>
          <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
          <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
          <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['reason']??'—')?></td>
          <td><div style="display:flex;gap:5px;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm" onclick="openUpload(<?=$a['id']?>,<?=$a['patient_id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')">💊 Upload Rx</button>
            <button class="btn btn-success btn-sm" onclick="completeAppt(<?=$a['id']?>)">✓ Mark Complete</button>
            <button class="btn btn-ghost btn-sm" onclick="viewIntake(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')" style="border-color:#7c3aed;color:#7c3aed">📋 Intake</button>
          </div></td>
        </tr>
        <?php endforeach?>
        </tbody>
      </table></div>
      <?php endif?>
    </div></div>
  </div>
</div>

<!-- TODAY -->
<div class="page" id="page-today">
  <div class="page-header"><h1>Today's Schedule 📅</h1><p><?=date('l, F j, Y')?></p></div>
  <div class="card"><div class="card-body" id="d-today-page">
    <?php if(empty($today_appts)):?>
    <div class="empty-state"><div class="es-icon">🌟</div><h4>No appointments today</h4><p>Your schedule is free!</p></div>
    <?php else:?>
    <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach($today_appts as $i=>$a):?>
    <div style="display:flex;gap:16px;align-items:flex-start;padding:16px;background:var(--g100);border-radius:var(--radius-sm);border-left:4px solid <?=$a['status']==='confirmed'?'#16a34a':'#d97706'?>">
      <div style="font-size:22px;font-weight:700;color:var(--g400);min-width:30px;text-align:center"><?=$i+1?></div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:15px;color:var(--g800)"><?=htmlspecialchars($a['pat_name'])?></div>
        <div style="font-size:13px;color:var(--g500);margin-top:3px">🕐 <?=date('g:i A',strtotime($a['appointment_time']))?></div>
        <?php if($a['reason']):?><div style="font-size:13px;color:var(--g600);margin-top:4px">📝 <?=htmlspecialchars($a['reason'])?></div><?php endif?>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span>
        <button class="btn btn-primary btn-sm" onclick="openUpload(<?=$a['id']?>,<?=$a['patient_id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')">💊 Rx</button>
        <?php if($a['status']==='pending'):?><button class="btn btn-success btn-sm" onclick="confirmAppt(<?=$a['id']?>)">✓ Confirm</button><?php endif?>
        <button class="btn btn-ghost btn-sm" onclick="completeAppt(<?=$a['id']?>)">Complete</button>
        <button class="btn btn-ghost btn-sm" onclick="viewIntake(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($a['pat_name']))?>')" style="border-color:#7c3aed;color:#7c3aed">📋 Intake</button>
      </div>
    </div>
    <?php endforeach?>
    </div>
    <?php endif?>
  </div></div>
</div>

<!-- PATIENTS -->
<div class="page" id="page-patients">
  <div class="page-header"><h1>My Patients 👥</h1><p>All patients you've seen or have appointments with</p></div>
  <div class="card"><div class="card-body" id="d-patients" style="padding:0">
    <?php if(empty($my_patients)):?>
    <div class="empty-state"><div class="es-icon">👥</div><h4>No patients yet</h4></div>
    <?php else:?>
    <div class="table-wrap"><table>
      <thead><tr><th>Patient</th><th>Contact</th><th>Gender</th><th>Total Visits</th><th>Last Visit</th></tr></thead>
      <tbody>
      <?php foreach($my_patients as $p):?>
      <tr>
        <td><strong><?=htmlspecialchars($p['full_name'])?></strong></td>
        <td><span style="font-size:12px"><?=htmlspecialchars($p['email'])?><br><?=htmlspecialchars($p['phone']??'')?></span></td>
        <td><?=ucfirst($p['gender']??'—')?></td>
        <td><?=$p['total_appts']?></td>
        <td><?=$p['last_visit']?date('M j, Y',strtotime($p['last_visit'])):'—'?></td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table></div>
    <?php endif?>
  </div></div>
</div>

<!-- PRESCRIPTIONS -->
<div class="page" id="page-prescriptions">
  <div class="page-header"><h1>Prescriptions 💊</h1><p>All prescriptions you've uploaded</p></div>
  <div style="margin-bottom:14px"><button class="btn btn-primary" onclick="showPage('appointments')">+ Upload New Prescription</button></div>
  <div class="card"><div class="card-body" id="d-prescriptions">
    <?php if(empty($my_prescriptions)):?>
    <div class="empty-state"><div class="es-icon">💊</div><h4>No prescriptions uploaded yet</h4><p>Upload prescriptions from the Appointments page.</p></div>
    <?php else: foreach($my_prescriptions as $p):?>
    <div class="file-card">
      <div class="file-icon"><?=$p['file_type']==='pdf'?'📄':'🖼️'?></div>
      <div class="file-info">
        <div class="file-name">Prescription for <?=htmlspecialchars($p['pat_name'])?></div>
        <div class="file-meta">Visit: <?=date('M j, Y',strtotime($p['appointment_date']))?> &nbsp;·&nbsp; Uploaded <?=date('M j, Y',strtotime($p['uploaded_at']))?></div>
        <?php if($p['notes']):?><div style="font-size:12px;color:var(--g500);margin-top:3px">📝 <?=htmlspecialchars($p['notes'])?></div><?php endif?>
      </div>
      <?php if($p['file_path']):?>
      <a href="../uploads/prescriptions/<?=htmlspecialchars($p['file_path'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a>
      <?php endif?>
    </div>
    <?php endforeach; endif?>
  </div></div>
</div>

<!-- HISTORY -->
<div class="page" id="page-history">
  <div class="page-header"><h1>Appointment History 🕐</h1></div>
  <div class="card"><div class="card-body" id="d-history" style="padding:0">
    <?php if(empty($all_appts)):?>
    <div class="empty-state"><div class="es-icon">🕐</div><h4>No history yet</h4></div>
    <?php else:?>
    <div class="table-wrap"><table>
      <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Reason</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($all_appts as $a):?>
      <tr>
        <td><strong><?=htmlspecialchars($a['pat_name'])?></strong></td>
        <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
        <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['reason']??'—')?></td>
        <td><span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table></div>
    <?php endif?>
  </div></div>
</div>

<!-- PROFILE -->
<div class="page" id="page-profile">
  <div class="page-header"><h1>My Profile 👤</h1></div>
  <div class="card" style="max-width:580px"><div class="card-body">
    <div id="profile-alert"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" class="form-control" id="p-name" value="<?=htmlspecialchars($profile['full_name'])?>"/></div>
      <div class="form-group"><label class="form-label">Email (read-only)</label><input type="email" class="form-control" value="<?=htmlspecialchars($profile['email'])?>" disabled style="background:var(--g100)"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" class="form-control" id="p-phone" value="<?=htmlspecialchars($profile['phone']??'')?>"/></div>
      <div class="form-group"><label class="form-label">Qualification</label><input type="text" class="form-control" id="p-qual" value="<?=htmlspecialchars($profile['qualification']??'')?>"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Specialization</label>
        <select class="form-control" id="p-spec">
          <?php foreach($specs as $s):?><option value="<?=$s?>" <?=($profile['specialization']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach?>
        </select></div>
      <div class="form-group"><label class="form-label">Experience (years)</label><input type="number" class="form-control" id="p-exp" value="<?=(int)($profile['experience_years']??0)?>"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Consultation Fee (₹)</label><input type="number" class="form-control" id="p-fee" value="<?=(float)($profile['consultation_fee']??0)?>"/></div>
      <div class="form-group"><label class="form-label">Bio</label><textarea class="form-control" id="p-bio"><?=htmlspecialchars($profile['bio']??'')?></textarea></div>
    </div>
    <button class="btn btn-primary" onclick="saveProfile()">💾 Save Changes</button>
  </div></div>
</div>

<!-- SCHEDULE / AVAILABILITY -->
<div class="page" id="page-schedule">
  <div class="page-header"><h1>Set Availability ⏰</h1><p>Configure your working days and appointment slot timings</p></div>
  <div class="card" style="max-width:500px"><div class="card-body">
    <div id="schedule-alert"></div>
    <div class="form-group">
      <label class="form-label">Available Days</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px" id="days-picker">
        <?php $avDays=array_map('trim',explode(',', $profile['available_days']??'Mon,Tue,Wed,Thu,Fri'));
        foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d):?>
        <button type="button" class="slot <?=in_array($d,$avDays)?'selected':''?>" onclick="toggleDay(this,'<?=$d?>')"><?=$d?></button>
        <?php endforeach?>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Start Time</label>
        <input type="time" class="form-control" id="slot-start" value="<?=substr($profile['slot_start']??'09:00:00',0,5)?>"/></div>
      <div class="form-group"><label class="form-label">End Time</label>
        <input type="time" class="form-control" id="slot-end" value="<?=substr($profile['slot_end']??'17:00:00',0,5)?>"/></div>
    </div>
    <div class="form-group"><label class="form-label">Slot Duration (minutes)</label>
      <select class="form-control" id="slot-dur">
        <?php foreach([15,20,30,45,60] as $m):?>
        <option value="<?=$m?>" <?=($profile['slot_duration']??30)==$m?'selected':''?>><?=$m?> minutes</option>
        <?php endforeach?>
      </select></div>
    <div class="form-group"><label class="form-label" style="margin-bottom:8px">Accepting Appointments</label>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" id="is-available" <?=($profile['is_available']??1)?'checked':''?> style="width:16px;height:16px"/>
        <span style="font-size:13.5px;color:var(--g600)">Yes, I am currently accepting appointments</span>
      </label></div>
    <button class="btn btn-primary" onclick="saveSchedule()">💾 Save Schedule</button>
  </div></div>
</div>

</div></div></div>

<!-- PRESCRIPTION UPLOAD MODAL -->
<div class="modal-overlay hidden" id="modal-upload">
  <div class="modal modal-lg">
    <div class="modal-header"><h3>💊 Upload Prescription</h3><button class="modal-close" onclick="closeM('modal-upload')">✕</button></div>
    <div class="modal-body">
      <div id="upload-alert"></div>
      <p id="upload-for" style="color:var(--g500);font-size:13.5px;margin-bottom:16px"></p>
      <input type="hidden" id="upload-appt-id"/>
      <input type="hidden" id="upload-pat-id"/>
      <div class="form-group"><label class="form-label">Prescription File (PDF or Image)</label>
        <input type="file" class="form-control" id="rx-file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" style="height:auto;padding:8px"/></div>
      <div class="form-group"><label class="form-label">Notes / Instructions for Patient</label>
        <textarea class="form-control" id="rx-notes" placeholder="e.g. Take twice daily after meals for 5 days. Rest well and drink plenty of water."></textarea></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeM('modal-upload')">Cancel</button>
      <button class="btn btn-primary" id="upload-btn" onclick="uploadPrescription()">📤 Upload Prescription</button>
    </div>
  </div>
</div>

<!-- CONFIRM MODAL -->
<div class="modal-overlay hidden" id="modal-confirm">
  <div class="modal">
    <div class="modal-header"><h3>Confirm Appointment</h3><button class="modal-close" onclick="closeM('modal-confirm')">✕</button></div>
    <div class="modal-body"><p style="color:var(--g600);font-size:14.5px">Confirm this appointment and notify the patient?</p><input type="hidden" id="confirm-appt-id"/></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeM('modal-confirm')">Cancel</button>
      <button class="btn btn-success" id="confirm-btn" onclick="doConfirm()">✓ Yes, Confirm</button>
    </div>
  </div>
</div>

<script>
const PAGE_TITLES={dashboard:'Dashboard',appointments:'Appointments',today:"Today's Schedule",
  patients:'My Patients',prescriptions:'Prescriptions',history:'History',profile:'My Profile',schedule:'Set Availability'};
function showPage(n){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(i=>i.classList.remove('active'));
  document.getElementById('page-'+n)?.classList.add('active');
  document.getElementById('nav-'+n)?.classList.add('active');
  document.getElementById('topbar-title').textContent=PAGE_TITLES[n]||'';
  document.getElementById('notif-drop').classList.add('hidden');
}
function toggleNotif(){document.getElementById('notif-drop').classList.toggle('hidden')}
document.addEventListener('click',e=>{
  if(!e.target.closest('#notif-btn')&&!e.target.closest('#notif-drop'))
    document.getElementById('notif-drop').classList.add('hidden');
});
function markAllRead(){
  fetch('api/notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_all'})});
  document.querySelectorAll('.notif-item').forEach(el=>el.classList.remove('unread'));
  document.querySelectorAll('.notif-dot').forEach(el=>el.remove());
  document.querySelector('.nb')?.remove();
}
function openM(id){document.getElementById(id).classList.remove('hidden')}
function closeM(id){document.getElementById(id).classList.add('hidden')}
function switchTab(tabId,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

// Confirm appointment
function confirmAppt(id){document.getElementById('confirm-appt-id').value=id;openM('modal-confirm')}
async function doConfirm(){
  const id=document.getElementById('confirm-appt-id').value;
  const btn=document.getElementById('confirm-btn');
  btn.disabled=true;btn.innerHTML='<div class="spinner"></div>';
  const res=await fetch('api/update_appointment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({appointment_id:id,status:'confirmed'})});
  const data=await res.json();
  closeM('modal-confirm');
  if(data.success) location.reload();
  else alert(data.message||'Failed');
  btn.disabled=false;btn.innerHTML='✓ Yes, Confirm';
}

// Mark complete
async function completeAppt(id){
  if(!confirm('Mark this appointment as completed?')) return;
  const res=await fetch('api/update_appointment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({appointment_id:id,status:'completed'})});
  const data=await res.json();
  if(data.success) location.reload();
  else alert(data.message||'Failed');
}

// Upload prescription
function openUpload(apptId,patId,patName){
  document.getElementById('upload-appt-id').value=apptId;
  document.getElementById('upload-pat-id').value=patId;
  document.getElementById('upload-for').textContent='Uploading prescription for: '+patName;
  document.getElementById('rx-file').value='';
  document.getElementById('rx-notes').value='';
  document.getElementById('upload-alert').innerHTML='';
  openM('modal-upload');
}
async function uploadPrescription(){
  const file=document.getElementById('rx-file').files[0];
  const notes=document.getElementById('rx-notes').value.trim();
  const apptId=document.getElementById('upload-appt-id').value;
  const patId=document.getElementById('upload-pat-id').value;
  if(!file){showAlert('upload-alert','Please select a file.','error');return;}
  const maxMB=10;
  if(file.size>maxMB*1024*1024){showAlert('upload-alert',`File too large. Max ${maxMB}MB.`,'error');return;}
  const btn=document.getElementById('upload-btn');
  btn.disabled=true;btn.innerHTML='<div class="spinner"></div> Uploading…';
  const fd=new FormData();
  fd.append('file',file);fd.append('appointment_id',apptId);
  fd.append('patient_id',patId);fd.append('notes',notes);
  try{
    const res=await fetch('api/upload_prescription.php',{method:'POST',body:fd});
    const data=await res.json();
    if(data.success){showAlert('upload-alert','✅ Prescription uploaded! Patient has been notified.','success');setTimeout(()=>{closeM('modal-upload');location.reload();},2000);}
    else showAlert('upload-alert',data.message||'Upload failed.','error');
  }catch(e){showAlert('upload-alert','Server error.','error');}
  btn.disabled=false;btn.innerHTML='📤 Upload Prescription';
}

// Profile save
async function saveProfile(){
  const payload={full_name:document.getElementById('p-name').value.trim(),
    phone:document.getElementById('p-phone').value.trim(),
    qualification:document.getElementById('p-qual').value.trim(),
    specialization:document.getElementById('p-spec').value,
    experience:document.getElementById('p-exp').value,
    fee:document.getElementById('p-fee').value,
    bio:document.getElementById('p-bio').value.trim()};
  const res=await fetch('api/update_profile.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await res.json();
  showAlert('profile-alert',data.message||(data.success?'Saved!':'Failed'),data.success?'success':'error');
}

// Schedule
let selDays=<?=json_encode(array_map('trim',explode(',',$profile['available_days']??'Mon,Tue,Wed,Thu,Fri')))?>;
function toggleDay(btn,day){
  if(selDays.includes(day)){selDays=selDays.filter(d=>d!==day);btn.classList.remove('selected');}
  else{selDays.push(day);btn.classList.add('selected');}
}
async function saveSchedule(){
  const payload={available_days:selDays.join(','),
    slot_start:document.getElementById('slot-start').value+':00',
    slot_end:document.getElementById('slot-end').value+':00',
    slot_duration:document.getElementById('slot-dur').value,
    is_available:document.getElementById('is-available').checked?1:0};
  const res=await fetch('api/update_schedule.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await res.json();
  showAlert('schedule-alert',data.message||(data.success?'Schedule saved!':'Failed'),data.success?'success':'error');
}

function showAlert(id,msg,type){
  const el=document.getElementById(id);if(!el)return;
  el.innerHTML=`<div class="alert alert-${type}"><span>${type==='success'?'✅':'⚠️'}</span><span>${msg}</span></div>`;
  setTimeout(()=>{if(el)el.innerHTML=''},5000);
}
if(window.innerWidth<=768) document.getElementById('menu-btn').style.display='flex';

// ═══════════════════════════════════════════════════════════════
//  LIVE POLLING — doctor dashboard, every 5 seconds
// ═══════════════════════════════════════════════════════════════
function dfmtDate(d){ if(!d) return '—'; const iso=d.includes('T')?d:d.includes(' ')?d.replace(' ','T'):d+'T00:00:00'; return new Date(iso).toLocaleDateString('en-IN',{month:'short',day:'numeric',year:'numeric'}); }
function dfmtTime(t){ if(!t) return '—'; const[h,m]=t.split(':'); const ap=h>=12?'PM':'AM'; return `${h%12||12}:${m} ${ap}`; }
function dbadge(s){ return `<span class="badge badge-${s}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`; }
function dActionBtns(a){
  let btns='';
  const pn=(a.patient_name||'').replace(/'/g,"\\'");
  if(a.status==='pending') btns+=`<button class="btn btn-ghost btn-sm" onclick="confirmAppt(${a.id})">✅ Confirm</button>`;
  if(['pending','confirmed'].includes(a.status)) btns+=`<button class="btn btn-ghost btn-sm" onclick="openUpload(${a.id},${a.patient_id},'${pn}')" style="margin-left:4px">💊 Rx</button>`;
  if(a.status==='confirmed') btns+=`<button class="btn btn-primary btn-sm" onclick="completeAppt(${a.id})" style="margin-left:4px">🏁 Complete</button>`;
  btns+=`<button class="btn btn-ghost btn-sm" onclick="viewIntake(${a.id},'${pn}')" style="margin-left:4px;border-color:#7c3aed;color:#7c3aed" title="View pre-visit questionnaire">📋 Intake</button>`;
  return btns;
}
function dApptTable(data, emptyMsg='No appointments'){
  if(!data.length) return `<div class="empty-state"><div class="es-icon">✅</div><h4>${emptyMsg}</h4></div>`;
  return `<div class="table-wrap"><table>
    <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Status</th><th>Reason</th><th>Actions</th></tr></thead>
    <tbody>${data.map(a=>`<tr>
      <td><strong>${a.patient_name}</strong></td>
      <td>${dfmtDate(a.appointment_date)}</td><td>${dfmtTime(a.appointment_time)}</td>
      <td>${dbadge(a.status)}</td>
      <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.reason||'—'}</td>
      <td>${dActionBtns(a)}</td>
    </tr>`).join('')}</tbody></table></div>`;
}

let _dLastHash='';
async function pollDoctor(){
  try{
    const res=await fetch('api/poll.php');
    const d=await res.json();
    const hash=JSON.stringify({s:d.stats,t:d.today_appts?.length,p:d.pending?.length,c:d.confirmed?.length,pa:d.patients?.length,pr:d.prescriptions?.length,h:d.history?.length,n:d.notifications?.length});
    if(hash===_dLastHash) return;
    _dLastHash=hash;

    // Stats
    const sm={pending:'dst-pending',confirmed:'dst-confirmed',completed:'dst-completed',patients:'dst-patients'};
    for(const[k,id] of Object.entries(sm)){ const el=document.getElementById(id); if(el) el.textContent=d.stats[k]??''; }

    // Today dash + today page
    const todayHtml = !d.today_appts?.length
      ? '<div class="empty-state"><div class="es-icon">🌟</div><h4>No appointments today</h4><p>Enjoy your day!</p></div>'
      : d.today_appts.map(a=>`<div class="appt-card"><div><div class="ac-doc">${a.patient_name}</div><div class="ac-spec" style="opacity:.7;font-size:12px;margin-bottom:8px">${a.reason||'No reason stated'}</div><div class="appt-meta"><span>🕐 ${dfmtTime(a.appointment_time)}</span></div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">${dbadge(a.status)}${dActionBtns(a)}</div></div>`).join('');
    const dd=document.getElementById('d-today-dash'); if(dd) dd.innerHTML=todayHtml;
    const dp=document.getElementById('d-today-page'); if(dp) dp.innerHTML=todayHtml;

    // Pending dash
    const pdash=document.getElementById('d-pend-dash'); if(pdash) pdash.innerHTML=dApptTable(d.pending||[],'No pending appointments');
    // Appointments tabs
    const tp=document.getElementById('d-tab-pend'); if(tp) tp.innerHTML=dApptTable(d.pending||[],'No pending appointments');
    const tc=document.getElementById('d-tab-conf'); if(tc) tc.innerHTML=dApptTable(d.confirmed||[],'No confirmed appointments');

    // Patients
    const pts=document.getElementById('d-patients');
    if(pts) pts.innerHTML=!d.patients?.length?'<div class="empty-state"><div class="es-icon">👥</div><h4>No patients yet</h4></div>'
      :`<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Total Visits</th><th>Last Visit</th></tr></thead><tbody>
      ${d.patients.map(p=>`<tr><td><strong>${p.full_name}</strong></td><td>${p.email}</td><td>${p.phone||'—'}</td><td>${p.visits}</td><td>${dfmtDate(p.last_visit)}</td></tr>`).join('')}</tbody></table></div>`;

    // Prescriptions
    const prs=document.getElementById('d-prescriptions');
    if(prs) prs.innerHTML=!d.prescriptions?.length?'<div class="empty-state"><div class="es-icon">💊</div><h4>No prescriptions uploaded yet</h4><p>Upload from Appointments page.</p></div>'
      :d.prescriptions.map(p=>`<div class="file-card"><div class="file-icon">${p.file_type==='pdf'?'📄':'🖼️'}</div><div class="file-info"><div class="file-name">Rx — ${p.patient_name} · ${dfmtDate(p.appointment_date)}</div><div class="file-meta">Uploaded ${dfmtDate(p.uploaded_at)}</div>${p.notes?`<div style="font-size:12px;color:var(--g500);margin-top:3px">📝 ${p.notes}</div>`:''}</div><a href="../uploads/prescriptions/${p.file_path}" target="_blank" class="btn btn-ghost btn-sm">View</a></div>`).join('');

    // History
    const hist=document.getElementById('d-history');
    if(hist) hist.innerHTML=!d.history?.length?'<div class="empty-state"><div class="es-icon">🕐</div><h4>No history yet</h4></div>'
      :`<div class="table-wrap"><table><thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Status</th><th>Reason</th></tr></thead><tbody>
      ${d.history.map(a=>`<tr><td><strong>${a.patient_name}</strong></td><td>${dfmtDate(a.appointment_date)}</td><td>${dfmtTime(a.appointment_time)}</td><td>${dbadge(a.status)}</td><td>${a.reason||'—'}</td></tr>`).join('')}</tbody></table></div>`;

    // Notifications
    const unread=d.unread||0;
    const nb=document.querySelector('.notif-badge'); if(nb) nb.style.display=unread>0?'block':'none';
    const nl=document.getElementById('notif-list');
    if(nl) nl.innerHTML=!d.notifications?.length?'<div style="padding:24px;text-align:center;color:var(--g400);font-size:13px">No notifications yet</div>'
      :d.notifications.map(n=>`<div class="notif-item${n.is_read?'':' unread'}" onclick="markRead(${n.id})">${!n.is_read?'<div class="notif-dot"></div>':''}<div><div class="notif-text"><strong>${n.title}</strong><br>${n.message}</div><div class="notif-time">${new Date(n.created_at.replace(' ','T')).toLocaleDateString('en-IN',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'})}</div></div></div>`).join('');
  } catch(e){ /* silent */ }
}
pollDoctor();
setInterval(pollDoctor,5000);
</script>

<!-- ── INTAKE QUESTIONNAIRE VIEWER MODAL (Doctor) ────────────── -->
<div class="modal-overlay hidden" id="modal-intake-view">
  <div class="modal" style="max-width:660px;max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-header" style="flex-shrink:0;background:linear-gradient(135deg,#4c1d95,#7c3aed);color:#fff;border-radius:var(--radius) var(--radius) 0 0">
      <h3 style="color:#fff">📋 Pre-Visit Intake — <span id="intake-view-name"></span></h3>
      <button class="modal-close" onclick="closeM('modal-intake-view')" style="color:#fff;opacity:.8">✕</button>
    </div>
    <div class="modal-body" id="intake-view-body" style="overflow-y:auto;flex:1">
      <div class="empty-state"><div class="es-icon">⏳</div><h4>Loading…</h4></div>
    </div>
    <div class="modal-footer" style="flex-shrink:0">
      <div id="intake-legend" style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;flex:1">
        <span style="display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#dcfce7;border:1.5px solid #16a34a"></span> Low concern</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#fef9c3;border:1.5px solid #ca8a04"></span> Moderate concern</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#fee2e2;border:1.5px solid #dc2626"></span> High concern</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#fce7f3;border:1.5px solid #db2777"></span> Urgent / flag</span>
      </div>
      <button class="btn btn-ghost" onclick="closeM('modal-intake-view')" style="flex-shrink:0">Close</button>
    </div>
  </div>
</div>

<style>
.intake-row{display:flex;gap:14px;align-items:flex-start;padding:13px 16px;border-radius:10px;margin-bottom:10px;border:1.5px solid transparent}
.intake-row .iq-num{font-weight:700;font-size:15px;min-width:24px;color:#7c3aed;flex-shrink:0;padding-top:1px}
.intake-row .iq-body{flex:1}
.intake-row .iq-q{font-size:13px;font-weight:600;color:var(--g700);margin-bottom:4px}
.intake-row .iq-ans{font-size:14px;font-weight:700}
.intake-row .iq-pill{display:inline-block;padding:3px 11px;border-radius:99px;font-size:12px;font-weight:700;margin-top:4px}
.ic-green {background:#dcfce7;border-color:#16a34a;} .ic-green .iq-ans{color:#15803d} .ic-green .iq-pill{background:#16a34a;color:#fff}
.ic-yellow{background:#fef9c3;border-color:#ca8a04;} .ic-yellow .iq-ans{color:#92400e} .ic-yellow .iq-pill{background:#ca8a04;color:#fff}
.ic-red   {background:#fee2e2;border-color:#dc2626;} .ic-red .iq-ans{color:#991b1b} .ic-red .iq-pill{background:#dc2626;color:#fff}
.ic-pink  {background:#fce7f3;border-color:#db2777;} .ic-pink .iq-ans{color:#9d174d} .ic-pink .iq-pill{background:#db2777;color:#fff}
</style>

<script>
// Intake questions + options + colour logic for each answer value
const INTAKE_QUESTIONS = [
  { q:'Primary reason for visit',
    opts:['New symptom or concern','Follow-up for an existing condition','Medication review / refill','Routine check-up'],
    colors:['ic-red','ic-yellow','ic-yellow','ic-green'] },
  { q:'Duration of main concern',
    opts:['Less than 24 hours','1 – 7 days','1 – 4 weeks','More than 1 month'],
    colors:['ic-pink','ic-red','ic-yellow','ic-yellow'] },
  { q:'Severity of main concern',
    opts:['Mild','Moderate','Severe','Very severe'],
    colors:['ic-green','ic-yellow','ic-red','ic-pink'] },
  { q:'Recent change in symptoms',
    opts:['Getting worse','Staying the same','Improving','Not applicable'],
    colors:['ic-red','ic-yellow','ic-green','ic-green'] },
  { q:'Impact on daily life',
    opts:['Not at all','Slightly','Moderately','Severely'],
    colors:['ic-green','ic-yellow','ic-yellow','ic-red'] },
  { q:'Number of current medications',
    opts:['None','1 – 2 medications','3 – 5 medications','More than 5 medications'],
    colors:['ic-green','ic-green','ic-yellow','ic-red'] },
  { q:'Chronic medical conditions',
    opts:['None','One condition','Two to three conditions','More than three conditions'],
    colors:['ic-green','ic-green','ic-yellow','ic-red'] },
  { q:'Hospitalisations / surgeries in past year',
    opts:['No','Hospitalisation only','Surgery only','Both hospitalisation and surgery'],
    colors:['ic-green','ic-yellow','ic-yellow','ic-red'] },
  { q:'Urgent symptoms present right now',
    opts:['Chest pain','Shortness of breath','Severe / unbearable pain','None of the above'],
    colors:['ic-pink','ic-pink','ic-pink','ic-green'] },
  { q:'Feeling down or depressed (past 2 weeks)',
    opts:['Never','Several days','More than half the days','Nearly every day'],
    colors:['ic-green','ic-yellow','ic-red','ic-pink'] },
];

const CONCERN_LABELS = {
  'ic-green':'Low concern','ic-yellow':'Moderate concern','ic-red':'High concern','ic-pink':'Urgent / flag'
};

async function viewIntake(apptId, patName){
  document.getElementById('intake-view-name').textContent = patName;
  document.getElementById('intake-view-body').innerHTML =
    '<div class="empty-state"><div class="es-icon">⏳</div><h4>Loading intake…</h4></div>';
  openM('modal-intake-view');
  try{
    const res = await fetch(`api/get_intake.php?appointment_id=${apptId}`);
    const data = await res.json();
    if(!data.success){
      document.getElementById('intake-view-body').innerHTML =
        `<div class="empty-state"><div class="es-icon">📋</div><h4>No intake submitted</h4><p style="color:var(--g500);font-size:13px">${data.message||'The patient has not completed the pre-visit questionnaire yet.'}</p></div>`;
      return;
    }
    const intake = data.intake;
    let html = `<div style="padding:4px 0 8px;font-size:12px;color:var(--g500)">Submitted: ${new Date(intake.submitted_at.replace(' ','T')).toLocaleString('en-IN',{dateStyle:'medium',timeStyle:'short'})}</div>`;
    INTAKE_QUESTIONS.forEach((item, idx) => {
      const qKey = `q${idx+1}`;
      const val = parseInt(intake[qKey]);
      if(!val || val<1 || val>4) return;
      const colorClass = item.colors[val-1];
      const answerText = item.opts[val-1];
      const label = CONCERN_LABELS[colorClass];
      html += `<div class="intake-row ${colorClass}">
        <div class="iq-num">${idx+1}</div>
        <div class="iq-body">
          <div class="iq-q">${item.q}</div>
          <div class="iq-ans">${answerText}</div>
          <span class="iq-pill">${label}</span>
        </div>
      </div>`;
    });
    document.getElementById('intake-view-body').innerHTML = html;
  }catch(e){
    document.getElementById('intake-view-body').innerHTML =
      '<div class="empty-state"><div class="es-icon">⚠️</div><h4>Error loading intake</h4></div>';
  }
}
</script>
</body>
</html>
