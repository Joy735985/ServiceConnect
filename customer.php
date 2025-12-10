<?php
// customer.php — Safe/robust Customer Dashboard
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/functions.php')) {
  require_once __DIR__ . '/functions.php';
}

// Role & session guard
if (function_exists('requireRole')) {
  requireRole(['Customer','Admin']);
} else {
  if (empty($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['Customer','Admin'], true)) {
    header('Location: login.php'); exit;
  }
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  die('Database connection $conn not set. Check db.php');
}

$me = $_SESSION['user'];
$customerId = (int)($me['id'] ?? 0);
if (!$customerId) { header('Location: login.php'); exit; }

// ----------------- Helpers (Must match functions.php and avoid redeclaration) -----------------
function hasTable(mysqli $conn, $name) {
  $n = $conn->real_escape_string($name);
  $rs = $conn->query("SHOW TABLES LIKE '$n'");
  $ok = $rs && $rs->num_rows > 0;
  if ($rs) $rs->close();
  return $ok;
}
function hasColumn(mysqli $conn, $table, $col) {
  $t = $conn->real_escape_string($table);
  $c = $conn->real_escape_string($col);
  $rs = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  $ok = $rs && $rs->num_rows > 0;
  if ($rs) $rs->close();
  return $ok;
}


// ----------------- Quick stats -----------------
$activeOrders   = 0;
$walletBalance  = 0.00;
$avgRating      = null;
$avatar         = '';
$greetingName   = $me['first_name'] ?? 'Customer';

// (A) pull avatar + wallet from users
$selectCols = array('profile_image');
$balanceCol = null;
if (hasColumn($conn, 'users', 'wallet_balance')) $balanceCol = 'wallet_balance';
elseif (hasColumn($conn, 'users', 'balance')) $balanceCol = 'balance';
if ($balanceCol) $selectCols[] = $balanceCol;
if (hasColumn($conn, 'users', 'rating')) $selectCols[] = 'rating';

$cols = implode(',', array_map(function($c) { return "`$c`"; }, $selectCols));
$stmt = $conn->prepare("SELECT $cols FROM users WHERE id=?");
if ($stmt) {
  $stmt->bind_param("i", $customerId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $avatar = $row['profile_image'] ?? '';
    if ($balanceCol && isset($row[$balanceCol])) $walletBalance = (float)$row[$balanceCol];
    if (isset($row['rating'])) $avgRating = (float)$row['rating'];
  }
  $stmt->close();
}

// (B) active orders count (Pending or Accepted)
$ordersTableExists = hasTable($conn, 'orders');
if ($ordersTableExists) {
  $hasStatus = hasColumn($conn, 'orders', 'status');
  $statusSql = $hasStatus ? "AND status IN ('Pending','Accepted','In Progress')" : "";
  $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? $statusSql");
  if ($stmt) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($activeOrders);
    $stmt->fetch();
    $stmt->close();
  }
}

// (C) average rating from ratings table (optional)
if (($avgRating === null || $avgRating <= 0) && hasTable($conn, 'ratings')) {
  if (hasColumn($conn, 'ratings', 'customer_id') && hasColumn($conn, 'ratings', 'rating')) {
    $stmt = $conn->prepare("SELECT ROUND(AVG(rating),1) FROM ratings WHERE customer_id=?");
    if ($stmt) {
      $stmt->bind_param("i", $customerId);
      $stmt->execute();
      $stmt->bind_result($avgRating);
      $stmt->fetch();
      $stmt->close();
    }
  }
}
$avgRating = $avgRating ? (float)$avgRating : 4.8;

// ----------------- Active/Recent Orders (Last 5) -----------------
$upcoming = array();
if ($ordersTableExists) {
  $providerJoin = '';
  $providerField = 'provider';
  
  if (hasColumn($conn, 'orders', 'technician_id') && hasTable($conn, 'users')) {
    $providerJoin = "LEFT JOIN users t ON t.id = o.technician_id";
    $providerName = "CONCAT(COALESCE(t.first_name,''),' ',COALESCE(t.last_name,''))";
    $providerField = "$providerName AS provider";
  } else {
    $providerField = "NULL AS provider";
  }

  $serviceCol = hasColumn($conn, 'orders', 'service_name') ? 'o.service_name' : "NULL";
  $statusCol  = hasColumn($conn, 'orders', 'status')       ? 'o.status'       : "NULL";
  $updatedCol = hasColumn($conn, 'orders', 'updated_at')   ? 'o.updated_at'   : 'o.date_time'; 

  $orderBy = "$updatedCol DESC, o.date_time DESC";

  $sql = "
    SELECT 
      o.id,
      $serviceCol AS service_name,
      o.date_time,
      $statusCol  AS status,
      $providerField
    FROM orders o
    $providerJoin
    WHERE o.customer_id = ? 
    ORDER BY $orderBy
    LIMIT 5";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $upcoming[] = $r;
    }
    $stmt->close();
  }
}

