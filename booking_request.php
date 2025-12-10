<?php
// booking_request.php — Technician view
error_reporting(E_ALL);
ini_set('display_errors','1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/db.php';

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$tech_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0;
if ($tech_id <= 0) { header("Location: {$BASE}login.php"); exit; }

$allowed = ['Pending','Accepted','Declined','Completed','All'];
$tab = $_GET['tab'] ?? 'Pending';
if (!in_array($tab, $allowed, true)) $tab = 'Pending';

if ($tab === 'All') {
  $sql = "
    SELECT o.id, o.service_name, o.date_time, o.status,
           COALESCE(u.name, CONCAT(u.first_name,' ',u.last_name), u.email, CONCAT('Customer #', u.id)) AS customer_name,
           u.email, u.mobile, u.profile_image
    FROM orders o
    LEFT JOIN users u ON u.id = o.customer_id
    WHERE o.technician_id = ?
      AND o.status IN ('Completed', 'Declined')
    ORDER BY o.date_time DESC, o.id DESC
    LIMIT 200
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $tech_id);
} else {
  $sql = "
    SELECT o.id, o.service_name, o.date_time, o.status,
           COALESCE(u.name, CONCAT(u.first_name,' ',u.last_name), u.email, CONCAT('Customer #', u.id)) AS customer_name,
           u.email, u.mobile, u.profile_image
    FROM orders o
    LEFT JOIN users u ON u.id = o.customer_id
    WHERE o.technician_id = ? AND o.status = ?
    ORDER BY o.date_time DESC, o.id DESC
    LIMIT 200
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('is', $tech_id, $tab);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($dt){ $ts=strtotime($dt); return $ts?date('M d, Y h:i A',$ts):$dt; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Requests</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
/* --- Theme & Base --- */
:root{
  --bg: #f6f7fb;
  --panel: #ffffff;
  --line: #e9edf3;
  --muted: #6b7280;
  --ink: #0f172a;
  --brand: #635bff;
  --ok: #16a34a;
  --no: #dc2626;
}

*{ box-sizing: border-box }
html,body{ height:100% }
body{
  margin:0;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  color:var(--ink);
  background:
    radial-gradient(1200px 600px at -10% -10%, #eef2ff 0%, transparent 40%),
    radial-gradient(1200px 600px at 110% -10%, #e9f0ff 0%, transparent 40%),
    var(--bg);
}

/* --- Layout --- */
.container{ max-width:1120px; margin:32px auto 40px; padding:0 18px; }

/* --- Header --- */
header{
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 20px 18px;
  margin-bottom: 22px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 12px 28px rgba(15, 23, 42, .06);
  position: relative;
}
header h1{ margin:0; font-size:20px; letter-spacing:.2px; color:var(--brand); }
header .sub{ color:var(--muted); font-size:13px; margin-top:6px }

/* Back button */
header a[href$="technician.php"]{
  position:absolute; right:14px; top:14px;
  display:inline-flex; align-items:center; gap:8px;
  padding:10px 14px;
  border-radius:999px;
  background:var(--brand);
  color:#fff; text-decoration:none; font-weight:700;
  box-shadow:0 8px 18px rgba(99,91,255,.25);
  transition:transform .15s ease, filter .15s ease;
}
header a[href$="technician.php"]::before{ content:"←"; font-weight:800; }
header a[href$="technician.php"]:hover{ transform:translateY(-1px); filter:brightness(1.05); }
header a[href$="technician.php"]:active{ transform:translateY(0); }

/* Tabs */
.tabs{ display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; justify-content:center; }
.tabs a{
  display:inline-block;
  padding:7px 12px;
  border-radius:999px;
  border:1px solid var(--line);
  background:#fff;
  color:#0b1220;
  text-decoration:none;
  font-weight:600;
  font-size:13px;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.tabs a:hover{ border-color:#d6dbeb; }
.tabs a.active{
  background:var(--brand);
  color:#fff;
  border-color:var(--brand);
  box-shadow:0 6px 14px rgba(99,91,255,.25);
}

/* --- Cards --- */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:16px;
}
.card{
  background:var(--panel);
  border:1px solid var(--line);
  border-radius:18px;
  padding:16px;
  box-shadow:0 1px 2px rgba(15,23,42,.04),0 14px 34px rgba(15,23,42,.06);
  transition:transform .18s ease, box-shadow .18s ease;
}
.card:hover{
  transform:translateY(-2px);
  box-shadow:0 2px 6px rgba(15,23,42,.06),0 20px 44px rgba(15,23,42,.1);
}

/* Card Content */
.top{ display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.avatar{ width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid #eef1f7; }
.name{ font-weight:700; letter-spacing:.2px; }
.email{ font-size:12.5px; color:var(--muted); }

.service{ margin:6px 0 4px; font-weight:700; font-size:14px; color:var(--brand); }
.datetime{ color:var(--muted); font-size:12.5px; margin-bottom:10px; }

/* Status */
.status{
  display:inline-block; padding:5px 10px;
  border-radius:999px; font-weight:700; font-size:12px;
  border:1px solid var(--line);
}
.status.Pending{ background:#fff7ed; color:#9a3412; border-color:#fde5c5; }
.status.Accepted{ background:#ecfdf5; color:#166534; border-color:#b7f0d1; }
.status.Declined{ background:#fef2f2; color:#991b1b; border-color:#f7c9c9; }
.status.Completed{ background:#eef2ff; color:#1e3a8a; border-color:#ced8ff; }

/* Actions */
.actions{ display:flex; gap:10px; margin-top:12px; }
.btn{
  display:inline-block; text-align:center;
  padding:10px 12px; border-radius:12px;
  font-weight:700; text-decoration:none; border:1px solid transparent;
  transition:transform .15s ease, filter .15s ease;
}
.btn:hover{ transform:translateY(-1px); }
.btn:active{ transform:translateY(0); }
.btn-accept{
  background:var(--ok); color:#fff;
  box-shadow:0 10px 20px rgba(22,163,74,.18);
}
.btn-decline{
  background:#fff; color:var(--no); border-color:#fecaca;
}
.btn-decline:hover{ background:#fff5f5; }

/* Empty + Toast */
.empty{ text-align:center; color:var(--muted); font-size:14px; margin-top:40px; }
.toast{
  position:fixed; top:20px; right:20px;
  background:#16a34a; color:#fff;
  padding:12px 18px; border-radius:12px; font-weight:700;
  box-shadow:0 10px 24px rgba(22,163,74,.25);
  z-index:999; animation:fadeout 4s forwards;
}
@keyframes fadeout{ 0%,90%{opacity:1} 100%{opacity:0;visibility:hidden} }

/* Responsive */
@media(max-width:600px){
  header{ padding:16px 14px; }
  header a[href$="technician.php"]{ right:10px; top:10px; padding:8px 12px; }
  .grid{ gap:12px; }
}
</style>
</head>

<body>
<?php if (isset($_GET['ok'])): ?>
  <div class="toast">✅ Booking <?= h($_GET['ok']) ?> successfully!</div>
<?php endif; ?>

<div class="container">
  <header>
    <h1>Booking Requests</h1>
    <a href="technician.php">Back to Dashboard</a>
    <div class="tabs">
      <?php foreach ($allowed as $t): ?>
        <a href="?tab=<?= urlencode($t) ?>" class="<?= $tab===$t?'active':'' ?>"><?= h($t) ?></a>
      <?php endforeach; ?>
    </div>
  </header>

  <?php if (!$rows): ?>
    <div class="empty">No bookings found for “<?= h($tab) ?>”.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $r): ?>
        <div class="card">
          <div class="top">
            <img class="avatar" src="<?= h($r['profile_image'] ?: $BASE.'uploads/default-avatar.png') ?>" alt="avatar">
            <div>
              <div class="name"><?= h($r['customer_name'] ?: 'Customer') ?></div>
              <div class="email"><?= h($r['email'] ?: '') ?><?= $r['mobile'] ? ' • '.h($r['mobile']) : '' ?></div>
            </div>
          </div>
          <div class="service">Service: <?= h($r['service_name']) ?></div>
          <div class="datetime"><?= fmt_dt($r['date_time']) ?></div>
          <div class="status <?= h($r['status']) ?>"><?= h($r['status']) ?></div>

          <?php if (strcasecmp($r['status'],'Pending')===0): ?>
            <div class="actions">
              <a class="btn btn-accept" href="<?= $BASE ?>order_accept.php?id=<?= (int)$r['id'] ?>">Accept</a>
              <a class="btn btn-decline" href="<?= $BASE ?>order_decline.php?id=<?= (int)$r['id'] ?>">Decline</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
