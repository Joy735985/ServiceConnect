<?php
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

// Ensure role (rehydrate if missing)
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($role === null) {
  $rs = $conn->prepare("SELECT role FROM users WHERE id = ?");
  $rs->bind_param("i", $uid);
  $rs->execute();
  $r = $rs->get_result();
  if ($row = $r->fetch_assoc()) {
    $role = $row['role'];
    $_SESSION['role'] = $role;
  }
  $rs->close();
}

if (strtolower((string)$role) !== 'technician') {
  header("Location: technician.php");
  exit;
}

// Validate presence
if (!isset($_POST['skills']) || !is_array($_POST['skills'])) {
  header("Location: set_rent.php");
  exit;
}

$skills = $_POST['skills'];
$amounts = $_POST['amount'] ?? [];
$units = $_POST['unit'] ?? [];
$currencies = $_POST['currency'] ?? [];

$validUnits = ['per hour','per day','per job'];
$validCurrencies = ['BDT','USD','INR'];

// Prepared upsert
$sql = "INSERT INTO technician_skill_rates (technician_id, skill_name, amount, unit, currency)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), unit = VALUES(unit), currency = VALUES(currency)";
$stmt = $conn->prepare($sql);

foreach ($skills as $skill) {
  $skillKey = (string)$skill;

  $amount = isset($amounts[$skillKey]) ? trim((string)$amounts[$skillKey]) : '';
  $unit = isset($units[$skillKey]) ? trim((string)$units[$skillKey]) : 'per job';
  $currency = isset($currencies[$skillKey]) ? trim((string)$currencies[$skillKey]) : 'BDT';

  if ($amount === '' || !is_numeric($amount)) {
    // Skip blank or invalid amounts (do not modify existing)
    continue;
  }
  $amount = (float)$amount;
  if ($amount < 0) {
    continue;
  }

  if (!in_array($unit, $validUnits, true)) $unit = 'per job';
  if (!in_array($currency, $validCurrencies, true)) $currency = 'BDT';

  $amount = number_format($amount, 2, '.', '');

  $stmt->bind_param("isdss", $uid, $skillKey, $amount, $unit, $currency);
  $stmt->execute();
}

$stmt->close();

header("Location: set_rent.php?saved=1");
exit;
