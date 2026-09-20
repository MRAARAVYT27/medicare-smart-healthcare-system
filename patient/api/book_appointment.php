<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
header('Content-Type: application/json');

$data      = json_decode(file_get_contents('php://input'), true);
$uid       = (int)$_SESSION['user_id'];
$doctor_id = (int)($data['doctor_id'] ?? 0);
$date      = sanitize($data['date']   ?? '');
$time      = sanitize($data['time']   ?? '');
$reason    = sanitize($data['reason'] ?? '');

if (!$doctor_id || !$date || !$time) jsonResponse(['success'=>false,'message'=>'Missing required fields.']);

// Validate date not in past
if ($date < date('Y-m-d')) jsonResponse(['success'=>false,'message'=>'Cannot book appointments in the past.']);

// Validate time not in past for same-day bookings
if ($date === date('Y-m-d') && $time <= date('H:i:s')) {
    jsonResponse(['success'=>false,'message'=>'This time slot has already passed. Please choose a later slot.']);
}

// Validate time format
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) jsonResponse(['success'=>false,'message'=>'Invalid time format.']);

// Check doctor exists and is available
$stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, dp.specialization, dp.consultation_fee,
    dp.available_days, dp.slot_start, dp.slot_end, dp.slot_duration
    FROM users u JOIN doctor_profiles dp ON dp.user_id=u.id
    WHERE u.id=? AND u.role='doctor' AND u.is_active=1 AND dp.is_available=1");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
if (!$doc) jsonResponse(['success'=>false,'message'=>'Doctor not found or unavailable.']);

// Check the day is allowed
$dayName = date('D', strtotime($date)); // e.g. "Mon"
$allowed = array_map('trim', explode(',', $doc['available_days'] ?? 'Mon,Tue,Wed,Thu,Fri'));
$allowed = array_map(fn($d) => substr($d, 0, 3), $allowed);
if (!in_array($dayName, $allowed)) {
    jsonResponse(['success'=>false,'message'=>"Doctor is not available on {$dayName}."]);
}

// Check slot is not already booked
$stmt = $conn->prepare("SELECT id FROM appointments
    WHERE doctor_id=? AND appointment_date=? AND appointment_time=?
    AND status IN ('pending','confirmed')");
$stmt->bind_param('iss', $doctor_id, $date, $time);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) jsonResponse(['success'=>false,'message'=>'This slot is already booked. Please choose another time.']);

// Check patient doesn't have another booking same day same doctor
$stmt = $conn->prepare("SELECT id FROM appointments
    WHERE patient_id=? AND doctor_id=? AND appointment_date=? AND status IN ('pending','confirmed')");
$stmt->bind_param('iis', $uid, $doctor_id, $date);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) jsonResponse(['success'=>false,'message'=>'You already have an appointment with this doctor on this date.']);

// Insert appointment
$stmt = $conn->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'pending')");
$stmt->bind_param('iisss', $uid, $doctor_id, $date, $time, $reason);

if (!$stmt->execute()) jsonResponse(['success'=>false,'message'=>'Could not book appointment: '.$conn->error]);
$appt_id = $conn->insert_id;

// Get patient info
$patient = $conn->query("SELECT full_name, email FROM users WHERE id=$uid")->fetch_assoc();

// Create notification for patient
$notif_msg = "Your appointment with Dr. {$doc['full_name']} ({$doc['specialization']}) on ".date('M j, Y', strtotime($date))." at ".date('g:i A', strtotime($time))." has been booked.";
$notif_title = "Appointment Booked";
$stmt = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'appointment')");
$stmt->bind_param('iss', $uid, $notif_title, $notif_msg);
$stmt->execute();

// Create notification for doctor
$doc_notif = "New appointment from {$patient['full_name']} on ".date('M j, Y', strtotime($date))." at ".date('g:i A', strtotime($time)).".";
$doc_notif_title = "New Appointment Request";
$stmt = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'appointment')");
$stmt->bind_param('iss', $doctor_id, $doc_notif_title, $doc_notif);
$stmt->execute();

// Send confirmation email to patient
$emailDetails = [
    'doctor_name'    => 'Dr. ' . $doc['full_name'],
    'specialization' => $doc['specialization'],
    'date'           => date('D, M j, Y', strtotime($date)),
    'time'           => date('g:i A', strtotime($time)),
    'notes'          => $reason,
];
sendAppointmentEmail($patient['email'], $patient['full_name'], $emailDetails, 'booked');

// Send email to doctor too
sendAppointmentEmail($doc['email'], 'Dr. ' . $doc['full_name'], array_merge($emailDetails, ['doctor_name' => $patient['full_name']]), 'booked');

jsonResponse(['success'=>true,'message'=>'Appointment booked successfully!','appointment_id'=>$appt_id]);
?>
