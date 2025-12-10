<?php
require 'db.php';
require 'functions.php';
requireRole(['Technician','Admin']); // allow Admin preview

$me     = $_SESSION['user'];
$techId = (int)$me['id'];

// Fetch latest user row (wallet/photo/etc.)
$uRes = $conn->query("SELECT * FROM users WHERE id = {$techId} LIMIT 1");
$user = $uRes ? $uRes->fetch_assoc() : $me;

// Escape helper
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Profile photo (try common columns, else default)
$profilePhoto = '';
foreach (['profile_image','photo','avatar'] as $col) {
  if (isset($user[$col]) && trim($user[$col]) !== '') { $profilePhoto = $user[$col]; break; }
}
if ($profilePhoto === '') { $profilePhoto = 'uploads/avatars/default.png'; } // ensure this exists

// Wallet balance
$walletBalance = isset($user['wallet_balance']) ? (float)$user['wallet_balance'] : 0.0;

// Stats
$success = 0; $pending = 0; $rating = 0.0;

if ($conn->query("SHOW TABLES LIKE 'orders'")->num_rows) {
  $row1 = $conn->query("SELECT COUNT(*) c FROM orders WHERE technician_id={$techId} AND status='Completed'")->fetch_assoc();
  $row2 = $conn->query("SELECT COUNT(*) c FROM orders WHERE technician_id={$techId} AND status='Pending'")->fetch_assoc();
  $success = (int)($row1['c'] ?? 0);
  $pending = (int)($row2['c'] ?? 0);
}

if ($conn->query("SHOW TABLES LIKE 'ratings'")->num_rows) {
  $rrow   = $conn->query("SELECT ROUND(AVG(rating),1) r FROM ratings WHERE technician_id={$techId}")->fetch_assoc();
  $rating = isset($rrow['r']) && $rrow['r'] !== null ? (float)$rrow['r'] : 0.0;
}

