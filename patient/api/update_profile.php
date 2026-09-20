<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data      = json_decode(file_get_contents('php://input'), true);
$uid       = (int)$_SESSION['user_id'];
$full_name = sanitize($data['full_name'] ?? '');
$phone     = sanitize($data['phone']     ?? '');
$dob       = sanitize($data['dob']       ?? '');
$gender    = sanitize($data['gender']    ?? '');
$address   = sanitize($data['address']   ?? '');

if (!$full_name) jsonResponse(['success'=>false,'message'=>'Name cannot be empty.']);

$dob_val = $dob ?: null;
$stmt = $conn->prepare("UPDATE users SET full_name=?,phone=?,date_of_birth=?,gender=?,address=? WHERE id=?");
$stmt->bind_param('sssssi', $full_name, $phone, $dob_val, $gender, $address, $uid);

if ($stmt->execute()) {
    $_SESSION['full_name'] = $full_name;
    jsonResponse(['success'=>true,'message'=>'Profile updated successfully!']);
} else {
    jsonResponse(['success'=>false,'message'=>'Failed to update profile.']);
}
?>
