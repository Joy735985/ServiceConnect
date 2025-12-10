<?php
// admin_withdrawals.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'db.php';

// --- Auth: only Admins ---
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'Admin') {
  header('Location: login.php'); exit;
}

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$flash = null;

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['tx_id'], $_POST['csrf'])) {
  if (!hash_equals($csrf, $_POST['csrf'])) {
    $flash = ['type'=>'error','msg'=>'Invalid form token'];
  } else {
    $tx_id = (int)$_POST['tx_id'];
    $action = $_POST['action']; // approve|reject

    // Load the transaction first (pending only to avoid double processing)
    $stmt = $conn->prepare("SELECT id, user_id, amount, status FROM wallet_transactions WHERE id=? AND type='withdraw' LIMIT 1");
    $stmt->bind_param("i", $tx_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if (!$tx) {
      $flash = ['type'=>'error','msg'=>'Transaction not found.'];
    } elseif ($tx['status'] !== 'pending') {
      $flash = ['type'=>'error','msg'=>'This request is already processed.'];
    } else {
      // Atomic update
      $conn->begin_transaction();
      try {
        if ($action === 'approve') {
          $u = $conn->prepare("UPDATE wallet_transactions SET status='approved' WHERE id=?");
          $u->bind_param("i", $tx_id);
          $u->execute();
          // NOTE: If you *didn’t* deduct at request time, you would deduct here.
          $conn->commit();
          $flash = ['type'=>'success','msg'=>'Withdrawal approved.'];
        } elseif ($action === 'reject') {
          // Because your wallet.php deducted immediately, we REFUND on reject:
          $r1 = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
          $r1->bind_param("di", $tx['amount'], $tx['user_id']);
          $r1->execute();

          $r2 = $conn->prepare("UPDATE wallet_transactions SET status='rejected' WHERE id=?");
          $r2->bind_param("i", $tx_id);
          $r2->execute();

          $conn->commit();
          $flash = ['type'=>'success','msg'=>'Withdrawal rejected and amount refunded.'];
        } else {
          throw new Exception('Unknown action.');
        }
      } catch (Throwable $e) {
        $conn->rollback();
        $flash = ['type'=>'error','msg'=>'Operation failed.'];
      }
    }
  }
}

// Fetch list (pending first)
$list = $conn->query("
  SELECT w.id, w.user_id, u.first_name, u.last_name, u.email,
         w.amount, w.status, w.created_at
  FROM wallet_transactions w
  JOIN users u ON u.id = w.user_id
  WHERE w.type='withdraw'
  ORDER BY (w.status='pending') DESC, w.created_at DESC
");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Withdrawal Requests</title>
  <style>
    body{font-family:Arial, sans-serif; margin:24px;}
    .flash{padding:10px 12px;border-radius:10px;margin-bottom:12px}
    .flash.success{background:#e8f8e8;color:#0a6d0a;border:1px solid #bfe6bf}
    .flash.error{background:#ffe9e9;color:#8a1f1f;border:1px solid #ffc9c9}
    table{width:100%; border-collapse:collapse}
    th,td{padding:10px; border-bottom:1px solid #eee; text-align:left}
    th{background:#f6f6f6}
    .actions form{display:inline}
    button{padding:6px 10px;border:0;border-radius:8px;cursor:pointer}
    .approve{background:#0f62fe;color:#fff}
    .reject{background:#c62828;color:#fff}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px}
    .pending{background:#fff3cd;color:#8a6d3b}
    .approved{background:#e8f8e8;color:#0a6d0a}
    .rejected{background:#ffe9e9;color:#8a1f1f}
  </style>
</head>
<body>
  <h1>💼 Withdrawal Requests</h1>
  <?php if($flash): ?>
    <div class="flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Technician</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php while($r = $list->fetch_assoc()): ?>
      <tr>
        <td>#<?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?><br>
            <small><?= htmlspecialchars($r['email'] ?? '') ?></small></td>
        <td>৳<?= number_format((float)$r['amount'], 2) ?></td>
        <td>
          <?php $s = strtolower($r['status']); ?>
          <span class="badge <?= $s ?>"><?= ucfirst($r['status']) ?></span>
        </td>
        <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
        <td class="actions">
          <?php if ($r['status']==='pending'): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="tx_id" value="<?= (int)$r['id'] ?>">
              <button class="approve" name="action" value="approve">Approve</button>
            </form>
            <form method="post" onsubmit="return confirm('Reject and refund this request?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="tx_id" value="<?= (int)$r['id'] ?>">
              <button class="reject" name="action" value="reject">Reject</button>
            </form>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <p style="margin-top:16px"><a href="admin.php">⬅ Back to Admin</a></p>
</body>
</html>
