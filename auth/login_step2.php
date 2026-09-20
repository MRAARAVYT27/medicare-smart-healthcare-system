<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$email = trim(sanitize($data['email'] ?? ''));
$otp   = trim((string)($data['otp'] ?? ''));  // cast to string to handle numeric JSON values

if (!$email || !$otp) {
    jsonResponse(['success'=>false,'message'=>'Missing email or OTP.']);
}

// Verify OTP — check exact match, not used, not expired
$stmt = $conn->prepare("SELECT id FROM otp_verifications WHERE email=? AND otp=? AND purpose='login' AND is_used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    jsonResponse(['success'=>false,'message'=>'Invalid or expired OTP.']);
}
$otpRow = $res->fetch_assoc();

// Mark OTP used
$stmt = $conn->prepare("UPDATE otp_verifications SET is_used=1 WHERE id=?");
$stmt->bind_param('i', $otpRow['id']);
$stmt->execute();

// Fetch user
$stmt = $conn->prepare("SELECT id,full_name,email,role,profile_photo,is_active FROM users WHERE email=?");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    jsonResponse(['success'=>false,'message'=>'User not found.']);
}
$user = $res->fetch_assoc();

if (!$user['is_active']) {
    jsonResponse(['success'=>false,'message'=>'Your account is deactivated.']);
}

// Set session
setUserSession($user);
clearPendingRoleCookie();

// Absolute path so redirect works regardless of subfolder name
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$redirectMap = [
    'patient' => $base . '/patient/dashboard.php',
    'doctor'  => $base . '/doctor/dashboard.php',
    'admin'   => $base . '/admin/dashboard.php',
];

jsonResponse(['success'=>true,'redirect'=> $redirectMap[$user['role']] ?? $base . '/index.php']);
?>
