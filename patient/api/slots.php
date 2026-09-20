<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date      = $_GET['date'] ?? '';

if (!$doctor_id || !$date) jsonResponse(['booked' => []]);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonResponse(['booked' => []]);

// Get already-booked slots for this doctor on this date
$stmt = $conn->prepare("SELECT appointment_time FROM appointments
    WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','confirmed')");
$stmt->bind_param('is', $doctor_id, $date);
$stmt->execute();
$res  = $stmt->get_result();
$booked = [];
while ($row = $res->fetch_assoc()) {
    $booked[] = $row['appointment_time'];
}

// If user is booking for today, also tell client what the current time is
// so it can mark already-passed slots as unavailable.
$is_today     = ($date === date('Y-m-d'));
$current_time = date('H:i:s');

jsonResponse([
    'booked'       => $booked,
    'is_today'     => $is_today,
    'current_time' => $current_time
]);
?>
