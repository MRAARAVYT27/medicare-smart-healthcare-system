<?php
require_once __DIR__ . '/../../includes/auth_helper.php';
requireRole('doctor','../../index.php');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$data           = json_decode(file_get_contents('php://input'), true);
$uid            = (int)$_SESSION['user_id'];
$available_days = sanitize($data['available_days'] ?? 'Mon,Tue,Wed,Thu,Fri');
$slot_start     = sanitize($data['slot_start']     ?? '09:00:00');
$slot_end       = sanitize($data['slot_end']       ?? '17:00:00');
$slot_duration  = (int)($data['slot_duration']     ?? 30);
$is_available   = (int)($data['is_available']      ?? 1);

// Basic validation
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $slot_start)) jsonResponse(['success'=>false,'message'=>'Invalid start time.']);
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $slot_end))   jsonResponse(['success'=>false,'message'=>'Invalid end time.']);
if ($slot_start >= $slot_end) jsonResponse(['success'=>false,'message'=>'Start time must be before end time.']);
if (!in_array($slot_duration, [15,20,30,45,60])) jsonResponse(['success'=>false,'message'=>'Invalid slot duration.']);

$stmt = $conn->prepare("INSERT INTO doctor_profiles (user_id,available_days,slot_start,slot_end,slot_duration,is_available)
    VALUES (?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE available_days=VALUES(available_days),slot_start=VALUES(slot_start),
    slot_end=VALUES(slot_end),slot_duration=VALUES(slot_duration),is_available=VALUES(is_available)");
$stmt->bind_param('isssii', $uid, $available_days, $slot_start, $slot_end, $slot_duration, $is_available);

if ($stmt->execute()) jsonResponse(['success'=>true,'message'=>'Schedule updated successfully!']);
else jsonResponse(['success'=>false,'message'=>'Failed to update schedule.']);
?>
