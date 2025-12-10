<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$tech_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT id, skill_name, amount, unit, experience_years
    FROM technician_skill_rates
    WHERE technician_id = ?
    ORDER BY skill_name
");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Experience per skill</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="layout">
<div class="content">
  <div class="card">
    <h2>Experience per Skill</h2>
    <p class="meta">Set how many years of experience you have for each skill.</p>

    <form action="save_experience.php" method="post">
      <table class="table">
        <thead>
          <tr>
            <th>Skill</th>
            <th>Rate</th>
            <th>Experience (years)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($skills as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['skill_name']) ?></td>
              <td>
                ৳<?= number_format((float)$s['amount'],2) ?>
                <span style="font-size:12px;color:#7A7A9D;"><?= htmlspecialchars($s['unit']) ?></span>
              </td>
              <td>
                <input type="number" min="0" max="50" class="input"
                       name="experience[<?= (int)$s['id'] ?>]"
                       value="<?= (int)$s['experience_years'] ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="submit" class="btn primary" style="margin-top:10px;">Save experience</button>
    </form>
  </div>

  <a href="technician.php" class="btn" style="margin-top:16px;display:inline-block;">Back to dashboard</a>
</div>
</body>
</html>
