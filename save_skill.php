<?php
// save_skill.php
session_start();
require_once 'db.php';

// Helper: get current user id from common session keys
function current_user_id() {
  if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
  if (isset($_SESSION['id'])) return (int)$_SESSION['id'];
  if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  return 0;
}

$uid = current_user_id();
if ($uid <= 0) {
  header("Location: login.php");
  exit;
}

// Normalize posted skills
$skills = $_POST['skills'] ?? [];
if (!is_array($skills)) $skills = [];
$skills = array_values(array_filter(array_map('trim', $skills)));
$skills = array_unique($skills);

if (!count($skills)) {
  header("Location: add_skill.php");
  exit;
}

// Insert (ignore duplicates). For best results ensure a UNIQUE(technician_id, skill_name) index.
$stmt = $conn->prepare("INSERT IGNORE INTO technician_skills (technician_id, skill_name) VALUES (?, ?)");
foreach ($skills as $skill) {
  $stmt->bind_param("is", $uid, $skill);
  $stmt->execute();
}
$stmt->close();


// Save technician experience (no case changes)
$years = isset($_POST['years_of_experience']) ? intval($_POST['years_of_experience']) : 0;
$details = isset($_POST['experience_details']) ? trim($_POST['experience_details']) : '';

$check = $conn->prepare("SELECT id FROM technician_experience WHERE technician_id = ? LIMIT 1");
$check->bind_param("i", $uid);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $upd = $conn->prepare("
        UPDATE technician_experience
        SET years_of_experience = ?, experience_details = ?
        WHERE technician_id = ?
    ");
    $upd->bind_param("isi", $years, $details, $uid);
    $upd->execute();
    $upd->close();
} else {
    $ins = $conn->prepare("
        INSERT INTO technician_experience (technician_id, years_of_experience, experience_details)
        VALUES (?, ?, ?)
    ");
    $ins->bind_param("iis", $uid, $years, $details);
    $ins->execute();
    $ins->close();
}

$check->close();

header("Location: add_skill.php?saved=1");
exit;
