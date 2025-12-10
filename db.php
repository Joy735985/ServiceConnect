<?php
// db.php - Render/Docker/Local compatible DB connector

date_default_timezone_set('Asia/Dhaka');

// Read from environment variables first (Render / Docker)
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$port = getenv('DB_PORT'); // optional

// Fallback to local defaults if env not set (XAMPP / localhost)
if (!$host) $host = "localhost";
if (!$db)   $db   = "my_db";     // ✅ তোমার লোকাল DB নাম এখানে সেট করা হলো
if (!$user) $user = "root";
if ($pass === false) $pass = "";
if (!$port) $port = 3306;

// Create connection
$conn = new mysqli($host, $user, $pass, $db, (int)$port);

// Connection check
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to support Bangla/emoji/etc.
$conn->set_charset("utf8mb4");

// Optional: strict mode for better data integrity
$conn->query("SET sql_mode = 'STRICT_ALL_TABLES'");
?>
