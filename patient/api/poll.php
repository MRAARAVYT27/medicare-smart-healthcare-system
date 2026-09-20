<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient', '../../index.php');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');
$uid = currentUser()['id'];

// ── Stats ──────────────────────────────────────────────────────
$s_pend = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid AND status='pending'")->fetch_assoc()['c'];
$s_conf = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid AND status='confirmed'")->fetch_assoc()['c'];
$s_comp = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid AND status='completed'")->fetch_assoc()['c'];
$s_prx  = $conn->query("SELECT COUNT(*) c FROM prescriptions WHERE patient_id=$uid")->fetch_assoc()['c'];
$unread = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];

// ── Upcoming appointments ──────────────────────────────────────
$upcoming = [];
$r = $conn->query("SELECT a.*,u.full_name doctor_name,dp.specialization,dp.consultation_fee
    FROM appointments a JOIN users u ON a.doctor_id=u.id
    LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE a.patient_id=$uid AND a.status IN ('pending','confirmed') AND a.appointment_date>=CURDATE()
    ORDER BY a.appointment_date,a.appointment_time LIMIT 5");
while($row=$r->fetch_assoc()) $upcoming[]=$row;

// ── Active appointments ────────────────────────────────────────
$active = [];
$r = $conn->query("SELECT a.*,u.full_name doctor_name,dp.specialization,dp.consultation_fee
    FROM appointments a JOIN users u ON a.doctor_id=u.id
    LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE a.patient_id=$uid AND a.status IN ('pending','confirmed')
    ORDER BY a.appointment_date DESC,a.appointment_time DESC");
while($row=$r->fetch_assoc()) $active[]=$row;

// ── Doctors list (for booking) ─────────────────────────────────
$doctors = [];
$r = $conn->query("SELECT u.id,u.full_name,dp.specialization,dp.consultation_fee,dp.experience_years,
    dp.qualification,dp.available_days,dp.slot_start,dp.slot_end,dp.slot_duration
    FROM users u JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE u.role='doctor' AND u.is_active=1 AND dp.is_available=1
    ORDER BY dp.specialization,u.full_name");
while($row=$r->fetch_assoc()) $doctors[]=$row;

// ── Prescriptions ──────────────────────────────────────────────
$prescriptions = [];
$r = $conn->query("SELECT p.*,u.full_name doctor_name,dp.specialization,a.appointment_date
    FROM prescriptions p JOIN users u ON p.doctor_id=u.id
    LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    LEFT JOIN appointments a ON p.appointment_id=a.id
    WHERE p.patient_id=$uid ORDER BY p.uploaded_at DESC");
while($row=$r->fetch_assoc()) $prescriptions[]=$row;

// ── Billing ────────────────────────────────────────────────────
$billing = [];
$r = $conn->query("SELECT br.*,a.appointment_date,u.full_name doctor_name,dp.specialization
    FROM billing_receipts br LEFT JOIN appointments a ON br.appointment_id=a.id
    LEFT JOIN users u ON a.doctor_id=u.id LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE br.patient_id=$uid ORDER BY br.uploaded_at DESC");
while($row=$r->fetch_assoc()) $billing[]=$row;

// ── History ────────────────────────────────────────────────────
$history = [];
$r = $conn->query("SELECT a.*,u.full_name doctor_name,dp.specialization,dp.consultation_fee
    FROM appointments a JOIN users u ON a.doctor_id=u.id
    LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE a.patient_id=$uid ORDER BY a.appointment_date DESC,a.appointment_time DESC");
while($row=$r->fetch_assoc()) $history[]=$row;

// ── Notifications ──────────────────────────────────────────────
$notifs = [];
$r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 15");
while($row=$r->fetch_assoc()) $notifs[]=$row;

jsonResponse([
    'stats'         => ['pending'=>$s_pend,'confirmed'=>$s_conf,'completed'=>$s_comp,'prescriptions'=>$s_prx],
    'unread'        => (int)$unread,
    'upcoming'      => $upcoming,
    'active'        => $active,
    'doctors'       => $doctors,
    'prescriptions' => $prescriptions,
    'billing'       => $billing,
    'history'       => $history,
    'notifications' => $notifs,
]);
?>
