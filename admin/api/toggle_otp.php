<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$data         = json_decode(file_get_contents('php://input'), true);
$user_id      = (int)($data['user_id']      ?? 0);
$otp_required = (int)($data['otp_required'] ?? 1);

if (!$user_id) {
    jsonResponse(['success'=>false, 'message'=>'Missing user ID.']);
}

// Force to 0 or 1
$otp_required = $otp_required ? 1 : 0;

$stmt = $conn->prepare("UPDATE users SET otp_required=? WHERE id=?");
$stmt->bind_param('ii', $otp_required, $user_id);

if (!$stmt->execute()) {
    jsonResponse(['success'=>false, 'message'=>'Database error: ' . $conn->error]);
}

// Read back what was actually persisted, for verification.
$check = $conn->prepare("SELECT otp_required FROM users WHERE id=?");
$check->bind_param('i', $user_id);
$check->execute();
$row = $check->get_result()->fetch_assoc();

jsonResponse([
    'success' => true,
    'message' => $otp_required ? 'OTP verification enabled.' : 'OTP verification disabled.',
    'otp_required' => $otp_required,
    'debug_persisted_value' => $row['otp_required'],
    'debug_persisted_type'  => gettype($row['otp_required']),
]);
?>
