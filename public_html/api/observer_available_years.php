<?php
// api/observer_available_years.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'observer') {
    echo json_encode(['years' => []]);
    exit;
}

$observer_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT u.id FROM users u JOIN observer_assignments oa ON u.id = oa.branch_id WHERE oa.observer_id = ? AND u.role = 'branch'");
mysqli_stmt_bind_param($stmt, "i", $observer_id);
mysqli_stmt_execute($stmt);
$res_b = mysqli_stmt_get_result($stmt);
$branch_ids = [];
while ($b = mysqli_fetch_assoc($res_b)) $branch_ids[] = $b['id'];
mysqli_stmt_close($stmt);

if (empty($branch_ids)) { echo json_encode(['years' => []]); exit; }

$placeholders = implode(',', array_fill(0, count($branch_ids), '?'));
$types = str_repeat('i', count($branch_ids));

$years = [];

$stmt = mysqli_prepare($conn, "SELECT DISTINCT report_date FROM daily_reports WHERE user_id IN ($placeholders) ORDER BY report_date");
mysqli_stmt_bind_param($stmt, $types, ...$branch_ids);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $y = intval(explode('-', $row['report_date'])[0]); if (!in_array($y, $years)) $years[] = $y; }
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT DISTINCT record_date FROM income_daily_records WHERE branch_id IN ($placeholders) ORDER BY record_date");
mysqli_stmt_bind_param($stmt, $types, ...$branch_ids);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $y = intval(explode('-', $row['record_date'])[0]); if (!in_array($y, $years)) $years[] = $y; }
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT DISTINCT record_year FROM income_monthly_records WHERE branch_id IN ($placeholders) ORDER BY record_year");
mysqli_stmt_bind_param($stmt, $types, ...$branch_ids);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) { $y = intval($row['record_year']); if (!in_array($y, $years)) $years[] = $y; }
mysqli_stmt_close($stmt);

sort($years);
echo json_encode(['years' => $years]);