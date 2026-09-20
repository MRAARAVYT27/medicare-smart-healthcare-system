<?php
// Doctor profile update
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('doctor','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data          = json_decode(file_get_contents('php://input'), true);
$uid           = (int)$_SESSION['user_id'];
$full_name     = sanitize($data['full_name']     ?? '');
$phone         = sanitize($data['phone']         ?? '');
$qualification = sanitize($data['qualification'] ?? '');
$specialization= sanitize($data['specialization']?? '');
$experience    = (int)($data['experience']       ?? 0);
$fee           = (float)($data['fee']            ?? 0);
$bio           = sanitize($data['bio']           ?? '');

if (!$full_name) jsonResponse(['success'=>false,'message'=>'Name cannot be empty.']);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE users SET full_name=?,phone=? WHERE id=?");
    $stmt->bind_param('ssi', $full_name, $phone, $uid);
    $stmt->execute();

    // Upsert doctor profile
    $stmt = $conn->prepare("INSERT INTO doctor_profiles (user_id,specialization,qualification,experience_years,consultation_fee,bio)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE specialization=VALUES(specialization),qualification=VALUES(qualification),
        experience_years=VALUES(experience_years),consultation_fee=VALUES(consultation_fee),bio=VALUES(bio)");
    $stmt->bind_param('issids', $uid, $specialization, $qualification, $experience, $fee, $bio);
    $stmt->execute();

    $conn->commit();
    $_SESSION['full_name'] = $full_name;
    jsonResponse(['success'=>true,'message'=>'Profile updated successfully!']);
} catch(Exception $e) {
    $conn->rollback();
    jsonResponse(['success'=>false,'message'=>'Update failed: '.$e->getMessage()]);
}
?>
