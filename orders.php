<?php
// orders.php — Customer order list & rating entry point (FINAL)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/functions.php')) {
  require_once __DIR__ . '/functions.php';
}

// Require customer role if helper function exists
if (function_exists('requireRole')) {
  requireRole(['Customer','Admin']);
} elseif (!isset($_SESSION['user']['id']) && !isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$me = $_SESSION['user'] ?? [];
$customerId = $me['id'] ?? ($_SESSION['user_id'] ?? 0);
if ($customerId <= 0) {
  header("Location: login.php");
  exit;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

/**
 * Check if this customer has already rated a specific order
 */
function orderHasRating($conn, $orderId, $customerId) {
    $stmt = $conn->prepare(
        "SELECT id FROM ratings WHERE order_id = ? AND customer_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $orderId, $customerId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

/* =========================================================
   CANCEL ORDER HANDLER (FINAL FIX)
   - customer can cancel ONLY when status = Pending
   - auto-detect ENUM allowed values
   ========================================================= */
$toast = null;

function getEnumValues(mysqli $conn, $table, $column){
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $rs = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if(!$rs || $rs->num_rows === 0) return [];
    $row = $rs->fetch_assoc();
    $rs->close();

    $type = $row['Type'] ?? '';
    if (stripos($type, "enum(") !== 0) return [];

    // parse enum('A','B','C')
    preg_match("/^enum\\((.*)\\)$/i", $type, $m);
    if(empty($m[1])) return [];

    $vals = str_getcsv($m[1], ',', "'");
    return array_map('trim', $vals);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $cancelId = (int)$_POST['cancel_order_id'];

    if ($cancelId > 0) {
        // 1) Verify order belongs to this customer and is Pending
        $stmt = $conn->prepare("
            SELECT id, status 
            FROM orders 
            WHERE id = ? AND customer_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $cancelId, $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $orderRow = $res->fetch_assoc();
        $stmt->close();

        if ($orderRow && ($orderRow['status'] ?? '') === 'Pending') {

            // 2) Detect allowed status values
            $allowed = getEnumValues($conn, "orders", "status");

            // 3) Pick best cancel-like status that exists
            $cancelStatus = null;
            foreach (['Cancelled','Canceled','Declined','Rejected'] as $try) {
                if (in_array($try, $allowed, true)) {
                    $cancelStatus = $try;
                    break;
                }
            }

            // If status is not ENUM or we couldn't detect, fallback to 'Declined'
            if (!$cancelStatus) {
                $cancelStatus = 'Declined';
            }

            // 4) Update safely
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = ? 
                WHERE id = ? AND customer_id = ? AND status = 'Pending'
            ");
            $stmt->bind_param("sii", $cancelStatus, $cancelId, $customerId);
            $stmt->execute();
            $stmt->close();

            $toast = ['type' => 'success', 'msg' => "✅ Order {$cancelStatus} successfully."];

        } else {
            $toast = ['type' => 'error', 'msg' => '❌ You can only cancel Pending orders.'];
        }
    }
}

// Technician join (provider name)
$providerJoin  = '';
$providerField = 'NULL AS provider_name';
if (tableExists($conn, 'users')) {
  $providerJoin  = 'LEFT JOIN users t ON t.id = o.technician_id';
  $providerField = "
    COALESCE(
        NULLIF(t.name, ''),
        NULLIF(CONCAT(COALESCE(t.first_name,''),' ',COALESCE(t.last_name,'')),' '),
        CONCAT('Technician #', t.id)
    ) AS provider_name
  ";
}

// Load customer orders
$sql = "
  SELECT 
    o.id,
    o.service_name,
    o.date_time,
    o.status,
    $providerField
  FROM orders o
  $providerJoin
  WHERE o.customer_id = ?
  ORDER BY o.date_time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
while ($r = $res->fetch_assoc()) {
  $orders[] = $r;
}
$stmt->close();

// Logic for the optional "Switch Profile" link
$switchProfileLink = '';
$currentId = $customerId;
if ($currentId) {
    $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    $curEmail = $me['email'] ?? null;

    if ($curEmail) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND LOWER(role) = 'technician' LIMIT 1");
        $stmt_check->bind_param("s", $curEmail);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
          $switchProfileLink = '<a href="' . $BASE . 'switch_profile.php?to=technician" class="sidebar-link">🔁 Switch to Technician Profile</a>';
        } else {
          $switchProfileLink = '<a href="' . $BASE . 'signup.php?role=technician" class="sidebar-link">➕ Create Technician Account</a>';
        }
        $stmt_check->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Orders</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .table{width:100%;border-collapse:separate;border-spacing:0;}
    .pill{display:inline-block;padding:4px 10px;border-radius:14px;background:#f5f5f7}
    
    body{
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
      background: radial-gradient(circle at top left, #fdf2f8 0%, #eef2ff 35%, #ecfeff 70%, #f0fdf4 100%);
      color:#0f172a;
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

    .toprow h2{
      font-size:26px;
      font-weight:900;
      background: linear-gradient(90deg, #0ea5e9, #6366f1, #ec4899, #f59e0b);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
      margin:0;
    }

    .card{
      background:#fff;
      border-radius:14px;
      box-shadow: 0 12px 28px rgba(15,23,42,.08);
      overflow:hidden;
      border:1px solid rgba(15,23,42,.06);
      padding:20px;
    }
    .card h3{margin:0 0 15px;font-size:18px;color:#333}

    .table thead th{
      background: linear-gradient(90deg, #0ea5e9, #6366f1, #8b5cf6);
      color:#fff;
      font-weight:800;
      font-size:14px;
      text-transform:uppercase;
      letter-spacing:.5px;
      padding:10px 12px;
      text-align:left;
    }
    .table td{
      font-size:14px;
      color:#0f172a;
      padding:10px 12px;
      border-bottom:1px solid #eee;
    }
    .table tbody tr:nth-child(even){background:#f8fafc}
    .table tbody tr:hover{
      background:#ecfeff;
      transform:scale(1.005);
    }

    .table td .btn{
      background:#0ea5e9;
      color:#fff;
      padding:6px 10px;
      border-radius:8px;
      text-decoration:none;
      font-weight:600;
      font-size:13px;
      transition:.2s;
      display:inline-block;
      border:none;
      cursor:pointer;
    }
    .table td .btn:hover{background:#007bbd}

    /* cancel button */
    .btn-cancel{
      background:#ef4444 !important;
    }
    .btn-cancel:hover{
      background:#dc2626 !important;
    }

    .table td span{
      font-weight:600;
      color:#22c55e;
    }

    @media (max-width: 768px){
      .app{padding:10px;gap:10px;flex-direction:column;}
      .sidebar{height:auto;position:relative;}
      .toprow h2{font-size:22px;}
    }
  </style>
</head>
<body>

<?php if ($toast): ?>
  <div id="toast"
       style="position:fixed;right:16px;bottom:16px;
              background:<?= $toast['type']==='success' ? '#dcfce7' : '#fee2e2' ?>;
              color:<?= $toast['type']==='success' ? '#15803d' : '#991b1b' ?>;
              padding:10px 14px;border-radius:10px;font-weight:700;
              box-shadow:0 8px 24px rgba(0,0,0,.15);z-index:9999;">
    <?= h($toast['msg']) ?>
  </div>
  <script>
    setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.remove(); }, 2500);
  </script>
<?php endif; ?>

<div class="app">

  <aside class="sidebar">
    <div class="brand"><span class="dot">S</span> ServiceConnect</div>
    <nav class="nav">
      <a href="customer.php">Dashboard</a>
      <a href="search_service.php">Find Service</a>
      <a href="customer_edit_profile.php">Edit Profile</a>
      <a href="orders.php" class="active">My Orders</a>
      <a href="logout.php">Logout</a>
      <?= $switchProfileLink ?>
    </nav>
  </aside>

  <main class="main">

    <div class="toprow">
      <h2>My Orders</h2>
      <div class="pill">Order History</div>
    </div>

    <section class="card">
      <h3>All Orders</h3>

      <table class="table">
        <thead>
        <tr>
          <th>#</th>
          <th>Service</th>
          <th>Date &amp; Time</th>
          <th>Technician</th>
          <th>Status</th>
          <th>Rating</th>
          <th>Action</th>
        </tr>
        </thead>

        <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="7" style="text-align:center; color:#666;">No orders found.</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $i => $o): ?>
            <?php $hasRating = orderHasRating($conn, (int)$o['id'], $customerId); ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= h($o['service_name']) ?></td>
              <td><?= h(date('M d, Y h:i A', strtotime($o['date_time']))) ?></td>
              <td><?= h($o['provider_name'] ?: '-') ?></td>
              <td><?= h($o['status']) ?></td>

              <td>
                <?php if (($o['status'] ?? '') === 'Completed' && !$hasRating): ?>
                  <a class="btn" href="order_details.php?id=<?= (int)$o['id'] ?>">Rate technician</a>
                <?php elseif ($hasRating): ?>
                  <span>Rated</span>
                <?php else: ?>
                  <span>-</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if (($o['status'] ?? '') === 'Pending'): ?>
                  <form method="post" style="display:inline"
                        onsubmit="return confirm('Cancel this order?');">
                    <input type="hidden" name="cancel_order_id" value="<?= (int)$o['id'] ?>">
                    <button type="submit" class="btn btn-cancel">Cancel</button>
                  </form>
                <?php else: ?>
                  <span>-</span>
                <?php endif; ?>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </section>

  </main>
</div>

</body>
</html>