$avatarUrl = $avatar && trim($avatar) !== '' ? htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8')
                                             : 'https://via.placeholder.com/64';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Customer Dashboard - ServiceConnect</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* =========================================================
       TECHNICIAN DASHBOARD STYLES COPIED FOR CUSTOMER.PHP
       ========================================================= */

    :root {
        --secondary: #6366f1;
        --primary: #0ea5e9;
        --text-muted: #64748b;
        --error: #ef4444;
        --success-bg: #dcfce7;
        --success-ink: #15803d;
        --card: #fff;
        --line: #e2e8f0;
        --bg: #f8fafc;
        --radius-lg: 14px;
        --sidebar-active-bg: #6366f1;
    }

    body{
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
      background: radial-gradient(circle at top left, #fdf2f8 0%, #eef2ff 35%, #ecfeff 70%, #f0fdf4 100%);
      color:#0f172a;
    }

    .layout{
      min-height:100vh;
      display:flex; 
      gap:18px;
      padding:18px;
      width:auto;
    }
    .main{
      flex:1;
      background: rgba(255,255,255,.75);
      backdrop-filter: blur(8px);
      border-radius:18px;
      padding:18px 18px 24px;
      box-shadow: 0 10px 30px rgba(15,23,42,.08);
      max-width: none;
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
      min-width: 220px;
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
    .nav a.active i { 
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
    
    .topbar {
      background: linear-gradient(90deg, #ffffff 0%, #f8fafc 50%, #ffffff 100%);
      padding:14px 14px;
      border-radius:14px;
      box-shadow: inset 0 0 0 1px rgba(15,23,42,.06);
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .topbar h2{
        font-size:26px;
        font-weight:900;
        background: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899, #f59e0b);
        -webkit-background-clip:text;
        background-clip:text;
        color:transparent;
        margin: 5px 0 0;
    }
    .topbar .breadcrumb{
        color: #64748b !important;
    }

    .header-card {
        display:flex;
        align-items:center;
        gap: 16px;
        background: linear-gradient(135deg, #0855a1ff, #040617ff);
        padding: 18px;
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15,23,42,.05);
        border: 1px solid rgba(15,23,42,.06);
        margin-bottom: 22px;
    }
    .header-card img {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        border:3px solid transparent;
        background:
            linear-gradient(#fff,#fff) padding-box,
            conic-gradient(#22c55e, #0ea5e9, #6366f1, #ec4899, #f59e0b, #22c55e) border-box;
        box-shadow: 0 10px 28px rgba(0,0,0,.18);
    }

    .stats {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
        gap:14px;
        margin-top: 0 !important;
    }

    .stats .stat {
        display:flex;
        justify-content: space-between; 
        align-items: center;
        padding:14px;
        border-radius:12px;
        background:#fff;
        box-shadow: 0 12px 30px rgba(2,6,23,.10);
        transition:.25s ease;
        position:relative;
        overflow:hidden;
        border:none; 
    }
    .stats .stat:hover{
        transform: translateY(-4px);
        box-shadow: 0 18px 40px rgba(2,6,23,.16);
    }
    .stats .stat::before{
        content:"";
        position:absolute;
        top:0;left:0;right:0;height:5px;
        background: linear-gradient(90deg,#22c55e,#0ea5e9,#6366f1,#8b5cf6,#ec4899,#f59e0b);
    }

    .stat-content .value { 
        font-size: 26px; 
        font-weight: 700; 
        background: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899);
        -webkit-background-clip:text;
        background-clip:text;
        color:transparent;
        margin-top: 0 !important; 
    }
    .stat-content .label { 
        font-size: 13px; 
        color: #666;
    }

    .stats .stat .ic {
        font-size: 24px;
        color: #6366f1;
        width: fit-content;
        margin-left: 10px;
    }
    .stats .stat a.pill {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        border: none;
        box-shadow: 0 4px 10px rgba(0,0,0,.15);
        text-decoration: none;
        background: #0ea5e9; 
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

    .pill-status { 
        display: inline-block;
        padding: 4px 10px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #fff !important;
    }
    .pill-status.accepted,
    .pill-status.inprogress { 
        background: #0ea5e9;
    }
    .pill-status.pending { 
        background: #f97316;
    }
    .pill-status.completed { 
        background: #22c55e;
    }
    
    @media (max-width: 768px){
      .layout{padding:10px;gap:10px;flex-direction:column;}
      .sidebar{height:auto;position:relative;}
      .main{padding:10px;}
    }

    /* ===========================
       NEW CHATBOT UI (FINAL)
       =========================== */
    #chat-bubble{
      position:fixed; right:22px; bottom:22px;
      width:62px; height:62px; border-radius:50%;
      background:linear-gradient(135deg,#6366f1,#0ea5e9);
      color:#fff; display:grid; place-items:center;
      font-size:24px; cursor:pointer; z-index:9999;
      box-shadow:0 12px 30px rgba(99,102,241,.45);
      transition:.2s ease;
    }
    #chat-bubble:hover{ transform:translateY(-3px) scale(1.03); }

    #chat-window{
      position:fixed; right:22px; bottom:100px;
      width:360px; max-width:92vw; height:520px; max-height:75vh;
      background:#fff; border-radius:18px;
      display:none; flex-direction:column;
      box-shadow:0 20px 60px rgba(15,23,42,.25);
      border:1px solid #e2e8f0; z-index:9999; overflow:hidden;
      animation:chatPop .22s ease;
    }
    @keyframes chatPop{
      from{ transform:translateY(10px); opacity:0; }
      to{ transform:translateY(0); opacity:1; }
    }

    #chat-header{
      padding:12px 14px;
      background:linear-gradient(135deg,#6366f1,#0ea5e9);
      color:#fff; font-weight:800; font-size:15px;
      display:flex; justify-content:space-between; align-items:center;
    }
    #chat-header .chat-title{ display:flex; gap:8px; align-items:center; }
    #chat-header button{
      background:rgba(255,255,255,.2);
      border:none; color:#fff; width:32px; height:32px;
      border-radius:10px; cursor:pointer; font-size:15px;
    }
    #chat-header button:hover{ background:rgba(255,255,255,.28); }

    #chat-body{
      flex:1; padding:14px; overflow-y:auto; background:#f8fafc;
      display:flex; flex-direction:column; gap:10px;
    }
    .msg{
      max-width:82%; padding:10px 12px; border-radius:14px;
      font-size:14px; line-height:1.4; word-wrap:break-word;
      box-shadow:0 6px 18px rgba(15,23,42,.06);
    }
    .msg.bot{
      background:#fff; align-self:flex-start; border:1px solid #e5e7eb;
    }
    .msg.user{
      background:#6366f1; color:#fff; align-self:flex-end;
    }
    .msg .time{ display:block; font-size:11px; opacity:.6; margin-top:4px; }

    #chat-typing{
      display:none; font-size:13px; color:#64748b;
      padding:0 14px 8px;
    }

    #chat-input-area{
      padding:10px; background:#fff; border-top:1px solid #e5e7eb;
      display:flex; gap:8px; align-items:center;
    }
    #chat-input{
      flex:1; height:44px; padding:0 12px; border-radius:12px;
      border:1px solid #e5e7eb; outline:none; font-size:14px;
    }
    #chat-input:focus{
      border-color:#a5b4fc;
      box-shadow:0 0 0 3px rgba(99,102,241,.12);
    }
    #chat-send{
      width:44px; height:44px; border-radius:12px;
      border:none; background:#6366f1; color:#fff; cursor:pointer;
      font-size:16px; display:grid; place-items:center;
    }
    #chat-send:hover{ background:#4f46e5; }

  </style>
