<?php
require_once __DIR__ . '/../includes/auth_helper.php';
requireRole('patient','../index.php');
require_once __DIR__ . '/../config/db.php';
$user = currentUser();
$uid  = (int)$user['id'];

// Stats
$s_pend = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid AND status='pending'")->fetch_assoc()['c'];
$s_conf = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid AND status='confirmed'")->fetch_assoc()['c'];
$s_comp = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid AND status='completed'")->fetch_assoc()['c'];
$s_prx  = $conn->query("SELECT COUNT(*) c FROM prescriptions WHERE patient_id=$uid")->fetch_assoc()['c'];

// Upcoming
$upcoming = [];
$r = $conn->query("SELECT a.*,u.full_name doc_name,dp.specialization,dp.consultation_fee fee
  FROM appointments a JOIN users u ON a.doctor_id=u.id
  LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
  WHERE a.patient_id=$uid AND a.status IN('pending','confirmed') AND a.appointment_date>=CURDATE()
  ORDER BY a.appointment_date,a.appointment_time LIMIT 5");
while($row=$r->fetch_assoc()) $upcoming[]=$row;

// Unread notifications
$unread = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];
$notifs = [];
$r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 12");
while($row=$r->fetch_assoc()) $notifs[]=$row;

// All appointments for history tab
$all_appts = [];
$r = $conn->query("SELECT a.*,u.full_name doc_name,dp.specialization,dp.consultation_fee fee
  FROM appointments a JOIN users u ON a.doctor_id=u.id
  LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
  WHERE a.patient_id=$uid ORDER BY a.appointment_date DESC,a.appointment_time DESC");
while($row=$r->fetch_assoc()) $all_appts[]=$row;

// Doctors
$doctors=[];
$r=$conn->query("SELECT u.id,u.full_name,dp.specialization,dp.consultation_fee,dp.experience_years,
  dp.qualification,dp.available_days,dp.slot_start,dp.slot_end,dp.slot_duration
  FROM users u JOIN doctor_profiles dp ON dp.user_id=u.id
  WHERE u.role='doctor' AND u.is_active=1 AND dp.is_available=1
  ORDER BY dp.specialization,u.full_name");
while($row=$r->fetch_assoc()) $doctors[]=$row;
$specs=array_unique(array_column($doctors,'specialization')); sort($specs);

// Prescriptions
$prescriptions=[];
$r=$conn->query("SELECT p.*,u.full_name doc_name,dp.specialization,a.appointment_date
  FROM prescriptions p JOIN users u ON p.doctor_id=u.id
  LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
  LEFT JOIN appointments a ON p.appointment_id=a.id
  WHERE p.patient_id=$uid ORDER BY p.uploaded_at DESC");
while($row=$r->fetch_assoc()) $prescriptions[]=$row;

// Billing
$bills=[];
$r=$conn->query("SELECT br.*,a.appointment_date,u.full_name doc_name,dp.specialization
  FROM billing_receipts br LEFT JOIN appointments a ON br.appointment_id=a.id
  LEFT JOIN users u ON a.doctor_id=u.id LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
  WHERE br.patient_id=$uid ORDER BY br.uploaded_at DESC");
while($row=$r->fetch_assoc()) $bills[]=$row;

// Profile
$profile=$conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Patient Dashboard — MediCare</title>
<link rel="stylesheet" href="../assets/css/dashboard.css"/>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sb-icon">🏥</div>
    <div><div class="sb-name">MediCare</div><div class="sb-role">Patient Portal</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">Main</div>
    <button class="nav-item active" id="nav-dashboard" onclick="showPage('dashboard')"><span class="ni">🏠</span>Dashboard</button>
    <button class="nav-item" id="nav-book"         onclick="showPage('book')"><span class="ni">🗓️</span>Book Appointment</button>
    <button class="nav-item" id="nav-appointments" onclick="showPage('appointments')">
      <span class="ni">📋</span>My Appointments<?php if($s_pend+$s_conf>0):?><span class="nav-count"><?=$s_pend+$s_conf?></span><?php endif?>
    </button>
    <div class="sidebar-section">Health</div>
    <button class="nav-item" id="nav-symptoms"     onclick="showPage('symptoms')"><span class="ni">🤖</span>Symptom Analyser</button>
    <button class="nav-item" id="nav-prescriptions"onclick="showPage('prescriptions')">
      <span class="ni">💊</span>Prescriptions<?php if($s_prx>0):?><span class="nav-count"><?=$s_prx?></span><?php endif?>
    </button>
    <button class="nav-item" id="nav-billing"      onclick="showPage('billing')"><span class="ni">🧾</span>Billing & Receipts</button>
    <div class="sidebar-section">Account</div>
    <button class="nav-item" id="nav-history"      onclick="showPage('history')"><span class="ni">🕐</span>History</button>
    <button class="nav-item" id="nav-profile"      onclick="showPage('profile')"><span class="ni">👤</span>My Profile</button>
  </nav>
  <div class="sidebar-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?=strtoupper(substr($user['name'],0,1))?></div>
      <div><div class="sb-uname"><?=htmlspecialchars($user['name'])?></div>
      <div class="sb-uemail"><?=htmlspecialchars($user['email'])?></div></div>
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
        <div style="padding:24px;text-align:center;color:var(--g400);font-size:13px">No notifications yet</div>
        <?php else: foreach($notifs as $n):?>
        <div class="notif-item <?=$n['is_read']?'':'unread'?>" onclick="markRead(<?=$n['id']?>)">
          <?php if(!$n['is_read']):?><div class="notif-dot"></div><?php endif?>
          <div><div class="notif-text"><strong><?=htmlspecialchars($n['title'])?></strong><br><?=htmlspecialchars($n['message'])?></div>
          <div class="notif-time"><?=date('M j, g:i a',strtotime($n['created_at']))?></div></div>
        </div>
        <?php endforeach; endif?>
      </div>
    </div>
    <span style="font-size:13px;color:var(--g500)">Hi, <strong style="color:var(--navy)"><?=explode(' ',$user['name'])[0]?></strong></span>
  </div>
</header>

<div class="page-content">

<!-- PAGE: DASHBOARD -->
<div class="page active" id="page-dashboard">
  <div class="page-header">
    <h1>Welcome back, <?=explode(' ',$user['name'])[0]?>! 👋</h1>
    <p>Here's an overview of your health activity</p>
  </div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-ico ico-amber">🕐</div><div><div class="stat-num" id="st-pending"><?=$s_pend?></div><div class="stat-lbl">Pending</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-green">✅</div><div><div class="stat-num" id="st-confirmed"><?=$s_conf?></div><div class="stat-lbl">Confirmed</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-blue">🏁</div><div><div class="stat-num" id="st-completed"><?=$s_comp?></div><div class="stat-lbl">Completed</div></div></div>
    <div class="stat-card"><div class="stat-ico ico-purple">💊</div><div><div class="stat-num" id="st-prescriptions"><?=$s_prx?></div><div class="stat-lbl">Prescriptions</div></div></div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">🗓️ Upcoming Appointments</span>
      <button class="btn btn-primary btn-sm" onclick="showPage('book')">+ Book New</button></div>
    <div class="card-body" id="upcoming-body">
      <?php if(empty($upcoming)):?>
      <div class="empty-state"><div class="es-icon">📅</div><h4>No upcoming appointments</h4><p>Book your first appointment with a specialist.</p></div>
      <?php else: foreach($upcoming as $a):?>
      <div class="appt-card">
        <div>
          <div class="ac-doc">Dr. <?=htmlspecialchars($a['doc_name'])?></div>
          <div class="ac-spec"><?=htmlspecialchars($a['specialization'])?></div>
          <div class="ac-meta"><span>📅 <?=date('D, M j Y',strtotime($a['appointment_date']))?></span><span>🕐 <?=date('g:i A',strtotime($a['appointment_time']))?></span><span>₹<?=number_format($a['fee'],0)?></span></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:9px">
          <span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span>
          <?php if(in_array($a['status'],['pending','confirmed'])):?>
          <button class="cancel-btn" onclick="confirmCancel(<?=$a['id']?>,'Dr. <?=htmlspecialchars(addslashes($a['doc_name']))?>')">Cancel</button>
          <?php endif?>
        </div>
      </div>
      <?php endforeach; endif?>
    </div>
  </div>
  <div class="qa-grid">
    <?php foreach([['🗓️','Book Appointment','book'],['🤖','Symptom Analyser','symptoms'],['💊','Prescriptions','prescriptions'],['🕐','History','history'],['🧾','Billing','billing'],['👤','My Profile','profile']] as [$ic,$lb,$pg]):?>
    <button class="qa-card" onclick="showPage('<?=$pg?>')"><div class="qa-icon"><?=$ic?></div><div class="qa-label"><?=$lb?></div></button>
    <?php endforeach?>
  </div>
</div>

<!-- PAGE: BOOK APPOINTMENT -->
<div class="page" id="page-book">
  <div class="page-header"><h1>Book an Appointment</h1><p>Find a doctor and select a convenient time slot</p></div>
  <div class="card">
    <div class="card-body">
      <div class="search-bar">
        <input type="text" id="doc-search" placeholder="🔍 Search doctor name…" oninput="filterDocs()"/>
        <select id="spec-filter" onchange="filterDocs()">
          <option value="">All Specializations</option>
          <?php foreach($specs as $s):?><option><?=htmlspecialchars($s)?></option><?php endforeach?>
        </select>
      </div>
      <div class="doctor-grid" id="doc-grid">
        <?php if(empty($doctors)):?>
        <div class="empty-state" style="grid-column:1/-1"><div class="es-icon">👨‍⚕️</div><h4>No doctors listed yet</h4><p>Doctors will appear once added by admin.</p></div>
        <?php else: foreach($doctors as $d):?>
        <div class="doctor-card"
             data-id="<?=$d['id']?>" data-name="<?=htmlspecialchars($d['full_name'])?>"
             data-spec="<?=htmlspecialchars($d['specialization'])?>" data-fee="<?=$d['consultation_fee']?>"
             data-days="<?=htmlspecialchars($d['available_days']??'Mon,Tue,Wed,Thu,Fri')?>"
             data-start="<?=$d['slot_start']??'09:00:00'?>" data-end="<?=$d['slot_end']??'17:00:00'?>"
             data-dur="<?=$d['slot_duration']??30?>" onclick="selectDoc(this)">
          <div class="doc-avatar"><?=strtoupper(substr($d['full_name'],0,1))?></div>
          <div class="doc-name">Dr. <?=htmlspecialchars($d['full_name'])?></div>
          <div class="doc-spec"><?=htmlspecialchars($d['specialization'])?></div>
          <div class="doc-meta">
            <?php if($d['experience_years']):?><span>🏆 <?=$d['experience_years']?> yrs experience</span><?php endif?>
            <?php if($d['qualification']):?><span>🎓 <?=htmlspecialchars($d['qualification'])?></span><?php endif?>
          </div>
          <div class="doc-fee">₹<?=number_format($d['consultation_fee'],0)?> <span style="font-size:11px;font-weight:400;color:var(--g400)">/visit</span></div>
        </div>
        <?php endforeach; endif?>
      </div>
    </div>
  </div>
  <div id="booking-panel" style="display:none">
    <div class="card">
      <div class="card-header">
        <span class="card-title" id="booking-title">Select a Time Slot</span>
        <button class="btn btn-ghost btn-sm" onclick="clearDoc()">✕ Clear</button>
      </div>
      <div class="card-body">
        <div id="book-alert"></div>
        <div class="form-row" style="margin-bottom:18px">
          <div class="form-group">
            <label class="form-label">Select Date</label>
            <input type="date" class="form-control" id="book-date" min="<?=date('Y-m-d')?>" onchange="loadSlots()"/>
          </div>
          <div class="form-group">
            <label class="form-label">Reason for Visit</label>
            <input type="text" class="form-control" id="book-reason" placeholder="Brief description…"/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Available Time Slots</label>
          <div class="slot-grid" id="slot-grid"><span style="color:var(--g400);font-size:13px">Pick a date first</span></div>
        </div>
        <input type="hidden" id="selected-slot"/>
        <div style="margin-top:18px">
          <button class="btn btn-primary" id="book-btn" onclick="bookAppt()">🗓️ Confirm Booking</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PAGE: MY APPOINTMENTS -->
<div class="page" id="page-appointments">
  <div class="page-header"><h1>My Appointments</h1><p>Track and manage your upcoming visits</p></div>
  <div class="card">
    <div class="card-header"><span class="card-title">📋 Active Appointments</span>
      <button class="btn btn-primary btn-sm" onclick="showPage('book')">+ Book New</button></div>
    <div class="card-body" id="active-appts-body" style="padding:0">
      <?php
      $active=array_filter($all_appts,fn($a)=>in_array($a['status'],['pending','confirmed']));
      if(empty($active)):?>
      <div class="empty-state"><div class="es-icon">🗓️</div><h4>No active appointments</h4><p>Book a new appointment to get started.</p></div>
      <?php else:?>
      <div class="table-wrap"><table>
        <thead><tr><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Fee</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($active as $a):?>
        <tr>
          <td><strong>Dr. <?=htmlspecialchars($a['doc_name'])?></strong></td>
          <td><?=htmlspecialchars($a['specialization'])?></td>
          <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
          <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
          <td><span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
          <td>₹<?=number_format($a['fee'],0)?></td>
          <td><button class="btn btn-danger btn-sm" onclick="confirmCancel(<?=$a['id']?>,'Dr. <?=htmlspecialchars(addslashes($a['doc_name']))?>')">Cancel</button></td>
        </tr>
        <?php endforeach?>
        </tbody>
      </table></div>
      <?php endif?>
    </div>
  </div>
</div>

<!-- PAGE: SYMPTOM ANALYSER -->
<div class="page" id="page-symptoms">
  <div class="page-header"><h1>AI Symptom Analyser 🤖</h1><p>Describe your symptoms for AI-powered health insights. Set your Gemini API key with the 🔑 button.</p></div>
  <div class="card">
    <div class="card-header"><span class="card-title">🩺 Health Assessment Chat</span>
      <button class="btn btn-ghost btn-sm" onclick="clearChat()">🗑️ New Chat</button></div>
    <div class="chat-wrap">
      <div class="chat-messages" id="chat-msgs">
        <div class="chat-msg bot">
          <div class="chat-avatar">🤖</div>
          <div class="chat-bubble">Hello! I'm your MediCare AI health assistant. 👋<br><br>
            Describe your symptoms in detail — e.g. <em>"I have a headache, 38.5°C fever for 2 days, and a sore throat."</em><br><br>
            I'll analyze your symptoms, suggest possible conditions, and recommend which specialist to consult.<br><br>
            <strong>⚠️ Note:</strong> This is for informational purposes only and not a substitute for professional medical advice. Always consult a doctor.
          </div>
        </div>
      </div>
      <div class="chat-input-row">
        <input type="text" class="chat-input" id="chat-input" placeholder="Describe your symptoms here…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"/>
        <button class="btn btn-primary" id="send-btn" onclick="sendMsg()">Send</button>
      </div>
    </div>
  </div>
</div>

<!-- PAGE: PRESCRIPTIONS -->
<div class="page" id="page-prescriptions">
  <div class="page-header"><h1>Prescriptions 💊</h1><p>View prescriptions uploaded by your doctors</p></div>
  <div class="card"><div class="card-body" id="presc-body">
    <?php if(empty($prescriptions)):?>
    <div class="empty-state"><div class="es-icon">💊</div><h4>No prescriptions yet</h4><p>Prescriptions from your doctors will appear here after consultations.</p></div>
    <?php else: foreach($prescriptions as $p):?>
    <div class="file-card">
      <div class="file-icon"><?=$p['file_type']==='pdf'?'📄':'🖼️'?></div>
      <div class="file-info">
        <div class="file-name">Prescription — Dr. <?=htmlspecialchars($p['doc_name'])?></div>
        <div class="file-meta"><?=htmlspecialchars($p['specialization'])?> &nbsp;·&nbsp; Visit: <?=date('M j, Y',strtotime($p['appointment_date']))?> &nbsp;·&nbsp; Uploaded <?=date('M j, Y',strtotime($p['uploaded_at']))?></div>
        <?php if($p['notes']):?><div style="font-size:12px;color:var(--g500);margin-top:3px">📝 <?=htmlspecialchars($p['notes'])?></div><?php endif?>
      </div>
      <?php if($p['file_path']):?>
      <a href="../uploads/prescriptions/<?=htmlspecialchars($p['file_path'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a>
      <?php endif?>
    </div>
    <?php endforeach; endif?>
  </div></div>
</div>

<!-- PAGE: BILLING -->
<div class="page" id="page-billing">
  <div class="page-header"><h1>Billing & Receipts 🧾</h1><p>View payment receipts uploaded after offline payment at the billing counter</p></div>
  <div class="card"><div class="card-body" id="bill-body">
    <?php if(empty($bills)):?>
    <div class="empty-state"><div class="es-icon">🧾</div><h4>No receipts yet</h4><p>After paying at the billing counter, the admin will upload your receipt here.</p></div>
    <?php else: foreach($bills as $b):?>
    <div class="file-card">
      <div class="file-icon">🧾</div>
      <div class="file-info">
        <div class="file-name">Receipt — Dr. <?=htmlspecialchars($b['doc_name'])?> (<?=date('M j, Y',strtotime($b['appointment_date']))?>) </div>
        <div class="file-meta">Amount: <strong>₹<?=number_format($b['amount_paid'],2)?></strong> &nbsp;·&nbsp; <?=htmlspecialchars($b['payment_method'])?> &nbsp;·&nbsp; <?=date('M j, Y',strtotime($b['uploaded_at']))?></div>
      </div>
      <?php if($b['file_path']):?>
      <a href="../uploads/receipts/<?=htmlspecialchars($b['file_path'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a>
      <?php endif?>
    </div>
    <?php endforeach; endif?>
  </div></div>
</div>

<!-- PAGE: HISTORY -->
<div class="page" id="page-history">
  <div class="page-header"><h1>Appointment History 🕐</h1><p>All your past and cancelled appointments</p></div>
  <div class="card"><div class="card-body" id="hist-body" style="padding:0">
    <?php if(empty($all_appts)):?>
    <div class="empty-state"><div class="es-icon">🕐</div><h4>No history yet</h4><p>Your appointment history will appear here.</p></div>
    <?php else:?>
    <div class="table-wrap"><table>
      <thead><tr><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Fee</th><th>Reason</th></tr></thead>
      <tbody>
      <?php foreach($all_appts as $a):?>
      <tr>
        <td><strong>Dr. <?=htmlspecialchars($a['doc_name'])?></strong></td>
        <td><?=htmlspecialchars($a['specialization'])?></td>
        <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
        <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
        <td><span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
        <td>₹<?=number_format($a['fee'],0)?></td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['reason']??'—')?></td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table></div>
    <?php endif?>
  </div></div>
</div>

<!-- PAGE: PROFILE -->
<div class="page" id="page-profile">
  <div class="page-header"><h1>My Profile 👤</h1><p>Update your personal information</p></div>
  <div class="card" style="max-width:560px"><div class="card-body">
    <div id="profile-alert"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Full Name</label>
        <input type="text" class="form-control" id="p-name" value="<?=htmlspecialchars($profile['full_name'])?>"/></div>
      <div class="form-group"><label class="form-label">Email (read-only)</label>
        <input type="email" class="form-control" value="<?=htmlspecialchars($profile['email'])?>" disabled style="background:var(--g100)"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Phone</label>
        <input type="tel" class="form-control" id="p-phone" value="<?=htmlspecialchars($profile['phone']??'')?>"/></div>
      <div class="form-group"><label class="form-label">Date of Birth</label>
        <input type="date" class="form-control" id="p-dob" value="<?=htmlspecialchars($profile['date_of_birth']??'')?>"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Gender</label>
        <select class="form-control" id="p-gender">
          <option value="">Select</option>
          <option value="male"   <?=($profile['gender']??'')==='male'  ?'selected':''?>>Male</option>
          <option value="female" <?=($profile['gender']??'')==='female'?'selected':''?>>Female</option>
          <option value="other"  <?=($profile['gender']??'')==='other' ?'selected':''?>>Other</option>
        </select></div>
      <div class="form-group" style="grid-column:1/-1"><label class="form-label">Address</label>
        <textarea class="form-control" id="p-address"><?=htmlspecialchars($profile['address']??'')?></textarea></div>
    </div>
    <button class="btn btn-primary" onclick="saveProfile()">💾 Save Changes</button>
  </div></div>
</div>

</div><!-- /page-content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- CANCEL MODAL -->
<div class="modal-overlay hidden" id="modal-cancel">
  <div class="modal">
    <div class="modal-header"><h3>Cancel Appointment</h3><button class="modal-close" onclick="closeM('modal-cancel')">✕</button></div>
    <div class="modal-body">
      <p style="color:var(--g600);font-size:14.5px">Are you sure you want to cancel your appointment with <strong id="cancel-doc"></strong>?</p>
      <p style="color:var(--red);font-size:13px;margin-top:10px">⚠️ This action cannot be undone.</p>
      <input type="hidden" id="cancel-id"/>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeM('modal-cancel')">Keep Appointment</button>
      <button class="btn btn-danger" id="cancel-confirm-btn" onclick="doCancel()">Yes, Cancel It</button>
    </div>
  </div>
</div>

<!-- API KEY MODAL -->
<div class="modal-overlay hidden" id="modal-apikey">
  <div class="modal">
    <div class="modal-header"><h3>🔑 Gemini API Key</h3><button class="modal-close" onclick="closeM('modal-apikey')">✕</button></div>
    <div class="modal-body">
      <p style="color:var(--g600);font-size:13.5px;margin-bottom:14px">Enter your <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color:var(--blue)">Google Gemini API key</a> to enable the AI Symptom Analyser. It is stored only in your browser (localStorage).</p>
      <div class="form-group"><label class="form-label">API Key</label>
        <input type="password" class="form-control" id="apikey-inp" placeholder="AIzaSy…"/></div>
      <p style="font-size:12px;color:var(--g400)">🔒 Never sent to our servers — stays in your browser only.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeM('modal-apikey')">Cancel</button>
      <button class="btn btn-primary" onclick="saveKey()">Save Key</button>
    </div>
  </div>
</div>

<!-- 🔑 Floating API Key Button -->
<button id="api-key-btn" onclick="openKeyModal()" title="Set Gemini API Key">🔑</button>

<!-- ── PRE-VISIT INTAKE QUESTIONNAIRE MODAL ─────────────────── -->
<div class="modal-overlay hidden" id="modal-intake">
  <div class="modal" style="max-width:640px;max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-header" style="flex-shrink:0">
      <h3>📋 Pre-Visit Health Questionnaire</h3>
    </div>
    <div class="modal-body" style="overflow-y:auto;flex:1;padding-bottom:8px">
      <p style="color:var(--g500);font-size:13px;margin-bottom:20px">Please answer these 10 questions to help your doctor prepare for your visit. This takes about 2 minutes.</p>
      <div id="intake-alert"></div>
      <input type="hidden" id="intake-appt-id"/>

      <?php
      $intake_questions = [
        1 => ['q' => 'What is your primary reason for today\'s visit?',
              'opts' => ['New symptom or concern','Follow-up for an existing condition','Medication review / refill','Routine check-up']],
        2 => ['q' => 'How long have you been experiencing your main concern?',
              'opts' => ['Less than 24 hours','1 – 7 days','1 – 4 weeks','More than 1 month']],
        3 => ['q' => 'How would you describe the severity of your main concern?',
              'opts' => ['Mild','Moderate','Severe','Very severe']],
        4 => ['q' => 'Have your symptoms changed recently?',
              'opts' => ['Getting worse','Staying the same','Improving','Not applicable']],
        5 => ['q' => 'How much is your daily life affected by this problem?',
              'opts' => ['Not at all','Slightly','Moderately','Severely']],
        6 => ['q' => 'How many medications are you currently taking?',
              'opts' => ['None','1 – 2 medications','3 – 5 medications','More than 5 medications']],
        7 => ['q' => 'Do you have any chronic medical conditions?',
              'opts' => ['None','One condition','Two to three conditions','More than three conditions']],
        8 => ['q' => 'Have you had any hospitalisations or surgeries in the past year?',
              'opts' => ['No','Hospitalisation only','Surgery only','Both hospitalisation and surgery']],
        9 => ['q' => 'Are any of the following symptoms present right now?',
              'opts' => ['Chest pain','Shortness of breath','Severe / unbearable pain','None of the above']],
       10 => ['q' => 'In the past two weeks, how often have you felt down, depressed, or had little interest in activities?',
              'opts' => ['Never','Several days','More than half the days','Nearly every day']],
      ];
      foreach ($intake_questions as $n => $q):
      ?>
      <div class="intake-q" style="margin-bottom:22px">
        <div style="font-weight:600;font-size:14px;color:var(--g800);margin-bottom:10px">
          <?=$n?>. <?=htmlspecialchars($q['q'])?>
        </div>
        <div style="display:flex;flex-direction:column;gap:7px">
          <?php foreach ($q['opts'] as $i => $opt): $val = $i + 1; ?>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 12px;border-radius:8px;border:1.5px solid var(--g200);transition:all .15s;font-size:13px;color:var(--g700)"
                 onmouseover="this.style.borderColor='#059669'" onmouseout="if(!document.getElementById('iq<?=$n?>_<?=$val?>').checked)this.style.borderColor='var(--g200)'"
                 id="lbl-iq<?=$n?>_<?=$val?>">
            <input type="radio" name="iq<?=$n?>" id="iq<?=$n?>_<?=$val?>" value="<?=$val?>"
                   onchange="highlightIntakeOpt(<?=$n?>,<?=$val?>)"
                   style="accent-color:#059669;width:15px;height:15px;flex-shrink:0"/>
            <span><?=htmlspecialchars($opt)?></span>
          </label>
          <?php endforeach ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
    <div class="modal-footer" style="flex-shrink:0;border-top:1px solid var(--g200);padding-top:14px">
      <button class="btn btn-ghost" onclick="skipIntake()">Skip for now</button>
      <button class="btn btn-primary" onclick="submitIntake()" id="intake-submit-btn">Submit Questionnaire</button>
    </div>
  </div>
</div>

<script>
// ── PAGE NAV ──────────────────────────────────────────
const PAGE_TITLES={dashboard:'Dashboard',book:'Book Appointment',appointments:'My Appointments',
  symptoms:'Symptom Analyser',prescriptions:'Prescriptions',billing:'Billing & Receipts',
  history:'Appointment History',profile:'My Profile'};

function showPage(name){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  document.getElementById('page-'+name)?.classList.add('active');
  document.getElementById('nav-'+name)?.classList.add('active');
  document.getElementById('topbar-title').textContent=PAGE_TITLES[name]||'';
  document.getElementById('notif-drop').classList.add('hidden');
}

// ── NOTIFICATIONS ─────────────────────────────────────
function toggleNotif(){document.getElementById('notif-drop').classList.toggle('hidden')}
document.addEventListener('click',e=>{
  if(!e.target.closest('#notif-btn')&&!e.target.closest('#notif-drop'))
    document.getElementById('notif-drop').classList.add('hidden');
});
function markAllRead(){
  fetch('api/notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'mark_all'})});
  document.querySelectorAll('.notif-item').forEach(el=>{el.classList.remove('unread')});
  document.querySelectorAll('.notif-dot').forEach(el=>el.remove());
  document.querySelector('.nb')?.remove();
}
function markRead(id){
  fetch('api/notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'mark_read',id})});
  const items=document.querySelectorAll('.notif-item');
  items.forEach(el=>{if(el.onclick?.toString().includes(id)){el.classList.remove('unread');el.querySelector('.notif-dot')?.remove()}});
}

// ── MODAL ─────────────────────────────────────────────
function openM(id){document.getElementById(id).classList.remove('hidden')}
function closeM(id){document.getElementById(id).classList.add('hidden')}

// ── DOCTOR SEARCH ─────────────────────────────────────
let selDoc=null;
function filterDocs(){
  const q=document.getElementById('doc-search').value.toLowerCase();
  const sp=document.getElementById('spec-filter').value;
  document.querySelectorAll('.doctor-card').forEach(c=>{
    const show=(!q||c.dataset.name.toLowerCase().includes(q))&&(!sp||c.dataset.spec===sp);
    c.style.display=show?'':'none';
  });
}
function selectDoc(card){
  document.querySelectorAll('.doctor-card').forEach(c=>c.classList.remove('selected'));
  card.classList.add('selected');
  selDoc={id:card.dataset.id,name:card.dataset.name,spec:card.dataset.spec,
    fee:card.dataset.fee,days:card.dataset.days,
    start:card.dataset.start,end:card.dataset.end,dur:parseInt(card.dataset.dur)};
  document.getElementById('booking-panel').style.display='block';
  document.getElementById('booking-title').textContent=`📅 Book with Dr. ${selDoc.name} — ${selDoc.spec}`;
  document.getElementById('book-date').value='';
  document.getElementById('slot-grid').innerHTML='<span style="color:var(--g400);font-size:13px">Pick a date first</span>';
  document.getElementById('selected-slot').value='';
  document.getElementById('booking-panel').scrollIntoView({behavior:'smooth',block:'start'});
}
function clearDoc(){
  selDoc=null;
  document.querySelectorAll('.doctor-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('booking-panel').style.display='none';
}

// ── SLOTS ─────────────────────────────────────────────
async function loadSlots(){
  if(!selDoc) return;
  const date=document.getElementById('book-date').value;
  if(!date) return;
  const dayName=new Date(date+'T12:00:00').toLocaleDateString('en-US',{weekday:'short'});
  const allowed=selDoc.days.split(',').map(d=>d.trim().substring(0,3));
  const grid=document.getElementById('slot-grid');
  document.getElementById('selected-slot').value='';
  if(!allowed.includes(dayName)){
    grid.innerHTML=`<span style="color:var(--red);font-size:13px">⚠️ Not available on ${dayName}. Available: ${selDoc.days}</span>`;
    return;
  }
  grid.innerHTML='<span style="color:var(--g400);font-size:13px">Loading slots…</span>';
  const res=await fetch(`api/slots.php?doctor_id=${selDoc.id}&date=${date}`);
  const data=await res.json();
  const booked=data.booked||[];
  const isToday    = !!data.is_today;
  const currentT   = data.current_time || '00:00:00';
  const slots=genSlots(selDoc.start,selDoc.end,selDoc.dur);
  if(!slots.length){grid.innerHTML='<span style="color:var(--g400);font-size:13px">No slots configured.</span>';return;}
  grid.innerHTML='';
  let availableCount = 0;
  slots.forEach(t=>{
    const isBooked = booked.includes(t);
    const isPast   = isToday && t <= currentT;
    if (isPast) return; // skip rendering past slots entirely
    availableCount++;
    const btn=document.createElement('button');
    btn.className='slot'+(isBooked?' booked':'');
    btn.textContent=fmt12(t); btn.dataset.time=t;
    if(!isBooked) btn.onclick=()=>{
      document.querySelectorAll('.slot').forEach(s=>s.classList.remove('selected'));
      btn.classList.add('selected');
      document.getElementById('selected-slot').value=t;
    };
    grid.appendChild(btn);
  });
  if (availableCount === 0) {
    grid.innerHTML = '<span style="color:var(--amber);font-size:13px">⏰ No more available slots for today. Please pick another date.</span>';
  }
}
function genSlots(start,end,dur){
  const slots=[];let[h,m]=start.split(':').map(Number);
  const[eh,em]=end.split(':').map(Number);
  while(h*60+m<eh*60+em){
    slots.push(`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:00`);
    m+=dur;if(m>=60){h+=Math.floor(m/60);m%=60;}
  }
  return slots;
}
function fmt12(t){const[h,m]=t.split(':').map(Number);const ap=h>=12?'PM':'AM';return`${h%12||12}:${String(m).padStart(2,'0')} ${ap}`;}

// ── BOOK ─────────────────────────────────────────────
async function bookAppt(){
  const date=document.getElementById('book-date').value;
  const slot=document.getElementById('selected-slot').value;
  const reason=document.getElementById('book-reason').value.trim();
  if(!selDoc) return showAlert('book-alert','Please select a doctor.','error');
  if(!date)   return showAlert('book-alert','Please select a date.','error');
  if(!slot)   return showAlert('book-alert','Please select a time slot.','error');
  const btn=document.getElementById('book-btn');
  btn.disabled=true;btn.innerHTML='<div class="spinner"></div> Booking…';
  try{
    const res=await fetch('api/book_appointment.php',{method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({doctor_id:selDoc.id,date,time:slot,reason})});
    const data=await res.json();
    if(data.success){
      showAlert('book-alert','✅ Appointment booked! A confirmation email has been sent to you.','success');
      clearDoc();
      // Open intake questionnaire modal
      setTimeout(()=>{
        document.getElementById('intake-appt-id').value = data.appointment_id;
        openM('modal-intake');
      }, 800);
    } else {
      showAlert('book-alert',data.message||'Booking failed. Please try again.','error');
    }
  }catch(e){showAlert('book-alert','Server error. Please try again.','error');}
  btn.disabled=false;btn.innerHTML='🗓️ Confirm Booking';
}

// ── CANCEL ───────────────────────────────────────────
function confirmCancel(id,name){
  document.getElementById('cancel-id').value=id;
  document.getElementById('cancel-doc').textContent=name;
  openM('modal-cancel');
}
async function doCancel(){
  const id=document.getElementById('cancel-id').value;
  const btn=document.getElementById('cancel-confirm-btn');
  btn.disabled=true;btn.innerHTML='<div class="spinner"></div>';
  try{
    const res=await fetch('api/cancel_appointment.php',{method:'POST',
      headers:{'Content-Type':'application/json'},body:JSON.stringify({appointment_id:id})});
    const data=await res.json();
    closeM('modal-cancel');
    if(data.success) location.reload();
    else alert(data.message||'Could not cancel.');
  }catch(e){alert('Server error.');}
  btn.disabled=false;btn.innerHTML='Yes, Cancel It';
}

// ── GEMINI CHAT ───────────────────────────────────────
let chatHistory=[];
function getKey(){return localStorage.getItem('gemini_api_key')||'';}

async function sendMsg(){
  const input=document.getElementById('chat-input');
  const text=input.value.trim();
  if(!text) return;
  if(!getKey()){openKeyModal();return;}
  addMsg(text,'user');input.value='';
  chatHistory.push({role:'user',parts:[{text}]});
  showTyping();
  try{
    const sysPrompt=`You are MediCare AI, a compassionate and knowledgeable medical symptom analysis assistant.
When a patient describes symptoms, you MUST:
1. 🔍 **Symptom Summary** – Briefly restate the key symptoms you detected
2. 🩺 **Possible Conditions** – List 2–4 likely conditions with brief explanations (be clear these are possibilities, not diagnoses)
3. 👨‍⚕️ **Recommended Specialist** – State the type of doctor to consult (e.g. General Physician, Cardiologist, Dermatologist, ENT Specialist, etc.)
4. 💊 **Immediate Self-Care** – 2–3 actionable tips the patient can do right now
5. 🚨 **Red Flags** – Mention any symptoms that would require emergency care
6. ⚠️ **Disclaimer** – Always end with: "This analysis is for informational purposes only. Please consult a licensed doctor for proper diagnosis and treatment."

Use clear headings, bullet points, and emojis. Be empathetic and professional.
If the message is not about health symptoms, politely redirect the user to describe their symptoms.`;

    const payload={
      contents:[{role:'user',parts:[{text:sysPrompt+'\n\nPatient: '+text}]},...chatHistory.slice(0,-1),{role:'user',parts:[{text}]}],
      generationConfig:{temperature:0.6,maxOutputTokens:1400}
    };
    const resp=await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${getKey()}`,
      {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await resp.json();
    removeTyping();
    if(data.error){addMsg('⚠️ API Error: '+(data.error.message||'Invalid API key. Please check your Gemini API key.'),'bot');return;}
    const reply=data.candidates?.[0]?.content?.parts?.[0]?.text||'Sorry, I could not analyze your symptoms. Please try again.';
    chatHistory.push({role:'model',parts:[{text:reply}]});
    addMsg(reply,'bot');
    fetch('api/save_analysis.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({symptoms:text,result:reply})});
  }catch(e){removeTyping();addMsg('⚠️ Could not connect to AI. Check your API key and internet connection.','bot');}
}

function addMsg(text,role){
  const wrap=document.getElementById('chat-msgs');
  const div=document.createElement('div');
  div.className='chat-msg '+role;
  const html=text.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/\*(.*?)\*/g,'<em>$1</em>')
    .replace(/#{1,3} (.+)/g,'<strong>$1</strong>').replace(/\n/g,'<br>');
  div.innerHTML=`<div class="chat-avatar">${role==='user'?'👤':'🤖'}</div><div class="chat-bubble">${html}</div>`;
  wrap.appendChild(div);wrap.scrollTop=wrap.scrollHeight;
}
function showTyping(){
  const wrap=document.getElementById('chat-msgs');
  const div=document.createElement('div');div.id='typing-ind';div.className='chat-msg bot';
  div.innerHTML='<div class="chat-avatar">🤖</div><div class="chat-bubble"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
  wrap.appendChild(div);wrap.scrollTop=wrap.scrollHeight;
}
function removeTyping(){document.getElementById('typing-ind')?.remove();}
function clearChat(){chatHistory=[];document.getElementById('chat-msgs').innerHTML=`<div class="chat-msg bot"><div class="chat-avatar">🤖</div><div class="chat-bubble">Chat cleared. Describe your symptoms to begin a new analysis.</div></div>`;}

// ── API KEY ───────────────────────────────────────────
function openKeyModal(){document.getElementById('apikey-inp').value=getKey();openM('modal-apikey');}
function saveKey(){
  const k=document.getElementById('apikey-inp').value.trim();
  if(!k){alert('Please enter a valid API key.');return;}
  localStorage.setItem('gemini_api_key',k);closeM('modal-apikey');
  showAlert('','','');// just close
}

// ── PROFILE ───────────────────────────────────────────
async function saveProfile(){
  const payload={full_name:document.getElementById('p-name').value.trim(),
    phone:document.getElementById('p-phone').value.trim(),
    dob:document.getElementById('p-dob').value,
    gender:document.getElementById('p-gender').value,
    address:document.getElementById('p-address').value.trim()};
  const res=await fetch('api/update_profile.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await res.json();
  showAlert('profile-alert',data.message||(data.success?'Profile saved!':'Save failed.'),data.success?'success':'error');
}

// ── ALERT ─────────────────────────────────────────────
function showAlert(id,msg,type){
  if(!id||!msg) return;
  const el=document.getElementById(id);if(!el) return;
  el.innerHTML=`<div class="alert alert-${type}"><span>${type==='success'?'✅':type==='info'?'ℹ️':'⚠️'}</span><span>${msg}</span></div>`;
  setTimeout(()=>{if(el)el.innerHTML=''},5000);
}

// ── RESPONSIVE ───────────────────────────────────────
if(window.innerWidth<=768) document.getElementById('menu-btn').style.display='flex';

// ═══════════════════════════════════════════════════════════════
//  LIVE POLLING — updates all sections every 5 seconds
// ═══════════════════════════════════════════════════════════════
function fmtDate(d){ if(!d) return '—'; const iso=d.includes('T')?d:d.includes(' ')?d.replace(' ','T'):d+'T00:00:00'; return new Date(iso).toLocaleDateString('en-IN',{month:'short',day:'numeric',year:'numeric'}); }
function fmtTime(t){ if(!t) return '—'; const[h,m]=t.split(':'); const ap=h>=12?'PM':'AM'; return `${h%12||12}:${m} ${ap}`; }
function badgeHtml(s){ return `<span class="badge badge-${s}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`; }

function renderUpcoming(data){
  const el=document.getElementById('upcoming-body'); if(!el) return;
  if(!data.length){ el.innerHTML='<div class="empty-state"><div class="es-icon">📅</div><h4>No upcoming appointments</h4><p>Book your first appointment with a specialist.</p></div>'; return; }
  el.innerHTML=data.map(a=>`
    <div class="appt-card">
      <div>
        <div class="appt-doc">Dr. ${a.doctor_name}</div>
        <div class="appt-spec">${a.specialization||''}</div>
        <div class="appt-meta"><span>📅 ${fmtDate(a.appointment_date)}</span><span>🕐 ${fmtTime(a.appointment_time)}</span></div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
        ${badgeHtml(a.status)}
        ${['pending','confirmed'].includes(a.status)?`<button class="cancel-pill" onclick="confirmCancel(${a.id},'Dr. ${a.doctor_name.replace(/'/g,"\\'")}')">Cancel</button>`:''}
      </div>
    </div>`).join('');
}

function renderActiveAppts(data){
  const el=document.getElementById('active-appts-body'); if(!el) return;
  const active=data.filter(a=>['pending','confirmed'].includes(a.status));
  if(!active.length){ el.innerHTML='<div class="empty-state"><div class="es-icon">🗓️</div><h4>No active appointments</h4><p>Book a new appointment to get started.</p></div>'; return; }
  el.innerHTML=`<div class="table-wrap"><table>
    <thead><tr><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Fee</th><th>Action</th></tr></thead>
    <tbody>${active.map(a=>`<tr>
      <td><strong>Dr. ${a.doctor_name}</strong></td><td>${a.specialization||'—'}</td>
      <td>${fmtDate(a.appointment_date)}</td><td>${fmtTime(a.appointment_time)}</td>
      <td>${badgeHtml(a.status)}</td><td>₹${Number(a.consultation_fee||0).toLocaleString('en-IN')}</td>
      <td><button class="btn btn-danger btn-sm" onclick="confirmCancel(${a.id},'Dr. ${a.doctor_name.replace(/'/g,"\\'")}')">Cancel</button></td>
    </tr>`).join('')}</tbody></table></div>`;
}

function renderDoctors(data){
  const grid=document.getElementById('doc-grid'); if(!grid) return;
  const sel=document.getElementById('spec-filter'); if(!sel) return;
  const curSpec=sel.value;
  // Update specialization options
  const specs=[...new Set(data.map(d=>d.specialization).filter(Boolean))].sort();
  sel.innerHTML='<option value="">All Specializations</option>'+specs.map(s=>`<option${s===curSpec?' selected':''}>${s}</option>`).join('');
  if(!data.length){ grid.innerHTML='<div class="empty-state" style="grid-column:1/-1"><div class="es-icon">👨‍⚕️</div><h4>No doctors listed yet</h4><p>Doctors will appear once added by admin.</p></div>'; return; }
  grid.innerHTML=data.map(d=>`
    <div class="doctor-card" data-id="${d.id}" data-name="${d.full_name}" data-spec="${d.specialization||''}"
      data-fee="${d.consultation_fee}" data-days="${d.available_days||'Mon,Tue,Wed,Thu,Fri'}"
      data-start="${d.slot_start||'09:00:00'}" data-end="${d.slot_end||'17:00:00'}" data-dur="${d.slot_duration||30}"
      onclick="selectDoc(this)">
      <div class="doc-avatar">${d.full_name.charAt(0).toUpperCase()}</div>
      <div class="doc-name">Dr. ${d.full_name}</div>
      <div class="doc-spec">${d.specialization||''}</div>
      <div class="doc-meta">${d.experience_years?`<span>🏆 ${d.experience_years} yrs experience</span>`:''}${d.qualification?`<span>🎓 ${d.qualification}</span>`:''}</div>
      <div class="doc-fee">₹${Number(d.consultation_fee||0).toLocaleString('en-IN')} <span style="font-size:11px;font-weight:400;color:var(--g400)">/visit</span></div>
    </div>`).join('');
  filterDocs();
}

function renderPrescriptions(data){
  const el=document.getElementById('presc-body'); if(!el) return;
  if(!data.length){ el.innerHTML='<div class="empty-state"><div class="es-icon">💊</div><h4>No prescriptions yet</h4><p>Prescriptions from your doctors will appear here after consultations.</p></div>'; return; }
  el.innerHTML=data.map(p=>`
    <div class="file-card">
      <div class="file-icon">${p.file_type==='pdf'?'📄':'🖼️'}</div>
      <div class="file-info">
        <div class="file-name">Prescription — Dr. ${p.doctor_name}</div>
        <div class="file-meta">${p.specialization||''} · ${fmtDate(p.appointment_date)} · Uploaded ${fmtDate(p.uploaded_at)}</div>
        ${p.notes?`<div style="font-size:12px;color:var(--g500);margin-top:4px">📝 ${p.notes}</div>`:''}
      </div>
      <a href="../uploads/prescriptions/${p.file_path}" target="_blank" class="btn btn-ghost btn-sm">View</a>
    </div>`).join('');
}

function renderBilling(data){
  const el=document.getElementById('bill-body'); if(!el) return;
  if(!data.length){ el.innerHTML='<div class="empty-state"><div class="es-icon">🧾</div><h4>No receipts yet</h4><p>After paying at the billing counter, the admin will upload your receipt here.</p></div>'; return; }
  el.innerHTML=data.map(b=>`
    <div class="file-card">
      <div class="file-icon">🧾</div>
      <div class="file-info">
        <div class="file-name">Receipt — Dr. ${b.doctor_name||'—'} (${fmtDate(b.appointment_date)})</div>
        <div class="file-meta">Amount: <strong>₹${Number(b.amount_paid||0).toLocaleString('en-IN')}</strong> · ${b.payment_method||''} · Uploaded ${fmtDate(b.uploaded_at)}</div>
      </div>
      ${b.file_path?`<a href="../uploads/receipts/${b.file_path}" target="_blank" class="btn btn-ghost btn-sm">View</a>`:''}
    </div>`).join('');
}

function renderHistory(data){
  const el=document.getElementById('hist-body'); if(!el) return;
  if(!data.length){ el.innerHTML='<div class="empty-state"><div class="es-icon">🕐</div><h4>No history yet</h4><p>Your appointment history will appear here.</p></div>'; return; }
  el.innerHTML=`<div class="table-wrap"><table>
    <thead><tr><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Fee</th><th>Reason</th></tr></thead>
    <tbody>${data.map(a=>`<tr>
      <td><strong>Dr. ${a.doctor_name}</strong></td><td>${a.specialization||'—'}</td>
      <td>${fmtDate(a.appointment_date)}</td><td>${fmtTime(a.appointment_time)}</td>
      <td>${badgeHtml(a.status)}</td><td>₹${Number(a.consultation_fee||0).toLocaleString('en-IN')}</td>
      <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.reason||'—'}</td>
    </tr>`).join('')}</tbody></table></div>`;
}

function renderNotifs(data, unread){
  const badge=document.querySelector('.notif-badge');
  if(badge) badge.style.display = unread>0?'block':'none';
  // update nav counts
  const apptNav=document.querySelector('#nav-appointments .nav-count');
  // We'll update on next poll via stats
  const list=document.getElementById('notif-list'); if(!list) return;
  if(!data.length){ list.innerHTML='<div style="padding:24px;text-align:center;color:var(--g400);font-size:13px">No notifications yet</div>'; return; }
  list.innerHTML=data.map(n=>`
    <div class="notif-item${n.is_read?'':' unread'}" onclick="markRead(${n.id})">
      ${!n.is_read?'<div class="notif-dot"></div>':''}
      <div>
        <div class="notif-text"><strong>${n.title}</strong><br>${n.message}</div>
        <div class="notif-time">${new Date(n.created_at.replace(' ','T')).toLocaleDateString('en-IN',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
      </div>
    </div>`).join('');
}

function updateStats(s){
  const ids={pending:'st-pending',confirmed:'st-confirmed',completed:'st-completed',prescriptions:'st-prescriptions'};
  for(const[k,id] of Object.entries(ids)){ const el=document.getElementById(id); if(el && el.textContent!=s[k]) el.textContent=s[k]; }
}

let _lastPollHash = '';
async function pollPatient(){
  try{
    const res  = await fetch('api/poll.php');
    const data = await res.json();
    const hash = JSON.stringify({s:data.stats, u:data.unread, up:data.upcoming?.length, d:data.doctors?.length, a:data.active?.length, p:data.prescriptions?.length, b:data.billing?.length, h:data.history?.length, n:data.notifications?.length});
    if(hash === _lastPollHash) return; // nothing changed
    _lastPollHash = hash;
    updateStats(data.stats);
    renderUpcoming(data.upcoming||[]);
    renderActiveAppts(data.active||[]);
    renderDoctors(data.doctors||[]);
    renderPrescriptions(data.prescriptions||[]);
    renderBilling(data.billing||[]);
    renderHistory(data.history||[]);
    renderNotifs(data.notifications||[], data.unread||0);
  } catch(e){ /* silent fail — don't disrupt UI */ }
}
// Initial render via poll, then repeat every 5s
pollPatient();
setInterval(pollPatient, 5000);
document.addEventListener('click',e=>{
  const sb=document.getElementById('sidebar');
  if(window.innerWidth<=768&&sb.classList.contains('open')&&!e.target.closest('.sidebar')&&!e.target.closest('#menu-btn'))
    sb.classList.remove('open');
});

// ── PRE-VISIT INTAKE QUESTIONNAIRE ───────────────────────────
function highlightIntakeOpt(qn, val){
  // Reset all options for this question
  for(let v=1;v<=4;v++){
    const lbl=document.getElementById(`lbl-iq${qn}_${v}`);
    if(lbl){
      lbl.style.borderColor = (v===val) ? '#059669' : 'var(--g200)';
      lbl.style.background  = (v===val) ? '#f0fdf4' : '';
    }
  }
}

async function submitIntake(){
  const apptId = document.getElementById('intake-appt-id').value;
  const payload = {appointment_id: parseInt(apptId)};
  for(let i=1;i<=10;i++){
    const sel = document.querySelector(`input[name="iq${i}"]:checked`);
    payload[`q${i}`] = sel ? parseInt(sel.value) : 0;
  }
  const btn = document.getElementById('intake-submit-btn');
  btn.disabled=true; btn.innerHTML='<div class="spinner"></div> Saving…';
  try{
    const res = await fetch('api/save_intake.php',{method:'POST',
      headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
    const data = await res.json();
    if(data.success){
      closeM('modal-intake');
      setTimeout(()=>location.reload(), 400);
    } else {
      const al=document.getElementById('intake-alert');
      al.innerHTML=`<div class="alert alert-error"><span>⚠️</span><span>${data.message}</span></div>`;
      btn.disabled=false; btn.innerHTML='Submit Questionnaire';
    }
  }catch(e){
    const al=document.getElementById('intake-alert');
    al.innerHTML=`<div class="alert alert-error"><span>⚠️</span><span>Server error. Please try again.</span></div>`;
    btn.disabled=false; btn.innerHTML='Submit Questionnaire';
  }
}

function skipIntake(){
  closeM('modal-intake');
  setTimeout(()=>location.reload(), 300);
}
</script>
</body>
</html>
