<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

$data     = json_decode(file_get_contents('php://input'), true);
$email    = sanitize($data['email']    ?? '');
$password = $data['password']          ?? '';

if (!$email || !$password) {
    jsonResponse(['success'=>false,'message'=>'Please enter your email and password.']);
}

// Fetch user
$stmt = $conn->prepare("SELECT id,full_name,email,password,role,is_verified,is_active,otp_required,profile_photo FROM users WHERE email=?");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    jsonResponse(['success'=>false,'message'=>'No account found with this email.']);
}

$user = $res->fetch_assoc();

if (!$user['is_active']) {
    jsonResponse(['success'=>false,'message'=>'Your account has been deactivated. Contact support.']);
}
if (!password_verify($password, $user['password'])) {
    jsonResponse(['success'=>false,'message'=>'Incorrect password. Please try again.']);
}

// ── OTP DISABLED for this user? Log in directly. ─────────────
if (isset($user['otp_required']) && (int)$user['otp_required'] === 0) {
    setPendingRoleCookie($user['role']);  // ensure correct session is open
    setUserSession($user);
    clearPendingRoleCookie();
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    $redirectMap = [
        'patient' => $base . '/patient/dashboard.php',
        'doctor'  => $base . '/doctor/dashboard.php',
        'admin'   => $base . '/admin/dashboard.php',
    ];
    jsonResponse([
        'success'  => true,
        'skip_otp' => true,
        'redirect' => $redirectMap[$user['role']] ?? $base . '/index.php',
        'message'  => 'Login successful. Redirecting…'
    ]);
}

// Credentials OK — set pending role cookie so step2 opens the right session
setPendingRoleCookie($user['role']);

// Send login OTP — invalidate old first
$stmt = $conn->prepare("UPDATE otp_verifications SET is_used=1 WHERE email=? AND purpose='login' AND is_used=0");
$stmt->bind_param('s', $email);
$stmt->execute();

$otp = generateOTP(6);

$stmt = $conn->prepare("INSERT INTO otp_verifications (email,otp,purpose,expires_at) VALUES (?,?,'login', DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();

$result = sendOTPEmail($email, $user['full_name'], $otp, 'login');

if ($result['success']) {
    jsonResponse(['success'=>true,'message'=>'OTP sent to your email.']);
} else {
    jsonResponse(['success'=>false,'message'=>'Could not send OTP: '.$result['message']]);
}
?>
