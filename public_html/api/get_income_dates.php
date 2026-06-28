<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['dates' => []]);
    exit();
}

$branch_id = intval($_GET['branch_id'] ?? 0);
$type = $_GET['type'] ?? 'daily';

if ($branch_id <= 0) {
    echo json_encode(['dates' => []]);
    exit();
}

$dates = [];

if ($type === 'monthly') {
    $result = mysqli_query($conn, 
        "SELECT DISTINCT CONCAT(record_year, '-', LPAD(record_month, 2, '0'), '-01') as d 
         FROM income_monthly_records 
         WHERE branch_id = $branch_id 
         ORDER BY d DESC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $dates[] = $row['d'];
    }
} else {
    $result = mysqli_query($conn, 
        "SELECT DISTINCT record_date as d 
         FROM income_daily_records 
         WHERE branch_id = $branch_id 
         ORDER BY d DESC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $dates[] = $row['d'];
    }
}

echo json_encode(['dates' => $dates]);
exit();