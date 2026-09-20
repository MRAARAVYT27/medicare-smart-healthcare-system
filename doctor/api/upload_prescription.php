<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('doctor','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$uid     = (int)$_SESSION['user_id'];
$appt_id = (int)($_POST['appointment_id'] ?? 0);
$pat_id  = (int)($_POST['patient_id']     ?? 0);
$notes   = sanitize($_POST['notes']       ?? '');

if (!$appt_id || !$pat_id) jsonResponse(['success'=>false,'message'=>'Missing required fields.']);
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success'=>false,'message'=>'File upload failed or no file selected.']);
}

// Verify appointment belongs to this doctor
$stmt = $conn->prepare("SELECT id FROM appointments WHERE id=? AND doctor_id=?");
$stmt->bind_param('ii', $appt_id, $uid);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) jsonResponse(['success'=>false,'message'=>'Appointment not found.']);

$file     = $_FILES['file'];
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed  = ['pdf','jpg','jpeg','png','gif','webp'];
if (!in_array($ext, $allowed)) jsonResponse(['success'=>false,'message'=>'Invalid file type. Allowed: PDF, JPG, PNG.']);
if ($file['size'] > 10 * 1024 * 1024) jsonResponse(['success'=>false,'message'=>'File too large. Maximum 10MB.']);

$file_type = $ext === 'pdf' ? 'pdf' : 'image';
$filename  = 'rx_' . $appt_id . '_' . time() . '.' . $ext;
$upload_dir = __DIR__ . '/../../uploads/prescriptions/';

if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
    jsonResponse(['success'=>false,'message'=>'Could not save file. Check folder permissions.']);
}

// Check if prescription already exists for this appointment — update or insert
$stmt = $conn->prepare("SELECT id FROM prescriptions WHERE appointment_id=? AND doctor_id=?");
$stmt->bind_param('ii', $appt_id, $uid);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $stmt = $conn->prepare("UPDATE prescriptions SET file_path=?,file_type=?,notes=?,uploaded_at=NOW() WHERE id=?");
    $stmt->bind_param('sssi', $filename, $file_type, $notes, $existing['id']);
} else {
    $stmt = $conn->prepare("INSERT INTO prescriptions (appointment_id,doctor_id,patient_id,file_path,file_type,notes) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('iiisss', $appt_id, $uid, $pat_id, $filename, $file_type, $notes);
}
if (!$stmt->execute()) jsonResponse(['success'=>false,'message'=>'Database error saving prescription.']);

// Notify patient
$doc = $conn->query("SELECT full_name FROM users WHERE id=$uid")->fetch_assoc();
$notif_msg = "Dr. {$doc['full_name']} has uploaded a prescription for your appointment.";
$notif_title = "New Prescription";
$stmt = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'prescription')");
$stmt->bind_param('iss', $pat_id, $notif_title, $notif_msg);
$stmt->execute();

// Mark appointment as completed if confirmed
$conn->query("UPDATE appointments SET status='completed' WHERE id=$appt_id AND status='confirmed'");

jsonResponse(['success'=>true,'message'=>'Prescription uploaded successfully!','file'=>$filename]);
?>
