<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$appt_id = (int)($data['appointment_id'] ?? 0);
$status  = sanitize($data['status'] ?? '');

if (!$appt_id || !in_array($status, ['confirmed', 'cancelled', 'completed'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid request.']);
}

// Fetch appointment + names for notification
$stmt = $conn->prepare(
    "SELECT a.*, p.full_name pat_name, p.id pat_id, d.full_name doc_name, d.id doc_id
     FROM appointments a
     JOIN users p ON a.patient_id = p.id
     JOIN users d ON a.doctor_id  = d.id
     WHERE a.id = ?"
);
$stmt->bind_param('i', $appt_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    jsonResponse(['success' => false, 'message' => 'Appointment not found.']);
}

// Update status
$stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $appt_id);
if (!$stmt->execute()) {
    jsonResponse(['success' => false, 'message' => 'Database update failed.']);
}

// Notify patient
$pat_id = (int)$appt['pat_id'];
$title  = match ($status) {
    'confirmed' => 'Appointment Confirmed',
    'cancelled' => 'Appointment Cancelled',
    'completed' => 'Appointment Completed',
    default     => 'Appointment Update',
};
$msg = "Your appointment with Dr. {$appt['doc_name']} on "
     . date('M j, Y', strtotime($appt['appointment_date']))
     . " has been {$status} by the admin.";

$stmt = $conn->prepare(
    "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'appointment')"
);
$stmt->bind_param('iss', $pat_id, $title, $msg);
$stmt->execute();

// Notify doctor too
$doc_id  = (int)$appt['doc_id'];
$doc_msg = "Appointment with {$appt['pat_name']} on "
         . date('M j, Y', strtotime($appt['appointment_date']))
         . " has been {$status} by the admin.";
$stmt = $conn->prepare(
    "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'appointment')"
);
$stmt->bind_param('iss', $doc_id, $title, $doc_msg);
$stmt->execute();

jsonResponse(['success' => true, 'message' => "Appointment {$status}."]);
?>
