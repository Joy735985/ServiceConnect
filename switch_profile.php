<?php
session_start();
require_once 'db.php';

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

if (empty($_SESSION['user']['email'])) {
  header("Location: {$BASE}login.php");
  exit;
}

$email      = $_SESSION['user']['email'];
$targetRole = $_GET['to'] ?? '';

if (!in_array($targetRole, ['technician','customer'], true)) {
  header("Location: {$BASE}index.php");
  exit;
}

// Find the other profile by same email + role
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND LOWER(role) = ? LIMIT 1");
$stmt->bind_param("ss", $email, $targetRole);
$stmt->execute();
$res = $stmt->get_result();

if ($user = $res->fetch_assoc()) {
  // Swap session user and go to the right dashboard
  $_SESSION['user'] = $user;
  header("Location: " . ($targetRole === 'technician' ? "{$BASE}technician.php" : "{$BASE}customer.php"));
  exit;
}

// If not found, send to signup with target role preselected
header("Location: {$BASE}signup.php?role=" . urlencode($targetRole));
exit;
