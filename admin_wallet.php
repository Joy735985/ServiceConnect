<?php
require 'db.php';
session_start();

$result = $conn->query("SELECT w.id, w.user_id, u.first_name, u.last_name, w.amount, w.status, w.created_at
                        FROM wallet_transactions w
                        JOIN users u ON w.user_id = u.id
                        WHERE w.type='withdraw' ORDER BY w.created_at DESC");

if (isset($_GET['approve'])) {
  $id = intval($_GET['approve']);
  $conn->query("UPDATE wallet_transactions SET status='approved' WHERE id=$id");
}

if (isset($_GET['reject'])) {
  $id = intval($_GET['reject']);
  $conn->query("UPDATE wallet_transactions SET status='rejected' WHERE id=$id");
}

$result = $conn->query("SELECT w.id, u.first_name, u.last_name, w.amount, w.status, w.created_at
                        FROM wallet_transactions w
                        JOIN users u ON w.user_id = u.id
                        WHERE w.type='withdraw' ORDER BY w.created_at DESC");
?>
<h1>Withdrawal Requests</h1>
<table border="1" cellpadding="8">
<tr><th>ID</th><th>Technician</th><th>Amount</th><th>Status</th><th>Action</th><th>Date</th></tr>
<?php while($r=$result->fetch_assoc()): ?>
<tr>
  <td><?= $r['id'] ?></td>
  <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
  <td>$<?= number_format($r['amount'],2) ?></td>
  <td><?= ucfirst($r['status']) ?></td>
  <td>
    <?php if($r['status']=='pending'): ?>
      <a href="?approve=<?= $r['id'] ?>">Approve</a> |
      <a href="?reject=<?= $r['id'] ?>">Reject</a>
    <?php else: ?>
      <?= ucfirst($r['status']) ?>
    <?php endif; ?>
  </td>
  <td><?= $r['created_at'] ?></td>
</tr>
<?php endwhile; ?>
</table>
