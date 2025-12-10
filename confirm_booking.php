<?php
session_start();
require_once 'db.php';

$customer_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0;
if (!$customer_id) { header("Location: login.php"); exit; }

$tech_id = (int)($_POST['tech_id'] ?? 0);
$service = trim($_POST['service'] ?? '');
$date    = trim($_POST['date'] ?? '');
$time    = trim($_POST['time'] ?? '');

if (!$tech_id || $service === '' || $date === '' || $time === '') {
  http_response_code(422);
  exit('Missing required fields.');
}

$dt = date('Y-m-d H:i:s', strtotime($date.' '.$time));

// make sure tech offers this skill
$check = $conn->prepare("SELECT 1 FROM technician_skills WHERE technician_id=? AND skill_name=? LIMIT 1");
$check->bind_param("is", $tech_id, $service);
$check->execute();
$res = $check->get_result();
$ok  = $res && $res->num_rows > 0;  // true if at least one match
$check->close();

if (!$ok) {
  http_response_code(400);
  exit('Technician does not offer that service.');
}

$stmt = $conn->prepare("
  INSERT INTO orders (customer_id, technician_id, service_name, date_time, status)
  VALUES (?, ?, ?, ?, 'Pending')
");
$stmt->bind_param("iiss", $customer_id, $tech_id, $service, $dt);
$stmt->execute();
$stmt->close();

header("Location: customer.php?booking=success");
exit;
