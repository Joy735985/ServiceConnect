<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // make mysqli throw

$servername = "127.0.0.1";  // ✅ localhost → 127.0.0.1
$username   = "root";
$password   = "";
$database   = "my_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  die("❌ Connection failed: " . $conn->connect_error);
}

//echo "✅ Database connected successfully!";
?>
