<?php
require_once __DIR__ . '/../includes/auth_helper.php';
requireRole('admin','../index.php');
require_once __DIR__ . '/../config/db.php';
$user = currentUser();
$uid  = (int)$user['id'];

// Stats
$s_docs    = $conn->query("SELECT COUNT(*) c FROM users WHERE role='doctor' AND is_active=1")->fetch_assoc()['c'];
$s_patients= $conn->query("SELECT COUNT(*) c FROM users WHERE role='patient' AND is_active=1")->fetch_assoc()['c'];
$s_today   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURDATE() AND status IN('pending','confirmed')")->fetch_assoc()['c'];
$s_pending = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];

// All doctors
$doctors=[];
$r=$conn->query("SELECT u.*,dp.specialization,dp.consultation_fee,dp.experience_years,dp.qualification,dp.is_available
  FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
  WHERE u.role='doctor' ORDER BY dp.specialization,u.full_name");
while($row=$r->fetch_assoc()) $doctors[]=$row;

// All appointments
$all_appts=[];
$r=$conn->query("SELECT a.*,p.full_name pat_name,d.full_name doc_name,dp.specialization,dp.consultation_fee
  FROM appointments a
  JOIN users p ON a.patient_id=p.id
  JOIN users d ON a.doctor_id=d.id
  LEFT JOIN doctor_profiles dp ON dp.user_id=d.id
  ORDER BY a.appointment_date DESC,a.appointment_time DESC LIMIT 200");
while($row=$r->fetch_assoc()) $all_appts[]=$row;

// All prescriptions
$all_rx=[];
$r=$conn->query("SELECT p.*,pat.full_name pat_name,doc.full_name doc_name,dp.specialization,a.appointment_date
  FROM prescriptions p
  JOIN users pat ON p.patient_id=pat.id
  JOIN users doc ON p.doctor_id=doc.id
  LEFT JOIN doctor_profiles dp ON dp.user_id=doc.id
  LEFT JOIN appointments a ON p.appointment_id=a.id
  ORDER BY p.uploaded_at DESC");
while($row=$r->fetch_assoc()) $all_rx[]=$row;

// All billing
$all_bills=[];
$r=$conn->query("SELECT br.*,p.full_name pat_name,d.full_name doc_name,a.appointment_date
  FROM billing_receipts br
  JOIN users p ON br.patient_id=p.id
  LEFT JOIN appointments a ON br.appointment_id=a.id
  LEFT JOIN users d ON a.doctor_id=d.id
  ORDER BY br.uploaded_at DESC");
while($row=$r->fetch_assoc()) $all_bills[]=$row;

// All patients
$all_patients=[];
$r=$conn->query("SELECT u.*,COUNT(a.id) total_appts FROM users u
  LEFT JOIN appointments a ON a.patient_id=u.id
  WHERE u.role='patient' GROUP BY u.id ORDER BY u.created_at DESC");
while($row=$r->fetch_assoc()) $all_patients[]=$row;

// Appointments for billing dropdown (completed without receipt)
$billable=[];
$r=$conn->query("SELECT a.id,a.appointment_date,a.appointment_time,
  p.full_name pat_name,p.id pat_id,d.full_name doc_name,dp.consultation_fee
  FROM appointments a JOIN users p ON a.patient_id=p.id JOIN users d ON a.doctor_id=d.id
  LEFT JOIN doctor_profiles dp ON dp.user_id=d.id
  LEFT JOIN billing_receipts br ON br.appointment_id=a.id
  WHERE a.status IN('completed','confirmed') AND br.id IS NULL
  ORDER BY a.appointment_date DESC LIMIT 100");
while($row=$r->fetch_assoc()) $billable[]=$row;

$specs=['General Medicine','Cardiology','Dermatology','Neurology','Orthopedics','Pediatrics',
  'Psychiatry','Gynecology','Ophthalmology','ENT','Oncology','Endocrinology','Nephrology','Gastroenterology','Pulmonology','Urology'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Dashboard — MediCare</title>
<link rel="stylesheet" href="../assets/css/dashboard.css"/>
<style>
  .sidebar{background:#3b0764}
  .sb-role{color:rgba(255,255,255,.4)}
  .nav-item:hover{background:rgba(255,255,255,.08)}
  .nav-item.active{background:rgba(255,255,255,.14)}
  .sidebar-section{color:rgba(255,255,255,.3)}
  .btn-primary{background:linear-gradient(135deg,#4c1d95,#7c3aed)}
  .topbar-title{color:#4c1d95}
  .page-header h1{color:#4c1d95}
  .stat-card:hover{border-color:#7c3aed}
  /* OTP Toggle Switch */
  .otp-switch{position:relative;display:inline-block;width:42px;height:24px}
  .otp-switch input{opacity:0;width:0;height:0}
  .otp-switch .slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;transition:.3s;border-radius:24px}
  .otp-switch .slider::before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;transition:.3s;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.2)}
  .otp-switch input:checked + .slider{background:#16a34a}
  .otp-switch input:checked + .slider::before{transform:translateX(18px)}
  .otp-switch input:disabled + .slider{opacity:.5;cursor:not-allowed}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sb-icon">🛡️</div>
    <div><div class="sb-name">MediCare</div><div class="sb-role">Admin Console</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">Overview</div>
    <button class="nav-item active" id="nav-dashboard"    onclick="showPage('dashboard')"><span class="ni">🏠</span>Dashboard</button>
    <button class="nav-item" id="nav-appointments"        onclick="showPage('appointments')"><span class="ni">🗓️</span>All Appointments<?php if($s_pending>0):?><span class="nav-count"><?=$s_pending?></span><?php endif?></button>
    <div class="sidebar-section">Management</div>
    <button class="nav-item" id="nav-doctors"             onclick="showPage('doctors')"><span class="ni">👨‍⚕️</span>Doctors</button>
    <button class="nav-item" id="nav-patients"            onclick="showPage('patients')"><span class="ni">🧑‍⚕️</span>Patients</button>
    <button class="nav-item" id="nav-prescriptions"       onclick="showPage('prescriptions')"><span class="ni">💊</span>Prescriptions</button>
    <button class="nav-item" id="nav-billing"             onclick="showPage('billing')"><span class="ni">🧾</span>Billing & Receipts</button>
    <div class="sidebar-section" style="margin-top:8px">Account</div>
    <button class="nav-item" id="nav-settings"            onclick="showPage('settings')"><span class="ni">⚙️</span>Settings</button>
  </nav>
  <div class="sidebar-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?=strtoupper(substr($user['name'],0,1))?></div>
      <div><div class="sb-uname"><?=htmlspecialchars($user['name'])?></div><div class="sb-uemail">Administrator</div></div>
    </div>
    <a href="../auth/logout.php" class="nav-item" style="color:rgba(255,100,100,.75);margin-top:3px"><span class="ni">🚪</span>Sign out</a>
  </div>
</aside>

<div class="main">
<header class="topbar">
  <div style="display:flex;align-items:center;gap:10px">
    <button class="tb-btn" id="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none">☰</button>
    <span class="topbar-title" id="topbar-title">Dashboard</span>
  </div>
  <div class="topbar-right">
    <span style="font-size:13px;color:var(--g500)">Admin: <strong style="color:#4c1d95"><?=htmlspecialchars($user['name'])?></strong></span>
  </div>
</header>
<div class="page-content">

<!-- DASHBOARD -->
<div class="page active" id="page-dashboard">
  <div class="page-header"><h1>Admin Console 🛡️</h1><p>System overview — <?=date('l, F j, Y')?></p></div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-ico ico-blue">👨‍⚕️</div><div><div class="stat-num" id="ast-doctors"><?=$s_docs?></div><div class="stat-lbl">Active Doctors</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-green">🧑‍⚕️</div><div><div class="stat-num" id="ast-patients"><?=$s_patients?></div><div class="stat-lbl">Patients</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-amber">📅</div><div><div class="stat-num" id="ast-today"><?=$s_today?></div><div class="stat-lbl">Today's Appts</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-purple">🕐</div><div><div class="stat-num" id="ast-pending"><?=$s_pending?></div><div class="stat-lbl">Pending</div></div></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px">
    <?php foreach([['👨‍⚕️','Manage Doctors','doctors'],['🗓️','Appointments','appointments'],['🧾','Upload Receipt','billing'],['💊','Prescriptions','prescriptions'],['🧑‍⚕️','Patients','patients']] as [$ic,$lb,$pg]):?>
    <button class="qa-card" onclick="showPage('<?=$pg?>')"><div class="qa-icon"><?=$ic?></div><div class="qa-label"><?=$lb?></div></button>
    <?php endforeach?>
  </div>
</div>

<!-- APPOINTMENTS -->
<div class="page" id="page-appointments">
  <div class="page-header"><h1>All Appointments 🗓️</h1></div>
  <div class="card" style="margin-bottom:12px">
    <div class="card-body" style="padding:12px 18px">
      <div class="search-bar">
        <input type="text" id="appt-search" placeholder="🔍 Search patient or doctor…" oninput="filterAppts()"/>
        <select id="appt-status-filter" onchange="filterAppts()">
          <option value="">All Statuses</option>
          <option>pending</option><option>confirmed</option><option>completed</option><option>cancelled</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card"><div class="card-body" id="a-appts-body" style="padding:0">
    <div class="table-wrap"><table id="appt-table">
      <thead><tr><th>Patient</th><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Fee</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($all_appts as $a):?>
      <tr data-patient="<?=strtolower($a['pat_name'])?>" data-doctor="<?=strtolower($a['doc_name'])?>" data-status="<?=$a['status']?>">
        <td><strong><?=htmlspecialchars($a['pat_name'])?></strong></td>
        <td>Dr. <?=htmlspecialchars($a['doc_name'])?></td>
        <td><?=htmlspecialchars($a['specialization'])?></td>
        <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
        <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
        <td><span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
        <td>₹<?=number_format($a['consultation_fee'],0)?></td>
        <td>
          <?php if($a['status']==='pending'):?>
          <button class="btn btn-success btn-sm" onclick="adminUpdateAppt(<?=$a['id']?>,'confirmed')">✓</button>
          <?php endif?>
          <?php if(in_array($a['status'],['pending','confirmed'])):?>
          <button class="btn btn-danger btn-sm" onclick="adminUpdateAppt(<?=$a['id']?>,'cancelled')">✕</button>
          <?php endif?>
        </td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table></div>
  </div></div>
</div>

<!-- DOCTORS -->
<div class="page" id="page-doctors">
  <div class="page-header"><h1>Doctors Management 👨‍⚕️</h1></div>
  <div style="margin-bottom:14px"><button class="btn btn-primary" onclick="openAddDoctor()">+ Add Doctor</button></div>
  <div class="card"><div class="card-body" id="a-doctors-body" style="padding:0">
    <?php if(empty($doctors)):?>
    <div class="empty-state"><div class="es-icon">👨‍⚕️</div><h4>No doctors added yet</h4><p>Add doctors so patients can book appointments.</p></div>
    <?php else:?>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Specialization</th><th>Qualification</th><th>Experience</th><th>Fee</th><th>Status</th><th>OTP Required</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($doctors as $d):?>
      <tr>
        <td><strong>Dr. <?=htmlspecialchars($d['full_name'])?></strong><br><span style="font-size:11px;color:var(--g400)"><?=htmlspecialchars($d['email'])?></span></td>
        <td><?=htmlspecialchars($d['specialization']??'—')?></td>
        <td><?=htmlspecialchars($d['qualification']??'—')?></td>
        <td><?=$d['experience_years']??0?> yrs</td>
        <td>₹<?=number_format($d['consultation_fee']??0,0)?></td>
        <td><?=$d['is_available']??1?'<span class="badge badge-confirmed">Available</span>':'<span class="badge badge-cancelled">Unavailable</span>'?></td>
        <td>
          <label class="otp-switch">
            <input type="checkbox" <?=($d['otp_required']??1)?'checked':''?> onchange="toggleOTP(<?=$d['id']?>, this.checked ? 1 : 0)"/>
            <span class="slider"></span>
          </label>
          <span style="font-size:10px;color:#999">[debug: <?=var_export($d['otp_required'],true)?> / <?=gettype($d['otp_required'])?>]</span>
        </td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="openEditDoctor(<?=htmlspecialchars(json_encode($d),ENT_QUOTES)?>)">Edit</button>
          <button class="btn btn-danger btn-sm" onclick="toggleDoctorActive(<?=$d['id']?>,<?=$d['is_active']?>)">
            <?=$d['is_active']?'Deactivate':'Activate'?>
          </button>
        </td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table></div>
    <?php endif?>
  </div></div>
</div>

<!-- PATIENTS -->
<div class="page" id="page-patients">
  <div class="page-header"><h1>Patients 🧑‍⚕️</h1></div>
  <div class="card"><div class="card-body" id="a-patients-body" style="padding:0">
    <?php if(empty($all_patients)):?>
    <div class="empty-state"><div class="es-icon">🧑‍⚕️</div><h4>No patients registered</h4></div>
    <?php else:?>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Total Appointments</th><th>Registered</th><th>OTP Required</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($all_patients as $p):?>
      <tr>
        <td><strong><?=htmlspecialchars($p['full_name'])?></strong></td>
        <td><?=htmlspecialchars($p['email'])?></td>
        <td><?=htmlspecialchars($p['phone']??'—')?></td>
        <td><?=ucfirst($p['gender']??'—')?></td>
        <td><?=$p['total_appts']?></td>
        <td><?=date('M j, Y',strtotime($p['created_at']))?></td>
        <td>
          <label class="otp-switch">
            <input type="checkbox" <?=($p['otp_required']??1)?'checked':''?> onchange="toggleOTP(<?=$p['id']?>, this.checked ? 1 : 0)"/>
            <span class="slider"></span>
          </label>
          <span style="font-size:10px;color:#999">[debug: <?=var_export($p['otp_required'],true)?> / <?=gettype($p['otp_required'])?>]</span>
        </td>
        <td>
          <button class="btn btn-danger btn-sm" onclick="togglePatientActive(<?=$p['id']?>,<?=$p['is_active']?>)">
            <?=$p['is_active']?'Deactivate':'Activate'?>
          </button>
        </td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table></div>
    <?php endif?>
  </div></div>
</div>

<!-- PRESCRIPTIONS DB -->
<div class="page" id="page-prescriptions">
  <div class="page-header"><h1>Prescription Database 💊</h1></div>
  <div class="card"><div class="card-body" id="a-presc-body">
    <?php if(empty($all_rx)):?>
    <div class="empty-state"><div class="es-icon">💊</div><h4>No prescriptions yet</h4></div>
    <?php else: foreach($all_rx as $p):?>
    <div class="file-card">
      <div class="file-icon"><?=$p['file_type']==='pdf'?'📄':'🖼️'?></div>
      <div class="file-info">
        <div class="file-name">Dr. <?=htmlspecialchars($p['doc_name'])?> → <?=htmlspecialchars($p['pat_name'])?></div>
        <div class="file-meta"><?=htmlspecialchars($p['specialization'])?> &nbsp;·&nbsp; <?=date('M j, Y',strtotime($p['appointment_date']))?> &nbsp;·&nbsp; Uploaded <?=date('M j, Y',strtotime($p['uploaded_at']))?></div>
        <?php if($p['notes']):?><div style="font-size:12px;color:var(--g500);margin-top:3px">📝 <?=htmlspecialchars($p['notes'])?></div><?php endif?>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <?php if($p['file_path']):?><a href="../uploads/prescriptions/<?=htmlspecialchars($p['file_path'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a><?php endif?>
        <button class="btn btn-danger btn-sm" onclick="deleteRx(<?=$p['id']?>)">🗑</button>
      </div>
    </div>
    <?php endforeach; endif?>
  </div></div>
</div>

<!-- BILLING -->
<div class="page" id="page-billing">
  <div class="page-header"><h1>Billing & Receipts 🧾</h1></div>
  <div style="margin-bottom:14px"><button class="btn btn-primary" onclick="openBilling()">+ Upload Receipt</button></div>
  <div class="card"><div class="card-body" id="a-billing-body">
    <?php if(empty($all_bills)):?>
    <div class="empty-state"><div class="es-icon">🧾</div><h4>No receipts uploaded yet</h4></div>
    <?php else: foreach($all_bills as $b):?>
    <div class="file-card">
      <div class="file-icon">🧾</div>
      <div class="file-info">
        <div class="file-name"><?=htmlspecialchars($b['pat_name'])?> — Dr. <?=htmlspecialchars($b['doc_name'])?></div>
        <div class="file-meta">₹<?=number_format($b['amount_paid'],2)?> &nbsp;·&nbsp; <?=htmlspecialchars($b['payment_method'])?> &nbsp;·&nbsp; <?=date('M j, Y',strtotime($b['uploaded_at']))?></div>
      </div>
      <?php if($b['file_path']):?><a href="../uploads/receipts/<?=htmlspecialchars($b['file_path'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a><?php endif?>
    </div>
    <?php endforeach; endif?>
  </div></div>
</div>

<!-- SETTINGS PAGE -->
<div class="page" id="page-settings">
  <div class="page-header"><h1>Settings ⚙️</h1><p>Manage your admin account credentials</p></div>
  <div class="card" style="max-width:520px">
    <div class="card-header"><span class="card-title">🔐 Change Admin Email &amp; Password</span></div>
    <div class="card-body">
      <div id="settings-alert"></div>
      <div class="form-group">
        <label class="form-label">Current Email</label>
        <input type="email" class="form-control" value="<?=htmlspecialchars($user['email'])?>" disabled style="background:var(--g100)"/>
      </div>
      <div class="form-group">
        <label class="form-label">New Email Address</label>
        <input type="email" class="form-control" id="admin-new-email" placeholder="new@example.com"/>
      </div>
      <hr style="border:none;border-top:1px solid var(--g200);margin:18px 0"/>
      <div class="form-group">
        <label class="form-label">New Password <span style="color:var(--g400);font-size:11px">(leave blank to keep current)</span></label>
        <input type="password" class="form-control" id="admin-new-password" placeholder="Min 8 characters"/>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" class="form-control" id="admin-confirm-password" placeholder="Re-enter new password"/>
      </div>
      <hr style="border:none;border-top:1px solid var(--g200);margin:18px 0"/>
      <div class="form-group">
        <label class="form-label">Current Password <span style="color:var(--red);font-size:11px">*required to save changes</span></label>
        <input type="password" class="form-control" id="admin-current-password" placeholder="Enter your current password"/>
      </div>
      <button class="btn btn-primary" onclick="saveAdminSettings()" id="settings-save-btn">💾 Save Changes</button>
    </div>
  </div>
</div>

</div></div></div>

<!-- ADD/EDIT DOCTOR MODAL -->
<div class="modal-overlay hidden" id="modal-doctor">
  <div class="modal modal-lg">
    <div class="modal-header"><h3 id="doctor-modal-title">Add Doctor</h3><button class="modal-close" onclick="closeM('modal-doctor')">✕</button></div>
    <div class="modal-body">
      <div id="doctor-alert"></div>
      <input type="hidden" id="doc-edit-id"/>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Full Name</label><input type="text" class="form-control" id="doc-name" placeholder="Dr. Full Name"/></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control" id="doc-email" placeholder="doctor@example.com"/></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Password <span id="pw-hint" style="font-size:10px;color:var(--g400)">(required for new)</span></label>
          <input type="password" class="form-control" id="doc-password" placeholder="Min 8 characters"/></div>
        <div class="form-group"><label class="form-label">Phone</label><input type="tel" class="form-control" id="doc-phone" placeholder="+91…"/></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Specialization</label>
          <select class="form-control" id="doc-spec">
            <?php foreach($specs as $s):?><option><?=$s?></option><?php endforeach?>
          </select></div>
        <div class="form-group"><label class="form-label">Qualification</label><input type="text" class="form-control" id="doc-qual" placeholder="MBBS, MD…"/></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Experience (years)</label><input type="number" class="form-control" id="doc-exp" value="0"/></div>
        <div class="form-group"><label class="form-label">Consultation Fee (₹)</label><input type="number" class="form-control" id="doc-fee" value="500"/></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeM('modal-doctor')">Cancel</button>
      <button class="btn btn-primary" id="save-doc-btn" onclick="saveDoctor()">Save Doctor</button>
    </div>
  </div>
</div>

<!-- BILLING UPLOAD MODAL -->
<div class="modal-overlay hidden" id="modal-billing">
  <div class="modal modal-lg">
    <div class="modal-header"><h3>🧾 Upload Billing Receipt</h3><button class="modal-close" onclick="closeM('modal-billing')">✕</button></div>
    <div class="modal-body">
      <div id="billing-alert"></div>
      <div class="form-group"><label class="form-label">Select Appointment</label>
        <select class="form-control" id="bill-appt">
          <option value="">— Select appointment —</option>
          <?php foreach($billable as $b):?>
          <option value="<?=$b['id']?>" data-patid="<?=$b['pat_id']?>" data-fee="<?=$b['consultation_fee']?>">
            <?=htmlspecialchars($b['pat_name'])?> → Dr. <?=htmlspecialchars($b['doc_name'])?> | <?=date('M j',strtotime($b['appointment_date']))?> <?=date('g:i A',strtotime($b['appointment_time']))?>
          </option>
          <?php endforeach?>
        </select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Amount Paid (₹)</label><input type="number" class="form-control" id="bill-amount" placeholder="0.00"/></div>
        <div class="form-group"><label class="form-label">Payment Method</label>
          <select class="form-control" id="bill-method">
            <option value="cash">Cash</option><option value="card">Card</option>
            <option value="upi">UPI</option><option value="netbanking">Net Banking</option><option value="insurance">Insurance</option>
          </select></div>
      </div>
      <div class="form-group"><label class="form-label">Receipt File (optional)</label>
        <input type="file" class="form-control" id="bill-file" accept=".pdf,.jpg,.jpeg,.png" style="height:auto;padding:8px"/></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeM('modal-billing')">Cancel</button>
      <button class="btn btn-primary" id="bill-save-btn" onclick="saveBilling()">📤 Upload Receipt</button>
    </div>
  </div>
</div>

<script>
const PAGE_TITLES={dashboard:'Dashboard',appointments:'All Appointments',doctors:'Doctors Management',
  patients:'Patients',prescriptions:'Prescriptions',billing:'Billing & Receipts',settings:'Settings'};
function showPage(n){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(i=>i.classList.remove('active'));
  document.getElementById('page-'+n)?.classList.add('active');
  document.getElementById('nav-'+n)?.classList.add('active');
  document.getElementById('topbar-title').textContent=PAGE_TITLES[n]||'';
}
function openM(id){document.getElementById(id).classList.remove('hidden')}
function closeM(id){document.getElementById(id).classList.add('hidden')}
function showAlert(id,msg,type){
  const el=document.getElementById(id);if(!el)return;
  el.innerHTML=`<div class="alert alert-${type}"><span>${type==='success'?'✅':'⚠️'}</span><span>${msg}</span></div>`;
  setTimeout(()=>{if(el)el.innerHTML=''},5000);
}

// Appointment filter
function filterAppts(){
  const q=document.getElementById('appt-search').value.toLowerCase();
  const st=document.getElementById('appt-status-filter').value;
  document.querySelectorAll('#appt-table tbody tr').forEach(tr=>{
    const match=(!q||(tr.dataset.patient+' '+tr.dataset.doctor).includes(q))&&(!st||tr.dataset.status===st);
    tr.style.display=match?'':'none';
  });
}

// Update appointment
async function adminUpdateAppt(id,status){
  if(!confirm(`Set appointment to ${status}?`)) return;
  const res=await fetch('api/update_appointment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({appointment_id:id,status})});
  const data=await res.json();
  if(data.success) location.reload();
  else alert(data.message||'Failed');
}

// Add/Edit Doctor
function openAddDoctor(){
  document.getElementById('doctor-modal-title').textContent='Add New Doctor';
  document.getElementById('doc-edit-id').value='';
  document.getElementById('doc-name').value='';
  document.getElementById('doc-email').value='';
  document.getElementById('doc-password').value='';
  document.getElementById('doc-phone').value='';
  document.getElementById('doc-qual').value='';
  document.getElementById('doc-exp').value='0';
  document.getElementById('doc-fee').value='500';
  document.getElementById('pw-hint').textContent='(required for new doctor)';
  document.getElementById('doctor-alert').innerHTML='';
  openM('modal-doctor');
}
function openEditDoctor(d){
  document.getElementById('doctor-modal-title').textContent='Edit Doctor';
  document.getElementById('doc-edit-id').value=d.id||'';
  document.getElementById('doc-name').value=d.full_name||'';
  document.getElementById('doc-email').value=d.email||'';
  document.getElementById('doc-password').value='';
  document.getElementById('doc-phone').value=d.phone||'';
  document.getElementById('doc-spec').value=d.specialization||'';
  document.getElementById('doc-qual').value=d.qualification||'';
  document.getElementById('doc-exp').value=d.experience_years||0;
  document.getElementById('doc-fee').value=d.consultation_fee||0;
  document.getElementById('pw-hint').textContent='(leave blank to keep current)';
  document.getElementById('doctor-alert').innerHTML='';
  openM('modal-doctor');
}
async function saveDoctor(){
  const editId=document.getElementById('doc-edit-id').value;
  const payload={
    id:editId,
    full_name:document.getElementById('doc-name').value.trim(),
    email:document.getElementById('doc-email').value.trim(),
    password:document.getElementById('doc-password').value,
    phone:document.getElementById('doc-phone').value.trim(),
    specialization:document.getElementById('doc-spec').value,
    qualification:document.getElementById('doc-qual').value.trim(),
    experience:document.getElementById('doc-exp').value,
    fee:document.getElementById('doc-fee').value,
  };
  if(!payload.full_name||!payload.email) return showAlert('doctor-alert','Name and email are required.','error');
  if(!editId&&!payload.password) return showAlert('doctor-alert','Password is required for new doctors.','error');
  const btn=document.getElementById('save-doc-btn');
  btn.disabled=true;btn.innerHTML='<div class="spinner"></div>';
  const res=await fetch('api/save_doctor.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await res.json();
  if(data.success){showAlert('doctor-alert','✅ Doctor saved!','success');setTimeout(()=>{closeM('modal-doctor');location.reload();},1500);}
  else showAlert('doctor-alert',data.message||'Failed','error');
  btn.disabled=false;btn.innerHTML='Save Doctor';
}

// Toggle active
async function toggleDoctorActive(id,current){
  const act=current?0:1;
  if(!confirm(current?'Deactivate this doctor?':'Activate this doctor?')) return;
  const res=await fetch('api/toggle_user.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:id,is_active:act})});
  const data=await res.json();
  if(data.success) location.reload();
  else alert(data.message||'Failed');
}
async function togglePatientActive(id,current){
  const act=current?0:1;
  if(!confirm(current?'Deactivate this patient?':'Activate this patient?')) return;
  const res=await fetch('api/toggle_user.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:id,is_active:act})});
  const data=await res.json();
  if(data.success) location.reload();
  else alert(data.message||'Failed');
}

// Toggle OTP requirement for a user
async function toggleOTP(userId, required){
  const checkbox = event.target;
  checkbox.disabled = true;
  try {
    const res  = await fetch('api/toggle_otp.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:userId, otp_required:required})});
    const data = await res.json();
    if(!data.success){
      alert(data.message || 'Could not update OTP setting.');
      checkbox.checked = !checkbox.checked;  // revert
    }
  } catch(e) {
    alert('Server error. Could not update OTP setting.');
    checkbox.checked = !checkbox.checked;
  }
  checkbox.disabled = false;
}

// Billing
function openBilling(){document.getElementById('billing-alert').innerHTML='';openM('modal-billing');}
document.getElementById('bill-appt')?.addEventListener('change',function(){
  const opt=this.options[this.selectedIndex];
  const fee=opt.dataset.fee||'';
  document.getElementById('bill-amount').value=fee;
});
async function saveBilling(){
  const apptId=document.getElementById('bill-appt').value;
  const opt=document.getElementById('bill-appt').options[document.getElementById('bill-appt').selectedIndex];
  const patId=opt.dataset.patid||'';
  const amount=document.getElementById('bill-amount').value;
  const method=document.getElementById('bill-method').value;
  const file=document.getElementById('bill-file').files[0];
  if(!apptId) return showAlert('billing-alert','Please select an appointment.','error');
  if(!amount)  return showAlert('billing-alert','Please enter the amount paid.','error');
  const fd=new FormData();
  fd.append('appointment_id',apptId);fd.append('patient_id',patId);
  fd.append('amount_paid',amount);fd.append('payment_method',method);
  if(file) fd.append('file',file);
  const btn=document.getElementById('bill-save-btn');
  btn.disabled=true;btn.innerHTML='<div class="spinner"></div>';
  const res=await fetch('api/upload_receipt.php',{method:'POST',body:fd});
  const data=await res.json();
  if(data.success){showAlert('billing-alert','✅ Receipt uploaded!','success');setTimeout(()=>{closeM('modal-billing');location.reload();},1500);}
  else showAlert('billing-alert',data.message||'Failed','error');
  btn.disabled=false;btn.innerHTML='📤 Upload Receipt';
}

// Delete Rx
async function deleteRx(id){
  if(!confirm('Delete this prescription?')) return;
  const res=await fetch('api/delete_prescription.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
  const data=await res.json();
  if(data.success) location.reload();
  else alert(data.message||'Failed');
}

// Admin Settings
async function saveAdminSettings() {
  const newEmail    = document.getElementById('admin-new-email').value.trim();
  const newPassword = document.getElementById('admin-new-password').value;
  const confirmPw   = document.getElementById('admin-confirm-password').value;
  const currentPw   = document.getElementById('admin-current-password').value;

  if (!currentPw) return showAlert('settings-alert','Current password is required to save changes.','error');
  if (!newEmail && !newPassword) return showAlert('settings-alert','Enter a new email or new password to update.','error');
  if (newPassword && newPassword.length < 8) return showAlert('settings-alert','New password must be at least 8 characters.','error');
  if (newPassword && newPassword !== confirmPw) return showAlert('settings-alert','New passwords do not match.','error');

  const btn = document.getElementById('settings-save-btn');
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> Saving…';

  const res  = await fetch('api/update_admin.php', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({new_email: newEmail, new_password: newPassword, current_password: currentPw})});
  const data = await res.json();

  if (data.success) {
    showAlert('settings-alert', '✅ ' + data.message, 'success');
    document.getElementById('admin-new-email').value = '';
    document.getElementById('admin-new-password').value = '';
    document.getElementById('admin-confirm-password').value = '';
    document.getElementById('admin-current-password').value = '';
    if (data.reload) setTimeout(() => location.reload(), 1500);
  } else {
    showAlert('settings-alert', data.message || 'Failed to save.', 'error');
  }
  btn.disabled = false; btn.innerHTML = '💾 Save Changes';
}

if(window.innerWidth<=768) document.getElementById('menu-btn').style.display='flex';

// ═══════════════════════════════════════════════════════════════
//  LIVE POLLING — admin dashboard, every 5 seconds
// ═══════════════════════════════════════════════════════════════
function afmt(d){ if(!d) return '—'; const iso=d.includes('T')?d:d.includes(' ')?d.replace(' ','T'):d+'T00:00:00'; return new Date(iso).toLocaleDateString('en-IN',{month:'short',day:'numeric',year:'numeric'}); }
function atfmt(t){ if(!t) return '—'; const[h,m]=t.split(':'); const ap=h>=12?'PM':'AM'; return `${h%12||12}:${m} ${ap}`; }
function abadge(s){ return `<span class="badge badge-${s}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`; }

let _aLastHash='';
async function pollAdmin(){
  try{
    const res=await fetch('api/poll.php');
    const d=await res.json();
    const hash=JSON.stringify({s:d.stats,a:d.appointments?.length,doc:d.doctors?.length,p:d.patients?.length,pr:d.prescriptions?.length,b:d.billing?.length});
    if(hash===_aLastHash) return;
    _aLastHash=hash;

    // Stats
    const sm={doctors:'ast-doctors',patients:'ast-patients',today:'ast-today',pending:'ast-pending'};
    for(const[k,id] of Object.entries(sm)){ const el=document.getElementById(id); if(el) el.textContent=d.stats[k]??''; }

    // Appointments table
    const ab=document.getElementById('a-appts-body');
    if(ab){
      if(!d.appointments?.length){ ab.innerHTML='<div class="empty-state"><div class="es-icon">📅</div><h4>No appointments yet</h4></div>'; }
      else {
        ab.innerHTML=`<div class="table-wrap"><table id="appt-table">
          <thead><tr><th>Patient</th><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>${d.appointments.map(a=>`<tr>
            <td><strong>${a.patient_name}</strong></td><td>Dr. ${a.doctor_name}</td><td>${a.specialization||'—'}</td>
            <td>${afmt(a.appointment_date)}</td><td>${atfmt(a.appointment_time)}</td><td>${abadge(a.status)}</td>
            <td>${['pending','confirmed'].includes(a.status)?`<button class="btn btn-ghost btn-sm" onclick="adminUpdateAppt(${a.id},'${a.status==='pending'?'confirmed':'completed'}')">
              ${a.status==='pending'?'✅ Confirm':'🏁 Complete'}</button>
              <button class="btn btn-danger btn-sm" onclick="adminUpdateAppt(${a.id},'cancelled')" style="margin-left:4px">✕ Cancel</button>`:'—'}</td>
          </tr>`).join('')}</tbody></table></div>`;
        filterAppts();
      }
    }

    // Doctors table
    const db=document.getElementById('a-doctors-body');
    if(db){
      window._adminDoctors = d.doctors || [];
      if(!d.doctors?.length){ db.innerHTML='<div class="empty-state"><div class="es-icon">👨‍⚕️</div><h4>No doctors added yet</h4><p>Add doctors so patients can book appointments.</p></div>'; }
      else db.innerHTML=`<div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Specialization</th><th>Qualification</th><th>Experience</th><th>Fee</th><th>Status</th><th>OTP Required</th><th>Actions</th></tr></thead>
        <tbody>${d.doctors.map((doc,i)=>`<tr>
          <td><strong>Dr. ${doc.full_name}</strong><br><span style="font-size:11px;color:var(--g400)">${doc.email}</span></td>
          <td>${doc.specialization||'—'}</td><td>${doc.qualification||'—'}</td><td>${doc.experience_years||0} yrs</td>
          <td>₹${Number(doc.consultation_fee||0).toLocaleString('en-IN')}</td>
          <td>${doc.is_available?'<span class="badge badge-confirmed">Available</span>':'<span class="badge badge-cancelled">Unavailable</span>'}</td>
          <td><label class="otp-switch"><input type="checkbox" ${doc.otp_required?'checked':''} onchange="toggleOTP(${doc.id},this.checked?1:0)"/><span class="slider"></span></label></td>
          <td>
            <button class="btn btn-ghost btn-sm" onclick="openEditDoctor(window._adminDoctors[${i}])">Edit</button>
            <button class="btn btn-danger btn-sm" onclick="toggleDoctorActive(${doc.id},${doc.is_active})">${doc.is_active?'Deactivate':'Activate'}</button>
          </td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    // Patients table
    const pb=document.getElementById('a-patients-body');
    if(pb){
      if(!d.patients?.length){ pb.innerHTML='<div class="empty-state"><div class="es-icon">🧑‍⚕️</div><h4>No patients registered</h4></div>'; }
      else pb.innerHTML=`<div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Total Appointments</th><th>Registered</th><th>OTP Required</th><th>Actions</th></tr></thead>
        <tbody>${d.patients.map(p=>`<tr>
          <td><strong>${p.full_name}</strong></td><td>${p.email}</td><td>${p.phone||'—'}</td>
          <td>${p.gender?p.gender.charAt(0).toUpperCase()+p.gender.slice(1):'—'}</td><td>${p.total_appts}</td>
          <td>${afmt(p.created_at)}</td>
          <td><label class="otp-switch"><input type="checkbox" ${p.otp_required?'checked':''} onchange="toggleOTP(${p.id},this.checked?1:0)"/><span class="slider"></span></label></td>
          <td><button class="btn btn-danger btn-sm" onclick="togglePatientActive(${p.id},${p.is_active})">${p.is_active?'Deactivate':'Activate'}</button></td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    // Prescriptions
    const prb=document.getElementById('a-presc-body');
    if(prb){
      if(!d.prescriptions?.length){ prb.innerHTML='<div class="empty-state"><div class="es-icon">💊</div><h4>No prescriptions yet</h4></div>'; }
      else prb.innerHTML=d.prescriptions.map(p=>`<div class="file-card">
        <div class="file-icon">${p.file_type==='pdf'?'📄':'🖼️'}</div>
        <div class="file-info">
          <div class="file-name">${p.patient_name} ← Dr. ${p.doctor_name}</div>
          <div class="file-meta">${afmt(p.appointment_date)} · Uploaded ${afmt(p.uploaded_at)}</div>
        </div>
        <a href="../uploads/prescriptions/${p.file_path}" target="_blank" class="btn btn-ghost btn-sm">View</a>
        <button class="btn btn-danger btn-sm" onclick="deleteRx(${p.id})" style="margin-left:4px">Delete</button>
      </div>`).join('');
    }

    // Billing
    const bb=document.getElementById('a-billing-body');
    if(bb){
      if(!d.billing?.length){ bb.innerHTML='<div class="empty-state"><div class="es-icon">🧾</div><h4>No receipts uploaded yet</h4></div>'; }
      else bb.innerHTML=d.billing.map(b=>`<div class="file-card">
        <div class="file-icon">🧾</div>
        <div class="file-info">
          <div class="file-name">${b.patient_name} · Dr. ${b.doctor_name||'—'} (${afmt(b.appointment_date)})</div>
          <div class="file-meta">₹${Number(b.amount_paid||0).toLocaleString('en-IN')} · ${b.payment_method||''} · ${afmt(b.uploaded_at)}</div>
        </div>
        ${b.file_path?`<a href="../uploads/receipts/${b.file_path}" target="_blank" class="btn btn-ghost btn-sm">View</a>`:''}
      </div>`).join('');
    }
  } catch(e){ /* silent */ }
}
pollAdmin();
setInterval(pollAdmin,5000);
</script>
</body>
</html>
