<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data     = json_decode(file_get_contents('php://input'), true);
$uid      = (int)$_SESSION['user_id'];
$symptoms = sanitize($data['symptoms'] ?? '');
$result   = $data['result'] ?? '';

if (!$symptoms || !$result) jsonResponse(['success'=>false]);

// Extract suggested specialist from result (simple heuristic)
$specialist = '';
if (preg_match('/(?:consult|see|visit|refer|specialist)[:\s]+(?:a\s+)?([A-Za-z\s]+(?:Doctor|Physician|Specialist|Surgeon|ist))/i', $result, $m)) {
    $specialist = trim($m[1]);
}

$stmt = $conn->prepare("INSERT INTO symptom_analyses (patient_id,symptoms,analysis_result,suggested_specialist) VALUES (?,?,?,?)");
$stmt->bind_param('isss', $uid, $symptoms, $result, $specialist);
$stmt->execute();

jsonResponse(['success'=>true]);
?>
