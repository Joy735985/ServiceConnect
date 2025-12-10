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

// Load current technician skills
$existing = [];
$exRes = $conn->query("SELECT skill_name FROM technician_skills WHERE technician_id = {$uid} ORDER BY skill_name");
if ($exRes) {
  while ($r = $exRes->fetch_assoc()) $existing[] = $r['skill_name'];
  $exRes->close();
}

// Load skills for suggestions (ADMIN CATALOG FIRST)
$skills = [];
if ($conn->query("SHOW TABLES LIKE 'skills_catalog'")->num_rows) {
  $res = $conn->query("SELECT skill_name FROM skills_catalog WHERE is_active=1 ORDER BY skill_name");
  while ($r = $res->fetch_assoc()) {
    $skills[] = $r['skill_name'];
  }
  if ($res) $res->close();
} else {
  // fallback to old behavior if catalog table doesn't exist
  $res = $conn->query("SELECT DISTINCT skill_name FROM technician_skills WHERE skill_name <> '' ORDER BY skill_name");
  while ($r = $res->fetch_assoc()) {
    $skills[] = $r['skill_name'];
  }
  if ($res) $res->close();
}


// Default suggested skills (fallback list)
$SUGGESTED_SKILLS = [
  'AC Repair', 'Plumbing', 'Electrical', 'Carpentry', 'Painting',
  'Appliance Repair', 'Cleaning', 'Pest Control', 'IT Support',
  'CCTV Installation', 'Generator Repair', 'Water Pump Repair',
  'Furniture Repair', 'Washing Machine Repair', 'Refrigerator Repair'
];

// Merge DB suggestions with fallback (unique)
if (count($skills)) {
  foreach ($skills as $s) {
    if (!in_array($s, $SUGGESTED_SKILLS)) $SUGGESTED_SKILLS[] = $s;
  }
  sort($SUGGESTED_SKILLS);
}

