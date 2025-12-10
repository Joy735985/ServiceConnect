<?php
require 'db.php';
require 'functions.php';
requireRole(['Admin']);

$admin = $_SESSION['user'];

// ---- filters & pagination ----
$q         = trim($_GET['q'] ?? '');
$status    = trim($_GET['status'] ?? '');   // optional: active / inactive
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;
$offset    = ($page - 1) * $per_page;

$where  = "WHERE role='Customer'";
$params = [];
$types  = "";

// search by name/email/phone
if ($q !== '') {
  $where .= " AND (CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ? 
                OR email LIKE ? 
                OR phone LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types   .= "sss";
}

// optional status filter
if ($status !== '') {
  $where .= " AND status = ?";
  $params[] = $status;
  $types   .= "s";
}

// total count
$count_sql  = "SELECT COUNT(*) AS c FROM users $where";
$count_stmt = $conn->prepare($count_sql);
if ($types !== "") { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total = (int)($count_stmt->get_result()->fetch_assoc()['c'] ?? 0);
$count_stmt->close();
$total_pages = max(1, (int)ceil($total / $per_page));

// list query
$list_sql = "
  SELECT id, first_name, last_name, email, phone, address, status, created_at
  FROM users
  $where
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
";
$list_stmt = $conn->prepare($list_sql);
if ($types !== "") {
  $types2  = $types . "ii";
  $params2 = array_merge($params, [ $per_page, $offset ]);
  $list_stmt->bind_param($types2, ...$params2);
} else {
  $list_stmt->bind_param("ii", $per_page, $offset);
}
$list_stmt->execute();
$customers = $list_stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>All Customers</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{
      --bg:#f6f7fb; --card:#fff; --ink:#1f2330; --muted:#7a7a9d; --line:#eceef5;
      --primary:#5b3dd9; --primary-ink:#fff; --shadow:0 8px 24px rgba(31,35,48,.08);
      --radius:16px; --success-bg:#e9f9ef; --success-ink:#0b8243;
      --danger-bg:#ffe9e9; --danger-ink:#b32020;
    }
    body{background:var(--bg); color:var(--ink)}
    .main{background:transparent}
    .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); padding:20px}
    .breadcrumb{color:var(--muted)}
    .filters{display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px}
    .filters input,.filters select{background:#fff; border:1px solid var(--line); border-radius:12px; padding:10px 12px; min-width:240px; transition:box-shadow .2s,border-color .2s}
    .filters input:focus,.filters select:focus{outline:0; border-color:#d8d3ff; box-shadow:0 0 0 4px rgba(91,61,217,.15)}
    .btn{display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:0; border-radius:12px; cursor:pointer; background:linear-gradient(135deg,var(--primary) 0%,#7b62ee 100%); color:var(--primary-ink); box-shadow:0 6px 18px rgba(91,61,217,.25); text-decoration:none; font-weight:600}
    .btn:hover{transform:translateY(-1px); box-shadow:0 8px 22px rgba(91,61,217,.32)}
    .btn.secondary{background:#eff0fb; color:#433c7c; box-shadow:none}
    .btn.secondary:hover{background:#e5e7fb}
    .table{width:100%; border-collapse:separate; border-spacing:0}
    .table thead th{background:#f4f5fb; color:#3c3f52; font-weight:700; border-bottom:1px solid var(--line); padding:14px 12px; position:sticky; top:0; z-index:1}
    .table tbody td{padding:14px 12px; border-bottom:1px solid var(--line)}
    .table tbody tr:nth-child(even){background:#fafbff}
    .table tbody tr:hover{background:#f2f3ff}
    .pill-status{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-weight:600; font-size:.85rem}
    .pill-status.active{background:var(--success-bg); color:var(--success-ink)}
    .pill-status.inactive{background:var(--danger-bg); color:var(--danger-ink)}
    .pager{margin-top:14px; display:flex; gap:8px; flex-wrap:wrap}
    .pager a{padding:8px 12px; border-radius:10px; border:1px solid var(--line); text-decoration:none; color:#3c3f52; background:#fff}
    .pager a.current{background:var(--primary); color:#fff; border-color:transparent; box-shadow:0 6px 18px rgba(91,61,217,.25)}
    @media (max-width:760px){.table thead{display:none}.table,.table tbody,.table tr,.table td{display:block;width:100%}.table tr{margin-bottom:12px; box-shadow:var(--shadow); border-radius:14px; overflow:hidden}.table td{border:0; border-bottom:1px solid var(--line)}.table td:last-child{border-bottom:0}}
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="dot">S</div>ServIQ</div>
    <nav class="nav">
      <a href="admin.php"><i class="fa-solid fa-house"></i> Dashboard</a>
      <a href="#"><i class="fa-solid fa-user-plus"></i> Hire Technician</a>
      <a href="#"><i class="fa-solid fa-calendar-check"></i> Book Appointment</a>
      <a href="#"><i class="fa-solid fa-list-check"></i> Recommended List</a>
      <a href="#"><i class="fa-solid fa-wallet"></i> Payment</a>
      <a href="#"><i class="fa-solid fa-location-dot"></i> Tracking</a>
      <a href="#"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a>
      <a href="all_technicians.php"><i class="fa-solid fa-users"></i> All Technicians</a>
      <a class="active" href="all_customers.php"><i class="fa-solid fa-user"></i> All Customers</a>
      <a href="#"><i class="fa-solid fa-star"></i> Ratings</a>
      <a href="admin_withdrawals.php">Withdrawal Requests</a>
      <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <div class="breadcrumb"><a href="admin.php" style="text-decoration:none">Home</a> / All Customers</div>
        <h2>All Customers</h2>
      </div>
      <div class="avatar" title="<?=esc($admin['email'])?>"></div>
    </div>

    <div class="card">
      <form class="filters" method="get" action="">
        <input type="text" name="q" placeholder="Search by name, email, phone" value="<?=esc($q)?>">
        <select name="status">
          <option value="">All status</option>
          <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($q!=='' || $status!==''): ?>
          <a class="btn secondary" href="all_customers.php">Reset</a>
        <?php endif; ?>
      </form>

      <table class="table">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Status</th><th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($customers->num_rows === 0): ?>
            <tr><td colspan="7">No customers found.</td></tr>
          <?php else: $i = $offset + 1; while($c = $customers->fetch_assoc()):
            $isActive = strtolower($c['status'] ?? '') === 'active'; ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= esc(trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? ''))) ?></td>
              <td><?= esc($c['email'] ?? '') ?></td>
              <td><?= esc($c['phone'] ?? '') ?></td>
              <td><?= esc($c['address'] ?? '') ?></td>
              <td><span class="pill-status <?= $isActive?'active':'inactive' ?>"><?= esc(ucfirst($c['status'] ?? '')) ?></span></td>
              <td><small><?= esc($c['created_at'] ?? '') ?></small></td>
            </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1): ?>
        <div class="pager">
          <?php for ($p=1; $p<=$total_pages; $p++):
            $qs = http_build_query(array_filter(['q'=>$q,'status'=>$status,'page'=>$p])); ?>
            <a class="<?= $p==$page?'current':'' ?>" href="all_customers.php?<?= $qs ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
