<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$email   = sanitize($data['email']   ?? '');
$purpose = sanitize($data['purpose'] ?? 'registration');
$name    = sanitize($data['name']    ?? 'User');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success'=>false,'message'=>'Invalid email address.']);
}

// Invalidate previous OTPs for this email+purpose
$stmt = $conn->prepare("UPDATE otp_verifications SET is_used=1 WHERE email=? AND purpose=? AND is_used=0");
$stmt->bind_param('ss', $email, $purpose);
$stmt->execute();

// Generate + store new OTP
$otp = generateOTP(6);
// Use MySQL's own NOW() to avoid PHP/MySQL timezone mismatch
$stmt = $conn->prepare("INSERT INTO otp_verifications (email, otp, purpose, expires_at) VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$stmt->bind_param('sss', $email, $otp, $purpose);

if (!$stmt->execute()) {
    jsonResponse(['success'=>false,'message'=>'Database error. Please try again.']);
}

// Send email
$result = sendOTPEmail($email, $name, $otp, $purpose);

if ($result['success']) {
    jsonResponse(['success'=>true,'message'=>'OTP sent successfully.']);
} else {
    jsonResponse(['success'=>false,'message'=>'Failed to send email: '.$result['message']]);
}
?>
