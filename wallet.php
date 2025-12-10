<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'db.php';
require_once 'functions.php';

// Helper for escaping
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Redirect if not logged in
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$user_id = (int)$user['id'];
$role = strtolower($user['role']);

// Allow only Technicians
if ($role !== 'technician') {
    // If Admin/Customer tries to access, redirect to their dashboard or login
    if ($role === 'customer') {
        header('Location: customer.php');
    } elseif ($role === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

// Fetch latest wallet balance
$stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($wallet_balance);
$stmt->fetch();
$stmt->close();

$message = "";

// Handle withdraw
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount']);
    if ($amount <= 0) {
        $message = "<p class='error-message'>Invalid amount.</p>";
    } elseif ($amount > $wallet_balance) {
        $message = "<p class='error-message'>Insufficient balance.</p>";
    } else {
        // Record withdrawal as pending
        $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, status) VALUES (?, ?, 'withdraw', 'pending')");
        $stmt->bind_param("id", $user_id, $amount);
        $stmt->execute();

        // Deduct from wallet immediately
        $new_balance = $wallet_balance - $amount;
        $update = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $update->bind_param("di", $new_balance, $user_id);
        $update->execute();

        $wallet_balance = $new_balance;
        $message = "<p class='success-message'>Withdrawal request submitted successfully!</p>";
    }
}

// Fetch transaction history
$tx = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
$tx->bind_param("i", $user_id);
$tx->execute();
$transactions = $tx->get_result();

