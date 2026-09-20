<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('doctor','../../index.php');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/intake_helper.php';
header('Content-Type: application/json');

// Make sure the intake table exists even if the SQL migration was never
// run manually — prevents prepare() from returning false below.
ensureIntakeTable($conn);

$appointment_id = (int)($_GET['appointment_id'] ?? 0);
$uid            = (int)$_SESSION['user_id'];

if (!$appointment_id) {
    jsonResponse(['success'=>false,'message'=>'Missing appointment ID.']);
}

// Verify the appointment belongs to this doctor
$stmt = $conn->prepare("SELECT id FROM appointments WHERE id=? AND doctor_id=?");
if (!$stmt) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$conn->error]);
}
$stmt->bind_param('ii', $appointment_id, $uid);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    jsonResponse(['success'=>false,'message'=>'Appointment not found.']);
}

// Fetch intake
$stmt = $conn->prepare("SELECT * FROM appointment_intake WHERE appointment_id=?");
if (!$stmt) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$conn->error]);
}
$stmt->bind_param('i', $appointment_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    jsonResponse(['success'=>false,'message'=>'No intake form submitted for this appointment.']);
}

jsonResponse(['success'=>true,'intake'=>$row]);
?>