</head>
<body>

<div class="layout">
  <aside class="sidebar">
    <div class="brand"><span class="dot">S</span> ServiceConnect</div>
    <nav class="nav">
      <a class="active" href="customer.php"><i class="fa-solid fa-house"></i> Dashboard</a>
      <a href="search_service.php"><i class="fa-solid fa-magnifying-glass"></i> Find Service</a>
      <a href="customer_edit_profile.php"><i class="fa-solid fa-user-edit"></i> Edit Profile</a>
      <a href="orders.php"><i class="fa-solid fa-list-check"></i> My Orders</a>
      <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      <?php
        $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
        $currentId = $_SESSION['user']['id'] ?? null;
        if ($currentId) {
          $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
          $stmt->bind_param("i", $currentId);
          $stmt->execute();
          $stmt->bind_result($curEmail);
          $stmt->fetch();
          $stmt->close();

          if ($curEmail) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND LOWER(role) = 'technician' LIMIT 1");
            $stmt->bind_param("s", $curEmail);
            $stmt->execute();
            $stmt->store_result();

            $linkClass = 'class="sidebar-link"';

            if ($stmt->num_rows > 0) {
              echo '<a href="', $BASE, 'switch_profile.php?to=technician" ', $linkClass, '><i class="fa-solid fa-repeat"></i> Switch to Technician Profile</a>';
            } else {
              echo '<a href="', $BASE, 'signup.php?role=technician" ', $linkClass, '><i class="fa-solid fa-plus-circle"></i> Create Technician Account</a>';
            }
            $stmt->close();
          }
        }
      ?>
    </nav>
  </aside>

  <main class="main">
    
    <div class="topbar">
        <div>
            <div class="breadcrumb">Home / Dashboard</div>
            <h2>Welcome back, <?= htmlspecialchars($greetingName); ?>!</h2>
            <p style="margin:4px 0 0; font-size:14px; color:var(--text-muted);">
                Customer Dashboard Overview
            </p>
        </div>
    </div>

    <section class="header-card">
        <div class="badge">
             <img src="<?= $avatarUrl ?>" alt="Profile Photo">
        </div>
        <div class="header-meta">
            <strong><?= esc($me['first_name'] ?? '').' '.esc($me['last_name'] ?? '') ?></strong><br>
            <span style="color: var(--text-muted); font-size: 13px;">Role: <?= esc($me['role']) ?> | Email: <?= esc($me['email']) ?></span>
        </div>
    </section>

    <section class="stats">
      <div class="stat">
        <div class="stat-content">
          <span class="label">Active Orders</span>
          <span class="value"><?= (int)$activeOrders ?></span>
        </div>
        <div class="ic"><i class="fa-solid fa-list-check"></i></div>
      </div>
      <div class="stat">
        <div class="stat-content">
          <span class="label">Book a Service</span>
          <a href="search_service.php" class="pill" style="margin-top: 10px; background: #ec4899;">Find Now</a>
        </div>
        <div class="ic"><i class="fa-solid fa-calendar-plus"></i></div>
      </div>
    </section>

    <section class="card" style="margin-top:22px">
      <h3>Recent Orders (Status: Active or Completed)</h3>
      <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Service</th>
              <th>Date &amp; Time</th>
              <th>Provider</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$upcoming): ?>
            <tr><td colspan="5" style="color:var(--text-muted);">No recent bookings.</td></tr>
          <?php else: foreach ($upcoming as $i => $row): ?>
            <?php 
                $statusClass = strtolower($row['status'] ?? 'pending'); 
                if (in_array($statusClass, ['accepted', 'in progress'])) {
                    $statusClass = 'accepted';
                } elseif ($statusClass === 'completed') {
                    $statusClass = 'completed';
                } else {
                    $statusClass = 'pending';
                }
            ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($row['service_name'] ?? '—') ?></td>
              <td><?= isset($row['date_time']) ? date('M d, Y h:i A', strtotime($row['date_time'])) : '—' ?></td>
              <td><?= htmlspecialchars(($row['provider'] ?? '') !== '' ? $row['provider'] : '—') ?></td>
              <td><span class="pill-status <?= $statusClass ?>"><?= htmlspecialchars($row['status'] ?? '—') ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
    </section>
  </main>
