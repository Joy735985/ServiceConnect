<?php
// order_details.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

// Only Customer/Admin can view this page
if (function_exists('requireRole')) {
    requireRole(['Customer', 'Admin']);
} elseif (!isset($_SESSION['user']['id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$customerId = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0);
if ($customerId <= 0) {
    header("Location: login.php");
    exit;
}

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    die('Invalid order id');
}

// Load order + technician info
$sql = "
    SELECT 
        o.*,
        COALESCE(
            NULLIF(u.name, ''),
            NULLIF(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')),' '),
            NULLIF(o.provider,''),
            CONCAT('Technician #', o.technician_id)
        ) AS tech_name
    FROM orders o
    JOIN users u ON u.id = o.technician_id
    WHERE o.id = ? AND o.customer_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $orderId, $customerId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Order not found.');
}

// Check if this order already has a rating from this customer
$stmt = $conn->prepare("
    SELECT * FROM ratings 
    WHERE order_id = ? AND customer_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $orderId, $customerId);
$stmt->execute();
$ratingRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Status pill color
$status = strtolower($order['status']);
$statusColor = '#6366f1'; // default (purple)
if ($status === 'pending') {
    $statusColor = '#f97316'; // orange
} elseif ($status === 'accepted') {
    $statusColor = '#0ea5e9'; // sky
} elseif ($status === 'completed') {
    $statusColor = '#22c55e'; // green
} elseif ($status === 'cancelled') {
    $statusColor = '#ef4444'; // red
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order details</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">

    <style>
        /* make layout full-screen & colorful */
        body.layout{
            /* This background gradient style is from the original order_details.php, but it doesn't match the radial-gradient in customer.php */
            background: radial-gradient(circle at top left,#e0f2fe 0,#eef0fb 42%,#fce7f3 100%);
        }
        .app{
            grid-template-columns:260px 1fr;
            min-height:100vh;
            width:100%;
        }
        /* START: Sidebar styles updated to match customer.php */
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
        /* END: Sidebar styles updated to match customer.php */

        .main{
            width:100%;
            max-width:100%;
            padding:28px 40px 32px;
        }
        @media (max-width:900px){
            .main{
                padding:22px 18px 24px;
            }
        }

        .topbar{
            margin-bottom:8px;
        }
        .breadcrumb{
            font-size:13px;
            color:#8b8bb0;
        }

        /* hero header */
        .hero-banner{
            background: linear-gradient(120deg,#6366f1 0%,#a855f7 50%,#ec4899 100%);
            border-radius:22px;
            padding:18px 20px;
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            box-shadow:0 14px 35px rgba(99,102,241,.35);
            width:100%;
        }
        .hero-left h1{
            font-size:22px;
            margin:0 0 4px;
        }
        .hero-left p{
            margin:0;
            font-size:13px;
            opacity:.9;
        }
        .hero-pill{
            display:inline-flex;
            align-items:center;
            gap:8px;
            background:rgba(15,23,42,.18);
            padding:6px 12px;
            border-radius:999px;
            font-size:12px;
            margin-top:10px;
        }
        .hero-pill span:first-child{
            width:22px;
            height:22px;
            border-radius:999px;
            display:grid;
            place-items:center;
            background:#fff;
            color:#4f46e5;
            font-size:13px;
        }
        .hero-right{
            text-align:right;
            font-size:13px;
        }
        .hero-right strong{
            display:block;
            font-size:18px;
        }

        @media (max-width:960px){
            .hero-banner{
                flex-direction:column;
                align-items:flex-start;
            }
            .hero-right{
                text-align:left;
            }
        }

        /* main two-column layout */
        .order-layout{
            display:grid;
            grid-template-columns:1.8fr 1.2fr;
            gap:18px;
            margin-top:18px;
            width:100%;
        }
        @media (max-width:960px){
            .order-layout{
                grid-template-columns:1fr;
            }
        }

        .order-card{
            background:#ffffff;
            border-radius:20px;
            padding:22px 24px 18px;
            box-shadow:0 10px 26px rgba(15,23,42,0.06);
            border:1px solid rgba(148,163,184,.35);
            width:100%;
        }
        .order-card + .order-card{
            margin-top:14px;
        }

        .order-header-row{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            margin-bottom:10px;
        }
        .order-header-row h2{
            margin:0;
            font-size:20px;
        }

        .status-pill{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 12px;
            border-radius:999px;
            font-size:12px;
            color:#fff;
            font-weight:500;
            box-shadow:0 6px 16px rgba(15,23,42,.25);
        }
        .status-pill .dot{
            width:500px;
            height:8px;
            border-radius:999px;
            background:#fff;
            opacity:.95;
        }

        .meta-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:10px 20px;
            margin-top:6px;
            font-size:14px;
            width:100%;
        }
        @media (max-width:750px){
            .meta-grid{
                grid-template-columns:1fr;
            }
        }
        .meta-label{
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:#9ca3af;
            margin-bottom:2px;
        }
        .meta-value{
            color:#111827;
        }

        .chip{
            display:inline-flex;
            align-items:center;
            gap:6px;
            font-size:11px;
            padding:4px 9px;
            border-radius:999px;
            background:#eef2ff;
            color:#4f46e5;
        }

        .notes-title{
            font-size:14px;
            font-weight:600;
            margin-bottom:4px;
        }
        .notes-text{
            font-size:14px;
            color:#4b5563;
            line-height:1.5;
        }
        .notes-empty{
            font-size:13px;
            color:#9ca3af;
        }
        .notes-divider{
            margin:12px 0;
            border:none;
            border-top:1px dashed #e5e7eb;
        }

        .card-section-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            margin-bottom:8px;
        }
        .card-section-header h3{
            margin:0;
            font-size:16px;
        }
        .muted{
            font-size:13px;
            color:#6b7280;
        }

        .rating-stars-preview{
            font-size:18px;
            margin:4px 0;
        }

        .input{
            border-radius:12px;
        }

        .btn.primary{
            border-radius:999px;
            padding:10px 20px;
            font-weight:600;
            box-shadow:0 10px 24px rgba(99,102,241,.35);
            transition:transform .1s ease, box-shadow .1s ease, filter .1s ease;
        }
        .btn.primary:hover{
            transform:translateY(-1px);
            filter:brightness(1.05);
            box-shadow:0 12px 30px rgba(99,102,241,.45);
        }

        .btn.back-link{
            background:#ffffff;
            border-radius:999px;
            padding:8px 14px;
            border:1px solid rgba(148,163,184,.6);
            margin-top:20px;
            display:inline-flex;
            align-items:center;
            gap:6px;
            font-size:14px;
        }
        .btn.back-link:hover{
            background:#eef2ff;
        }
        .btn.back-link span{
            font-size:12px;
        }
    </style>
</head>
<body class="layout">
<div class="app">
    <aside class="sidebar">
        <div class="brand"><span class="dot">S</span> ServiceConnect</div>
        <nav class="nav">
            <a href="customer.php">Dashboard</a>
            <a href="search_service.php">Find Service</a>
            <a href="orders.php" class="active">My Orders</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="breadcrumb">
                Home / My Orders / #<?= (int)$order['id'] ?>
            </div>
        </div>

        <section class="hero-banner">
            <div class="hero-left">
                <h1>Order #<?= (int)$order['id'] ?></h1>
                <p>Track your service, see notes, and share your experience with the technician.</p>
                <div class="hero-pill">
                    <span>🔧</span>
                    <span><?= h($order['service_name']) ?></span>
                </div>
            </div>
            <div class="hero-right">
                <span class="muted">Scheduled for</span>
                <strong><?= h(date('d M Y, h:i A', strtotime($order['date_time']))) ?></strong>
                <span class="muted">
                    with <strong><?= h($order['tech_name']) ?></strong>
                </span>
            </div>
        </section>

        <div class="order-layout">

            <section class="order-card">
                <div class="order-header-row">
                    <div>
                        <span class="chip">
                            <span>📋</span> Order overview
                        </span>
                    </div>
                    <div class="status-pill" style="background:<?= $statusColor ?>;">
                        <span class="dot"></span>
                        <?= h($order['status']) ?>
                    </div>
                </div>

                <div class="meta-grid">
                    <div>
                        <div class="meta-label">Service</div>
                        <div class="meta-value"><?= h($order['service_name']) ?></div>
                    </div>
                    <div>
                        <div class="meta-label">Technician</div>
                        <div class="meta-value"><?= h($order['tech_name']) ?></div>
                    </div>
                    <div>
                        <div class="meta-label">Date &amp; time</div>
                        <div class="meta-value"><?= h(date('d M Y, h:i A', strtotime($order['date_time']))) ?></div>
                    </div>
                    <div>
                        <div class="meta-label">Booking ID</div>
                        <div class="meta-value">#<?= (int)$order['id'] ?></div>
                    </div>
                </div>
            </section>

            <section class="order-card">
                <div class="card-section-header">
                    <h3>Notes & updates</h3>
                </div>

                <?php if (!empty($order['notes'])): ?>
                    <p class="notes-title">Your notes</p>
                    <p class="notes-text"><?= nl2br(h($order['notes'])) ?></p>
                <?php else: ?>
                    <p class="notes-empty">You didn’t add any notes for this booking.</p>
                <?php endif; ?>

                <?php if (!empty($order['technician_comment'])): ?>
                    <hr class="notes-divider">
                    <p class="notes-title">Technician comment</p>
                    <p class="notes-text"><?= nl2br(h($order['technician_comment'])) ?></p>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($order['status'] !== 'Completed'): ?>

            <section class="order-card" style="margin-top:16px;">
                <div class="card-section-header">
                    <h3>Rating locked</h3>
                </div>
                <p class="muted">
                    You’ll be able to rate this technician once the work is marked
                    <strong>Completed</strong> by the provider.
                </p>
            </section>

        <?php elseif ($ratingRow): ?>

            <section class="order-card" style="margin-top:16px;">
                <div class="card-section-header">
                    <h3>Your rating</h3>
                    <span class="muted">Thank you for sharing your experience 💜</span>
                </div>

                <p class="rating-stars-preview">
                    <strong><?= (int)$ratingRow['rating'] ?></strong> / 5 ⭐
                </p>

                <?php if (!empty($ratingRow['comment'])): ?>
                    <p class="notes-title" style="margin-top:10px;">Comment</p>
                    <p class="notes-text"><?= nl2br(h($ratingRow['comment'])) ?></p>
                <?php else: ?>
                    <p class="notes-empty">You didn’t write a comment for this rating.</p>
                <?php endif; ?>
            </section>

        <?php else: ?>

            <section class="order-card" style="margin-top:16px;">
                <div class="card-section-header">
                    <h3>Rate this technician</h3>
                    <span class="muted">Your feedback helps us improve every visit.</span>
                </div>

                <form action="submit_rating.php" method="post" style="margin-top:6px;">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <input type="hidden" name="technician_id" value="<?= (int)$order['technician_id'] ?>">

                    <label class="meta-label" style="margin-bottom:4px;">Rating (1–5)</label>
                    <select name="rating" class="input" required>
                        <option value="">Select rating</option>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Good</option>
                        <option value="3">3 - Average</option>
                        <option value="2">2 - Poor</option>
                        <option value="1">1 - Very poor</option>
                    </select>

                    <label class="meta-label" style="margin-top:12px;margin-bottom:4px;">Comment (optional)</label>
                    <textarea name="comment" class="input" rows="3" placeholder="Write your feedback..."></textarea>

                    <button type="submit" class="btn primary" style="margin-top:14px;">
                        Submit rating
                    </button>
                </form>
            </section>

        <?php endif; ?>

        <a href="orders.php" class="btn back-link">
            <span>←</span> Back to orders
        </a>
    </main>
</div>
</body>
</html>