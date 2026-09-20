<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// ── Stats ──────────────────────────────────────────────────────
$s_docs    = $conn->query("SELECT COUNT(*) c FROM users WHERE role='doctor' AND is_active=1")->fetch_assoc()['c'];
$s_patients= $conn->query("SELECT COUNT(*) c FROM users WHERE role='patient' AND is_active=1")->fetch_assoc()['c'];
$s_today   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURDATE()")->fetch_assoc()['c'];
$s_pending = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];

// ── All appointments ───────────────────────────────────────────
$appointments = [];
$r = $conn->query("SELECT a.*,p.full_name patient_name,d.full_name doctor_name,dp.specialization
    FROM appointments a
    JOIN users p ON a.patient_id=p.id JOIN users d ON a.doctor_id=d.id
    LEFT JOIN doctor_profiles dp ON dp.user_id=d.id
    ORDER BY a.appointment_date DESC,a.appointment_time DESC LIMIT 100");
while($row=$r->fetch_assoc()) $appointments[]=$row;

// ── Doctors ────────────────────────────────────────────────────
$doctors = [];
$r = $conn->query("SELECT u.*,dp.specialization,dp.qualification,dp.experience_years,
    dp.consultation_fee,dp.is_available
    FROM users u LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE u.role='doctor' ORDER BY u.full_name");
while($row=$r->fetch_assoc()) $doctors[]=$row;

// ── Patients ───────────────────────────────────────────────────
$patients = [];
$r = $conn->query("SELECT u.*,COUNT(a.id) total_appts
    FROM users u LEFT JOIN appointments a ON a.patient_id=u.id
    WHERE u.role='patient' GROUP BY u.id ORDER BY u.created_at DESC");
while($row=$r->fetch_assoc()) $patients[]=$row;

// ── Prescriptions ──────────────────────────────────────────────
$prescriptions = [];
$r = $conn->query("SELECT p.*,pt.full_name patient_name,d.full_name doctor_name,a.appointment_date
    FROM prescriptions p JOIN users pt ON p.patient_id=pt.id JOIN users d ON p.doctor_id=d.id
    LEFT JOIN appointments a ON p.appointment_id=a.id
    ORDER BY p.uploaded_at DESC");
while($row=$r->fetch_assoc()) $prescriptions[]=$row;

// ── Billing ────────────────────────────────────────────────────
$billing = [];
$r = $conn->query("SELECT br.*,u.full_name patient_name,a.appointment_date,d.full_name doctor_name
    FROM billing_receipts br JOIN users u ON br.patient_id=u.id
    LEFT JOIN appointments a ON br.appointment_id=a.id
    LEFT JOIN users d ON a.doctor_id=d.id
    ORDER BY br.uploaded_at DESC");
while($row=$r->fetch_assoc()) $billing[]=$row;

jsonResponse([
    'stats'         => ['doctors'=>$s_docs,'patients'=>$s_patients,'today'=>$s_today,'pending'=>$s_pending],
    'appointments'  => $appointments,
    'doctors'       => $doctors,
    'patients'      => $patients,
    'prescriptions' => $prescriptions,
    'billing'       => $billing,
]);
?>
