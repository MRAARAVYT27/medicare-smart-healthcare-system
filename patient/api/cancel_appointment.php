<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$uid     = (int)$_SESSION['user_id'];
$appt_id = (int)($data['appointment_id'] ?? 0);

if (!$appt_id) jsonResponse(['success'=>false,'message'=>'Invalid appointment.']);

// Verify this appointment belongs to this patient and is cancellable
$stmt = $conn->prepare("SELECT a.*, u.full_name doc_name, u.email doc_email,
    dp.specialization, p.full_name pat_name, p.email pat_email
    FROM appointments a
    JOIN users u ON a.doctor_id=u.id
    LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
    JOIN users p ON a.patient_id=p.id
    WHERE a.id=? AND a.patient_id=? AND a.status IN ('pending','confirmed')");
$stmt->bind_param('ii', $appt_id, $uid);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) jsonResponse(['success'=>false,'message'=>'Appointment not found or cannot be cancelled.']);

// Update status
$stmt = $conn->prepare("UPDATE appointments SET status='cancelled' WHERE id=?");
$stmt->bind_param('i', $appt_id);
if (!$stmt->execute()) jsonResponse(['success'=>false,'message'=>'Failed to cancel appointment.']);

// Notify patient
$msg = "Your appointment with Dr. {$appt['doc_name']} on ".date('M j, Y', strtotime($appt['appointment_date']))." has been cancelled.";
$stmt = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'Appointment Cancelled',?,'appointment')");
$stmt->bind_param('is', $uid, $msg);
$stmt->execute();

// Notify doctor
$doc_msg = "Appointment with {$appt['pat_name']} on ".date('M j, Y', strtotime($appt['appointment_date']))." at ".date('g:i A', strtotime($appt['appointment_time']))." has been cancelled by the patient.";
$doc_uid = (int)$appt['doctor_id'];
$stmt = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'Appointment Cancelled',?,'appointment')");
$stmt->bind_param('is', $doc_uid, $doc_msg);
$stmt->execute();

// Send cancellation emails
$details = [
    'doctor_name'    => 'Dr. ' . $appt['doc_name'],
    'specialization' => $appt['specialization'],
    'date'           => date('D, M j, Y', strtotime($appt['appointment_date'])),
    'time'           => date('g:i A', strtotime($appt['appointment_time'])),
    'notes'          => '',
];
sendAppointmentEmail($appt['pat_email'], $appt['pat_name'], $details, 'cancelled');

jsonResponse(['success'=>true,'message'=>'Appointment cancelled.']);
?>
