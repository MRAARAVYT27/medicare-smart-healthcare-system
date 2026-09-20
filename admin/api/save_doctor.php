<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('admin', '../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data           = json_decode(file_get_contents('php://input'), true);
$edit_id        = (int)($data['id']             ?? 0);
$full_name      = sanitize($data['full_name']   ?? '');
$email          = sanitize($data['email']       ?? '');
$password       = $data['password']             ?? '';
$phone          = sanitize($data['phone']       ?? '');
$specialization = sanitize($data['specialization'] ?? '');
$qualification  = sanitize($data['qualification']  ?? '');
$experience     = (int)($data['experience']     ?? 0);
$fee            = (float)($data['fee']          ?? 0);

// Validation
if (!$full_name || !$email) {
    jsonResponse(['success' => false, 'message' => 'Full name and email are required.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address.']);
}

$conn->begin_transaction();
try {
    if ($edit_id) {
        // ── EDIT existing doctor ──────────────────────────────────
        // Check email not taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $edit_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception('That email is already used by another account.');
        }

        if ($password) {
            if (strlen($password) < 8) throw new Exception('Password must be at least 8 characters.');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password=?, phone=? WHERE id=? AND role='doctor'");
            $stmt->bind_param('ssssi', $full_name, $email, $hash, $phone, $edit_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=? AND role='doctor'");
            $stmt->bind_param('sssi', $full_name, $email, $phone, $edit_id);
        }
        if (!$stmt->execute()) throw new Exception('Failed to update user.');

        // Upsert doctor profile
        $stmt = $conn->prepare(
            "INSERT INTO doctor_profiles (user_id, specialization, qualification, experience_years, consultation_fee)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               specialization   = VALUES(specialization),
               qualification    = VALUES(qualification),
               experience_years = VALUES(experience_years),
               consultation_fee = VALUES(consultation_fee)"
        );
        $stmt->bind_param('issid', $edit_id, $specialization, $qualification, $experience, $fee);
        if (!$stmt->execute()) throw new Exception('Failed to update doctor profile.');

    } else {
        // ── ADD new doctor ────────────────────────────────────────
        if (!$password || strlen($password) < 8) {
            throw new Exception('Password is required and must be at least 8 characters.');
        }

        // Check email uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception('A user with this email already exists.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, password, phone, role, is_verified, is_active)
             VALUES (?, ?, ?, ?, 'doctor', 1, 1)"
        );
        $stmt->bind_param('ssss', $full_name, $email, $hash, $phone);
        if (!$stmt->execute()) throw new Exception('Failed to create doctor account.');
        $new_id = $conn->insert_id;

        $stmt = $conn->prepare(
            "INSERT INTO doctor_profiles (user_id, specialization, qualification, experience_years, consultation_fee, is_available)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('issid', $new_id, $specialization, $qualification, $experience, $fee);
        if (!$stmt->execute()) throw new Exception('Failed to create doctor profile.');
    }

    $conn->commit();
    jsonResponse(['success' => true, 'message' => $edit_id ? 'Doctor updated successfully.' : 'Doctor added successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}
?>
