<?php
// customer_edit_profile.php — Edit Profile + Change Password (fixed session role bug)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// FIX: Start session only if one isn't already active (prevents the Notice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------- SAFEGUARD ROLE (prevents "Access Denied" after update) --------
if (!isset($_SESSION['user']['role'])) {
    $_SESSION['user']['role'] = 'Customer';
}

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/functions.php')) require_once __DIR__ . '/functions.php';

// role guard
if (function_exists('requireRole')) {
  requireRole(['Customer','Admin']);
} else {
  if (empty($_SESSION['user']) || !in_array(($_SESSION['user']['role'] ?? ''), ['Customer','Admin'], true)) {
    die('Not logged in or insufficient role.');
  }
}

$me = $_SESSION['user'];
$customerId = (int)($me['id'] ?? 0);
if (!$customerId) die('Missing customer id in session.');

if (!isset($conn) || !($conn instanceof mysqli)) {
  die('Database connection $conn not set. Check db.php');
}

// Helpers
function hasColumn(mysqli $conn, $table, $col) {
  $t = $conn->real_escape_string($table);
  $c = $conn->real_escape_string($col);
  $rs = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  $ok = $rs && $rs->num_rows > 0;
  if ($rs) $rs->close();
  return $ok;
}

// Which optional columns exist?
$hasPhone   = hasColumn($conn, 'users', 'phone');
$hasAddress = hasColumn($conn, 'users', 'address');

// Load existing profile
$selectCols = array('first_name','last_name','profile_image','email');
if ($hasPhone)   $selectCols[] = 'phone';
if ($hasAddress) $selectCols[] = 'address';
$colsSql = implode(',', array_map(function($c) { return "`$c`"; }, $selectCols));

$stmt = $conn->prepare("SELECT $colsSql FROM users WHERE id=?");
if (!$stmt) die('Prepare failed (load): '.$conn->error);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  $user = array('first_name'=>'','last_name'=>'','profile_image'=>'','email'=>'');
  if ($hasPhone)   $user['phone']   = '';
  if ($hasAddress) $user['address'] = '';
}

$errors = array();
$pwErrors = array();
$pwSuccess = null;

// ---------- Change Password Handler ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='change_password') {
  $current = trim($_POST['current_password'] ?? '');
  $new     = trim($_POST['new_password'] ?? '');
  $confirm = trim($_POST['confirm_password'] ?? '');

  if ($current === '' || $new === '' || $confirm === '') {
    $pwErrors[] = 'Please fill in all fields.';
  }
  if ($new !== $confirm) {
    $pwErrors[] = 'New password and confirmation do not match.';
  }
  if (strlen($new) < 8) {
    $pwErrors[] = 'New password must be at least 8 characters.';
  }

  if (!$pwErrors) {
    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    if (!$stmt) {
      $pwErrors[] = 'Prepare failed: '.$conn->error;
    } else {
      $stmt->bind_param("i", $customerId);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();

      if (!$row) {
        $pwErrors[] = 'User not found.';
      } else {
        $stored = (string)$row['password'];
        $isBcrypt = (strpos($stored, '$2y$') === 0) || (strpos($stored, '$2a$') === 0);

        $ok = $isBcrypt ? password_verify($current, $stored) : ($current === $stored);
        if (!$ok) {
          $pwErrors[] = 'Current password is incorrect.';
        } else {
          $newHash = password_hash($new, PASSWORD_BCRYPT);
          $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
          if (!$stmt2) {
            $pwErrors[] = 'Prepare failed: '.$conn->error;
          } else {
            $stmt2->bind_param("si", $newHash, $customerId);
            if ($stmt2->execute()) {
              $pwSuccess = 'Password updated successfully.';
            } else {
              $pwErrors[] = 'Failed to update password: '.$stmt2->error;
            }
            $stmt2->close();
          }
        }
      }
    }
  }
}

