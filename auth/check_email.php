<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$email = sanitize($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['available'=>false,'message'=>'Invalid email format.']);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    jsonResponse(['available'=>false,'message'=>'This email is already registered. Please sign in.']);
} else {
    jsonResponse(['available'=>true]);
}
?>
