<?php
// mark_completed.php
session_start();
require_once 'db.php';
if (file_exists('functions.php')) {
    require_once 'functions.php';
}

if (function_exists('requireRole')) {
    requireRole(['Technician','Admin']);
} elseif (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$tech_id = $_SESSION['user']['id'] ?? (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id > 0) {
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = 'Completed', completion_time = NOW()
            WHERE id = ? AND technician_id = ?
        ");
        $stmt->bind_param("ii", $order_id, $tech_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: technician.php?msg=completed");
exit;
