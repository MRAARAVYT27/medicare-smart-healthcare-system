<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$admin_id    = (int)$_SESSION['user_id'];
$appt_id     = (int)($_POST['appointment_id'] ?? 0);
$pat_id      = (int)($_POST['patient_id']     ?? 0);
$amount_paid = (float)($_POST['amount_paid']  ?? 0);
$method      = sanitize($_POST['payment_method'] ?? 'cash');

$allowed_methods = ['cash', 'card', 'upi', 'netbanking', 'insurance'];
if (!$appt_id || !$pat_id) {
    jsonResponse(['success' => false, 'message' => 'Appointment and patient are required.']);
}
if ($amount_paid <= 0) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid amount paid.']);
}
if (!in_array($method, $allowed_methods)) {
    $method = 'cash';
}

// Verify appointment exists
$stmt = $conn->prepare("SELECT a.id, d.full_name doc_name FROM appointments a JOIN users d ON a.doctor_id = d.id WHERE a.id = ?");
$stmt->bind_param('i', $appt_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) {
    jsonResponse(['success' => false, 'message' => 'Appointment not found.']);
}

// Handle optional file upload
$filename = null;
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file    = $_FILES['file'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed)) {
        jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG.']);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(['success' => false, 'message' => 'File too large. Maximum 5MB.']);
    }

    $upload_dir = __DIR__ . '/../../uploads/receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = 'receipt_' . $appt_id . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        jsonResponse(['success' => false, 'message' => 'Could not save file. Check folder permissions.']);
    }
}

// Insert billing record
$stmt = $conn->prepare(
    "INSERT INTO billing_receipts (appointment_id, patient_id, amount_paid, file_path, payment_method, uploaded_by)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('iidssi', $appt_id, $pat_id, $amount_paid, $filename, $method, $admin_id);
if (!$stmt->execute()) {
    jsonResponse(['success' => false, 'message' => 'Failed to save receipt.']);
}

// Notify patient
$formatted_amount = '₹' . number_format($amount_paid, 2);
$notif_title = 'Billing Receipt Available';
$notif_msg   = "Your billing receipt for your appointment with Dr. {$appt['doc_name']} has been uploaded. Amount paid: {$formatted_amount} via {$method}.";

$stmt = $conn->prepare(
    "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'billing')"
);
$stmt->bind_param('iss', $pat_id, $notif_title, $notif_msg);
$stmt->execute();

jsonResponse([
    'success' => true,
    'message' => 'Receipt uploaded and patient notified.',
    'file'    => $filename,
]);
?>
