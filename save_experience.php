<?php
// save_experience.php
session_start();
require_once 'db.php';

if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$tech_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['experience'])) {
    foreach ($_POST['experience'] as $rowId => $years) {
        $rowId = (int)$rowId;
        $years = max(0, (int)$years);

        $stmt = $conn->prepare("
            UPDATE technician_skill_rates
            SET experience_years = ?
            WHERE id = ? AND technician_id = ?
        ");
        $stmt->bind_param("iii", $years, $rowId, $tech_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: tech_experience.php?saved=1");
exit;