// -------------------------------------------------------------
// UPCOMING WORKS (Accepted only, future time)
// -------------------------------------------------------------
$upcoming = $conn->query("
  SELECT 
    o.id,
    o.date_time,
    o.status,
    o.service_name,
    CONCAT(c.first_name,' ',c.last_name) AS customer_name,
    c.phone AS contact,
    c.email,
    c.id AS customer_id
  FROM orders o
  LEFT JOIN users c ON c.id = o.customer_id
  WHERE 
    o.technician_id = {$techId}
    AND o.date_time >= NOW() /* ভবিষ্যতে নির্ধারিত কাজ */
    AND LOWER(o.status) = 'accepted' 
  ORDER BY o.date_time ASC
  LIMIT 5
");


// -------------------------------------------------------------
// BOOKING REQUESTS (Pending status, NO DATE FILTER)
// -------------------------------------------------------------
$requests = $conn->query("
  SELECT o.id, o.date_time, o.service_name,
         CONCAT(c.first_name,' ',c.last_name) AS customer_name,
         c.phone AS contact
  FROM orders o
  LEFT JOIN users c ON c.id = o.customer_id
  WHERE o.technician_id = {$techId} 
    AND o.status = 'Pending' /* শুধু মাত্র Pending কাজ */
  ORDER BY o.date_time ASC
  LIMIT 10
");

// Work history (completed) - last 10
$history = $conn->query("
  SELECT 
    o.id,
    o.service_name,
    o.date_time,
    o.completion_time,
    CONCAT(c.first_name,' ',c.last_name) AS customer_name,
    c.phone AS contact
  FROM orders o
  LEFT JOIN users c ON c.id = o.customer_id
  WHERE 
    o.technician_id = {$techId}
    AND o.status = 'Completed'
  ORDER BY o.completion_time DESC
  LIMIT 10
");

// Prepare avatar URL and greeting name
$avatarUrl = $profilePhoto && trim($profilePhoto) !== '' ? h($profilePhoto)
                                                         : 'https://via.placeholder.com/64';
$greetingName = $user['first_name'] ?? 'Technician';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Technician Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="serviq.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* =========================================================
       TECHNICIAN DASHBOARD STYLES (MATCHING CUSTOMER.PHP UI)
       ========================================================= */

    body{
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
      background: radial-gradient(circle at top left, #fdf2f8 0%, #eef2ff 35%, #ecfeff 70%, #f0fdf4 100%);
      color:#0f172a;
    }

    .layout{
      min-height:100vh;
      display:flex; /* Changed from grid to flex for simplicity */
      gap:18px;
      padding:18px;
      width:auto; /* Reset width */
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
    .nav a.active i { /* ensure icons look right in active state */
        color: #6366f1;
    }
    .sidebar-link{
        color: #fff !important;
        background: rgba(255,255,255,.12) !important;
        border:1px solid rgba(255,255,255,.18) !important;
        margin-top: 15px !important;
        font-weight: 700 !important;
    }
    .sidebar-link i {
        color: #fff !important;
    }

    .content{
      flex:1;
      background: rgba(255,255,255,.75);
      backdrop-filter: blur(8px);
      border-radius:18px;
      padding:18px 18px 24px;
      box-shadow: 0 10px 30px rgba(15,23,42,.08);
      max-width: none;
    }

    .toprow{
      background: linear-gradient(90deg, #ffffff 0%, #f8fafc 50%, #ffffff 100%);
      padding:14px 14px;
      border-radius:14px;
      box-shadow: inset 0 0 0 1px rgba(15,23,42,.06);
      margin-bottom: 20px;
    }

    .toprow h1{
      font-size:26px;
      font-weight:900;
      background: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899, #f59e0b);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
    }
    .toprow .breadcrumb,
    .toprow p {
        color: #64748b !important; /* Muted text */
    }

    .header-card {
        display:flex;
        align-items:center;
        gap: 16px;
        background: linear-gradient(135deg, #bee5a4ff, #d5d8dcff);
        padding: 18px;
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15,23,42,.05);
        border: 1px solid rgba(120, 121, 122, 0.06);
        margin-bottom: 22px;
    }
    .header-card .badge {
        background: none;
        padding: 0;
    }
    .header-card img {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        /* Avatar rainbow border style from customer.php */
        border:3px solid transparent;
        background:
            linear-gradient(#fff,#fff) padding-box,
            conic-gradient(#22c55e, #0ea5e9, #6366f1, #ec4899, #f59e0b, #22c55e) border-box;
        box-shadow: 0 10px 28px rgba(0,0,0,.18);
    }
    .header-card .header-meta strong {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }
    .header-card .header-meta span {
        font-size: 13px;
    }

    .stats {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
        gap:14px;
    }
    .stats .stat {
        display:flex;
        flex-direction:column;
        gap:4px;
        padding:14px;
        border-radius:12px;
        background:#fff;
        box-shadow: 0 12px 30px rgba(2,6,23,.10);
        transition:.25s ease;
        position:relative;
        overflow:hidden;
        border:none; /* Remove original border */
    }
    .stats .stat:hover{
        transform: translateY(-4px);
        box-shadow: 0 18px 40px rgba(2,6,23,.16);
    }
    .stats .stat::before{ /* Top colored bar */
        content:"";
        position:absolute;
        top:0;left:0;right:0;height:5px;
        background: linear-gradient(90deg,#22c55e,#0ea5e9,#6366f1,#8b5cf6,#ec4899,#f59e0b);
    }

    .stats .stat .ic {
        font-size: 24px;
        color: #6366f1;
        margin-bottom: 8px;
        width: fit-content;
    }
    .stats .stat h3 {
        font-size: 26px;
        font-weight: 700;
        margin: 0;
        /* Gradient text color */
        background: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899);
        -webkit-background-clip:text;
        background-clip:text;
        color:transparent;
    }
    .stats .stat p {
        font-size: 13px;
        color: #666;
        margin: 0 0 8px;
    }
    .stats .stat a.pill.btn.primary {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        border: none;
        box-shadow: 0 4px 10px rgba(0,0,0,.15);
        text-decoration: none;
    }
    .stats .stat a.pill.btn { /* Secondary buttons (View Wallet) */
        background: #f1f5f9;
        color: #0f172a;
        box-shadow: none;
        border: 1px solid #e2e8f0;
    }

    .card {
        background: #fff;
        border-radius:14px;
        box-shadow: 0 12px 28px rgba(15,23,42,.08);
        overflow:hidden;
        border:1px solid rgba(15,23,42,.06);
    }
    .card h3 {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        padding: 15px 18px 10px;
        margin: 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .table th{
        background: linear-gradient(90deg, #0ea5e9, #6366f1, #8b5cf6);
        color:#fff;
        font-weight:800;
        font-size:14px;
        text-transform:uppercase;
        letter-spacing:.5px;
        border-bottom:none;
        padding:10px 12px;
        text-align:left;
    }
    .table td{
        padding:10px 12px;
        border-bottom:1px solid #eee;
        text-align:left;
        font-size:15px;
        color:#0f172a;
        font-weight: 500;
    }
    .table tbody tr:nth-child(even){
        background:#f8fafc;
    }
    .table tbody tr:hover{
        background: #ecfeff;
        transform: scale(1.01);
    }
    
    .chip{ /* Styling the pending count chip in sidebar */
        background: #facc15; /* Yellow */
        color: #111827;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 800;
        margin-left: auto;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #fff !important; /* Force white text */
    }
    .status-accepted { background: #0ea5e9; } /* Sky Blue */
    .status-completed { background: #22c55e; } /* Green */
    .status-pending { background: #f97316; } /* Orange */

    .table td .btn,
    .table td a.btn {
        display: inline-block;
        border-radius: 999px;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 4px 8px rgba(0,0,0,.1);
        transition: .2s ease;
    }
    .table td .btn.primary {
        background: #6366f1; /* Indigo */
        color: #fff;
    }
    .table td a.btn:not(.primary) {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        box-shadow: none;
    }
    .table td a.btn:not(.primary):hover {
        background: #e2e8f0;
    }
    .table td form { display: inline-block; margin-right: 5px; }
    
    @media (max-width: 768px){
      .layout{padding:10px;gap:10px;flex-direction:column;}
      .sidebar{height:auto;position:relative;}
      .content{padding:10px;}
    }

  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><span class="dot">S</span>ServiceConnect</div>
      <nav class="nav">
        <a class="active" href="technician.php"><i class="fa-solid fa-house"></i> Dashboard</a>
        <a href="edit_profile.php"><i class="fa-solid fa-user-edit"></i> Edit Profile</a>
        <a href="add_skill.php"><i class="fa-solid fa-tools"></i> Add Skill</a>
        <a href="set_rent.php"><i class="fa-solid fa-hand-holding-dollar"></i> Set Rent</a>
        <a href="booking_request.php"><i class="fa-solid fa-clipboard-list"></i> Booking Requests <span class="chip"><?= (int)$pending ?></span></a>
        <a href="wallet.php"><i class="fa-solid fa-wallet"></i> Wallet Balance</a>
        <a href="rating.php"><i class="fa-solid fa-star"></i> Ratings</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        <?php
// Base path for safe links
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// ---- Switch Profile (Technician -> Customer) ----
$currentId = $_SESSION['user']['id'] ?? null;
if ($currentId) {
  $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $currentId);
  $stmt->execute();
  $stmt->bind_result($curEmail);
  $stmt->fetch();
  $stmt->close();

  if ($curEmail) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND LOWER(role) = 'customer' LIMIT 1");
    $stmt->bind_param("s", $curEmail);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      echo '<a href="', $BASE, 'switch_profile.php?to=customer" class="sidebar-link"><i class="fa-solid fa-repeat"></i> Switch to Customer Profile</a>';
    } else {
      echo '<a href="', $BASE, 'signup.php?role=customer" class="sidebar-link"><i class="fa-solid fa-plus-circle"></i> Create Customer Account</a>';
    }
    $stmt->close();
  }
}
?>


      </nav>
    </aside>

    <main class="content">
      <div class="toprow">
        <div>
          <div class="breadcrumb">Home / Dashboard</div>
          <h1>Welcome, <?= h($greetingName); ?>!</h1>
          <p style="margin:4px 0 0; font-size:14px;">
            Technician Dashboard Overview
          </p>
        </div>
      </div>


      <section class="header-card">
        <div class="badge">
          <img src="<?= $avatarUrl ?>" alt="Profile Photo">
        </div>
        <div class="header-meta">
          <strong><?= h($user['first_name'] ?? '') ?> <?= h($user['last_name'] ?? '') ?></strong><br>
          <span style="color:#64748b;">Role: Technician
          <?php if (!empty($user['phone'])): ?> | Mobile: <?= h($user['phone']) ?><?php endif; ?>
          <?php if (!empty($user['email'])): ?> | Email: <?= h($user['email']) ?><?php endif; ?></span>
          <div style="margin-top:8px">
            <a href="edit_profile.php" class="pill btn primary" style="background:#6366f1;">Edit Profile</a>
          </div>
        </div>
      
      </section>

      <div class="stats">
        <div class="stat">
          <div class="ic"><i class="fa-solid fa-wrench"></i></div>
          <div>
            <h3><?= (int)$success ?></h3>
            <p>Successful Work</p>
            <a class="pill btn primary" style="background:#0ea5e9; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.5);" href="work_history.php">View Work</a>
          </div>
        </div>
        <div class="stat">
          <div class="ic">⏳</div>
          <div>
            <h3><?= (int)$pending ?></h3>
            <p>Pending Requests</p>
            <a class="pill btn primary" style="background:#f97316;" href="booking_request.php">Review Requests</a>
          </div>
        </div>
        <div class="stat">
          <div class="ic">⭐</div>
          <div>
            <h3><?= number_format($rating,1) ?></h3>
            <p>Average Rating</p>
            <a class="pill btn primary" style="background:#8b5cf6;" href="rating.php">View Details</a>
          </div>
        </div>
        <div class="stat">
          <div class="ic">💰</div>
          <div>
            <h3>৳ <?= number_format($walletBalance, 2) ?></h3>
            <p>Wallet Balance</p>
            <div style="margin-top:8px; display:flex; gap:8px;">
              <a class="pill btn primary" style="background:#ec4899; box-shadow: 0 4px 10px rgba(236, 72, 153, 0.5);" href="wallet.php">Withdraw</a>
            </div>
          </div>
        </div>
      </div>

       <section class="card" style="margin-top:18px;">
        <h3>Upcoming Works</h3>
        <table class="table">
          <thead>
            <tr>
              <th>Service</th>
              <th>Customer</th>
              <th>Contact</th>
              <th>Time</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($upcoming && $upcoming->num_rows): ?>
            <?php while ($r = $upcoming->fetch_assoc()): ?>
              <tr>
                <td><?= h($r['service_name']) ?></td>
                <td><?= h($r['customer_name'] ?: 'N/A') ?></td>
                <td><?= h($r['contact'] ?: '-') ?></td>
                <td><?= h(date('d M Y, h:i A', strtotime($r['date_time']))) ?></td>
                <td>
                  <?php $status_class = strtolower((string)$r['status']); ?>
                  <?php if ($status_class === 'completed'): ?>
                    <span class="status-badge status-completed">Completed</span>
                  <?php else: ?>
                    <form action="mark_completed.php" method="post" style="display:inline;">
                      <input type="hidden" name="order_id" value="<?= (int)$r['id'] ?>">
                      <button type="submit" class="btn primary"
                              onclick="return confirm('Mark this work as completed?');"
                              style="background:#22c55e;">
                        Work completed
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="color:#64748b;">No accepted upcoming jobs</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>


      <section class="card" style="margin-top:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 18px 5px 18px; border-bottom: 1px solid #f1f5f9;">
          <h3>Booking Requests (Pending)</h3>
          <a class="btn primary" href="booking_request.php" style="background:#f97316;">Manage All</a>
        </div>
        <table class="table">
          <thead><tr><th>Service</th><th>Customer</th><th>Contact</th><th>Requested Time</th><th>Action</th></tr></thead>
          <tbody>
          <?php if ($requests && $requests->num_rows): ?>
            <?php while($q = $requests->fetch_assoc()): ?>
              <tr>
                <td><?= h($q['service_name'] ?: 'N/A') ?></td>
                <td><?= h($q['customer_name'] ?: 'N/A') ?></td>
                <td><?= h($q['contact'] ?: '-') ?></td>
                <td><?= h(date('d M Y, h:i A', strtotime($q['date_time']))) ?></td>
                <td>
                  <a class="btn primary" href="order_accept.php?id=<?= (int)$q['id'] ?>">Accept</a>
                  <a class="btn" href="order_decline.php?id=<?= (int)$q['id'] ?>">Decline</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="color:#64748b;">No new pending requests</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="card" style="margin-top:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 18px 5px 18px; border-bottom: 1px solid #f1f5f9;">
          <h3>Work History (Completed)</h3>
          <a class="btn primary" href="booking_request.php?tab=Completed" style="background:#0ea5e9;">View All</a>
        </div>

        <table class="table">
          <thead>
            <tr>
              <th>Service</th>
              <th>Customer</th>
              <th>Contact</th>
              <th>Scheduled Time</th>
              <th>Completed Time</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($history && $history->num_rows): ?>
            <?php while($hrow = $history->fetch_assoc()): ?>
              <tr>
                <td><?= h($hrow['service_name'] ?: 'N/A') ?></td>
                <td><?= h($hrow['customer_name'] ?: 'N/A') ?></td>
                <td><?= h($hrow['contact'] ?: '-') ?></td>
                <td><?= h(date('d M Y, h:i A', strtotime($hrow['date_time']))) ?></td>
                <td><?= h($hrow['completion_time'] ? date('d M Y, h:i A', strtotime($hrow['completion_time'])) : '-') ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="color:#64748b;">No completed works</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

  <script>
  (function(){
    const btn = document.querySelector('.sidebar-toggle');
    if(!btn) return;

    // restore state
    const saved = localStorage.getItem('sidebar_open');
    if(saved === '0') document.body.classList.add('sidebar-closed');

    btn.addEventListener('click', function(){
      document.body.classList.toggle('sidebar-closed');
      const isOpen = !document.body.classList.contains('sidebar-closed');
      localStorage.setItem('sidebar_open', isOpen ? '1' : '0');
    });
  })();
  </script>
</body>
</html>