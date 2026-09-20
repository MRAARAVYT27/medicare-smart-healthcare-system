<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Invalid prescription ID.']);
}

// Fetch file path before deleting so we can remove the file too
$stmt = $conn->prepare("SELECT file_path FROM prescriptions WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    jsonResponse(['success' => false, 'message' => 'Prescription not found.']);
}

// Delete DB record
$stmt = $conn->prepare("DELETE FROM prescriptions WHERE id = ?");
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    jsonResponse(['success' => false, 'message' => 'Failed to delete prescription.']);
}

// Delete physical file if it exists
if (!empty($row['file_path'])) {
    $file_path = __DIR__ . '/../../uploads/prescriptions/' . basename($row['file_path']);
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
}

jsonResponse(['success' => true, 'message' => 'Prescription deleted.']);
?>
