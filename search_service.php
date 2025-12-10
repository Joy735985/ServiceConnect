<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once 'db.php';

if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$customer_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'];

// Load skills for suggestions
$skills = [];
$res = $conn->query("SELECT DISTINCT skill_name FROM technician_skills WHERE skill_name <> '' ORDER BY skill_name");
while ($r = $res->fetch_assoc()) {
  $skills[] = $r['skill_name'];
}
if ($res) {
  $res->close();
}

// Read filters
$service    = trim($_GET['service'] ?? '');
$min_budget = isset($_GET['min_budget']) && $_GET['min_budget'] !== '' ? (float)$_GET['min_budget'] : null;
$max_budget = isset($_GET['max_budget']) && $_GET['max_budget'] !== '' ? (float)$_GET['max_budget'] : null;
$sort       = trim($_GET['sort'] ?? 'rating');

// NEW: Read selected date. Removed service_time parameter.
$service_date = trim($_GET['service_date'] ?? date('Y-m-d')); 
$service_time = ''; // Hardcoded empty since it's removed from UI

// ----------------------------
// ✅ ONLY CHANGE: FORCE TOMORROW MINIMUM
// ----------------------------
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// if date is today or past, force tomorrow
if ($service_date < $tomorrow) {
    $service_date = $tomorrow;
}
// ----------------------------

$techs = [];

