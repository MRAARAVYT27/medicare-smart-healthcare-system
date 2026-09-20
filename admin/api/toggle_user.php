<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data      = json_decode(file_get_contents('php://input'), true);
$user_id   = (int)($data['user_id']   ?? 0);
$is_active = (int)($data['is_active'] ?? 0); // 0 or 1

if (!$user_id) {
    jsonResponse(['success' => false, 'message' => 'Invalid user.']);
}

// Prevent admin from deactivating themselves
$admin_id = (int)$_SESSION['user_id'];
if ($user_id === $admin_id) {
    jsonResponse(['success' => false, 'message' => 'You cannot deactivate your own account.']);
}

// Only allow toggling doctors and patients, not other admins
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    jsonResponse(['success' => false, 'message' => 'User not found.']);
}
if ($row['role'] === 'admin') {
    jsonResponse(['success' => false, 'message' => 'Cannot deactivate another admin.']);
}

$stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
$stmt->bind_param('ii', $is_active, $user_id);

if ($stmt->execute()) {
    $action = $is_active ? 'activated' : 'deactivated';
    jsonResponse(['success' => true, 'message' => "User {$action} successfully."]);
} else {
    jsonResponse(['success' => false, 'message' => 'Update failed.']);
}
?>
