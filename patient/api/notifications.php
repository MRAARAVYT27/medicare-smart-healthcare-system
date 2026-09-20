<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$uid    = (int)$_SESSION['user_id'];
$action = $data['action'] ?? '';

if ($action === 'mark_all') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    jsonResponse(['success'=>true]);
} elseif ($action === 'mark_read') {
    $id = (int)($data['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    jsonResponse(['success'=>true]);
} else {
    jsonResponse(['success'=>false,'message'=>'Unknown action']);
}
?>
