<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$data            = json_decode(file_get_contents('php://input'), true);
$new_email       = trim($data['new_email']       ?? '');
$new_password    = $data['new_password']          ?? '';
$current_password= $data['current_password']      ?? '';

$uid = currentUser()['id'];

// Must supply current password
if (!$current_password) {
    jsonResponse(['success'=>false, 'message'=>'Current password is required.']);
}

// Fetch current hash
$stmt = $conn->prepare("SELECT password, email FROM users WHERE id=? AND role='admin'");
$stmt->bind_param('i', $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    jsonResponse(['success'=>false, 'message'=>'Admin account not found.']);
}
if (!password_verify($current_password, $row['password'])) {
    jsonResponse(['success'=>false, 'message'=>'Current password is incorrect.']);
}

// Nothing to update?
if (!$new_email && !$new_password) {
    jsonResponse(['success'=>false, 'message'=>'Provide a new email or new password to update.']);
}

$updates = [];
$params  = [];
$types   = '';

// Email update
if ($new_email) {
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success'=>false, 'message'=>'Invalid email address format.']);
    }
    if ($new_email === $row['email']) {
        jsonResponse(['success'=>false, 'message'=>'New email is the same as the current email.']);
    }
    // Check not taken by another user
    $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id != ?");
    $chk->bind_param('si', $new_email, $uid);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        jsonResponse(['success'=>false, 'message'=>'That email is already used by another account.']);
    }
    $updates[] = 'email=?';
    $params[]  = $new_email;
    $types    .= 's';
}

// Password update
if ($new_password) {
    if (strlen($new_password) < 8) {
        jsonResponse(['success'=>false, 'message'=>'New password must be at least 8 characters.']);
    }
    $updates[] = 'password=?';
    $params[]  = password_hash($new_password, PASSWORD_DEFAULT);
    $types    .= 's';
}

$types    .= 'i';
$params[]  = $uid;

$sql  = "UPDATE users SET " . implode(', ', $updates) . " WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    jsonResponse(['success'=>false, 'message'=>'Database error. Please try again.']);
}

// Refresh session with new email if changed
if ($new_email) {
    $_SESSION['user']['email'] = $new_email;
}

$msg = [];
if ($new_email)    $msg[] = 'email updated';
if ($new_password) $msg[] = 'password updated';

jsonResponse(['success'=>true, 'message'=>'Admin ' . implode(' and ', $msg) . ' successfully.', 'reload'=>true]);
?>
