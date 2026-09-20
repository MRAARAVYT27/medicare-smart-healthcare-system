<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('doctor','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$uid     = (int)$_SESSION['user_id'];
$appt_id = (int)($data['appointment_id'] ?? 0);
$status  = sanitize($data['status'] ?? '');

if (!$appt_id || !in_array($status, ['confirmed','completed','cancelled'])) {
    jsonResponse(['success'=>false,'message'=>'Invalid request.']);
}

// Verify this appointment belongs to this doctor
$stmt = $conn->prepare("SELECT a.*,u.full_name pat_name,u.email pat_email,u.id pat_id
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.id=? AND a.doctor_id=?");
$stmt->bind_param('ii', $appt_id, $uid);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) jsonResponse(['success'=>false,'message'=>'Appointment not found.']);

$stmt = $conn->prepare("UPDATE appointments SET status=? WHERE id=?");
$stmt->bind_param('si', $status, $appt_id);
if (!$stmt->execute()) jsonResponse(['success'=>false,'message'=>'Update failed.']);

// Notify patient
$doc = $conn->query("SELECT full_name FROM users WHERE id=$uid")->fetch_assoc();
$pat_id = (int)$appt['pat_id'];
$title  = match($status) {
    'confirmed' => 'Appointment Confirmed',
    'completed' => 'Appointment Completed',
    'cancelled' => 'Appointment Cancelled',
    default     => 'Appointment Update'
};
$msg = "Your appointment with Dr. {$doc['full_name']} on ".date('M j, Y', strtotime($appt['appointment_date']))." has been {$status}.";
$stmt = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'appointment')");
$stmt->bind_param('iss', $pat_id, $title, $msg);
$stmt->execute();

jsonResponse(['success'=>true,'message'=>"Appointment {$status}."]);
?>
