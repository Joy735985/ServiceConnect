<?php
// work_history.php — Completed work list for technician
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';
require 'functions.php';
requireRole(['Technician','Admin']); // same auth as technician.php

$me     = $_SESSION['user'];
$techId = (int)$me['id'];

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($dt){
    $ts = strtotime($dt);
    return $ts ? date("M d, Y h:i A", $ts) : $dt;
}

// Load all completed orders with customer info
$sql = "
    SELECT 
        o.id,
        o.service_name,
        o.date_time,
        o.status,
        o.notes,
        o.technician_comment,

        COALESCE(
            NULLIF(c.name,''),
            NULLIF(CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')),' '),
            c.email,
            CONCAT('Customer #', o.customer_id)
        ) AS customer_name,
        c.email AS customer_email,
        c.mobile AS customer_mobile,
        c.profile_image AS customer_image

    FROM orders o
    LEFT JOIN users c ON c.id = o.customer_id
    WHERE o.technician_id = ?
      AND o.status = 'Completed'
    ORDER BY o.date_time DESC, o.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $techId);
$stmt->execute();
$works = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Successful Work History</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="dashboard.css">

<style>
.container{max-width:1100px;margin:30px auto;padding:0 18px;}
.header{
    background:#fff;border:1px solid #e5e7eb;border-radius:16px;
    padding:18px;display:flex;justify-content:space-between;align-items:center;
    box-shadow:0 6px 20px rgba(15,23,42,.06);
}
.header h2{margin:0;font-size:20px;font-weight:800;color:#635bff;}
.back{
    text-decoration:none;font-weight:700;color:#635bff;padding:8px 12px;
    border-radius:999px;border:1px solid #e5e7eb;background:#fff;
}
.grid{
    margin-top:18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
    gap:14px;
}
.card{
    background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;
    display:flex;gap:12px;align-items:flex-start;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
}
.avatar{
    width:52px;height:52px;border-radius:999px;object-fit:cover;border:2px solid #eef2ff;
}
.name{font-weight:800;font-size:16px;}
.meta{color:#64748b;font-size:13px;margin-top:2px;}
.service{margin-top:8px;font-weight:800;color:#635bff;}
.dt{margin-top:4px;font-size:13px;color:#64748b;}
.badge{
    margin-top:10px;display:inline-flex;align-items:center;padding:5px 10px;
    border-radius:999px;font-size:12px;font-weight:800;
    color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;
}
.actions{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;}
.btn{
    padding:7px 12px;border-radius:10px;border:1px solid #e5e7eb;
    background:#fff;text-decoration:none;font-weight:700;font-size:13px;color:#0f172a;
}
.btn.primary{background:#635bff;color:#fff;border-color:transparent;}
.empty{text-align:center;color:#64748b;padding:40px 0;}
</style>
</head>
<body>

<div class="container">
  <div class="header">
    <h2>Successful Works (Completed)</h2>
    <a class="back" href="technician.php">← Back to Dashboard</a>
  </div>

  <?php if (!$works): ?>
    <div class="empty">No completed works yet.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($works as $w): ?>
        <div class="card">
          <?php $img = $w['customer_image'] ?: "https://via.placeholder.com/52"; ?>
          <img class="avatar" src="<?= h($img) ?>" alt="">

          <div style="flex:1">
            <div class="name"><?= h($w['customer_name']) ?></div>
            <div class="meta">
              <?= h($w['customer_email'] ?: '') ?>
              <?= $w['customer_mobile'] ? " • ".h($w['customer_mobile']) : "" ?>
            </div>

            <div class="service">Service: <?= h($w['service_name']) ?></div>
            <div class="dt"><?= h(fmt_dt($w['date_time'])) ?></div>

            <div class="badge">Completed</div>

            <div class="actions">
              <a class="btn primary" href="order_details.php?id=<?= (int)$w['id'] ?>">View Details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
