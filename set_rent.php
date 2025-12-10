<?php
session_start();
require_once 'db.php';

// Helper: get current user id from common session keys
function current_user_id() {
  if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
  if (isset($_SESSION['id'])) return (int)$_SESSION['id'];
  if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  return 0;
}

$uid = current_user_id();
if ($uid <= 0) {
  header("Location: login.php");
  exit;
}

// Ensure role is available; if not in session, fetch from DB
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($role === null) {
  $rs = $conn->prepare("SELECT role FROM users WHERE id = ?");
  $rs->bind_param("i", $uid);
  $rs->execute();
  $r = $rs->get_result();
  if ($row = $r->fetch_assoc()) {
    $role = $row['role'];
    $_SESSION['role'] = $role;
  }
  $rs->close();
}

// Only technicians may access
if (strtolower((string)$role) !== 'technician') {
  http_response_code(403);
  ?>
  <!DOCTYPE html>
  <html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Access Restricted</title>
  <style>body{font-family:sans-serif;padding:24px} .card{border:1px solid #ddd;border-radius:12px;padding:16px;max-width:720px;margin:auto}</style>
  </head><body><div class="card">
    <h2>Access Restricted</h2><p>This page is only for Technician accounts.</p>
    <p><a href="technician.php">← Back to Dashboard</a></p></div></body></html>
  <?php
  exit;
}

// Load technician's skills
$skills = [];
$qs = $conn->prepare("SELECT skill_name FROM technician_skills WHERE technician_id = ? ORDER BY skill_name ASC");
$qs->bind_param("i", $uid);
$qs->execute();
$rs = $qs->get_result();
while ($row = $rs->fetch_assoc()) { $skills[] = $row['skill_name']; }
$qs->close();

// Load existing per-skill rates
$rates = []; // [skill_name] => ['amount'=>..., 'unit'=>..., 'currency'=>...]
if (!empty($skills)) {
  // Use IN clause to fetch all existing rates at once
  $placeholders = implode(',', array_fill(0, count($skills), '?'));
  $types = str_repeat('s', count($skills));

  $sql = "SELECT skill_name, amount, unit, currency
          FROM technician_skill_rates
          WHERE technician_id = ? AND skill_name IN ($placeholders)";

  $stmt = $conn->prepare($sql);
  // bind technician_id + each skill as separate params
  $bindTypes = 'i' . $types;
  $params = array_merge([$bindTypes, $uid], $skills);
  // workaround for dynamic bind_param
  $refs = [];
  foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
  call_user_func_array([$stmt, 'bind_param'], $refs);

  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $rates[$row['skill_name']] = [
      'amount'   => $row['amount'],
      'unit'     => $row['unit'],
      'currency' => $row['currency'],
    ];
  }
  $stmt->close();
}