</div>

<!-- ===========================
     NEW CHATBOT (FINAL)
     =========================== -->
<div id="chat-bubble" title="AI Assistant">
  <i class="fa-regular fa-comments"></i>
</div>

<div id="chat-window" aria-hidden="true">
  <div id="chat-header">
    <div class="chat-title">
      <span>🤖</span>
      <span>ServiceConnect AI</span>
    </div>
    <button id="chat-close"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <div id="chat-body"></div>
  <div id="chat-typing">AI is typing...</div>

  <div id="chat-input-area">
    <input id="chat-input" type="text" placeholder="Ask me anything about ServiceConnect..." autocomplete="off" />
    <button id="chat-send"><i class="fa-solid fa-paper-plane"></i></button>
  </div>
</div>

<?php if (!empty($_GET['booking']) && $_GET['booking'] === 'success'): ?>
  <div id="bookedToast" 
       style="position:fixed;right:16px;bottom:16px;background:var(--success-bg);color:var(--success-ink);padding:10px 14px;border-radius:10px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);z-index:9999; border: 1px solid var(--success-ink);">
    ✅ Booking confirmed successfully!
  </div>
  <script>
    setTimeout(function(){
      var toast = document.getElementById('bookedToast');
      if(toast) toast.remove();
    }, 2500);
  </script>
