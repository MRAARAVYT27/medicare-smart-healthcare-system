<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('patient','../../index.php');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/intake_helper.php';
header('Content-Type: application/json');

// Make sure the intake table exists even if the SQL migration was never
// run manually — prevents prepare() from returning false below.
ensureIntakeTable($conn);

$data           = json_decode(file_get_contents('php://input'), true);
$uid            = (int)$_SESSION['user_id'];
$appointment_id = (int)($data['appointment_id'] ?? 0);

if (!$appointment_id) {
    jsonResponse(['success'=>false,'message'=>'Missing appointment ID.']);
}

// Verify the appointment belongs to this patient
$stmt = $conn->prepare("SELECT id FROM appointments WHERE id=? AND patient_id=?");
if (!$stmt) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$conn->error]);
}
$stmt->bind_param('ii', $appointment_id, $uid);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    jsonResponse(['success'=>false,'message'=>'Appointment not found.']);
}

// Validate answers — all 10 must be 1-4
$answers = [];
for ($i = 1; $i <= 10; $i++) {
    $val = (int)($data["q$i"] ?? 0);
    if ($val < 1 || $val > 4) {
        jsonResponse(['success'=>false,'message'=>"Please answer all 10 questions (question $i is missing or invalid)."]);
    }
    $answers[] = $val;
}

// Upsert (in case patient re-submits)
$stmt = $conn->prepare(
    "INSERT INTO appointment_intake
        (appointment_id, patient_id, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        q1=VALUES(q1), q2=VALUES(q2), q3=VALUES(q3), q4=VALUES(q4),
        q5=VALUES(q5), q6=VALUES(q6), q7=VALUES(q7), q8=VALUES(q8),
        q9=VALUES(q9), q10=VALUES(q10), submitted_at=CURRENT_TIMESTAMP"
);
if (!$stmt) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$conn->error]);
}
$stmt->bind_param('iiiiiiiiiiii',
    $appointment_id, $uid,
    $answers[0], $answers[1], $answers[2], $answers[3], $answers[4],
    $answers[5], $answers[6], $answers[7], $answers[8], $answers[9]
);

if ($stmt->execute()) {
    jsonResponse(['success'=>true,'message'=>'Intake saved successfully.']);
} else {
    jsonResponse(['success'=>false,'message'=>'Could not save intake: '.$conn->error]);
}
?>
