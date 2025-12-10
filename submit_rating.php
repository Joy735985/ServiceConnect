<?php
// submit_rating.php — handle customer rating submission
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

// Only customers/admins can post ratings
if (function_exists('requireRole')) {
    requireRole(['Customer', 'Admin']);
} elseif (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$customerId = $_SESSION['user']['id'] ?? (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $techId  = (int)($_POST['technician_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $review  = trim($_POST['review'] ?? '');

    if ($orderId && $techId && $rating >= 1 && $rating <= 5) {

        // Confirm this order belongs to the logged-in customer and is Completed
        $stmt = $conn->prepare("
            SELECT technician_id 
            FROM orders 
            WHERE id = ? AND customer_id = ? AND status = 'Completed'
        ");
        $stmt->bind_param("ii", $orderId, $customerId);
        $stmt->execute();
        $stmt->bind_result($dbTechId);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            if (!$dbTechId) $dbTechId = $techId;

            // Avoid duplicate rating for same order+customer
            $stmt = $conn->prepare("
                SELECT id FROM ratings 
                WHERE order_id = ? AND customer_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $orderId, $customerId);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            if (!$exists) {
                // Adjust column list if your ratings table differs
                $stmt = $conn->prepare("
                    INSERT INTO ratings (order_id, technician_id, customer_id, rating, review, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iiiis", $orderId, $dbTechId, $customerId, $rating, $review);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Back to order details
header("Location: order_details.php?id=" . (int)$orderId);
exit;