<?php endif; ?>

<script>
/* ===========================
   NEW CHATBOT LOGIC (FINAL)
   - Works without API
   - If you later add /chat_api.php it auto-uses it
   =========================== */

const bubble = document.getElementById("chat-bubble");
const win    = document.getElementById("chat-window");
const closeB = document.getElementById("chat-close");
const body   = document.getElementById("chat-body");
const input  = document.getElementById("chat-input");
const sendB  = document.getElementById("chat-send");
const typing = document.getElementById("chat-typing");

let started = false;

// ---------- UI helpers ----------
function timeNow(){
  return new Date().toLocaleTimeString([], {hour:"2-digit", minute:"2-digit"});
}
function addMsg(text, who="bot"){
  const div = document.createElement("div");
  div.className = `msg ${who}`;
  div.innerHTML = `${text}<span class="time">${timeNow()}</span>`;
  body.appendChild(div);
  body.scrollTop = body.scrollHeight;
}
function setTyping(on){
  typing.style.display = on ? "block" : "none";
  if(on) body.scrollTop = body.scrollHeight;
}

// ---------- Open / close ----------
function openChat(){
  win.style.display = "flex";
  win.setAttribute("aria-hidden","false");
  input.focus();

  if(!started){
    started = true;
    addMsg(
      `Hi! I’m your ServiceConnect assistant. 😊<br>
       Try asking things like:<br>
       • "How to book a service?"<br>
       • "How to cancel an order?"<br>
       • "Wallet recharge?"`
    );
  }
}
function closeChat(){
  win.style.display = "none";
  win.setAttribute("aria-hidden","true");
}

bubble.addEventListener("click", () => {
  if(win.style.display === "flex") closeChat();
  else openChat();
});
closeB.addEventListener("click", closeChat);

// ---------- Simple offline AI brain ----------
function localAI(userText){
  const t = userText.toLowerCase();

  if(t.includes("book") || t.includes("schedule")){
    return `To book a service:<br>
      1) Go to <b>Find Service</b><br>
      2) Search a service<br>
      3) Choose date (from tomorrow)<br>
      4) Click <b>View Schedule / Book</b> and select a slot.`;
  }

  if(t.includes("cancel") || t.includes("decline")){
    return `You can cancel before technician accepts:<br>
      1) Go to <b>My Orders</b><br>
      2) Open the order<br>
      3) Press <b>Cancel</b> (if available).`;
  }

  if(t.includes("wallet") || t.includes("payment") || t.includes("recharge")){
    return `For wallet/payment:<br>
      • Check your balance on dashboard<br>
      • Add money from Wallet page<br>
      • After service, payment is deducted automatically.`;
  }

  if(t.includes("rating") || t.includes("review")){
    return `After a job is completed:<br>
      1) Open <b>My Orders</b><br>
      2) Click completed order<br>
      3) Submit rating + review.`;
  }

  if(t.includes("technician") || t.includes("provider")){
    return `Technicians are matched based on rating, experience & availability.<br>
    You can filter by budget and sort in Find Service.`;
  }

  return `I can help with booking, orders, ratings, and wallet.  
Try asking a short question like “How to book?”`;
}

// ---------- Send / receive ----------
async function handleSend(){
  const text = input.value.trim();
  if(!text) return;

  addMsg(text, "user");
  input.value = "";
  setTyping(true);

  try{
    const res = await fetch("chat_api.php", {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify({message:text})
    });

    if(res.ok){
      const data = await res.json();
      if(data && data.reply){
        setTyping(false);
        addMsg(data.reply, "bot");
        return;
      }
    }
  }catch(e){
    // fallback to local AI
  }

  setTimeout(() => {
    setTyping(false);
    addMsg(localAI(text), "bot");
  }, 600);
}

sendB.addEventListener("click", handleSend);
input.addEventListener("keydown", (e)=>{
  if(e.key === "Enter") handleSend();
});
</script>

</body>
</html>