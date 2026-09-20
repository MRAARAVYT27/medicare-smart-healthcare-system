<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$otp           = trim((string)($data['otp'] ?? ''));  // cast to string, handles numeric JSON
$email         = trim(sanitize($data['email']           ?? ''));
$password      = $data['password']                 ?? '';
$role          = sanitize($data['role']            ?? 'patient');
$full_name     = sanitize($data['full_name']       ?? '');
$phone         = sanitize($data['phone']           ?? '');
$dob           = sanitize($data['dob']             ?? '');
$gender        = sanitize($data['gender']          ?? '');
$specialization= sanitize($data['specialization'] ?? '');
$qualification = sanitize($data['qualification']  ?? '');
$experience    = (int)($data['experience']         ?? 0);
$fee           = (float)($data['fee']              ?? 0);

// Validate required fields
if (!$email || !$password || !$full_name || !$otp) {
    jsonResponse(['success'=>false,'message'=>'Missing required fields.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success'=>false,'message'=>'Invalid email address.']);
}
if (strlen($password) < 8) {
    jsonResponse(['success'=>false,'message'=>'Password too short.']);
}
if (!in_array($role, ['patient'])) {
    jsonResponse(['success'=>false,'message'=>'Only patient accounts can be self-registered. Contact the administrator.']);
}

// Verify OTP
$stmt = $conn->prepare("SELECT id FROM otp_verifications WHERE email=? AND otp=? AND purpose='registration' AND is_used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    jsonResponse(['success'=>false,'message'=>'Invalid or expired OTP. Please request a new one.']);
}
$otpRow = $res->fetch_assoc();

// Check email not taken (race condition guard)
$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    jsonResponse(['success'=>false,'message'=>'Email already registered.']);
}

// Hash password
$hashed = password_hash($password, PASSWORD_BCRYPT);

// Insert user
$dobVal = $dob ?: null;
$stmt = $conn->prepare("INSERT INTO users (full_name,email,password,role,phone,date_of_birth,gender,is_verified) VALUES (?,?,?,?,?,?,?,1)");
$stmt->bind_param('sssssss', $full_name, $email, $hashed, $role, $phone, $dobVal, $gender);

if (!$stmt->execute()) {
    jsonResponse(['success'=>false,'message'=>'Registration failed. Please try again.']);
}
$userId = $conn->insert_id;

// If doctor, insert profile
if ($role === 'doctor' && $specialization) {
    $stmt = $conn->prepare("INSERT INTO doctor_profiles (user_id,specialization,qualification,experience_years,consultation_fee) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issid', $userId, $specialization, $qualification, $experience, $fee);
    $stmt->execute();
}

// Mark OTP used
$stmt = $conn->prepare("UPDATE otp_verifications SET is_used=1 WHERE id=?");
$stmt->bind_param('i', $otpRow['id']);
$stmt->execute();

// Create session
$user = ['id'=>$userId,'full_name'=>$full_name,'email'=>$email,'role'=>$role,'profile_photo'=>''];
setPendingRoleCookie($role);
setUserSession($user);
clearPendingRoleCookie();

$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$redirectMap = ['patient'=> $base.'/patient/dashboard.php','doctor'=> $base.'/doctor/dashboard.php','admin'=> $base.'/admin/dashboard.php'];
jsonResponse(['success'=>true,'redirect'=>$redirectMap[$role]]);
?>
