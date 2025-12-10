<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/db.php';

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$tech_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0;
if ($tech_id <= 0) { header("Location: {$BASE}login.php"); exit; }

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) { header("Location: {$BASE}booking_request.php?error=missing_order"); exit; }

$sql = "SELECT id, status FROM orders WHERE id=? AND technician_id=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $tech_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { header("Location: {$BASE}booking_request.php?error=not_found_or_forbidden"); exit; }
if (strcasecmp($order['status'],'Pending')!==0) { header("Location: {$BASE}booking_request.php?error=not_pending"); exit; }

$upd = $conn->prepare("UPDATE orders SET status='Declined', updated_at=NOW() WHERE id=? AND technician_id=? AND status='Pending'");
$upd->bind_param("ii", $order_id, $tech_id);
$upd->execute();
$ok = $upd->affected_rows > 0;
$upd->close();

header("Location: {$BASE}booking_request.php?" . ($ok ? "ok=declined" : "error=update_failed"));
exit;
