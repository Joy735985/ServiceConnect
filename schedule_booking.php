<?php
session_start();
require_once 'db.php';

// Check user authentication
if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$customer_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'];

// Required inputs
$tech_id = isset($_GET['tech_id']) ? (int)$_GET['tech_id'] : 0;
$service = isset($_GET['service']) ? trim((string)$_GET['service']) : '';
$selected_date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d'); // Use date from search

if ($tech_id <= 0 || $service === '') {
  header("Location: search_service.php");
  exit;
}

/* -------------------------------------------------
   ✅ ONLY CHANGE: BLOCK TODAY + PAST DATES
   ------------------------------------------------- */
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

if ($selected_date < $tomorrow) {
    $selected_date = $tomorrow;
}
/* ------------------------------------------------- */

// 1. DEFINE SLOTS
// Working period: 10:00 AM to 8:00 PM (5 slots, 2 hours each)
$all_slots = [
    '10:00:00', // 10:00 AM - 12:00 PM
    '12:00:00', // 12:00 PM - 02:00 PM
    '14:00:00', // 02:00 PM - 04:00 PM
    '16:00:00', // 04:00 PM - 06:00 PM
    '18:00:00'  // 06:00 PM - 08:00 PM
];

// 2. CHECK OCCUPIED SLOTS for the selected date
$occupied_slots = [];

$stmt = $conn->prepare("
    SELECT TIME(date_time) AS slot_time
    FROM orders
    WHERE technician_id = ?
    AND DATE(date_time) = ?
    AND status IN ('Accepted', 'Pending')
");
$stmt->bind_param("is", $tech_id, $selected_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    // Only capture the hour/minute/second part for comparison
    $occupied_slots[] = $row['slot_time'];
}
$stmt->close();

// 3. DETERMINE FREE SLOTS
$free_slots = [];
foreach ($all_slots as $slot) {
    if (!in_array($slot, $occupied_slots)) {
        $free_slots[$slot] = date('h:i A', strtotime($slot));
    }
}


// Fetch technician basic info (existing logic)
$tech = [
  'name' => 'Technician',
  'profile_image' => 'assets/default-avatar.png'
];

$stmt = $conn->prepare("SELECT first_name, last_name, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  $tech['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $tech['name'];
  $tech['profile_image'] = $row['profile_image'] ?: $tech['profile_image'];
}
$stmt->close();

// Fetch per-skill rent (if any) (existing logic)
$rate = ['amount' => null, 'unit' => 'per job', 'currency' => 'BDT'];
$stmt = $conn->prepare("
  SELECT amount, unit, currency
  FROM technician_skill_rates
  WHERE technician_id = ? AND skill_name = ?
  LIMIT 1
");
$stmt->bind_param("is", $tech_id, $service);
$stmt->execute();
$res = $stmt->get_result();
if ($r = $res->fetch_assoc()) {
  $rate['amount']   = $r['amount'];
  $rate['unit']     = $r['unit'] ?: 'per job';
  $rate['currency'] = $r['currency'] ?: 'BDT';
}
$stmt->close();

// Optional average rating (existing logic)
$rating = null;
if ($conn->query("SHOW TABLES LIKE 'ratings'")->num_rows) {
  $stmt = $conn->prepare("SELECT ROUND(AVG(rating),1) AS rating FROM ratings WHERE technician_id = ?");
  $stmt->bind_param("i", $tech_id);
  $stmt->execute();
  $stmt->bind_result($rating);
  $stmt->fetch();
  $stmt->close();
}
if (!$rating) $rating = 5.0;

// Min date defaults (existing logic unchanged)
$today = date('Y-m-d');
$is_today = ($selected_date === $today);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Schedule Service</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:'Poppins',sans-serif;background:#f7f8fc;margin:0;padding:40px;}
    .container{max-width:720px;margin:auto;}
    .card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 6px 20px rgba(0,0,0,.06);}
    .header{display:flex;align-items:center;gap:14px;margin-bottom:16px}
    .header img{width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid #ddd}
    .muted{color:#666}
    .pill{display:inline-block;background:#ecebff;color:#3a2fd6;border-radius:999px;padding:4px 10px;font-weight:600;font-size:12px;margin-top:6px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width:640px){.row{grid-template-columns:1fr}}
    input[type="date"],input[type="time"],textarea, select{
      width:100%;padding:12px;border-radius:12px;border:1px solid #ddd;font-size:14px;
      box-shadow:0 2px 6px rgba(0,0,0,.04);outline:0;background:#fff
    }
    .btn{background:#6f60ff;color:#fff;padding:10px 16px;border:0;border-radius:10px;cursor:pointer;font-weight:600;text-decoration:none}
    .btn:hover{background:#5c4ee5}
    .btn-ghost{background:#eee;color:#333}
    .meta{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
    .chip{background:#f7f7ff;border:1px solid #e5e7ff;border-radius:999px;padding:6px 10px;font-weight:600;font-size:13px}
    .bar{height:1px;background:#eee;margin:16px 0}
    .actions{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px}
    .time-slot-display { padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 12px; font-size: 14px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="header">
        <img src="<?= htmlspecialchars($tech['profile_image']) ?>" alt="Technician">
        <div>
          <h2 style="margin:0"><?= htmlspecialchars($tech['name']) ?></h2>
          <div class="meta">
            <span class="chip">Service: <?= htmlspecialchars($service) ?></span>
            <span class="chip">Rating: ⭐ <?= htmlspecialchars($rating) ?></span>
            <?php if ($rate['amount'] !== null): ?>
              <span class="chip">Rate: <?= htmlspecialchars($rate['currency']) ?> <?= htmlspecialchars(number_format((float)$rate['amount'], 2)) ?> / <?= htmlspecialchars($rate['unit']) ?></span>
            <?php else: ?>
              <span class="chip">Rate: Not set</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="bar"></div>
      <p style="font-weight:700; color:#4a4a4a;">
        Selected Date: <?= date('M d, Y', strtotime($selected_date)) ?>
      </p>

      <form action="confirm_booking.php" method="POST">
        <input type="hidden" name="tech_id" value="<?= (int)$tech_id ?>">
        <input type="hidden" name="service" value="<?= htmlspecialchars($service, ENT_QUOTES) ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">

        <div class="row">
          <div style="grid-column: 1 / -1;">
            <label for="time" class="muted">Choose Time Slot (2-hour slot)</label>
            <?php if (empty($free_slots)): ?>
                <div class="time-slot-display" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;">
                    ❌ No free slots available on this date.
                </div>
                <input type="hidden" name="time" value="">
            <?php else: ?>
                <select id="time" name="time" required>
                    <option value="">Select a free slot</option>
                    <?php foreach ($free_slots as $slot_raw => $slot_formatted): ?>
                        <option value="<?= htmlspecialchars($slot_raw) ?>">
                            <?= $slot_formatted ?> - <?= date('h:i A', strtotime($slot_raw . ' + 2 hours')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:12px">
          <label for="notes" class="muted">Notes (optional)</label>
          <textarea id="notes" name="notes" rows="3" placeholder="Describe the issue or add access notes…"></textarea>
        </div>

        <div class="actions">
          <a href="search_service.php?service=<?= urlencode($service) ?>&service_date=<?= urlencode($selected_date) ?>" class="btn btn-ghost">← Change Date/Tech</a>
          <?php if (!empty($free_slots)): ?>
            <button type="submit" class="btn">Confirm & Book</button>
          <?php else: ?>
             <button type="button" class="btn" disabled style="background:#ccc;">Confirm & Book (No Slots)</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