// Read optional saved flag
$saved = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Add Skill</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="serviq.css">
  <link rel="stylesheet" href="style.css">
  <style>
    :root{
      /* Outlook-inspired palette */
      --bg: #f5f7fb;
      --bg-soft: #edf1f8;
      --panel: #ffffff;
      --ink: #0f172a;
      --muted: #64748b;
      --line: #dbe3ef;

      --brand: #0f6cbd;       /* Outlook blue */
      --brand-2: #115ea3;     /* deeper blue */
      --brand-soft: #e8f1fb;  /* blue tint */
      --good: #107c10;        /* Outlook green */

      --chip-bg: #f2f6fc;
      --chip-border: #cfd9ea;
      --chip-checked: #0f6cbd;

      --shadow: 0 10px 28px rgba(16,24,40,.08);
      --radius: 18px;
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
      background:
        radial-gradient(1200px 600px at 10% -10%, #e9f2ff, transparent),
        radial-gradient(1200px 600px at 90% -10%, #eef2ff, transparent),
        var(--bg);
      color: var(--ink);
      min-height:100vh;
    }

    .wrap{
      max-width:1150px;
      margin: 18px auto;
      padding: 0 16px;
    }

    /* Top bar with Back to Dashboard */
    .topbar{
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 14px 16px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      box-shadow: var(--shadow);
      margin-bottom: 14px;
    }
    .topbar h1{
      margin:0; font-size:20px; font-weight:800; letter-spacing:.2px;
      display:flex; align-items:center; gap:8px;
    }
    .app-dot{
      width:10px;height:10px;border-radius:50%;background:var(--brand);
      box-shadow: 0 0 0 3px var(--brand-soft);
    }
    .top-actions{
      display:flex; gap:8px; align-items:center;
    }

    .btn{
      padding:10px 14px;
      border-radius:12px;
      border:1px solid var(--line);
      background:#fff;
      cursor:pointer;
      font-weight:700;
      font-size:14px;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:8px;
      color: var(--ink);
      transition: .15s ease;
      box-shadow: 0 2px 8px rgba(16,24,40,.04);
    }
    .btn:hover{ transform: translateY(-1px); }
    .btn-primary{
      background: var(--brand);
      color:#fff;
      border-color: transparent;
    }
    .btn-primary:hover{ background: var(--brand-2); }
    .btn-muted{
      background: var(--bg-soft);
      color: var(--ink);
    }

    .page{
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap:14px;
    }
    @media (max-width: 980px){
      .page{ grid-template-columns:1fr; }
    }

    .card{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding:16px;
    }

    .card h2{ margin:0 0 10px; font-size:21px; }
    .section-title{ margin:0 0 8px; font-size:18px; font-weight:800; }

    .search-box{
      display:flex; gap:8px; margin-bottom:12px;
      background: #f7f9fd;
      border:1px solid var(--line);
      padding:8px;
      border-radius:12px;
    }
    .search-box input{
      flex:1; border:none; background:transparent; outline:none; font-size:15px; color:var(--ink);
    }

    .skills{
      display:flex; flex-wrap:wrap; gap:8px;
      max-height:420px; overflow:auto; padding-right:4px;
    }

    .chip{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 12px; border-radius:999px;
      border:1px solid var(--chip-border);
      background: var(--chip-bg);
      cursor:pointer; user-select:none; font-size:14px;
      transition:.15s ease;
    }
    .chip input{ display:none; }
    .chip .check{
      width:18px;height:18px;border-radius:6px;
      background:#e6ebf5; display:grid; place-items:center;
      transition:.15s ease;
    }
    .chip input:checked + .check{ background: var(--chip-checked); }
    .chip input:checked ~ .label{ font-weight:800; color:#0b3b6b; }
    .chip:hover{ transform: translateY(-1px); }

    .footer{
      display:flex; align-items:center; justify-content:space-between;
      margin-top:12px; padding-top:12px; border-top:1px dashed #e6ebf5;
    }

    .pill{
      display:inline-block; padding:6px 10px; border-radius:999px;
      background: #edf2fa;
      font-size:13px; margin:4px 4px 0 0;
      border:1px solid var(--line);
    }

    .muted{ color:var(--muted); font-size:13px; }
    .note{ margin-top:8px; font-size:13px; color:#475569; }

    .toast{
      position:fixed; right:18px; bottom:18px; z-index:9;
      background: #0f172a; color:#fff; padding:12px 14px; border-radius:12px;
      opacity:0; transform: translateY(8px); transition:.25s ease;
    }
    .toast.show{ opacity:1; transform: translateY(0); }

    .form-group{ margin-top:12px; }
    .form-group label{
      display:block; font-weight:800; margin-bottom:6px; color:#1f2937;
    }
    .form-group input, .form-group textarea{
      width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;
      outline:none; font-size:14px; background:#fff;
      transition: .12s ease;
    }
    .form-group input:focus, .form-group textarea:focus{
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--brand-soft);
    }

    .kbd{
      display:inline-block; padding:2px 6px; border:1px solid var(--line);
      border-radius:6px; background:#fff; font-size:12px; font-weight:700;
    }
  </style>
</head>
<body>
  <div class="wrap">

    <!-- TOP BAR (Back to Dashboard restored) -->
    <div class="topbar">
      <h1><span class="app-dot"></span> Add Skills & Experience</h1>
      <div class="top-actions">
        <a href="technician.php" class="btn btn-muted">← Back to Dashboard</a>
      </div>
    </div>

    <div class="page">
      <!-- Left -->
      <div class="card">
        <h2>Add Your Skills</h2>

        <div class="search-box">
          <input id="search" type="text" placeholder="Search skill (e.g. Plumbing)" autocomplete="off">
          <button type="button" class="btn btn-muted" id="clearBtn">Clear</button>
        </div>

        <!-- Main Save Form -->
        <div>
          <form method="POST" action="save_skill.php" id="skillForm">
            <div class="skills" id="skillsGrid">
              <?php foreach ($SUGGESTED_SKILLS as $s):
                $checked = in_array($s, $existing) ? 'checked' : '';
              ?>
                <label class="chip" data-label="<?php echo htmlspecialchars(strtolower($s), ENT_QUOTES); ?>">
                  <input type="checkbox" name="skills[]" value="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>" <?php echo $checked; ?>>
                  <span class="check">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M5 12.5l4 4 10-10" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <span class="label"><?php echo htmlspecialchars($s); ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="form-group">
              <label for="years_of_experience">Years of Experience</label>
              <input type="number" id="years_of_experience" name="years_of_experience" min="0" max="60" required>
            </div>

            <div class="form-group">
              <label for="experience_details">Experience Details</label>
              <textarea id="experience_details" name="experience_details" rows="4" placeholder="Describe your work experience"></textarea>
            </div>

            <div class="footer">
              <div class="left-actions muted">
                Tip: Press <span class="kbd">/</span> to focus search
              </div>
              <div class="right-actions" style="display:flex; gap:8px;">
                <a href="technician.php" class="btn btn-muted">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Skills</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Right: current skills preview -->
      <div class="card">
        <h3 class="section-title">Your Current Skills</h3>
        <div>
          <?php if (count($existing)): ?>
            <?php foreach ($existing as $e): ?>
              <span class="pill">
                <?php echo htmlspecialchars($e); ?>
              </span>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="muted">No skills added yet.</p>
          <?php endif; ?>
        </div>
        <p class="note">These are already attached to your profile. Adding duplicates is automatically ignored.</p>
      </div>
    </div>
  </div>

  <div class="toast" id="toast">Skills saved successfully</div>

  <script>
    // Live search
    const search = document.getElementById('search');
    const skillsGrid = document.getElementById('skillsGrid');
    const chips = Array.from(skillsGrid.querySelectorAll('.chip'));
    const clearBtn = document.getElementById('clearBtn');

    function filterSkills(q){
      q = q.trim().toLowerCase();
      chips.forEach(ch => {
        const label = ch.dataset.label || '';
        ch.style.display = label.includes(q) ? 'inline-flex' : 'none';
      });
    }

    search.addEventListener('input', e => filterSkills(e.target.value));
    clearBtn.addEventListener('click', () => {
      search.value='';
      filterSkills('');
      search.focus();
    });

    // Focus search on "/"
    window.addEventListener('keydown', (e)=>{
      if(e.key === '/' && document.activeElement !== search){
        e.preventDefault();
        search.focus();
      }
    });

    // Toast if saved
    const saved = <?php echo json_encode($saved); ?>;
    if(saved){
      const t = document.getElementById('toast');
      t.classList.add('show');
      setTimeout(()=>t.classList.remove('show'), 1800);
    }
  </script>
</body>
</html>
