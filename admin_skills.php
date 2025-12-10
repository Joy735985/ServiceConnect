<?php
require 'db.php';
require 'functions.php';
requireRole(['Admin']);

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $newSkill = trim($_POST['skill_name'] ?? '');
  if ($newSkill === '') {
    $err = 'Skill name cannot be empty';
  } else {
    // insert unique skill
    $stmt = $conn->prepare("INSERT IGNORE INTO skills_catalog (skill_name, is_active) VALUES (?,1)");
    $stmt->bind_param("s", $newSkill);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $ok = 'Skill added successfully';
    } else {
      $err = 'Skill already exists';
    }
    $stmt->close();
  }
}

// Toggle active/inactive
if (isset($_GET['toggle'])) {
  $id = (int)$_GET['toggle'];
  $conn->query("UPDATE skills_catalog SET is_active = IF(is_active=1,0,1) WHERE id=$id");
  header("Location: admin_skills.php"); exit;
}

// Delete skill (optional)
// Delete skill (even if running/used by technicians)
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];

  // get skill_name first
  $stmt = $conn->prepare("SELECT skill_name FROM skills_catalog WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->bind_result($skillName);
  $stmt->fetch();
  $stmt->close();

  if ($skillName) {
    // remove from technician_skills if exists
    if ($conn->query("SHOW TABLES LIKE 'technician_skills'")->num_rows) {
      $stmt = $conn->prepare("DELETE FROM technician_skills WHERE skill_name=?");
      $stmt->bind_param("s", $skillName);
      $stmt->execute();
      $stmt->close();
    }

    // remove from technician_skill_rates if exists
    if ($conn->query("SHOW TABLES LIKE 'technician_skill_rates'")->num_rows) {
      $stmt = $conn->prepare("DELETE FROM technician_skill_rates WHERE skill_name=?");
      $stmt->bind_param("s", $skillName);
      $stmt->execute();
      $stmt->close();
    }

    // finally remove from catalog
    $stmt = $conn->prepare("DELETE FROM skills_catalog WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
  }

  header("Location: admin_skills.php"); exit;
}


$skills = [];
$res = $conn->query("SELECT id, skill_name, is_active FROM skills_catalog ORDER BY skill_name ASC");
while ($r = $res->fetch_assoc()) $skills[] = $r;
$res->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Skills</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap{max-width:900px;margin:18px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
    .row{display:flex;gap:10px;align-items:center}
    .input{height:44px;border:1px solid #dbe3ef;border-radius:10px;padding:0 12px;flex:1}
    .btn{padding:10px 16px;border-radius:10px;border:none;background:#4f6cff;color:#fff;font-weight:700;cursor:pointer}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left}
    .pill{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700}
    .active{background:#dcfce7;color:#166534}
    .inactive{background:#fee2e2;color:#991b1b}
    .link{color:#2563eb;text-decoration:none;font-weight:700}
    .msg{margin:10px 0;font-weight:700}
    .msg.ok{color:#16a34a}.msg.err{color:#dc2626}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h2 style="margin:0">Skill Catalog</h2>
        <a class="link" href="admin.php">← Back to Dashboard</a>
      </div>

      <?php if($ok): ?><div class="msg ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
      <?php if($err): ?><div class="msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <form method="post" class="row" style="margin-top:10px;">
        <input class="input" name="skill_name" placeholder="Enter new skill name (e.g. Solar Panel Repair)">
        <button class="btn" type="submit">Add Skill</button>
      </form>
    </div>

    <div class="card" style="margin-top:14px;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Skill</th>
            <th>Status</th>
            <th style="width:200px">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!count($skills)): ?>
            <tr><td colspan="4">No skills added yet.</td></tr>
          <?php else: foreach($skills as $i=>$s): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($s['skill_name']) ?></td>
              <td>
                <?php if((int)$s['is_active']===1): ?>
                  <span class="pill active">Active</span>
                <?php else: ?>
                  <span class="pill inactive">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="link" href="admin_skills.php?toggle=<?= (int)$s['id'] ?>">
                  <?= (int)$s['is_active']===1 ? 'Deactivate' : 'Activate' ?>
                </a>
                &nbsp;|&nbsp;
                <a class="link" style="color:#dc2626" href="admin_skills.php?del=<?= (int)$s['id'] ?>"
                   onclick="return confirm('Delete this skill?');">Delete</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