// ---------- Profile Update Handler ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'change_password')) {
  $first = trim($_POST['first_name'] ?? ($user['first_name'] ?? ''));
  $last  = trim($_POST['last_name']  ?? ($user['last_name']  ?? ''));
  $phone = $hasPhone   ? trim($_POST['phone']   ?? ($user['phone']   ?? '')) : null;
  $addr  = $hasAddress ? trim($_POST['address'] ?? ($user['address'] ?? '')) : null;

  $imgPath = $user['profile_image'] ?? '';

  if (!empty($_FILES['profile_image']['name'])) {
    $f = $_FILES['profile_image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $mime = @mime_content_type($f['tmp_name']);
      $allowed = array('image/jpeg','image/png','image/webp');
      if (!in_array($mime, $allowed, true)) {
        $errors[] = 'Only JPG/PNG/WEBP allowed.';
      } elseif ($f['size'] > 2*1024*1024) {
        $errors[] = 'Max 2MB.';
      } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg','jpeg','png','webp'), true)) {
          $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        }
        $dir = __DIR__ . '/uploads/customer_avatars';
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        $fname = 'cust_'.$customerId.'_'.time().'.'.$ext;
        $abs = $dir.'/'.$fname;
        $rel = 'uploads/customer_avatars/'.$fname;
        if (move_uploaded_file($f['tmp_name'], $abs)) {
          $imgPath = $rel;
        } else {
          $errors[] = 'Failed to move uploaded file.';
        }
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Upload error code: '.$f['error'];
    }
  }

  if (!$errors) {
    $set = array('first_name = ?', 'last_name = ?', 'profile_image = ?');
    $types = 'sss';
    $values = array(&$first, &$last, &$imgPath);

    if ($hasPhone)   { $set[] = 'phone = ?';   $types .= 's'; $values[] = &$phone; }
    if ($hasAddress) { $set[] = 'address = ?'; $types .= 's'; $values[] = &$addr;  }

    $setSql = implode(', ', $set);
    $sql = "UPDATE users SET $setSql WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      $errors[] = 'Prepare failed (update): '.$conn->error;
    } else {
      $types .= 'i';
      $values[] = &$customerId;

      $bindParams = array_merge(array($types), $values);
      $ref = array();
      foreach ($bindParams as $k => $v) { $ref[$k] = &$bindParams[$k]; }
      call_user_func_array(array($stmt, 'bind_param'), $ref);

      if ($stmt->execute()) {
        $_SESSION['user']['first_name'] = $first;
        $_SESSION['user']['last_name']  = $last;
        $_SESSION['user']['profile_image'] = $imgPath; // Update session image path
        if ($hasPhone) $_SESSION['user']['phone'] = $phone;
        
        // ✅ FIX: Keep role after update so dashboard works
        if (empty($_SESSION['user']['role'])) {
            $_SESSION['user']['role'] = 'Customer';
        }

        header('Location: customer_edit_profile.php?updated=1');
        exit;
      } else {
        $errors[] = 'Update failed: '.$stmt->error;
      }
      $stmt->close();
    }
  }
}

// Re-fetch after redirect
if (isset($_GET['updated'])) {
  $stmt = $conn->prepare("SELECT $colsSql FROM users WHERE id=?");
  if ($stmt) {
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: $user;
    $stmt->close();
  }
}

$avatarUrl = !empty($user['profile_image']) ? htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8')
                                            : 'https://via.placeholder.com/96';
                                            
