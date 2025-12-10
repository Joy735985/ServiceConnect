<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "db.php";

// ---- Technician auth (same session system as technician.php) ----
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'Technician') {
    header("Location: login.php");
    exit;
}

$tech_id = (int)$_SESSION['user']['id'];

// ---- Fetch ratings with customer id ----
$sql = "
    SELECT r.id, r.order_id, r.customer_id, r.rating, r.review, r.created_at
    FROM ratings r
    WHERE r.technician_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---- Average rating ----
$avg = 0;
if (count($ratings) > 0) {
    $sum = array_sum(array_column($ratings, 'rating'));
    $avg = round($sum / count($ratings), 2);
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Ratings</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style.css">
<style>
.container{max-width:1000px;margin:30px auto;padding:0 15px;}
.card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 18px rgba(0,0,0,.06);margin-bottom:20px;}
.header{display:flex;justify-content:space-between;align-items:center;}
.avg{font-size:28px;font-weight:800;color:#635bff;}
.table{width:100%;border-collapse:collapse;}
.table th,.table td{padding:12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
.badge{padding:4px 10px;border-radius:999px;background:#eef2ff;color:#1e3a8a;font-weight:700;font-size:12px;}
.back{display:inline-block;margin-top:8px;text-decoration:none;font-weight:700;color:#635bff;}
.empty{text-align:center;color:#777;padding:30px;}
</style>
</head>
<body>

<div class="container">

  <div class="card header">
    <div>
      <h2 style="margin:0;">Ratings & Reviews</h2>
      <a class="back" href="technician.php">← Back to Dashboard</a>
    </div>
    <div class="avg"><?= h($avg) ?> ⭐</div>
  </div>

  <div class="card">
    <?php if (!$ratings): ?>
      <div class="empty">No ratings yet.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Customer ID</th>
            <th>Order ID</th>
            <th>Rating</th>
            <th>Review</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ratings as $r): ?>
          <tr>
            <td><span class="badge">#<?= (int)$r['customer_id'] ?></span></td>
            <td>#<?= (int)$r['order_id'] ?></td>
            <td><?= h($r['rating']) ?> ⭐</td>
            <td><?= h($r['review'] ?: '—') ?></td>
            <td><?= h(date("M d, Y h:i A", strtotime($r['created_at']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
