<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
  return isset($_SESSION['user']);
}

function requireLogin() {
  if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
  }
}

function requireRole($roles = []) {
  requireLogin();
  $role = $_SESSION['user']['role'] ?? '';
  if (!in_array($role, $roles)) {
    echo "Access Denied!";
    exit;
  }
}

function esc($str) {
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
