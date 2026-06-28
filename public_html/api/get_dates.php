<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// ⭐ چک دستی session (بدون requireLogin برای API)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['dates' => [], 'has_more' => false, 'error' => 'not_logged_in']);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 100;
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

if ($branch_id <= 0) {
    echo json_encode(['dates' => [], 'has_more' => false, 'error' => 'invalid_branch']);
    exit;
}

// ⭐ Prepared Statement
$stmt = mysqli_prepare($conn, "SELECT report_date FROM daily_reports WHERE user_id = ? ORDER BY report_date DESC");
mysqli_stmt_bind_param($stmt, "i", $branch_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$dates = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dates[] = $row['report_date'];
}

echo json_encode([
    'dates' => $dates,
    'has_more' => false,
    'count' => count($dates)
]);

exit();