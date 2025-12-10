<?php
require 'db.php';
require 'functions.php';
requireRole(['Admin']);

$admin = $_SESSION['user'];

// counts
$techCount = $conn->query("SELECT COUNT(*) c FROM users WHERE role='Technician'")->fetch_assoc()['c'] ?? 0;
$custCount = $conn->query("SELECT COUNT(*) c FROM users WHERE role='Customer'")->fetch_assoc()['c'] ?? 0;

// messages table optional; fallback 0
$msgCount  = 0;
if ($conn->query("SHOW TABLES LIKE 'messages'")->num_rows) {
  $msgCount = $conn->query("SELECT COUNT(*) c FROM messages")->fetch_assoc()['c'] ?? 0;
}

// upcoming (next 5)
$upcoming = $conn->query("
  SELECT o.id, o.date_time, o.status,
         CONCAT(c.first_name,' ',c.last_name) AS customer_name,
         CONCAT(t.first_name,' ',t.last_name) AS tech_name
  FROM orders o
  LEFT JOIN users c ON c.id = o.customer_id
  LEFT JOIN users t ON t.id = o.technician_id
  WHERE o.date_time >= NOW()
  ORDER BY o.date_time ASC
  LIMIT 5
");
?>
<!doctype html>
<html lang="en">
<head>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="style.css">
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- =========================================================
       PROFESSIONAL DASHBOARD THEME
       ONLY CSS ADDED — NO CASE/HTML/PHP CHANGED
       ========================================================= -->
  <style>
    :root{
      --bg-a:#0b1020;
      --bg-b:#0a3a5a;
      --bg-c:#452b7d;
      --panel:rgba(255,255,255,.10);
      --panel-strong:rgba(255,255,255,.14);
      --panel-solid:#0f172a;
      --line:rgba(255,255,255,.14);
      --line-soft:rgba(255,255,255,.08);
      --ink:#e6edf7;
      --muted:#b7c3d6;

      --brand:#4f6cff;
      --brand2:#22d3ee;
      --accent:#22c55e;
      --warn:#f59e0b;
      --pink:#ec4899;
      --violet:#8b5cf6;
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:var(--ink);
      background:
        radial-gradient(1200px 800px at 0% -10%, #0ea5e9 0%, transparent 55%),
        radial-gradient(1200px 900px at 100% -10%, #8b5cf6 0%, transparent 55%),
        radial-gradient(1100px 900px at 50% 120%, #22c55e 0%, transparent 60%),
        linear-gradient(135deg, var(--bg-a), var(--bg-b) 45%, var(--bg-c));
      min-height:100vh;
    }

    /* Shell */
    .app{
      display:flex;
      min-height:100vh;
      gap:0;
    }

    /* Sidebar */
    .sidebar{
      width:260px;
      background:
        linear-gradient(180deg, rgba(7,12,32,.95), rgba(7,12,32,.85)),
        radial-gradient(600px 400px at 50% -20%, rgba(79,108,255,.25), transparent 70%);
      border-right:1px solid var(--line-soft);
      padding:18px 14px;
      position:sticky;top:0;height:100vh;
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
      backdrop-filter: blur(8px);
    }

    .brand{
      display:flex;align-items:center;gap:10px;
      font-weight:900;font-size:18px;letter-spacing:.3px;
      padding:10px 10px 16px;border-bottom:1px solid var(--line-soft);
      margin-bottom:10px;
      background: linear-gradient(90deg,#fff,#dbe7ff,#b7f9ff);
      -webkit-background-clip:text;background-clip:text;color:transparent;
    }
    .brand .dot{
      width:34px;height:34px;border-radius:10px;
      display:grid;place-items:center;
      background: linear-gradient(135deg,var(--brand),var(--brand2),var(--violet));
      color:white;font-weight:900;
      box-shadow:0 10px 22px rgba(79,108,255,.5);
    }

    .nav{
      display:flex;flex-direction:column;gap:6px;margin-top:8px;
    }
    .nav a{
      display:flex;align-items:center;gap:12px;
      padding:11px 12px;border-radius:12px;
      color:var(--ink);text-decoration:none;font-weight:600;font-size:14px;
      background:transparent;border:1px solid transparent;
      transition:.18s ease;
      position:relative;overflow:hidden;
    }
    .nav a i{width:18px;text-align:center;opacity:.9}
    .nav a:hover{
      background:rgba(255,255,255,.06);
      border-color:var(--line-soft);
      transform: translateX(2px);
    }
    .nav a.active{
      background: linear-gradient(90deg, rgba(79,108,255,.22), rgba(34,211,238,.18));
      border:1px solid rgba(79,108,255,.45);
      box-shadow:0 10px 22px rgba(0,0,0,.25);
    }
    .nav a.active::before{
      content:"";
      position:absolute;left:0;top:0;bottom:0;width:4px;
      background: linear-gradient(180deg,var(--brand),var(--brand2));
      border-radius:10px;
    }

    /* Main area */
    .main{
      flex:1;
      padding:22px 22px 28px;
    }

    /* Topbar */
    .topbar{
      display:flex;align-items:center;justify-content:space-between;
      background: var(--panel);
      border:1px solid var(--line);
      border-radius:16px;
      padding:16px 18px;
      box-shadow:0 14px 36px rgba(0,0,0,.25);
      backdrop-filter: blur(10px);
      margin-bottom:16px;
      position:relative;overflow:hidden;
    }
    .topbar::before{
      content:"";
      position:absolute;left:0;right:0;top:0;height:5px;
      background: linear-gradient(90deg,var(--accent),var(--brand2),var(--violet),var(--pink),var(--warn));
      opacity:.9;
    }
    .breadcrumb{
      color:var(--muted);font-size:13px;font-weight:600;
      letter-spacing:.2px;margin-bottom:4px;
    }
    .topbar h2{
      margin:0;font-size:20px;font-weight:900;
      background: linear-gradient(90deg,#fff,#dbe7ff,#b7f9ff);
      -webkit-background-clip:text;background-clip:text;color:transparent;
    }
    .avatar{
      width:44px;height:44px;border-radius:50%;
      background:
        radial-gradient(circle at 30% 30%, #fff, transparent 40%),
        conic-gradient(var(--brand),var(--brand2),var(--violet),var(--pink),var(--brand));
      border:2px solid rgba(255,255,255,.7);
      box-shadow:0 10px 22px rgba(0,0,0,.35);
    }

    /* Grid */
    .grid-2{
      display:grid;grid-template-columns:1.6fr .9fr;gap:18px;
    }
    @media (max-width:1100px){
      .grid-2{grid-template-columns:1fr;}
      .sidebar{position:relative;height:auto;width:100%}
    }

    /* Cards base */
    .card, .header-card{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius:16px;
      padding:16px;
      box-shadow:0 12px 30px rgba(0,0,0,.25);
      backdrop-filter: blur(10px);
      position:relative;overflow:hidden;
    }
    .card::before, .header-card::before{
      content:"";
      position:absolute;left:0;right:0;top:0;height:4px;
      background: linear-gradient(90deg,var(--brand),var(--brand2),var(--violet));
      opacity:.85;
    }
    .card h3{
      margin:0 0 12px;font-size:16px;font-weight:800;
      color:#f8fafc;
      display:flex;align-items:center;gap:8px;
    }

    /* Header card */
    .header-card{
      display:flex;align-items:center;gap:12px;margin-bottom:12px;
    }
    .badge{
      width:56px;height:56px;border-radius:14px;
      display:grid;place-items:center;font-size:22px;color:white;
      background: linear-gradient(135deg,var(--brand),var(--brand2),var(--violet));
      box-shadow:0 10px 22px rgba(79,108,255,.45);
      border:1px solid rgba(255,255,255,.2);
    }
    .header-meta{
      font-size:14px;color:var(--muted);line-height:1.5;
    }
    .header-meta strong{
      color:#fff;font-weight:900;font-size:16px;
    }

    /* Stats row */
    .stats{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
      gap:12px;margin-bottom:12px;
    }
    .stat{
      background: var(--panel-strong);
      border:1px solid var(--line);
      border-radius:16px;
      padding:14px;
      display:flex;gap:12px;align-items:center;
      box-shadow:0 10px 24px rgba(0,0,0,.22);
      position:relative;overflow:hidden;
      transition:.2s ease;
    }
    .stat:hover{
      transform: translateY(-3px);
      box-shadow:0 18px 40px rgba(0,0,0,.35);
      border-color:rgba(255,255,255,.28);
    }
    .stat::after{
      content:"";
      position:absolute;right:-40px;top:-40px;width:120px;height:120px;border-radius:50%;
      background: radial-gradient(circle, rgba(255,255,255,.18), transparent 65%);
    }
    .stat .ic{
      width:46px;height:46px;border-radius:12px;
      display:grid;place-items:center;font-size:18px;color:white;
      background: linear-gradient(135deg,var(--accent),var(--brand2));
      box-shadow:0 8px 18px rgba(34,211,238,.4);
      border:1px solid rgba(255,255,255,.2);
    }
    .stat:nth-child(2) .ic{
      background: linear-gradient(135deg,var(--violet),var(--brand));
      box-shadow:0 8px 18px rgba(139,92,246,.45);
    }
    .stat:nth-child(3) .ic{
      background: linear-gradient(135deg,var(--pink),var(--warn));
      box-shadow:0 8px 18px rgba(236,72,153,.45);
    }

    .stat h3{
      margin:0;font-size:22px;font-weight:900;color:#fff;line-height:1.1;
    }
    .stat p{
      margin:2px 0 6px;color:var(--muted);font-size:13px;font-weight:700;
    }
    .pill{
      display:inline-block;
      padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800;
      background:rgba(255,255,255,.12);
      border:1px solid rgba(255,255,255,.18);
      color:#fff;letter-spacing:.04em;
    }

    /* Table */
    .table{
      width:100%;
      border-collapse:collapse;
      background: transparent;
      border-radius:12px;
      overflow:hidden;
    }
    .table thead th{
      text-align:left;
      font-size:12px;text-transform:uppercase;letter-spacing:.06em;
      color:#e2e8f0;font-weight:800;
      padding:10px 12px;
      border-bottom:1px solid var(--line-soft);
      background: rgba(0,0,0,.18);
    }
    .table tbody td{
      padding:12px;
      border-bottom:1px dashed var(--line-soft);
      color:var(--ink);font-size:14px;font-weight:600;
    }
    .table tbody tr{
      transition:.15s ease;
    }
    .table tbody tr:hover{
      background: rgba(255,255,255,.05);
    }

    /* Buttons */
    .btn{
      padding:8px 14px;border-radius:999px;border:none;
      background: linear-gradient(135deg,var(--brand),var(--brand2),var(--violet));
      color:white;font-weight:800;font-size:13px;cursor:pointer;
      box-shadow:0 10px 20px rgba(79,108,255,.45);
      transition:.18s ease;
    }
    .btn:hover{transform: translateY(-2px);filter:saturate(1.15)}

    /* Right-side placeholder cards to look like charts */
    aside .card{
      min-height:160px;
      background:
        radial-gradient(500px 260px at 5% 0%, rgba(34,211,238,.18), transparent 70%),
        radial-gradient(520px 300px at 95% 0%, rgba(139,92,246,.22), transparent 70%),
        var(--panel);
      border:1px solid var(--line);
    }
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="dot">S</div>ServiceConnect
</div>
<nav class="nav">
  <a class="active" href="admin.php"><i class="fa-solid fa-house"></i> Dashboard</a>
  <a href="all_technicians.php"><i class="fa-solid fa-user-plus"></i> Hire Technician</a>
  <a href="booking_request.php"><i class="fa-solid fa-calendar-check"></i> Book Appointment</a>
  <a href="all_customers.php"><i class="fa-solid fa-list-check"></i> Recommended List</a>
  <a href="admin_skills.php"><i class="fa-solid fa-screwdriver-wrench"></i> Manage Skills</a>
  <a href="admin_wallet.php"><i class="fa-solid fa-wallet"></i> Payment</a>
  <a href="orders.php"><i class="fa-solid fa-location-dot"></i> Tracking</a>
  <a href="all_technicians.php"><i class="fa-solid fa-users"></i> All Technicians</a>
  <a href="rating.php"><i class="fa-solid fa-star"></i> Ratings</a>
  <a href="admin_withdrawals.php">Withdrawal Requests</a>
  <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</nav>

  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <div class="breadcrumb">Home / Dashboard</div>
        <h2>Welcome to Admin Dashboard</h2>
      </div>
      <div class="avatar" title="<?=esc($admin['email'])?>"></div>
    </div>

    <div class="grid-2">
      <section>
        <div class="header-card">
          <div class="badge"><i class="fa-solid fa-user-shield"></i></div>
          <div class="header-meta">
            <strong><?=esc($admin['first_name'].' '.$admin['last_name'])?></strong><br>
            Role: Admin | Mobile: <?=esc($admin['phone']??'—')?> | Email: <?=esc($admin['email'])?>
          </div>
        </div>

        <div class="stats">
          <div class="stat">
            <div class="ic"><i class="fa-solid fa-user-gear"></i></div>
            <div>
              <h3><?= (int)$techCount ?></h3>
              <p>Technicians</p>
              <span class="pill">Technicians</span>
            </div>
          </div>
          <div class="stat">
            <div class="ic"><i class="fa-solid fa-user"></i></div>
            <div>
              <h3><?= (int)$custCount ?></h3>
              <p>Customers</p>
              <span class="pill">Customers</span>
            </div>
          </div>
          <div class="stat">
            <div class="ic"><i class="fa-regular fa-comment-dots"></i></div>
            <div>
              <h3><?= (int)$msgCount ?></h3>
              <p>Message</p>
              <span class="pill">Message</span>
            </div>
          </div>
        </div>

        <div class="card">
          <h3>Upcoming Appointments</h3>
          <table class="table">
            <thead>
              <tr><th>Name</th><th>Technician</th><th>Time</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php if($upcoming->num_rows===0): ?>
                <tr><td colspan="4">No upcoming appointments</td></tr>
              <?php else: while($row=$upcoming->fetch_assoc()): ?>
                <tr>
                  <td><?=esc($row['customer_name']??'—')?></td>
                  <td><?=esc($row['tech_name']??'—')?></td>
                  <td><?=esc(date('h:i A, d M Y', strtotime($row['date_time'])))?></td>
                  <td><button class="btn">Done</button></td>
                </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <aside>
        <div class="card" style="height:160px"></div>
        <div class="card" style="margin-top:18px;height:360px"></div>
      </aside>
    </div>
  </main>
</div>
</body>
</html>
