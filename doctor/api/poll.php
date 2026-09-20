<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('doctor', '../../index.php');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');
$uid = currentUser()['id'];

// ── Stats ──────────────────────────────────────────────────────
$s_today   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND appointment_date=CURDATE() AND status IN ('pending','confirmed')")->fetch_assoc()['c'];
$s_pending = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='pending'")->fetch_assoc()['c'];
$s_done    = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='completed'")->fetch_assoc()['c'];
$s_pts     = $conn->query("SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id=$uid")->fetch_assoc()['c'];
$unread    = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];

// ── Today's appointments ───────────────────────────────────────
$today_appts = [];
$r = $conn->query("SELECT a.*,u.full_name patient_name,u.phone patient_phone
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=$uid AND a.appointment_date=CURDATE() AND a.status IN ('pending','confirmed')
    ORDER BY a.appointment_time");
while($row=$r->fetch_assoc()) $today_appts[]=$row;

// ── Pending appointments ───────────────────────────────────────
$pending = [];
$r = $conn->query("SELECT a.*,u.full_name patient_name
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=$uid AND a.status='pending'
    ORDER BY a.appointment_date,a.appointment_time");
while($row=$r->fetch_assoc()) $pending[]=$row;

// ── Confirmed appointments ─────────────────────────────────────
$confirmed = [];
$r = $conn->query("SELECT a.*,u.full_name patient_name
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=$uid AND a.status='confirmed'
    ORDER BY a.appointment_date,a.appointment_time");
while($row=$r->fetch_assoc()) $confirmed[]=$row;

// ── Patients list ──────────────────────────────────────────────
$patients = [];
$r = $conn->query("SELECT u.id,u.full_name,u.email,u.phone,COUNT(a.id) visits,MAX(a.appointment_date) last_visit
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=$uid GROUP BY u.id ORDER BY last_visit DESC");
while($row=$r->fetch_assoc()) $patients[]=$row;

// ── Prescriptions ──────────────────────────────────────────────
$prescriptions = [];
$r = $conn->query("SELECT p.*,u.full_name patient_name,a.appointment_date
    FROM prescriptions p JOIN users u ON p.patient_id=u.id
    LEFT JOIN appointments a ON p.appointment_id=a.id
    WHERE p.doctor_id=$uid ORDER BY p.uploaded_at DESC");
while($row=$r->fetch_assoc()) $prescriptions[]=$row;

// ── History ────────────────────────────────────────────────────
$history = [];
$r = $conn->query("SELECT a.*,u.full_name patient_name
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=$uid ORDER BY a.appointment_date DESC,a.appointment_time DESC");
while($row=$r->fetch_assoc()) $history[]=$row;

// ── Notifications ──────────────────────────────────────────────
$notifs = [];
$r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 15");
while($row=$r->fetch_assoc()) $notifs[]=$row;

jsonResponse([
    'stats'         => ['today'=>$s_today,'pending'=>$s_pending,'completed'=>$s_done,'patients'=>$s_pts],
    'unread'        => (int)$unread,
    'today_appts'   => $today_appts,
    'pending'       => $pending,
    'confirmed'     => $confirmed,
    'patients'      => $patients,
    'prescriptions' => $prescriptions,
    'history'       => $history,
    'notifications' => $notifs,
]);
?>