$hasSaved = isset($_GET['saved']);
$validCurrencies = ['BDT','USD','INR'];
$validUnits = ['per hour','per day','per job'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Set Your Rent (Per Skill)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg: #0b1020; --card: rgba(255,255,255,0.08); --border: rgba(255,255,255,0.12);
      --text:#eaf0ff; --muted:#b8c1ff; --brand:#5b8cff; --brand-2:#7f56d9;
      --chip: rgba(255,255,255,.06); --chip-border: rgba(255,255,255,.14);
      --shadow: 0 12px 32px rgba(0,0,0,.35);
    }
    @media (prefers-color-scheme: light) {
      :root { --bg:#f6f8ff; --card:#fff; --border:rgba(0,0,0,.08); --text:#0b1020; --muted:#475569; --chip:#f4f6ff; --chip-border:rgba(0,0,0,0.08); --shadow:0 10px 26px rgba(2,6,23,.06); }
    }
    *{box-sizing:border-box}
    body{margin:0; font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto; background:
      radial-gradient(1200px 600px at 10% -20%, rgba(123,97,255,.25), transparent 60%),
      radial-gradient(1000px 500px at 100% 0%, rgba(51,92,255,.18), transparent 60%), var(--bg);
      color:var(--text); min-height:100vh; padding:36px 16px 56px; display:grid; place-items:start center;
    }
    .shell{width:100%; max-width:980px}
    .header{background:linear-gradient(135deg,var(--brand),var(--brand-2)); color:#fff; border-radius:18px; padding:22px; box-shadow:var(--shadow); display:flex; justify-content:space-between; align-items:center; gap:12px}
    .btn{appearance:none;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-ghost{background:rgba(255,255,255,.16); color:#fff; border:1px solid rgba(255,255,255,.28); text-decoration:none}
    .card{margin-top:16px; background:var(--card); border:1px solid var(--border); border-radius:16px; box-shadow:var(--shadow); padding:18px; backdrop-filter:saturate(140%) blur(8px)}

    .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px }
    .search { flex:1 1 240px; display:flex; gap:8px; align-items:center; background:var(--chip); border:1px solid var(--chip-border); border-radius:12px; padding:8px 12px }
    .search input { flex:1; background:transparent; border:0; outline:0; color:var(--text); font-size:14px }
    .btn-muted{background:var(--chip); color:var(--text); border:1px solid var(--chip-border); text-decoration:none; display:inline-block}

    .rates-grid { display:grid; grid-template-columns: repeat(2, minmax(280px, 1fr)); gap:12px }
    @media (max-width: 900px) { .rates-grid { grid-template-columns: 1fr; } }

    .rate-item { border:1px solid var(--chip-border); background:var(--chip); border-radius:14px; padding:14px }
    .rate-item h4 { margin:0 0 10px; font-size:16px }
    .row { display:grid; grid-template-columns: 1.1fr .9fr .9fr; gap:10px }
    @media (max-width:560px){ .row { grid-template-columns: 1fr 1fr; } }

    label{ font-weight:600; margin-bottom:6px; display:block; font-size:13px; color:var(--muted) }
    input[type="number"], select{
      width:100%; background:#fff0; border:1px solid var(--chip-border); color:var(--text);
      padding:10px 12px; border-radius:12px; outline:0; font-size:14px;
    }
    .footer{display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-top:12px}
    .notice{background:#0e9f6e; color:#fff; padding:10px 12px; border-radius:12px; margin:10px 0; display:none}
    .muted{ color:var(--muted) }
    .count { padding: 8px 12px; border-radius: 999px; background: linear-gradient(to right, rgba(91,140,255,.18), rgba(127,86,217,.18)); border:1px solid var(--chip-border); font-weight: 600; }
    .pill { display:inline-flex; align-items:center; gap:8px; background: var(--chip); border:1px solid var(--chip-border); border-radius: 999px; padding: 8px 12px; margin: 6px 6px 0 0; font-size: 13px; font-weight:600; }
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div>
        <h2 style="margin:0">Set Your Rent (Per Skill)</h2>
        <p style="margin:.35rem 0 0; opacity:.9">Define rates for each skill you have added.</p>
      </div>
      <a class="btn btn-ghost" href="technician.php">← Back to Dashboard</a>
    </div>

    <div class="card">
      <?php if ($hasSaved): ?>
        <div class="notice" id="savedNote">Saved successfully.</div>
        <script>setTimeout(()=>{var n=document.getElementById('savedNote'); if(n) n.style.display='none';}, 2200)</script>
      <?php endif; ?>

      <div class="toolbar">
        <div class="search" title="Type to filter skills">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/>
          </svg>
          <input id="search" type="text" placeholder="Search skills...">
        </div>
        <span class="count" id="skillCount"><?php echo count($skills); ?> skills</span>
      </div>

      <form action="save_rent.php" method="POST" id="rentForm">
        <div class="rates-grid" id="ratesGrid">
          <?php if (empty($skills)): ?>
            <p class="muted">You don't have any skills yet. Please add skills first.</p>
          <?php else: ?>
            <?php foreach ($skills as $skill):
              $r = $rates[$skill] ?? ['amount'=>'', 'unit'=>'per job', 'currency'=>'BDT'];
            ?>
            <div class="rate-item" data-label="<?php echo htmlspecialchars(strtolower($skill), ENT_QUOTES); ?>">
              <h4><?php echo htmlspecialchars($skill); ?></h4>
              <input type="hidden" name="skills[]" value="<?php echo htmlspecialchars($skill, ENT_QUOTES); ?>">
              <div class="row">
                <div>
                  <label>Amount</label>
                  <input type="number" step="0.01" min="0" name="amount[<?php echo htmlspecialchars($skill, ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($r['amount'], ENT_QUOTES); ?>" placeholder="e.g. 1500.00">
                </div>
                <div>
                  <label>Currency</label>
                  <select name="currency[<?php echo htmlspecialchars($skill, ENT_QUOTES); ?>]">
                    <?php foreach ($validCurrencies as $c): $sel = ($r['currency'] === $c) ? 'selected' : ''; ?>
                      <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label>Unit</label>
                  <select name="unit[<?php echo htmlspecialchars($skill, ENT_QUOTES); ?>]">
                    <?php foreach ($validUnits as $u): $sel = ($r['unit'] === $u) ? 'selected' : ''; ?>
                      <option value="<?php echo $u; ?>" <?php echo $sel; ?>><?php echo $u; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="footer">
          <div class="muted">Leave amount blank to keep it unset for that skill.</div>
          <div>
            <a href="technician.php" class="btn btn-muted">Cancel</a>
            <button type="submit" class="btn btn-ghost" style="border:1px solid rgba(255,255,255,.28); background:#fff; color:#0b1020">Save All</button>
          </div>
        </div>
      </form>
    </div>

    <?php if (!empty($rates)): ?>
    <div class="card">
      <h3 style="margin:6px 0 10px">Current Saved Rates</h3>
      <div>
        <?php foreach ($rates as $sk => $r): ?>
          <span class="pill"><?php echo htmlspecialchars($sk . ': ' . $r['currency'] . ' ' . number_format((float)$r['amount'], 2) . ' / ' . $r['unit']); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <script>
    // Show saved notice if present
    (function(){
      var note = document.getElementById('savedNote');
      if (note) { note.style.display = 'block'; }
    })();

    // Search filter
    const search = document.getElementById('search');
    const grid = document.getElementById('ratesGrid');
    const items = Array.from(grid.querySelectorAll('.rate-item'));
    search.addEventListener('input', (e) => {
      const v = e.target.value.trim().toLowerCase();
      items.forEach(it => {
        const lbl = it.getAttribute('data-label') || '';
        it.style.display = lbl.includes(v) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