if ($service !== '') {
  // --- Load calculation parameters ---
  $load_date_sql = $service_date; 
  
  $sql = "
    SELECT 
      u.id AS tech_id,
      COALESCE(
        NULLIF(u.name,''),
        NULLIF(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')),' '),
        NULLIF(u.email,''),
        CONCAT('Technician #', u.id)
      ) AS tech_name,
      u.profile_image AS avatar,
      COALESCE(rates.amount, 0)       AS rent_amount,
      COALESCE(rates.unit, 'per job') AS rent_unit,
      COALESCE(ROUND(AVG(rt.rating),1), 0) AS avg_rating,
      COUNT(rt.id) AS rating_count,
      
      -- Experience: Max 5 years for 45%
      COALESCE(e.years_of_experience,0) AS years_of_experience,
      (LEAST(COALESCE(e.years_of_experience,0), 5) / 5.0) * 45.0 AS experience_score,
      
      -- Rating: Max 5 stars for 45%
      (COALESCE(ROUND(AVG(rt.rating),1), 0) / 5.0) * 45.0 AS rating_score,

      -- Load: Calculate occupied slots for the SELECTED DATE
      (
        SELECT COUNT(o.id) FROM orders o 
        WHERE o.technician_id = u.id 
          AND DATE(o.date_time) = ? -- Parameter used for load_date_sql
          AND o.status IN ('Accepted', 'Pending')
      ) AS occupied_slots,
      
      -- Total Score calculation
      (
        (COALESCE(ROUND(AVG(rt.rating),1), 0) / 5.0) * 45.0 + 
        (LEAST(COALESCE(e.years_of_experience,0), 5) / 5.0) * 45.0 + 
        (LEAST(5 - 
          (SELECT COUNT(o.id) FROM orders o 
            WHERE o.technician_id = u.id 
              AND DATE(o.date_time) = ? -- Parameter used for load_date_sql
              AND o.status IN ('Accepted', 'Pending')), 5
        ) / 5.0) * 10.0
      ) AS total_score
      
    FROM technician_skills ts
    JOIN users u ON u.id = ts.technician_id AND LOWER(u.role) = 'technician'
    LEFT JOIN technician_skill_rates rates 
      ON rates.technician_id = ts.technician_id 
     AND rates.skill_name = ts.skill_name
    LEFT JOIN ratings rt ON rt.technician_id = ts.technician_id
    LEFT JOIN technician_experience e ON e.technician_id = u.id
    WHERE ts.skill_name = ?
  ";

  $types  = "sss"; // 2 for Load Date (in occupied_slots subqueries) + 1 for Service Name
  $params = [$load_date_sql, $load_date_sql, $service];

  // 🔹 Apply budget filters
  if ($min_budget !== null) {
    $sql   .= " AND rates.amount >= ?";
    $types .= "d";
    $params[] = $min_budget;
  }
  if ($max_budget !== null) {
    $sql   .= " AND rates.amount <= ?";
    $types .= "d";
    $params[] = $max_budget;
  }

  $sql .= " GROUP BY u.id, tech_name, avatar, rent_amount, rent_unit, years_of_experience ";

  // dynamic ORDER BY based on sort
  if ($sort === 'total_score') {
    $sql .= " ORDER BY total_score DESC, avg_rating DESC, tech_name ASC ";
  } else if ($sort === 'budget_low') {
    $sql .= " ORDER BY rent_amount ASC, avg_rating DESC, tech_name ASC ";
  } else if ($sort === 'budget_high') {
    $sql .= " ORDER BY rent_amount DESC, avg_rating DESC, tech_name ASC ";
  } else {
    $sql .= " ORDER BY avg_rating DESC, rent_amount ASC, tech_name ASC ";
  }

  $stmt = $conn->prepare($sql);
  // Dynamic binding for parameters
  $bindParams = array_merge([$types], $params);
  $refs = [];
  foreach ($bindParams as $key => $value) {
      $refs[$key] = &$bindParams[$key];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);

  $stmt->execute();
  $techs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// For showing values back in the form
$min_budget_value = $min_budget !== null ? $min_budget : '';
$max_budget_value = $max_budget !== null ? $max_budget : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Find a Service</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg1:#eef2ff;
      --bg2:#e0f2fe;
      --bg3:#dcfce7;
      --panel:#ffffff;
      --line:#e3e9f3;
      --muted:#6b7280;
      --ink:#0f172a;
      --brand:#4f6cff;
      --brand2:#7b8cff;
      --accent:#22c55e;
      --accent2:#06b6d4;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{
      font-family: 'Poppins', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:var(--ink);
      background:
        radial-gradient(1200px 600px at 8% -10%, var(--bg1), transparent),
        radial-gradient(1200px 600px at 92% -10%, var(--bg2), transparent),
        radial-gradient(1200px 700px at 50% 120%, var(--bg3), transparent),
        #f6f8fc;
      min-height:100vh;
    }

    .container{
      max-width:1080px;
      margin:0 auto;
      padding:26px 16px 40px;
    }

    /* Header bar like 2nd screenshot */
    .header-bar{
      background:rgba(255,255,255,0.95);
      border:1px solid rgba(148,163,184,.35);
      border-radius:18px;
      padding:14px 16px;
      box-shadow:0 10px 30px rgba(15,23,42,.08);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:14px;
    }
    .header-left{
      display:flex;
      align-items:center;
      gap:10px;
      font-size:18px;
      font-weight:700;
    }
    .dot{
      width:10px;height:10px;border-radius:50%;
      background:linear-gradient(135deg,var(--brand),var(--accent2));
      box-shadow:0 0 0 4px rgba(99,91,255,.12);
    }

    .back-btn{
      display:inline-flex;align-items:center;gap:8px;
      padding:10px 16px;
      border-radius:12px;
      border:1px solid var(--line);
      background:#f8fafc;
      color:#0f172a;text-decoration:none;
      font-weight:600;font-size:14px;
      box-shadow:0 4px 12px rgba(15,23,42,.06);
    }

    /* Search panel like 2nd screenshot */
    .search-panel{
      background:rgba(255,255,255,0.95);
      border-radius:20px;
      padding:16px;
      border:1px solid rgba(148,163,184,.35);
      box-shadow:0 16px 40px rgba(15,23,42,.10);
      margin-bottom:18px;
    }

    /* Google pill search bar */
    .search-pill{
      display:flex;
      align-items:center;
      gap:10px;
      background:#fff;
      border:1px solid var(--line);
      border-radius:999px;
      padding:10px 12px 10px 16px;
      box-shadow:0 10px 24px rgba(15,23,42,.06);
      position:relative;
    }
    .search-pill .icon{
      font-size:18px;
      color:var(--accent2);
    }
    .search-pill input{
      flex:1;
      border:none;
      outline:none;
      font-size:16px;
      padding:6px 4px;
      background:transparent;
      color:var(--ink);
    }
    .search-pill input::placeholder{color:#94a3b8}

    .btn{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 18px;border-radius:999px;border:none;
      background:linear-gradient(135deg,var(--brand),var(--brand2));
      color:#fff;font-weight:600;font-size:14px;
      box-shadow:0 10px 24px rgba(79,70,229,.30);cursor:pointer;
      white-space:nowrap;
    }

    /* Filters row */
    .filters{
      margin-top:12px;
      display:grid;
      grid-template-columns:1fr 1fr 1fr 1fr; /* 4 columns now */
      gap:12px;
      align-items:end;
    }
    @media (max-width:1100px){
      .filters{grid-template-columns:repeat(3,1fr);}
    }
    @media (max-width:768px){
      .filters{grid-template-columns:repeat(2,1fr);}
    }
    @media (max-width:500px){
      .filters{grid-template-columns:1fr;}
    }

    .label{
      font-size:13px;color:#111827;font-weight:600;margin:0 0 6px 2px;
    }
    .input{
      width:100%;height:44px;padding:0 12px;border-radius:12px;
      border:1px solid var(--line);font-size:14px;background:#fff;
      transition:box-shadow .15s ease,border-color .15s ease;
    }
    .input:focus{
      border-color:#d6dbeb;
      box-shadow:0 0 0 3px rgba(99,91,255,.10);
      outline:none;
    }

    .reset-row{
      margin-top:12px;
      display:flex;
      justify-content:flex-end;
    }
    .reset-btn{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 18px;border-radius:12px;border:1px solid var(--line);
      background:#f8fafc;color:#111827;font-weight:600;font-size:14px;
      cursor:pointer;
      box-shadow:0 4px 10px rgba(15,23,42,.06);
      text-decoration:none;
    }

    .muted{color:var(--muted);font-size:14px}

    /* Results grid/cards */
    .grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
      gap:16px;
      margin-top:14px;
      margin-bottom:30px;
    }
    .card{
      background:rgba(255,255,255,0.96);
      border-radius:20px;
      padding:22px;
      box-shadow:0 2px 4px rgba(15,23,42,.05),0 14px 36px rgba(99,91,255,.15);
      margin-bottom:20px;
      position:relative;
      overflow:hidden;
      border:1px solid rgba(226,232,240,.9);
    }
    .row{display:flex;gap:16px;align-items:center}
    .avatar{
      width:64px;height:64px;border-radius:50%;
      object-fit:cover;border:2px solid rgba(148,163,184,.4);
      background:#e5e7eb;
    }
    .name{font-weight:700;font-size:16px}
    .rent{
      margin-left:auto;text-align:right;font-weight:700;font-size:18px;
    }
    .rent .sub{font-size:12px;color:var(--muted);font-weight:500}
    .actions{
      margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center
    }
    .actions .input{height:40px;font-size:14px}

    /* kept for safety, even though not used now */
    .badge-pill{
      position:absolute;right:20px;top:20px;
      font-size:11px;text-transform:uppercase;letter-spacing:.06em;
      padding:4px 10px;border-radius:999px;
      background:rgba(34,197,94,.1);color:#16a34a;
      border:1px solid rgba(34,197,94,.25);
    }

    .suggestions{
      position:absolute;left:0;right:0;top:110%;
      background:#fff;border-radius:14px;margin-top:6px;
      box-shadow:0 18px 45px rgba(15,23,42,.15);
      max-height:220px;overflow:auto;z-index:20;
      border:1px solid rgba(226,232,240,.9);
      display:none;
    }
    .suggestions div{
      padding:8px 12px;font-size:14px;cursor:pointer;
    }
    .suggestions div:hover{
      background:rgba(99,102,241,.06);
    }

    .empty{text-align:center;color:var(--muted);margin-top:30px}
  </style>

  <style>
    body{
      background:
        radial-gradient(1200px 600px at 0% -10%, #fdf2f8, transparent),
        radial-gradient(1200px 600px at 100% -10%, #eef2ff, transparent),
        radial-gradient(1100px 700px at 50% 120%, #ecfeff, transparent),
        linear-gradient(180deg, #f8fafc 0%, #f6f8ff 40%, #f0fdf4 100%);
    }

    .header-bar{
      position:relative;
      overflow:hidden;
      border:none;
      background: rgba(255,255,255,.92);
      backdrop-filter: blur(8px);
      box-shadow: 0 12px 30px rgba(2,6,23,.10);
    }
    .header-bar::before{
      content:"";
      position:absolute;left:0;right:0;top:0;height:5px;
      background: linear-gradient(90deg,#22c55e,#0ea5e9,#6366f1,#8b5cf6,#ec4899,#f59e0b);
    }
    .header-left{
      background: linear-gradient(90deg,#0ea5e9,#6366f1,#ec4899);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
      font-weight:900;
    }

    .back-btn{
      background: linear-gradient(135deg,#ffffff,#f8fafc);
      border:1px solid rgba(148,163,184,.55);
      transition:.2s ease;
    }
    .back-btn:hover{
      transform: translateY(-2px);
      box-shadow:0 10px 24px rgba(15,23,42,.10);
      border-color:rgba(99,102,241,.6);
    }

    .search-panel{
      position:relative;
      overflow:hidden;
      border:none;
      background: rgba(255,255,255,.93);
      backdrop-filter: blur(8px);
    }
    .search-panel::before{
      content:"";
      position:absolute;left:0;right:0;top:0;height:6px;
      background: linear-gradient(90deg,#0ea5e9,#22c55e,#f59e0b,#ec4899,#8b5cf6);
    }

    .search-pill{
      border:1px solid rgba(148,163,184,.5);
      box-shadow:0 12px 28px rgba(99,91,255,.18);
      transition:.2s ease;
    }
    .search-pill:focus-within{
      box-shadow:0 0 0 4px rgba(99,102,241,.12), 0 14px 34px rgba(6,182,212,.22);
      border-color:rgba(99,102,241,.7);
    }

    .btn{
      background:
        linear-gradient(135deg,#0ea5e9 0%, #6366f1 45%, #ec4899 100%);
      box-shadow:0 12px 26px rgba(99,102,241,.35);
      transition:.2s ease;
    }
    .btn:hover{
      transform: translateY(-2px);
      box-shadow:0 16px 36px rgba(236,72,153,.35);
      filter:saturate(1.1);
    }

    .input:focus{
      border-color:#a5b4fc;
      box-shadow:
        0 0 0 3px rgba(14,165,233,.12),
        0 0 0 6px rgba(236,72,153,.08);
    }

    .reset-btn{
      background: linear-gradient(135deg,#fff,#f8fafc);
      border:1px solid rgba(148,163,184,.55);
      transition:.2s ease;
    }
    .reset-btn:hover{
      transform: translateY(-2px);
      box-shadow:0 10px 24px rgba(15,23,42,.10);
      border-color:rgba(34,197,94,.6);
      color:#16a34a;
    }

    .card{
      position:relative;
      border:none;
      background:
        linear-gradient(145deg, rgba(255,255,255,1), rgba(255,255,255,.95));
      box-shadow: 0 12px 30px rgba(2,6,23,.10), 0 0 0 1px rgba(226,232,240,.9) inset;
      transition:.25s ease;
    }
    .card:hover{
      transform: translateY(-5px);
      box-shadow: 0 18px 45px rgba(2,6,23,.16), 0 0 0 1px rgba(199,210,254,.9) inset;
    }
    .card::before{
      content:"";
      position:absolute;left:0;right:0;top:0;height:6px;
      background: linear-gradient(90deg,#22c55e,#0ea5e9,#6366f1,#8b5cf6,#ec4899,#f59e0b);
    }

    .avatar{
      border:3px solid transparent !important;
      background:
        linear-gradient(#fff,#fff) padding-box,
        conic-gradient(#22c55e, #0ea5e9, #6366f1, #ec4899, #f59e0b, #22c55e) border-box !important;
      box-shadow:0 10px 24px rgba(0,0,0,.18);
    }

    .name{
      font-size:17px;
      font-weight:900;
      background: linear-gradient(90deg,#0ea5e9,#6366f1,#ec4899);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
    }

    .rent{
      background: linear-gradient(90deg,#22c55e,#0ea5e9,#6366f1);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
      font-weight:900;
    }

    .suggestions{
      border:none;
      box-shadow:0 18px 50px rgba(99,102,241,.25);
    }
    .suggestions div:hover{
      background: linear-gradient(90deg, rgba(14,165,233,.08), rgba(236,72,153,.08));
    }

    .empty{
      background: rgba(255,255,255,.9);
      border-radius:14px;
      padding:16px;
      box-shadow:0 10px 24px rgba(15,23,42,.08);
      border:1px dashed rgba(148,163,184,.6);
    }

    /* =========================================================
       FIX 1: MAKE SUGGESTIONS SCROLLABLE
       ========================================================= */
    .search-panel{overflow:visible !important;}
    .search-pill{overflow:visible !important;}
    .suggestions{
      max-height:260px !important;
      overflow-y:auto !important;
      -webkit-overflow-scrolling:touch;
      overscroll-behavior:contain;
    }

    /* =========================================================
       FIX 2: SHOW FULL TEXT IN SEARCH INPUT (SAFARI FLEX ISSUE)
       ========================================================= */
    .search-pill > div{
      min-width:0 !important;   /* allow flex child to shrink correctly */
      flex:1 1 auto;
    }
    .search-pill input{
      width:100% !important;
      min-width:0 !important;
      padding-right:10px;       /* small breathing so text doesn't hide */
    }
  </style>
</head>
<body>

<div class="container">

  <div class="header-bar">
    <div class="header-left">
      <span class="dot"></span>
      <span>Search Technician</span>
    </div>
    <a href="customer.php" class="back-btn">← Back to Dashboard</a>
  </div>

  <div class="search-panel">

    <form id="searchForm" method="get" action="search_service.php">

      <div class="search-pill">
        <span class="icon">🔍</span>
        <div style="position:relative;flex:1;">
          <input
            id="serviceInput"
            name="service"
            placeholder="Search for a service (e.g. Plumbing, AC Repair)"
            value="<?= htmlspecialchars($service) ?>"
            autocomplete="off"
          />
          <div id="suggestions" class="suggestions"></div>
        </div>
        <button class="btn" type="submit">Search</button>
      </div>

      <div class="filters">
        <div>
          <div class="label">Service Date</div>
          <input 
            class="input"
            type="date"
            name="service_date"
            value="<?= htmlspecialchars($service_date) ?>"
            required
            min="<?= $tomorrow ?>"
          />
        </div>
        <div>
          <div class="label">Min Budget</div>
          <input
            class="input"
            type="number"
            step="0.01"
            min="0"
            name="min_budget"
            value="<?= htmlspecialchars($min_budget_value) ?>"
          />
        </div>

        <div>
          <div class="label">Max Budget</div>
          <input
            class="input"
            type="number"
            step="0.01"
            min="0"
            name="max_budget"
            value="<?= htmlspecialchars($max_budget_value) ?>"
          />
        </div>

        <div>
          <div class="label">Sort by</div>
          <select class="input" name="sort">
            <option value="total_score" <?= $sort==='total_score'?'selected':'' ?>>Total Score</option>
            <option value="rating" <?= $sort==='rating'?'selected':'' ?>>Best Rating</option>
            <option value="budget_low" <?= $sort==='budget_low'?'selected':'' ?>>Budget Low to High</option>
            <option value="budget_high" <?= $sort==='budget_high'?'selected':'' ?>>Budget High to Low</option>
          </select>
        </div>
      </div>

      <div class="reset-row">
        <a class="reset-btn" href="search_service.php">Reset</a>
      </div>

    </form>
  </div>

  <?php if ($service === ''): ?>
    <p class="muted">Start typing to pick a service.</p>
  <?php else: ?>
    <h3 style="margin:14px 0">
      Available technicians for
      <span style="color:var(--brand);font-weight:700;">
        <?= htmlspecialchars($service) ?>
      </span>
      <span class="muted" style="font-size:13px;">
        on **<?= date('M d, Y', strtotime($service_date)) ?>**
      </span>
      <?php if ($min_budget !== null || $max_budget !== null): ?>
        <span class="muted" style="font-size:13px;">
          (Filtered by budget
          <?php if ($min_budget !== null): ?>from ৳<?= number_format($min_budget, 2) ?><?php endif; ?>
          <?php if ($max_budget !== null): ?> to ৳<?= number_format($max_budget, 2) ?><?php endif; ?>)
        </span>
      <?php endif; ?>
    </h3>
    <?php if (!$techs): ?>
      <div class="empty">No technicians found for this search.</div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($techs as $t): ?>
          <div class="card">
            <div class="row">
              <img class="avatar"
                   src="<?= $t['avatar'] ? htmlspecialchars($t['avatar']) : 'uploads/default-avatar.png' ?>"
                   alt="">
              <div style="flex:1">
                <div class="name"><?= htmlspecialchars($t['tech_name']) ?></div>
                <div class="muted">
                  Experience: <?= (int)$t['years_of_experience'] ?> years
                </div>
                <div class="muted">
                  ⭐ <?= number_format((float)$t['avg_rating'],1) ?>
                  (<?= (int)$t['rating_count'] ?> reviews)
                </div>
                <div style="font-size:15px; font-weight:700; color:var(--accent); margin-top:4px;">
                    Total Score: <?= number_format((float)$t['total_score'], 1) ?> / 100
                </div>
                <div class="muted" style="font-size:11px;">
                    (Rating: <?= number_format((float)$t['rating_score'], 1) ?>, 
                    Exp: <?= number_format((float)$t['experience_score'], 1) ?>, 
                    Load: <?= number_format((5 - (int)$t['occupied_slots']) / 5 * 10, 1) ?>)
                </div>
              </div>
              <div class="rent">
                ৳<?= number_format((float)$t['rent_amount'],2) ?>
                <div class="sub"><?= htmlspecialchars($t['rent_unit']) ?></div>
              </div>
            </div>
            <form class="actions" method="get" action="schedule_booking.php">
              <input type="hidden" name="tech_id" value="<?= (int)$t['tech_id'] ?>">
              <input type="hidden" name="service" value="<?= htmlspecialchars($service) ?>">
              <input type="hidden" name="date" value="<?= htmlspecialchars($service_date) ?>">
              <button class="btn" type="submit">View Schedule / Book</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<script>
  const skills = <?= json_encode($skills) ?>;
  const form   = document.getElementById('searchForm');
  const input  = document.getElementById('serviceInput');
  const box    = document.getElementById('suggestions');
  const dateInput = document.querySelector('input[name="service_date"]');

  function showSuggestions(value){
    const v = value.toLowerCase().trim();
    if(!v){
      box.style.display='none';
      box.innerHTML='';
      return;
    }
    const items = skills.filter(s=>s.toLowerCase().includes(v)).slice(0,8);
    if(!items.length){
      box.style.display='none';
      box.innerHTML='';
      return;
    }
    box.innerHTML = items.map(s=>`<div>${s}</div>`).join('');
    box.style.display='block';
  }

  input.addEventListener('input',()=>showSuggestions(input.value));
  input.addEventListener('focus',()=>showSuggestions(input.value));
  box.addEventListener('click',e=>{
    if(e.target.tagName==='DIV'){
      input.value=e.target.textContent.trim();
      form.submit();
    }
  });
  document.addEventListener('click',e=>{
    if(!form.contains(e.target)) box.style.display='none';
  });

  // Ensure submitting the form triggers a search
  dateInput.addEventListener('change',()=> {
    if(input.value.trim() !== '') form.submit();
  });
</script>
</body>
</html>