// Logic for the optional "Switch Profile" link
$switchProfileLink = '';
$currentId = $me['id'] ?? null;
if ($currentId && isset($conn)) {
    $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    
    // Check if curEmail is already in $me, otherwise query (use $user array if possible)
    $curEmail = $me['email'] ?? ($user['email'] ?? null);

    if ($curEmail) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND LOWER(role) = 'technician' LIMIT 1");
        $stmt->bind_param("s", $curEmail);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
          $switchProfileLink = '<a href="' . $BASE . 'switch_profile.php?to=technician" class="sidebar-link">🔁 Switch to Technician Profile</a>';
        } else {
          $switchProfileLink = '<a href="' . $BASE . 'signup.php?role=technician" class="sidebar-link">➕ Create Technician Account</a>';
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    /* =========================================================
       INCLUDED STYLES FROM customer.php FOR UI CONSISTENCY
       ========================================================= */
    .profile-card{max-width:760px;margin:0 auto}
    .avatar-lg{width:96px;height:96px;border-radius:50%;object-fit:cover;background:#eee}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .input{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
    .submit{margin-top:12px;padding:10px 14px;border-radius:10px;border:0;background:#111;color:#fff;cursor:pointer}
    .note{font-size:12px;color:#666}
    @media (max-width:720px){.form-row{grid-template-columns:1fr}}
    
    /* CUSTOMER DASHBOARD COLORFUL UI */
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

    .toprow{ /* Using this for the header area */
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
      background: #fff;
      border-radius:14px;
      box-shadow: 0 12px 28px rgba(15,23,42,.08);
      overflow:hidden;
      border:1px solid rgba(15,23,42,.06);
      padding:20px; /* Added padding to cards */
    }
    
    .profile-card{
        max-width: none; /* remove width limit for better flow in new UI */
        margin: 0;
    }
    
    /* Submit button specific style for the new theme */
    .submit{
        background: linear-gradient(90deg, #0ea5e9, #6366f1);
        color:#fff;
        font-weight: 700;
        transition: .25s ease;
        box-shadow: 0 6px 16px rgba(14,165,233,.35);
    }
    .submit:hover{
        opacity: 0.9;
        box-shadow: 0 8px 20px rgba(14,165,233,.45);
    }
    
    @media (max-width: 768px){
      .app{padding:10px;gap:10px;}
      .sidebar{height:auto;position:relative;}
      .toprow h2{font-size:22px;}
    }
    
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><span class="dot">S</span> ServiceConnect</div>
    <nav class="nav">
      <a href="customer.php">Dashboard</a>
      <a href="search_service.php">Find Service</a>
      <a href="customer_edit_profile.php" class="active">Edit Profile</a>
      <a href="orders.php">My Orders</a>
      <a href="logout.php">Logout</a>
      <?= $switchProfileLink ?>
    </nav>
  </aside>

  <main class="main">
    
    <div class="toprow">
      <h2>Edit Profile</h2>
      <div class="pill">Settings</div>
    </div>
    
    <div class="card profile-card">
      <h3 style="margin-top:0">Update Information</h3>

      <?php if (!empty($_GET['updated'])): ?>
        <p style="background:#e7f6ee;color:#0a6b3e;padding:10px;border-radius:10px">Profile updated successfully.</p>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div style="background:#fff3f2;color:#9c1c10;padding:10px;border-radius:10px">
          <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" style="margin-top:12px">
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:20px;">
          <img class="avatar-lg" src="<?= $avatarUrl ?>" alt="avatar">
          <div>
            <label>Change profile photo</label><br>
            <input type="file" name="profile_image" accept="image/*">
            <div class="note">JPG/PNG/WEBP • Max 2MB</div>
          </div>
        </div>

        <div class="form-row" style="margin-top:14px">
          <div>
            <label>First name</label>
            <input class="input" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
          </div>
          <div>
            <label>Last name</label>
            <input class="input" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
          </div>
        </div>

        <?php if ($hasPhone || $hasAddress): ?>
        <div class="form-row" style="margin-top:12px">
          <?php if ($hasPhone): ?>
          <div>
            <label>Phone</label>
            <input class="input" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
          </div>
          <?php endif; ?>
          <?php if ($hasAddress): ?>
          <div>
            <label>Address</label>
            <input class="input" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <button class="submit" type="submit">Save changes</button>
      </form>
    </div>

    <div class="card profile-card" style="margin-top:22px">
      <h3 style="margin-top:0">Change password</h3>

      <?php if (!empty($pwSuccess)): ?>
        <p style="background:#e7f6ee;color:#0a6b3e;padding:10px;border-radius:10px">
          <?= htmlspecialchars($pwSuccess) ?>
        </p>
      <?php endif; ?>

      <?php if (!empty($pwErrors)): ?>
        <div style="background:#fff3f2;color:#9c1c10;padding:10px;border-radius:10px">
          <?php foreach ($pwErrors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off" style="margin-top:12px">
        <input type="hidden" name="action" value="change_password">
        <div class="form-row">
          <div>
            <label>Current password</label>
            <input class="input" type="password" name="current_password" required>
          </div>
          <div>
            <label>New password</label>
            <input class="input" type="password" name="new_password" minlength="8" required>
          </div>
        </div>
        <div class="form-row" style="margin-top:12px">
          <div>
            <label>Confirm new password</label>
            <input class="input" type="password" name="confirm_password" minlength="8" required>
          </div>
        </div>
        <button class="submit" type="submit" style="margin-top:12px">Update password</button>
      </form>
    </div>
  </main>
</div>
</body>
</html>