// Logic for the optional "Switch Profile" link
$switchProfileLink = '';
$currentId = $user_id;
if ($currentId) {
    $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    
    $curEmail = $user['email'] ?? null;
    
    if ($curEmail) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND LOWER(role) = 'customer' LIMIT 1");
        $stmt_check->bind_param("s", $curEmail);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
          $switchProfileLink = '<a href="' . $BASE . 'switch_profile.php?to=customer" class="sidebar-link">🔁 Switch to Customer Profile</a>';
        } else {
          $switchProfileLink = '<a href="' . $BASE . 'signup.php?role=customer" class="sidebar-link">➕ Create Customer Account</a>';
        }
        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Technician Wallet</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    /* =========================================================
       Dashboard UI Style Integration
       ========================================================= */

    /* General Reset and Font */
    .pill{display:inline-block;padding:4px 10px;border-radius:14px;background:#f5f5f7}
    
    body{
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
      background: radial-gradient(circle at top left, #fdf2f8 0%, #eef2ff 35%, #ecfeff 70%, #f0fdf4 100%);
      color:#0f172a;
      margin: 0;
    }

    .app{
      min-height:100vh;
      display:flex;
      gap:18px;
      padding:18px;
    }

    .sidebar{
      background: linear-gradient(180deg, #0ea5e9 0%, #6366f1 40%, #8b5cf6 70%, #ec4899 100%);
      color:#fff;
      border-radius:18px;
      padding:18px 14px;
      box-shadow: 0 18px 40px rgba(99,102,241,.35);
      position:sticky;
      top:18px;
      height:calc(100vh - 36px);
      overflow:auto;
    }

    .brand{
      font-weight:800;
      font-size:20px;
      letter-spacing:.3px;
      padding:12px 10px;
      border-radius:14px;
      background: rgba(255,255,255,.12);
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:14px;
    }
    .brand .dot{
      width:34px;height:34px;
      display:grid;place-items:center;
      background: conic-gradient(from 180deg, #22c55e, #facc15, #f97316, #ec4899, #22c55e);
      border-radius:50%;
      color:#0f172a;
      font-weight:900;
      box-shadow: inset 0 0 0 2px rgba(255,255,255,.7);
    }

    .nav a,
    .sidebar-link{
      display:flex;
      align-items:center;
      gap:8px;
      color:#fff;
      text-decoration:none;
      padding:10px 12px;
      margin:6px 4px;
      border-radius:12px;
      font-weight:600;
      background: rgba(255,255,255,.08);
      transition: .25s ease;
      border:1px solid rgba(255,255,255,.12);
    }

    .nav a:hover,
    .sidebar-link:hover{
      transform: translateX(4px);
      background: rgba(255,255,255,.18);
      box-shadow: 0 8px 20px rgba(0,0,0,.18);
    }

    .nav a.active{
      background: rgba(255,255,255,.95);
      color:#111827;
      font-weight:800;
      box-shadow: 0 10px 25px rgba(255,255,255,.35);
    }

    .main{
      flex:1;
      background: rgba(255,255,255,.75);
      backdrop-filter: blur(8px);
      border-radius:18px;
      padding:18px 18px 24px;
      box-shadow: 0 10px 30px rgba(15,23,42,.08);
    }
    
    .toprow{ 
      background: linear-gradient(90deg, #ffffff 0%, #f8fafc 50%, #ffffff 100%);
      padding:14px 14px;
      border-radius:14px;
      box-shadow: inset 0 0 0 1px rgba(15,23,42,.06);
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom: 22px; 
    }

    .toprow h1, .toprow h2{
      font-size:26px;
      font-weight:900;
      background: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899, #f59e0b);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
      margin:0;
    }

    .card{
      background: #fff;
      border-radius:14px;
      box-shadow: 0 12px 28px rgba(15,23,42,.08);
      overflow:hidden;
      border:1px solid rgba(15,23,42,.06);
      padding:20px;
      margin-bottom: 20px;
    }
    
    /* Wallet Specific Styles */
    .balance-box {
        padding: 20px;
        background: linear-gradient(90deg, #22c55e, #10b981);
        color: white;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 8px 16px rgba(34,197,94,0.4);
    }
    .balance-box h2, .balance-box h3 {
        color: white;
        margin: 0;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    .balance-box h2 { font-size: 32px; font-weight: 900; margin-top: 5px; }

    .transaction-table {
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0;
    }
    .transaction-table th {
        background: linear-gradient(90deg, #0ea5e9, #6366f1, #8b5cf6);
        color: #fff;
        font-weight: 800;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: none;
        padding: 10px 12px;
        text-align: left;
    }
    .transaction-table td {
        padding: 10px 12px; 
        border-bottom: 1px solid #eee; 
        font-size: 14px;
    }
    .transaction-table tbody tr:nth-child(even){ background:#f8fafc; }
    .transaction-table tbody tr:hover{ background: #ecfeff; }

    .withdraw-form input[type=number] {
        padding: 10px; 
        border: 1px solid #dbe3ef; 
        border-radius: 8px;
        width: 180px;
        margin-right: 10px;
        outline: none;
    }
    .withdraw-form button {
        padding: 10px 15px; 
        background: #f59e0b;
        border: none; 
        color: white; 
        border-radius: 8px; 
        cursor: pointer;
        font-weight: 700;
        transition: background-color 0.2s;
        box-shadow: 0 4px 8px rgba(245, 158, 11, 0.4);
    }
    .withdraw-form button:hover { background: #d97706; }
    
    .success-message {
        background: #e7f6ee;
        color: #0a6b3e;
        padding: 10px;
        border-radius: 10px;
        font-weight: 600;
    }
    .error-message {
        background: #fff3f2;
        color: #9c1c10;
        padding: 10px;
        border-radius: 10px;
        font-weight: 600;
    }

    @media (max-width: 768px){
      .app{padding:10px;gap:10px;}
      .sidebar{height:auto;position:relative; width: 100%;}
      .main{padding:10px;}
      .toprow h1, .toprow h2{font-size:22px;}
    }

</style>
</head>
<body>

<div class="app">
    <aside class="sidebar">
        <div class="brand"><span class="dot">S</span> ServiceConnect</div>
        <nav class="nav">
            <a href="technician.php">Dashboard</a>
            <a href="edit_profile.php"> Edit Profile</a>
            <a href="add_skill.php">Add Skill</a>
            <a href="set_rent.php">Set Rent</a>
            <a href="booking_request.php"> Booking Requests</a>
            <a class="active" href="wallet.php"> Wallet Balance</a>
            <a href="logout.php">Logout</a>
            <?= $switchProfileLink ?>
        </nav>
    </aside>

    <main class="main">
        
        <div class="toprow">
            <h1>Technician Wallet</h1>
            <div class="pill">Financial Overview</div>
        </div>

        <div class="card">
            <div class="balance-box">
                <h3>Welcome, <?= h($user['first_name'] ?? '') ?></h3>
                <h2>Current Balance: ৳<?= number_format($wallet_balance, 2) ?></h2>
            </div>
            
            <?php if($message): ?><div style="margin-bottom:15px;"><?= $message ?></div><?php endif; ?>

            <form method="POST" class="withdraw-form">
                <label style="font-weight: 600;">Request Withdrawal: </label>
                <input type="number" name="amount" step="0.01" min="1" placeholder="Amount (৳)" required>
                <button type="submit" name="withdraw">Withdraw</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Transaction History</h3>
            <table class="transaction-table">
                <thead>
                    <tr><th>ID</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td>৳<?= number_format($row['amount'], 2) ?></td>
                        <td><?= ucfirst($row['type']) ?></td>
                        <td><?= ucfirst($row['status']) ?></td>
                        <td><?= $row['created_at'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <p><a href="technician.php" class="btn btn-muted" style="border:none;">⬅ Back to Dashboard</a></p>
    </main>
</div>
</body>
</html>