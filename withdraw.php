<?php
require 'db.php';
require 'functions.php';
requireRole(['Technician','Admin']);

$me = $_SESSION['user'];
$techId = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    if ($amount > 0) {
        $conn->query("INSERT INTO wallet_transactions (user_id, amount, type, status, created_at)
                      VALUES ({$techId}, {$amount}, 'withdraw', 'pending', NOW())");
        echo "<script>alert('Withdrawal request submitted!');window.location='wallet.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Withdraw Funds</title>
<link rel="stylesheet" href="serviq.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="layout">
    <?php include 'technician.php'; ?>
    <main class="content">
      <h1>Withdraw Funds</h1>
      <form method="post" style="max-width:400px;">
        <label>Amount (৳)</label>
        <input type="number" name="amount" step="0.01" min="1" required class="input">
        <button class="btn-small" type="submit" style="margin-top:10px;">Request Withdraw</button>
      </form>
    </main>
  </div>
</body>
</html>
